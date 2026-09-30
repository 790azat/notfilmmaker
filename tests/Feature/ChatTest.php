<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Setting;
use App\Support\Telegram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private int $tgId = 100;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['api.telegram.org/*' => fn () => Http::response(['ok' => true, 'result' => ['message_id' => ++$this->tgId, 'username' => 'nf_bot']])]);
        Setting::put('telegram_token', '123:'.str_repeat('a', 35));
        Setting::put('telegram_bot', 'nf_bot');
    }

    private function webhook(array $message)
    {
        return $this->withHeader('X-Telegram-Bot-Api-Secret-Token', Telegram::webhookSecret())
            ->postJson('/telegram/webhook', ['message' => $message + ['chat' => ['id' => $message['from']['id'], 'type' => 'private']]]);
    }

    public function test_widget_shows_only_when_owner_connected(): void
    {
        $this->get('/')->assertOk()->assertDontSee('site-chat', false);
        $this->webhook(['message_id' => 1, 'from' => ['id' => 555, 'first_name' => 'Hrach'], 'text' => '/start '.Telegram::adminCode()])->assertOk();
        $this->assertSame([555], Telegram::admins());
        $this->get('/')->assertOk()->assertSee('site-chat', false);
    }

    public function test_visitor_message_reaches_owner_and_reply_comes_back(): void
    {
        $sid = str_repeat('ab', 12);

        // до подключения владельца сообщение копится
        $this->postJson('/chat/send', ['sid' => $sid, 'text' => 'Сколько стоит клип?', 'page' => '/'])->assertOk()->assertJson(['n' => 1]);
        $this->assertFalse(Chat::first()->messages()->first()->delivered);

        // владелец подключается, и ему приходит накопленное
        $this->webhook(['message_id' => 1, 'from' => ['id' => 555], 'text' => '/start '.Telegram::adminCode()]);
        $this->assertTrue(Chat::first()->messages()->first()->delivered);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'sendMessage') && $r['chat_id'] === 555 && str_contains($r['text'], 'Сколько стоит клип?'));

        // reply владельца на это уведомление уходит в чат на сайте
        $link = \App\Models\TelegramLink::where('target', 'web:'.Chat::first()->id)->firstOrFail();
        $this->webhook(['message_id' => 2, 'from' => ['id' => 555], 'text' => 'От 300 000 драм', 'reply_to_message' => ['message_id' => $link->tg_message_id]])->assertOk();

        $this->getJson('/chat/poll?sid='.$sid.'&after=1')->assertOk()->assertJson(['n' => 2, 'msgs' => [['f' => 'a', 't' => 'От 300 000 драм']]]);
    }

    public function test_flood_is_limited_and_webhook_needs_secret(): void
    {
        $sid = str_repeat('cd', 12);
        $this->postJson('/chat/send', ['sid' => $sid, 'text' => 'one'])->assertOk();
        $this->postJson('/chat/send', ['sid' => $sid, 'text' => 'two'])->assertStatus(429);
        $this->postJson('/chat/send', ['sid' => 'bad', 'text' => 'x'])->assertStatus(422);

        $this->postJson('/telegram/webhook', ['message' => []])->assertForbidden();
    }

    public function test_direct_bot_messages_are_forwarded_and_answered(): void
    {
        Setting::put('telegram_admins', [555]);
        $this->webhook(['message_id' => 7, 'from' => ['id' => 42, 'first_name' => 'Ani', 'language_code' => 'hy'], 'text' => 'Բարև']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'forwardMessage') && $r['from_chat_id'] === 42);

        $link = \App\Models\TelegramLink::where('target', 'tg:42')->firstOrFail();
        $this->webhook(['message_id' => 8, 'from' => ['id' => 555], 'text' => 'Ողջույն', 'reply_to_message' => ['message_id' => $link->tg_message_id]]);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'copyMessage') && $r['chat_id'] === 42);
    }
}
