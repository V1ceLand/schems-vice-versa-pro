<?php
/**
 * Общие помощники для новостного бота War Thunder.
 */

const WT_UA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

/** Лимиты Discord: 2000 символов в сообщении и 10 вложений. */
const DISCORD_API          = 'https://discord.com/api/v10';
const DISCORD_MAX_CONTENT  = 2000;
const DISCORD_MAX_FILES    = 10;

function wt_state_path(): string
{
    return __DIR__ . '/posted.json';
}

/**
 * Читает .env из корня проекта, если переменных нет в окружении.
 */
function wt_env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    static $dotenv = null;
    if ($dotenv === null) {
        $dotenv = [];
        $file = dirname(__DIR__, 2) . '/.env';
        if (is_readable($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $dotenv[trim($k)] = trim($v, " \t\"'");
            }
        }
    }

    if (isset($dotenv[$key]) && $dotenv[$key] !== '') {
        return $dotenv[$key];
    }

    return $default;
}

function wt_http_get(string $url, int $timeout = 30): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_USERAGENT      => WT_UA,
        CURLOPT_HTTPHEADER     => ['Accept-Language: ru,en;q=0.8'],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($body === false || $code < 200 || $code >= 300) {
        return null;
    }

    return $body;
}

/** @return array{posted: array<string, array<string, string>>} */
function wt_load_state(): array
{
    $path = wt_state_path();
    if (!is_readable($path)) {
        return ['posted' => []];
    }

    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data) || !isset($data['posted']) || !is_array($data['posted'])) {
        return ['posted' => []];
    }

    return $data;
}

function wt_save_state(array $state): void
{
    ksort($state['posted']);
    file_put_contents(
        wt_state_path(),
        json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );
}

/**
 * Ключ дедупликации: URL без протокола, языкового префикса, query и якоря.
 */
function wt_dedup_key(string $url): string
{
    $url = strtolower(trim($url));
    $url = preg_replace('~^https?://~', '', $url);
    $url = preg_replace('~[?#].*$~', '', (string) $url);
    $url = preg_replace('~^www\.~', '', (string) $url);
    $url = preg_replace('~/(ru|en)/news/~', '/news/', (string) $url);
    $url = preg_replace('~-(ru|en)$~', '', (string) $url);

    return rtrim((string) $url, '/');
}

function wt_is_posted(array $state, string $url): bool
{
    return isset($state['posted'][wt_dedup_key($url)]);
}

