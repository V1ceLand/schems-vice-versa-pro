<?php
require_once __DIR__ . '/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

$page_title = 'Вход в SchemHub';
$page_description = 'Войдите в SchemHub через Telegram или Google, чтобы публиковать и сохранять Litematica-схемы.';
require_once __DIR__ . '/header.php';
?>

<section class="relative mx-auto flex min-h-[calc(100vh-15rem)] max-w-lg items-center py-8">
    <div class="site-grid pointer-events-none absolute inset-x-[-10rem] inset-y-0 -z-10 opacity-50"></div>
    <div class="glass-panel w-full overflow-hidden rounded-[2rem] p-6 sm:p-9">
        <div class="mb-8 text-center">
            <div class="brand-mark mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-300 via-emerald-500 to-cyan-600 text-2xl text-zinc-950"><i class="fa-solid fa-cubes"></i></div>
            <h1 class="mt-5 text-3xl font-black tracking-tight text-white">Добро пожаловать</h1>
            <p class="mt-2 text-sm leading-relaxed text-zinc-400">Войдите, чтобы публиковать схемы, оценивать работы и собирать свою библиотеку.</p>
        </div>

        <div id="google-signin" class="flex min-h-[44px] justify-center"></div>
        <p id="google-error" class="mt-3 hidden text-center text-sm text-red-300"></p>

        <div class="my-6 flex items-center gap-3 text-xs text-zinc-500"><span class="h-px flex-1 bg-white/[.09]"></span><span>или</span><span class="h-px flex-1 bg-white/[.09]"></span></div>

        <div id="telegram-login-widget" class="flex min-h-[46px] justify-center">
            <script async src="https://telegram.org/js/telegram-widget.js?22"
                    data-telegram-login="<?= htmlspecialchars(TG_BOT_USERNAME) ?>"
                    data-size="large" data-radius="10"
                    data-auth-url="/auth.php" data-request-access="write"></script>
        </div>

        <div class="mt-7 rounded-xl border border-emerald-300/10 bg-emerald-400/[.06] px-4 py-3 text-xs leading-relaxed text-zinc-400">
            <i class="fa-solid fa-shield-halved mr-1.5 text-emerald-300"></i>Мы не получаем пароль от Google или Telegram — сервис передаёт только подтверждённые данные профиля.
        </div>
    </div>
</section>

<script src="https://accounts.google.com/gsi/client" async defer></script>
<script>
function handleGoogleCredential(response) {
    const error = document.getElementById('google-error');
    error.classList.add('hidden');
    fetch('/google_auth.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({credential: response.credential})
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') window.location.assign(data.redirect || '/');
        else { error.textContent = data.message || 'Не удалось войти через Google.'; error.classList.remove('hidden'); }
    })
    .catch(() => { error.textContent = 'Не удалось связаться с сервером. Попробуйте ещё раз.'; error.classList.remove('hidden'); });
}

window.onload = () => {
    if (window.google?.accounts?.id) {
        google.accounts.id.initialize({client_id: '<?= htmlspecialchars(GOOGLE_CLIENT_ID) ?>', callback: handleGoogleCredential, auto_select: false});
        google.accounts.id.renderButton(document.getElementById('google-signin'), {theme: 'outline', size: 'large', shape: 'rectangular', text: 'signin_with', locale: 'ru', width: 360});
    }
};
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
