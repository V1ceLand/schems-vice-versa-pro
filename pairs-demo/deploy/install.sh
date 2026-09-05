#!/usr/bin/env bash
#
# Ставит витрину «Пар» на сервер с nginx (проверено на Ubuntu 24.04).
# Запускать на самом сервере, из каталога pairs-demo:
#
#   sudo ./deploy/install.sh
#
# По умолчанию сайт становится основным на 80 порту и заменяет стандартную
# страницу nginx. Чтобы этого не делать, добавьте --keep-default: тогда
# нужно будет самому решить, какой server{} отвечает на IP.

set -euo pipefail

ROOT_DIR=/var/www/pairs-demo
SITE_NAME=pairs-demo
SERVER_NAME=_
KEEP_DEFAULT=0

usage() {
    cat <<'USAGE'
Использование: install.sh [опции]

  --root КАТАЛОГ     куда положить файлы (по умолчанию /var/www/pairs-demo)
  --server-name ИМЯ  значение server_name (по умолчанию _ — отвечать на любой)
  --keep-default     не выключать стандартный сайт nginx
  -h, --help         эта справка
USAGE
}

while [ $# -gt 0 ]; do
    case "$1" in
        --root) ROOT_DIR="$2"; shift 2 ;;
        --server-name) SERVER_NAME="$2"; shift 2 ;;
        --keep-default) KEEP_DEFAULT=1; shift ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Неизвестная опция: $1" >&2; usage >&2; exit 2 ;;
    esac
done

if [ "$(id -u)" -ne 0 ]; then
    echo "Нужны права root: запустите через sudo." >&2
    exit 1
fi

SRC_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)

if [ ! -f "$SRC_DIR/index.html" ] || [ ! -d "$SRC_DIR/assets" ]; then
    echo "В $SRC_DIR нет index.html и assets/ — запустите скрипт из каталога pairs-demo." >&2
    exit 1
fi

command -v nginx >/dev/null || { echo "nginx не установлен: apt install nginx" >&2; exit 1; }

echo "Источник:   $SRC_DIR"
echo "Назначение: $ROOT_DIR"

# Файлы сайта. README, скрипты и конфиги на сервер не едут.
install -d -m 755 "$ROOT_DIR"
install -m 644 "$SRC_DIR/index.html" "$ROOT_DIR/index.html"
install -d -m 755 "$ROOT_DIR/assets"
for file in "$SRC_DIR"/assets/*; do
    install -m 644 "$file" "$ROOT_DIR/assets/$(basename "$file")"
done

# Файлы, которые могли остаться от прошлой версии и больше не нужны.
for stale in "$ROOT_DIR"/assets/*; do
    name=$(basename "$stale")
    [ -e "$SRC_DIR/assets/$name" ] || { echo "Удаляю устаревший assets/$name"; rm -f "$stale"; }
done

if id -u www-data >/dev/null 2>&1; then
    chown -R www-data:www-data "$ROOT_DIR"
fi

# Конфигурация nginx.
CONF_SRC="$SRC_DIR/deploy/nginx-pairs-demo.conf"
if [ -d /etc/nginx/sites-available ]; then
    CONF_DST="/etc/nginx/sites-available/$SITE_NAME"
    LINK_DST="/etc/nginx/sites-enabled/$SITE_NAME"
else
    CONF_DST="/etc/nginx/conf.d/$SITE_NAME.conf"
    LINK_DST=""
fi

[ -e "$CONF_DST" ] && cp -a "$CONF_DST" "$CONF_DST.bak-$(date +%Y%m%d-%H%M%S)"

sed -e "s#root /var/www/pairs-demo;#root $ROOT_DIR;#" \
    -e "s#server_name _;#server_name $SERVER_NAME;#" \
    "$CONF_SRC" > "$CONF_DST"
chmod 644 "$CONF_DST"
echo "Конфигурация: $CONF_DST"

# На хостах без IPv6 строка listen [::]:80 роняет весь nginx с
# «Address family not supported by protocol» — там её просто нет смысла держать.
if [ ! -f /proc/net/if_inet6 ]; then
    sed -i 's|^\( *\)listen \[::\]:80|\1# listen [::]:80|' "$CONF_DST"
    echo "IPv6 на хосте нет — слушаем только IPv4."
fi

if [ -n "$LINK_DST" ]; then
    ln -sfn "$CONF_DST" "$LINK_DST"
fi

# Стандартная страница nginx и наш сайт не могут оба быть default_server:
# nginx откажется стартовать с «a duplicate default server». Поэтому либо
# выключаем стандартный сайт, либо снимаем default_server с нашего.
DEFAULT_LINK=/etc/nginx/sites-enabled/default
DEFAULT_REMOVED=0

rollback() {
    echo "Проверка конфигурации не прошла — откатываю." >&2
    [ "$DEFAULT_REMOVED" -eq 1 ] && ln -sfn /etc/nginx/sites-available/default "$DEFAULT_LINK"
    [ -n "$LINK_DST" ] && rm -f "$LINK_DST"
    rm -f "$CONF_DST"
    echo "Сайт не установлен, nginx работает на прежней конфигурации." >&2
}

if [ "$KEEP_DEFAULT" -eq 1 ]; then
    sed -i 's/ default_server;/;/' "$CONF_DST"
    echo "Стандартный сайт оставлен; наш server{} отвечает по server_name = $SERVER_NAME."
    if [ "$SERVER_NAME" = "_" ]; then
        echo "  Внимание: с --keep-default укажите --server-name, иначе на IP будет отвечать стандартная страница."
    fi
elif [ -L "$DEFAULT_LINK" ]; then
    rm -f "$DEFAULT_LINK"
    DEFAULT_REMOVED=1
    echo "Стандартный сайт nginx выключен."
    echo "  Вернуть: ln -s /etc/nginx/sites-available/default $DEFAULT_LINK && systemctl reload nginx"
fi

if ! nginx -t; then
    rollback
    exit 1
fi

# Наличие /run/systemd/system — единственная надёжная проверка, что systemd
# действительно управляет машиной: сам systemctl есть и там, где его нет кому слушать.
if [ -d /run/systemd/system ] && command -v systemctl >/dev/null; then
    if systemctl is-active --quiet nginx; then systemctl reload nginx; else systemctl start nginx; fi
elif pgrep -x nginx >/dev/null; then
    nginx -s reload
else
    echo "nginx не запущен — стартую."
    nginx
fi

echo
echo "Готово. Проверьте:"
if [ "$SERVER_NAME" = "_" ]; then
    echo "  curl -sI http://127.0.0.1/ | head -1"
    echo "  и откройте IP сервера в браузере"
else
    echo "  curl -sI -H 'Host: $SERVER_NAME' http://127.0.0.1/ | head -1"
    echo "  и откройте http://$SERVER_NAME/"
fi
