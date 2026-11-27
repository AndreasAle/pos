<?php

namespace Tests\Feature\Subscription;

use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase, BuildsPosScenario;

    private function owner(): User
    {
        return User::factory()->create([
            'business_id' => $this->business->id,
            'outlet_id'   => $this->outlet->id,
            'role'        => 'owner',
            'is_active'   => true,
        ]);
    }

    private function sub(int $businessId, string $status): BusinessSubscription
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Business', 'slug' => 'biz-'.uniqid(), 'price' => 12000,
            'max_outlets' => 5, 'max_users' => 30, 'features' => ['pos'], 'is_active' => true,
        ]);

        return BusinessSubscription::create([
            'business_id' => $businessId, 'subscription_plan_id' => $plan->id,
            'starts_at' => today(), 'ends_at' => today()->addDays(30),
            'status' => $status, 'amount' => 12000, 'months' => 1, 'payment_token' => 'SUB-TEST',
        ]);
    }

    public function test_invoice_shows_for_every_status(): void
    {
        $this->setUpPos();
        $owner = $this->owner();
        config(['yoka_invoice.signer' => 'Penanda Tangan Uji', 'yoka_invoice.nib' => '123']);

        foreach (['active' => 'LUNAS', 'pending' => 'BELUM DIBAYAR', 'cancelled' => 'DIBATALKAN'] as $status => $stamp) {
            $this->actingAs($owner)
                ->get(route('saas.invoice', $this->sub($this->business->id, $status)))
                ->assertOk()
                ->assertSee($stamp)
                ->assertSee('Dua belas ribu rupiah')
                ->assertSee('Penanda Tangan Uji')
                ->assertSee('NIB 123');
        }
    }

    public function test_cannot_open_another_business_invoice(): void
    {
        $this->setUpPos();
        $other = Business::factory()->create();

        $this->actingAs($this->owner())
            ->get(route('saas.invoice', $this->sub($other->id, 'active')))
            ->assertNotFound();
    }
}
