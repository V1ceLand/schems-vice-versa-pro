<?php
require_once __DIR__ . '/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM schematics WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $_SESSION['user_id']]);
$schem = $stmt->fetch();

if (!$schem) {
    die("Схема не найдена или у вас нет прав на её редактирование.");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $version_label = trim($_POST['version_label'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $style = $_POST['style'] ?? '';
    $type = $_POST['type'] ?? '';

    if (empty($title)) {
        $error = 'Название не может быть пустым.';
    } else {
        $update = $pdo->prepare("UPDATE schematics SET title = ?, version_label = ?, description = ?, style = ?, type = ? WHERE id = ?");
        $update->execute([$title, $version_label, $description, $style, $type, $id]);
        $success = 'Изменения сохранены!';
        // Обновляем данные в текущем объекте
        $schem['title'] = $title;
        $schem['version_label'] = $version_label;
        $schem['description'] = $description;
        $schem['style'] = $style;
        $schem['type'] = $type;
    }
}
?>

<div class="max-w-3xl mx-auto">
    <div class="flex items-center gap-4 mb-8">
        <a href="<?= get_schematic_url($schem['id'], $schem['title']) ?>" class="bg-zinc-900 hover:bg-zinc-800 text-white p-3 rounded-xl border border-zinc-800 transition-colors">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <h1 class="text-3xl font-bold text-white">Редактирование схемы</h1>
    </div>

    <?php if($error): ?>
        <div class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-xl mb-6 flex items-center gap-3">
            <i class="fa-solid fa-circle-exclamation"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <?php if($success): ?>
        <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl mb-6 flex items-center gap-3">
            <i class="fa-solid fa-circle-check"></i> <?= $success ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6 bg-zinc-900/50 border border-zinc-800 p-8 rounded-3xl">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:col-span-2">
                <label class="block text-zinc-400 text-sm font-bold mb-2 uppercase tracking-wider">Название постройки</label>
                <input type="text" name="title" value="<?= htmlspecialchars($schem['title']) ?>" required
                       class="w-full bg-zinc-950 border border-zinc-800 rounded-xl py-3 px-4 text-white focus:border-blue-500 outline-none transition-colors">
            </div>
            <div>
                <label class="block text-zinc-400 text-sm font-bold mb-2 uppercase tracking-wider">Версия</label>
                <input type="text" name="version_label" value="<?= htmlspecialchars($schem['version_label'] ?? '1.0') ?>" required
                       class="w-full bg-zinc-950 border border-zinc-800 rounded-xl py-3 px-4 text-white focus:border-blue-500 outline-none transition-colors">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-zinc-400 text-sm font-bold mb-2 uppercase tracking-wider">Стиль</label>
                <select name="style" class="w-full bg-zinc-950 border border-zinc-800 rounded-xl py-3 px-4 text-white focus:border-blue-500 outline-none transition-colors cursor-pointer">
                    <?php foreach($BUILD_STYLES as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $schem['style'] == $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-zinc-400 text-sm font-bold mb-2 uppercase tracking-wider">Тип постройки</label>
                <select name="type" class="w-full bg-zinc-950 border border-zinc-800 rounded-xl py-3 px-4 text-white focus:border-blue-500 outline-none transition-colors cursor-pointer">
                    <?php foreach($BUILD_TYPES as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $schem['type'] == $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-zinc-400 text-sm font-bold mb-2 uppercase tracking-wider">Описание</label>
            <textarea name="description" rows="5" 
                      class="w-full bg-zinc-950 border border-zinc-800 rounded-xl py-3 px-4 text-white focus:border-blue-500 outline-none transition-colors resize-none"><?= htmlspecialchars($schem['description']) ?></textarea>
        </div>

        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-4 rounded-xl transition-all shadow-lg shadow-blue-900/20 flex items-center justify-center gap-2">
            <i class="fa-solid fa-save"></i> Сохранить изменения
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
