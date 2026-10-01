#!/usr/bin/env bash
# Готовит SSH-доступ к SmartApe на раннере GitHub Actions: по ключу (SMARTAPE_SSH_KEY)
# или по паролю (SMARTAPE_SSH_PASSWORD). Создаёт команду remote: remote 'команда на сервере'.
set -euo pipefail
: "${SMARTAPE_HOST:?Задайте переменную SMARTAPE_HOST}" "${SMARTAPE_USER:?Задайте переменную SMARTAPE_USER}"
PORT="${SMARTAPE_PORT:-22}"
mkdir -p ~/.ssh ~/bin
chmod 700 ~/.ssh
ssh-keyscan -p "$PORT" -H "$SMARTAPE_HOST" >> ~/.ssh/known_hosts 2>/dev/null

SSH=(ssh -p "$PORT" -o BatchMode=yes)
if [ -n "${SMARTAPE_SSH_KEY:-}" ]; then
  printf '%s\n' "$SMARTAPE_SSH_KEY" > ~/.ssh/smartape
  chmod 600 ~/.ssh/smartape
  SSH+=(-i ~/.ssh/smartape)
elif [ -n "${SMARTAPE_SSH_PASSWORD:-}" ]; then
  sudo apt-get install -y -qq sshpass >/dev/null
  printf '%s' "$SMARTAPE_SSH_PASSWORD" > ~/.ssh/smartape-pass
  chmod 600 ~/.ssh/smartape-pass
  SSH=(sshpass -f "$HOME/.ssh/smartape-pass" ssh -p "$PORT" -o PubkeyAuthentication=no)
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
