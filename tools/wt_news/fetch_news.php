<?php
/**
 * Собирает свежие новости War Thunder с официального сайта и патчноуты,
 * отсеивает уже опубликованные и печатает JSON-кандидатов.
 *
 * Пример:
 *   php tools/wt_news/fetch_news.php --limit=5
 *   php tools/wt_news/fetch_news.php --source=changelog --limit=3
 *   php tools/wt_news/fetch_news.php --all           # не фильтровать по posted.json
 */

require __DIR__ . '/lib.php';

$options = getopt('', ['limit::', 'lang::', 'source::', 'all', 'body-chars::']);
$limit     = max(1, (int) ($options['limit'] ?? 5));
$lang      = in_array($options['lang'] ?? 'ru', ['ru', 'en'], true) ? ($options['lang'] ?? 'ru') : 'ru';
$source    = $options['source'] ?? 'news';
$showAll   = array_key_exists('all', $options);
$bodyChars = max(200, (int) ($options['body-chars'] ?? 2500));

$state = wt_load_state();

/** Разбирает страницу-витрину (/news/ или /game/changelog/) и возвращает список анонсов. */
function wt_parse_news_list(string $html, string $lang, string $sourceLabel): array
{
    $items = [];
    $blocks = preg_split('~<div class="showcase__item~', $html);
    array_shift($blocks);

    foreach ($blocks as $block) {
        if (!preg_match('~<a class="widget__link" href="([^"]+)"~', $block, $m)) {
            continue;
        }
        $url = 'https://warthunder.com' . html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $title = '';
        if (preg_match('~<div class="widget__title">(.*?)</div>~s', $block, $m)) {
            $title = wt_clean_text($m[1]);
        }
        if ($title === '') {
            continue;
        }

        $summary = '';
        if (preg_match('~<div class="widget__comment">(.*?)</div>~s', $block, $m)) {
            $summary = wt_clean_text($m[1]);
        }

        $date = '';
        if (preg_match('~widget-meta__item--right">(.*?)</li>~s', $block, $m)) {
            $date = wt_clean_text($m[1]);
        }

        $thumb = null;
        if (preg_match('~data-src="([^"]+)"~', $block, $m)) {
            $thumb = str_starts_with($m[1], '//') ? 'https:' . $m[1] : $m[1];
        }

        $items[] = [
            'source'  => $sourceLabel,
            'lang'    => $lang,
            'url'     => $url,
            'title'   => $title,
            'summary' => $summary,
            'date'    => $date,
            'thumb'   => $thumb,
        ];
    }

    return $items;
}

/**
 * Вытаскивает текст статьи из блока `section section--narrow article`,
 * отрезая шапку сайта, блок «читайте также» и подвал.
 */
function wt_extract_article_body(string $html, int $bodyChars): string
{
    $body = '';
    $start = strpos($html, 'section section--narrow article"');
    if ($start === false) {
        $start = strpos($html, 'section section--narrow article ');
    }

    if ($start !== false) {
        $end = strpos($html, 'article-also', $start + 10);
        if ($end === false) {
            $end = strpos($html, '<footer', $start);
        }
        $tagEnd = strpos($html, '>', $start);
        $start = $tagEnd !== false ? $tagEnd + 1 : $start;
        $body = wt_clean_text(substr($html, $start, ($end !== false && $end > $start ? $end - $start : 20000)));
    }

    if ($body === '' && preg_match('~<article[^>]*>(.*?)</article>~s', $html, $m)) {
        $body = wt_clean_text($m[1]);
    }

    // Убираем служебные строки навигации, если что-то просочилось.
    $lines = array_filter(
        array_map('trim', explode("\n", $body)),
        static fn (string $line): bool => $line !== '' && $line !== '^' && mb_strlen($line) > 2
    );
    $body = trim(implode("\n", $lines));

    if (mb_strlen($body) > $bodyChars) {
        $body = mb_substr($body, 0, $bodyChars) . '…';
    }

    return $body;
}

/**
 * Собирает крупные картинки статьи — чтобы можно было выбрать не только обложку.
 */
function wt_extract_article_images(string $html, ?string $cover): array
{
    $images = [];
    if ($cover) {
        $images[] = $cover;
    }

    if (preg_match_all('~(?:src|data-src)="((?://|https://)staticfiles\.warthunder\.com/[^"]+\.(?:jpg|jpeg|png))"~i', $html, $m)) {
        foreach ($m[1] as $url) {
            $url = str_starts_with($url, '//') ? 'https:' . $url : $url;
            if (str_contains($url, '/_thumbs/') || preg_match('~(icon|logo|avatar)~i', $url)) {
                continue;
            }
            $images[] = $url;
        }
    }

    return array_values(array_slice(array_unique($images), 0, 10));
}

/** Дотягивает картинку и текст статьи. */
function wt_enrich(array $item, int $bodyChars): array
{
    $html = wt_http_get($item['url']);
    if ($html === null) {
        $item['image'] = $item['thumb'];
        $item['body']  = $item['summary'];
        $item['error'] = 'article_fetch_failed';

        return $item;
    }

    $image = wt_meta($html, 'og:image');
    if ($image === null && preg_match('~<img[^>]+src="(//staticfiles\.warthunder\.com[^"]+)"~', $html, $m)) {
        $image = 'https:' . $m[1];
    }
    $item['image'] = $image ?: $item['thumb'];

    $item['body']   = wt_extract_article_body($html, $bodyChars);
    $item['images'] = wt_extract_article_images($html, $item['image']);

    if (!isset($item['title_full'])) {
        $item['title_full'] = wt_meta($html, 'og:title') ?? $item['title'];
    }

    return $item;
}

$listUrl = $source === 'changelog'
    ? "https://warthunder.com/{$lang}/game/changelog/"
    : "https://warthunder.com/{$lang}/news/";

$listHtml = wt_http_get($listUrl);
if ($listHtml === null) {
    fwrite(STDERR, "Не удалось загрузить {$listUrl}\n");
    exit(1);
}

$sourceLabel = $source === 'changelog' ? 'warthunder.com/game/changelog' : 'warthunder.com/news';
$items = wt_parse_news_list($listHtml, $lang, $sourceLabel);

$candidates = [];
foreach ($items as $item) {
    if (!$showAll && wt_is_posted($state, $item['url'])) {
        continue;
    }
    $candidates[] = wt_enrich($item, $bodyChars);
    if (count($candidates) >= $limit) {
        break;
    }
}

echo json_encode([
    'fetched_at' => date('c'),
    'source'     => $listUrl,
    'skipped'    => count($items) - count($candidates),
    'count'      => count($candidates),
    'items'      => $candidates,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
