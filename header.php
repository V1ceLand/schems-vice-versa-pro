<?php
if (session_status() === PHP_SESSION_NONE) { require_once __DIR__ . '/config.php'; }

// Базовые SEO значения
$page_title = $page_title ?? "SchemHub — База Litematica схем для Minecraft";
$page_description = $page_description ?? "Находите, скачивайте и делитесь постройками Litematica на платформе, созданной для сообщества.";
$page_image = $page_image ?? "https://" . $_SERVER['HTTP_HOST'] . "/assets/default-preview.jpg";
$canonical_url = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($page_description) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($canonical_url) ?>">
    
    <!-- Open Graph (Discord, VK, Telegram) -->
    <meta property="og:title" content="<?= htmlspecialchars($page_title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($page_description) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($page_image) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonical_url) ?>">
    <meta property="og:type" content="website">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($page_title) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($page_description) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($page_image) ?>">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { color-scheme: dark; }
        html { scroll-behavior: smooth; }
        body {
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:
                radial-gradient(900px 500px at 8% -12%, rgba(16,185,129,.13), transparent 62%),
                radial-gradient(780px 420px at 94% 8%, rgba(59,130,246,.13), transparent 62%),
                #09090b;
        }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .site-grid {
            background-image: linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
            background-size: 42px 42px;
            mask-image: linear-gradient(to bottom, black 10%, transparent 86%);
        }
        .glass-panel { background: rgba(24,24,27,.72); border: 1px solid rgba(255,255,255,.09); box-shadow: 0 18px 60px rgba(0,0,0,.24); }
        .brand-mark { box-shadow: 0 0 0 1px rgba(110,231,183,.24), 0 10px 28px rgba(16,185,129,.20); }
        .nav-link { color: #a1a1aa; transition: color .2s ease, background .2s ease; }
        .nav-link:hover { color: #f4f4f5; background: rgba(255,255,255,.06); }
        .diorama { perspective: 950px; transform-style: preserve-3d; }
        .diorama-card { box-shadow: 0 26px 50px rgba(0,0,0,.45), inset 0 1px rgba(255,255,255,.12); }
        .diorama-card::after { content: ""; position: absolute; inset: 0; background: linear-gradient(118deg, rgba(255,255,255,.14), transparent 32%, transparent 70%, rgba(16,185,129,.12)); pointer-events: none; }
    </style>
</head>
<body class="text-zinc-100 min-h-screen flex flex-col selection:bg-emerald-400/30">

    <header class="border-b border-white/[0.07] bg-zinc-950/75 backdrop-blur-xl sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-5 md:px-6 h-[4.5rem] flex items-center justify-between gap-4">
            
            <a href="/" class="text-xl font-extrabold tracking-tight flex items-center gap-3 hover:text-emerald-300 transition-colors">
                <span class="brand-mark w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-300 via-emerald-500 to-cyan-600 text-zinc-950 flex items-center justify-center"><i class="fa-solid fa-cubes text-base"></i></span>
                <span>Schem<span class="text-emerald-400">Hub</span></span>
            </a>
            
            <nav class="hidden md:flex items-center gap-1 font-medium text-sm">
                <a href="/" class="nav-link rounded-lg px-3 py-2">Главная</a>
                <a href="/catalog" class="nav-link rounded-lg px-3 py-2">Каталог</a>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="/upload" class="ml-2 bg-emerald-400 hover:bg-emerald-300 text-zinc-950 rounded-lg px-3.5 py-2 font-bold transition-colors">
                        <i class="fa-solid fa-cloud-arrow-up mr-1.5"></i> Загрузить
                    </a>
                <?php endif; ?>
            </nav>

            <div class="flex items-center gap-4">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="<?= get_user_url($_SESSION['user_id'], $_SESSION['first_name']) ?>" class="flex items-center gap-3 hover:bg-white/5 px-2.5 py-1.5 rounded-xl transition-colors">
                        <?php $avatar = $_SESSION['photo_url'] ?? 'https://ui-avatars.com/api/?background=2563eb&color=fff&name=' . urlencode($_SESSION['first_name']); ?>
                        <img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="w-9 h-9 rounded-full border border-zinc-700">
                        <span class="text-sm font-semibold hidden sm:block"><?= htmlspecialchars($_SESSION['first_name']) ?></span>
                    </a>
                    <a href="/logout.php" class="text-zinc-500 hover:text-red-400 p-2" title="Выйти из аккаунта">
                        <i class="fa-solid fa-right-from-bracket text-lg"></i>
                    </a>
                <?php else: ?>
                    <a href="/login" class="rounded-xl border border-white/10 bg-white/[.06] px-4 py-2.5 text-sm font-bold text-white transition hover:border-emerald-300/40 hover:bg-emerald-400/10 hover:text-emerald-200">
                        <i class="fa-solid fa-right-to-bracket mr-1.5"></i>Войти
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="flex-grow max-w-7xl mx-auto w-full px-5 md:px-6 py-8 md:py-10">
