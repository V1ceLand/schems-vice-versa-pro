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

<section class="relative mx-auto flex flex-wrap items-center justify-center gap-14" style="min-height:calc(100vh - 15rem);padding:clamp(30px,6vw,60px) 0">
    <div class="site-grid pointer-events-none absolute inset-x-[-10rem] inset-y-0 -z-10 opacity-40"></div>

    <div class="flex-none" style="max-width:420px;min-width:280px">
        <h1 class="mb-3" style="font-size:clamp(30px,4vw,44px);line-height:1.03">Вход в SchemHub</h1>
        <p class="mb-6" style="font-size:15.5px;line-height:1.6;opacity:.75;max-width:36ch">Войдите, чтобы публиковать схемы, оценивать работы и собирать свою библиотеку. Пароль придумывать не надо.</p>

        <div style="background:var(--color-surface);border-radius:32px;padding:24px;max-width:360px">
            <div id="google-signin" class="flex min-h-[44px] justify-center"></div>
            <p id="google-error" class="mt-3 hidden text-center text-sm" style="color:#ff9d9d"></p>

            <div class="my-5 flex items-center gap-3 text-xs" style="opacity:.5"><span class="h-px flex-1" style="background:var(--color-divider)"></span><span>или</span><span class="h-px flex-1" style="background:var(--color-divider)"></span></div>

            <div id="telegram-login-widget" class="flex min-h-[46px] justify-center">
                <script async src="https://telegram.org/js/telegram-widget.js?22"
                        data-telegram-login="<?= htmlspecialchars(TG_BOT_USERNAME) ?>"
                        data-size="large" data-radius="10"
                        data-auth-url="/auth.php" data-request-access="write"></script>
            </div>
        </div>

        <p class="mt-5 text-xs leading-relaxed" style="opacity:.6;max-width:40ch"><i class="fa-solid fa-shield-halved" style="color:var(--color-accent)"></i> Мы не получаем пароль от Google или Telegram — сервис передаёт только подтверждённые данные профиля.</p>
    </div>

    <div class="flex-none relative" style="width:220px">
        <div style="position:absolute;top:-8%;left:-10%;width:60%;aspect-ratio:1;border-radius:50%;background:var(--color-accent-200)"></div>
        <div class="washed elev-lg relative" style="border-radius:44px;overflow:hidden;aspect-ratio:3/4;display:flex;align-items:center;justify-content:center">
            <i class="fa-solid fa-cubes" style="font-size:48px;opacity:.2"></i>
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
