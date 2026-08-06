<?php
// Ожидается, что переменная $schem доступна в области видимости
// Также ожидаются глобальные массивы $BUILD_STYLES и $BUILD_TYPES из config.php
$rating_val = $schem['rating'] ?? $schem['avg_rating'] ?? 0;
$dl_val = $schem['real_downloads'] ?? $schem['auth_dl'] ?? 0;
$author_avatar = get_image_url($schem['photo_url'] ?? 'https://ui-avatars.com/api/?background=d67f48&color=1b1815&name='.urlencode($schem['first_name'] ?? $schem['username'] ?? '?'));
$shape_class = (($schem['avatar_shape'] ?? 'circle') === 'square') ? 'rounded-md' : 'rounded-full';
?>
<!-- SEO Ссылка на схему через get_schematic_url -->
<div onclick="window.location.href='<?= get_schematic_url($schem['id'], $schem['title']) ?>'" class="card elev-sm cursor-pointer" style="padding:11px;gap:11px">
    <div class="relative">
        <div class="washed" style="border-radius:26px;overflow:hidden;aspect-ratio:4/3;display:flex;align-items:center;justify-content:center">
            <?php if(!empty($schem['thumbnail_path'])): ?>
                <img src="<?= get_image_url($schem['thumbnail_path']) ?>" class="w-full h-full object-cover" loading="lazy">
            <?php else: ?>
                <i class="fa-solid fa-cubes" style="font-size:40px;opacity:.2"></i>
            <?php endif; ?>
        </div>
        <div class="absolute top-2.5 right-2.5 flex items-center gap-1.5" style="background:var(--color-bg);border-radius:999px;padding:4px 11px;font-size:12px;font-weight:700;box-shadow:var(--shadow-sm)">
            <i class="fa-solid fa-star" style="color:var(--color-accent)"></i> <?= number_format($rating_val, 1) ?><span style="opacity:.45;font-weight:400">(<?= $schem['rating_count'] ?? 0 ?>)</span>
        </div>
    </div>

    <h3 class="card-title" style="padding:0 4px;margin-top:11px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;min-height:2.4em"><?= htmlspecialchars($schem['title']) ?></h3>

    <div class="flex flex-wrap gap-1.5" style="padding:0 4px;margin-top:6px">
        <?php if(!empty($schem['style']) && isset($BUILD_STYLES[$schem['style']])): ?>
            <span class="tag tag-accent"><?= htmlspecialchars($BUILD_STYLES[$schem['style']]) ?></span>
        <?php endif; ?>
        <?php if(!empty($schem['type']) && isset($BUILD_TYPES[$schem['type']])): ?>
            <span class="tag tag-accent-2"><?= htmlspecialchars($BUILD_TYPES[$schem['type']]) ?></span>
        <?php endif; ?>
    </div>

    <div class="flex items-center justify-between gap-2" style="padding:9px 4px 2px;margin-top:auto;border-top:1px solid var(--color-divider)">
        <!-- SEO Ссылка на автора -->
        <a href="<?= get_user_url($schem['user_id'], $schem['username'] ?? $schem['first_name']) ?>" onclick="event.stopPropagation();" class="flex items-center gap-2 min-w-0" style="opacity:.9">
            <img src="<?= htmlspecialchars($author_avatar) ?>" class="w-6 h-6 <?= $shape_class ?> flex-none" style="box-shadow:inset 0 0 0 1px var(--color-divider)">
            <span class="text-sm font-semibold truncate" style="max-width:100px">@<?= htmlspecialchars($schem['username'] ?? $schem['first_name']) ?></span>
        </a>

        <div class="flex items-center gap-2.5 text-xs flex-none" style="opacity:.65">
            <span title="Просмотры" class="flex items-center gap-1"><i class="fa-solid fa-eye"></i> <?= $schem['views'] ?? 0 ?></span>
            <span title="Уникальные скачивания" class="flex items-center gap-1"><i class="fa-solid fa-download"></i> <?= $dl_val ?></span>
        </div>
    </div>
</div>
