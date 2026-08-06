<?php
require_once __DIR__ . '/config.php';
// Задаем SEO параметры до подключения header.php
$page_title = "Загрузить постройку | SchemHub";
require_once __DIR__ . '/header.php';

// Защита страницы
if (!isset($_SESSION['user_id'])) {
    echo "<div class='text-center py-20'>
            <i class='fa-solid fa-lock text-6xl text-zinc-700 mb-4'></i>
            <h2 class='text-2xl font-bold text-white'>Доступ закрыт</h2>
            <p class='text-zinc-500 mt-2'>Пожалуйста, авторизуйтесь через Telegram.</p>
          </div>";
    require_once __DIR__ . '/footer.php';
    exit;
}

$error = '';
$is_update = isset($_GET['update_id']);
$update_id = $is_update ? (int)$_GET['update_id'] : 0;
$parent_schem = null;

// Если это обновление - проверяем права
if ($is_update) {
    $stmt = $pdo->prepare("SELECT * FROM schematics WHERE id = ? AND user_id = ?");
    $stmt->execute([$update_id, $_SESSION['user_id']]);
    $parent_schem = $stmt->fetch();
    
    if (!$parent_schem) {
        die("<div class='text-center py-20 text-xl text-red-400'>Схема не найдена или вы не являетесь её автором.</div>");
    }
}

// Функция отправки уведомления в Telegram
function sendTgNotification($chat_id, $text) {
    if (!defined('TG_BOT_TOKEN')) return;
    $url = "https://api.telegram.org/bot" . TG_BOT_TOKEN . "/sendMessage";
    $data = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
    $options = ['http' => ['header' => "Content-type: application/x-www-form-urlencoded\r\n", 'method' => 'POST', 'content' => http_build_query($data), 'timeout' => 3]];
    @file_get_contents($url, false, stream_context_create($options));
}

// Получаем теги для новой схемы (УДАЛЕНО - используем массивы из config.php)
// $stmt_tags = $pdo->query("SELECT * FROM tags ORDER BY name ASC");
// $all_tags = $stmt_tags->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_update_post = !empty($_POST['is_update']);
    $thumbnail_base64 = $_POST['thumbnail_base64'] ?? '';
    
    // Валидация в зависимости от режима
    if ($is_update_post) {
        $version_label = trim($_POST['version_label'] ?? $_POST['version_name'] ?? '');
        $description = trim($_POST['description'] ?? $parent_schem['description']);
        $style = $_POST['style'] ?? $parent_schem['style'];
        $type = $_POST['type'] ?? $parent_schem['type'];
        $changelog = trim($_POST['changelog'] ?? '');
        
        if (empty($version_label)) $error = 'Укажите номер версии (например, 1.1).';
        $final_title = $parent_schem['title'];
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $style = $_POST['style'] ?? '';
        $type = $_POST['type'] ?? '';
        $version_label = '1.0';
        
        if (empty($title)) $error = 'Заполните название схемы.';
        
        $final_title = $title;
    }

    // SEO Слаг для красивого названия файлов (вместо рандомного ID)
    $title_slug = generate_slug($final_title);
    if(empty($title_slug)) $title_slug = 'schematic';

    if (empty($error) && isset($_FILES['schematic_file']) && $_FILES['schematic_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['schematic_file']['tmp_name'];
        $original_file_name = $_FILES['schematic_file']['name'];
        $file_size = $_FILES['schematic_file']['size'];
        $ext = strtolower(pathinfo($original_file_name, PATHINFO_EXTENSION));
        
        if ($ext !== 'litematic') {
            $error = 'Ошибка: Разрешены только файлы формата .litematic!';
        } elseif ($file_size > 50 * 1024 * 1024) { 
            $error = 'Ошибка: Размер файла не должен превышать 50 МБ.';
        } else {
            $upload_dir = 'uploads/schematics/';
            $thumb_dir = 'uploads/thumbnails/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            if (!is_dir($thumb_dir)) mkdir($thumb_dir, 0777, true);
            
            // Сохраняем файл с красивым SEO именем
            $file_path = $upload_dir . $title_slug . '-' . time() . '.litematic';
            $thumbnail_path = null;

            if (!empty($thumbnail_base64)) {
                $img_parts = explode(";base64,", $thumbnail_base64);
                if (count($img_parts) == 2) {
                    $thumbnail_path = $thumb_dir . $title_slug . '-' . time() . '.jpg';
                    file_put_contents($thumbnail_path, base64_decode($img_parts[1]));
                }
            }
            
            if (move_uploaded_file($file_tmp, $file_path)) {
                try {
                    $pdo->beginTransaction();
                    
                    if ($is_update_post) {
                        // Определяем корневой ID группы версий
                        $root_id = $parent_schem['parent_id'] ?? $parent_schem['id'];
                        
                        // Создаем НОВУЮ запись в schematics для этой версии
                        $stmt = $pdo->prepare("INSERT INTO schematics (user_id, parent_id, title, description, style, type, version_label, file_name, file_path, file_size, thumbnail_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $_SESSION['user_id'], 
                            $root_id, 
                            $final_title, 
                            $description, 
                            $style, 
                            $type, 
                            $version_label, 
                            $original_file_name, 
                            $file_path, 
                            $file_size, 
                            $thumbnail_path
                        ]);
                        
                        $schematic_id = $pdo->lastInsertId();
                        
                        // Также записываем в историю (для совместимости или доп. лога)
                        $v_stmt = $pdo->prepare("INSERT INTO schematic_versions (schematic_id, version_name, changelog, file_name, file_path, file_size) VALUES (?, ?, ?, ?, ?, ?)");
                        $v_stmt->execute([$schematic_id, $version_label, $changelog, $original_file_name, $file_path, $file_size]);
                        
                        $schematic_url = get_schematic_url($schematic_id, $final_title);
                        $tg_msg = "✅ <b>Новая версия схемы!</b>\n\nВы успешно опубликовали версию <b>v$version_label</b> для схемы <b>{$final_title}</b> как отдельную страницу.\n\n<a href='https://{$_SERVER['HTTP_HOST']}$schematic_url'>Перейти к версии</a>";
                        
                    } else {
                        // Создаем новую схему (с полями style и type)
                        $stmt = $pdo->prepare("INSERT INTO schematics (user_id, title, description, style, type, version_label, file_name, file_path, file_size, thumbnail_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$_SESSION['user_id'], $final_title, $description, $style, $type, '1.0', $original_file_name, $file_path, $file_size, $thumbnail_path]);
                        $schematic_id = $pdo->lastInsertId();
                        
                        // Создаем версию "1.0" в истории
                        $v_stmt = $pdo->prepare("INSERT INTO schematic_versions (schematic_id, version_name, changelog, file_name, file_path, file_size) VALUES (?, '1.0', 'Первый релиз', ?, ?, ?)");
                        $v_stmt->execute([$schematic_id, $original_file_name, $file_path, $file_size]);
                        
                        $schematic_url = get_schematic_url($schematic_id, $final_title);
                        $tg_msg = "🎉 <b>Новая публикация!</b>\n\nСхема <b>$final_title</b> успешно опубликована!\n\n<a href='https://{$_SERVER['HTTP_HOST']}$schematic_url'>Перейти к схеме</a>";
                    }
                    
                    $pdo->commit();
                    
                    if (isset($_SESSION['tg_id'])) sendTgNotification($_SESSION['tg_id'], $tg_msg);
                    
                    header("Location: " . get_schematic_url($schematic_id, $final_title));
                    exit;
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    if (file_exists($file_path)) unlink($file_path);
                    if ($thumbnail_path && file_exists($thumbnail_path)) unlink($thumbnail_path);
                    $error = 'Ошибка БД: ' . $e->getMessage();
                }
            } else {
                $error = 'Ошибка при сохранении файла на сервере.';
            }
        }
    } elseif (empty($error)) {
        $error = 'Пожалуйста, выберите файл схемы для загрузки.';
    }
}
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r134/three.min.js"></script>

