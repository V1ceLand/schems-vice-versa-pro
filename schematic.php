
<?php
require_once __DIR__ . '/config.php';

$id = (int)($_GET['id'] ?? 0);
$current_user_id = $_SESSION['user_id'] ?? 0;

// Получаем схему для проверки и SEO
$stmt = $pdo->prepare("
    SELECT s.*, u.username, u.first_name, u.photo_url, u.avatar_shape,
           COALESCE((SELECT COUNT(*) FROM user_downloads ud WHERE ud.schematic_id = s.id), 0) as real_downloads,
           COALESCE((SELECT AVG(rating) FROM user_ratings ur WHERE ur.schematic_id = s.id), 0) as avg_rating,
           COALESCE((SELECT COUNT(*) FROM user_ratings ur WHERE ur.schematic_id = s.id), 0) as rating_count
    FROM schematics s 
    JOIN users u ON s.user_id = u.id 
    WHERE s.id = ?
");
$stmt->execute([$id]);
$schem = $stmt->fetch();

if (!$schem) {
    $page_title = "Схема не найдена | SchemHub";
    require_once __DIR__ . '/header.php';
    echo "<div class='text-center py-20 text-xl text-red-400'>Схема не найдена или была удалена.</div>";
    require_once __DIR__ . '/footer.php';
    exit;
}

// Задаем динамические SEO мета-теги для header.php
$page_title = $schem['title'] . " — Скачать для Litematica | SchemHub";
$page_description = mb_strimwidth($schem['description'], 0, 150, "...");
$page_image = !empty($schem['thumbnail_path']) ? "https://" . $_SERVER['HTTP_HOST'] . get_image_url($schem['thumbnail_path']) : null;

// --- AJAX ОБРАБОТКА (Моментальная оценка звездами) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rate') {
    header('Content-Type: application/json');
    if ($current_user_id > 0) {
        $rating = (int)$_POST['rating'];
        if ($rating >= 1 && $rating <= 5) {
            $stmt = $pdo->prepare("INSERT INTO user_ratings (user_id, schematic_id, rating) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE rating = VALUES(rating)");
            $stmt->execute([$current_user_id, $id, $rating]);
            echo json_encode(['status' => 'success']);
        }
    }
    exit;
}

// Удаление схемы (Только для владельца)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_schematic') {
    if ($schem['user_id'] == $current_user_id) {
        $v_stmt = $pdo->prepare("SELECT file_path FROM schematic_versions WHERE schematic_id = ?");
        $v_stmt->execute([$id]);
        while($v = $v_stmt->fetch()) { if (file_exists($v['file_path'])) unlink($v['file_path']); }
        if (file_exists($schem['file_path'])) unlink($schem['file_path']);
        if ($schem['thumbnail_path']) { $thumbs = explode(',', $schem['thumbnail_path']); foreach($thumbs as $t) { if (file_exists($t)) unlink($t); } }
        
        $pdo->prepare("DELETE FROM schematic_tags WHERE schematic_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM user_downloads WHERE schematic_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM user_ratings WHERE schematic_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM schematic_versions WHERE schematic_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM schematics WHERE id = ?")->execute([$id]);
        
        header("Location: /catalog");
        exit;
    }
}

require_once __DIR__ . '/header.php';

$is_owner = ($schem['user_id'] == $current_user_id && $current_user_id > 0);

// Уникальный просмотр: один раз на аккаунт, а для гостя — один раз за текущую сессию.
// Идентификатор гостя не содержит персональных данных и исчезает вместе с серверной сессией.
if (empty($_SESSION['schemhub_viewer_id'])) {
    $_SESSION['schemhub_viewer_id'] = bin2hex(random_bytes(24));
}
$viewer_key = $current_user_id > 0 ? 'account:' . $current_user_id : 'session:' . $_SESSION['schemhub_viewer_id'];
try {
    $view_stmt = $pdo->prepare("INSERT IGNORE INTO schematic_views (schematic_id, viewer_key, user_id) VALUES (?, ?, ?)");
    $view_stmt->execute([$id, $viewer_key, $current_user_id ?: null]);
    if ($view_stmt->rowCount() === 1) {
        $pdo->prepare("UPDATE schematics SET views = views + 1 WHERE id = ?")->execute([$id]);
    }
} catch (PDOException $e) {
    // До применения миграции каталог остаётся доступен; ошибка не должна ломать просмотр схемы.
    error_log('SchemHub unique view: ' . $e->getMessage());
}

// Получаем теги схемы
$tags_stmt = $pdo->prepare("SELECT t.name, t.category FROM schematic_tags st JOIN tags t ON st.tag_id = t.id WHERE st.schematic_id = ?");
$tags_stmt->execute([$id]);
$tags = $tags_stmt->fetchAll();

