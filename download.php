
<?php
require_once __DIR__ . '/config.php';

if (!isset($_GET['id'])) {
    die("ID схемы не указан.");
}

$id = (int)$_GET['id'];
$version_id = isset($_GET['version_id']) ? (int)$_GET['version_id'] : 0;

// Если запрошена конкретная (старая) версия
if ($version_id > 0) {
    // Получаем файл версии + название самой схемы
    $stmt = $pdo->prepare("
        SELECT v.file_name, v.file_path, s.title 
        FROM schematic_versions v 
        JOIN schematics s ON v.schematic_id = s.id 
        WHERE v.id = ? AND v.schematic_id = ?
    ");
    $stmt->execute([$version_id, $id]);
} else {
    // Если запрошена основная (последняя) версия
    $stmt = $pdo->prepare("SELECT file_name, file_path, title FROM schematics WHERE id = ?");
    $stmt->execute([$id]);
}

$schem = $stmt->fetch();

if (!$schem || !file_exists($schem['file_path'])) {
    die("Файл не найден на сервере или был удален.");
}

// Засчитываем статистику ТОЛЬКО для основной (последней) версии
if ($version_id == 0) {
    // 1. Увеличиваем сырой (общий) счетчик скачиваний
    $update = $pdo->prepare("UPDATE schematics SET downloads = downloads + 1 WHERE id = ?");
    $update->execute([$id]);

    // 2. Уникальные скачивания (Только для авторизованных, 1 раз на юзера)
    if (isset($_SESSION['user_id'])) {
        $user_id = (int)$_SESSION['user_id'];
        $dl_stmt = $pdo->prepare("INSERT IGNORE INTO user_downloads (user_id, schematic_id) VALUES (?, ?)");
        $dl_stmt->execute([$user_id, $id]);
    }
}

// Генерируем красивое название файла на основе названия схемы
$friendly_name = generate_slug($schem['title']);
if (empty($friendly_name)) {
    $friendly_name = 'schematic';
}

// Если это старая версия, добавляем индикатор версии к файлу
if ($version_id > 0) {
    $friendly_name .= '-old-version';
}

$friendly_name .= '.litematic';

// Отдаем файл пользователю
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $friendly_name . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($schem['file_path']));
readfile($schem['file_path']);
exit;
?>