function wt_meta(string $html, string $property): ?string
{
    $patterns = [
        '~<meta[^>]+(?:property|name)=["\']' . preg_quote($property, '~') . '["\'][^>]*content=["\']([^"\']*)["\']~i',
        '~<meta[^>]+content=["\']([^"\']*)["\'][^>]*(?:property|name)=["\']' . preg_quote($property, '~') . '["\']~i',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $html, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    return null;
}

function wt_clean_text(string $html): string
{
    $html = preg_replace('~<(script|style|noscript)[^>]*>.*?</\1>~is', ' ', $html);
    $html = preg_replace('~<br\s*/?>~i', "\n", (string) $html);
    $html = preg_replace('~</(p|div|li|h[1-6]|tr)>~i', "\n", (string) $html);
    $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace("\xC2\xA0", ' ', $text);
    $text = preg_replace('~[ \t]+~', ' ', $text);
    $text = preg_replace('~\n\s*\n\s*\n+~', "\n\n", (string) $text);

    return trim((string) $text);
}

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

/**
 * Ключевые слова халявы: бесплатные раздачи, коды, дропсы, скидки.
 * Возвращает найденные слова, по ним же считаем «халявность» материала.
 *
 * @return string[]
 */
function wt_freebie_hits(string $text): array
{
    $groups = [
        'промокод'  => '~промокод|бонус-?код|gift ?code|инвайт-?код~iu',
        'дропсы'    => '~twitch ?drops|дропс|дроп(?:ы|ов)?\b~iu',
        'бесплатно' => '~бесплатн|даром|без оплаты~iu',
        'раздача'   => '~раздач|розыгрыш|giveaway|дарим|подар(?:ок|ки|очн)~iu',
        'купон'     => '~купон~iu',
        'скидка'    => '~скидк|-\d{2}\s?%|распродаж~iu',
        'награда'   => '~награда за просмотр|за просмотр стрим~iu',
        'событие'   => '~получите[^.]{0,60}в событии|в событии «|наградой станет|выполняя задания|за выполнение заданий~iu',
        'плюшки'    => '~наклейк|декоратор|титул|иконк[аи] профиля~iu',
    ];

    $hits = [];
    foreach ($groups as $label => $pattern) {
        if (preg_match($pattern, $text)) {
            $hits[] = $label;
        }
    }

    return $hits;
}

/**
 * Насколько материал похож на халяву. Бесплатное весит больше, чем скидка.
 */
function wt_freebie_score(array $hits): int
{
    $weights = [
        'промокод'  => 5,
        'бесплатно' => 4,
        'раздача'   => 4,
        'дропсы'    => 4,
        'купон'     => 2,
        'награда'   => 2,
        'скидка'    => 1,
        'событие'   => 3,
        'плюшки'    => 1,
    ];

    $score = 0;
    foreach ($hits as $hit) {
        $score += $weights[$hit] ?? 0;
    }

    return $score;
}

/** Запрос к Discourse-форуму War Thunder. */
function wt_forum_json(string $path): ?array
{
    $body = wt_http_get('https://forum.warthunder.ru' . $path, 25);
    if ($body === null) {
        return null;
    }

    $data = json_decode($body, true);

    return is_array($data) ? $data : null;
}

/**
 * Запрос к Discord API. Возвращает [успех, разобранный ответ].
 *
 * @param array<string, mixed> $params  Тело запроса (или multipart-поля).
 */
function wt_discord_request(string $token, string $method, string $path, array $params = [], bool $multipart = false): array
{
    $ch = curl_init(DISCORD_API . $path);
    $headers = ['Authorization: Bot ' . $token];

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 90,
    ];

    if ($method !== 'GET') {
        if ($multipart) {
            $opts[CURLOPT_POSTFIELDS] = $params;
        } else {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_POSTFIELDS] = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    $opts[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opts);

    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return [false, ['message' => 'curl: ' . $err]];
    }

    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        $data = ['message' => 'Неразбираемый ответ: ' . substr((string) $raw, 0, 300)];
    }

    return [$code >= 200 && $code < 300, $data];
}

/**
 * Отправляет сообщение в канал: текст плюс до 10 файлов вложениями.
 *
 * @param string[] $files   Локальные пути к картинкам.
 * @param ?string  $mention 'everyone', 'here' или null — кого пингуем.
 */
function wt_discord_post(string $token, string $channelId, string $content, array $files = [], ?string $mention = null): array
{
    $path = '/channels/' . $channelId . '/messages';

    // Пинг живёт первой строкой сообщения; без allowed_mentions Discord
    // отрисует его текстом, но никого не уведомит.
    $allowed = ['parse' => []];
    if ($mention === 'everyone' || $mention === 'here') {
        $content = '@' . $mention . "\n" . $content;
        $allowed = ['parse' => ['everyone']];
    }

    if ($files === []) {
        return wt_discord_request($token, 'POST', $path, [
            'content'          => $content,
            'allowed_mentions' => $allowed,
        ]);
    }

    $attachments = [];
    $params      = [];
    foreach (array_values($files) as $i => $file) {
        $attachments[]         = ['id' => $i, 'filename' => basename($file)];
        $params['files[' . $i . ']'] = new CURLFile(
            $file,
            str_ends_with($file, '.png') ? 'image/png' : 'image/jpeg',
            basename($file)
        );
    }

    $params['payload_json'] = json_encode([
        'content'          => $content,
        'attachments'      => $attachments,
        'allowed_mentions' => $allowed,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return wt_discord_request($token, 'POST', $path, $params, true);
}

/** Человекочитаемая ошибка из ответа Discord. */
function wt_discord_error(array $response): string
{
    $parts = [];
    if (isset($response['message'])) {
        $parts[] = (string) $response['message'];
    }
    if (isset($response['code'])) {
        $parts[] = 'code ' . $response['code'];
    }
    if (isset($response['errors'])) {
        $parts[] = json_encode($response['errors'], JSON_UNESCAPED_UNICODE);
    }

    return $parts === [] ? json_encode($response, JSON_UNESCAPED_UNICODE) : implode(', ', $parts);
}
