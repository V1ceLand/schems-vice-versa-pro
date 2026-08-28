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
 *   php tools/wt_news/publish.php --url=... --image=... --text-file=... --dry-run
 */

require __DIR__ . '/lib.php';

$options = getopt('', ['url:', 'image:', 'text-file:', 'title::', 'dry-run', 'no-state', 'channel::']);

foreach (['url', 'image', 'text-file'] as $required) {
    if (empty($options[$required])) {
        fwrite(STDERR, "Не хватает --{$required}\n");
        exit(2);
    }
}

$sourceUrl = (string) $options['url'];
$imageUrl  = (string) $options['image'];
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
    fwrite(STDOUT, "=== DRY RUN ===\nКанал: " . ($channel ?: '<не задан>') . "\nФото: {$imageUrl}\nИсточник: {$sourceUrl}\n--- подпись (" . mb_strlen($caption) . " симв.) ---\n{$caption}\n");
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

// Картинку скачиваем сами: у staticfiles бывает защита от чужих загрузчиков,
// а Telegram по прямой ссылке иногда получает 403.
$imageBytes = wt_http_get($imageUrl, 60);
$tmpImage   = null;
if ($imageBytes !== null && strlen($imageBytes) > 1024) {
    $ext      = preg_match('~\.(png|jpe?g)(?:$|\?)~i', $imageUrl, $m) ? strtolower($m[1]) : 'jpg';
    $tmpImage = sys_get_temp_dir() . '/wt_news_' . bin2hex(random_bytes(6)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    file_put_contents($tmpImage, $imageBytes);
}

$photoParams = [
    'chat_id'    => $channel,
    'caption'    => $caption,
    'parse_mode' => 'HTML',
];
$photoParams['photo'] = $tmpImage !== null
    ? new CURLFile($tmpImage, 'image/' . (str_ends_with($tmpImage, '.png') ? 'png' : 'jpeg'), basename($tmpImage))
    : $imageUrl;

[$ok, $response] = tg_call($token, 'sendPhoto', $photoParams);

// Если фото не приняли — не теряем пост, отправляем текстом.
if (!$ok) {
    fwrite(STDERR, "sendPhoto не прошёл: " . ($response['description'] ?? '?') . "\nПробую отправить текстом.\n");
    [$ok, $response] = tg_call($token, 'sendMessage', [
        'chat_id'    => $channel,
        'text'       => $caption,
        'parse_mode' => 'HTML',
    ]);
}

if ($tmpImage !== null) {
    @unlink($tmpImage);
}

if (!$ok) {
    fwrite(STDERR, "Публикация не удалась: " . ($response['description'] ?? json_encode($response)) . "\n");
    exit(1);
}

$messageId = $response['result']['message_id'] ?? null;

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
        'image'      => $imageUrl,
        'message_id' => (string) $messageId,
        'posted_at'  => date('c'),
    ];
    wt_save_state($state);
}

fwrite(STDOUT, "Опубликовано: message_id={$messageId}, источник={$sourceUrl}\n");
