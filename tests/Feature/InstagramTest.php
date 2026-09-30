<?php

namespace Tests\Feature;

use App\Livewire\Admin\Works;
use App\Models\Setting;
use App\Models\User;
use App\Models\Work;
use App\Support\Instagram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class InstagramTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_posts_in_pages_and_skips_existing(): void
    {
        Storage::fake('public');
        Setting::put('instagram_token', 'IGAAtesttokentesttokentest');

        Http::fake([
            'graph.instagram.com/*/me/media*after=p2*' => Http::response(['data' => [
                ['id' => '3', 'media_type' => 'VIDEO', 'media_url' => 'https://cdn.test/v.mp4', 'thumbnail_url' => 'https://cdn.test/t.jpg', 'permalink' => 'https://www.instagram.com/reel/ABC/', 'caption' => 'Official music video', 'timestamp' => '2024-05-01T10:00:00+0000'],
            ], 'paging' => []]),
            'graph.instagram.com/*/me/media*' => Http::response(['data' => [
                ['id' => '1', 'media_type' => 'IMAGE', 'media_url' => 'https://cdn.test/1.jpg', 'permalink' => 'https://www.instagram.com/p/A1/', 'caption' => "Portrait session #yerevan\nmore text", 'timestamp' => '2025-01-01T10:00:00+0000'],
                ['id' => '2', 'media_type' => 'CAROUSEL_ALBUM', 'permalink' => 'https://www.instagram.com/p/A2/', 'timestamp' => '2025-02-01T10:00:00+0000', 'children' => ['data' => [
                    ['media_type' => 'IMAGE', 'media_url' => 'https://cdn.test/2a.jpg'],
                    ['media_type' => 'IMAGE', 'media_url' => 'https://cdn.test/2b.jpg'],
                ]]],
            ], 'paging' => ['next' => 'https://graph.instagram.com/next', 'cursors' => ['after' => 'p2']]]),
            'cdn.test/*.mp4' => Http::response('video', 200, ['Content-Type' => 'video/mp4']),
            'cdn.test/*' => Http::response('image', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $this->assertSame(['added' => 3, 'more' => false], Instagram::importBatch(fromStart: true));

        $photo = Work::where('instagram_id', 'A1')->first();
        $this->assertSame('Portrait session', $photo->title);
        $this->assertSame('photo', $photo->category);
        $this->assertNotNull($photo->cover);

        $this->assertCount(2, Work::where('instagram_id', 'A2')->first()->media);

        $video = Work::where('instagram_id', 'ABC')->first();
        $this->assertSame('music_video', $video->category);
        $this->assertStringEndsWith('.mp4', $video->video_url);

        $this->assertSame(0, Instagram::importBatch(fromStart: true)['added']);
    }

    public function test_embeds_instagram_links(): void
    {
        $work = new Work(['video_url' => 'https://www.instagram.com/reel/ABC_1/']);
        $this->assertSame('https://www.instagram.com/reel/ABC_1/embed', $work->embedUrl());
    }

    public function test_settings_page_shows_instagram_card(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee('Instagram')->assertSee('IGAA');
        Setting::put('instagram_token', 'IGAAtesttokentesttokentest');
        Setting::put('instagram_username', 'not_filmmaker');
        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee('@not_filmmaker');
    }

    public function test_archive_post_creates_work_once(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $items = [['type' => 'image', 'path' => 'gallery/a.jpg'], ['type' => 'image', 'path' => 'gallery/b.jpg']];

        Livewire::actingAs($admin)->test(Works::class)
            ->call('importArchivePost', 'CkDNW34skvT', 'carousel', $items, null, 1666515006000)
            ->assertReturned(true)
            ->call('importArchivePost', 'CkDNW34skvT', 'carousel', $items, null, 1666515006000)
            ->assertReturned(false)
            ->call('instagramCodes')
            ->assertReturned(['CkDNW34skvT']);

        $work = Work::where('instagram_id', 'CkDNW34skvT')->first();
        $this->assertSame('Фотосерия · октябрь 2022', $work->getTranslation('title', 'ru'));
        $this->assertSame(2022, $work->year);
        $this->assertCount(2, $work->media);
    }

    public function test_archive_post_rejects_foreign_urls(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)->test(Works::class)
            ->call('importArchivePost', 'Abcdefghijk', 'photo', [['type' => 'image', 'path' => 'https://evil.test/x.jpg']], null, 1666515006000)
            ->assertStatus(422);
        $this->assertSame(0, Work::count());
    }
}
