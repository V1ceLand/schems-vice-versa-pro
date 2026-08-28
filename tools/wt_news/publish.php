<?php
/**
 * Публикует пост с фото в Telegram-канал и отмечает новость как опубликованную.
 *
 * Требует переменные окружения (или строки в .env):
 *   TG_BOT_TOKEN     — токен бота из @BotFather
 *   TG_NEWS_CHANNEL  — @username или числовой id канала (бот должен быть админом)
 *
 * Пример:
 *   php tools/wt_news/publish.php \
 *     --url=https://warthunder.com/ru/news/17945-... \
 *     --image=https://staticfiles.warthunder.com/... \
 *     --text-file=/tmp/post.txt
 *
 * Несколько фото уходят альбомом (до 10, подпись на первом):
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
if (count($imageUrls) > 10) {
    // Telegram не принимает альбом больше чем из 10 медиа.
    $imageUrls = array_slice($imageUrls, 0, 10);
}

$sourceUrl = (string) $options['url'];
$textFile  = (string) $options['text-file'];
$dryRun    = array_key_exists('dry-run', $options);

if (!is_readable($textFile)) {
    fwrite(STDERR, "Файл с текстом не читается: {$textFile}\n");
    exit(2);
}

$caption = trim((string) file_get_contents($textFile));
if ($caption === '') {
    fwrite(STDERR, "Текст поста пустой\n");
    exit(2);
}

$state = wt_load_state();
if (wt_is_posted($state, $sourceUrl)) {
    fwrite(STDERR, "Эта новость уже публиковалась: {$sourceUrl}\n");
    exit(3);
}

// Telegram: подпись к фото — до 1024 символов, отдельное сообщение — до 4096.
$captionLimit = 1024;
$tail = null;
if (mb_strlen($caption) > $captionLimit) {
    $cut = mb_strrpos(mb_substr($caption, 0, $captionLimit - 1), "\n\n");
    if ($cut === false || $cut < 300) {
        $cut = $captionLimit - 1;
    }
    $tail    = trim(mb_substr($caption, $cut));
    $caption = trim(mb_substr($caption, 0, $cut));
}

$token   = wt_env('TG_BOT_TOKEN');
$channel = $options['channel'] ?? wt_env('TG_NEWS_CHANNEL');

if ($dryRun) {
    fwrite(STDOUT, "=== DRY RUN ===\nКанал: " . ($channel ?: '<не задан>') . "\nФото (" . count($imageUrls) . "):\n  " . implode("\n  ", $imageUrls) . "\nИсточник: {$sourceUrl}\n--- подпись (" . mb_strlen($caption) . " симв.) ---\n{$caption}\n");
    if ($tail !== null) {
        fwrite(STDOUT, "--- продолжение отдельным сообщением (" . mb_strlen($tail) . " симв.) ---\n{$tail}\n");
    }
    exit(0);
}

if (!$token || !$channel) {
    fwrite(STDERR, "Нужны TG_BOT_TOKEN и TG_NEWS_CHANNEL в окружении или .env\n");
    exit(2);
}

/** Вызов Bot API. Возвращает [ok, ответ]. */
function tg_call(string $token, string $method, array $params, int $timeout = 60): array
{
    $ch = curl_init("https://api.telegram.org/bot{$token}/{$method}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $params,
        CURLOPT_TIMEOUT        => $timeout,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return [false, ['description' => 'curl: ' . $err]];
    }

    $decoded = json_decode((string) $raw, true);
    if (!is_array($decoded)) {
        return [false, ['description' => 'Неразбираемый ответ: ' . substr((string) $raw, 0, 300)]];
    }

    return [(bool) ($decoded['ok'] ?? false), $decoded];
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

function wt_curl_file(string $path): CURLFile
{
    return new CURLFile($path, str_ends_with($path, '.png') ? 'image/png' : 'image/jpeg', basename($path));
}

// Картинки скачиваем сами: staticfiles иногда отдаёт 403 загрузчику Telegram.
$localFiles = [];
foreach ($imageUrls as $url) {
    $localFiles[$url] = wt_download_image($url);
}

$ok        = false;
$response  = [];
$messageId = null;

if (count($imageUrls) > 1) {
    // Альбом: подпись живёт на первом фото и показывается над всей группой.
    $media  = [];
    $params = ['chat_id' => $channel];

    foreach (array_values($imageUrls) as $i => $url) {
        $item = ['type' => 'photo'];
        if ($localFiles[$url] !== null) {
            $field = 'file' . $i;
            $params[$field] = wt_curl_file($localFiles[$url]);
            $item['media']  = 'attach://' . $field;
        } else {
            $item['media'] = $url;
        }
        if ($i === 0) {
            $item['caption']    = $caption;
            $item['parse_mode'] = 'HTML';
        }
        $media[] = $item;
    }

    $params['media'] = json_encode($media, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    [$ok, $response] = tg_call($token, 'sendMediaGroup', $params);

    if ($ok) {
        $messageId = $response['result'][0]['message_id'] ?? null;
    } else {
        fwrite(STDERR, "sendMediaGroup не прошёл: " . ($response['description'] ?? '?') . "\nПробую отправить одним фото.\n");
    }
}

// Одно фото: либо так и задумано, либо альбом не ушёл.
if (!$ok) {
    $first  = $imageUrls[0];
    $params = [
        'chat_id'    => $channel,
        'caption'    => $caption,
        'parse_mode' => 'HTML',
        'photo'      => $localFiles[$first] !== null ? wt_curl_file($localFiles[$first]) : $first,
    ];
    [$ok, $response] = tg_call($token, 'sendPhoto', $params);
    if ($ok) {
        $messageId = $response['result']['message_id'] ?? null;
    } else {
        fwrite(STDERR, "sendPhoto не прошёл: " . ($response['description'] ?? '?') . "\nПробую отправить текстом.\n");
    }
}

// Совсем без картинки пост всё равно лучше, чем потерянный пост.
if (!$ok) {
    [$ok, $response] = tg_call($token, 'sendMessage', [
        'chat_id'    => $channel,
        'text'       => $caption,
        'parse_mode' => 'HTML',
    ]);
    $messageId = $response['result']['message_id'] ?? null;
}

foreach ($localFiles as $path) {
    if ($path !== null) {
        @unlink($path);
    }
}

if (!$ok) {
    fwrite(STDERR, "Публикация не удалась: " . ($response['description'] ?? json_encode($response)) . "\n");
    exit(1);
}

if ($tail !== null) {
    [$tailOk, $tailResponse] = tg_call($token, 'sendMessage', [
        'chat_id'             => $channel,
        'text'                => $tail,
        'parse_mode'          => 'HTML',
        'reply_to_message_id' => $messageId,
    ]);
    if (!$tailOk) {
        fwrite(STDERR, "Хвост поста не отправился: " . ($tailResponse['description'] ?? '?') . "\n");
    }
}

if (!array_key_exists('no-state', $options)) {
    $state['posted'][wt_dedup_key($sourceUrl)] = [
        'url'        => $sourceUrl,
        'title'      => (string) ($options['title'] ?? ''),
        'images'     => $imageUrls,
        'message_id' => (string) $messageId,
        'posted_at'  => date('c'),
    ];
    wt_save_state($state);
}

fwrite(STDOUT, "Опубликовано: message_id={$messageId}, источник={$sourceUrl}\n");
