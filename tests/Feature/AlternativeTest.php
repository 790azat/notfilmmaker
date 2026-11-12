<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Work;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlternativeTest extends TestCase
{
    use RefreshDatabase;

    public function test_desk_page_renders_with_records_in_every_language(): void
    {
        Work::create(['title' => ['en' => 'Night clip', 'ru' => 'Ночной клип'], 'category' => 'music_video', 'video_url' => 'works/clip.mp4', 'cover' => 'covers/a.jpg']);
        Work::create(['title' => ['en' => 'Hidden'], 'category' => 'music_video', 'video_url' => 'works/b.mp4', 'is_published' => false]);

        foreach (array_keys(config('app.locales')) as $locale) {
            $this->withSession(['locale' => $locale]);
            $this->get('/alternative')->assertOk()->assertSee('desk-canvas', false)->assertDontSee('Hidden');
        }

        $this->withSession(['locale' => 'en']);
        $data = $this->extractData($this->get('/alternative')->getContent());
        $this->assertCount(1, $data['albums']);
        $this->assertSame('Night clip', $data['albums'][0]['title']);
        $this->assertStringEndsWith('works/clip.mp4', $data['albums'][0]['video']);
        $this->assertSame(route('alternative.book'), $data['site']['urls']['book']);
    }

    public function test_calendar_booking_lands_in_messages(): void
    {
        $date = now()->addDays(3)->format('Y-m-d');

        $this->postJson('/alternative/book', ['date' => $date, 'kind' => 'shoot', 'name' => 'Ani', 'contact' => 'ani@example.com', 'note' => 'Music video'])
            ->assertOk()->assertJson(['ok' => true]);

        $m = Message::sole();
        $this->assertSame('ani@example.com', $m->email);
        $this->assertStringContainsString($date, $m->project_type);
        $this->assertStringContainsString('Music video', $m->body);

        $this->postJson('/alternative/book', ['date' => $date, 'kind' => 'call', 'name' => 'Aram', 'contact' => '+374 99 123456'])->assertOk();
        $this->assertSame('+374 99 123456', Message::latest('id')->first()->phone);
    }

    public function test_booking_rejects_past_dates_and_bots(): void
    {
        $this->postJson('/alternative/book', ['date' => now()->subDay()->format('Y-m-d'), 'kind' => 'shoot', 'name' => 'A', 'contact' => 'a@b.c'])
            ->assertStatus(422);
        $this->postJson('/alternative/book', ['date' => now()->format('Y-m-d'), 'kind' => 'party', 'name' => 'A', 'contact' => 'a@b.c'])
            ->assertStatus(422);
        $this->postJson('/alternative/book', ['date' => now()->format('Y-m-d'), 'kind' => 'visit', 'name' => 'Bot', 'contact' => 'x@y.z', 'website' => 'spam'])
            ->assertOk();

        $this->assertSame(0, Message::count());
    }

    private function extractData(string $html): array
    {
        preg_match('~<script type="application/json" id="desk-data">(.*?)</script>~s', $html, $m);

        return json_decode($m[1], true);
    }
}
