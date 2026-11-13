<?php

namespace Tests\Feature\Channels;

use App\Models\ChannelIntegration;
use App\Models\ChannelOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

class ShopeeFoodInboundTest extends TestCase
{
    use BuildsPosScenario, RefreshDatabase;

    private const BASE = '/api/shopeefood/sf-test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPos();

        config([
            'channels.shopeefood.inbound_client_id'     => 'cid-1',
            'channels.shopeefood.inbound_client_secret' => 'secret-1',
            'channels.shopeefood.indicator'             => 'sf-test',
        ]);
    }

    private function link(string $storeId = '20278000', string $status = 'connected'): ChannelIntegration
    {
        return ChannelIntegration::create([
            'business_id' => $this->business->id, 'outlet_id' => $this->outlet->id,
            'provider' => 'shopeefood', 'merchant_id' => $storeId, 'status' => $status,
        ]);
    }

    private function token(): string
    {
        return $this->postJson(self::BASE.'/gettoken', ['client_id' => 'cid-1', 'client_secret' => 'secret-1'])
            ->assertOk()->json('access_token');
    }

    private function payload(array $order = []): array
    {
        return ['code' => 0, 'msg' => 'success', 'data' => ['order' => array_merge([
            'id' => '2091320494251008577', 'store_id' => '20278000', 'currency' => 'IDR', 'region' => 'ID',
            'order_short_code' => 'SF014', 'status' => 'APPROVED', 'items' => [], 'amount' => ['total' => 18000],
        ], $order)]];
    }

    public function test_endpoints_are_404_until_configured(): void
    {
        config(['channels.shopeefood.inbound_client_id' => null]);

        $this->postJson(self::BASE.'/gettoken', ['client_id' => 'cid-1', 'client_secret' => 'secret-1'])->assertNotFound();
    }

    public function test_a_wrong_indicator_is_404(): void
    {
        $this->postJson('/api/shopeefood/other/gettoken', ['client_id' => 'cid-1', 'client_secret' => 'secret-1'])->assertNotFound();
    }

    public function test_gettoken_rejects_bad_credentials_and_issues_a_token_for_good_ones(): void
    {
        $this->postJson(self::BASE.'/gettoken', ['client_id' => 'cid-1', 'client_secret' => 'nope'])->assertStatus(401);

        $this->postJson(self::BASE.'/gettoken', ['client_id' => 'cid-1', 'client_secret' => 'secret-1'])
            ->assertOk()->assertJsonStructure(['access_token', 'expires_in'])->assertJsonPath('expires_in', 3600);
    }

    public function test_business_endpoints_need_a_valid_bearer_token(): void
    {
        $this->link();

        $this->postJson(self::BASE.'/orders', $this->payload())->assertStatus(401);
        $this->postJson(self::BASE.'/orders', $this->payload(), ['Authorization' => 'Bearer bogus'])->assertStatus(401);
        $this->assertSame(0, ChannelOrder::count());
    }

    public function test_an_order_is_stored_and_resending_it_does_not_duplicate(): void
    {
        $this->link();
        $h = ['Authorization' => 'Bearer '.$this->token()];

        $this->postJson(self::BASE.'/orders', $this->payload(), $h)->assertOk()->assertJson(['code' => 0, 'message' => 'success']);
        $this->postJson(self::BASE.'/orders', $this->payload(['status' => 'MERCHANT_CONFIRMED']), $h)->assertOk();

        $this->assertSame(1, ChannelOrder::count());
        $o = ChannelOrder::first();
        $this->assertSame($this->business->id, $o->business_id);
        $this->assertSame($this->outlet->id, $o->outlet_id);
        $this->assertSame('SF014', $o->short_code);
        $this->assertSame('MERCHANT_CONFIRMED', $o->status);
    }

    public function test_orders_for_an_unlinked_or_pending_store_are_refused(): void
    {
        $h = ['Authorization' => 'Bearer '.$this->token()];

        $this->postJson(self::BASE.'/orders', $this->payload(), $h)->assertStatus(404);

        // Terdaftar tapi belum diverifikasi tim Yoka: belum boleh menerima pesanan.
        $this->link('20278000', 'pending');
        $this->postJson(self::BASE.'/orders', $this->payload(), $h)->assertStatus(404);
        $this->assertSame(0, ChannelOrder::count());
    }

    public function test_order_status_updates_an_existing_order_and_404s_for_unknown_ones(): void
    {
        $this->link();
        $h = ['Authorization' => 'Bearer '.$this->token()];
        $this->postJson(self::BASE.'/orders', $this->payload(), $h)->assertOk();

        $this->postJson(self::BASE.'/order/status', ['id' => '2091320494251008577', 'store_id' => '20278000', 'status' => 'CANCELED'], $h)->assertOk();
        $this->assertSame('CANCELED', ChannelOrder::first()->status);

        $this->postJson(self::BASE.'/order/status', ['id' => 'missing', 'store_id' => '20278000', 'status' => 'CANCELED'], $h)->assertStatus(404);
        $this->postJson(self::BASE.'/order/status', ['id' => 'x'], $h)->assertStatus(400);
    }

    public function test_menu_result_records_and_clears_the_last_error(): void
    {
        $i = $this->link();
        $h = ['Authorization' => 'Bearer '.$this->token()];

        $this->postJson(self::BASE.'/menu/notification/result', ['store_id' => '20278000', 'result' => 1, 'msg' => 'Dish is not found'], $h)->assertOk();
        $this->assertStringContainsString('Dish is not found', $i->fresh()->last_error);

        $this->postJson(self::BASE.'/menu/notification/result', ['store_id' => '20278000', 'result' => 0, 'msg' => ''], $h)->assertOk();
        $this->assertNull($i->fresh()->last_error);
    }

    public function test_order_goes_to_the_business_that_owns_the_store_id(): void
    {
        $this->link('111');
        $other = $this->foreignUser();
        ChannelIntegration::create([
            'business_id' => $other->business_id, 'outlet_id' => $other->outlet_id,
            'provider' => 'shopeefood', 'merchant_id' => '222', 'status' => 'connected',
        ]);
        $h = ['Authorization' => 'Bearer '.$this->token()];

        $this->postJson(self::BASE.'/orders', $this->payload(['id' => 'A', 'store_id' => '222']), $h)->assertOk();

        $this->assertSame($other->business_id, ChannelOrder::where('external_order_id', 'A')->value('business_id'));
    }
}
