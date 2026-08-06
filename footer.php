</main> <!-- Закрываем тег main из header.php -->

    <footer class="mt-auto" style="border-top: 1px solid var(--color-divider);">
        <div class="max-w-7xl mx-auto px-5 md:px-6 py-8 flex flex-col md:flex-row items-center justify-between text-sm" style="color: color-mix(in srgb, var(--color-text) 65%, transparent);">
            <div class="flex items-center gap-2">
                <span style="color: var(--color-accent)"><i class="fa-solid fa-cubes"></i></span>&copy; <?= date('Y') ?> SchemHub. Постройки для сообщества Minecraft.
            </div>
            <div class="flex gap-4 mt-4 md:mt-0 font-semibold">
                <a href="#" class="transition-colors" onmouseover="this.style.color='var(--color-accent-700)'" onmouseout="this.style.color=''">Правила</a>
                <a href="#" class="transition-colors" onmouseover="this.style.color='var(--color-accent-700)'" onmouseout="this.style.color=''">Telegram Бот</a>
            </div>
        </div>
    </footer>
</body>
</html>
