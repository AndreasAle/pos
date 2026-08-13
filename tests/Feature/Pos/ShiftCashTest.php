<?php

namespace Tests\Feature\Pos;

use App\Services\PosOrderService;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * Closing a shift reconciles the physical drawer against what the system thinks
 * was taken in cash. Get this wrong and an honest cashier is accused of a
 * shortfall — or a real one goes unnoticed.
 */
class ShiftCashTest extends TestCase
{
    use RefreshDatabase, BuildsPosScenario;

    private function shifts(): ShiftService
    {
        return app(ShiftService::class);
    }

    private function sell(float $total, array $overrides = []): void
    {
        $product = $this->product(price: $total);

        app(PosOrderService::class)->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1],
        ], $overrides));
    }

    public function test_a_drawer_that_matches_cash_sales_shows_no_difference(): void
    {
        $this->setUpPos();
        $this->sell(150000, ['payment_method' => 'cash', 'paid_amount' => 150000]);

        // Opening float 100.000 + 150.000 cash taken.
        $closed = $this->shifts()->closeShift($this->shift, 250000, null);

        $this->assertEquals(250000, $closed->closing_cash_expected);
        $this->assertEquals(0, $closed->cash_difference);
        $this->assertSame('closed', $closed->status);
        $this->assertNotNull($closed->closed_at);
    }

    public function test_non_cash_sales_are_not_expected_in_the_drawer(): void
    {
        $this->setUpPos();
        $this->sell(150000, ['payment_method' => 'qris', 'paid_amount' => 150000]);

        $closed = $this->shifts()->closeShift($this->shift, 100000, null);

        $this->assertEquals(100000, $closed->closing_cash_expected);
        $this->assertEquals(0, $closed->cash_difference);
    }

    public function test_only_the_cash_half_of_a_split_payment_is_expected(): void
    {
        $this->setUpPos();
        $this->sell(100000, [
            'paid_amount'    => 0,
            'split_payments' => [
                ['method' => 'cash', 'amount' => 60000],
                ['method' => 'qris', 'amount' => 40000],
            ],
        ]);

        // Drawer: 100.000 float + 60.000 cash = 160.000. Nothing is missing.
        $closed = $this->shifts()->closeShift($this->shift, 160000, null);

        $this->assertEquals(160000, $closed->closing_cash_expected);
        $this->assertEquals(0, $closed->cash_difference);
    }

    public function test_a_split_payment_led_by_a_non_cash_method_still_counts_its_cash(): void
    {
        $this->setUpPos();
        $this->sell(100000, [
            'paid_amount'    => 0,
            'split_payments' => [
                ['method' => 'qris', 'amount' => 40000],
                ['method' => 'cash', 'amount' => 60000],
            ],
        ]);

        $closed = $this->shifts()->closeShift($this->shift, 160000, null);

        $this->assertEquals(160000, $closed->closing_cash_expected);
        $this->assertEquals(0, $closed->cash_difference);
    }

    public function test_a_split_payment_with_no_cash_leg_expects_nothing_extra(): void
    {
        $this->setUpPos();
        $this->sell(100000, [
            'paid_amount'    => 0,
            'split_payments' => [
                ['method' => 'qris',     'amount' => 40000],
                ['method' => 'transfer', 'amount' => 60000],
            ],
        ]);

        $closed = $this->shifts()->closeShift($this->shift, 100000, null);

        $this->assertEquals(100000, $closed->closing_cash_expected);
        $this->assertEquals(0, $closed->cash_difference);
    }

    public function test_mixed_sales_are_totalled_correctly(): void
    {
        $this->setUpPos();
        $this->sell(50000, ['payment_method' => 'cash', 'paid_amount' => 50000]);
        $this->sell(70000, ['payment_method' => 'qris', 'paid_amount' => 70000]);
        $this->sell(100000, [
            'paid_amount'    => 0,
            'split_payments' => [
                ['method' => 'cash', 'amount' => 30000],
                ['method' => 'qris', 'amount' => 70000],
            ],
        ]);

        // 100.000 float + 50.000 + 30.000 cash = 180.000
        $closed = $this->shifts()->closeShift($this->shift, 180000, null);

        $this->assertEquals(180000, $closed->closing_cash_expected);
        $this->assertEquals(0, $closed->cash_difference);
    }

    public function test_a_real_shortfall_is_still_reported(): void
    {
        $this->setUpPos();
        $this->sell(150000, ['payment_method' => 'cash', 'paid_amount' => 150000]);

        $closed = $this->shifts()->closeShift($this->shift, 200000, 'Kurang');

        $this->assertEquals(250000, $closed->closing_cash_expected);
        $this->assertEquals(-50000, $closed->cash_difference);
        $this->assertSame('Kurang', $closed->notes);
    }

    public function test_a_voided_cash_sale_is_no_longer_expected_in_the_drawer(): void
    {
        $this->setUpPos();
        $this->sell(150000, ['payment_method' => 'cash', 'paid_amount' => 150000]);

        $order = \App\Models\Order::sole();
        $this->actingAs($this->cashier)->post(route('orders.void', $order), ['reason' => 'Salah']);

        // The money went back to the customer, so only the float should remain.
        $closed = $this->shifts()->closeShift($this->shift->fresh(), 100000, null);

        $this->assertEquals(100000, $closed->closing_cash_expected);
        $this->assertEquals(0, $closed->cash_difference);
    }

    public function test_another_shifts_takings_are_not_counted(): void
    {
        $this->setUpPos();
        $this->sell(150000, ['payment_method' => 'cash', 'paid_amount' => 150000]);

        $otherShift = \App\Models\CashierShift::factory()->create([
            'business_id'  => $this->business->id,
            'outlet_id'    => $this->outlet->id,
            'user_id'      => $this->cashier->id,
            'opening_cash' => 50000,
        ]);

        $closed = $this->shifts()->closeShift($otherShift, 50000, null);

        $this->assertEquals(50000, $closed->closing_cash_expected);
        $this->assertEquals(0, $closed->cash_difference);
    }
}