// Получаем историю версий (все схемы из этой же группы)
$root_id = $schem['parent_id'] ?? $schem['id'];
$versions_stmt = $pdo->prepare("
    SELECT id, title, version_label, created_at 
    FROM schematics 
    WHERE id = ? OR parent_id = ? 
    ORDER BY created_at DESC
");
$versions_stmt->execute([$root_id, $root_id]);
$all_versions = $versions_stmt->fetchAll();

// Проверяем, является ли текущая версия последней
$is_latest = ($all_versions[0]['id'] == $id);

// Оценка текущего пользователя
$user_rating = 0;
if ($current_user_id) {
    $r_stmt = $pdo->prepare("SELECT rating FROM user_ratings WHERE user_id = ? AND schematic_id = ?");
    $r_stmt->execute([$current_user_id, $id]);
    $user_rating = $r_stmt->fetchColumn() ?: 0;
}

$author_avatar = get_image_url($schem['photo_url'] ?? 'https://ui-avatars.com/api/?background=2563eb&color=fff&name='.urlencode($schem['first_name']));
$avatar_class = ($schem['avatar_shape'] === 'square') ? 'rounded-xl' : 'rounded-full';
?>

<!-- Блок информации о схеме -->
<div class="bg-zinc-900 border border-zinc-800 rounded-3xl p-8 mb-8 flex flex-col md:flex-row justify-between items-start gap-8">
    <div class="flex-1 w-full">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
            <div class="flex items-center gap-4">
                <h1 class="text-3xl font-bold text-white"><?= htmlspecialchars($schem['title']) ?></h1>
                <?php if(!empty($schem['version_label'])): ?>
                    <span class="bg-emerald-500/20 text-emerald-400 px-3 py-1 rounded-xl text-sm font-bold border border-emerald-500/20">v<?= htmlspecialchars($schem['version_label']) ?></span>
                <?php endif; ?>
            </div>
            
            <div class="flex items-center gap-3 bg-zinc-950 px-4 py-2 rounded-2xl border border-zinc-800">
                <div class="text-amber-400 text-lg font-bold" id="avgRating"><?= number_format($schem['avg_rating'], 1) ?></div>
                <div class="flex text-sm">
                    <?php for($i=1; $i<=5; $i++): ?>
                        <i class="<?= $i <= round($schem['avg_rating']) ? 'fa-solid text-amber-400' : 'fa-regular text-zinc-600' ?> fa-star"></i>
                    <?php endfor; ?>
                </div>
                <div class="text-zinc-500 text-xs">(<span id="ratingCount"><?= $schem['rating_count'] ?></span> оценок)</div>
            </div>
        </div>
        
        <div class="flex flex-wrap gap-2 mb-6">
            <?php if(!empty($schem['style']) && isset($BUILD_STYLES[$schem['style']])): ?>
                <span class="px-3 py-1 bg-purple-500/10 text-purple-400 rounded-lg text-xs font-bold border border-purple-500/20 uppercase tracking-wider flex items-center gap-1.5">
                    <i class="fa-solid fa-palette"></i> <?= htmlspecialchars($BUILD_STYLES[$schem['style']]) ?>
                </span>
            <?php endif; ?>
            
            <?php if(!empty($schem['type']) && isset($BUILD_TYPES[$schem['type']])): ?>
                <span class="px-3 py-1 bg-blue-500/10 text-blue-400 rounded-lg text-xs font-bold border border-blue-500/20 uppercase tracking-wider flex items-center gap-1.5">
                    <i class="fa-solid fa-layer-group"></i> <?= htmlspecialchars($BUILD_TYPES[$schem['type']]) ?>
                </span>
            <?php endif; ?>
        </div>
        
        <div class="bg-zinc-950/50 p-5 rounded-2xl border border-zinc-800 text-zinc-300 leading-relaxed mb-6 whitespace-pre-wrap"><?= htmlspecialchars($schem['description']) ?></div>
        
        <div class="flex flex-wrap items-center gap-6">
            <a href="<?= get_user_url($schem['user_id'], $schem['username'] ?? $schem['first_name']) ?>" class="flex items-center gap-3 hover:bg-zinc-800 p-2 pr-4 rounded-xl transition-colors border border-transparent hover:border-zinc-700">
                <img src="<?= htmlspecialchars($author_avatar) ?>" class="w-10 h-10 <?= $avatar_class ?> border border-zinc-700 object-cover">
                <div>
                    <div class="text-xs text-zinc-500 uppercase font-bold tracking-wider mb-0.5">Автор</div>
                    <div class="text-blue-400 font-semibold leading-none">@<?= htmlspecialchars($schem['username'] ?? $schem['first_name']) ?></div>
                </div>
            </a>
            
            <div class="hidden md:block h-10 w-px bg-zinc-800"></div>
            
            <div class="flex gap-6 text-sm font-mono text-zinc-400">
                <span title="Просмотры"><i class="fa-solid fa-eye text-zinc-600 mr-2 text-lg"></i><?= $schem['views'] + 1 ?></span>
                <span title="Уникальные скачивания"><i class="fa-solid fa-download text-emerald-600 mr-2 text-lg"></i><?= $schem['real_downloads'] ?></span>
                <span title="Размер файла"><i class="fa-solid fa-hard-drive text-blue-600 mr-2 text-lg"></i><?= round($schem['file_size'] / 1024, 1) ?> KB</span>
            </div>
        </div>
    </div>
    
    <div class="w-full md:w-auto flex flex-col gap-3 shrink-0">
        <!-- Кнопка скачивания с ЧПУ маршрутом -->
        <a href="/download/<?= $schem['id'] ?>" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-4 px-8 rounded-2xl transition-all shadow-[0_0_20px_rgba(59,130,246,0.3)] hover:shadow-[0_0_30px_rgba(59,130,246,0.5)] flex items-center justify-center gap-3 hover:-translate-y-1">
            <i class="fa-solid fa-download text-xl"></i>
            <div class="text-left">
                <div class="leading-none mb-1">Скачать .litematic</div>
                <div class="text-[10px] font-normal opacity-70">Последняя версия</div>
            </div>
        </a>
        <div class="text-center text-xs text-zinc-500 font-mono mb-4"><?= htmlspecialchars(basename($schem['file_name'])) ?></div>

        <!-- Поставить оценку (Для авторизованных) -->
        <?php if($current_user_id && !$is_owner): ?>
            <div class="bg-zinc-950 border border-zinc-800 p-4 rounded-2xl text-center">
                <div class="text-xs text-zinc-400 font-medium mb-2">Оценить постройку:</div>
                <div class="flex justify-center gap-2 flex-row-reverse" id="starContainer">
                    <?php for($i=5; $i>=1; $i--): ?>
                        <i class="rating-star cursor-pointer text-2xl transition-colors peer 
                                  <?= $i <= $user_rating ? 'fa-solid text-amber-400' : 'fa-regular text-zinc-600 hover:text-amber-400 peer-hover:text-amber-400' ?>" 
                           data-val="<?= $i ?>" onclick="rateSchematic(<?= $i ?>)"></i>
                    <?php endfor; ?>
                </div>
                <div id="ratingThanks" class="text-xs text-emerald-400 mt-2 h-4 opacity-0 transition-opacity">Спасибо за оценку!</div>
            </div>
        <?php elseif(!$current_user_id): ?>
            <div class="text-xs text-zinc-500 text-center bg-zinc-950 p-3 rounded-xl border border-zinc-800">
                Авторизуйтесь, чтобы оценить
            </div>
        <?php endif; ?>

        <!-- Инструменты автора -->
        <?php if($is_owner): ?>
            <div class="mt-2 space-y-2">
                <a href="/upload?update_id=<?= $id ?>" class="block w-full text-center bg-zinc-800 hover:bg-emerald-600 text-white py-2.5 rounded-xl text-sm font-medium transition-colors">
                    <i class="fa-solid fa-clock-rotate-left mr-1"></i> Опубликовать новую версию
                </a>
                <a href="/edit.php?id=<?= $id ?>" class="block w-full text-center bg-zinc-800 hover:bg-blue-600 text-white py-2.5 rounded-xl text-sm font-medium transition-colors border border-zinc-700">
                    <i class="fa-solid fa-pen-to-square mr-1"></i> Редактировать параметры
                </a>
                <form method="POST" onsubmit="return confirm('Вы уверены? Это удалит ЭТУ версию навсегда!');">
                    <input type="hidden" name="action" value="delete_schematic">
                    <button type="submit" class="w-full text-center bg-zinc-800 hover:bg-red-600 text-red-400 hover:text-white py-2.5 rounded-xl text-sm font-medium transition-colors border border-transparent hover:border-red-500">
                        <i class="fa-solid fa-trash mr-1"></i> Удалить эту версию
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- БЛОК ВЕРСИЙ -->
<?php if(count($all_versions) > 1): ?>
    <div class="bg-zinc-900 border border-zinc-800 rounded-3xl p-6 md:p-8 mb-8">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold flex items-center gap-3">
                <i class="fa-solid fa-clock-rotate-left text-blue-500"></i> История версий
            </h2>
            <?php if(!$is_latest): ?>
                <div class="bg-amber-500/10 text-amber-500 text-xs font-bold px-3 py-1 rounded-full border border-amber-500/20 animate-pulse">
                    <i class="fa-solid fa-triangle-exclamation mr-1"></i> Доступна новая версия
                </div>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach($all_versions as $v): ?>
                <a href="<?= get_schematic_url($v['id'], $v['title']) ?>" 
                   class="p-4 rounded-2xl border transition-all flex items-center justify-between group <?= $v['id'] == $id ? 'bg-blue-600/10 border-blue-500/50' : 'bg-zinc-950 border-zinc-800 hover:border-zinc-600' ?>">
                    <div class="overflow-hidden">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-[10px] bg-zinc-800 text-zinc-400 px-1.5 py-0.5 rounded font-bold uppercase tracking-tighter">v<?= htmlspecialchars($v['version_label'] ?? '1.0') ?></span>
                            <?php if($v['id'] == $all_versions[0]['id']): ?>
                                <span class="text-[10px] bg-emerald-500/20 text-emerald-400 px-1.5 py-0.5 rounded uppercase font-black">Latest</span>
                            <?php endif; ?>
                        </div>
                        <div class="font-bold text-white group-hover:text-blue-400 transition-colors truncate text-sm"><?= htmlspecialchars($v['title']) ?></div>
                        <div class="text-[10px] text-zinc-500 font-mono mt-1"><?= date('d.m.Y', strtotime($v['created_at'])) ?></div>
                    </div>
                    <?php if($v['id'] == $id): ?>
                        <i class="fa-solid fa-circle-check text-blue-500"></i>
                    <?php else: ?>
                        <i class="fa-solid fa-chevron-right text-zinc-700 group-hover:text-zinc-400 transition-all group-hover:translate-x-1"></i>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<script>
async function rateSchematic(rating) {
    const stars = document.querySelectorAll('.rating-star');
    stars.forEach(star => {
        const val = parseInt(star.getAttribute('data-val'));
        if (val <= rating) {
            star.className = 'rating-star cursor-pointer text-2xl transition-colors peer fa-solid text-amber-400';
        } else {
            star.className = 'rating-star cursor-pointer text-2xl transition-colors peer fa-regular text-zinc-600 hover:text-amber-400 peer-hover:text-amber-400';
        }
    });
    
    const thanks = document.getElementById('ratingThanks');
    thanks.classList.remove('opacity-0');
    setTimeout(() => thanks.classList.add('opacity-0'), 3000);

    let fd = new FormData();
    fd.append('action', 'rate');
    fd.append('rating', rating);
    // Отправляем на текущий URL, чтобы не зависеть от .php расширения
    await fetch(window.location.href, { method: 'POST', body: fd });
}
</script>

<!-- ================= ЭКРАН ПРОСМОТРА СХЕМЫ ================= -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r134/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.134.0/examples/js/controls/OrbitControls.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.134.0/examples/js/controls/PointerLockControls.js"></script>

<div id="view-viewer" class="relative">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-zinc-900 rounded-3xl p-6" id="sizes">
            <div class="text-center py-6 text-zinc-500 animate-pulse">Анализ файла...</div>
        </div>
        <div class="bg-zinc-900 rounded-3xl p-6 col-span-2">
            <h2 class="text-lg font-semibold mb-4 flex items-center gap-2">
                <i class="fa-solid fa-layer-group text-emerald-400"></i> Метаданные и Регионы
            </h2>
            <div id="metadata" class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm mb-4 text-zinc-500">Загрузка...</div>
            <div id="regions" class="grid grid-cols-1 gap-2"></div>
        </div>
    </div>

    <!-- 3D Контейнер -->
    <div class="bg-zinc-900 rounded-3xl p-6 mb-8">
        <div class="flex flex-wrap gap-3 items-center justify-between mb-4">
            <h2 class="text-lg font-semibold flex items-center gap-2">
                <i class="fa-solid fa-cube text-purple-400"></i> 3D модель схемы
            </h2>
            <div class="flex flex-wrap items-center gap-2">
                <button onclick="resetViewerCamera()" class="px-4 py-2 bg-zinc-800 hover:bg-zinc-700 rounded-xl text-xs font-medium flex items-center gap-2 transition-colors" title="Вернуть камеру к исходному ракурсу">
                    <i class="fa-solid fa-camera-rotate text-emerald-400"></i> Ракурс
                </button>
                <button onclick="toggleAutoRotate()" id="autoRotateBtn" class="px-4 py-2 bg-zinc-800 hover:bg-zinc-700 rounded-xl text-xs font-medium flex items-center gap-2 transition-colors">
                    <i class="fa-solid fa-rotate text-sky-400"></i> <span id="autoRotateText">Автоповорот: ВЫКЛ</span>
                </button>
                <button onclick="toggleBoundingBox()" id="toggleBBoxBtn" class="px-4 py-2 bg-zinc-800 hover:bg-zinc-700 rounded-xl text-xs font-medium flex items-center gap-2 transition-colors">
                    <i class="fa-solid fa-border-all text-pink-400"></i> <span id="bboxText">Границы: ВКЛ</span>
                </button>
                <button onclick="toggleTooltip()" id="tooltipBtn" class="px-4 py-2 bg-zinc-800 hover:bg-zinc-700 rounded-xl text-xs font-medium flex items-center gap-2 transition-colors">
                    <i class="fa-solid fa-message text-indigo-400" id="tooltipIcon"></i> <span id="tooltipText">Подсказка: ВКЛ</span>
                </button>
                <div class="flex items-center gap-2 bg-zinc-800 border border-zinc-700 rounded-xl px-3 py-1.5 h-8">
                    <i class="fa-solid fa-gauge-high text-amber-400 text-xs"></i>
                    <span class="text-xs font-medium text-zinc-300">Скорость:</span>
                    <input type="range" id="speedSlider" min="0.1" max="3.0" step="0.1" value="1.0" class="w-20 accent-amber-500 h-1.5 bg-zinc-950 rounded-lg appearance-none cursor-pointer">
                    <input type="number" id="speedInput" min="0.1" max="3.0" step="0.1" value="1.0" class="w-12 bg-zinc-950 border border-zinc-700 text-white text-xs rounded px-1 py-0.5 text-center outline-none focus:border-amber-500 transition-colors">
                    <span class="text-xs text-zinc-500">x</span>
                </div>
                <button onclick="toggleFlightMode()" id="flightModeBtn" class="px-4 py-2 bg-zinc-800 hover:bg-zinc-700 rounded-xl text-xs font-medium flex items-center gap-2 transition-colors" title="Свободный: летим куда смотрим. Плоский: высота меняется только на Пробел/Shift">
                    <i class="fa-solid fa-arrows-up-down-left-right text-emerald-400" id="modeIcon"></i> <span id="flightModeText">Режим: Свободный</span>
                </button>
                <button onclick="enterFlyMode()" class="px-4 py-2 bg-sky-500/20 text-sky-400 hover:bg-sky-500/30 rounded-xl text-xs font-medium flex items-center gap-2 transition-colors">
                    <i class="fa-solid fa-plane"></i> Камера (WASD)
                </button>
            </div>
        </div>
        
        <div id="threeContainer" class="relative overflow-hidden rounded-2xl border border-white/10 bg-[#07111f] shadow-inner shadow-black/40" style="height: min(68vh, 680px); min-height: 440px;">
            <canvas id="threeCanvas" class="w-full h-full outline-none" style="image-rendering: pixelated;"></canvas>
            
            <div class="absolute top-4 left-4 bg-zinc-950/80 px-5 py-3 rounded-2xl font-mono text-xs flex gap-8 pointer-events-none z-10 shadow-lg border border-white/10 backdrop-blur-md">
                <div>X <span id="dimX" class="text-sky-400 font-bold">0</span></div>
                <div>Y <span id="dimY" class="text-sky-400 font-bold">0</span></div>
                <div>Z <span id="dimZ" class="text-sky-400 font-bold">0</span></div>
            </div>

            <div id="blockTooltip" class="hidden absolute bottom-4 left-4 bg-zinc-950/90 backdrop-blur-md p-4 rounded-2xl z-20 pointer-events-none flex gap-5 items-center border border-zinc-800 shadow-2xl transition-opacity duration-200">
                <div class="w-12 h-12 relative flex-shrink-0" style="perspective: 1000px;">
                    <div class="w-full h-full relative" style="transform-style: preserve-3d; transform: rotateX(-30deg) rotateY(-45deg);">
                        <div id="tt-top" class="absolute inset-0 border border-black/30" style="transform: rotateX(90deg) translateZ(24px); image-rendering: pixelated; background-size: cover; filter: brightness(1.15);"></div>
                        <div id="tt-front" class="absolute inset-0 border border-black/30" style="transform: translateZ(24px); image-rendering: pixelated; background-size: cover; filter: brightness(1.0);"></div>
                        <div id="tt-right" class="absolute inset-0 border border-black/30" style="transform: rotateY(90deg) translateZ(24px); image-rendering: pixelated; background-size: cover; filter: brightness(0.75);"></div>
                    </div>
                </div>
                <div class="flex flex-col text-zinc-300 pointer-events-none min-w-[120px]">
                    <div id="tt-name" class="font-bold text-white text-sm tracking-wide leading-tight">Block Name</div>
                    <div id="tt-id" class="text-zinc-500 text-[10px] mb-1.5">minecraft:block</div>
                    <div class="flex gap-4 text-xs">
                        <div title="Количество"><i class="fa-solid fa-cubes text-emerald-500 mr-1.5"></i><span id="tt-count" class="font-mono text-emerald-400 font-semibold">0</span></div>
                        <div title="Координаты блока"><i class="fa-solid fa-location-dot text-sky-500 mr-1.5"></i><span id="tt-coords" class="font-mono text-sky-400 font-semibold">0, 0, 0</span></div>
                    </div>
                    <div class="mt-1.5 text-[10px] text-zinc-500">Ctrl+C — скопировать ID блока</div>
                </div>
            </div>

            <div id="crosshair" class="hidden absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 flex items-center justify-center w-8 h-8 pointer-events-none z-10">
                <div class="absolute w-3 h-0.5 bg-white/70 shadow-[0_0_2px_black]"></div>
                <div class="absolute w-0.5 h-3 bg-white/70 shadow-[0_0_2px_black]"></div>
            </div>

            <div id="loading3D" class="absolute inset-0 bg-zinc-950/80 backdrop-blur-sm flex items-center justify-center z-50 transition-opacity duration-500">
                <div class="bg-zinc-900 border border-zinc-700 p-6 rounded-2xl text-center w-80 shadow-2xl">
                    <h3 id="loadingTitle" class="text-sm font-bold mb-2 text-white flex justify-center items-center gap-2">
                        <i class="fa-solid fa-spinner fa-spin text-blue-500"></i> Загрузка с сервера...
                    </h3>
                    <p id="loadingDesc" class="text-zinc-400 text-xs mb-3 font-mono">Скачивание файла</p>
                    <div class="w-full h-1.5 bg-zinc-800 rounded-full overflow-hidden">
                        <div id="loadingBar" class="h-full bg-blue-500 w-0 transition-all duration-300"></div>
                    </div>
                </div>
            </div>
        </div>
        <p class="text-center text-xs text-zinc-500 mt-3">Все регионы схемы собраны в одной сцене. ЛКМ = вращать • ПКМ = двигать • Колёсико = зум • Камера WASD: выход на ESC.</p>
    </div>

    <!-- 2D Превью -->
    <div class="bg-zinc-900 rounded-3xl p-6">
        <h2 class="text-lg font-semibold flex items-center gap-2 mb-4">
            <i class="fa-solid fa-layer-group text-rose-400"></i> 2D превью (по слоям)
        </h2>
        <div class="flex justify-center mb-6">
            <div id="layerCanvasWrap" class="relative inline-block">
                <canvas id="previewCanvas" width="560" height="560" style="image-rendering: pixelated; border-radius: 16px; background: #0f172a;"></canvas>
                <div id="layerTooltip" class="hidden absolute z-20 min-w-48 pointer-events-none rounded-xl border border-white/10 bg-zinc-950/95 p-3 text-left shadow-2xl backdrop-blur-md">
                    <div class="flex items-center gap-3"><div id="layerBlockIcon" class="h-9 w-9 rounded-lg border border-white/10 bg-zinc-800 bg-cover" style="image-rendering:pixelated"></div><div><div id="layerBlockName" class="text-sm font-bold text-white">Block</div><div id="layerBlockId" class="text-[10px] text-zinc-400">minecraft:block</div></div></div>
                    <div id="layerBlockCoords" class="mt-2 font-mono text-[11px] text-emerald-300"></div><div class="mt-1 text-[10px] text-zinc-500">Ctrl+C — скопировать ID</div>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-4 px-4 bg-zinc-950 p-4 rounded-2xl border border-zinc-800">
            <span class="text-sm font-medium text-zinc-400 w-16">Слой Y</span>
            <input type="range" id="ySlider" min="0" max="100" value="0" class="flex-1 accent-blue-500 cursor-pointer" disabled>
            <span id="currentY" class="font-mono text-lg font-bold w-12 text-right text-blue-400">0</span>
        </div>
    </div>
</div>

<script type="module">
    import * as NBT from "https://cdn.jsdelivr.net/npm/nbtify@2.2.0/+esm";
    if (typeof BigInt !== 'undefined' && !BigInt.prototype.toJSON) { BigInt.prototype.toJSON = function () { return this.toString(); }; }

    let currentParsed = null, currentRegion = null, currentHeight = 0;
    let scene, camera, renderer, modelGroup, controls, flyControls;
    let autoRotate = false, animationFrameId = null;
    let currentGenerationId = 0, isGenerating = false;
    let flightMode = 'free', speedMult = 1;
    let globalPalette = [], globalCounts = [], currentDims = {sx:0, sy:0, sz:0};
    let tooltipEnabled = true, lastHoveredId = null, lastRaycastTime = 0, hoveredBlock = null, currentLayerLayout = null;
    const raycaster = new THREE.Raycaster();
    const mouse = new THREE.Vector2(-10, -10);
    let mouseMoved = false, isMoving = false, isFlying = false;
    let moveState = { forward: false, backward: false, left: false, right: false, up: false, down: false };
    let clock = new THREE.Clock(), flySpeed = 15;
    const invisibleBlocks = ['air', 'light', 'barrier', 'structure_void', 'cave_air', 'void_air'];

    async function fetchSchematic(url) {
        try {
            document.getElementById('loadingBar').style.width = '10%';
            const response = await fetch(url);
            if (!response.ok) throw new Error("Ошибка загрузки файла");
            document.getElementById('loadingBar').style.width = '40%';
            document.getElementById('loadingDesc').textContent = 'Чтение NBT...';
            
            const buffer = await response.arrayBuffer();
            currentParsed = await NBT.read(buffer, { endian: "big" });
            startViewer(currentParsed);
        } catch (err) {
            document.getElementById('loadingTitle').innerHTML = '<i class="fa-solid fa-triangle-exclamation text-red-500"></i> Ошибка';
            document.getElementById('loadingDesc').textContent = err.message;
            document.getElementById('loadingBar').classList.add('bg-red-500');
        }
    }

    // ИСПОЛЬЗУЕМ АБСОЛЮТНЫЙ ПУТЬ ДЛЯ ЗАГРУЗКИ ФАЙЛА (важно для SEO ссылок)
    fetchSchematic('<?= get_image_url($schem['file_path']) ?>');

    function findDeep(obj, targetKey) {
        if (!obj || typeof obj !== 'object') return null;
        if (targetKey in obj) return obj[targetKey];
        for (let key in obj) { const found = findDeep(obj[key], targetKey); if (found !== null) return found; }
        return null;
    }

    function startViewer(parsed) {
        const regionsObj = findDeep(parsed, 'Regions') || {};
        const regionNames = Object.keys(regionsObj);
        let totalBlocks = 0;

        if (regionNames.length) {
            currentRegion = regionsObj[regionNames[0]];
            const sx = Math.abs(Number(currentRegion.Size?.x ?? 0)), sy = Math.abs(Number(currentRegion.Size?.y ?? 0)), sz = Math.abs(Number(currentRegion.Size?.z ?? 0));
            totalBlocks = sx * sy * sz;
            currentHeight = sy;

            document.getElementById('sizes').innerHTML = `
                <div class="text-xs uppercase tracking-widest text-zinc-500 mb-1">ВСЕГО БЛОКОВ</div>
                <div class="text-5xl font-bold text-white mb-4">${totalBlocks.toLocaleString('ru-RU')}</div>
                <div class="text-zinc-400 text-sm">Габариты: <span class="text-emerald-400 font-mono">${sx}×${sy}×${sz}</span></div>`;

            document.getElementById('metadata').innerHTML = `
                <div class="text-zinc-400">Root</div><div class="font-mono">${parsed.name || 'LitematicaSchematic'}</div>
                <div class="text-zinc-400">Версия</div><div class="font-mono">${findDeep(parsed, 'Version') ?? '—'}</div>
                <div class="text-zinc-400">DataVersion</div><div class="font-mono">${findDeep(parsed, 'MinecraftDataVersion') ?? '—'}</div>`;

            document.getElementById('regions').innerHTML = '';
            regionNames.forEach(name => {
                const r = regionsObj[name];
                const rsx = Math.abs(Number(r.Size?.x ?? 0)), rsy = Math.abs(Number(r.Size?.y ?? 0)), rsz = Math.abs(Number(r.Size?.z ?? 0));
                document.getElementById('regions').innerHTML += `<div class="bg-zinc-800 rounded-xl p-3 flex justify-between items-center"><div class="font-semibold text-sm">${name}</div><div class="px-2 py-1 bg-zinc-900 rounded-lg text-xs font-mono text-emerald-400">${rsx}×${rsy}×${rsz}</div></div>`;
            });
        }

        const slider = document.getElementById('ySlider');
        slider.max = currentHeight - 1;
        slider.value = Math.floor(currentHeight / 2);
        slider.disabled = false;
        slider.oninput = () => renderCurrentLayer();
        renderCurrentLayer();

        init3DAsync();
    }

    const canvasTextureCache = {};
    function loadCanvasTexture(name) { if (!name) return Promise.resolve(null); if (canvasTextureCache[name]) return canvasTextureCache[name]; canvasTextureCache[name] = new Promise(resolve => { const image = new Image(); image.crossOrigin = 'anonymous'; image.onload = () => resolve(image); image.onerror = () => resolve(null); image.src = `${modelBaseUrl}textures/block/${name}.png`; }); return canvasTextureCache[name]; }
    function renderCurrentLayer() {
        if (!currentRegion) return;
        const y = parseInt(document.getElementById('ySlider').value);
        document.getElementById('currentY').textContent = y;
        const ctx = document.getElementById('previewCanvas').getContext('2d');
        const sx = Math.abs(Number(currentRegion.Size?.x ?? 0)), sz = Math.abs(Number(currentRegion.Size?.z ?? 0));
        if (sx === 0 || sz === 0) return;

        const palette = currentRegion.BlockStatePalette || [];
        const blocks = decodeBlockStates(currentRegion);
        const cellSize = Math.max(1, Math.floor(560 / Math.max(sx, sz)));
        const offsetX = Math.floor((560 - sx * cellSize) / 2), offsetZ = Math.floor((560 - sz * cellSize) / 2);

        ctx.clearRect(0, 0, 560, 560);
        const textureNames = new Set();
        for (let z = 0; z < sz; z++) {
            for (let x = 0; x < sx; x++) {
                const id = blocks[(y * sz * sx) + (z * sx) + x];
                const state = palette[id], name = state ? state.Name || '' : '';
                ctx.fillStyle = isInvisible(name) ? 'rgba(30,41,59,0.4)' : getBlockColor(name);
                ctx.fillRect(offsetX + x * cellSize, offsetZ + z * cellSize, cellSize, cellSize);
                if (!isInvisible(name)) textureNames.add(getTextureNames(name, state?.Properties)[2]);
            }
        }
        currentLayerLayout = {y, sx, sz, cellSize, offsetX, offsetZ, palette, blocks};
        Promise.all([...textureNames].map(async name => [name, await loadCanvasTexture(name)])).then(loaded => {
            if (!currentLayerLayout || currentLayerLayout.y !== y) return;
            const images = Object.fromEntries(loaded);
            for (let z = 0; z < sz; z++) for (let x = 0; x < sx; x++) {
                const state = palette[blocks[(y * sz * sx) + (z * sx) + x]], texture = state && getTextureNames(state.Name || '', state.Properties)[2], image = images[texture];
                if (image) ctx.drawImage(image, offsetX + x * cellSize, offsetZ + z * cellSize, cellSize, cellSize);
            }
        });
    }

    function decodeBlockStates(region) { const sx = Math.abs(Number(region.Size?.x ?? 0)), sy = Math.abs(Number(region.Size?.y ?? 0)), sz = Math.abs(Number(region.Size?.z ?? 0)); const volume = sx * sy * sz; const blocks = new Uint16Array(volume); const paletteLen = (region.BlockStatePalette || []).length; const longs = region.BlockStates || []; if (volume === 0 || paletteLen === 0 || longs.length === 0) return blocks; let bitsPerEntry = Math.max(2, Math.ceil(Math.log2(paletteLen))); const entriesPerLongModern = Math.floor(64 / bitsPerEntry); const expectedModern = Math.ceil(volume / entriesPerLongModern); const mask = (1n << BigInt(bitsPerEntry)) - 1n; if (longs.length === expectedModern) { let blockIdx = 0; for (let i = 0; i < longs.length; i++) { let currentLong = BigInt(longs[i]); for (let j = 0; j < entriesPerLongModern; j++) { if (blockIdx >= volume) break; let id = Number(currentLong & mask); blocks[blockIdx] = id < paletteLen ? id : 0; currentLong >>= BigInt(bitsPerEntry); blockIdx++; } } } else { let longIdx = 0, bitOffset = 0n, currentLong = BigInt(longs[0]), bpe = BigInt(bitsPerEntry); for (let i = 0; i < volume; i++) { let val = 0n, bitsRead = 0n; while (bitsRead < bpe) { let bitsAvail = 64n - bitOffset; let bitsToRead = (bpe - bitsRead) < bitsAvail ? (bpe - bitsRead) : bitsAvail; let localMask = (1n << bitsToRead) - 1n; let part = (currentLong >> bitOffset) & localMask; val |= (part << bitsRead); bitsRead += bitsToRead; bitOffset += bitsToRead; if (bitOffset >= 64n) { longIdx++; currentLong = longIdx < longs.length ? BigInt(longs[longIdx]) : 0n; bitOffset = 0n; } } blocks[i] = Number(val) < paletteLen ? Number(val) : 0; } } return blocks; }
    function isInvisible(name) { return name ? invisibleBlocks.some(b => name.toLowerCase().includes(b)) : true; }
    function isPlantBlock(name) { if(!name) return false; name = name.toLowerCase(); if(name.includes('grass_block')) return false; return ['grass','fern','bush','flower','sapling','mushroom','web','lily','tulip','orchid','allium','bluet','daisy','dandelion','poppy','rose','lilac','peony','cornflower','kelp','bamboo','dripleaf','cane','vine','wheat','carrot','potato','beetroot','sprouts','roots','crop'].some(p => name.includes(p)); }
    function isBlockTransparent(name) { if(!name) return true; name = name.toLowerCase(); if(isInvisible(name) || isPlantBlock(name)) return true; return ['glass','leaves','slab','stairs','fence','door','trapdoor','wall','pane','water','lava','chest','lantern','torch','button','lever','sign','bed','carpet','snow','ladder','rail','wire','camp','anvil','hopper','cauldron','bell','chain','rod','daylight_detector','repeater','comparator','potted','enchanting_table','lectern'].some(t => name.includes(t)); }
    function getBlockColor(name) { if (!name) return '#ffffff'; name = name.toLowerCase().replace('minecraft:', ''); if (name.includes('grass_block') || name.includes('emerald') || name.includes('slime')) return '#4ade80'; if (name.includes('leaves') || name.includes('vine') || name.includes('fern') || name.includes('wheat') || name === 'short_grass') return '#22c55e'; if (name.includes('dirt') || name.includes('podzol') || name.includes('path') || name.includes('farmland') || name.includes('pot')) return '#854d0e'; if (name.includes('wood') || name.includes('log') || name.includes('planks') || name.includes('door') || name.includes('fence') || name.includes('chest') || name.includes('barrel') || name.includes('sign') || name.includes('button')) return '#b45309'; if (name.includes('stone') || name.includes('cobble') || name.includes('andesite') || name.includes('gravel') || name.includes('bedrock') || name.includes('furnace') || name.includes('deepslate')) return '#6b7280'; if (name.includes('sand') || name.includes('sponge') || name.includes('hay') || name.includes('gold')) return '#fcd34d'; if (name.includes('water')) return '#3b82f6'; if (name.includes('glass') || name.includes('ice')) return '#7dd3fc'; if (name.includes('lava') || name.includes('magma') || name.includes('redstone') || name.includes('brick') || name.includes('tnt') || name.includes('mushroom')) return '#ef4444'; if (name.includes('iron') || name.includes('quartz') || name.includes('snow') || name.includes('white') || name.includes('diorite')) return '#f8fafc'; if (name.includes('obsidian') || name.includes('black') || name.includes('coal')) return '#1e293b'; let hash = 0; for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash); return `hsl(${Math.abs(hash) % 360}, 60%, 50%)`; }
    function getTextureNames(name, props) {
        name = name.toLowerCase().replace('minecraft:', ''); const p = props || {};
        if (name === 'short_grass') return Array(6).fill('short_grass'); if (name === 'wheat') return Array(6).fill('wheat_stage7');
        if (name === 'enchanting_table') return ['enchanting_table_side', 'enchanting_table_side', 'enchanting_table_top', 'enchanting_table_bottom', 'enchanting_table_side', 'enchanting_table_side'];
        if (name === 'lectern') return ['lectern_sides', 'lectern_sides', 'lectern_top', 'lectern_base', 'lectern_front', 'lectern_sides'];
        let base = name.replace('_stairs', '').replace('_slab', '').replace('_wall', '').replace('_fence_gate', '').replace('_fence', '').replace('_button', '').replace('_pressure_plate', '');
        if (base.startsWith('potted_')) base = base.replace('potted_', ''); if (base === 'stone_brick') base = 'stone_bricks'; if (base === 'deepslate_tile') base = 'deepslate_tiles';
        if (['oak', 'spruce', 'birch', 'jungle', 'acacia', 'dark_oak', 'mangrove', 'cherry', 'crimson', 'warped'].includes(base)) base += '_planks';
        let top = base, bottom = base, side = base;
        if (base === 'grass_block') { top = 'grass_block_top'; bottom = 'dirt'; side = 'grass_block_side'; }
        else if (base === 'podzol') { top = 'podzol_top'; bottom = 'dirt'; side = 'podzol_side'; }
        else if (base === 'mycelium') { top = 'mycelium_top'; bottom = 'dirt'; side = 'mycelium_side'; }
        else if (base === 'dirt_path') { top = 'dirt_path_top'; bottom = 'dirt'; side = 'dirt_path_side'; }
        else if (base.endsWith('_log') || base.endsWith('_stem') || base.endsWith('_hyphae')) {
            const end = base + '_top';
            if (p.axis === 'x') return [end, end, base, base, base, base];
            if (p.axis === 'z') return [base, base, base, base, end, end];
            top = end; bottom = end;
        } else if (isPlantBlock(name)) { top = p.half === 'upper' ? base + '_top' : (p.half === 'lower' ? base + '_bottom' : base); bottom = top; side = top; }
        else if (name.includes('door') && !name.includes('trapdoor')) { top = base + (p.half === 'upper' ? '_top' : '_bottom'); bottom = top; side = top; }
        else if (name === 'water') { top = bottom = side = 'water_still'; }
        else if (name === 'lava') { top = bottom = side = 'lava_still'; }
        if (base === 'furnace' || base === 'smoker' || base === 'blast_furnace') {
            const front = base + (p.lit === 'true' ? '_front_on' : '_front'), faces = [base, base, base + '_top', 'stone', base, base];
            faces[{east: 0, west: 1, south: 4, north: 5}[p.facing] ?? 5] = front; return faces;
        }
        return [side, side, top, bottom, side, side];
    }
    
    const textureCache = {}; const texLoader = new THREE.TextureLoader();
    function loadTextureAsync(texName) { if (!texName) return Promise.resolve(null); if (textureCache[texName]) return textureCache[texName]; textureCache[texName] = new Promise(resolve => { const url = `https://raw.githubusercontent.com/InventivetalentDev/minecraft-assets/1.20.1/assets/minecraft/textures/block/${texName}.png`; texLoader.load(url, (tex) => { tex.magFilter = THREE.NearestFilter; tex.minFilter = THREE.NearestFilter; tex.colorSpace = THREE.SRGBColorSpace; tex.wrapS = THREE.ClampToEdgeWrapping; tex.wrapT = THREE.ClampToEdgeWrapping; resolve(tex); }, undefined, () => resolve(null)); }); return textureCache[texName]; }
    function buildBlockParts(name, props) { name = name.toLowerCase(); const p = props || {}, parts = []; if (name.includes('button')) parts.push({x:5, y:5, z:14, w:6, h:4, d:2}); else if (name.includes('gate')) parts.push({x:0, y:5, z:7, w:16, h:6, d:2}); else if (name.startsWith('potted_') || name.includes('pot')) parts.push({x:5, y:0, z:5, w:6, h:6, d:6}); else if (name === 'enchanting_table') parts.push({x:0, y:0, z:0, w:16, h:12, d:16}); else if (name === 'lectern') parts.push({x:0, y:0, z:0, w:16, h:14, d:16}); else if (isPlantBlock(name)) { parts.push({x: 7.9, y: 0, z: 0, w: 0.2, h: 16, d: 16}); parts.push({x: 0, y: 0, z: 7.9, w: 16, h: 16, d: 0.2}); } else if (name.includes('slab')) { if (p.type === 'top') parts.push({x:0, y:8, z:0, w:16, h:8, d:16}); else if (p.type === 'double') parts.push({x:0, y:0, z:0, w:16, h:16, d:16}); else parts.push({x:0, y:0, z:0, w:16, h:8, d:16}); } else if (name.includes('stairs')) { const isTop = (p.half === 'top'); parts.push({x:0, y: isTop ? 8 : 0, z:0, w:16, h:8, d:16}); const y2 = isTop ? 0 : 8; if (p.facing === 'east') parts.push({x:0, y:y2, z:0, w:8, h:8, d:16}); else if (p.facing === 'west') parts.push({x:8, y:y2, z:0, w:8, h:8, d:16}); else if (p.facing === 'south') parts.push({x:0, y:y2, z:0, w:16, h:8, d:8}); else parts.push({x:0, y:y2, z:8, w:16, h:8, d:8}); } else if (name.includes('fence') && !name.includes('gate')) { parts.push({x:6, y:0, z:6, w:4, h:16, d:4}); if (p.north === 'true') parts.push({x:6, y:6, z:0, w:4, h:9, d:6}); if (p.south === 'true') parts.push({x:6, y:6, z:10, w:4, h:9, d:6}); if (p.west === 'true') parts.push({x:0, y:6, z:6, w:6, h:9, d:4}); if (p.east === 'true') parts.push({x:10, y:6, z:6, w:6, h:9, d:4}); } else if (name.includes('wall') && !name.includes('sign') && !name.includes('torch')) { parts.push({x:4, y:0, z:4, w:8, h:16, d:8}); if (p.north === 'true' || p.north === 'low' || p.north === 'tall') parts.push({x:4, y:0, z:0, w:8, h:14, d:4}); if (p.south === 'true' || p.south === 'low' || p.south === 'tall') parts.push({x:4, y:0, z:12, w:8, h:14, d:4}); if (p.west === 'true' || p.west === 'low' || p.west === 'tall') parts.push({x:0, y:0, z:4, w:4, h:14, d:8}); if (p.east === 'true' || p.east === 'low' || p.east === 'tall') parts.push({x:12, y:0, z:4, w:4, h:14, d:8}); } else if (name.includes('pane') || name.includes('bars')) { parts.push({x:7, y:0, z:7, w:2, h:16, d:2}); if (p.north === 'true') parts.push({x:7, y:0, z:0, w:2, h:16, d:7}); if (p.south === 'true') parts.push({x:7, y:0, z:9, w:2, h:16, d:7}); if (p.west === 'true') parts.push({x:0, y:0, z:7, w:7, h:16, d:2}); if (p.east === 'true') parts.push({x:9, y:0, z:7, w:7, h:16, d:2}); } else if (name.includes('trapdoor')) { const t = 3; if (p.open === 'true') { if (p.facing === 'north') parts.push({x:0, y:0, z:16-t, w:16, h:16, d:t}); else if (p.facing === 'south') parts.push({x:0, y:0, z:0, w:16, h:16, d:t}); else if (p.facing === 'west') parts.push({x:16-t, y:0, z:0, w:t, h:16, d:16}); else parts.push({x:0, y:0, z:0, w:t, h:16, d:16}); } else { if (p.half === 'top') parts.push({x:0, y:16-t, z:0, w:16, h:t, d:16}); else parts.push({x:0, y:0, z:0, w:16, h:t, d:16}); } } else if (name.includes('door') && !name.includes('trapdoor')) { const t = 3; if (p.facing === 'east') parts.push({x:0, y:0, z:0, w:t, h:16, d:16}); else if (p.facing === 'west') parts.push({x:16-t, y:0, z:0, w:t, h:16, d:16}); else if (p.facing === 'south') parts.push({x:0, y:0, z:0, w:16, h:16, d:t}); else parts.push({x:0, y:0, z:16-t, w:16, h:16, d:t}); } else if (name.includes('carpet') || name.includes('pressure_plate') || name.includes('snow')) parts.push({x:0, y:0, z:0, w:16, h:1, d:16}); else if (name.includes('bed')) parts.push({x:0, y:0, z:0, w:16, h:9, d:16}); else if (name.includes('torch')) { if (name.includes('wall_torch')) { if (p.facing === 'east') parts.push({x: 0, y: 4, z: 7, w: 4, h: 10, d: 2}); else if (p.facing === 'west') parts.push({x: 12, y: 4, z: 7, w: 4, h: 10, d: 2}); else if (p.facing === 'south') parts.push({x: 7, y: 4, z: 0, w: 2, h: 10, d: 4}); else parts.push({x: 7, y: 4, z: 12, w: 2, h: 10, d: 4}); } else parts.push({x:7, y:0, z:7, w:2, h:10, d:2}); } else if (name.includes('chest')) parts.push({x:1, y:0, z:1, w:14, h:14, d:14}); else if (name.includes('daylight_detector') || name.includes('camp')) parts.push({x:0, y:0, z:0, w:16, h:7, d:16}); else if (name.includes('redstone_wire') || name.includes('rail')) parts.push({x:0, y:0, z:0, w:16, h:0.5, d:16}); else if (name.includes('repeater') || name.includes('comparator')) parts.push({x:0, y:0, z:0, w:16, h:2, d:16}); else if (name.includes('lantern') || name.includes('bell')) parts.push({x:4, y: p.hanging === 'true' ? 7 : 0, z:4, w:8, h:9, d:8}); else parts.push({x:0, y:0, z:0, w:16, h:16, d:16}); return parts; }
    function createExactGeometry(parts) { if (parts.length === 0) parts.push({x:0, y:0, z:0, w:16, h:16, d:16}); let totalVerts = 0, totalInds = 0; parts.forEach(p => { const g = new THREE.BoxGeometry(p.w/16, p.h/16, p.d/16); totalVerts += g.attributes.position.count; totalInds += g.index.count; }); const posArray = new Float32Array(totalVerts * 3), normArray = new Float32Array(totalVerts * 3), uvArray = new Float32Array(totalVerts * 2), indArray = new Uint32Array(totalInds); const merged = new THREE.BufferGeometry(); let vOffset = 0, iOffset = 0; parts.forEach(p => { const geo = new THREE.BoxGeometry(p.w/16, p.h/16, p.d/16); geo.translate((p.x + p.w/2 - 8)/16, (p.y + p.h/2 - 8)/16, (p.z + p.d/2 - 8)/16); posArray.set(geo.attributes.position.array, vOffset * 3); normArray.set(geo.attributes.normal.array, vOffset * 3); uvArray.set(geo.attributes.uv.array, vOffset * 2); for(let i=0; i<geo.index.array.length; i++) indArray[iOffset + i] = geo.index.array[i] + vOffset; geo.groups.forEach(g => merged.addGroup(iOffset + g.start, g.count, g.materialIndex)); vOffset += geo.attributes.position.count; iOffset += geo.index.count; }); merged.setAttribute('position', new THREE.BufferAttribute(posArray, 3)); merged.setAttribute('normal', new THREE.BufferAttribute(normArray, 3)); merged.setAttribute('uv', new THREE.BufferAttribute(uvArray, 2)); merged.setIndex(new THREE.BufferAttribute(indArray, 1)); return merged; }

    // Реальные описания блоков Minecraft. Это особенно важно для люков, дверей,
    // панелей и ступеней: модель и поворот берутся из blockstates, а не угадываются.
    const modelJsonCache = {}, blockstateJsonCache = {}, modelBaseUrl = 'https://raw.githubusercontent.com/InventivetalentDev/minecraft-assets/1.20.1/assets/minecraft/';
    const faceOrder = ['east', 'west', 'up', 'down', 'south', 'north'];
    function assetId(value) { return String(value || '').replace(/^minecraft:/, '').replace(/^block\//, 'block/'); }
    function loadAssetJson(path, cache) { if (cache[path]) return cache[path]; cache[path] = fetch(modelBaseUrl + path).then(r => r.ok ? r.json() : null).catch(() => null); return cache[path]; }
    function propertyMatches(condition, props) {
        if (!condition) return true; if (Array.isArray(condition)) return condition.some(c => propertyMatches(c, props));
        return Object.entries(condition).every(([key, expected]) => String(expected).split('|').includes(String(props?.[key] ?? '')));
    }
    function selectedStateModels(state, props) {
        if (!state) return []; if (state.variants) {
            for (const [rule, candidate] of Object.entries(state.variants)) {
                const condition = Object.fromEntries(rule ? rule.split(',').map(p => p.split('=')) : []);
                if (propertyMatches(condition, props)) return [Array.isArray(candidate) ? candidate[0] : candidate];
            }
        }
        if (state.multipart) return state.multipart.filter(part => propertyMatches(part.when, props)).map(part => Array.isArray(part.apply) ? part.apply[0] : part.apply);
        return [];
    }
    async function resolveModel(modelRef) {
        const id = assetId(modelRef); if (!id) return null;
        const data = await loadAssetJson(`models/${id}.json`, modelJsonCache); if (!data) return null;
        let parent = null;
        if (data.parent) parent = await resolveModel(data.parent);
        return { elements: data.elements || parent?.elements || [], textures: {...(parent?.textures || {}), ...(data.textures || {})} };
    }
    function resolveTextureRef(ref, textures) {
        let current = ref || ''; const seen = new Set();
        while (current.startsWith('#') && !seen.has(current)) { seen.add(current); current = textures[current.slice(1)] || ''; }
        return current ? assetId(current).replace(/^block\//, '') : null;
    }
    function rotateElement(geo, rotation) {
        if (!rotation) return; const origin = rotation.origin || [8, 8, 8], ox = (origin[0] - 8) / 16, oy = (origin[1] - 8) / 16, oz = (origin[2] - 8) / 16, angle = THREE.MathUtils.degToRad(rotation.angle || 0);
        geo.translate(-ox, -oy, -oz); if (rotation.axis === 'x') geo.rotateX(angle); if (rotation.axis === 'y') geo.rotateY(angle); if (rotation.axis === 'z') geo.rotateZ(angle); geo.translate(ox, oy, oz);
    }
    function createMinecraftGeometry(descriptors) {
        const items = [], slots = [], slotIndex = new Map(); let totalVerts = 0, totalInds = 0;
        const getSlot = texture => { const key = texture || '__invisible'; if (!slotIndex.has(key)) { slotIndex.set(key, slots.length); slots.push(texture || null); } return slotIndex.get(key); };
        descriptors.forEach(desc => desc.model.elements.forEach(element => { const from = element.from || [0,0,0], to = element.to || [16,16,16], geo = new THREE.BoxGeometry((to[0]-from[0])/16, (to[1]-from[1])/16, (to[2]-from[2])/16); geo.translate((from[0]+to[0]-16)/32, (from[1]+to[1]-16)/32, (from[2]+to[2]-16)/32); rotateElement(geo, element.rotation); if (desc.rotation?.x) geo.rotateX(THREE.MathUtils.degToRad(desc.rotation.x)); if (desc.rotation?.y) geo.rotateY(THREE.MathUtils.degToRad(desc.rotation.y)); items.push({geo, element, textures: desc.model.textures}); totalVerts += geo.attributes.position.count; totalInds += geo.index.count; }));
        if (!items.length) return null; const pos = new Float32Array(totalVerts*3), normal = new Float32Array(totalVerts*3), uv = new Float32Array(totalVerts*2), ind = new Uint32Array(totalInds); const merged = new THREE.BufferGeometry(); let vo = 0, io = 0;
        items.forEach(({geo, element, textures}) => { pos.set(geo.attributes.position.array, vo*3); normal.set(geo.attributes.normal.array, vo*3); uv.set(geo.attributes.uv.array, vo*2); geo.groups.forEach((group, faceIndex) => { const face = element.faces?.[faceOrder[faceIndex]], texture = face ? resolveTextureRef(face.texture, textures) : null, slot = getSlot(texture); merged.addGroup(io + group.start, group.count, slot); if (face?.uv) { const vertex = vo + faceIndex*4, [u1,v1,u2,v2] = face.uv.map(n => n/16), a = [u1,1-v1, u2,1-v1, u1,1-v2, u2,1-v2]; uv.set(a, vertex*2); } }); for (let i=0; i<geo.index.array.length; i++) ind[io+i] = geo.index.array[i] + vo; vo += geo.attributes.position.count; io += geo.index.count; });
        merged.setAttribute('position', new THREE.BufferAttribute(pos,3)); merged.setAttribute('normal', new THREE.BufferAttribute(normal,3)); merged.setAttribute('uv', new THREE.BufferAttribute(uv,2)); merged.setIndex(new THREE.BufferAttribute(ind,1)); return {geometry: merged, textureNames: slots};
    }
    async function getBlockVisual(block) {
        const name = (block.Name || '').toLowerCase().replace('minecraft:', '');
        const state = await loadAssetJson(`blockstates/${name}.json`, blockstateJsonCache), refs = selectedStateModels(state, block.Properties), descriptors = [];
        for (const ref of refs) { const model = await resolveModel(ref?.model); if (model) descriptors.push({model, rotation: ref}); }
        return createMinecraftGeometry(descriptors) || {geometry: createExactGeometry(buildBlockParts(name, block.Properties)), textureNames: getTextureNames(name, block.Properties)};
    }

    function setProgress(percent, text) {
        document.getElementById('loadingDesc').textContent = text;
        document.getElementById('loadingBar').style.width = `${percent}%`;
    }

    let sceneBounds = null, cameraHome = null, animationStarted = false;
    function regionInfo(region) {
        const size = region.Size || {}, pos = region.Position || {};
        const sx = Math.abs(Number(size.x ?? 0)), sy = Math.abs(Number(size.y ?? 0)), sz = Math.abs(Number(size.z ?? 0));
        const px = Number(pos.x ?? 0), py = Number(pos.y ?? 0), pz = Number(pos.z ?? 0);
        return { sx, sy, sz, px, py, pz, dx: Number(size.x ?? 0) < 0 ? -1 : 1, dy: Number(size.y ?? 0) < 0 ? -1 : 1, dz: Number(size.z ?? 0) < 0 ? -1 : 1 };
    }
    function blockKey(x, y, z) { return `${x}|${y}|${z}`; }
    function resetViewerCamera() { if (!camera || !controls || !cameraHome) return; camera.position.copy(cameraHome.position); controls.target.copy(cameraHome.target); controls.update(); }
    function toggleAutoRotate() { autoRotate = !autoRotate; if (controls) controls.autoRotate = autoRotate; document.getElementById('autoRotateText').textContent = `Автоповорот: ${autoRotate ? 'ВКЛ' : 'ВЫКЛ'}`; }

    async function init3DAsync() {
        currentGenerationId++; const myGenId = currentGenerationId; isGenerating = true; hideTooltip();
        try {
            const regionsObj = findDeep(currentParsed, 'Regions') || {}, regions = Object.values(regionsObj);
            const world = new Map(); let minX = Infinity, minY = Infinity, minZ = Infinity, maxX = -Infinity, maxY = -Infinity, maxZ = -Infinity;
            setProgress(48, 'Сборка регионов в единую сцену...');
            for (const region of regions) {
                const info = regionInfo(region), palette = region.BlockStatePalette || [], blocks = decodeBlockStates(region);
                for (let y = 0; y < info.sy; y++) for (let z = 0; z < info.sz; z++) for (let x = 0; x < info.sx; x++) {
                    const state = palette[blocks[(y * info.sz * info.sx) + (z * info.sx) + x]]; if (!state || isInvisible(state.Name || '')) continue;
                    const wx = info.px + x * info.dx, wy = info.py + y * info.dy, wz = info.pz + z * info.dz;
                    world.set(blockKey(wx, wy, wz), { x: wx, y: wy, z: wz, block: state });
                    minX = Math.min(minX, wx); minY = Math.min(minY, wy); minZ = Math.min(minZ, wz); maxX = Math.max(maxX, wx); maxY = Math.max(maxY, wy); maxZ = Math.max(maxZ, wz);
                }
            }
            if (!world.size) throw new Error('В схеме нет видимых блоков.');
            const sx = maxX - minX + 1, sy = maxY - minY + 1, sz = maxZ - minZ + 1, maxDim = Math.max(sx, sy, sz, 10);
            const center = new THREE.Vector3((minX + maxX + 1) / 2, (minY + maxY + 1) / 2, (minZ + maxZ + 1) / 2);
            flySpeed = Math.max(maxDim * .8, 15); currentDims = {sx, sy, sz}; sceneBounds = {minX, minY, minZ, maxX, maxY, maxZ, center, maxDim};
            document.getElementById('dimX').textContent = sx; document.getElementById('dimY').textContent = sy; document.getElementById('dimZ').textContent = sz;

            const container = document.getElementById('threeContainer');
            renderer = new THREE.WebGLRenderer({ canvas: document.getElementById('threeCanvas'), antialias: true, powerPreference: 'high-performance' });
            renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2)); renderer.setSize(container.clientWidth, container.clientHeight); renderer.outputEncoding = THREE.sRGBEncoding; renderer.toneMapping = THREE.NoToneMapping; renderer.toneMappingExposure = 1;
            scene = new THREE.Scene(); scene.background = new THREE.Color(0x07111f); scene.fog = new THREE.FogExp2(0x07111f, 0.008 / Math.max(1, maxDim / 45));
            camera = new THREE.PerspectiveCamera(46, container.clientWidth / container.clientHeight, 0.05, maxDim * 12);
            controls = new THREE.OrbitControls(camera, renderer.domElement); controls.enableDamping = true; controls.dampingFactor = .075; controls.minDistance = Math.max(2, maxDim * .12); controls.maxDistance = maxDim * 5; controls.target.set(0, 0, 0); controls.autoRotateSpeed = .55;
            const distance = maxDim * 1.55; camera.position.set(distance * .9, distance * .68, distance * 1.1); cameraHome = { position: camera.position.clone(), target: controls.target.clone() };
            flyControls = new THREE.PointerLockControls(camera, renderer.domElement); scene.add(flyControls.getObject());
            flyControls.addEventListener('lock', () => { isFlying = true; controls.enabled = false; clock.start(); document.getElementById('crosshair').classList.remove('hidden'); });
            flyControls.addEventListener('unlock', () => { isFlying = false; controls.enabled = true; document.getElementById('crosshair').classList.add('hidden'); controls.target.copy(camera.position).add(camera.getWorldDirection(new THREE.Vector3()).multiplyScalar(maxDim / 2)); });

            scene.add(new THREE.HemisphereLight(0x9fb7ca, 0x101a13, .55));
            scene.add(new THREE.AmbientLight(0x5d7184, .18));
            const sun = new THREE.DirectionalLight(0xffd39d, .88); sun.position.set(maxDim * .8, maxDim * 1.6, maxDim); scene.add(sun);
            const rim = new THREE.DirectionalLight(0x5ca2c4, .14); rim.position.set(-maxDim, maxDim * .45, -maxDim); scene.add(rim);
            const floor = new THREE.Mesh(new THREE.PlaneGeometry(maxDim * 5, maxDim * 5), new THREE.MeshStandardMaterial({color: 0x101d28, roughness: 1, metalness: .05})); floor.rotation.x = -Math.PI / 2; floor.position.y = -sy / 2 - .035; scene.add(floor);
            const grid = new THREE.GridHelper(maxDim * 4, Math.min(80, Math.max(12, Math.round(maxDim * 2))), 0x285363, 0x173441); grid.position.y = floor.position.y + .005; grid.material.transparent = true; grid.material.opacity = .38; scene.add(grid);
            const bbox = new THREE.LineSegments(new THREE.EdgesGeometry(new THREE.BoxGeometry(sx, sy, sz)), new THREE.LineBasicMaterial({color: 0x72f7d3, transparent: true, opacity: .65})); bbox.userData.isBoundingBox = true; scene.add(bbox);
            modelGroup = new THREE.Group(); scene.add(modelGroup);

            // Объединяем одинаковые состояния в InstancedMesh, но сохраняем реальную форму каждого блока.
            const groups = new Map();
            for (const entry of world.values()) {
                const b = entry.block, name = (b.Name || '').toLowerCase(), p = b.Properties || {};
                const neighbor = (dx, dy, dz) => world.get(blockKey(entry.x + dx, entry.y + dy, entry.z + dz));
                const exposed = [[1,0,0],[-1,0,0],[0,1,0],[0,-1,0],[0,0,1],[0,0,-1]].some(([x,y,z]) => { const n = neighbor(x,y,z); return !n || isBlockTransparent(n.block.Name || ''); });
                if (!exposed && buildBlockParts(name, p).length === 1) continue;
                const signature = `${name}|${JSON.stringify(p)}`; if (!groups.has(signature)) groups.set(signature, { block: b, positions: [] }); groups.get(signature).positions.push(entry);
            }
            globalPalette = []; globalCounts = []; let paletteId = 0, completed = 0;
            setProgress(63, 'Создание точной геометрии блоков...');
            const groupedItems = [...groups.values()];
            const visuals = await Promise.all(groupedItems.map(item => getBlockVisual(item.block)));
            const textureTasks = [];
            for (let itemIndex = 0; itemIndex < groupedItems.length; itemIndex++) {
                const item = groupedItems[itemIndex], visual = visuals[itemIndex], name = (item.block.Name || '').toLowerCase(), geometry = visual.geometry;
                const placeholder = Array(Math.max(1, visual.textureNames.length)).fill(null).map(() => new THREE.MeshStandardMaterial({color: getBlockColor(name), roughness: .88}));
                const mesh = new THREE.InstancedMesh(geometry, placeholder, item.positions.length); mesh.userData = { paletteId, positions: item.positions }; mesh.frustumCulled = false;
                const dummy = new THREE.Object3D(); item.positions.forEach((entry, index) => { dummy.position.set(entry.x + .5 - center.x, entry.y + .5 - center.y, entry.z + .5 - center.z); dummy.updateMatrix(); mesh.setMatrixAt(index, dummy.matrix); }); mesh.instanceMatrix.needsUpdate = true; modelGroup.add(mesh);
                globalPalette[paletteId] = item.block; globalCounts[paletteId] = item.positions.length; paletteId++;
                textureTasks.push((async () => {
                    const textures = await Promise.all(visual.textureNames.map(loadTextureAsync)); const translucent = /glass|water|ice/.test(name), cutout = isPlantBlock(name) || /leaves|vine|pane|bars/.test(name), emissive = /lava|glowstone|sea_lantern|shroomlight|lantern/.test(name);
                    const materials = textures.map((texture, face) => visual.textureNames[face] ? new THREE.MeshStandardMaterial({ color: texture ? 0xffffff : getBlockColor(name), map: texture || null, roughness: .92, metalness: 0, transparent: translucent || cutout, opacity: translucent ? (name.includes('water') ? .68 : .58) : 1, depthWrite: !translucent, alphaTest: cutout ? .35 : 0, side: cutout ? THREE.DoubleSide : THREE.FrontSide, emissive: emissive ? new THREE.Color(name.includes('lava') ? 0xff5d18 : 0xd8f7a2) : new THREE.Color(0x000000), emissiveMap: emissive ? texture : null, emissiveIntensity: emissive ? .22 : 0 }) : new THREE.MeshBasicMaterial({transparent: true, opacity: 0, depthWrite: false}));
                    if (myGenId === currentGenerationId) mesh.material = materials;
                    completed++; setProgress(72 + Math.round((completed / Math.max(1, groups.size)) * 26), `Текстуры и материалы: ${completed}/${groups.size}`);
                })());
            }
            if (!animationStarted) { animationStarted = true; animate3D(); }
            await Promise.all(textureTasks); if (myGenId !== currentGenerationId) return; setProgress(100, 'Сцена готова'); setTimeout(() => document.getElementById('loading3D').classList.add('opacity-0', 'pointer-events-none'), 500);
        } catch (err) { console.error(err); document.getElementById('loadingTitle').innerHTML = '<i class="fa-solid fa-triangle-exclamation text-red-500"></i> Ошибка рендера'; document.getElementById('loadingDesc').textContent = err.message; }
        finally { isGenerating = false; }
    }

    document.addEventListener('keydown', (e) => { if (!isFlying) return; if (e.code === 'Space') e.preventDefault(); switch(e.code) { case 'KeyW': moveState.forward = true; break; case 'KeyS': moveState.backward = true; break; case 'KeyA': moveState.left = true; break; case 'KeyD': moveState.right = true; break; case 'Space': moveState.up = true; break; case 'ShiftLeft': case 'ShiftRight': moveState.down = true; break; } });
    document.addEventListener('keyup', (e) => { if (!isFlying) return; switch(e.code) { case 'KeyW': moveState.forward = false; break; case 'KeyS': moveState.backward = false; break; case 'KeyA': moveState.left = false; break; case 'KeyD': moveState.right = false; break; case 'Space': moveState.up = false; break; case 'ShiftLeft': case 'ShiftRight': moveState.down = false; break; } });
    document.addEventListener('keydown', async (e) => {
        if (!(e.ctrlKey || e.metaKey) || e.code !== 'KeyC' || !hoveredBlock) return;
        e.preventDefault(); const id = hoveredBlock.block.Name || '';
        try { await navigator.clipboard.writeText(id); } catch (_) { const input = document.createElement('textarea'); input.value = id; document.body.appendChild(input); input.select(); document.execCommand('copy'); input.remove(); }
        const status = hoveredBlock.source === '2d' ? document.getElementById('layerBlockCoords') : document.getElementById('tt-coords');
        if (status) { const previous = status.textContent; status.textContent = 'ID скопирован'; setTimeout(() => { if (status.textContent === 'ID скопирован') status.textContent = previous; }, 1200); }
    });
    const threeContainer = document.getElementById('threeContainer');
    threeContainer.addEventListener('mousemove', (e) => { if (!tooltipEnabled || isFlying || !renderer) return; const rect = renderer.domElement.getBoundingClientRect(); mouse.x = ((e.clientX - rect.left) / rect.width) * 2 - 1; mouse.y = -((e.clientY - rect.top) / rect.height) * 2 + 1; mouseMoved = true; });
    threeContainer.addEventListener('mouseleave', () => { if (!isFlying) { mouse.x = -10; mouse.y = -10; hideTooltip(); } });
    const previewCanvas = document.getElementById('previewCanvas');
    previewCanvas.addEventListener('mousemove', e => {
        const layout = currentLayerLayout; if (!layout) return; const rect = previewCanvas.getBoundingClientRect(), px = (e.clientX - rect.left) * (560 / rect.width), pz = (e.clientY - rect.top) * (560 / rect.height), x = Math.floor((px - layout.offsetX) / layout.cellSize), z = Math.floor((pz - layout.offsetZ) / layout.cellSize);
        if (x < 0 || z < 0 || x >= layout.sx || z >= layout.sz) return hideLayerTooltip(); const state = layout.palette[layout.blocks[(layout.y * layout.sz * layout.sx) + (z * layout.sx) + x]]; if (!state || isInvisible(state.Name || '')) return hideLayerTooltip(); showLayerTooltip(state, x, layout.y, z, e.clientX - rect.left, e.clientY - rect.top);
    });
    previewCanvas.addEventListener('mouseleave', hideLayerTooltip);

    function animate3D() {
        requestAnimationFrame(animate3D); const delta = clock.getDelta();
        if (isFlying) {
            const speed = flySpeed * speedMult * delta;
            if (flightMode === 'flat') {
                const dir = new THREE.Vector3(); camera.getWorldDirection(dir); dir.y = 0; dir.normalize();
                const right = new THREE.Vector3().crossVectors(camera.up, dir).normalize();
                if (moveState.forward) camera.position.addScaledVector(dir, speed); if (moveState.backward) camera.position.addScaledVector(dir, -speed); if (moveState.left) camera.position.addScaledVector(right, speed); if (moveState.right) camera.position.addScaledVector(right, -speed); if (moveState.up) camera.position.y += speed; if (moveState.down) camera.position.y -= speed;
            } else {
                if (moveState.forward) camera.translateZ(-speed); if (moveState.backward) camera.translateZ(speed); if (moveState.left) camera.translateX(-speed); if (moveState.right) camera.translateX(speed); if (moveState.up) camera.position.y += speed; if (moveState.down) camera.position.y -= speed;
            }
        } else if (controls) controls.update();

        const now = performance.now();
        if (!isGenerating && tooltipEnabled && modelGroup && (mouseMoved || isFlying) && (now - lastRaycastTime > 100)) {
            lastRaycastTime = now; mouseMoved = false; if (isFlying) { mouse.x = 0; mouse.y = 0; }
            if (mouse.x >= -1 && mouse.x <= 1 && mouse.y >= -1 && mouse.y <= 1) {
                raycaster.setFromCamera(mouse, camera); const intersects = raycaster.intersectObjects(modelGroup.children, false);
                if (intersects.length > 0) {
                    const hit = intersects[0], mesh = hit.object, pId = mesh.userData.paletteId;
                    if (pId !== undefined) {
                        const pos = mesh.userData.positions?.[hit.instanceId];
                        if (pos) showTooltip(pId, pos.x, pos.y, pos.z); else hideTooltip();
                    } else hideTooltip();
                } else hideTooltip();
            }
        }
        if (renderer && scene && camera) renderer.render(scene, camera);
    }

    window.showTooltip = function(id, x, y, z) {
        document.getElementById('blockTooltip').classList.remove('hidden'); document.getElementById('tt-coords').textContent = `${x}, ${y}, ${z}`;
        const block = globalPalette[id]; if (!block) return; hoveredBlock = {block, x, y, z, source: '3d'}; if (lastHoveredId === id) return; lastHoveredId = id;
        const nameStr = block.Name || ''; document.getElementById('tt-name').textContent = nameStr.replace('minecraft:', '').split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' '); document.getElementById('tt-id').textContent = nameStr; document.getElementById('tt-count').textContent = (globalCounts[id] || 0).toLocaleString('ru-RU');
        const [right, left, top, bottom, front, back] = getTextureNames(nameStr, block.Properties); const baseUrl = 'https://raw.githubusercontent.com/InventivetalentDev/minecraft-assets/1.20.1/assets/minecraft/textures/block/'; const color = getBlockColor(nameStr);
        const ttTop = document.getElementById('tt-top'), ttFront = document.getElementById('tt-front'), ttRight = document.getElementById('tt-right');
        ttTop.style.backgroundColor = top ? 'transparent' : color; ttFront.style.backgroundColor = front ? 'transparent' : color; ttRight.style.backgroundColor = right ? 'transparent' : color;
        ttTop.style.backgroundImage = top ? `url(${baseUrl}${top}.png)` : 'none'; ttFront.style.backgroundImage = front ? `url(${baseUrl}${front}.png)` : 'none'; ttRight.style.backgroundImage = right ? `url(${baseUrl}${right}.png)` : 'none';
    }
    function showLayerTooltip(block, x, y, z, left, top) { const tooltip = document.getElementById('layerTooltip'), wrap = document.getElementById('layerCanvasWrap'), name = block.Name || '', texture = getTextureNames(name, block.Properties)[2]; hoveredBlock = {block, x, y, z, source: '2d'}; document.getElementById('layerBlockName').textContent = name.replace('minecraft:', '').split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' '); document.getElementById('layerBlockId').textContent = name; document.getElementById('layerBlockCoords').textContent = `${x}, ${y}, ${z}`; const icon = document.getElementById('layerBlockIcon'); icon.style.backgroundImage = texture ? `url(${modelBaseUrl}textures/block/${texture}.png)` : 'none'; icon.style.backgroundColor = getBlockColor(name); tooltip.classList.remove('hidden'); tooltip.style.left = `${Math.min(left + 14, wrap.clientWidth - tooltip.offsetWidth - 6)}px`; tooltip.style.top = `${Math.max(6, Math.min(top + 14, wrap.clientHeight - tooltip.offsetHeight - 6))}px`; }
    function hideLayerTooltip() { document.getElementById('layerTooltip').classList.add('hidden'); if (hoveredBlock?.source === '2d') hoveredBlock = null; }
    window.hideTooltip = function() { document.getElementById('blockTooltip').classList.add('hidden'); lastHoveredId = null; if (hoveredBlock?.source === '3d') hoveredBlock = null; }
    window.toggleTooltip = function() { tooltipEnabled = !tooltipEnabled; document.getElementById('tooltipText').textContent = `Подсказка: ${tooltipEnabled ? 'ВКЛ' : 'ВЫКЛ'}`; document.getElementById('tooltipIcon').className = tooltipEnabled ? "fa-solid fa-message text-indigo-400" : "fa-solid fa-message text-zinc-500"; if(!tooltipEnabled) hideTooltip(); };
    
    let bboxVisible = true;
    window.toggleBoundingBox = function() {
        bboxVisible = !bboxVisible;
        document.getElementById('bboxText').textContent = `Границы: ${bboxVisible ? 'ВКЛ' : 'ВЫКЛ'}`;
        document.getElementById('toggleBBoxBtn').querySelector('i').className = bboxVisible ? "fa-solid fa-border-all text-pink-400" : "fa-solid fa-border-all text-zinc-500";
        if (scene) {
            scene.children.forEach(child => {
                if (child.type === 'LineSegments') {
                    child.visible = bboxVisible;
                }
            });
        }
    };

    const speedSlider = document.getElementById('speedSlider');
    const speedInput = document.getElementById('speedInput');
    
    speedSlider.addEventListener('input', (e) => {
        speedMult = parseFloat(e.target.value);
        speedInput.value = speedMult.toFixed(1);
    });
    
    speedInput.addEventListener('input', (e) => {
        let val = parseFloat(e.target.value);
        if (!isNaN(val)) {
            if (val < 0.1) val = 0.1;
            if (val > 3.0) val = 3.0;
            speedMult = val;
            speedSlider.value = val;
        }
    });

    window.toggleFlightMode = function() { flightMode = flightMode === 'free' ? 'flat' : 'free'; document.getElementById('flightModeText').textContent = `Режим: ${flightMode === 'free' ? 'Свободный' : 'Плоский'}`; document.getElementById('modeIcon').className = flightMode === 'flat' ? "fa-solid fa-arrows-left-right text-emerald-400" : "fa-solid fa-arrows-up-down-left-right text-emerald-400"; };
    window.enterFlyMode = function() { if (flyControls) flyControls.lock(); };
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
