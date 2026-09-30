<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\ChatMessage;
use App\Support\Telegram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Живой чат на сайте. Сообщение посетителя сохраняется и уходит владельцу в Telegram,
 * его reply в Telegram появляется в окне чата (окно опрашивает /chat/poll).
 */
class ChatController extends Controller
{
    private const MAX_PER_CHAT = 40;

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sid' => ['required', 'string', 'regex:/^[a-z0-9]{16,40}$/'],
            'text' => ['required', 'string', 'max:1500'],
            'page' => ['nullable', 'string', 'max:200'],
        ]);
        $text = trim($data['text']);
        abort_if($text === '', 422);

        $key = 'chat:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 60)) {
            return response()->json(['error' => 'slow'], 429);
        }

        $chat = Chat::firstOrCreate(['sid' => $data['sid']], [
            'page' => $data['page'] ?? null,
            'locale' => app()->getLocale(),
            'ip' => $request->ip(),
        ]);
        $mine = $chat->messages()->where('f', 'v');
        $last = (clone $mine)->latest('id')->first();
        if ($mine->count() >= self::MAX_PER_CHAT || ($last && $last->created_at->gt(now()->subSeconds(2)))) {
            return response()->json(['error' => 'slow'], 429);
        }
        RateLimiter::hit($key, 3600);

        $first = ! $last;
        $message = $chat->messages()->create(['f' => 'v', 'body' => $text]);
        $message->update(['delivered' => Telegram::notify(static::format($chat, $message, $first), 'web:'.$chat->id)]);

        return response()->json(['ok' => true, 'n' => $chat->messages()->count()]);
    }

    public function poll(Request $request): JsonResponse
    {
        $sid = (string) $request->query('sid');
        $after = max(0, (int) $request->query('after'));
        $chat = preg_match('/^[a-z0-9]{16,40}$/', $sid) ? Chat::where('sid', $sid)->first() : null;
        if (! $chat) {
            return response()->json(['msgs' => [], 'n' => 0]);
        }
        $all = $chat->messages()->get(['f', 'body']);

        return response()->json([
            'msgs' => $all->slice($after)->map(fn (ChatMessage $m) => ['f' => $m->f, 't' => $m->body])->values(),
            'n' => $all->count(),
        ]);
    }

    public static function format(Chat $chat, ChatMessage $message, bool $first): string
    {
        $head = $first
            ? '🌐 Чат на сайте, новый посетитель'.($chat->page ? " ({$chat->page})" : '')
            : '🌐 Чат на сайте';

        return "{$head} {$chat->tag()}:\n\n{$message->body}\n\nОтветьте (reply) на это сообщение, и ответ появится у посетителя в чате на сайте.";
    }
}
