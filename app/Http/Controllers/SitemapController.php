<?php

namespace App\Http\Controllers;

/** sitemap.xml untuk halaman publik saja (landing, fitur, panduan, artikel). */
class SitemapController extends Controller
{
    public function __invoke()
    {
        $urls = [[route('landing'), '1.0', 'weekly', null], [route('pricing'), '0.9', 'weekly', null], [route('pricing.bundles'), '0.8', 'weekly', null], [route('articles.index'), '0.8', 'weekly', null], [route('guides.index'), '0.8', 'monthly', null]];
        foreach (ArticleController::articles() as $slug => $a) {
            $urls[] = [route('articles.show', $slug), '0.7', 'monthly', $a['published']];
        }
        foreach (array_keys(GuideController::guides()) as $slug) {
            $urls[] = [route('guides.show', $slug), '0.6', 'monthly', null];
        }
        foreach (FeatureController::slugs() as $slug) {
            $urls[] = [route('features.show', $slug), '0.6', 'monthly', null];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as [$loc, $priority, $freq, $lastmod]) {
            $xml .= '  <url><loc>'.e($loc).'</loc>'.($lastmod ? '<lastmod>'.$lastmod.'</lastmod>' : '')."<changefreq>{$freq}</changefreq><priority>{$priority}</priority></url>\n";
        }

        return response($xml.'</urlset>', 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
