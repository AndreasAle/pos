<?php

namespace Tests\Feature\Channels;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SheetFeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

class SheetFeedTest extends TestCase
{
    use BuildsPosScenario, RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPos();
        $this->owner = User::factory()->create([
            'business_id' => $this->business->id, 'outlet_id' => $this->outlet->id,
            'role' => 'owner', 'is_active' => true,
        ]);
    }

    private function order(int $businessId, int $outletId, string $number, string $status = 'paid', string $name = 'Kopi Susu'): Order
    {
        $order = Order::create([
            'business_id' => $businessId, 'outlet_id' => $outletId, 'user_id' => $this->cashier->id,
            'order_number' => $number, 'subtotal' => 20000, 'grand_total' => 20000,
            'payment_method' => 'cash', 'payment_status' => 'paid', 'status' => $status,
            'ordered_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_name' => $name, 'price' => 20000,
            'cost_price' => 8000, 'qty' => 1, 'subtotal' => 20000,
        ]);

        return $order;
    }

    private function body($response): string
    {
        ob_start();
        $response->sendContent();

        return ob_get_clean();
    }

    public function test_owner_generates_a_link_and_the_token_is_not_stored_in_plain_text(): void
    {
        $this->actingAs($this->owner)->post(route('integrations.sheets.generate'))
            ->assertRedirect(route('integrations.index'));

        $feed = SheetFeed::first();
        $this->assertSame($this->business->id, $feed->business_id);
        $this->assertSame(48, strlen($feed->token));
        $this->assertNotSame($feed->token, $feed->getRawOriginal('token'));
        $this->assertNotSame($feed->token, $feed->token_hash);
    }

    public function test_cashier_cannot_generate_a_link(): void
    {
        $this->actingAs($this->cashier)->post(route('integrations.sheets.generate'))->assertForbidden();
        $this->assertSame(0, SheetFeed::count());
    }

    public function test_feed_returns_only_this_business_paid_orders_as_csv(): void
    {
        $feed = SheetFeed::issueFor($this->business->id);
        $this->order($this->business->id, $this->outlet->id, 'INV-1');
        $this->order($this->business->id, $this->outlet->id, 'INV-DRAFT', 'draft');

        $other = $this->foreignUser();
        $this->order($other->business_id, $other->outlet_id, 'INV-OTHER');

        $res = $this->get(route('sheets.feed', ['token' => $feed->token, 'dataset' => 'penjualan']));
        $res->assertOk()->assertHeader('Cache-Control');
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));

        $csv = $this->body($res->baseResponse);
        $this->assertStringContainsString('INV-1', $csv);
        $this->assertStringNotContainsString('INV-DRAFT', $csv);
        $this->assertStringNotContainsString('INV-OTHER', $csv);
    }

    public function test_item_dataset_lists_sold_products_and_neutralises_formulas(): void
    {
        $feed = SheetFeed::issueFor($this->business->id);
        $this->order($this->business->id, $this->outlet->id, 'INV-2', 'paid', '=HYPERLINK("x")');

        $csv = $this->body($this->get(route('sheets.feed', ['token' => $feed->token, 'dataset' => 'item']))->baseResponse);

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
    }

    public function test_wrong_token_or_dataset_is_404_and_regenerating_kills_the_old_link(): void
    {
        $old = SheetFeed::issueFor($this->business->id)->token;

        $this->get(route('sheets.feed', ['token' => 'salah', 'dataset' => 'penjualan']))->assertNotFound();
        $this->get(route('sheets.feed', ['token' => $old, 'dataset' => 'lain']))->assertNotFound();

        $this->actingAs($this->owner)->post(route('integrations.sheets.generate'));

        $this->get(route('sheets.feed', ['token' => $old, 'dataset' => 'penjualan']))->assertNotFound();
        $this->assertSame(1, SheetFeed::count());
    }

    public function test_revoke_removes_the_link(): void
    {
        $token = SheetFeed::issueFor($this->business->id)->token;

        $this->actingAs($this->owner)->delete(route('integrations.sheets.revoke'))
            ->assertRedirect(route('integrations.index'));

        $this->get(route('sheets.feed', ['token' => $token, 'dataset' => 'penjualan']))->assertNotFound();
    }

    public function test_integration_page_shows_the_formula_once_a_link_exists(): void
    {
        $this->actingAs($this->owner)->get(route('integrations.index'))
            ->assertOk()->assertSee('Hubungkan Google Sheets')->assertSee('Minta bantuan tim Yoka');

        $token = SheetFeed::issueFor($this->business->id)->token;

        $this->actingAs($this->owner)->get(route('integrations.index'))
            ->assertOk()->assertSee('IMPORTDATA', false)->assertSee($token);
    }
}
