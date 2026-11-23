<?php

namespace Tests\Feature\Pos;

use App\Models\CashierShift;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * Offline register (docs/offline-mode): idempotent sales and the sync endpoint
 * that replays cash sales queued on a device without a connection.
 */
class OfflineSyncTest extends TestCase
{
    use RefreshDatabase, BuildsPosScenario;

    protected function setUp(): void
    {
        parent::setUp();
        config(['pos.features.pos_offline' => true]);
    }

    /** @return array<string, mixed> */
    private function offlineSale(Product $product, array $overrides = []): array
    {
        return array_merge([
            'client_uuid'    => (string) Str::uuid(),
            'offline_number' => 'OFF-A7K-261123-0001',
            'user_id'        => $this->cashier->id,
            'shift_id'       => $this->shift->id,
            'ordered_at'     => now()->subHour()->toIso8601String(),
            'order_type'     => 'takeaway',
            'items'          => [['product_id' => $product->id, 'qty' => 2]],
            'paid_amount'    => 50000,
            'change_amount'  => 10000,
            'grand_total'    => 40000,
            'catalog_version' => 'abc123',
        ], $overrides);
    }

    private function sync(array $orders)
    {
        return $this->actingAs($this->cashier)->postJson(route('pos.sync'), [
            'device_id' => 'device-1',
            'orders'    => $orders,
        ]);
    }

    // ── Idempotent live sales ────────────────────────────────────────────────

    public function test_retrying_a_live_sale_with_the_same_uuid_records_it_once(): void
    {
        $this->setUpPos();
        $product = $this->product(20000);
        $uuid    = (string) Str::uuid();
        $payload = [
            'items'          => [['product_id' => $product->id, 'qty' => 1]],
            'payment_method' => 'cash',
            'paid_amount'    => 20000,
            'client_uuid'    => $uuid,
        ];

        $first  = $this->actingAs($this->cashier)->postJson(route('pos.store'), $payload)->assertOk();
        $second = $this->actingAs($this->cashier)->postJson(route('pos.store'), $payload)->assertOk();

        $first->assertJson(['duplicate' => false]);
        $second->assertJson(['duplicate' => true, 'order_id' => $first->json('order_id')]);
        $this->assertSame(1, Order::where('client_uuid', $uuid)->count());
    }

    public function test_a_live_sale_without_a_uuid_still_works(): void
    {
        $this->setUpPos();
        $product = $this->product(20000);

        $this->actingAs($this->cashier)->postJson(route('pos.store'), [
            'items'          => [['product_id' => $product->id, 'qty' => 1]],
            'payment_method' => 'cash',
            'paid_amount'    => 20000,
        ])->assertOk()->assertJson(['duplicate' => false]);
    }

    // ── Sync ─────────────────────────────────────────────────────────────────

    public function test_a_queued_sale_is_recorded_with_its_offline_number_and_time(): void
    {
        $this->setUpPos();
        $product = $this->product(20000);
        $sale    = $this->offlineSale($product);

        $res = $this->sync([$sale])->assertOk();

        $res->assertJsonPath('results.0.status', 'synced');
        $order = Order::where('client_uuid', $sale['client_uuid'])->firstOrFail();

        $this->assertSame('OFF-A7K-261123-0001', $order->offline_number);
        $this->assertSame('device-1', $order->device_id);
        $this->assertSame('cash', $order->payment_method);
        $this->assertSame('paid', $order->status);
        $this->assertEquals(40000, (float) $order->grand_total);
        $this->assertEquals(50000, (float) $order->paid_amount);
        $this->assertEquals(10000, (float) $order->change_amount);
        $this->assertSame($this->shift->id, $order->cashier_shift_id);
        $this->assertNull($order->offline_review);
        $this->assertNotNull($order->synced_at);
        // Dated when it was rung up, not when it reached the server.
        $this->assertTrue($order->created_at->lessThan(now()->subMinutes(50)));
        $this->assertTrue($order->ordered_at->equalTo($order->created_at));
    }

    public function test_replaying_the_same_batch_does_not_double_anything(): void
    {
        $this->setUpPos();
        $product = $this->product(20000);
        $sale    = $this->offlineSale($product);

        $this->sync([$sale])->assertJsonPath('results.0.status', 'synced');
        $this->sync([$sale])->assertJsonPath('results.0.status', 'duplicate');

        $this->assertSame(1, Order::where('client_uuid', $sale['client_uuid'])->count());
    }

    public function test_a_sale_that_reached_the_server_live_is_not_recorded_again_by_sync(): void
    {
        $this->setUpPos();
        $product = $this->product(20000);
        $uuid    = (string) Str::uuid();

        // The live request got through but its response was lost; the register
        // queued the same sale offline under the same uuid.
        $this->actingAs($this->cashier)->postJson(route('pos.store'), [
            'items'          => [['product_id' => $product->id, 'qty' => 2]],
            'payment_method' => 'cash',
            'paid_amount'    => 50000,
            'client_uuid'    => $uuid,
        ])->assertOk();

        $this->sync([$this->offlineSale($product, ['client_uuid' => $uuid])])
            ->assertJsonPath('results.0.status', 'duplicate');

        $this->assertSame(1, Order::where('business_id', $this->business->id)->count());
    }

    public function test_a_price_change_since_the_sale_is_recorded_and_flagged(): void
    {
        $this->setUpPos();
        // The register still had 20.000; today 2 x 30.000 = 60.000 > the 50.000 paid.
        $product = $this->product(30000);

        $this->sync([$this->offlineSale($product)])->assertJsonPath('results.0.status', 'synced');

        $order = Order::where('business_id', $this->business->id)->firstOrFail();
        $this->assertStringContainsString('price_mismatch', $order->offline_review);
        $this->assertStringContainsString('underpaid', $order->offline_review);
        $this->assertEquals(40000, $order->offline_meta['client_total']);
        $this->assertEquals(50000, (float) $order->paid_amount, 'The cash taken is what is recorded.');
    }

