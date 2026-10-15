<?php

namespace App\Http\Controllers;

use App\Models\Work;
use App\Support\Seo;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $pages = collect(['/', '/works', '/about', '/contact'])->map(fn ($p) => ['path' => $p, 'lastmod' => null, 'image' => null]);
        Work::published()->with('media')->ordered()->get()->each(fn (Work $w) => $pages->push([
            'path' => route('works.show', $w, false),
            'lastmod' => $w->updated_at?->toAtomString(),
            'image' => Seo::absolute($w->coverUrl()),
        ]));

        // Каждая страница на всех языках, с перекрёстными hreflang-ссылками.
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'."\n";
        foreach ($pages as $page) {
            $alternates = Seo::alternates($page['path']);
            $links = '';
            foreach ($alternates as $hreflang => $href) {
                $links .= '<xhtml:link rel="alternate" hreflang="'.$hreflang.'" href="'.e($href).'"/>';
            }
            foreach (array_keys(config('app.locales')) as $locale) {
                $xml .= '<url><loc>'.e($alternates[$locale]).'</loc>'
                    .($page['lastmod'] ? '<lastmod>'.$page['lastmod'].'</lastmod>' : '')
                    .$links
                    .($page['image'] ? '<image:image><image:loc>'.e($page['image']).'</image:loc></image:image>' : '')
                    ."</url>\n";
            }
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $lines = Seo::indexable()
            ? ['User-agent: *', 'Allow: /', 'Disallow: /admin', 'Disallow: /login', 'Disallow: /register', 'Disallow: /cron/', 'Disallow: /chat/', 'Disallow: /lang/', 'Disallow: /telegram/', '', 'Sitemap: '.Seo::base().'/sitemap.xml']
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
