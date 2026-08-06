<?php
// Ожидается, что переменная $schem доступна в области видимости
// Также ожидаются глобальные массивы $BUILD_STYLES и $BUILD_TYPES из config.php
?>
<!-- SEO Ссылка на схему через get_schematic_url -->
<div onclick="window.location.href='<?= get_schematic_url($schem['id'], $schem['title']) ?>'" class="bg-zinc-900/85 border border-white/[0.08] hover:-translate-y-1 hover:border-emerald-400/45 hover:shadow-2xl hover:shadow-emerald-950/20 rounded-2xl overflow-hidden group transition-all duration-300 flex flex-col cursor-pointer">
    <div class="h-44 bg-zinc-950 relative flex items-center justify-center overflow-hidden border-b border-white/[0.07]">
        <?php if(!empty($schem['thumbnail_path'])): ?>
            <!-- Абсолютный путь к картинке -->
            <img src="<?= get_image_url($schem['thumbnail_path']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" loading="lazy">
        <?php else: ?>
            <i class="fa-solid fa-cubes text-6xl text-zinc-800 group-hover:text-zinc-700 transition-colors"></i>
        <?php endif; ?>
        
        <div class="absolute top-3 right-3 bg-zinc-950/85 px-2.5 py-1 rounded-lg text-xs font-bold text-amber-300 border border-amber-300/20 backdrop-blur-md z-10 shadow-lg">
            <i class="fa-solid fa-star mr-1"></i> <?= number_format($schem['rating'] ?? $schem['avg_rating'] ?? 0, 1) ?> (<?= $schem['rating_count'] ?? 0 ?>)
        </div>
    </div>
    
    <div class="p-4 flex-grow flex flex-col">
        <h3 class="font-bold text-white mb-2 line-clamp-1 group-hover:text-emerald-300 transition-colors text-lg"><?= htmlspecialchars($schem['title']) ?></h3>
        
        <div class="flex flex-wrap gap-1.5 mb-3">
            <?php if(!empty($schem['style']) && isset($BUILD_STYLES[$schem['style']])): ?>
                <span class="px-2 py-0.5 bg-purple-500/10 text-purple-300 rounded-md text-[10px] font-bold border border-purple-500/20 uppercase truncate max-w-[100px]">
                    <?= htmlspecialchars($BUILD_STYLES[$schem['style']]) ?>
                </span>
            <?php endif; ?>
            <?php if(!empty($schem['type']) && isset($BUILD_TYPES[$schem['type']])): ?>
                <span class="px-2 py-0.5 bg-emerald-500/10 text-emerald-300 rounded-md text-[10px] font-bold border border-emerald-500/20 uppercase truncate max-w-[100px]">
                    <?= htmlspecialchars($BUILD_TYPES[$schem['type']]) ?>
                </span>
            <?php endif; ?>
        </div>
        
        <div class="mt-auto flex items-center justify-between border-t border-white/[0.07] pt-4">
            <!-- SEO Ссылка на автора -->
            <a href="<?= get_user_url($schem['user_id'], $schem['username'] ?? $schem['first_name']) ?>" onclick="event.stopPropagation();" class="flex items-center gap-2 hover:opacity-80 transition-opacity">
                <?php 
                    $author_avatar = get_image_url($schem['photo_url'] ?? 'https://ui-avatars.com/api/?background=2563eb&color=fff&name='.urlencode($schem['first_name'])); 
                    $shape_class = (($schem['avatar_shape'] ?? 'circle') === 'square') ? 'rounded-md' : 'rounded-full';
                ?>
                <img src="<?= htmlspecialchars($author_avatar) ?>" class="w-7 h-7 <?= $shape_class ?> border border-zinc-700">
                <span class="text-sm text-zinc-300 font-medium truncate max-w-[100px] group-hover:text-emerald-200">@<?= htmlspecialchars($schem['username'] ?? $schem['first_name']) ?></span>
            </a>
            
            <div class="flex items-center gap-3 text-xs text-zinc-400 font-medium">
                <span title="Просмотры" class="flex items-center gap-1.5"><i class="fa-solid fa-eye"></i> <?= $schem['views'] ?? 0 ?></span>
                <span title="Уникальные скачивания" class="flex items-center gap-1.5 text-emerald-400/90"><i class="fa-solid fa-download"></i> <?= $schem['real_downloads'] ?? $schem['auth_dl'] ?? 0 ?></span>
            </div>
        </div>
    </div>
</div>
