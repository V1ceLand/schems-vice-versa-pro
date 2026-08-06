
<?php
require_once __DIR__ . '/header.php';

$user_id = (int)($_GET['id'] ?? 0);
// ПРАВКА: Принудительно делаем ID из сессии числом, чтобы проверка владельца работала корректно
$current_user_id = (int)($_SESSION['user_id'] ?? 0); 
$is_owner = ($user_id === $current_user_id && $current_user_id > 0);

// --- AJAX ОБРАБОТКА (Моментальная подписка без перезагрузки) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_subscribe') {
    header('Content-Type: application/json');
    if (!$is_owner && $current_user_id > 0) {
        $check = $pdo->prepare("SELECT 1 FROM user_subscriptions WHERE subscriber_id = ? AND author_id = ?");
        $check->execute([$current_user_id, $user_id]);
        if ($check->fetch()) {
            $pdo->prepare("DELETE FROM user_subscriptions WHERE subscriber_id = ? AND author_id = ?")->execute([$current_user_id, $user_id]);
            echo json_encode(['status' => 'unsubscribed']);
        } else {
            $pdo->prepare("INSERT INTO user_subscriptions (subscriber_id, author_id) VALUES (?, ?)")->execute([$current_user_id, $user_id]);
            echo json_encode(['status' => 'subscribed']);
        }
    }
    exit;
}

// 1. Обработка сохранения профиля
if ($is_owner && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'edit_profile') {
        $new_desc = trim($_POST['description'] ?? '');
        $new_contacts = trim($_POST['contacts'] ?? '');
        $new_shape = ($_POST['avatar_shape'] ?? 'round') === 'square' ? 'square' : 'round';
        
        $upd = $pdo->prepare("UPDATE users SET description = ?, contacts = ?, avatar_shape = ? WHERE id = ?");
        $upd->execute([$new_desc, $new_contacts, $new_shape, $current_user_id]);
        
        header("Location: profile.php?id=$current_user_id");
        exit;
    } 
    
    // 2. Обработка удаления аккаунта
    if ($_POST['action'] === 'delete_account') {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$current_user_id]);
        session_destroy();
        header("Location: index.php");
        exit;
    }
}

// Получаем информацию о пользователе
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$profile_user = $stmt->fetch();

if (!$profile_user) {
    echo "<div class='text-center py-20 text-xl text-red-400'>Пользователь не найден.</div>";
    require_once __DIR__ . '/footer.php';
    exit;
}

// Статистика профиля
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(s.id) as total_schems, 
        COALESCE(SUM(s.views), 0) as total_views, 
        (SELECT COUNT(*) FROM user_downloads ud JOIN schematics s2 ON ud.schematic_id = s2.id WHERE s2.user_id = ?) as total_downloads 
    FROM schematics s WHERE s.user_id = ?
");
$stats_stmt->execute([$user_id, $user_id]);
$stats = $stats_stmt->fetch();

// Подписчики
$subs_stmt = $pdo->prepare("SELECT COUNT(*) FROM user_subscriptions WHERE author_id = ?");
$subs_stmt->execute([$user_id]);
$followers_count = $subs_stmt->fetchColumn();

// Проверка, подписан ли текущий юзер
$is_subscribed = false;
if ($current_user_id && !$is_owner) {
    $sub_check = $pdo->prepare("SELECT 1 FROM user_subscriptions WHERE subscriber_id = ? AND author_id = ?");
    $sub_check->execute([$current_user_id, $user_id]);
    $is_subscribed = (bool)$sub_check->fetch();
}