    public function test_a_shift_closed_before_sync_keeps_the_sale_in_that_shift(): void
    {
        $this->setUpPos();
        $product = $this->product(20000);
        $closed  = CashierShift::factory()->closed()->create([
            'business_id' => $this->business->id,
            'outlet_id'   => $this->outlet->id,
            'user_id'     => $this->cashier->id,
        ]);

        $this->sync([$this->offlineSale($product, ['shift_id' => $closed->id])])
            ->assertJsonPath('results.0.status', 'synced');

        $order = Order::where('business_id', $this->business->id)->firstOrFail();
        $this->assertSame($closed->id, $order->cashier_shift_id);
        $this->assertStringContainsString('shift_closed', $order->offline_review);
    }

    public function test_short_stock_does_not_lose_an_offline_sale(): void
    {
        $this->setUpPos(['allow_negative_stock' => false]);
        [$product, $ingredient] = $this->trackedProduct(perUnit: 10, stock: 5, price: 20000);

        $this->sync([$this->offlineSale($product)])->assertJsonPath('results.0.status', 'synced');

        $this->assertEquals(-15, (float) $ingredient->fresh()->current_stock);
    }

    public function test_a_clock_running_ahead_cannot_date_a_sale_into_the_future(): void
    {
        $this->setUpPos();
        $product = $this->product(20000);

        $this->sync([$this->offlineSale($product, ['ordered_at' => now()->addDays(3)->toIso8601String()])])
            ->assertJsonPath('results.0.status', 'synced');

        $this->assertTrue(Order::firstOrFail()->created_at->lessThanOrEqualTo(now()));
    }

    public function test_a_sale_older_than_the_limit_is_flagged_stale(): void
    {
        $this->setUpPos();
        $product = $this->product(20000);

        $this->sync([$this->offlineSale($product, ['ordered_at' => now()->subDays(10)->toIso8601String()])])
            ->assertJsonPath('results.0.status', 'synced');

        $this->assertStringContainsString('stale', Order::firstOrFail()->offline_review);
    }

    public function test_one_bad_sale_does_not_hold_back_the_rest(): void
    {
        $this->setUpPos();
        $product = $this->product(20000);
        $foreign = $this->foreignUser();
        $other   = Product::factory()->create(['business_id' => $foreign->business_id, 'price' => 20000]);

        $res = $this->sync([
            $this->offlineSale($other),
            $this->offlineSale($product),
        ])->assertOk();

        $res->assertJsonPath('results.0.status', 'rejected');
        $res->assertJsonPath('results.1.status', 'synced');
        $this->assertSame(1, Order::where('business_id', $this->business->id)->count());
    }

    public function test_a_seller_from_another_business_is_rejected(): void
    {
        $this->setUpPos();
        $product = $this->product(20000);
        $foreign = $this->foreignUser('cashier');

        $this->sync([$this->offlineSale($product, ['user_id' => $foreign->id])])
            ->assertJsonPath('results.0.status', 'rejected');

        $this->assertSame(0, Order::count());
    }

    public function test_an_invalid_sale_is_rejected_with_a_message(): void
    {
        $this->setUpPos();

        $res = $this->sync([['client_uuid' => (string) Str::uuid(), 'items' => []]])->assertOk();

        $res->assertJsonPath('results.0.status', 'rejected');
        $this->assertNotEmpty($res->json('results.0.message'));
    }

    public function test_a_batch_is_capped(): void
    {
        $this->setUpPos();
        $product = $this->product(20000);
        $orders  = array_map(fn () => $this->offlineSale($product), range(1, 21));

        $this->sync($orders)->assertStatus(422);
    }

    public function test_sync_requires_a_login(): void
    {
        $this->setUpPos();

        $this->postJson(route('pos.sync'), ['device_id' => 'x', 'orders' => [[]]])->assertStatus(401);
    }

    public function test_ping_hands_out_a_fresh_csrf_token(): void
    {
        $this->setUpPos();

        $this->actingAs($this->cashier)->getJson(route('pos.ping'))
            ->assertOk()
            ->assertJson(['ok' => true, 'user_id' => $this->cashier->id, 'shift_id' => $this->shift->id])
            ->assertJsonStructure(['csrf', 'server_time']);
    }

    // ── Service worker + page config ─────────────────────────────────────────

    public function test_the_service_worker_is_served_with_its_asset_list(): void
    {
        $res = $this->get('/pos-sw.js')->assertOk();

        $this->assertStringContainsString('javascript', $res->headers->get('Content-Type'));
        $this->assertSame('/', $res->headers->get('Service-Worker-Allowed'));
        $this->assertStringNotContainsString('__SW_VERSION__', $res->getContent());
        $this->assertStringNotContainsString('__PRECACHE__', $res->getContent());
    }

    public function test_the_service_worker_is_absent_while_the_feature_is_off(): void
    {
        config(['pos.features.pos_offline' => false]);

        $this->get('/pos-sw.js')->assertNotFound();
    }

    public function test_the_register_carries_the_offline_config_only_when_enabled(): void
    {
        $this->setUpPos();
        $this->product(20000);

        $this->actingAs($this->cashier)->get(route('pos.index'))
            ->assertOk()
            ->assertSee('catalog_version', false)
            ->assertSee('pos-sw.js', false);

        config(['pos.features.pos_offline' => false]);

        $this->actingAs($this->cashier)->get(route('pos.index'))
            ->assertOk()
            ->assertDontSee('catalog_version', false);
    }
}
