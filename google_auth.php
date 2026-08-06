<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

function reply($status, $message, $extra = []) {
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') reply('error', 'Некорректный запрос.');
$data = json_decode(file_get_contents('php://input'), true);
$token = is_array($data) ? trim((string)($data['credential'] ?? '')) : '';
if ($token === '') reply('error', 'Google не передал токен входа.');

// Google проверяет подпись ID-токена на своей стороне. Мы дополнительно строго сверяем аудиторию,
// issuer и статус подтверждения почты до создания сессии.
$url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($token);
$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
$raw = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$claims = $raw ? json_decode($raw, true) : null;

if ($code !== 200 || !is_array($claims) || ($claims['aud'] ?? '') !== GOOGLE_CLIENT_ID || !in_array($claims['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true) || ($claims['email_verified'] ?? '') !== 'true') {
    reply('error', 'Не удалось подтвердить вход через Google.');
}

$googleId = (string)($claims['sub'] ?? '');
$email = filter_var($claims['email'] ?? '', FILTER_VALIDATE_EMAIL);
$name = trim((string)($claims['name'] ?? 'Игрок'));
$picture = filter_var($claims['picture'] ?? '', FILTER_VALIDATE_URL) ?: null;
if ($googleId === '' || !$email) reply('error', 'В профиле Google не хватает необходимых данных.');

try {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE google_id = ? LIMIT 1');
    $stmt->execute([$googleId]);
    $user = $stmt->fetch();

    if (!$user) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            $pdo->prepare('UPDATE users SET google_id = ?, first_name = ?, photo_url = COALESCE(photo_url, ?) WHERE id = ?')->execute([$googleId, mb_substr($name, 0, 100), $picture, $user['id']]);
        } else {
            $username = preg_replace('/[^a-zA-Z0-9_]/', '', strtok($email, '@')) ?: 'player';
            $pdo->prepare('INSERT INTO users (telegram_id, google_id, email, first_name, username, photo_url) VALUES (NULL, ?, ?, ?, ?, ?)')->execute([$googleId, $email, mb_substr($name, 0, 100), mb_substr($username, 0, 64), $picture]);
            $user = ['id' => $pdo->lastInsertId(), 'first_name' => $name, 'username' => $username, 'photo_url' => $picture];
        }
    }

    $id = (int)$user['id'];
    $firstName = $user['first_name'] ?: $name;
    $_SESSION['user_id'] = $id;
    $_SESSION['first_name'] = $firstName;
    $_SESSION['username'] = $user['username'] ?? null;
    $_SESSION['photo_url'] = $user['photo_url'] ?? $picture;
    $_SESSION['user_email'] = $email;
    $_SESSION['auth_provider'] = 'google';
    reply('success', 'Вход выполнен.', ['redirect' => '/']);
} catch (Throwable $e) {
    error_log('SchemHub Google auth: ' . $e->getMessage());
    reply('error', 'Не удалось создать сессию. Попробуйте ещё раз.');
}
