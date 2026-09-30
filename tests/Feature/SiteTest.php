<?php

namespace Tests\Feature;

use App\Livewire\Auth\Register;
use App\Models\User;
use App\Models\Work;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_render_in_every_language(): void
    {
        $work = Work::create([
            'title' => ['hy' => 'Հոլովակ', 'ru' => 'Клип', 'en' => 'Clip'],
            'category' => 'music_video',
            'video_url' => 'https://youtu.be/abcdefghijk',
        ]);

        foreach (array_keys(config('app.locales')) as $locale) {
            $this->withSession(['locale' => $locale]);
            foreach (['/', '/works', '/works/'.$work->slug, '/about', '/contact', '/login', '/register'] as $url) {
                $this->get($url)->assertOk();
            }
        }

        $this->assertSame('abcdefghijk', $work->youtube_id);
    }

    public function test_first_user_becomes_admin_and_registration_closes(): void
    {
        // миграции создают админа по умолчанию, здесь проверяем пустую базу
        User::query()->delete();

        Livewire::test(Register::class)
            ->set('name', 'Hrach')->set('email', 'h@example.com')
            ->set('password', 'secret123')->set('password_confirmation', 'secret123')
            ->call('register')
            ->assertRedirect(route('admin.dashboard'));

        $this->assertTrue(User::first()->is_admin);
        $this->assertFalse(Register::isOpen());

        $this->get('/admin')->assertOk();
        $this->get('/admin/works/create')->assertOk();
        $this->get('/admin/settings')->assertOk();
    }

    public function test_non_admin_cannot_open_admin(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/admin')->assertForbidden();
    }
}
