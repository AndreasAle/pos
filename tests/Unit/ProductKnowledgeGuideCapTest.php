<?php

namespace Tests\Unit;

use App\Http\Controllers\GuideController;
use App\Services\PlatformAgents\CustomerService\ProductKnowledge;
use Tests\TestCase;

/**
 * Panduan di web boleh terus bertambah, tetapi potongan yang masuk prompt Adit
 * dibatasi per panduan dan selalu membawa tautan ke panduan lengkap.
 */
class ProductKnowledgeGuideCapTest extends TestCase
{
    public function test_every_guide_block_stays_under_the_cap_and_keeps_its_link(): void
    {
        $method = new \ReflectionMethod(ProductKnowledge::class, 'block');

        foreach (GuideController::guides() as $slug => $guide) {
            $block = $method->invoke(null, $slug, $guide);

            $this->assertLessThanOrEqual(ProductKnowledge::GUIDE_BLOCK_MAX, mb_strlen($block), "Panduan {$slug} terlalu panjang untuk prompt.");
            $this->assertStringContainsString("yokapos.com/panduan/{$slug}", $block);
        }
    }

    public function test_rendered_guides_for_a_complaint_stay_small(): void
    {
        $out = ProductKnowledge::render('kasirnya lemot banget padahal lagi rame, printer struk juga error, stok produk kacau');

        $this->assertLessThanOrEqual(2 * ProductKnowledge::GUIDE_BLOCK_MAX + 200, mb_strlen($out));
    }
}
