<?php
require_once __DIR__ . '/header.php';

// Получаем немного топовых схем для превью на главной (бегущая строка / сетка)
$top_stmt = $pdo->query("
    SELECT s.id, s.title, s.thumbnail_path, s.style, s.type, u.username
    FROM schematics s
    JOIN users u ON s.user_id = u.id
    WHERE s.id IN (SELECT MAX(id) FROM schematics GROUP BY COALESCE(parent_id, id))
    ORDER BY s.views DESC
    LIMIT 6
");
$top_schematics = $top_stmt->fetchAll();

// Статистика для героя и секции "Разделы каталога"
$stats_stmt = $pdo->query("SELECT COUNT(*) total, COUNT(DISTINCT user_id) authors, COALESCE(SUM(downloads),0) downloads FROM schematics WHERE parent_id IS NULL");
$stats = $stats_stmt->fetch();
$total_schematics = number_format($stats['total'], 0, ',', ' ');
$total_authors = number_format($stats['authors'], 0, ',', ' ');
$total_downloads = number_format($stats['downloads'], 0, ',', ' ');

$style_counts_stmt = $pdo->query("SELECT style, COUNT(*) c FROM schematics WHERE parent_id IS NULL GROUP BY style");
$style_counts = array_column($style_counts_stmt->fetchAll(), 'c', 'style');
$type_counts_stmt = $pdo->query("SELECT type, COUNT(*) c FROM schematics WHERE parent_id IS NULL GROUP BY type");
$type_counts = array_column($type_counts_stmt->fetchAll(), 'c', 'type');
?>

<!-- Hero -->
<section class="relative isolate overflow-hidden mb-16 md:mb-24 px-1">
    <div class="site-grid absolute inset-0 -z-10 opacity-70"></div>
    <div style="position:absolute;top:-15%;right:-8%;width:min(50%,420px);aspect-ratio:1;background:var(--color-accent-2-200);border-radius:58% 42% 47% 53% / 45% 52% 48% 55%;opacity:.5;animation:shMorph 26s ease-in-out infinite;z-index:-1"></div>
    <div class="flex flex-wrap items-center gap-x-16 gap-y-10 py-8 md:py-14">
        <div class="flex-1 min-w-[280px]" style="max-width:640px">
            <div class="tag tag-accent-2 mb-5" style="animation:shRise .7s cubic-bezier(.2,.7,.3,1) .05s both">Litematica · сообщество строителей</div>
            <h1 class="mb-5" style="font-size:clamp(38px,6vw,68px);line-height:.98;animation:shRise .8s cubic-bezier(.2,.7,.3,1) .12s both">Схемы .litematic<br>для Minecraft</h1>
            <p class="mb-8" style="font-size:clamp(16px,1.4vw,19px);line-height:1.6;max-width:42ch;opacity:.8;animation:shRise .8s cubic-bezier(.2,.7,.3,1) .22s both">
                Живой каталог построек: 3D-просмотр перед скачиванием, фильтры по стилю и типу, история версий у каждой схемы.
            </p>

            <form action="/catalog" method="get" class="elev-sm flex items-center gap-2 mb-10" style="background:var(--color-surface);border-radius:999px;padding:7px 7px 7px 20px;max-width:520px;animation:shRise .8s cubic-bezier(.2,.7,.3,1) .32s both">
                <i class="fa-solid fa-magnifying-glass" style="opacity:.5"></i>
                <input type="text" name="q" class="input" placeholder="Средневековый замок, статуя, ферма…" style="border:none;box-shadow:none;background:transparent;flex:1;min-width:0;padding-inline:0">
                <button type="submit" class="btn btn-primary" style="flex:none">Найти</button>
            </form>

            <div class="flex gap-8 md:gap-11 flex-wrap">
                <div style="animation:shRise .7s cubic-bezier(.2,.7,.3,1) .42s both"><div class="font-heading" style="font-size:30px;line-height:1"><?= $total_schematics ?></div><div style="font-size:13px;opacity:.6;margin-top:2px">схем в каталоге</div></div>
                <div style="animation:shRise .7s cubic-bezier(.2,.7,.3,1) .5s both"><div class="font-heading" style="font-size:30px;line-height:1"><?= $total_authors ?></div><div style="font-size:13px;opacity:.6;margin-top:2px">авторов</div></div>
                <div style="animation:shRise .7s cubic-bezier(.2,.7,.3,1) .58s both"><div class="font-heading" style="font-size:30px;line-height:1"><?= $total_downloads ?></div><div style="font-size:13px;opacity:.6;margin-top:2px">скачиваний</div></div>
            </div>
        </div>

        <div class="flex-1 min-w-[280px]" style="max-width:420px">
            <div class="washed elev-lg" style="border-radius:48px;overflow:hidden;aspect-ratio:4/5;display:flex;align-items:center;justify-content:center;background:var(--color-neutral-200)">
                <?php if (!empty($top_schematics[0]['thumbnail_path'])): ?>
                    <img src="<?= htmlspecialchars($top_schematics[0]['thumbnail_path']) ?>" class="w-full h-full object-cover">
                <?php else: ?>
                    <i class="fa-solid fa-cubes" style="font-size:64px;opacity:.25"></i>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Сейчас смотрят -->
<?php if ($top_schematics): ?>
<section class="mb-16 md:mb-24">
    <div class="flex items-baseline justify-between gap-4 flex-wrap mb-5">
        <h2 style="font-size:clamp(24px,3vw,32px)">Сейчас смотрят</h2>
        <a href="/catalog?sort=views" class="btn btn-ghost" style="font-size:14px">Все популярные <i class="fa-solid fa-arrow-right text-xs"></i></a>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <?php foreach ($top_schematics as $schem): ?>
            <a href="<?= get_schematic_url($schem['id'], $schem['title']) ?>" class="elev-sm flex items-center gap-3 transition-all" style="background:var(--color-surface);border-radius:24px;padding:11px">
                <div class="washed" style="width:56px;height:56px;border-radius:16px;overflow:hidden;flex:none;display:flex;align-items:center;justify-content:center">
                    <?php if (!empty($schem['thumbnail_path'])): ?>
                        <img src="<?= htmlspecialchars($schem['thumbnail_path']) ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <i class="fa-solid fa-cube" style="opacity:.3"></i>
                    <?php endif; ?>
                </div>
                <div class="min-w-0">
                    <div class="card-title" style="font-size:14.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($schem['title']) ?></div>
                    <div style="font-size:12px;opacity:.6;margin-top:3px">@<?= htmlspecialchars($schem['username']) ?><?php if (!empty($schem['style']) && isset($BUILD_STYLES[$schem['style']])): ?> · <?= $BUILD_STYLES[$schem['style']] ?><?php endif; ?></div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Разделы каталога -->
<section class="mb-16 md:mb-24">
    <h2 class="mb-2" style="font-size:clamp(24px,3vw,32px)">Разделы каталога</h2>
    <p class="mb-6" style="opacity:.7;font-size:15px;max-width:52ch">Стиль постройки и её назначение — два фильтра, с которых начинается почти любой поиск.</p>
    <div class="flex flex-wrap gap-2 mb-3">
        <?php foreach ($BUILD_STYLES as $key => $label): if (empty($style_counts[$key])) continue; ?>
            <a href="/catalog?style=<?= urlencode($key) ?>" class="tag tag-accent" style="transition:transform .18s cubic-bezier(.2,.7,.3,1)" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''"><?= $label ?> <span style="opacity:.55">· <?= $style_counts[$key] ?></span></a>
        <?php endforeach; ?>
    </div>
    <div class="flex flex-wrap gap-2">
        <?php foreach ($BUILD_TYPES as $key => $label): if (empty($type_counts[$key])) continue; ?>
            <a href="/catalog?type=<?= urlencode($key) ?>" class="tag tag-accent-2" style="transition:transform .18s cubic-bezier(.2,.7,.3,1)" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''"><?= $label ?> <span style="opacity:.55">· <?= $type_counts[$key] ?></span></a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Как это работает -->
<section class="mb-16 md:mb-24">
    <h2 class="mb-10" style="font-size:clamp(24px,3vw,32px)">Как это работает</h2>
    <div class="grid gap-8" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">
        <div>
            <div class="mb-4" style="width:64px;height:64px;border-radius:50%;background:var(--color-accent-200);color:var(--color-accent-800);display:grid;place-items:center;font-family:var(--font-heading);font-size:26px;animation:shBob 6s ease-in-out infinite">1</div>
            <h4 class="mb-2" style="font-size:18px">Найдите постройку</h4>
            <p style="font-size:14.5px;line-height:1.6;opacity:.75;max-width:32ch">Фильтры по стилю, типу и версии игры. В карточке — рейтинг, автор и число скачиваний.</p>
        </div>
        <div>
            <div class="mb-4" style="width:64px;height:64px;border-radius:50%;background:var(--color-accent-2-200);color:var(--color-accent-2-900);display:grid;place-items:center;font-family:var(--font-heading);font-size:26px;animation:shBob 6s ease-in-out .8s infinite">2</div>
            <h4 class="mb-2" style="font-size:18px">Осмотрите в 3D</h4>
            <p style="font-size:14.5px;line-height:1.6;opacity:.75;max-width:32ch">Модель собирается из самой схемы прямо в браузере — покрутите её перед скачиванием.</p>
        </div>
        <div>
            <div class="mb-4" style="width:64px;height:64px;border-radius:50%;background:var(--color-neutral-300);color:var(--color-neutral-900);display:grid;place-items:center;font-family:var(--font-heading);font-size:26px;animation:shBob 6s ease-in-out 1.6s infinite">3</div>
            <h4 class="mb-2" style="font-size:18px">Стройте</h4>
            <p style="font-size:14.5px;line-height:1.6;opacity:.75;max-width:32ch">Скачайте .litematic, положите в папку schematics и включите наложение в Litematica.</p>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="mb-16 md:mb-24">
    <div class="relative overflow-hidden flex items-center gap-10 flex-wrap" style="background:var(--color-accent-2-200);border-radius:48px;padding:clamp(28px,4vw,56px)">
        <div style="position:absolute;top:-70px;right:-40px;width:260px;height:260px;background:var(--color-accent-2-300);border-radius:52% 48% 44% 56% / 48% 56% 44% 52%;animation:shMorph 30s ease-in-out infinite;pointer-events:none"></div>
        <div class="relative flex-1" style="min-width:260px">
            <h2 class="mb-3" style="font-size:clamp(24px,3.2vw,34px);color:var(--color-accent-2-900)">Выложите свою постройку</h2>
            <p class="mb-6" style="font-size:15.5px;line-height:1.6;max-width:40ch;color:var(--color-accent-2-900);opacity:.85">Загрузка занимает минуту: файл, описание и теги. Версии схемы хранятся историей, старая ссылка продолжает работать.</p>
            <div class="flex gap-2 flex-wrap">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="/upload" class="btn btn-primary">Загрузить схему</a>
                <?php else: ?>
                    <a href="/login" class="btn btn-primary">Войти через Telegram</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- FAQ -->
<section class="mb-8">
    <h2 class="mb-8" style="font-size:clamp(24px,3vw,32px)">Частые вопросы</h2>
    <div class="grid gap-8" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr))">
        <div>
            <h4 class="mb-2" style="font-size:17px">Что такое .litematic</h4>
            <p class="mb-6" style="font-size:14.5px;line-height:1.65;opacity:.75">Формат мода Litematica: файл хранит все блоки постройки и их координаты. В игре он показывается полупрозрачной голограммой, по которой вы строите вручную или собираете в креативе.</p>
            <h4 class="mb-2" style="font-size:17px">Нужен ли аккаунт</h4>
            <p style="font-size:14.5px;line-height:1.65;opacity:.75">Смотреть каталог и модели можно без входа. Аккаунт нужен, чтобы скачивать схемы и публиковать свои — вход через Telegram, в один тап.</p>
        </div>
        <div>
            <h4 class="mb-2" style="font-size:17px">Можно ли выкладывать чужие постройки</h4>
            <p style="font-size:14.5px;line-height:1.65;opacity:.75">Только с разрешения автора и с указанием источника. Схемы без атрибуции удаляются по жалобе.</p>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
