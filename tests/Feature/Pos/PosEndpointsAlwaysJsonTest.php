<?php

namespace Tests\Feature\Pos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * The POS screen talks to these routes over fetch() and parses the reply as
 * JSON. If Laravel answers with HTML — which it does on a validation failure
 * when the request carries no Accept header — the cashier gets
 * "unexpected token ... is not valid JSON" and no clue what went wrong.
 *
 * Every request here deliberately mimics a bare fetch(): a JSON body, no
 * Accept header.
 */
class PosEndpointsAlwaysJsonTest extends TestCase
{
    use RefreshDatabase, BuildsPosScenario;

    /**
     * A POST shaped exactly like the POS screen's fetch(): Content-Type set,
     * Accept absent.
     *
     * @param  array<string, mixed>  $payload
     */
    private function bareFetch(string $url, array $payload = [])
    {
        return $this->call(
            'POST',
            $url,
            [], [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
    }

    public function test_a_qris_request_without_an_amount_replies_with_json(): void
    {
        $this->setUpPos(['qris_payload' => $this->staticQris()]);

        $response = $this->actingAs($this->cashier)
            ->bareFetch(route('pos.qris.dynamic'), []);

        $response->assertStatus(422);
        $this->assertJson($response->getContent(), 'Validation failure must be JSON, not an HTML redirect.');
        $response->assertJsonValidationErrors('amount');
    }

    public function test_a_qris_request_with_a_zero_amount_replies_with_json(): void
    {
        $this->setUpPos(['qris_payload' => $this->staticQris()]);

        $response = $this->actingAs($this->cashier)
            ->bareFetch(route('pos.qris.dynamic'), ['amount' => 0]);

        $response->assertStatus(422);
        $this->assertJson($response->getContent());
    }

    public function test_a_valid_qris_request_returns_the_svg(): void
    {
        $this->setUpPos(['qris_payload' => $this->staticQris()]);

        $this->actingAs($this->cashier)
            ->bareFetch(route('pos.qris.dynamic'), ['amount' => 33000])
            ->assertOk()
            ->assertJson(['success' => true, 'amount' => 33000])
            ->assertJsonStructure(['svg', 'merchant_name']);
    }

    public function test_a_qris_request_without_a_configured_code_explains_itself(): void
    {
        $this->setUpPos(); // no qris_payload

        $response = $this->actingAs($this->cashier)
            ->bareFetch(route('pos.qris.dynamic'), ['amount' => 33000]);

        $response->assertStatus(422);
        $this->assertStringContainsString('QRIS statis belum diatur', $response->json('message'));
    }

    public function test_an_invalid_cart_replies_with_json_rather_than_a_redirect(): void
    {
        $this->setUpPos();

        $response = $this->actingAs($this->cashier)->bareFetch(route('pos.store'), [
            'items'          => [['product_id' => 999999, 'qty' => 1]],
            'payment_method' => 'cash',
            'paid_amount'    => 0,
        ]);

        $response->assertStatus(422);
        $this->assertJson($response->getContent());
        $response->assertJsonValidationErrors('items.0.product_id');
    }

    public function test_holding_an_order_with_a_bad_payload_replies_with_json(): void
    {
        $this->setUpPos();

        $response = $this->actingAs($this->cashier)->bareFetch(route('pos.hold'), ['items' => []]);

        $response->assertStatus(422);
        $this->assertJson($response->getContent());
    }

    private function staticQris(): string
    {
        return '00020101021126570011ID.DANA.WWW011893600915303371673602090337167360303UMI51440014ID.CO.QRIS.WWW0215ID10265623758930303UMI5204737253033605802ID5906Conweb6014Kota Palembang61053016163045C12';
    }
}
