<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * Produk, kategori, bundling, stok, dan resep mengubah harga dan persediaan.
 * Kasir dan dapur tidak boleh membukanya walau tahu alamatnya; gudang hanya stok & resep.
 */
class MasterDataAuthorizationTest extends TestCase
{
    use BuildsPosScenario, RefreshDatabase;

    private const PRODUCT_PAGES = ['/products', '/products/create', '/bundles'];
    private const STOCK_PAGES = ['/inventory/ingredients', '/inventory/movements', '/recipes'];

    private function userWithRole(string $role): User
    {
        $this->setUpPos();

        return User::factory()->create([
            'business_id' => $this->business->id, 'outlet_id' => $this->outlet->id,
            'role' => $role, 'is_active' => true, 'email_verified_at' => now(),
        ]);
    }

    public static function staffRoles(): array
    {
        return [['cashier'], ['kitchen']];
    }

    #[DataProvider('staffRoles')]
    public function test_staff_cannot_open_product_or_stock_pages(string $role): void
    {
        $user = $this->userWithRole($role);

        foreach ([...self::PRODUCT_PAGES, ...self::STOCK_PAGES] as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }
    }

    #[DataProvider('staffRoles')]
    public function test_staff_cannot_change_prices_or_stock(string $role): void
    {
        $user = $this->userWithRole($role);

        $this->actingAs($user)->post('/products', ['name' => 'Curang', 'price' => 1])->assertForbidden();
        $this->actingAs($user)->post('/categories', ['name' => 'Curang'])->assertForbidden();
        $this->actingAs($user)->post('/inventory/ingredients', ['name' => 'Curang'])->assertForbidden();
    }

    public function test_warehouse_handles_stock_but_not_products(): void
    {
        $user = $this->userWithRole('warehouse');

        foreach (self::PRODUCT_PAGES as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }
        foreach (self::STOCK_PAGES as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }

    public function test_owner_and_admin_keep_access(): void
    {
        foreach (['owner', 'admin'] as $role) {
            $user = $this->userWithRole($role);
            foreach ([...self::PRODUCT_PAGES, ...self::STOCK_PAGES] as $url) {
                $this->actingAs($user)->get($url)->assertOk();
            }
        }
    }
}
