<?php

namespace Tests\Feature\Intelligence;

use App\Models\Business;
use App\Models\IntelligenceAuditEvent;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use App\Services\Intelligence\ActionService;
use App\Services\YokaAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class YokaActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['pos.features.intelligence_actions' => true, 'pos.features.subscription' => false, 'services.yoka_ai.key' => null]);
    }

    private function owner(): User
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        return User::factory()->create(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'role' => 'owner']);
    }

    public function test_chat_prepares_preview_without_mutating_product(): void
    {
        $owner = $this->owner();
        $product = Product::factory()->create(['business_id' => $owner->business_id, 'outlet_id' => $owner->outlet_id, 'name' => 'Es Kopi Susu', 'is_active' => true]);

        $answer = app(YokaAssistantService::class)->ask($owner, 'Matikan Es Kopi Susu');

        $this->assertTrue($product->fresh()->is_active);
        $this->assertSame('action_preview', data_get($answer, 'response.blocks.0.type'));
        $this->assertDatabaseHas('intelligence_action_proposals', ['business_id' => $owner->business_id, 'status' => 'pending']);
    }

    public function test_approved_action_executes_once_and_writes_audit(): void
    {
        $owner = $this->owner();
        $product = Product::factory()->create(['business_id' => $owner->business_id, 'name' => 'Americano', 'is_active' => true]);
        $service = app(ActionService::class);
        $proposal = $service->prepareProductAvailability($owner, 'Americano', false);
        $service->approve($owner, $proposal, 'SETUJUI');

        $first = $service->execute($owner, $proposal->fresh());
        $second = $service->execute($owner, $first);

        $this->assertFalse($product->fresh()->is_active);
        $this->assertSame('executed', $second->status);
        $this->assertSame(1, IntelligenceAuditEvent::where('object_id', $product->id)->count());
    }

    public function test_ambiguous_confirmation_cannot_approve(): void
    {
        $owner = $this->owner();
        Product::factory()->create(['business_id' => $owner->business_id, 'name' => 'Latte']);
        $proposal = app(ActionService::class)->prepareProductAvailability($owner, 'Latte', false);
        $this->expectException(ValidationException::class);
        app(ActionService::class)->approve($owner, $proposal, 'Ya');
    }

    public function test_stale_product_version_rejects_execution(): void
    {
        $owner = $this->owner();
        $product = Product::factory()->create(['business_id' => $owner->business_id, 'name' => 'Mocha']);
        $service = app(ActionService::class);
        $proposal = $service->prepareProductAvailability($owner, 'Mocha', false);
        $service->approve($owner, $proposal, 'SETUJUI');
        $product->forceFill(['updated_at' => now()->addSecond()])->saveQuietly();
        $this->expectException(ValidationException::class);
        $service->execute($owner, $proposal->fresh());
    }

    public function test_expired_proposal_cannot_be_approved(): void
    {
        $owner = $this->owner();
        Product::factory()->create(['business_id' => $owner->business_id, 'name' => 'Tea']);
        $proposal = app(ActionService::class)->prepareProductAvailability($owner, 'Tea', false);
        $proposal->update(['expires_at' => now()->subMinute()]);
        $this->expectException(ValidationException::class);
        app(ActionService::class)->approve($owner, $proposal->fresh(), 'SETUJUI');
    }

    public function test_other_tenant_cannot_approve_proposal(): void
    {
        $owner = $this->owner();
        $other = $this->owner();
        Product::factory()->create(['business_id' => $owner->business_id, 'name' => 'Tea']);
        $proposal = app(ActionService::class)->prepareProductAvailability($owner, 'Tea', false);
        $this->actingAs($other)->postJson(route('intelligence.actions.approve', $proposal), ['confirmation' => 'SETUJUI'])->assertForbidden();
    }
}
