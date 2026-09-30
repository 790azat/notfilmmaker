<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Models\Work;
use App\Support\SiteTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteTransferTest extends TestCase
{
    use RefreshDatabase;

    private const BLOB = 'https://abc123.public.blob.vercel-storage.com';

    public function test_export_and_import_replace_all_data(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'email' => 'owner@test']);
        $work = Work::create([
            'title' => ['hy' => 'Ֆիլմ', 'ru' => 'Фильм', 'en' => 'Film'],
            'category' => 'reels',
            'video_url' => self::BLOB.'/videos/a.mp4',
            'is_published' => true,
            'published_at' => now(),
        ]);
        $work->media()->create(['type' => 'image', 'path' => self::BLOB.'/gallery/b.jpg', 'sort_order' => 0]);
        Setting::put('portrait', self::BLOB.'/site/me.jpg');

        $export = json_decode(json_encode(SiteTransfer::export()), true);

        Work::query()->delete();
        User::query()->delete();
        User::factory()->create(['email' => 'stranger@test']);

        $counts = SiteTransfer::import($export);

        $this->assertSame(1, $counts['works']);
        $this->assertSame(['owner@test'], User::pluck('email')->all());
        $this->assertTrue(User::first()->is_admin);
        $this->assertSame($admin->password, User::first()->password);
        $restored = Work::with('media')->find($work->id);
        $this->assertSame('Фильм', $restored->getTranslation('title', 'ru'));
        $this->assertCount(1, $restored->media);

        // Новые записи после импорта получают свободные id.
        $this->assertGreaterThan($work->id, Work::create(['title' => ['en' => 'New'], 'category' => 'reels'])->id);
    }

    public function test_media_is_downloaded_and_links_become_local(): void
    {
        Storage::fake('public');
        Http::fake([
            'abc123.public.blob.vercel-storage.com/videos/a.mp4' => Http::response('video-bytes'),
            'abc123.public.blob.vercel-storage.com/site/me.jpg' => Http::response('jpg-bytes'),
            '*' => Http::response('', 404),
        ]);

        $work = Work::create(['title' => ['en' => 'Reel'], 'category' => 'reels', 'video_url' => self::BLOB.'/videos/a.mp4']);
        $missing = $work->media()->create(['type' => 'image', 'path' => self::BLOB.'/gallery/gone.jpg', 'sort_order' => 0]);
        Setting::put('portrait', self::BLOB.'/site/me.jpg');
        Setting::put('about_text', ['en' => 'Plain text']);

        $stats = SiteTransfer::localizeMedia();

        $this->assertSame(['files' => 2, 'failed' => 1, 'rows' => 2], $stats);
        Storage::disk('public')->assertExists('videos/a.mp4');
        $this->assertSame('video-bytes', Storage::disk('public')->get('videos/a.mp4'));
        $this->assertSame('videos/a.mp4', $work->fresh()->video_url);
        $this->assertSame(self::BLOB.'/gallery/gone.jpg', $missing->fresh()->path);
        $this->assertSame('site/me.jpg', Setting::query()->find('portrait')->value);
        $this->assertSame(['en' => 'Plain text'], Setting::query()->find('about_text')->value);

        // Повторный запуск не качает уже скачанное.
        $this->assertSame(0, SiteTransfer::localizeMedia()['files']);
    }

    public function test_old_domain_redirects_to_app_url_when_enabled(): void
    {
        config(['app.url' => 'https://notfilmmaker.com', 'app.redirect_to_app_url' => true]);

        $this->get('http://notfilmmaker.vercel.app/works?lang=ru')
            ->assertStatus(301)
            ->assertRedirect('https://notfilmmaker.com/works?lang=ru');
        $this->get('https://notfilmmaker.com/about')->assertOk();
        $this->get('http://notfilmmaker.vercel.app/cron/sync')->assertStatus(401);

        config(['app.redirect_to_app_url' => false]);
        $this->get('http://notfilmmaker.vercel.app/about')->assertOk();
    }
}
