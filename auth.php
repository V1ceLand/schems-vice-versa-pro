<?php
require_once __DIR__ . '/config.php';

// Функция проверки подлинности данных от Telegram
function checkTelegramAuthorization($auth_data) {
    $check_hash = $auth_data['hash'];
    unset($auth_data['hash']);
    
    $data_check_arr = [];
    foreach ($auth_data as $key => $value) {
        $data_check_arr[] = $key . '=' . $value;
    }
    sort($data_check_arr);
    $data_check_string = implode("\n", $data_check_arr);
    
    $secret_key = hash('sha256', TG_BOT_TOKEN, true);
    $hash = hash_hmac('sha256', $data_check_string, $secret_key);
    
    if (strcmp($hash, $check_hash) !== 0) {
        throw new Exception('Данные не от Telegram (ошибка подписи)');
    }
    // Проверка на устаревшие данные (старше 24 часов)
    if ((time() - $auth_data['auth_date']) > 86400) {
        throw new Exception('Сессия авторизации устарела');
    }
    return $auth_data;
}

try {
    // Проверяем GET запрос от виджета
    if (!isset($_GET['hash'])) {
        header('Location: index.php');
        exit;
    }

    $tg_user = checkTelegramAuthorization($_GET);
    
    // Достаем данные пользователя
    $tg_id = $tg_user['id'];
    $first_name = $tg_user['first_name'];
    $last_name = $tg_user['last_name'] ?? null;
    $username = $tg_user['username'] ?? null;
    $photo_url = $tg_user['photo_url'] ?? null;
    
    // Проверяем, есть ли уже такой пользователь в базе
    $stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ?");
    $stmt->execute([$tg_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Если юзер есть — обновляем его данные (вдруг он сменил аватарку или ник)
        $update_stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, username=?, photo_url=? WHERE telegram_id=?");
        $update_stmt->execute([$first_name, $last_name, $username, $photo_url, $tg_id]);
        $db_user_id = $user['id'];
    } else {
        // Если юзера нет — регистрируем нового
        $insert_stmt = $pdo->prepare("INSERT INTO users (telegram_id, first_name, last_name, username, photo_url) VALUES (?, ?, ?, ?, ?)");
        $insert_stmt->execute([$tg_id, $first_name, $last_name, $username, $photo_url]);
        $db_user_id = $pdo->lastInsertId();
    }
    
    // Авторизуем пользователя (записываем данные в сессию сервера)
    $_SESSION['user_id'] = $db_user_id;
    $_SESSION['tg_id'] = $tg_id;
    $_SESSION['first_name'] = $first_name;
    $_SESSION['username'] = $username;
    $_SESSION['photo_url'] = $photo_url;
    
    // Редирект обратно на главную страницу после успешного входа
    header('Location: index.php');
    exit;
    
} catch (Exception $e) {
    die("Ошибка авторизации: " . $e->getMessage());
}
?>