<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\Message;
use App\Models\TelegramLink;
use App\Support\Telegram;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/** Webhook Telegram-бота: подключение владельца, его ответы клиентам и сообщения, написанные боту напрямую. */
class TelegramController extends Controller
{
    private const HELLO = [
        'hy' => 'Բարև Ձեզ։ Գրեք ձեր հարցը կամ նախագծի գաղափարը, կպատասխանեմ այստեղ։',
        'ru' => 'Здравствуйте! Напишите ваш вопрос или идею проекта, я отвечу здесь.',
        'en' => 'Hi! Send your question or project idea and I will reply here.',
    ];

    private const THANKS = [
        'hy' => 'Շնորհակալություն, շուտով կպատասխանեմ։',
        'ru' => 'Спасибо! Скоро отвечу.',
        'en' => 'Thanks! I will reply soon.',
    ];

    public function __invoke(Request $request): Response
    {
        abort_unless(hash_equals(Telegram::webhookSecret(), (string) $request->header('X-Telegram-Bot-Api-Secret-Token')), 403);
        try {
            $this->handle($request->json()->all());
        } catch (Throwable $e) {
            report($e);
        }

        return response('ok');
    }

    private function handle(array $u): void
    {
        $m = $u['message'] ?? null;
        if (! $m || ($m['chat']['type'] ?? '') !== 'private') {
            return;
        }
        $chat = (int) $m['chat']['id'];
        $text = trim((string) ($m['text'] ?? ''));
        $from = $m['from'] ?? [];
        $who = trim(($from['first_name'] ?? '').' '.($from['last_name'] ?? '')).(isset($from['username']) ? ' @'.$from['username'] : '');
        $lang = in_array($from['language_code'] ?? '', ['hy', 'ru', 'en'], true) ? $from['language_code'] : 'ru';

        // Владелец подключается по секретной ссылке из админки
        if ($text === '/start '.Telegram::adminCode()) {
            Telegram::addAdmin($chat);
            Telegram::say($chat, "Готово: сообщения из чата на сайте, заявки с формы и сообщения этому боту будут приходить сюда.\n\nЧтобы ответить, сделайте reply на сообщение клиента.");
            $this->flush();

            return;
        }

        if (Telegram::isAdmin($chat)) {
            $this->adminReply($chat, $m, $text);

            return;
        }

        // Клиент пишет боту напрямую
        if (str_starts_with($text, '/start')) {
            Telegram::say($chat, self::HELLO[$lang]);
            Telegram::notify("👋 Новый человек в боте: {$who}", "tg:{$chat}");

            return;
        }
        Telegram::notify("💬 Сообщение боту от {$who}:", "tg:{$chat}", [$chat, (int) $m['message_id']]);
        if (! cache()->has("tg-thanks:{$chat}")) {
            cache()->put("tg-thanks:{$chat}", 1, now()->addHours(6));
            Telegram::say($chat, self::THANKS[$lang]);
        }
    }

    private function adminReply(int $chat, array $m, string $text): void
    {
        $replyTo = $m['reply_to_message']['message_id'] ?? null;
        if (! $replyTo) {
            Telegram::say($chat, 'Чтобы ответить клиенту, сделайте reply на его сообщение.');

            return;
        }
        $target = TelegramLink::where('tg_chat_id', $chat)->where('tg_message_id', $replyTo)->value('target');
        [$kind, $id] = array_pad(explode(':', (string) $target, 2), 2, null);

        if ($kind === 'web' && ($site = Chat::find($id))) {
            if ($text === '') {
                Telegram::say($chat, 'В чат на сайте уходит только текст.');

                return;
            }
            $site->messages()->create(['f' => 'a', 'body' => mb_substr($text, 0, 4000), 'delivered' => true]);
            $site->touch();
            Telegram::say($chat, '✓ Отправлено в чат на сайте '.$site->tag(), ['reply_to_message_id' => $m['message_id']]);

            return;
        }
        if ($kind === 'tg') {
            Telegram::api('copyMessage', ['chat_id' => (int) $id, 'from_chat_id' => $chat, 'message_id' => $m['message_id']]);
            Telegram::say($chat, '✓ Отправлено', ['reply_to_message_id' => $m['message_id']]);

            return;
        }
        if ($kind === 'mail' && ($message = Message::find($id))) {
            Telegram::say($chat, "Эта заявка пришла с формы на сайте, ответить можно по почте: {$message->email}".($message->phone ? " или по телефону {$message->phone}" : ''));

            return;
        }
        Telegram::say($chat, 'Не нашёл, кому отправить: сделайте reply на сообщение с текстом клиента.');
    }

    /** Сообщения из чата, пришедшие до подключения владельца, отправляем ему сразу. */
    private function flush(): void
    {
        $pending = ChatMessage::with('chat')->where('f', 'v')->where('delivered', false)->orderBy('id')->limit(50)->get();
        foreach ($pending as $message) {
            $text = '⏳ Пришло, пока бот не был подключён ('.$message->created_at->format('d.m H:i')." UTC)\n".ChatController::format($message->chat, $message, false);
            $message->update(['delivered' => Telegram::notify($text, 'web:'.$message->chat_id)]);
        }
    }
}
