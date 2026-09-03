<?php
/**
 * Проверка настройки: виден ли бот, доступен ли канал и можно ли в него писать.
 *
 *   php tools/wt_news/check.php
 */

require __DIR__ . '/lib.php';

$token   = wt_env('DISCORD_BOT_TOKEN');
$channel = wt_env('DISCORD_CHANNEL_ID');

if (!$token) {
    fwrite(STDERR, "DISCORD_BOT_TOKEN не задан\n");
    exit(2);
}
if (!$channel) {
    fwrite(STDERR, "DISCORD_CHANNEL_ID не задан\n");
    exit(2);
}

[$ok, $me] = wt_discord_request($token, 'GET', '/users/@me');
if (!$ok) {
    fwrite(STDERR, "Бот не отвечает: " . wt_discord_error($me) . "\n");
    exit(1);
}
fwrite(STDOUT, "Бот: " . ($me['username'] ?? '?') . " (id " . ($me['id'] ?? '?') . ")\n");

[$ok, $chat] = wt_discord_request($token, 'GET', '/channels/' . $channel);
if (!$ok) {
    fwrite(STDERR, "Канал недоступен: " . wt_discord_error($chat) . "\nДобавьте бота на сервер и дайте ему доступ к каналу.\n");
    exit(1);
}
fwrite(STDOUT, "Канал: #" . ($chat['name'] ?? '?') . " (сервер " . ($chat['guild_id'] ?? '?') . ")\n");

// Права на отправку проверяются только реальной отправкой, поэтому смотрим
// хотя бы тип канала: в голосовой или в категорию писать нельзя.
$textLike = [0, 5, 10, 11, 12, 15];
if (!in_array((int) ($chat['type'] ?? -1), $textLike, true)) {
    fwrite(STDERR, "Это не текстовый канал (type " . ($chat['type'] ?? '?') . ")\n");
    exit(1);
}

fwrite(STDOUT, "Всё готово.\n");
