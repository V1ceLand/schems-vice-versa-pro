<?php
/**
 * Проверка настройки: виден ли бот и есть ли у него доступ к каналу.
 *
 *   php tools/wt_news/check.php
 */

require __DIR__ . '/lib.php';

$token   = wt_env('TG_BOT_TOKEN');
$channel = wt_env('TG_NEWS_CHANNEL');

if (!$token) {
    fwrite(STDERR, "TG_BOT_TOKEN не задан\n");
    exit(2);
}
if (!$channel) {
    fwrite(STDERR, "TG_NEWS_CHANNEL не задан\n");
    exit(2);
}

function tg_get(string $token, string $method, array $query = []): array
{
    $url  = "https://api.telegram.org/bot{$token}/{$method}?" . http_build_query($query);
    $body = wt_http_get($url, 20);
    $data = $body === null ? null : json_decode($body, true);

    return is_array($data) ? $data : ['ok' => false, 'description' => 'нет ответа от api.telegram.org'];
}

$me = tg_get($token, 'getMe');
if (empty($me['ok'])) {
    fwrite(STDERR, "getMe: " . ($me['description'] ?? '?') . "\n");
    exit(1);
}
fwrite(STDOUT, "Бот: @" . ($me['result']['username'] ?? '?') . "\n");

$chat = tg_get($token, 'getChat', ['chat_id' => $channel]);
if (empty($chat['ok'])) {
    fwrite(STDERR, "getChat({$channel}): " . ($chat['description'] ?? '?') . "\nДобавьте бота администратором канала с правом публикации.\n");
    exit(1);
}
fwrite(STDOUT, "Канал: " . ($chat['result']['title'] ?? '?') . " (id " . ($chat['result']['id'] ?? '?') . ")\n");

$admins = tg_get($token, 'getChatAdministrators', ['chat_id' => $channel]);
$isAdmin = false;
foreach ($admins['result'] ?? [] as $admin) {
    if (($admin['user']['id'] ?? null) === ($me['result']['id'] ?? null)) {
        $isAdmin = true;
        $canPost = !empty($admin['can_post_messages']);
        fwrite(STDOUT, "Бот администратор: да, право публикации: " . ($canPost ? 'есть' : 'НЕТ') . "\n");
    }
}
if (!$isAdmin) {
    fwrite(STDERR, "Бот не в администраторах канала — публиковать не сможет.\n");
    exit(1);
}

fwrite(STDOUT, "Всё готово.\n");
