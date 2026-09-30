<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\TelegramLink;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Telegram-бот владельца сайта: сообщения из чата на сайте, заявки с формы и
 * сообщения самому боту приходят владельцу, а его reply уходит обратно адресату.
 * Токен бота задаётся в админке (Настройки → Telegram) или переменной TELEGRAM_BOT_TOKEN.
 * Получатели: те, кто открыл секретную ссылку t.me/<бот>?start=<код> из админки.
 */
class Telegram
{
    public static function token(): ?string
    {
        return Setting::get('telegram_token') ?: (config('services.telegram.token') ?: null);
    }

    public static function enabled(): bool
    {
        return (bool) static::token();
    }

    /** Список chat id владельцев, которым бот пересылает сообщения. */
    public static function admins(): array
    {
        return array_values(array_map('intval', (array) Setting::get('telegram_admins', [])));
    }

    public static function isAdmin(int $chatId): bool
    {
        return in_array($chatId, static::admins(), true);
    }

    public static function addAdmin(int $chatId): void
    {
        Setting::put('telegram_admins', array_values(array_unique([...static::admins(), $chatId])));
    }

    /** Секрет заголовка webhook: Telegram присылает его с каждым обновлением. */
    public static function webhookSecret(): string
    {
        return substr(hash_hmac('sha256', 'telegram-webhook', (string) config('app.key')), 0, 40);
    }

    /** Код для ссылки, по которой владелец подключает свой Telegram. */
    public static function adminCode(): string
    {
        return 'owner'.substr(hash_hmac('sha256', 'telegram-admin', (string) config('app.key')), 0, 24);
    }

    public static function adminLink(): ?string
    {
        $bot = Setting::get('telegram_bot');

        return $bot ? "https://t.me/{$bot}?start=".static::adminCode() : null;
    }

    public static function api(string $method, array $params = [], ?string $token = null): mixed
    {
        $token ??= static::token();
        if (! $token) {
            throw new RuntimeException('Telegram bot is not connected.');
        }
        $response = Http::timeout(15)->connectTimeout(5)->asJson()
            ->post("https://api.telegram.org/bot{$token}/{$method}", $params);
        $json = $response->json() ?? [];
        if (! ($json['ok'] ?? false)) {
            throw new RuntimeException($json['description'] ?? "Telegram {$method} failed ({$response->status()})");
        }

        return $json['result'];
    }

    /** Проверяет токен, ставит webhook на этот сайт и возвращает имя бота. */
    public static function connect(string $token, string $webhookUrl): string
    {
        $me = static::api('getMe', [], $token);
        static::api('setWebhook', [
            'url' => $webhookUrl,
            'secret_token' => static::webhookSecret(),
            'allowed_updates' => ['message'],
            'drop_pending_updates' => true,
        ], $token);
        Setting::put('telegram_token', $token);
        Setting::put('telegram_bot', $me['username']);

        return $me['username'];
    }

    public static function disconnect(): void
    {
        rescue(fn () => static::api('deleteWebhook'), report: false);
        foreach (['telegram_token', 'telegram_bot', 'telegram_admins'] as $key) {
            Setting::put($key, null);
        }
    }

    public static function say(int $chatId, string $text, array $extra = []): ?array
    {
        return rescue(fn () => static::api('sendMessage', ['chat_id' => $chatId, 'text' => $text, 'disable_web_page_preview' => true] + $extra));
    }

    /**
     * Сообщение всем владельцам. $target — куда уйдёт их reply: web:<chat id>, tg:<user id> или mail:<message id>.
     * Возвращает true, если хотя бы один владелец его получил.
     */
    public static function notify(string $text, ?string $target = null, ?array $forward = null): bool
    {
        if (! static::enabled()) {
            return false;
        }
        $delivered = false;
        foreach (static::admins() as $admin) {
            try {
                $ids = [static::api('sendMessage', ['chat_id' => $admin, 'text' => mb_substr($text, 0, 4000), 'disable_web_page_preview' => true])['message_id']];
                if ($forward) {
                    $ids[] = static::api('forwardMessage', ['chat_id' => $admin, 'from_chat_id' => $forward[0], 'message_id' => $forward[1]])['message_id'];
                }
                $delivered = true;
                if ($target) {
                    foreach ($ids as $id) {
                        TelegramLink::create(['tg_chat_id' => $admin, 'tg_message_id' => $id, 'target' => $target]);
                    }
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $delivered;
    }
}
