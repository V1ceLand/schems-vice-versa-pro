<?php
session_start();

// Все секреты и параметры окружения задаются через .env (см. .env.example)
// и подгружаются веб-сервером/docker-compose как переменные окружения.
$db_name = getenv('DB_NAME') ?: 'my_db_name';
$db_user = getenv('DB_USER') ?: 'user';
$db_pass = getenv('DB_PASS') ?: '';
$db_charset = getenv('DB_CHARSET') ?: 'utf8mb4';

define('TG_BOT_TOKEN', getenv('TG_BOT_TOKEN') ?: '');
define('TG_BOT_USERNAME', getenv('TG_BOT_USERNAME') ?: '');
// Идентификатор OAuth-клиента является публичным параметром браузерной авторизации.
define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '');

$options =[
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_TIMEOUT            => 3,
];

$pdo = null;
$errors =[];

// Скрипт по очереди попытается подключиться через все возможные маршруты
$hosts_to_try =[
    'my_database',  // действующий Docker-прокси БД на сервере сайта
    'db',           // Если сайт работает внутри Docker
    '172.17.0.1',   // Стандартный шлюз Docker
    '172.18.0.1',   // Альтернативный шлюз Docker
    '172.19.0.1',   // Альтернативный шлюз Docker 2
    '172.20.0.1',   // Альтернативный шлюз Docker 3
    '127.0.0.1'     // Классический локалхост
];

foreach ($hosts_to_try as $host) {
    try {
        $dsn = "mysql:host=$host;port=3306;dbname=$db_name;charset=$db_charset";
        $pdo = new PDO($dsn, $db_user, $db_pass, $options);
        break; // Ура! Подключились, прерываем цикл
    } catch (\PDOException $e) {
        $errors[] = "[$host] -> " . $e->getMessage();
    }
}

if (!$pdo) {
    die("<h3>Критическая ошибка: БД недоступна по всем маршрутам</h3>" . implode("<br>", $errors));
}

// ===== SEO ФУНКЦИИ =====
function generate_slug($str) {
    $char_map =[
        'а'=>'a', 'б'=>'b', 'в'=>'v', 'г'=>'g', 'д'=>'d', 'е'=>'e', 'ё'=>'yo', 'ж'=>'zh',
        'з'=>'z', 'и'=>'i', 'й'=>'j', 'к'=>'k', 'л'=>'l', 'м'=>'m', 'н'=>'n', 'о'=>'o',
        'п'=>'p', 'р'=>'r', 'с'=>'s', 'т'=>'t', 'у'=>'u', 'ф'=>'f', 'х'=>'h', 'ц'=>'c',
        'ч'=>'ch', 'ш'=>'sh', 'щ'=>'shh', 'ъ'=>'', 'ы'=>'y', 'ь'=>'', 'э'=>'e', 'ю'=>'yu', 'я'=>'ya',
        'А'=>'a', 'Б'=>'b', 'В'=>'v', 'Г'=>'g', 'Д'=>'d', 'Е'=>'e', 'Ё'=>'yo', 'Ж'=>'zh',
        'З'=>'z', 'И'=>'i', 'Й'=>'j', 'К'=>'k', 'Л'=>'l', 'М'=>'m', 'Н'=>'n', 'О'=>'o',
        'П'=>'p', 'Р'=>'r', 'С'=>'s', 'Т'=>'t', 'У'=>'u', 'Ф'=>'f', 'Х'=>'h', 'Ц'=>'c',
        'Ч'=>'ch', 'Ш'=>'sh', 'Щ'=>'shh', 'Ъ'=>'', 'Ы'=>'y', 'Ь'=>'', 'Э'=>'e', 'Ю'=>'yu', 'Я'=>'ya'
    ];
    $str = strtr($str, $char_map);
    $str = preg_replace('/[^a-zA-Z0-9]+/', '-', $str);
    return trim(strtolower($str), '-');
}

function get_schematic_url($id, $title) {
    return '/schematic/' . (int)$id . '-' . generate_slug($title);
}

function get_user_url($id, $name) {
    return '/user/' . (int)$id . '-' . generate_slug($name);
}

function get_image_url($path) {
    if (empty($path)) return '';
    if (strpos($path, 'http') === 0) return $path;
    return '/' . ltrim($path, '/');
}

// ===== СПИСКИ ТЕГОВ (СТИЛИ И ТИПЫ) =====
$BUILD_STYLES =[
    'medieval' => 'Средневековье', 'modern' => 'Модерн / Современный',
    'fantasy' => 'Фэнтези', 'sci-fi' => 'Киберпанк / Будущее',
    'steampunk' => 'Стимпанк', 'rustic' => 'Деревенский / Природный',
    'asian' => 'Азиатский', 'nordic' => 'Скандинавский',
    'gothic' => 'Готика', 'ruined' => 'Руины / Заброшенное', 'organic' => 'Органика'
];

$BUILD_TYPES =[
    'house' => 'Дом / Особняк', 'castle' => 'Замок / Крепость',
    'tower' => 'Башня / Маяк', 'temple' => 'Храм / Церковь',
    'village' => 'Деревня / Поселение', 'spawn' => 'Спавн / Хаб',
    'arena' => 'Арена / PvP-зона', 'farm' => 'Ферма / Мельница',
    'ship' => 'Корабль / Дирижабль', 'statue' => 'Статуя / Монумент',
    'shop' => 'Магазин / Палатка', 'decoration' => 'Декорация'
];
?>