// Получаем схемы
$schems_stmt = $pdo->prepare("
    SELECT s.*, 
           u.username, u.first_name, u.photo_url, u.avatar_shape,
           COALESCE((SELECT COUNT(*) FROM user_downloads ud WHERE ud.schematic_id = s.id), 0) as real_downloads,
           COALESCE((SELECT AVG(rating) FROM user_ratings ur WHERE ur.schematic_id = s.id), 0) as rating,
           COALESCE((SELECT COUNT(*) FROM user_ratings ur WHERE ur.schematic_id = s.id), 0) as rating_count
    FROM schematics s 
    JOIN users u ON s.user_id = u.id
    WHERE s.user_id = ? AND s.id IN (SELECT MAX(id) FROM schematics GROUP BY COALESCE(parent_id, id))
    ORDER BY s.created_at DESC
");
$schems_stmt->execute([$user_id]);
$user_schematics = $schems_stmt->fetchAll();

$display_name = $profile_user['username'] ? '@' . $profile_user['username'] : $profile_user['first_name'];
$contacts = $profile_user['contacts'] ?: ($profile_user['username'] ? '@'.$profile_user['username'] : 'Не указаны');
$avatar = $profile_user['photo_url'] ?? 'https://ui-avatars.com/api/?background=2563eb&color=fff&size=256&name=' . urlencode($profile_user['first_name']);
$avatar_shape = $profile_user['avatar_shape'] ?? 'round';
$avatar_class = $avatar_shape === 'square' ? 'rounded-2xl' : 'rounded-full';
?>

<!-- Шапка профиля -->
<div class="bg-zinc-900 border border-zinc-800 rounded-3xl p-8 mb-10 flex flex-col md:flex-row gap-8 items-center md:items-start relative overflow-hidden">
    <div class="absolute top-0 right-0 w-64 h-64 bg-blue-500/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/3 pointer-events-none"></div>
    
    <div class="relative z-10 shrink-0">
        <img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="w-32 h-32 <?= $avatar_class ?> border-4 border-zinc-800 shadow-xl object-cover">
    </div>
    
    <div class="flex-1 relative z-10 w-full text-center md:text-left">
        <div class="flex flex-col md:flex-row justify-between items-center md:items-start gap-4 mb-4">
            <div>
                <h1 class="text-3xl font-bold text-white mb-1"><?= htmlspecialchars($profile_user['first_name'] . ' ' . $profile_user['last_name']) ?></h1>
                <p class="text-blue-400 font-medium"><?= htmlspecialchars($display_name) ?></p>
            </div>
            
            <!-- Кнопки действий -->
            <div class="flex gap-3">
                <?php if($is_owner): ?>
                    <!-- Кнопка редактирования для Владельца -->
                    <button onclick="document.getElementById('editModal').classList.remove('hidden')" class="bg-zinc-800 hover:bg-zinc-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition-colors shadow-lg">
                        <i class="fa-solid fa-pen mr-2"></i> Редактировать профиль
                    </button>
                <?php elseif($current_user_id): ?>
                    <!-- Кнопка подписки для других авторизованных юзеров -->
                    <button id="subBtn" onclick="toggleSubscribe(<?= $user_id ?>)" class="px-5 py-2.5 rounded-xl text-sm font-semibold transition-colors shadow-lg <?= $is_subscribed ? 'bg-zinc-800 text-zinc-300 hover:bg-red-500/20 hover:text-red-400 border border-transparent hover:border-red-500' : 'bg-blue-600 text-white hover:bg-blue-500' ?>">
                        <?php if($is_subscribed): ?>
                            <i class="fa-solid fa-check mr-1" id="subIcon"></i> <span id="subText">Вы подписаны</span>
                        <?php else: ?>
                            <i class="fa-solid fa-user-plus mr-1" id="subIcon"></i> <span id="subText">Подписаться</span>
                        <?php endif; ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if(!empty($profile_user['description'])): ?>
            <p class="text-zinc-400 mb-6 text-sm max-w-3xl whitespace-pre-wrap mx-auto md:mx-0"><?= htmlspecialchars($profile_user['description']) ?></p>
        <?php endif; ?>

        <div class="flex flex-wrap justify-center md:justify-start items-center gap-6 mt-4">
            <div class="flex items-center gap-2 text-sm bg-zinc-950 px-4 py-2.5 rounded-xl border border-zinc-800">
                <i class="fa-brands fa-telegram text-blue-500 text-lg"></i>
                <span class="text-zinc-300 font-medium"><?= htmlspecialchars($contacts) ?></span>
            </div>
            <div class="flex gap-6 text-center">
                <div><div class="text-xl font-bold text-white"><?= $stats['total_schems'] ?: 0 ?></div><div class="text-[10px] text-zinc-500 uppercase font-bold tracking-wider mt-0.5">Схем</div></div>
                <div><div class="text-xl font-bold text-emerald-400"><?= $stats['total_downloads'] ?: 0 ?></div><div class="text-[10px] text-zinc-500 uppercase font-bold tracking-wider mt-0.5">Скачиваний</div></div>
                <div><div class="text-xl font-bold text-rose-400" id="followersCount"><?= $followers_count ?></div><div class="text-[10px] text-zinc-500 uppercase font-bold tracking-wider mt-0.5">Подписчиков</div></div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно редактирования профиля -->
<?php if($is_owner): ?>
<div id="editModal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-zinc-900 border border-zinc-700 rounded-3xl w-full max-w-lg p-6 relative shadow-2xl">
        <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="absolute top-5 right-5 text-zinc-500 hover:text-white transition-colors">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
        <h2 class="text-xl font-bold text-white mb-6">Настройки профиля</h2>
        
        <form method="POST" class="space-y-5">
            <input type="hidden" name="action" value="edit_profile">
            
            <div>
                <label class="block text-sm font-medium text-zinc-400 mb-2">О себе (описание)</label>
                <textarea name="description" rows="3" placeholder="Расскажите о себе или своих постройках..." class="w-full bg-zinc-950 border border-zinc-800 rounded-xl p-3 text-white focus:border-blue-500 outline-none transition-colors"><?= htmlspecialchars($profile_user['description'] ?? '') ?></textarea>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-zinc-400 mb-2">Контакты (Discord, TG и т.д.)</label>
                <input type="text" name="contacts" value="<?= htmlspecialchars($profile_user['contacts'] ?? '') ?>" placeholder="<?= htmlspecialchars($contacts) ?>" class="w-full bg-zinc-950 border border-zinc-800 rounded-xl p-3 text-white focus:border-blue-500 outline-none transition-colors">
            </div>

            <div class="bg-zinc-950 p-4 rounded-xl border border-zinc-800">
                <label class="block text-sm font-medium text-zinc-400 mb-3">Форма аватара</label>
                <div class="flex gap-6">
                    <label class="flex items-center gap-2 cursor-pointer group">
                        <input type="radio" name="avatar_shape" value="round" <?= $avatar_shape === 'round' ? 'checked' : '' ?> class="accent-blue-500 w-4 h-4">
                        <span class="text-zinc-300 group-hover:text-white transition-colors">Круглый</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer group">
                        <input type="radio" name="avatar_shape" value="square" <?= $avatar_shape === 'square' ? 'checked' : '' ?> class="accent-blue-500 w-4 h-4">
                        <span class="text-zinc-300 group-hover:text-white transition-colors">Квадратный</span>
                    </label>
                </div>
                <p class="text-xs text-zinc-500 mt-3 leading-relaxed">* Чтобы обновить саму фотографию профиля, перезайдите на сайт через Telegram-виджет.</p>
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3.5 rounded-xl mt-2 transition-colors shadow-lg shadow-blue-500/20">
                Сохранить изменения
            </button>
        </form>

        <hr class="border-zinc-800 my-6">
        
        <form method="POST" onsubmit="return confirm('Вы уверены? Это удалит ваш аккаунт безвозвратно. Ваши схемы потеряют автора.');">
            <input type="hidden" name="action" value="delete_account">
            <button type="submit" class="w-full bg-red-500/10 text-red-400 hover:bg-red-500 hover:text-white border border-red-500/20 hover:border-red-500 py-3 rounded-xl transition-all font-medium flex justify-center items-center gap-2">
                <i class="fa-solid fa-trash-can"></i> Удалить аккаунт
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<h2 class="text-2xl font-semibold mb-6 flex items-center gap-3">
    <i class="fa-solid fa-list-ul text-zinc-500"></i> Схемы автора
</h2>

<!-- Сетка схем пользователя -->
<?php if(empty($user_schematics)): ?>
    <div class="text-center py-16 bg-zinc-900/50 rounded-3xl border border-dashed border-zinc-700">
        <i class="fa-regular fa-folder-open text-5xl text-zinc-600 mb-4"></i>
        <p class="text-zinc-500">Этот пользователь еще не загрузил ни одной схемы.</p>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php foreach($user_schematics as $schem): ?>
            <?php include __DIR__ . '/schematic_card.php'; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Скрипт для моментального переключения Подписки (Оптимистичный UI) -->
<script>
async function toggleSubscribe(authorId) {
    const btn = document.getElementById('subBtn');
    const icon = document.getElementById('subIcon');
    const text = document.getElementById('subText');
    const countEl = document.getElementById('followersCount');
    let currentCount = parseInt(countEl.innerText);

    // Моментально меняем визуал (Оптимистичный подход)
    if (btn.classList.contains('bg-blue-600')) {
        // Становимся подписанными
        btn.className = "px-5 py-2.5 rounded-xl text-sm font-semibold transition-colors shadow-lg bg-zinc-800 text-zinc-300 hover:bg-red-500/20 hover:text-red-400 border border-transparent hover:border-red-500";
        icon.className = "fa-solid fa-check mr-1";
        text.innerText = "Вы подписаны";
        countEl.innerText = currentCount + 1;
    } else {
        // Отписываемся
        btn.className = "px-5 py-2.5 rounded-xl text-sm font-semibold transition-colors shadow-lg bg-blue-600 text-white hover:bg-blue-500";
        icon.className = "fa-solid fa-user-plus mr-1";
        text.innerText = "Подписаться";
        countEl.innerText = Math.max(0, currentCount - 1);
    }

    // В фоне отправляем запрос на сервер
    let fd = new FormData();
    fd.append('action', 'toggle_subscribe');
    await fetch(`profile.php?id=${authorId}`, { method: 'POST', body: fd });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>