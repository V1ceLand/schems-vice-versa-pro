
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
    echo "<div class='text-center py-20 text-xl' style='color:#ff9d9d'>Пользователь не найден.</div>";
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

<!-- Обложка профиля -->
<div class="relative overflow-hidden" style="height:clamp(110px,16vw,180px);border-radius:44px;background:var(--color-accent-2-200)">
    <div style="position:absolute;top:-20%;right:-6%;width:220px;height:220px;background:var(--color-accent-2-300);border-radius:52% 48% 44% 56% / 48% 56% 44% 52%;opacity:.6;animation:shMorph 30s ease-in-out infinite"></div>
</div>

<!-- Шапка профиля -->
<div class="relative flex flex-col md:flex-row gap-6 items-center md:items-end" style="margin-top:-52px;padding:0 8px;z-index:2">
    <img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="w-28 h-28 <?= $avatar_class ?> flex-none object-cover" style="box-shadow:0 0 0 6px var(--color-bg)">

    <div class="flex-1 w-full text-center md:text-left" style="padding-bottom:6px">
        <div class="flex flex-col md:flex-row justify-between items-center md:items-end gap-4">
            <div>
                <h1 class="mb-0.5" style="font-size:clamp(24px,3vw,34px)"><?= htmlspecialchars($profile_user['first_name'] . ' ' . $profile_user['last_name']) ?></h1>
                <p style="opacity:.6;font-size:15px"><?= htmlspecialchars($display_name) ?></p>
            </div>

            <!-- Кнопки действий -->
            <div class="flex gap-2">
                <?php if($is_owner): ?>
                    <button onclick="document.getElementById('editModal').classList.remove('hidden')" class="btn btn-secondary">
                        <i class="fa-solid fa-pen"></i> Редактировать профиль
                    </button>
                <?php elseif($current_user_id): ?>
                    <button id="subBtn" onclick="toggleSubscribe(<?= $user_id ?>)" class="btn <?= $is_subscribed ? 'btn-secondary' : 'btn-primary' ?>">
                        <?php if($is_subscribed): ?>
                            <i class="fa-solid fa-check" id="subIcon"></i> <span id="subText">Вы подписаны</span>
                        <?php else: ?>
                            <i class="fa-solid fa-user-plus" id="subIcon"></i> <span id="subText">Подписаться</span>
                        <?php endif; ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="flex flex-wrap items-end justify-between gap-6 mt-6 mb-10" style="padding:0 8px">
    <div style="max-width:46ch">
        <?php if(!empty($profile_user['description'])): ?>
            <p style="font-size:15px;line-height:1.6;opacity:.8;white-space:pre-wrap" class="mb-3"><?= htmlspecialchars($profile_user['description']) ?></p>
        <?php endif; ?>
        <div class="tag tag-neutral inline-flex">
            <i class="fa-brands fa-telegram" style="color:var(--color-accent)"></i>
            <?= htmlspecialchars($contacts) ?>
        </div>
    </div>
    <div class="flex gap-8 flex-wrap">
        <div><div class="font-heading" style="font-size:26px;line-height:1"><?= $stats['total_schems'] ?: 0 ?></div><div style="font-size:12px;opacity:.6;margin-top:2px">схем</div></div>
        <div><div class="font-heading" style="font-size:26px;line-height:1;color:var(--color-accent)"><?= $stats['total_downloads'] ?: 0 ?></div><div style="font-size:12px;opacity:.6;margin-top:2px">скачиваний</div></div>
        <div><div class="font-heading" style="font-size:26px;line-height:1" id="followersCount"><?= $followers_count ?></div><div style="font-size:12px;opacity:.6;margin-top:2px">подписчиков</div></div>
    </div>
</div>

<!-- Модальное окно редактирования профиля -->
<?php if($is_owner): ?>
<div id="editModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.7);backdrop-filter:blur(4px)">
    <div class="elev-lg w-full max-w-lg relative" style="background:var(--color-surface);border-radius:36px;padding:28px">
        <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="absolute top-5 right-5 transition-colors" style="opacity:.5">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
        <h2 class="mb-6" style="font-size:21px">Настройки профиля</h2>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="edit_profile">

            <div class="field">
                <label>О себе (описание)</label>
                <textarea name="description" rows="3" placeholder="Расскажите о себе или своих постройках..." class="input"><?= htmlspecialchars($profile_user['description'] ?? '') ?></textarea>
            </div>

            <div class="field">
                <label>Контакты (Discord, TG и т.д.)</label>
                <input type="text" name="contacts" value="<?= htmlspecialchars($profile_user['contacts'] ?? '') ?>" placeholder="<?= htmlspecialchars($contacts) ?>" class="input">
            </div>

            <div style="background:var(--color-bg);border-radius:22px;padding:16px 18px">
                <label class="block text-sm font-medium mb-3" style="opacity:.7">Форма аватара</label>
                <div class="flex gap-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="avatar_shape" value="round" <?= $avatar_shape === 'round' ? 'checked' : '' ?> style="accent-color:var(--color-accent);width:16px;height:16px">
                        <span>Круглый</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="avatar_shape" value="square" <?= $avatar_shape === 'square' ? 'checked' : '' ?> style="accent-color:var(--color-accent);width:16px;height:16px">
                        <span>Квадратный</span>
                    </label>
                </div>
                <p class="text-xs mt-3 leading-relaxed" style="opacity:.55">* Чтобы обновить саму фотографию профиля, перезайдите на сайт через Telegram-виджет.</p>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="padding:14px">
                Сохранить изменения
            </button>
        </form>

        <hr class="my-6" style="border-color:var(--color-divider)">

        <form method="POST" onsubmit="return confirm('Вы уверены? Это удалит ваш аккаунт безвозвратно. Ваши схемы потеряют автора.');">
            <input type="hidden" name="action" value="delete_account">
            <button type="submit" class="btn btn-block justify-center" style="background:color-mix(in srgb, #ef4444 12%, transparent);color:#ff9d9d">
                <i class="fa-solid fa-trash-can"></i> Удалить аккаунт
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<h2 class="mb-6 flex items-center gap-3" style="font-size:22px">
    <i class="fa-solid fa-list-ul" style="opacity:.5"></i> Схемы автора
</h2>

<!-- Сетка схем пользователя -->
<?php if(empty($user_schematics)): ?>
    <div class="text-center" style="padding:56px 20px;background:var(--color-surface);border-radius:44px">
        <div style="font-size:44px;opacity:.3;margin-bottom:14px"><i class="fa-regular fa-folder-open"></i></div>
        <p style="opacity:.6">Этот пользователь еще не загрузил ни одной схемы.</p>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
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
    if (btn.classList.contains('btn-primary')) {
        // Становимся подписанными
        btn.className = "btn btn-secondary";
        icon.className = "fa-solid fa-check";
        text.innerText = "Вы подписаны";
        countEl.innerText = currentCount + 1;
    } else {
        // Отписываемся
        btn.className = "btn btn-primary";
        icon.className = "fa-solid fa-user-plus";
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