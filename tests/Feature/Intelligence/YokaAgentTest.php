<?php

namespace Tests\Feature\Intelligence;

use App\Models\Business;
use App\Models\BusinessInsight;
use App\Models\IntelligenceAgent;
use App\Models\IntelligenceAgentRun;
use App\Models\Outlet;
use App\Models\User;
use App\Services\Intelligence\AgentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YokaAgentTest extends TestCase
{
    use RefreshDatabase;

    private function businessWithOwner(string $timezone = 'Asia/Jakarta'): Business
    {
        $business = Business::factory()->create(['timezone' => $timezone]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        User::factory()->create(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'role' => 'owner']);

        return $business;
    }

    public function test_default_catalog_contains_only_four_feasible_disabled_agents(): void
    {
        $business = $this->businessWithOwner();
        app(AgentService::class)->ensureDefaults($business);
        $this->assertSame(['end_of_day', 'low_stock', 'morning_briefing', 'sales_pace'], IntelligenceAgent::where('business_id', $business->id)->orderBy('agent_key')->pluck('agent_key')->all());
        $this->assertSame(0, IntelligenceAgent::where('business_id', $business->id)->where('enabled', true)->count());
    }

    public function test_daily_schedule_is_evaluated_in_agent_timezone(): void
    {
        $business = $this->businessWithOwner('Asia/Makassar');
        app(AgentService::class)->ensureDefaults($business);
        $agent = IntelligenceAgent::where('business_id', $business->id)->where('agent_key', 'morning_briefing')->firstOrFail();
        $agent->update(['enabled' => true]);
        $this->assertTrue(app(AgentService::class)->due($agent->fresh(), CarbonImmutable::parse('2026-11-12 08:00:00', 'Asia/Makassar')));
        $this->assertFalse(app(AgentService::class)->due($agent->fresh(), CarbonImmutable::parse('2026-11-12 07:59:00', 'Asia/Makassar')));
    }

    public function test_agent_run_key_makes_retries_idempotent(): void
    {
        $business = $this->businessWithOwner();
        app(AgentService::class)->ensureDefaults($business);
        $agent = IntelligenceAgent::where('business_id', $business->id)->where('agent_key', 'morning_briefing')->firstOrFail();
        $scheduled = CarbonImmutable::parse('2026-11-12 08:00:00', 'Asia/Jakarta');

        $first = app(AgentService::class)->run($agent, $scheduled);
        $second = app(AgentService::class)->run($agent, $scheduled);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, IntelligenceAgentRun::where('agent_id', $agent->id)->count());
        $this->assertSame('succeeded', $first->fresh()->status);
        $this->assertNotEmpty($first->fresh()->evidence);
        $this->assertSame(1, BusinessInsight::where('business_id', $business->id)->where('type', 'agent_morning_briefing')->count());
    }
}
