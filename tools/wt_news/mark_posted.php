<?php
/**
 * Отмечает новость как обработанную без публикации — например, если она
 * не подходит каналу и не должна снова попадать в кандидаты.
 *
 *   php tools/wt_news/mark_posted.php --url=https://warthunder.com/ru/news/... --reason=skipped
 */

require __DIR__ . '/lib.php';

$options = getopt('', ['url:', 'reason::', 'title::']);
if (empty($options['url'])) {
    fwrite(STDERR, "Не хватает --url\n");
    exit(2);
}

$url   = (string) $options['url'];
$state = wt_load_state();

$state['posted'][wt_dedup_key($url)] = [
    'url'       => $url,
    'title'     => (string) ($options['title'] ?? ''),
    'reason'    => (string) ($options['reason'] ?? 'skipped'),
    'posted_at' => date('c'),
];

wt_save_state($state);
fwrite(STDOUT, "Отмечено как обработанное: {$url}\n");
