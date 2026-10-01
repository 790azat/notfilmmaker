#!/usr/bin/env bash
# Готовит SSH-доступ к SmartApe на раннере GitHub Actions: по ключу (SMARTAPE_SSH_KEY)
# или по паролю (SMARTAPE_SSH_PASSWORD). Создаёт команду remote: remote 'команда на сервере'.
set -euo pipefail
: "${SMARTAPE_HOST:?Задайте переменную SMARTAPE_HOST}" "${SMARTAPE_USER:?Задайте переменную SMARTAPE_USER}"
PORT="${SMARTAPE_PORT:-22}"
# Адрес могли вставить как ssh://user@host:port или с пробелами: оставляем только имя хоста.
HOST="$(printf '%s' "$SMARTAPE_HOST" | tr -d '[:space:]')"
HOST="${HOST#*://}"; HOST="${HOST##*@}"; HOST="${HOST%%/*}"
if [[ "$HOST" == *:* ]]; then PORT="${HOST##*:}"; HOST="${HOST%%:*}"; fi
SMARTAPE_HOST="$HOST"
SMARTAPE_USER="$(printf '%s' "$SMARTAPE_USER" | tr -d '[:space:]')"
mkdir -p ~/.ssh ~/bin
chmod 700 ~/.ssh
# Диагностика: имя хоста резолвится и порт SSH открыт?
if ! getent hosts "$SMARTAPE_HOST" >/dev/null; then
  echo "Длина адреса: ${#HOST}, в нём точек: $(tr -cd . <<<"$HOST" | wc -c)"
  echo "::error::SMARTAPE_HOST не резолвится в IP. Укажите адрес SSH-сервера из письма SmartApe (не домен сайта, пока DNS не обновился)."
  exit 1
fi
if ! timeout 15 bash -c "</dev/tcp/$SMARTAPE_HOST/$PORT" 2>/dev/null; then
  echo "Отпечаток адреса: $(printf '%s' "$HOST" | sha256sum | cut -c1-12)"
  for p in 21 22 80 443 1500 2222 22022; do
    timeout 5 bash -c "</dev/tcp/$SMARTAPE_HOST/$p" 2>/dev/null && echo "порт $p открыт" || echo "порт $p закрыт"
  done
  echo "::error::Порт $PORT на SMARTAPE_HOST не отвечает. Проверьте, что SSH включён, и порт (переменная SMARTAPE_PORT)."
  exit 1
fi
ssh-keyscan -T 15 -p "$PORT" -H "$SMARTAPE_HOST" >> ~/.ssh/known_hosts 2>/dev/null || true

SSH=(ssh -p "$PORT" -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=20)
if [ -n "${SMARTAPE_SSH_KEY:-}" ]; then
  printf '%s\n' "$SMARTAPE_SSH_KEY" > ~/.ssh/smartape
  chmod 600 ~/.ssh/smartape
  SSH+=(-i ~/.ssh/smartape)
elif [ -n "${SMARTAPE_SSH_PASSWORD:-}" ]; then
  sudo apt-get install -y -qq sshpass >/dev/null
  printf '%s' "$SMARTAPE_SSH_PASSWORD" > ~/.ssh/smartape-pass
  chmod 600 ~/.ssh/smartape-pass
  SSH=(sshpass -f "$HOME/.ssh/smartape-pass" ssh -p "$PORT" -o PubkeyAuthentication=no -o StrictHostKeyChecking=accept-new -o ConnectTimeout=20)
else
  echo "Нужен секрет SMARTAPE_SSH_KEY или SMARTAPE_SSH_PASSWORD" >&2
  exit 1
fi

{
  echo '#!/usr/bin/env bash'
  printf 'exec'
  printf ' %q' "${SSH[@]}" "$SMARTAPE_USER@$SMARTAPE_HOST"
  echo ' "$@"'
} > ~/bin/remote
chmod +x ~/bin/remote
echo "$HOME/bin" >> "$GITHUB_PATH"

if ! ~/bin/remote 'echo connected' ; then
  echo "::error::SSH-сервер отвечает, но вход не удался: проверьте SMARTAPE_USER и пароль или ключ."
  exit 1
fi
