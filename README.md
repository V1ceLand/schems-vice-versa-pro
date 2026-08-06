# Schems Vice Versa Pro

Каталог Minecraft-схематик: загрузка, просмотр, скачивание построек, авторизация через Telegram и Google.

## Стек

- PHP + PDO (MySQL)
- Авторизация: Telegram Login Widget, Google Sign-In

## Структура

- `index.php`, `catalog.php`, `schematic.php` — витрина и карточка схематики
- `upload.php`, `edit.php` — загрузка и редактирование
- `auth.php`, `google_auth.php`, `login.php`, `logout.php` — авторизация
- `profile.php` — личный кабинет
- `header.php`, `footer.php`, `schematic_card.php` — переиспользуемые компоненты
- `config.php` — подключение к БД и константы (читает настройки из окружения)

## Настройка

1. Скопируйте `.env.example` в `.env` и заполните значения (БД, токен Telegram-бота, Google Client ID).
2. Настройте веб-сервер так, чтобы переменные из `.env` попадали в окружение PHP (например, через docker-compose `env_file` или `putenv`/`vlucas/phpdotenv`).
3. Создайте базу данных и накатите схему (в `config.php` описаны используемые таблицы через запросы).
