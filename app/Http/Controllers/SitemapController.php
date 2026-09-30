<?php

namespace App\Http\Controllers;

use App\Models\Work;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = collect(['/', '/works', '/about', '/contact'])->map(fn ($p) => ['loc' => url($p), 'lastmod' => null]);
        Work::published()->get(['slug', 'updated_at'])->each(fn ($w) => $urls->push(['loc' => route('works.show', $w), 'lastmod' => $w->updated_at?->toAtomString()]));

        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $u) {
            $xml .= '<url><loc>'.e($u['loc']).'</loc>'.($u['lastmod'] ? '<lastmod>'.$u['lastmod'].'</lastmod>' : '').'</url>';
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
