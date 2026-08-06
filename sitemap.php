--- START OF FILE sitemap.php ---

<?php
require_once __DIR__ . '/config.php';
header("Content-Type: application/xml; charset=utf-8");

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

$base = "https://" . $_SERVER['HTTP_HOST'];

// Основные страницы
$pages = ['/', '/catalog'];
foreach($pages as $p) {
    echo "<url><loc>{$base}{$p}</loc><changefreq>daily</changefreq><priority>1.0</priority></url>";
}

// Схемы
$schems = $pdo->query("SELECT id, title, created_at FROM schematics")->fetchAll();
foreach($schems as $s) {
    $url = $base . get_schematic_url($s['id'], $s['title']);
    $date = date('Y-m-d', strtotime($s['created_at']));
    echo "<url><loc>{$url}</loc><lastmod>{$date}</lastmod><changefreq>weekly</changefreq><priority>0.8</priority></url>";
}

// Профили пользователей
$users = $pdo->query("SELECT id, username, first_name FROM users")->fetchAll();
foreach($users as $u) {
    $url = $base . get_user_url($u['id'], $u['username'] ?? $u['first_name']);
    echo "<url><loc>{$url}</loc><changefreq>weekly</changefreq><priority>0.6</priority></url>";
}

echo '</urlset>';
?>