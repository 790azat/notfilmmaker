<?php

namespace Tests\Feature;

use App\Models\Work;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_have_canonical_hreflang_and_open_graph(): void
    {
        $this->get('/about?lang=ru&utm_source=x')
            ->assertOk()
            ->assertSee('<html lang="ru">', false)
            ->assertSee('<link rel="canonical" href="'.url('/').'/about?lang=ru">', false)
            ->assertSee('<link rel="alternate" hreflang="hy" href="'.url('/').'/about?lang=hy">', false)
            ->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/').'/about">', false)
            ->assertSee('<meta property="og:locale" content="ru_RU">', false)
            ->assertSee('application/ld+json', false);

        // язык из ссылки запоминается
        $this->get('/contact')->assertSee('<html lang="ru">', false);
    }

    public function test_work_page_has_video_schema(): void
    {
        $work = Work::create([
            'title' => ['hy' => 'Հոլովակ', 'ru' => 'Клип', 'en' => 'Clip'],
            'category' => 'music_video',
            'video_url' => 'https://youtu.be/abcdefghijk',
            'is_published' => true,
        ]);

        $html = $this->get('/works/'.$work->slug.'?lang=en')->assertOk()->getContent();
        $this->assertStringContainsString('<meta property="og:type" content="video.other">', $html);
        preg_match('~<script type="application/ld\+json">(.+?)</script>~s', $html, $m);
        $graph = collect(json_decode($m[1], true)['@graph']);
        $this->assertSame('Clip', $graph->firstWhere('@type', 'VideoObject')['name']);
        $this->assertNotNull($graph->firstWhere('@type', 'BreadcrumbList'));
    }

    public function test_admin_and_login_are_noindex(): void
    {
        $this->get('/login')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_sitemap_lists_every_language_and_robots_points_to_it(): void
    {
        $work = Work::create(['title' => ['en' => 'Clip'], 'category' => 'film', 'is_published' => true]);

        $this->get('/sitemap.xml')->assertOk()
            ->assertSee('<loc>'.url('/').'/works/'.$work->slug.'?lang=ru</loc>', false)
            ->assertSee('hreflang="x-default" href="'.url('/').'/"', false);

        $this->get('/robots.txt')->assertOk()
            ->assertSee('Sitemap: '.url('/').'/sitemap.xml')
            ->assertSee('Disallow: /admin');
    }
}
