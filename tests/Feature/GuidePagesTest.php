<?php

namespace Tests\Feature;

use App\Http\Controllers\GuideController;
use Tests\TestCase;

class GuidePagesTest extends TestCase
{
    public function test_index_lists_every_guide(): void
    {
        $res = $this->get('/panduan')->assertOk();
        foreach (GuideController::guides() as $slug => $guide) {
            $res->assertSee($guide['title'])->assertSee(route('guides.show', $slug), false);
        }
    }

    public function test_every_guide_page_renders_steps_faq_and_navigation(): void
    {
        foreach (GuideController::guides() as $slug => $guide) {
            $res = $this->get('/panduan/'.$slug)->assertOk()
                ->assertSee($guide['title'])
                ->assertSee('"@type":"HowTo"', false)
                ->assertSee(route('register'), false);
            foreach ($guide['steps'] as [$title]) {
                $res->assertSee($title);
            }
        }
    }

    public function test_unknown_guide_is_404(): void
    {
        $this->get('/panduan/tidak-ada')->assertNotFound();
    }

    public function test_guides_state_the_real_trial_length(): void
    {
        $text = json_encode(GuideController::guides(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('7 hari', $text);
        $this->assertStringNotContainsString('30 hari trial', $text);
        $this->assertStringNotContainsString('trial 30', $text);
    }

    public function test_landing_header_links_to_guides(): void
    {
        $this->get('/')->assertOk()->assertSee(route('guides.index'), false);
    }
}
