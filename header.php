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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Podkova:wght@600;700;800&family=Golos+Text:wght@400;500;600;700&display=swap">

    <!-- ===== Дизайн-система SchemHub (внедрено из /SchemHub.dc.html) ===== -->
    <style>
        :root {
            color-scheme: dark;
            --color-bg: #1b1815;
            --color-surface: #262119;
            --color-text: #f2e7d5;
            --color-divider: color-mix(in srgb, #f2e7d5 15%, transparent);
            --color-accent: #d67f48;
            --color-accent-2: #8fa073;
            --color-accent-100: #402310; --color-accent-200: #643312; --color-accent-300: #8c491a;
            --color-accent-400: #b2622d; --color-accent-500: #d67f48; --color-accent-600: #f6a06b;
            --color-accent-700: #ffc6a5; --color-accent-800: #ffe1d0; --color-accent-900: #fff2eb;
            --color-accent-2-100: #272e1b; --color-accent-2-200: #3d472b; --color-accent-2-300: #56633f;
            --color-accent-2-400: #728157; --color-accent-2-500: #8fa073; --color-accent-2-600: #aebf92;
            --color-accent-2-700: #ccdbb2; --color-accent-2-800: #e1eecc; --color-accent-2-900: #f0fae1;
            --color-neutral-100: #2e2b25; --color-neutral-200: #474238; --color-neutral-300: #645c50;
            --color-neutral-400: #82796a; --color-neutral-500: #a19786; --color-neutral-600: #c0b6a5;
            --color-neutral-700: #dcd3c4; --color-neutral-800: #eee7db; --color-neutral-900: #f9f4ed;
            --shadow-sm: 0 1px 2px rgba(0,0,0,.45);
            --shadow-md: 0 4px 16px rgba(0,0,0,.5);
            --shadow-lg: 0 18px 44px rgba(0,0,0,.6);
            --font-heading: "Podkova", Georgia, serif;
            --font-body: "Golos Text", system-ui, sans-serif;
        }
        html { scroll-behavior: smooth; }
        body {
            font-family: var(--font-body);
            background: var(--color-bg);
            color: var(--color-text);
        }
        h1, h2, h3, h4, h5, h6, .font-heading { font-family: var(--font-heading); font-weight: 700; letter-spacing: -.02em; }
        a { color: inherit; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .site-grid {
            background-image: linear-gradient(color-mix(in srgb, var(--color-text) 6%, transparent) 1px, transparent 1px), linear-gradient(90deg, color-mix(in srgb, var(--color-text) 6%, transparent) 1px, transparent 1px);
            background-size: 42px 42px;
            mask-image: linear-gradient(to bottom, black 10%, transparent 86%);
        }
        .glass-panel { background: var(--color-surface); border: 1px solid var(--color-divider); box-shadow: var(--shadow-sm); }
        .brand-mark { background: var(--color-accent); color: var(--color-bg); box-shadow: var(--shadow-sm); }
        .nav-link { color: color-mix(in srgb, var(--color-text) 75%, transparent); transition: color .2s ease, background .2s ease; }
        .nav-link:hover, .nav-link.active { color: var(--color-accent-700); background: var(--color-accent-100); }

        /* Кнопки */
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border-radius: 999px; font-weight: 700; font-family: var(--font-body); font-size: 14px; padding: 10px 20px; cursor: pointer; border: none; text-decoration: none; transition: transform .18s cubic-bezier(.2,.7,.3,1), background .18s ease, box-shadow .18s ease, color .18s ease; }
        .btn-primary { background: var(--color-accent); color: var(--color-bg); box-shadow: var(--shadow-sm); }
        .btn-primary:hover { background: var(--color-accent-600); transform: translateY(-2px); }
        .btn-secondary { background: transparent; color: var(--color-text); box-shadow: inset 0 0 0 1.5px var(--color-divider); }
        .btn-secondary:hover { box-shadow: inset 0 0 0 1.5px var(--color-accent); color: var(--color-accent-700); }
        .btn-ghost { background: transparent; color: var(--color-accent); padding: 0; box-shadow: none; }
        .btn-ghost:hover { color: var(--color-accent-600); }
        .btn-block { width: 100%; }
        .btn:disabled { opacity: .5; cursor: not-allowed; transform: none; }

        /* Теги / чипы */
        .tag { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 999px; font-size: 12.5px; font-weight: 600; }
        .tag-accent { background: var(--color-accent-200); color: var(--color-accent-800); }
        .tag-accent-2 { background: var(--color-accent-2-200); color: var(--color-accent-2-900); }
        .tag-neutral { background: var(--color-neutral-200); color: var(--color-neutral-800); }
        .tag-outline { background: transparent; box-shadow: inset 0 0 0 1px var(--color-divider); color: var(--color-text); }

        /* Карточки */
        .card { display: flex; flex-direction: column; background: var(--color-surface); border-radius: 26px; transition: transform .2s cubic-bezier(.2,.7,.3,1), box-shadow .2s ease; }
        .card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
        .card-title { font-family: var(--font-heading); font-weight: 700; font-size: 16px; line-height: 1.3; color: var(--color-text); }
        .elev-sm { box-shadow: var(--shadow-sm); }
        .elev-md { box-shadow: var(--shadow-md); }
        .elev-lg { box-shadow: var(--shadow-lg); }
        .washed { position: relative; background: var(--color-neutral-200); }

        /* Формы */
        .field { display: flex; flex-direction: column; gap: 6px; }
        .field label { font-size: 13px; font-weight: 600; opacity: .7; }
        .input, select.input, textarea.input { background: var(--color-surface); color: var(--color-text); border: none; border-radius: 999px; padding: 12px 18px; font: inherit; font-size: 14px; box-shadow: inset 0 0 0 1.5px var(--color-divider); outline: none; transition: box-shadow .15s ease; width: 100%; }
        textarea.input { border-radius: 22px; resize: vertical; }
        .input:focus { box-shadow: inset 0 0 0 1.5px var(--color-accent); }

        .table { width: 100%; border-collapse: collapse; }
        .table td, .table th { padding: 10px 4px; border-bottom: 1px solid var(--color-divider); text-align: left; }

        @keyframes shBreathe { 0%,100% { opacity: .55 } 50% { opacity: 1 } }
        @keyframes shMorph {
            0%, 100% { border-radius: 58% 42% 47% 53% / 45% 52% 48% 55%; transform: rotate(0deg) scale(1) }
            50% { border-radius: 44% 56% 60% 40% / 56% 44% 56% 44%; transform: rotate(14deg) scale(1.05) }
        }
        @keyframes shFloatA { 0%, 100% { transform: translate3d(0,0,0) rotate(-4deg) } 50% { transform: translate3d(6px,-18px,0) rotate(8deg) } }
        @keyframes shFloatB { 0%, 100% { transform: translate3d(0,0,0) rotate(6deg) } 50% { transform: translate3d(-8px,16px,0) rotate(-6deg) } }
        @keyframes shRise { from { opacity: 0; transform: translateY(30px) } to { opacity: 1; transform: none } }
        @keyframes shFade { from { opacity: 0 } to { opacity: 1 } }
        @keyframes shBob { 0%, 100% { transform: translateY(0) } 50% { transform: translateY(-10px) } }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <header class="sticky top-0 z-50" style="background:color-mix(in srgb, var(--color-bg) 90%, transparent); backdrop-filter: blur(16px); border-bottom: 1px solid var(--color-divider);">
        <div class="max-w-7xl mx-auto px-5 md:px-6 h-[4.5rem] flex items-center justify-between gap-4">

            <a href="/" class="text-xl font-extrabold tracking-tight flex items-center gap-3 transition-colors">
                <span class="brand-mark w-9 h-9 rounded-xl flex items-center justify-center"><i class="fa-solid fa-cubes text-base"></i></span>
                <span class="font-heading">Schem<span style="color:var(--color-accent)">Hub</span></span>
            </a>

            <nav class="hidden md:flex items-center gap-1 font-medium text-sm">
                <a href="/" class="nav-link rounded-full px-4 py-2">Главная</a>
                <a href="/catalog" class="nav-link rounded-full px-4 py-2">Каталог</a>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="/upload" class="btn btn-primary ml-2" style="padding:9px 18px">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Загрузить
                    </a>
                <?php endif; ?>
            </nav>

            <div class="flex items-center gap-4">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="<?= get_user_url($_SESSION['user_id'], $_SESSION['first_name']) ?>" class="flex items-center gap-3 px-2.5 py-1.5 rounded-xl transition-colors">
                        <?php $avatar = $_SESSION['photo_url'] ?? 'https://ui-avatars.com/api/?background=d67f48&color=1b1815&name=' . urlencode($_SESSION['first_name']); ?>
                        <img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="w-9 h-9 rounded-full" style="box-shadow: inset 0 0 0 1.5px var(--color-divider);">
                        <span class="text-sm font-semibold hidden sm:block"><?= htmlspecialchars($_SESSION['first_name']) ?></span>
                    </a>
                    <a href="/logout.php" class="p-2" style="color: color-mix(in srgb, var(--color-text) 45%, transparent);" title="Выйти из аккаунта" onmouseover="this.style.color='#ff8a8a'" onmouseout="this.style.color='color-mix(in srgb, var(--color-text) 45%, transparent)'">
                        <i class="fa-solid fa-right-from-bracket text-lg"></i>
                    </a>
                <?php else: ?>
                    <a href="/login" class="btn btn-secondary">
                        <i class="fa-solid fa-right-to-bracket"></i>Войти
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="flex-grow max-w-7xl mx-auto w-full px-5 md:px-6 py-8 md:py-10">
