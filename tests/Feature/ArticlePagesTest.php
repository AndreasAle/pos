<?php

namespace Tests\Feature;

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\GuideController;
use Tests\TestCase;

class ArticlePagesTest extends TestCase
{
    public function test_index_and_every_article_render_with_seo_tags(): void
    {
        $this->get('/artikel')->assertOk()->assertSee('og:title', false);
        foreach (ArticleController::articles() as $slug => $a) {
            $res = $this->get('/artikel/'.$slug)->assertOk()
                ->assertSee('<link rel="canonical" href="'.route('articles.show', $slug).'">', false)
                ->assertSee('"@type":"Article"', false)
                ->assertSee('"@type":"FAQPage"', false)
                ->assertSee('property="og:image"', false);
            foreach ($a['sections'] as $s) {
                $res->assertSee($s['h']);
            }
            $this->assertLessThanOrEqual(160, mb_strlen($a['description']), "Deskripsi {$slug} terlalu panjang untuk Google.");
            $this->assertFileExists(public_path($a['image']));
        }
    }

    public function test_unknown_article_is_404(): void
    {
        $this->get('/artikel/tidak-ada')->assertNotFound();
    }

    public function test_sitemap_lists_public_pages_only(): void
    {
        $res = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        foreach (array_keys(ArticleController::articles()) as $slug) {
            $res->assertSee(route('articles.show', $slug), false);
        }
        foreach (array_keys(GuideController::guides()) as $slug) {
            $res->assertSee(route('guides.show', $slug), false);
        }
        $res->assertDontSee('/platform', false)->assertDontSee('/login', false);
    }

    public function test_canonical_ignores_www_host(): void
    {
        config(['app.url' => 'https://yokapos.com']);
        $this->get('http://www.yokapos.com/fitur/pelanggan-promosi')->assertOk()
            ->assertSee('<link rel="canonical" href="https://yokapos.com/fitur/pelanggan-promosi">', false);
        $this->get('http://www.yokapos.com/')->assertOk()
            ->assertSee('<link rel="canonical" href="https://yokapos.com">', false);
    }

    public function test_landing_declares_yoka_brand_schema(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('"@type":"Organization"', false)
            ->assertSee('"@type":"SoftwareApplication"', false)
            ->assertSee('Conwebid Indonesia Digital', false);
    }

    public function test_login_and_register_are_noindex_and_not_blocked_in_robots(): void
    {
        $this->get('/login')->assertOk()->assertSee('noindex', false);
        $this->get('/register')->assertOk()->assertSee('noindex', false);
        $robots = file_get_contents(public_path('robots.txt'));
        $this->assertStringNotContainsString('Disallow: /login', $robots);
        $this->assertStringContainsString('Sitemap: https://yokapos.com/sitemap.xml', $robots);
    }

    public function test_articles_never_promise_a_30_day_trial(): void
    {
        $text = json_encode(ArticleController::articles(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('7 hari', $text);
        $this->assertDoesNotMatchRegularExpression('/trial[^"]{0,20}30 hari/u', $text);
    }
}
