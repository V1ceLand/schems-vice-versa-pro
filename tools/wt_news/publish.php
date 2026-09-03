<?php
/**
 * Публикует пост с фото в канал Discord и отмечает новость как опубликованную.
 *
 * Требует переменные окружения (или строки в .env):
 *   DISCORD_BOT_TOKEN   — токен бота из Developer Portal
 *   DISCORD_CHANNEL_ID  — числовой id канала (бот должен видеть канал и писать в него)
 *
 *   php tools/wt_news/publish.php \
 *     --url=https://warthunder.com/ru/news/17975-... \
 *     --image=https://staticfiles.warthunder.com/... \
 *     --text-file=/tmp/post.txt
 *
 * Несколько фото уходят одним сообщением (до 10 вложений):
 *   php tools/wt_news/publish.php --url=... --images=url1,url2,url3 --text-file=...
 *
 * Проверка без отправки: добавьте --dry-run
 */

require __DIR__ . '/lib.php';

$options = getopt('', ['url:', 'image:', 'images:', 'text-file:', 'title::', 'dry-run', 'no-state', 'channel::']);

foreach (['url', 'text-file'] as $required) {
    if (empty($options[$required])) {
        fwrite(STDERR, "Не хватает --{$required}\n");
        exit(2);
    }
}

// Картинки: повторяемый --image и/или --images со списком через запятую.
$imageUrls = [];
foreach ((array) ($options['image'] ?? []) as $one) {
    $imageUrls[] = trim((string) $one);
}
foreach (explode(',', (string) ($options['images'] ?? '')) as $one) {
    if (trim($one) !== '') {
        $imageUrls[] = trim($one);
    }
}
$imageUrls = array_values(array_unique(array_filter($imageUrls)));

if ($imageUrls === []) {
    fwrite(STDERR, "Не хватает --image или --images\n");
    exit(2);
}
if (count($imageUrls) > DISCORD_MAX_FILES) {
    $imageUrls = array_slice($imageUrls, 0, DISCORD_MAX_FILES);
}

$sourceUrl = (string) $options['url'];
$textFile  = (string) $options['text-file'];
$dryRun    = array_key_exists('dry-run', $options);

if (!is_readable($textFile)) {
    fwrite(STDERR, "Файл с текстом не читается: {$textFile}\n");
    exit(2);
}

$content = trim((string) file_get_contents($textFile));
if ($content === '') {
    fwrite(STDERR, "Текст поста пустой\n");
    exit(2);
}

$state = wt_load_state();
if (wt_is_posted($state, $sourceUrl)) {
    fwrite(STDERR, "Эта новость уже публиковалась: {$sourceUrl}\n");
    exit(3);
}

// В Discord сообщение до 2000 символов. Длинный текст режем по абзацу.
$tail = null;
if (mb_strlen($content) > DISCORD_MAX_CONTENT) {
    $cut = mb_strrpos(mb_substr($content, 0, DISCORD_MAX_CONTENT - 1), "\n\n");
    if ($cut === false || $cut < 500) {
        $cut = DISCORD_MAX_CONTENT - 1;
    }
    $tail    = trim(mb_substr($content, $cut));
    $content = trim(mb_substr($content, 0, $cut));
}

$token   = wt_env('DISCORD_BOT_TOKEN');
$channel = $options['channel'] ?? wt_env('DISCORD_CHANNEL_ID');

if ($dryRun) {
    fwrite(STDOUT, "=== DRY RUN ===\nКанал: " . ($channel ?: '<не задан>') . "\nФото (" . count($imageUrls) . "):\n  " . implode("\n  ", $imageUrls) . "\nИсточник: {$sourceUrl}\n--- сообщение (" . mb_strlen($content) . " симв.) ---\n{$content}\n");
    if ($tail !== null) {
        fwrite(STDOUT, "--- продолжение вторым сообщением (" . mb_strlen($tail) . " симв.) ---\n{$tail}\n");
    }
    exit(0);
}

if (!$token || !$channel) {
    fwrite(STDERR, "Нужны DISCORD_BOT_TOKEN и DISCORD_CHANNEL_ID в окружении или .env\n");
    exit(2);
}

/** Скачивает картинку во временный файл; null, если не вышло. */
function wt_download_image(string $url): ?string
{
    $bytes = wt_http_get($url, 60);
    if ($bytes === null || strlen($bytes) < 1024) {
        return null;
    }

    $ext  = preg_match('~\.(png|jpe?g)(?:$|\?)~i', $url, $m) ? strtolower($m[1]) : 'jpg';
    $path = sys_get_temp_dir() . '/wt_news_' . bin2hex(random_bytes(6)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    file_put_contents($path, $bytes);

    return $path;
}

// Картинки скачиваем сами: Discord тянет вложения только из загрузки,
// а staticfiles к тому же отдаёт 403 чужим загрузчикам.
$localFiles = [];
foreach ($imageUrls as $url) {
    $path = wt_download_image($url);
    if ($path !== null) {
        $localFiles[] = $path;
    } else {
        fwrite(STDERR, "Не скачалась картинка: {$url}\n");
    }
}

[$ok, $response] = wt_discord_post($token, $channel, $content, $localFiles);
$sentFiles = $ok ? count($localFiles) : 0;

// Без картинок пост всё равно лучше, чем потерянный пост.
if (!$ok && $localFiles !== []) {
    $reason = wt_discord_error($response);
    fwrite(STDERR, "С вложениями не прошло: {$reason}\nПробую отправить текстом.\n");
    if (str_contains($reason, '50013')) {
        fwrite(STDERR, "Похоже, у бота нет права «Прикреплять файлы» в этом канале.\n");
    }
    [$ok, $response] = wt_discord_post($token, $channel, $content, []);
}

foreach ($localFiles as $path) {
    @unlink($path);
}

if (!$ok) {
    fwrite(STDERR, "Публикация не удалась: " . wt_discord_error($response) . "\n");
    exit(1);
}

$messageId = $response['id'] ?? null;

if ($tail !== null) {
    [$tailOk, $tailResponse] = wt_discord_post($token, $channel, $tail, []);
    if (!$tailOk) {
        fwrite(STDERR, "Хвост поста не отправился: " . wt_discord_error($tailResponse) . "\n");
    }
}

if (!array_key_exists('no-state', $options)) {
    $state['posted'][wt_dedup_key($sourceUrl)] = [
        'url'        => $sourceUrl,
        'title'      => (string) ($options['title'] ?? ''),
        'images'     => $sentFiles > 0 ? $imageUrls : [],
        'message_id' => (string) $messageId,
        'posted_at'  => date('c'),
    ];
    wt_save_state($state);
}

fwrite(STDOUT, "Опубликовано: message_id={$messageId}, вложений={$sentFiles}, источник={$sourceUrl}\n");
