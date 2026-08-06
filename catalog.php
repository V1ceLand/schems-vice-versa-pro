
<?php
require_once __DIR__ . '/config.php';

// Задаем SEO мета-теги для header
$page_title = "Каталог Minecraft Схем | SchemHub";
$page_description = "Ищите лучшие схемы, постройки и механизмы для мода Litematica с помощью удобных фильтров и сортировки.";

require_once __DIR__ . '/header.php';

// Получаем теги (УДАЛЕНО - используем массивы из config.php)
// $tags_stmt = $pdo->query("SELECT * FROM tags ORDER BY name ASC");
// $all_tags = $tags_stmt->fetchAll();
// $type_tags = array_filter($all_tags, fn($t) => $t['category'] === 'type');
// $style_tags = array_filter($all_tags, fn($t) => $t['category'] === 'style');

// Чтение параметров фильтра
$search = $_GET['q'] ?? '';
$selected_type = $_GET['type'] ?? '';
$selected_style = $_GET['style'] ?? '';
$sort = $_GET['sort'] ?? 'new';
$time = $_GET['time'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;

$sql_from = "FROM schematics s 
             JOIN users u ON s.user_id = u.id 
             LEFT JOIN (SELECT schematic_id, COUNT(*) as auth_dl FROM user_downloads GROUP BY schematic_id) dl ON dl.schematic_id = s.id
             LEFT JOIN (SELECT schematic_id, AVG(rating) as avg_rating, COUNT(*) as rating_count FROM user_ratings GROUP BY schematic_id) r ON r.schematic_id = s.id
             WHERE s.id IN (SELECT MAX(id) FROM schematics GROUP BY COALESCE(parent_id, id))";
$params = [];

if (!empty($search)) {
    $sql_from .= " AND (s.title LIKE ? OR s.description LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if (!empty($selected_type) && array_key_exists($selected_type, $BUILD_TYPES)) {
    $sql_from .= " AND s.type = ?";
    $params[] = $selected_type;
}
if (!empty($selected_style) && array_key_exists($selected_style, $BUILD_STYLES)) {
    $sql_from .= " AND s.style = ?";
    $params[] = $selected_style;
}

if ($time === 'today') $sql_from .= " AND s.created_at >= CURDATE()";
if ($time === 'week')  $sql_from .= " AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
if ($time === 'month') $sql_from .= " AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";

$order_by = "ORDER BY s.created_at DESC";
if ($sort === 'downloads') $order_by = "ORDER BY COALESCE(dl.auth_dl, 0) DESC";
if ($sort === 'views')     $order_by = "ORDER BY s.views DESC";
if ($sort === 'rating')    $order_by = "ORDER BY COALESCE(r.avg_rating, 0) DESC, COALESCE(r.rating_count, 0) DESC";
if ($sort === 'ratio')     $order_by = "ORDER BY (COALESCE(r.avg_rating, 0) * COALESCE(dl.auth_dl, 0)) DESC";

$count_stmt = $pdo->prepare("SELECT COUNT(s.id) " . $sql_from);
$count_stmt->execute($params);
$total_items = $count_stmt->fetchColumn();
$total_pages = ceil($total_items / $per_page);
$offset = ($page - 1) * $per_page;

$sql = "SELECT s.*, u.username, u.first_name, u.photo_url, u.avatar_shape,
        COALESCE(dl.auth_dl, 0) as real_downloads, 
        COALESCE(r.avg_rating, 0) as rating, 
        COALESCE(r.rating_count, 0) as rating_count " . 
        $sql_from . " " . $order_by . " LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$schematics = $stmt->fetchAll();

// Функция сборки URL для фильтров и пагинации
function buildUrl($overrides = []) {
    $query = array_merge($_GET, $overrides);
    return '/catalog?' . http_build_query($query);
}
?>

<!-- Витрина каталога: не статичная плашка, а объёмный вход в библиотеку -->
<section class="relative isolate mb-8 overflow-hidden rounded-[2rem] border border-white/[.10] bg-zinc-900/70 px-7 py-10 md:px-12 md:py-12">
    <div class="site-grid absolute inset-0 -z-10 opacity-60"></div>
    <div class="absolute -left-24 bottom-0 h-64 w-64 rounded-full bg-blue-500/15 blur-3xl -z-10"></div>
    <div class="absolute right-0 top-0 h-72 w-72 rounded-full bg-emerald-400/15 blur-3xl -z-10"></div>
    <div class="grid items-center gap-9 lg:grid-cols-[1fr_390px]">
        <div class="relative z-10">
            <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-emerald-300/20 bg-emerald-400/10 px-3 py-1.5 text-xs font-bold uppercase tracking-[.16em] text-emerald-300"><i class="fa-solid fa-book-open"></i> Библиотека построек</div>
            <h1 class="max-w-xl text-4xl font-black leading-[.98] tracking-[-.045em] text-white md:text-6xl">Выбери идею.<br><span class="text-emerald-300">Собери свой мир.</span></h1>
            <p class="mt-5 max-w-xl text-base leading-relaxed text-zinc-300">Смотрите модель до скачивания, отбирайте проекты по стилю и находите вдохновение для следующей постройки.</p>
            <div class="mt-7 flex flex-wrap gap-3 text-sm">
                <span class="rounded-xl border border-white/[.09] bg-black/20 px-3 py-2 text-zinc-300"><i class="fa-solid fa-cubes mr-2 text-emerald-300"></i><?= number_format($total_items, 0, ',', ' ') ?> работ</span>
                <span class="rounded-xl border border-white/[.09] bg-black/20 px-3 py-2 text-zinc-300"><i class="fa-solid fa-eye mr-2 text-sky-300"></i>3D-просмотр</span>
            </div>
        </div>
        <div class="diorama relative hidden h-60 lg:block" aria-hidden="true">
            <div class="absolute inset-0 rounded-[2rem] bg-gradient-to-br from-emerald-400/10 to-blue-500/5 blur-xl"></div>
            <?php foreach (array_slice($schematics, 0, 3) as $i => $showcase): ?>
                <?php $transforms = ['translate3d(12px, 57px, -80px) rotateX(13deg) rotateY(-27deg) rotateZ(1deg)', 'translate3d(76px, 24px, 0) rotateX(13deg) rotateY(-27deg) rotateZ(1deg)', 'translate3d(163px, 71px, -42px) rotateX(13deg) rotateY(-27deg) rotateZ(1deg)']; ?>
                <div class="diorama-card absolute h-40 w-56 overflow-hidden rounded-2xl border border-white/20 bg-zinc-800" style="transform: <?= $transforms[$i] ?>">
                    <?php if (!empty($showcase['thumbnail_path'])): ?>
                        <img src="<?= htmlspecialchars(get_image_url($showcase['thumbnail_path'])) ?>" class="h-full w-full object-cover" alt="">
                    <?php else: ?>
                        <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-emerald-500/30 to-sky-700/30 text-5xl text-emerald-200"><i class="fa-solid fa-cube"></i></div>
                    <?php endif; ?>
                    <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/90 to-transparent px-4 pb-3 pt-10 text-sm font-bold text-white truncate"><?= htmlspecialchars($showcase['title']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Обертка для панели фильтров -->
<div id="catalog-filters" class="transition-opacity duration-300">
    <div class="bg-zinc-900 border border-zinc-800 rounded-2xl mb-8 shadow-sm">
        <div class="flex flex-wrap border-b border-zinc-800">
            <a href="<?= buildUrl(['sort'=>'new', 'page'=>1]) ?>" class="spa-link px-5 py-4 text-sm font-semibold transition-colors flex items-center gap-2 <?= $sort=='new' ? 'text-blue-400 border-b-2 border-blue-500 bg-blue-500/5' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/50' ?>">
                <i class="fa-solid fa-asterisk"></i> Новые
            </a>
            <a href="<?= buildUrl(['sort'=>'downloads', 'page'=>1]) ?>" class="spa-link px-5 py-4 text-sm font-semibold transition-colors flex items-center gap-2 <?= $sort=='downloads' ? 'text-blue-400 border-b-2 border-blue-500 bg-blue-500/5' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/50' ?>">
                <i class="fa-solid fa-download"></i> Скачивания
            </a>
            <a href="<?= buildUrl(['sort'=>'views', 'page'=>1]) ?>" class="spa-link px-5 py-4 text-sm font-semibold transition-colors flex items-center gap-2 <?= $sort=='views' ? 'text-blue-400 border-b-2 border-blue-500 bg-blue-500/5' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/50' ?>">
                <i class="fa-solid fa-eye"></i> Просмотры
            </a>
            <a href="<?= buildUrl(['sort'=>'rating', 'page'=>1]) ?>" class="spa-link px-5 py-4 text-sm font-semibold transition-colors flex items-center gap-2 <?= $sort=='rating' ? 'text-blue-400 border-b-2 border-blue-500 bg-blue-500/5' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/50' ?>">
                <i class="fa-solid fa-star"></i> Рейтинг
            </a>
            <a href="<?= buildUrl(['sort'=>'ratio', 'page'=>1]) ?>" class="spa-link px-5 py-4 text-sm font-semibold transition-colors flex items-center gap-2 <?= $sort=='ratio' ? 'text-blue-400 border-b-2 border-blue-500 bg-blue-500/5' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/50' ?>">
                <i class="fa-solid fa-fire text-amber-500"></i> Тренды
            </a>
        </div>
        
        <div class="flex flex-wrap gap-4 p-4 items-center bg-zinc-900/50 rounded-b-2xl">
            <select name="type" class="spa-select bg-zinc-950 border border-zinc-700 text-zinc-300 hover:border-zinc-500 py-2 px-4 rounded-xl text-sm outline-none transition-colors cursor-pointer">
                <option value="">Все типы</option>
                <?php foreach($BUILD_TYPES as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $selected_type == $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="style" class="spa-select bg-zinc-950 border border-zinc-700 text-zinc-300 hover:border-zinc-500 py-2 px-4 rounded-xl text-sm outline-none transition-colors cursor-pointer">
                <option value="">Все стили</option>
                <?php foreach($BUILD_STYLES as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $selected_style == $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="time" class="spa-select bg-zinc-950 border border-zinc-700 text-zinc-300 hover:border-zinc-500 py-2 px-4 rounded-xl text-sm outline-none transition-colors cursor-pointer">
                <option value="all" <?= $time == 'all' ? 'selected' : '' ?>>За все время</option>
                <option value="today" <?= $time == 'today' ? 'selected' : '' ?>>За сегодня</option>
                <option value="week" <?= $time == 'week' ? 'selected' : '' ?>>За неделю</option>
                <option value="month" <?= $time == 'month' ? 'selected' : '' ?>>За месяц</option>
            </select>

            <form id="search-form" class="ml-auto flex w-full md:w-auto mt-2 md:mt-0 relative">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Найти схему..." class="bg-zinc-950 border border-zinc-700 text-white py-2 px-4 rounded-l-xl text-sm outline-none w-full md:w-64 focus:border-blue-500 placeholder-zinc-500 transition-colors">
                <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-5 py-2 rounded-r-xl transition-colors font-medium">
                    <i class="fa-solid fa-search"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Обертка для сетки и пагинации (Динамический контент) -->
<div id="catalog-content" class="transition-opacity duration-300">
    <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6 text-sm text-zinc-400">
        <div>
            Показано <span class="text-white font-medium"><?= min($offset + 1, $total_items) ?> - <?= min($offset + $per_page, $total_items) ?></span> из <span class="text-white font-medium"><?= number_format($total_items, 0, ',', ' ') ?></span>
        </div>
        
        <?php if($total_pages > 1): ?>
        <div class="flex gap-2">
            <?php if($page > 1): ?>
                <a href="<?= buildUrl(['page' => $page - 1]) ?>" class="spa-link px-3 py-2 bg-zinc-900 border border-zinc-800 hover:border-zinc-600 rounded-lg transition-colors text-white"><i class="fa-solid fa-chevron-left"></i></a>
            <?php endif; ?>
            
            <?php 
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                for ($i = $start; $i <= $end; $i++): 
            ?>
                <a href="<?= buildUrl(['page' => $i]) ?>" class="spa-link px-4 py-2 rounded-lg font-medium transition-all <?= $i == $page ? 'bg-blue-600 text-white shadow-lg shadow-blue-900/20' : 'bg-zinc-900 border border-zinc-800 hover:border-zinc-600 text-zinc-300' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if($page < $total_pages): ?>
                <a href="<?= buildUrl(['page' => $page + 1]) ?>" class="spa-link px-3 py-2 bg-zinc-900 border border-zinc-800 hover:border-zinc-600 rounded-lg transition-colors text-white"><i class="fa-solid fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if(empty($schematics)): ?>
        <div class="text-center py-20 bg-zinc-900/50 rounded-3xl border border-dashed border-zinc-700">
            <i class="fa-solid fa-ghost text-6xl text-zinc-700 mb-4"></i>
            <h3 class="text-xl font-medium text-zinc-300">Ничего не найдено</h3>
            <p class="text-zinc-500 mt-2">Попробуйте изменить параметры фильтрации или поиска.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-10">
            <?php foreach($schematics as $schem): ?>
                <?php include __DIR__ . '/schematic_card.php'; ?>
            <?php endforeach; ?>
        </div>
        
        <?php if($total_pages > 1): ?>
        <div class="flex justify-center mb-8">
            <div class="flex gap-2 bg-zinc-900 border border-zinc-800 p-2 rounded-xl">
                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <a href="<?= buildUrl(['page' => $i]) ?>" class="spa-link px-4 py-2 rounded-lg font-medium transition-all <?= $i == $page ? 'bg-blue-600 text-white shadow-md shadow-blue-900/20' : 'hover:bg-zinc-800 text-zinc-400 hover:text-white' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const filtersContainer = document.getElementById('catalog-filters');
    const contentContainer = document.getElementById('catalog-content');

    async function loadCatalog(url, pushState = true) {
        filtersContainer.style.opacity = '0.5';
        contentContainer.style.opacity = '0.5';
        filtersContainer.style.pointerEvents = 'none';
        contentContainer.style.pointerEvents = 'none';

        try {
            const response = await fetch(url);
            const htmlText = await response.text();
            
            const parser = new DOMParser();
            const doc = parser.parseFromString(htmlText, 'text/html');
            
            filtersContainer.innerHTML = doc.getElementById('catalog-filters').innerHTML;
            contentContainer.innerHTML = doc.getElementById('catalog-content').innerHTML;
            
            if (pushState) {
                window.history.pushState({path: url}, '', url);
            }
        } catch (error) {
            console.error('Ошибка загрузки каталога:', error);
            window.location.href = url;
        } finally {
            filtersContainer.style.opacity = '1';
            contentContainer.style.opacity = '1';
            filtersContainer.style.pointerEvents = 'auto';
            contentContainer.style.pointerEvents = 'auto';
        }
    }

    window.addEventListener('popstate', (e) => {
        if (e.state && e.state.path) {
            loadCatalog(e.state.path, false);
        } else {
            loadCatalog(window.location.href, false);
        }
    });

    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('a.spa-link');
        if (link) {
            e.preventDefault();
            loadCatalog(link.href);
            if (window.scrollY > filtersContainer.offsetTop) {
                window.scrollTo({ top: filtersContainer.offsetTop - 20, behavior: 'smooth' });
            }
        }
    });

    document.body.addEventListener('change', (e) => {
        if (e.target.classList.contains('spa-select')) {
            const url = new URL(window.location.href);
            url.searchParams.set(e.target.name, e.target.value);
            url.searchParams.set('page', 1); 
            if (!e.target.value) {
                url.searchParams.delete(e.target.name);
            }
            loadCatalog(url.toString());
        }
    });

    document.body.addEventListener('submit', (e) => {
        if (e.target.id === 'search-form') {
            e.preventDefault();
            const formData = new FormData(e.target);
            const url = new URL(window.location.origin + '/catalog'); // Перекидываем на ЧПУ
            const q = formData.get('q');
            if (q) url.searchParams.set('q', q);
            url.searchParams.set('page', 1);
            loadCatalog(url.toString());
        }
    });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
