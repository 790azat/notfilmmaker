# Хостинг SmartApe (notfilmmaker.com)

Сайт выкладывается на виртуальный хостинг SmartApe (панель ISPmanager) через GitHub Actions:
каждый пуш в `main` собирает сайт (composer, npm) и по SSH выкладывает его на сервер
(workflow `.github/workflows/deploy-smartape.yml`, скрипт `scripts/smartape/release.sh`).
На сервере не нужны ни composer, ни Node.js.

## 1. ISPmanager (cp.smartape.ru → панель хостинга)

1. **Сайты → Создать**: домен `notfilmmaker.com`, псевдоним `www.notfilmmaker.com`,
   **PHP 8.4** (режим CGI или FPM), SSL — Let's Encrypt, «Перенаправлять HTTP на HTTPS».
   Корневую папку оставьте по умолчанию (`www/notfilmmaker.com`): при первой выкладке
   она станет ссылкой на `notfilmmaker/current/public`, а исходное содержимое переедет в `www/notfilmmaker.com.orig-*`.
2. **Базы данных → Создать**: MySQL, кодировка utf8mb4. Запомните имя базы, пользователя и пароль.
3. **SSH-доступ** должен быть включён для пользователя хостинга. Для входа по ключу добавьте
   открытый ключ в `~/.ssh/authorized_keys` (или через ISPmanager, если там есть раздел SSH-ключей).
4. **Планировщик (cron)**, раз в сутки — забирает новые видео с YouTube и посты Instagram:
   ```
   curl -fsS -H "Authorization: Bearer <CRON_SECRET>" https://notfilmmaker.com/cron/sync > /dev/null
   ```

## 2. DNS домена notfilmmaker.com

Любой из двух вариантов у регистратора домена:

- **NS-серверы SmartApe** (указаны в панели/письме о заказе хостинга) — тогда записи создаст ISPmanager сам.
- **Или A-записи** на IP сервера хостинга (ISPmanager → Сайты → IP-адрес):

| Тип | Имя | Значение |
| --- | --- | --- |
| A | `@` | IP сервера SmartApe |
| A | `www` | IP сервера SmartApe |

Сертификат Let's Encrypt выпускается, когда домен уже смотрит на сервер.

## 3. GitHub → Settings → Secrets and variables → Actions

**Variables**

| Имя | Пример |
| --- | --- |
| `SMARTAPE_HOST` | адрес SSH-сервера из письма SmartApe |
| `SMARTAPE_USER` | логин пользователя хостинга |
| `SMARTAPE_PORT` | необязательно, по умолчанию 22122 (SSH на shared-33.smartape.net) |
| `SMARTAPE_PHP_BIN` | необязательно: путь к PHP 8.4, если скрипт не нашёл его сам (например `/opt/php84/bin/php`) |

**Secrets**

| Имя | Что это |
| --- | --- |
| `SMARTAPE_SSH_KEY` | закрытый SSH-ключ (или `SMARTAPE_SSH_PASSWORD` — пароль SSH) |
| `SMARTAPE_DB_DATABASE`, `SMARTAPE_DB_USERNAME`, `SMARTAPE_DB_PASSWORD` | база MySQL из шага 1 |
| `CRON_SECRET` | необязательно: без него ключ создаётся на сервере в `shared/.env` |
| `TELEGRAM_BOT_TOKEN` | токен бота для чата (как на Vercel), необязательно |
| `SOURCE_DATABASE_URL` | `DATABASE_URL` из Vercel — только для переноса данных |

Пока нет `SMARTAPE_HOST`, workflow выкладки пропускается.

## 4. Первая выкладка и перенос данных

1. Actions → **Deploy to SmartApe** → Run workflow. Создаётся `notfilmmaker/shared/.env`
   (дальше его правят на сервере), генерируется `APP_KEY`, выполняются миграции.
2. Actions → **Move data to SmartApe** → Run workflow, в поле ввести `ПЕРЕНЕСТИ`.
   Работы, настройки, аккаунт админа и чаты копируются из Neon (`site:export` → `site:pull`),
   затем все фото и видео скачиваются из Vercel Blob в `notfilmmaker/shared/storage/app/public`
   (`site:media`, повторный запуск докачивает пропущенное).
3. Проверить https://notfilmmaker.com, вход в админку, загрузку видео.

## 5. После переезда: старый адрес на Vercel

В Vercel → Settings → Environment Variables (Production) поставить `APP_URL=https://notfilmmaker.com`
и `REDIRECT_TO_APP_URL=true`, затем Redeploy: notfilmmaker.vercel.app будет отдавать 301 на новый домен
(кроме `/cron/*` и вебхука Telegram), а canonical/hreflang/sitemap указывают на notfilmmaker.com.
Вебхук Telegram сам переключится на домен из `APP_URL` при первом открытии нового сайта.

## Структура на сервере

```
~/notfilmmaker/releases/<время>-<commit>   последние 3 версии (откат: ln -sfn <версия> ~/notfilmmaker/current)
~/notfilmmaker/shared/.env                 настройки
~/notfilmmaker/shared/storage              загруженные файлы, логи, кэш
~/notfilmmaker/current                     рабочая версия
~/www/notfilmmaker.com -> ~/notfilmmaker/current/public
```

Лимиты загрузки (512 МБ) заданы в `public/.user.ini`.