<div class="max-w-3xl mx-auto bg-zinc-900 border border-zinc-800 rounded-3xl p-8 shadow-xl mb-10">
    <div class="mb-8 border-b border-zinc-800 pb-6">
        <?php if($is_update): ?>
            <div class="inline-block bg-blue-500/10 text-blue-400 px-3 py-1 rounded-lg text-sm font-bold mb-3 border border-blue-500/20">Режим обновления</div>
            <h1 class="text-3xl font-bold text-white">Загрузка новой версии</h1>
            <p class="text-zinc-400 mt-2">Для схемы: <span class="text-emerald-400 font-medium"><?= htmlspecialchars($parent_schem['title']) ?></span></p>
        <?php else: ?>
            <h1 class="text-3xl font-bold flex items-center gap-3 text-white">
                <i class="fa-solid fa-cloud-arrow-up text-blue-500"></i> Опубликовать схему
            </h1>
            <p class="text-zinc-400 mt-2">Заполните детали о вашей постройке. 3D-превью создастся автоматически.</p>
        <?php endif; ?>
    </div>

    <?php if(!empty($error)): ?>
        <div class="bg-red-500/10 border border-red-500/50 text-red-400 px-5 py-4 rounded-2xl mb-6 flex items-center gap-3">
            <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-6">
        
        <?php if($is_update): ?>
            <input type="hidden" name="is_update" value="1">
            <div class="bg-zinc-900/50 border border-zinc-800 p-8 rounded-3xl mb-8">
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-12 h-12 bg-emerald-500/20 text-emerald-400 rounded-xl flex items-center justify-center text-xl border border-emerald-500/20">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-white">Новая версия для: <?= htmlspecialchars($parent_schem['title']) ?></h2>
                        <p class="text-zinc-500 text-sm">Эта версия будет иметь свою собственную страницу, но будет связана с основной схемой.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-zinc-400 text-sm font-bold mb-2 uppercase tracking-wider">Номер версии</label>
                        <input type="text" name="version_label" placeholder="Например: 1.1, 2.0, Beta..." required
                               class="w-full bg-zinc-950 border border-zinc-800 rounded-xl py-3 px-4 text-white focus:border-emerald-500 outline-none transition-colors">
                    </div>
                    <div>
                        <label class="block text-zinc-400 text-sm font-bold mb-2 uppercase tracking-wider">Что нового? (кратко)</label>
                        <input type="text" name="changelog" placeholder="Исправлены ошибки, добавлен декор..."
                               class="w-full bg-zinc-950 border border-zinc-800 rounded-xl py-3 px-4 text-white focus:border-emerald-500 outline-none transition-colors">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-zinc-400 text-sm font-bold mb-2 uppercase tracking-wider">Стиль (можно изменить)</label>
                        <select name="style" class="w-full bg-zinc-950 border border-zinc-800 rounded-xl py-3 px-4 text-white focus:border-emerald-500 outline-none transition-colors cursor-pointer">
                            <?php foreach($BUILD_STYLES as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $parent_schem['style'] == $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-zinc-400 text-sm font-bold mb-2 uppercase tracking-wider">Тип (можно изменить)</label>
                        <select name="type" class="w-full bg-zinc-950 border border-zinc-800 rounded-xl py-3 px-4 text-white focus:border-emerald-500 outline-none transition-colors cursor-pointer">
                            <?php foreach($BUILD_TYPES as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $parent_schem['type'] == $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-zinc-400 text-sm font-bold mb-2 uppercase tracking-wider">Описание версии (или оставить как есть)</label>
                    <textarea name="description" rows="3" 
                              class="w-full bg-zinc-950 border border-zinc-800 rounded-xl py-3 px-4 text-white focus:border-emerald-500 outline-none transition-colors resize-none"><?= htmlspecialchars($parent_schem['description']) ?></textarea>
                </div>
            </div>
        <?php else: ?>
            <div>
                <label class="block text-sm font-medium text-zinc-300 mb-2">Название схемы *</label>
                <input type="text" name="title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required maxlength="100" placeholder="Например: Супер-эффективная ферма железа" class="w-full bg-zinc-950 border border-zinc-700 rounded-xl py-3 px-4 text-white focus:outline-none focus:border-blue-500 transition-colors">
            </div>
            <div>
                <label class="block text-sm font-medium text-zinc-300 mb-2">Описание</label>
                <textarea name="description" rows="5" placeholder="Подробное описание..." class="w-full bg-zinc-950 border border-zinc-700 rounded-xl py-3 px-4 text-white focus:outline-none focus:border-blue-500 transition-colors"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-zinc-300 mb-2">Стиль постройки</label>
                    <select name="style" class="w-full bg-zinc-950 border border-zinc-700 rounded-xl py-3 px-4 text-white focus:outline-none focus:border-blue-500 transition-colors appearance-none cursor-pointer">
                        <option value="" <?= empty($_POST['style']) ? 'selected' : '' ?>>Не указан</option>
                        <?php foreach($BUILD_STYLES as $key => $label): ?>
                            <option value="<?= $key ?>" <?= ($_POST['style'] ?? '') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-300 mb-2">Тип постройки</label>
                    <select name="type" class="w-full bg-zinc-950 border border-zinc-700 rounded-xl py-3 px-4 text-white focus:outline-none focus:border-blue-500 transition-colors appearance-none cursor-pointer">
                        <option value="" <?= empty($_POST['type']) ? 'selected' : '' ?>>Не указан</option>
                        <?php foreach($BUILD_TYPES as $key => $label): ?>
                            <option value="<?= $key ?>" <?= ($_POST['type'] ?? '') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-zinc-800">
            <div>
                <label class="block text-sm font-medium text-zinc-300 mb-2">Файл .litematic *</label>
                <div class="relative border-2 border-dashed border-zinc-700 hover:border-blue-500 bg-zinc-950 rounded-2xl p-8 text-center transition-colors h-48 flex flex-col justify-center items-center">
                    <input type="file" id="fileInput" name="schematic_file" accept=".litematic" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                    <i class="fa-solid fa-file-arrow-up text-4xl text-blue-400 mb-3" id="uploadIcon"></i>
                    <div class="text-zinc-300 font-medium mb-1" id="fileName">Нажмите или перетащите файл</div>
                    <div class="text-xs text-zinc-500" id="fileStatus">Максимум 50 МБ</div>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-zinc-300 mb-2">Новое 3D-превью</label>
                <div class="bg-black rounded-2xl h-48 flex items-center justify-center overflow-hidden border border-zinc-800 relative">
                    <img id="previewImage" class="w-full h-full object-cover opacity-0 transition-opacity duration-300" alt="Превью" style="image-rendering: pixelated;">
                    <div id="previewPlaceholder" class="absolute text-zinc-600 text-sm flex flex-col items-center">
                        <i class="fa-solid fa-camera text-3xl mb-2"></i>
                        Появится после выбора файла
                    </div>
                </div>
                <input type="hidden" name="thumbnail_base64" id="thumbnail_base64">
            </div>
        </div>

        <button type="submit" id="submitBtn" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-4 px-6 rounded-xl transition-all shadow-lg shadow-blue-500/20 mt-4 disabled:opacity-50 disabled:cursor-not-allowed">
            <i class="fa-solid <?= $is_update ? 'fa-clock-rotate-left' : 'fa-upload' ?> mr-2"></i> 
            <?= $is_update ? 'Опубликовать обновление' : 'Опубликовать схему' ?>
        </button>
    </form>
</div>

<!-- Маленький холст для легкого фото (512x384) -->
<canvas id="renderCanvas" width="512" height="384" style="display:none;"></canvas>

<script type="module">
    import * as NBT from "https://cdn.jsdelivr.net/npm/nbtify@2.2.0/+esm";
    if (typeof BigInt !== 'undefined' && !BigInt.prototype.toJSON) { BigInt.prototype.toJSON = function () { return this.toString(); }; }

    function findDeep(obj, targetKey) { if (!obj || typeof obj !== 'object') return null; if (targetKey in obj) return obj[targetKey]; if ('data' in obj && typeof obj.data === 'object') { if (targetKey in obj.data) return obj.data[targetKey]; } for (let key in obj) { const found = findDeep(obj[key], targetKey); if (found !== null) return found; } return null; }
    const invisibleBlocks = ['air', 'light', 'barrier', 'structure_void', 'cave_air', 'void_air'];
    function isInvisible(name) { return name ? invisibleBlocks.some(b => name.toLowerCase().includes(b)) : true; }
    function isPlantBlock(name) { if(!name) return false; name = name.toLowerCase(); if(name.includes('grass_block')) return false; return ['grass','fern','bush','flower','sapling','mushroom','web','lily','tulip','orchid','allium','bluet','daisy','dandelion','poppy','rose','lilac','peony','cornflower','kelp','bamboo','dripleaf','cane','vine','wheat','carrot','potato','beetroot','sprouts','roots','crop'].some(p => name.includes(p)); }
    function isBlockTransparent(name) { if(!name) return true; name = name.toLowerCase(); if(isInvisible(name) || isPlantBlock(name)) return true; return ['glass','leaves','slab','stairs','fence','door','trapdoor','wall','pane','water','lava','chest','lantern','torch','button','lever','sign','bed','carpet','snow','ladder','rail','wire','camp','anvil','hopper','cauldron','bell','chain','rod','daylight_detector','repeater','comparator','potted','enchanting_table','lectern'].some(t => name.includes(t)); }
    function getBlockColor(name) { if (!name) return '#ffffff'; name = name.toLowerCase().replace('minecraft:', ''); if (name.includes('grass_block') || name.includes('emerald') || name.includes('slime')) return '#4ade80'; if (name.includes('leaves') || name.includes('vine') || name.includes('fern') || name.includes('wheat') || name === 'short_grass') return '#22c55e'; if (name.includes('dirt') || name.includes('podzol') || name.includes('path') || name.includes('farmland') || name.includes('pot')) return '#854d0e'; if (name.includes('wood') || name.includes('log') || name.includes('planks') || name.includes('door') || name.includes('fence') || name.includes('chest') || name.includes('barrel') || name.includes('sign') || name.includes('button')) return '#b45309'; if (name.includes('stone') || name.includes('cobble') || name.includes('andesite') || name.includes('gravel') || name.includes('bedrock') || name.includes('furnace') || name.includes('deepslate')) return '#6b7280'; if (name.includes('sand') || name.includes('sponge') || name.includes('hay') || name.includes('gold')) return '#fcd34d'; if (name.includes('water')) return '#3b82f6'; if (name.includes('glass') || name.includes('ice')) return '#7dd3fc'; if (name.includes('lava') || name.includes('magma') || name.includes('redstone') || name.includes('brick') || name.includes('tnt') || name.includes('mushroom')) return '#ef4444'; if (name.includes('iron') || name.includes('quartz') || name.includes('snow') || name.includes('white') || name.includes('diorite')) return '#f8fafc'; if (name.includes('obsidian') || name.includes('black') || name.includes('coal')) return '#1e293b'; let hash = 0; for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash); return `hsl(${Math.abs(hash) % 360}, 60%, 50%)`; }
    function decodeBlockStates(region) { const sx = Math.abs(Number(region.Size?.x ?? 0)), sy = Math.abs(Number(region.Size?.y ?? 0)), sz = Math.abs(Number(region.Size?.z ?? 0)); const volume = sx * sy * sz; const blocks = new Uint16Array(volume); const paletteLen = (region.BlockStatePalette || []).length; const longs = region.BlockStates || []; if (volume === 0 || paletteLen === 0 || longs.length === 0) return blocks; let bitsPerEntry = Math.max(2, Math.ceil(Math.log2(paletteLen))); const entriesPerLongModern = Math.floor(64 / bitsPerEntry); const expectedModern = Math.ceil(volume / entriesPerLongModern); const mask = (1n << BigInt(bitsPerEntry)) - 1n; if (longs.length === expectedModern) { let blockIdx = 0; for (let i = 0; i < longs.length; i++) { let currentLong = BigInt(longs[i]); for (let j = 0; j < entriesPerLongModern; j++) { if (blockIdx >= volume) break; let id = Number(currentLong & mask); blocks[blockIdx] = id < paletteLen ? id : 0; currentLong >>= BigInt(bitsPerEntry); blockIdx++; } } } else { let longIdx = 0, bitOffset = 0n, currentLong = BigInt(longs[0]), bpe = BigInt(bitsPerEntry); for (let i = 0; i < volume; i++) { let val = 0n, bitsRead = 0n; while (bitsRead < bpe) { let bitsAvail = 64n - bitOffset; let bitsToRead = (bpe - bitsRead) < bitsAvail ? (bpe - bitsRead) : bitsAvail; let localMask = (1n << bitsToRead) - 1n; let part = (currentLong >> bitOffset) & localMask; val |= (part << bitsRead); bitsRead += bitsToRead; bitOffset += bitsToRead; if (bitOffset >= 64n) { longIdx++; currentLong = longIdx < longs.length ? BigInt(longs[longIdx]) : 0n; bitOffset = 0n; } } blocks[i] = Number(val) < paletteLen ? Number(val) : 0; } } return blocks; }
    function getTextureNames(name, props) { name = name.toLowerCase().replace('minecraft:', ''); if (name === 'short_grass') return Array(6).fill('short_grass'); if (name === 'wheat') return Array(6).fill('wheat_stage7'); if (name === 'enchanting_table') return ['enchanting_table_side', 'enchanting_table_side', 'enchanting_table_top', 'enchanting_table_bottom', 'enchanting_table_side', 'enchanting_table_side']; if (name === 'lectern') return ['lectern_sides', 'lectern_sides', 'lectern_top', 'lectern_base', 'lectern_front', 'lectern_sides']; let base = name.replace('_stairs', '').replace('_slab', '').replace('_wall', '').replace('_fence_gate', '').replace('_fence', '').replace('_button', '').replace('_pressure_plate', ''); if (base.startsWith('potted_')) base = base.replace('potted_', ''); if (base === 'stone_brick') base = 'stone_bricks'; if (base === 'deepslate_tile') base = 'deepslate_tiles'; if (['oak', 'spruce', 'birch', 'jungle', 'acacia', 'dark_oak', 'mangrove', 'cherry', 'crimson', 'warped'].includes(base)) base += '_planks'; let top = base, bottom = base, side = base; if (base === 'grass_block') { top = 'grass_block_top'; bottom = 'dirt'; side = 'grass_block_side'; } else if (base === 'podzol') { top = 'podzol_top'; bottom = 'dirt'; side = 'podzol_side'; } else if (base === 'mycelium') { top = 'mycelium_top'; bottom = 'dirt'; side = 'mycelium_side'; } else if (base === 'dirt_path') { top = 'dirt_path_top'; bottom = 'dirt'; side = 'dirt_path_side'; } else if (base.endsWith('_log') || base.endsWith('_stem')) { top = base + '_top'; bottom = base + '_top'; side = base; if (props?.axis === 'x' || props?.axis === 'z') { top = base; bottom = base; side = base; } } else if (isPlantBlock(name)) { if (props?.half === 'upper') top = base + '_top'; else if (props?.half === 'lower') top = base + '_bottom'; bottom = top; side = top; } else if (name.includes('door') && !name.includes('trapdoor')) { top = base + (props?.half === 'upper' ? '_top' : '_bottom'); bottom = top; side = top; } else if (name === 'water') { top = 'water_still'; bottom = 'water_still'; side = 'water_still'; } else if (name === 'lava') { top = 'lava_still'; bottom = 'lava_still'; side = 'lava_still'; } else if (base === 'furnace' || base === 'smoker' || base === 'blast_furnace') { top = base + '_top'; bottom = 'stone'; side = base + (props?.lit === 'true' ? '_front_on' : '_front'); } return [side, side, top, bottom, side, side]; }
    const textureCache = {}; const texLoader = new THREE.TextureLoader();
    function loadTextureAsync(texName) { if (!texName) return Promise.resolve(null); if (textureCache[texName] !== undefined) return textureCache[texName]; return new Promise(resolve => { const url = `https://raw.githubusercontent.com/InventivetalentDev/minecraft-assets/1.20.1/assets/minecraft/textures/block/${texName}.png`; texLoader.load(url, (tex) => { tex.magFilter = THREE.NearestFilter; tex.minFilter = THREE.NearestFilter; tex.colorSpace = THREE.SRGBColorSpace; tex.wrapS = THREE.RepeatWrapping; tex.wrapT = THREE.RepeatWrapping; textureCache[texName] = Promise.resolve(tex); resolve(tex); }, undefined, () => { textureCache[texName] = Promise.resolve(null); resolve(null); }); }); }
    function buildBlockParts(name, props) { name = name.toLowerCase(); const p = props || {}, parts = []; if (name.includes('button')) parts.push({x:5, y:5, z:14, w:6, h:4, d:2}); else if (name.includes('gate')) parts.push({x:0, y:5, z:7, w:16, h:6, d:2}); else if (name.startsWith('potted_') || name.includes('pot')) parts.push({x:5, y:0, z:5, w:6, h:6, d:6}); else if (name === 'enchanting_table') parts.push({x:0, y:0, z:0, w:16, h:12, d:16}); else if (name === 'lectern') parts.push({x:0, y:0, z:0, w:16, h:14, d:16}); else if (isPlantBlock(name)) { parts.push({x: 7.9, y: 0, z: 0, w: 0.2, h: 16, d: 16}); parts.push({x: 0, y: 0, z: 7.9, w: 16, h: 16, d: 0.2}); } else if (name.includes('slab')) { if (p.type === 'top') parts.push({x:0, y:8, z:0, w:16, h:8, d:16}); else if (p.type === 'double') parts.push({x:0, y:0, z:0, w:16, h:16, d:16}); else parts.push({x:0, y:0, z:0, w:16, h:8, d:16}); } else if (name.includes('stairs')) { const isTop = (p.half === 'top'); parts.push({x:0, y: isTop ? 8 : 0, z:0, w:16, h:8, d:16}); const y2 = isTop ? 0 : 8; if (p.facing === 'east') parts.push({x:0, y:y2, z:0, w:8, h:8, d:16}); else if (p.facing === 'west') parts.push({x:8, y:y2, z:0, w:8, h:8, d:16}); else if (p.facing === 'south') parts.push({x:0, y:y2, z:0, w:16, h:8, d:8}); else parts.push({x:0, y:y2, z:8, w:16, h:8, d:8}); } else if (name.includes('fence') && !name.includes('gate')) { parts.push({x:6, y:0, z:6, w:4, h:16, d:4}); if (p.north === 'true') parts.push({x:6, y:6, z:0, w:4, h:9, d:6}); if (p.south === 'true') parts.push({x:6, y:6, z:10, w:4, h:9, d:6}); if (p.west === 'true') parts.push({x:0, y:6, z:6, w:6, h:9, d:4}); if (p.east === 'true') parts.push({x:10, y:6, z:6, w:6, h:9, d:4}); } else if (name.includes('wall') && !name.includes('sign') && !name.includes('torch')) { parts.push({x:4, y:0, z:4, w:8, h:16, d:8}); if (p.north === 'true' || p.north === 'low' || p.north === 'tall') parts.push({x:4, y:0, z:0, w:8, h:14, d:4}); if (p.south === 'true' || p.south === 'low' || p.south === 'tall') parts.push({x:4, y:0, z:12, w:8, h:14, d:4}); if (p.west === 'true' || p.west === 'low' || p.west === 'tall') parts.push({x:0, y:0, z:4, w:4, h:14, d:8}); if (p.east === 'true' || p.east === 'low' || p.east === 'tall') parts.push({x:12, y:0, z:4, w:4, h:14, d:8}); } else if (name.includes('pane') || name.includes('bars')) { parts.push({x:7, y:0, z:7, w:2, h:16, d:2}); if (p.north === 'true') parts.push({x:7, y:0, z:0, w:2, h:16, d:7}); if (p.south === 'true') parts.push({x:7, y:0, z:9, w:2, h:16, d:7}); if (p.west === 'true') parts.push({x:0, y:0, z:7, w:7, h:16, d:2}); if (p.east === 'true') parts.push({x:9, y:0, z:7, w:7, h:16, d:2}); } else if (name.includes('trapdoor')) { const t = 3; if (p.open === 'true') { if (p.facing === 'north') parts.push({x:0, y:0, z:16-t, w:16, h:16, d:t}); else if (p.facing === 'south') parts.push({x:0, y:0, z:0, w:16, h:16, d:t}); else if (p.facing === 'west') parts.push({x:16-t, y:0, z:0, w:t, h:16, d:16}); else parts.push({x:0, y:0, z:0, w:t, h:16, d:16}); } else { if (p.half === 'top') parts.push({x:0, y:16-t, z:0, w:16, h:t, d:16}); else parts.push({x:0, y:0, z:0, w:16, h:t, d:16}); } } else if (name.includes('door') && !name.includes('trapdoor')) { const t = 3; if (p.facing === 'east') parts.push({x:0, y:0, z:0, w:t, h:16, d:16}); else if (p.facing === 'west') parts.push({x:16-t, y:0, z:0, w:t, h:16, d:16}); else if (p.facing === 'south') parts.push({x:0, y:0, z:0, w:16, h:16, d:t}); else parts.push({x:0, y:0, z:16-t, w:16, h:16, d:t}); } else if (name.includes('carpet') || name.includes('pressure_plate') || name.includes('snow')) parts.push({x:0, y:0, z:0, w:16, h:1, d:16}); else if (name.includes('bed')) parts.push({x:0, y:0, z:0, w:16, h:9, d:16}); else if (name.includes('torch')) { if (name.includes('wall_torch')) { if (p.facing === 'east') parts.push({x: 0, y: 4, z: 7, w: 4, h: 10, d: 2}); else if (p.facing === 'west') parts.push({x: 12, y: 4, z: 7, w: 4, h: 10, d: 2}); else if (p.facing === 'south') parts.push({x: 7, y: 4, z: 0, w: 2, h: 10, d: 4}); else parts.push({x: 7, y: 4, z: 12, w: 2, h: 10, d: 4}); } else parts.push({x:7, y:0, z:7, w:2, h:10, d:2}); } else if (name.includes('chest')) parts.push({x:1, y:0, z:1, w:14, h:14, d:14}); else if (name.includes('daylight_detector') || name.includes('camp')) parts.push({x:0, y:0, z:0, w:16, h:7, d:16}); else if (name.includes('redstone_wire') || name.includes('rail')) parts.push({x:0, y:0, z:0, w:16, h:0.5, d:16}); else if (name.includes('repeater') || name.includes('comparator')) parts.push({x:0, y:0, z:0, w:16, h:2, d:16}); else if (name.includes('lantern') || name.includes('bell')) parts.push({x:4, y: p.hanging === 'true' ? 7 : 0, z:4, w:8, h:9, d:8}); else parts.push({x:0, y:0, z:0, w:16, h:16, d:16}); return parts; }
    function createExactGeometry(parts) { if (parts.length === 0) parts.push({x:0, y:0, z:0, w:16, h:16, d:16}); let totalVerts = 0, totalInds = 0; parts.forEach(p => { const g = new THREE.BoxGeometry(p.w/16, p.h/16, p.d/16); totalVerts += g.attributes.position.count; totalInds += g.index.count; }); const posArray = new Float32Array(totalVerts * 3), normArray = new Float32Array(totalVerts * 3), uvArray = new Float32Array(totalVerts * 2), indArray = new Uint32Array(totalInds); const merged = new THREE.BufferGeometry(); let vOffset = 0, iOffset = 0; parts.forEach(p => { const geo = new THREE.BoxGeometry(p.w/16, p.h/16, p.d/16); geo.translate((p.x + p.w/2 - 8)/16, (p.y + p.h/2 - 8)/16, (p.z + p.d/2 - 8)/16); posArray.set(geo.attributes.position.array, vOffset * 3); normArray.set(geo.attributes.normal.array, vOffset * 3); const positions = geo.attributes.position, normals = geo.attributes.normal; for (let i = 0; i < positions.count; i++) { const nx = Math.abs(normals.getX(i)), ny = Math.abs(normals.getY(i)); const px = positions.getX(i) + 0.5, py = positions.getY(i) + 0.5, pz = positions.getZ(i) + 0.5; if (nx > 0.5) { uvArray[(vOffset + i)*2] = pz; uvArray[(vOffset + i)*2+1] = py; } else if (ny > 0.5) { uvArray[(vOffset + i)*2] = px; uvArray[(vOffset + i)*2+1] = pz; } else { uvArray[(vOffset + i)*2] = px; uvArray[(vOffset + i)*2+1] = py; } } for(let i=0; i<geo.index.array.length; i++) indArray[iOffset + i] = geo.index.array[i] + vOffset; geo.groups.forEach(g => merged.addGroup(iOffset + g.start, g.count, g.materialIndex)); vOffset += geo.attributes.position.count; iOffset += geo.index.count; }); merged.setAttribute('position', new THREE.BufferAttribute(posArray, 3)); merged.setAttribute('normal', new THREE.BufferAttribute(normArray, 3)); merged.setAttribute('uv', new THREE.BufferAttribute(uvArray, 2)); merged.setIndex(new THREE.BufferAttribute(indArray, 1)); return merged; }

    const fileInput = document.getElementById('fileInput');
    const submitBtn = document.getElementById('submitBtn');

    fileInput.addEventListener('change', async (e) => {
        if (!e.target.files.length) return;
        const file = e.target.files[0];
        
        document.getElementById('fileName').textContent = file.name;
        document.getElementById('fileStatus').innerHTML = '<i class="fa-solid fa-spinner fa-spin text-blue-400"></i> Создание 3D превью...';
        document.getElementById('uploadIcon').className = "fa-solid fa-cubes text-4xl text-emerald-400 mb-3";
        submitBtn.disabled = true;

        try {
            const buffer = await file.arrayBuffer();
            const parsed = await NBT.read(buffer, { endian: "big" });
            const regionsObj = findDeep(parsed, 'Regions') || findDeep(parsed, 'regions') || {};
            const regionNames = Object.keys(regionsObj);
            
            if (!regionNames.length) throw new Error("Не удалось найти регионы (Regions) в файле.");
            
            const region = regionsObj[regionNames[0]];
            const sx = Math.abs(Number(region.Size?.x ?? 0)), sy = Math.abs(Number(region.Size?.y ?? 0)), sz = Math.abs(Number(region.Size?.z ?? 0));
            const volume = sx * sy * sz;
            const maxDim = Math.max(sx, sy, sz);

            const canvas = document.getElementById('renderCanvas');
            const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, preserveDrawingBuffer: true, alpha: true });
            renderer.setSize(512, 384);
            renderer.setClearColor(0x0f172a, 1);

            const scene = new THREE.Scene();
            const camera = new THREE.PerspectiveCamera(40, 512/384, 0.1, maxDim * 10);
            camera.position.set(0, maxDim * 0.1, maxDim * 1.5);
            camera.lookAt(0, 0, 0);

            scene.add(new THREE.AmbientLight(0xffffff, 0.95));
            const dirLight = new THREE.DirectionalLight(0xffffff, 0.5);
            dirLight.position.set(0, maxDim, maxDim);
            scene.add(dirLight);

            const palette = region.BlockStatePalette || [];
            const blocks = decodeBlockStates(region);
            
            const isTransLUT = new Uint8Array(palette.length), isInvisLUT = new Uint8Array(palette.length), isComplexLUT = new Uint8Array(palette.length);
            for(let i = 0; i < palette.length; i++) { 
                const name = palette[i]?.Name || ''; 
                isTransLUT[i] = isBlockTransparent(name) ? 1 : 0; 
                isInvisLUT[i] = isInvisible(name) ? 1 : 0; 
                isComplexLUT[i] = buildBlockParts(name, palette[i]?.Properties).length > 1 ? 1 : 0; 
            }
            
            const instanceCounts = new Array(palette.length).fill(0);
            const isVisibleFlags = new Uint8Array(volume);
            for (let y = 0; y < sy; y++) { 
                for (let z = 0; z < sz; z++) { 
                    for (let x = 0; x < sx; x++) { 
                        const idx = (y * sz * sx) + (z * sx) + x; const id = blocks[idx]; 
                        if (id >= palette.length || isInvisLUT[id]) continue; 
                        let visible = false; 
                        if (x === 0 || isTransLUT[blocks[idx - 1]]) visible = true; 
                        else if (x === sx - 1 || isTransLUT[blocks[idx + 1]]) visible = true; 
                        else if (y === 0 || isTransLUT[blocks[idx - (sz * sx)]]) visible = true; 
                        else if (y === sy - 1 || isTransLUT[blocks[idx + (sz * sx)]]) visible = true; 
                        else if (z === 0 || isTransLUT[blocks[idx - sx]]) visible = true; 
                        else if (z === sz - 1 || isTransLUT[blocks[idx + sx]]) visible = true; 
                        if (visible || isComplexLUT[id]) { instanceCounts[id]++; isVisibleFlags[idx] = 1; } 
                    } 
                } 
            }

            const instances = new Array(palette.length);
            const texPromises = [];
            const dummy = new THREE.Object3D();

            for(let id=0; id<palette.length; id++) {
                if(instanceCounts[id] > 0) {
                    const block = palette[id];
                    const name = (block.Name || '').toLowerCase();
                    const isGlassOrWater = name.includes('glass') || name.includes('water');
                    const fallbackColor = getBlockColor(name);
                    
                    const parts = buildBlockParts(name, block.Properties);
                    const geo = createExactGeometry(parts);
                    
                    const tempMats = Array(6).fill(null).map(() => new THREE.MeshLambertMaterial({ color: fallbackColor }));
                    instances[id] = new THREE.InstancedMesh(geo, tempMats, instanceCounts[id]);
                    
                    let currIdx = 0;
                    for (let y = 0; y < sy; y++) {
                        for (let z = 0; z < sz; z++) {
                            for (let x = 0; x < sx; x++) {
                                const idx = (y * sz * sx) + (z * sx) + x;
                                if (isVisibleFlags[idx] && blocks[idx] === id) {
                                    dummy.position.set(x - sx/2 + 0.5, y - sy/2 + 0.5, z - sz/2 + 0.5);
                                    dummy.updateMatrix();
                                    instances[id].setMatrixAt(currIdx++, dummy.matrix);
                                }
                            }
                        }
                    }
                    scene.add(instances[id]);

                    const p = (async () => {
                        const texNames = getTextureNames(name, block.Properties);
                        const textures = await Promise.all(texNames.map(tn => loadTextureAsync(tn)));
                        const texturedMats = textures.map((tex, faceIndex) => { 
                            let matColor = tex ? 0xffffff : fallbackColor; 
                            if (tex) { 
                                if (name.includes('leaves') || name.includes('vine') || name.includes('fern') || name.includes('wheat') || name === 'minecraft:short_grass') { matColor = 0x55c93f; } 
                                else if (name.includes('grass_block') && faceIndex === 2) { matColor = 0x55c93f; } 
                            } 
                            return new THREE.MeshLambertMaterial({ color: matColor, map: tex || null, transparent: isGlassOrWater || !!tex || isPlantBlock(name), opacity: isGlassOrWater ? 0.7 : 1.0, alphaTest: isPlantBlock(name) ? 0.5 : 0.1, side: THREE.DoubleSide }); 
                        });
                        instances[id].material = texturedMats;
                    })();
                    texPromises.push(p);
                }
            }

            await Promise.all(texPromises);
            renderer.render(scene, camera);
            
            const base64 = canvas.toDataURL('image/jpeg', 0.65);
            document.getElementById('thumbnail_base64').value = base64;
            
            const previewImg = document.getElementById('previewImage');
            previewImg.src = base64;
            previewImg.classList.remove('opacity-0');
            document.getElementById('previewPlaceholder').classList.add('hidden');
            
            document.getElementById('fileStatus').innerHTML = '<i class="fa-solid fa-check text-emerald-400"></i> Готово к загрузке';
            submitBtn.disabled = false;
            renderer.dispose();

        } catch (err) {
            console.error(err);
            document.getElementById('fileStatus').innerHTML = `<span class="text-red-400 text-xs">Ошибка: ${err.message}</span>`;
            submitBtn.disabled = true;
        }
    });
</script>

<?php require_once __DIR__ . '/footer.php'; ?>