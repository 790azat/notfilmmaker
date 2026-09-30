<?php

namespace Tests\Feature;

use App\Livewire\Admin\Settings;
use App\Models\Setting;
use App\Models\User;
use App\Models\Work;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HeroReelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_picks_home_screen_videos_in_order(): void
    {
        $works = collect(range(1, 6))->map(fn ($i) => Work::create([
            'title' => ['hy' => "Reel $i", 'ru' => "Reel $i", 'en' => "Reel $i"],
            'category' => 'reels',
            'video_url' => "https://cdn.test/reel-$i.mp4",
            'is_published' => true,
            'published_at' => now()->subDays($i),
        ]));

        Livewire::actingAs(User::factory()->create(['is_admin' => true]))
            ->test(Settings::class)
            ->assertSee('Reel 6')
            ->set('heroReels', [(string) $works[5]->id, '', (string) $works[3]->id, (string) $works[5]->id, ''])
            ->call('save');

        $this->assertSame([$works[5]->id, $works[3]->id], Setting::get('hero_reels'));

        // Выбранные идут первыми по порядку, остальные слоты добираются автоматически.
        $this->get('/')->assertOk()->assertSeeInOrder(['reel-6.mp4', 'reel-4.mp4', 'reel-1.mp4', 'reel-2.mp4', 'reel-3.mp4']);
    }
}
