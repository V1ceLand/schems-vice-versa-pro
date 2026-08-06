<?php
require_once __DIR__ . '/header.php';

// Получаем немного топовых схем для превью на главной (бегущая строка / сетка)
$top_stmt = $pdo->query("
    SELECT s.id, s.title, s.thumbnail_path, s.style, s.type, u.username 
    FROM schematics s 
    JOIN users u ON s.user_id = u.id 
    WHERE s.id IN (SELECT MAX(id) FROM schematics GROUP BY COALESCE(parent_id, id))
    ORDER BY s.views DESC 
    LIMIT 12
");
$top_schematics = $top_stmt->fetchAll();

// Считаем общее количество схем для статистики на главной
$count_stmt = $pdo->query("SELECT COUNT(*) FROM schematics");
$total_schematics = number_format($count_stmt->fetchColumn(), 0, ',', ' ');
?>

<!-- Hero -->
<section class="relative isolate overflow-hidden rounded-[2rem] border border-white/[0.09] bg-zinc-900/60 px-6 py-16 md:px-14 md:py-24 mb-10">
    <div class="site-grid absolute inset-0 -z-10 opacity-80"></div>
    <div class="absolute -right-20 -top-24 h-80 w-80 rounded-full bg-emerald-400/15 blur-3xl -z-10"></div>
    <div class="absolute left-1/2 bottom-0 h-56 w-96 -translate-x-1/2 rounded-full bg-blue-500/10 blur-3xl -z-10"></div>
    <div class="max-w-3xl">
        <div class="inline-flex items-center gap-2 rounded-full border border-emerald-300/20 bg-emerald-400/10 px-3 py-1.5 text-xs font-bold uppercase tracking-[.16em] text-emerald-300">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse"></span> Litematica community
        </div>
        <h1 class="mt-6 text-5xl font-black leading-[.95] tracking-[-.055em] text-white md:text-7xl">
            Стройте больше.<br><span class="bg-gradient-to-r from-emerald-300 to-cyan-300 bg-clip-text text-transparent">Ищите быстрее.</span>
        </h1>
        <p class="mt-6 max-w-2xl text-lg leading-relaxed text-zinc-300 md:text-xl">
            Живой каталог схем для Minecraft: скачивайте готовые постройки, смотрите их до загрузки и делитесь своими работами.
        </p>
        <div class="mt-9 flex flex-col gap-3 sm:flex-row">
            <a href="/catalog" class="rounded-xl bg-emerald-400 px-6 py-3.5 text-center font-bold text-zinc-950 shadow-[0_14px_35px_rgba(16,185,129,.24)] transition hover:-translate-y-0.5 hover:bg-emerald-300"><i class="fa-solid fa-compass mr-2"></i>Открыть каталог</a>
            <a href="/login" class="rounded-xl border border-white/10 bg-white/[.055] px-6 py-3.5 text-center font-semibold text-white transition hover:border-white/20 hover:bg-white/10"><i class="fa-solid fa-cloud-arrow-up mr-2 text-emerald-300"></i>Опубликовать схему</a>
        </div>
    </div>
    <div class="mt-12 grid max-w-2xl grid-cols-3 gap-3 border-t border-white/[.09] pt-6 text-left">
        <div><div class="text-2xl font-black text-white"><?= $total_schematics ?></div><div class="mt-1 text-xs text-zinc-400">схем в каталоге</div></div>
        <div><div class="text-2xl font-black text-white">3D</div><div class="mt-1 text-xs text-zinc-400">предпросмотр</div></div>
        <div><div class="text-2xl font-black text-white">.litematic</div><div class="mt-1 text-xs text-zinc-400">готово для игры</div></div>
    </div>
</section>

<!-- Мини-карточки популярных проектов (имитация интерфейса Modrinth) -->
<section class="mb-28 overflow-hidden">
    <div class="mb-5 flex items-center justify-between px-1"><h2 class="font-bold text-white">Сейчас смотрят</h2><a href="/catalog?sort=views" class="text-sm font-semibold text-emerald-400 hover:text-emerald-300">Все популярные <i class="fa-solid fa-arrow-right ml-1 text-xs"></i></a></div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <?php foreach($top_schematics as $schem): ?>
            <a href="<?= get_schematic_url($schem['id'], $schem['title']) ?>" class="glass-panel hover:border-emerald-400/40 hover:bg-zinc-800/80 rounded-xl p-3 flex items-center gap-3 transition-all group">
                <?php if(!empty($schem['thumbnail_path'])): ?>
                    <img src="<?= htmlspecialchars($schem['thumbnail_path']) ?>" class="w-10 h-10 rounded-lg object-cover bg-zinc-950">
                <?php else: ?>
                    <div class="w-10 h-10 rounded-lg bg-zinc-800 flex items-center justify-center text-zinc-500"><i class="fa-solid fa-cube"></i></div>
                <?php endif; ?>
                <div class="overflow-hidden flex-1">
                    <h4 class="text-white text-sm font-bold truncate group-hover:text-emerald-400 transition-colors"><?= htmlspecialchars($schem['title']) ?></h4>
                    
                    <div class="flex gap-1 mb-0.5 overflow-hidden">
                        <?php if(!empty($schem['style']) && isset($BUILD_STYLES[$schem['style']])): ?>
                            <span class="text-[9px] text-zinc-400 bg-zinc-800 px-1.5 rounded truncate border border-zinc-700/50"><?= $BUILD_STYLES[$schem['style']] ?></span>
                        <?php endif; ?>
                    </div>

                    <p class="text-zinc-500 text-xs truncate">от <?= htmlspecialchars($schem['username']) ?></p>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Секция "Для игроков" -->
<div class="max-w-6xl mx-auto px-4 mb-32">
    <div class="text-center mb-16">
        <span class="text-emerald-400 font-bold uppercase tracking-wider text-sm bg-emerald-500/10 px-3 py-1 rounded-full border border-emerald-500/20">Для игроков</span>
        <h2 class="text-3xl md:text-5xl font-bold text-white mt-6 mb-4">Более <?= $total_schematics ?> загруженных схематик</h2>
        <p class="text-zinc-400 text-lg max-w-2xl mx-auto">От огромных замков до компактных механизмов — вы точно найдёте то, что искали.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center mb-24">
        <div>
            <h3 class="text-3xl font-bold text-white mb-4">Ищите нужное<br>быстро и легко</h3>
            <p class="text-zinc-400 text-lg">Мгновенный поиск и мощные фильтры позволяют находить постройки уже во время ввода. Фильтруйте по стилю, размеру и типу посройки.</p>
        </div>
        <!-- Mockup поиска -->
        <div class="bg-zinc-900 border border-zinc-800 rounded-2xl p-6 shadow-2xl relative overflow-hidden">
            <div class="bg-zinc-950 border border-zinc-700 rounded-xl p-3 mb-4 flex items-center gap-3 text-zinc-400">
                <i class="fa-solid fa-search"></i>
                <span class="text-sm">Средневековый замок...</span>
            </div>
            <div class="space-y-3">
                <div class="h-16 bg-zinc-800/50 rounded-xl flex items-center px-4 gap-4">
                    <div class="w-10 h-10 bg-zinc-700 rounded-lg"></div>
                    <div class="flex-1 space-y-2"><div class="h-3 bg-zinc-600 rounded w-1/3"></div><div class="h-2 bg-zinc-700 rounded w-1/4"></div></div>
                </div>
                <div class="h-16 bg-zinc-800/50 rounded-xl flex items-center px-4 gap-4">
                    <div class="w-10 h-10 bg-zinc-700 rounded-lg"></div>
                    <div class="flex-1 space-y-2"><div class="h-3 bg-zinc-600 rounded w-1/2"></div><div class="h-2 bg-zinc-700 rounded w-1/3"></div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
        <!-- Mockup уведомлений -->
        <div class="bg-zinc-900 border border-zinc-800 rounded-2xl p-6 shadow-2xl order-2 md:order-1 relative">
            <h4 class="text-white font-bold mb-4 bg-zinc-800/50 py-2 px-4 rounded-lg inline-block text-sm">Уведомления</h4>
            <div class="space-y-3">
                <div class="p-4 bg-zinc-800/50 border border-zinc-700/50 rounded-xl flex gap-4">
                    <div class="w-10 h-10 bg-blue-500/20 text-blue-400 flex items-center justify-center rounded-lg"><i class="fa-solid fa-cube"></i></div>
                    <div>
                        <p class="text-white text-sm font-bold">Замок обновлён!</p>
                        <p class="text-zinc-500 text-xs">Добавлено подземелье • 2 часа назад</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="order-1 md:order-2">
            <h3 class="text-3xl font-bold text-white mb-4">Следите за тем,<br>что нравится</h3>
            <p class="text-zinc-400 text-lg">Подписывайтесь на любимых авторов, узнавайте о каждом обновлении построек и оставляйте свои отзывы.</p>
        </div>
    </div>
</div>

<!-- Секция "Для авторов" -->
<div class="bg-zinc-900/50 border-t border-zinc-800/50 py-24">
    <div class="max-w-6xl mx-auto px-4">
        <div class="text-center mb-16">
            <span class="text-blue-400 font-bold uppercase tracking-wider text-sm bg-blue-500/10 px-3 py-1 rounded-full border border-blue-500/20">Для авторов</span>
            <h2 class="text-3xl md:text-5xl font-bold text-white mt-6 mb-4">Делитесь творениями с игроками</h2>
            <p class="text-zinc-400 text-lg max-w-2xl mx-auto">Разместите свои схемы и найдите новых поклонников среди сообщества строителей.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Карточка 1 -->
            <div class="bg-zinc-900 border border-zinc-800 p-8 rounded-2xl hover:bg-zinc-800/80 transition-colors">
                <div class="w-12 h-12 bg-zinc-800 rounded-xl flex items-center justify-center mb-6 border border-zinc-700">
                    <i class="fa-solid fa-bullseye text-2xl text-zinc-300"></i>
                </div>
                <h3 class="text-xl font-bold text-white mb-3">Популярность</h3>
                <p class="text-zinc-400 text-sm leading-relaxed">Получайте внимание пользователей через удобный каталог, главную страницу и систему рекомендаций.</p>
            </div>

            <!-- Карточка 2 -->
            <div class="bg-zinc-900 border border-zinc-800 p-8 rounded-2xl hover:bg-zinc-800/80 transition-colors">
                <div class="w-12 h-12 bg-zinc-800 rounded-xl flex items-center justify-center mb-6 border border-zinc-700">
                    <i class="fa-solid fa-users text-2xl text-zinc-300"></i>
                </div>
                <h3 class="text-xl font-bold text-white mb-3">Сообщество</h3>
                <p class="text-zinc-400 text-sm leading-relaxed">Взаимодействуйте с игроками, получайте отзывы, оценки и предложения по улучшению ваших проектов.</p>
            </div>

            <!-- Карточка 3 -->
            <div class="bg-zinc-900 border border-zinc-800 p-8 rounded-2xl hover:bg-zinc-800/80 transition-colors">
                <div class="w-12 h-12 bg-zinc-800 rounded-xl flex items-center justify-center mb-6 border border-zinc-700">
                    <i class="fa-solid fa-chart-simple text-2xl text-zinc-300"></i>
                </div>
                <h3 class="text-xl font-bold text-white mb-3">Данные и статистика</h3>
                <p class="text-zinc-400 text-sm leading-relaxed">Получайте детальные отчёты о просмотрах, уникальных скачиваниях и оценках в удобной панели управления.</p>
            </div>
            
            <!-- Дополнительные карточки по необходимости -->
            <div class="bg-zinc-900 border border-zinc-800 p-8 rounded-2xl hover:bg-zinc-800/80 transition-colors">
                <div class="w-12 h-12 bg-zinc-800 rounded-xl flex items-center justify-center mb-6 border border-zinc-700">
                    <i class="fa-solid fa-cloud-arrow-up text-2xl text-zinc-300"></i>
                </div>
                <h3 class="text-xl font-bold text-white mb-3">Удобная загрузка</h3>
                <p class="text-zinc-400 text-sm leading-relaxed">Интуитивно понятный интерфейс для публикации Litematica файлов с поддержкой версий.</p>
            </div>


            <div class="bg-zinc-900 border border-zinc-800 p-8 rounded-2xl hover:bg-zinc-800/80 transition-colors relative overflow-hidden group">
                <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 to-blue-500/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <div class="w-12 h-12 bg-zinc-800 rounded-xl flex items-center justify-center mb-6 border border-zinc-700 relative z-10">
                    <i class="fa-solid fa-rocket text-2xl text-emerald-400"></i>
                </div>
                <h3 class="text-xl font-bold text-white mb-3 relative z-10">Начните прямо сейчас</h3>
                <p class="text-zinc-400 text-sm leading-relaxed relative z-10 mb-4">Присоединяйтесь к креативным строителям прямо сейчас.</p>
                <a href="upload.php" class="text-emerald-400 font-bold text-sm hover:underline relative z-10">Загрузить первую схему &rarr;</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
