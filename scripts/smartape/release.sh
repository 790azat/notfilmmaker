#!/usr/bin/env bash
# Выкладка новой версии на хостинге SmartApe (ISPmanager). Запускается из GitHub Actions по SSH:
#   release.sh <каталог релиза> <домен>
# Структура в домашнем каталоге:
#   notfilmmaker/releases/<commit>   распакованные версии (последние 3)
#   notfilmmaker/shared/.env         настройки (создаются при первой выкладке)
#   notfilmmaker/shared/storage      загруженные фото и видео, логи, кэш
#   notfilmmaker/current             ссылка на рабочую версию
#   www/<домен>                      ссылка на notfilmmaker/current/public (корень сайта)
set -euo pipefail

RELEASE="$1"
DOMAIN="${2:-notfilmmaker.com}"
APP="$HOME/notfilmmaker"
SHARED="$APP/shared"
WEBROOT="$HOME/${SMARTAPE_WEBROOT:-www/$DOMAIN}"

# PHP 8.4 из ISPmanager: у консольного php по умолчанию может быть другая версия.
PHP="${PHP_BIN:-}"
if [ -z "$PHP" ]; then
  for candidate in /opt/php84/bin/php /opt/alt/php84/usr/bin/php php8.4 php; do
    if command -v "$candidate" >/dev/null 2>&1 && "$candidate" -r 'exit(PHP_VERSION_ID >= 80400 ? 0 : 1);'; then
      PHP="$candidate"; break
    fi
  done
fi
if [ -z "$PHP" ]; then
  echo "Не найден PHP 8.4. Выберите PHP 8.4 для сайта в ISPmanager или задайте переменную PHP_BIN." >&2
  exit 1
fi
echo "PHP: $PHP ($("$PHP" -r 'echo PHP_VERSION;'))"

mkdir -p "$SHARED/storage/app/public" "$SHARED/storage/app/private" "$SHARED/storage/framework/cache/data" \
  "$SHARED/storage/framework/sessions" "$SHARED/storage/framework/views" "$SHARED/storage/logs"

# Первая выкладка: .env из шаблона, который прислал GitHub Actions, и новый APP_KEY.
# Дальше доступ к базе (DB_*) и токен бота каждый раз обновляются из секретов GitHub, остальное правится на сервере.
FIRST=0
if [ ! -f "$SHARED/.env" ]; then
  mv "$SHARED/.env.new" "$SHARED/.env"
  chmod 600 "$SHARED/.env"
  FIRST=1
elif [ -f "$SHARED/.env.new" ]; then
  for key in DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD TELEGRAM_BOT_TOKEN; do
    line="$(grep "^$key=" "$SHARED/.env.new" || true)"
    [ -n "$line" ] && [ "$line" != "$key=" ] || continue
    grep -v "^$key=" "$SHARED/.env" > "$SHARED/.env.tmp" || true
    printf '%s\n' "$line" >> "$SHARED/.env.tmp"
    mv "$SHARED/.env.tmp" "$SHARED/.env"
  done
  chmod 600 "$SHARED/.env"
fi
rm -f "$SHARED/.env.new"
echo "База: $(grep -E '^DB_(HOST|DATABASE)=' "$SHARED/.env" | tr '\n' ' ')пользователь $(grep '^DB_USERNAME=' "$SHARED/.env" | cut -d= -f2- | tr -d '\n' | wc -c) символов (отпечаток $(grep '^DB_USERNAME=' "$SHARED/.env" | cut -d= -f2- | tr -d '\n' | sha256sum | cut -c1-8)), пароль $(grep '^DB_PASSWORD=' "$SHARED/.env" | sed "s/^DB_PASSWORD='\(.*\)'$/\1/" | tr -d '\n' | wc -c) символов"

rm -rf "$RELEASE/storage"
ln -s "$SHARED/storage" "$RELEASE/storage"
ln -sfn "$SHARED/.env" "$RELEASE/.env"

cd "$RELEASE"
if [ "$FIRST" = 1 ] || ! grep -q '^APP_KEY=base64:' .env; then
  "$PHP" artisan key:generate --force
fi
# Ключ для ежедневного крона (/cron/sync), если его не задали в секретах GitHub.
if ! grep -q '^CRON_SECRET=.\+' .env; then
  sed -i '/^CRON_SECRET=/d' "$SHARED/.env"
  echo "CRON_SECRET=$(head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')" >> "$SHARED/.env"
fi
# Доступ к базе: подбираем имена с приставкой логина хостинга, если без неё не пускает.
if fixed="$("$PHP" "$RELEASE/scripts/smartape/db-check.php" "$SHARED/.env")"; then
  while IFS= read -r line; do
    key="${line%%=*}"
    if ! grep -qxF "$line" "$SHARED/.env"; then
      echo "Исправляю $key в .env"
      grep -v "^$key=" "$SHARED/.env" > "$SHARED/.env.tmp" || true
      printf '%s\n' "$line" >> "$SHARED/.env.tmp"
      mv "$SHARED/.env.tmp" "$SHARED/.env"
      chmod 600 "$SHARED/.env"
    fi
  done <<< "$fixed"
else
  echo "MySQL не пускает ни с одним вариантом имени: проверьте пользователя и пароль базы в ISPmanager." >&2
fi
"$PHP" artisan migrate --force
if [ ! -f "$SHARED/.seeded" ]; then
  "$PHP" artisan db:seed --force
  touch "$SHARED/.seeded"
fi
rm -f public/storage
ln -s "$SHARED/storage/app/public" public/storage
"$PHP" artisan optimize

# Переключаем сайт на новую версию одной операцией.
ln -sfn "$RELEASE" "$APP/current.tmp"
mv -Tf "$APP/current.tmp" "$APP/current"

# Корень сайта в ISPmanager — ссылка на public текущей версии.
if [ -d "$WEBROOT" ] && [ ! -L "$WEBROOT" ]; then
  mv "$WEBROOT" "$WEBROOT.orig-$(date +%s)"
fi
mkdir -p "$(dirname "$WEBROOT")"
ln -sfn "$APP/current/public" "$WEBROOT"

# Оставляем три последние версии для быстрого отката.
cd "$APP/releases" && ls -1t | tail -n +4 | xargs -r rm -rf
echo "Готово: $(readlink "$APP/current")"
