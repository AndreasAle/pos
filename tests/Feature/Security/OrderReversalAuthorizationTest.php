<?php

namespace Tests\Feature\Security;

use App\Models\Business;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * Void/refund order lunas mengubah uang dan stok. Kasir, dapur, dan gudang tidak boleh melakukannya
 * (cegah kecurangan "jual lalu void"). Pemilik dan admin tetap bisa.
 */
class OrderReversalAuthorizationTest extends TestCase
{
    use BuildsPosScenario, RefreshDatabase;

    private function paidOrderFor(string $role): array
    {
        $this->setUpPos();
        $business = $this->business;
        $outlet = $this->outlet;
        $user = User::factory()->create(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'role' => $role, 'is_active' => true]);
        $order = Order::create([
            'business_id' => $business->id, 'outlet_id' => $outlet->id, 'user_id' => $user->id,
            'order_number' => 'T-'.uniqid(), 'subtotal' => 20000, 'grand_total' => 20000,
            'payment_method' => 'cash', 'payment_status' => 'paid', 'status' => 'paid', 'ordered_at' => now(),
        ]);

        return [$user, $order];
    }

    public static function blockedRoles(): array
    {
        return [['cashier'], ['kitchen'], ['warehouse']];
    }

    #[DataProvider('blockedRoles')]
    public function test_staff_cannot_void_or_refund_paid_orders(string $role): void
    {
        [$user, $order] = $this->paidOrderFor($role);

        // Middleware role menolak dengan redirect (pola yang sama di seluruh aplikasi).
        foreach (['orders.void', 'orders.refund'] as $route) {
            $status = $this->actingAs($user)->post(route($route, $order), ['reason' => 'coba'])->status();
            $this->assertContains($status, [302, 403], "{$role} tidak boleh {$route}.");
        }
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_cashier_does_not_see_reversal_buttons(): void
    {
        [$user, $order] = $this->paidOrderFor('cashier');

        $this->actingAs($user)->get(route('orders.show', $order))->assertOk()
            ->assertDontSee("openModal('void-modal')", false)
            ->assertDontSee("openModal('refund-modal')", false);
    }

    public function test_admin_can_still_void(): void
    {
        [$user, $order] = $this->paidOrderFor('admin');

        $this->actingAs($user)->post(route('orders.void', $order), ['reason' => 'salah input'])->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->status);
    }
}
