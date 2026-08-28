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
