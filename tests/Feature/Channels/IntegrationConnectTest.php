<?php

namespace Tests\Feature\Channels;

use App\Models\ChannelIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

class IntegrationConnectTest extends TestCase
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

    private function gobiz(): void
    {
        config([
            'channels.gofood.client_id'     => 'gid',
            'channels.gofood.client_secret' => 'gsecret',
            'channels.gofood.redirect_uri'  => 'https://yokapos.test/integrasi/gofood/callback',
            'channels.gofood.oauth_url'     => 'https://goauth.test',
        ]);
    }

    private function connect(string $provider, array $extra = [])
    {
        return $this->actingAs($this->owner)->post(route('integrations.connect', $provider), array_merge([
            'outlet_id' => $this->outlet->id, 'merchant_id' => 'G951233971', 'consent' => '1',
        ], $extra));
    }

    public function test_without_credentials_the_link_is_only_recorded_as_pending(): void
    {
        $this->connect('gofood')->assertRedirect(route('integrations.index'));

        $row = ChannelIntegration::first();
        $this->assertSame('pending', $row->status);
        $this->assertNull($row->access_token);
    }

    public function test_the_same_store_id_cannot_be_registered_by_another_business(): void
    {
        $this->connect('shopeefood', ['merchant_id' => '555'])->assertRedirect(route('integrations.index'));

        $other = $this->foreignUser();
        $this->actingAs($other)->post(route('integrations.connect', 'shopeefood'), [
            'outlet_id' => $other->outlet_id, 'merchant_id' => '555', 'consent' => '1',
        ])->assertSessionHasErrors('merchant_id');

        $this->assertSame(1, ChannelIntegration::count());
    }

    public function test_the_same_outlet_can_resubmit_its_own_id(): void
    {
        $this->connect('shopeefood', ['merchant_id' => '555'])->assertSessionHasNoErrors();
        $this->connect('shopeefood', ['merchant_id' => '555', 'merchant_name' => 'Nama baru'])->assertSessionHasNoErrors();

        $this->assertSame(1, ChannelIntegration::count());
    }

    public function test_an_outlet_from_another_business_is_rejected(): void
    {
        $other = $this->foreignUser();

        $this->connect('gofood', ['outlet_id' => $other->outlet_id])->assertNotFound();
    }

    public function test_with_credentials_gofood_redirects_to_gobiz_login(): void
    {
        $this->gobiz();

        $res = $this->connect('gofood');
        $res->assertRedirect();

        $url = $res->headers->get('Location');
        $this->assertStringStartsWith('https://goauth.test/oauth2/auth?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $this->assertSame('gid', $q['client_id']);
        $this->assertSame('code', $q['response_type']);
        $this->assertSame('merchant', $q['user_type']);
        $this->assertSame('https://yokapos.test/integrasi/gofood/callback', $q['redirect_uri']);
        $this->assertStringContainsString('openid', $q['scope']);
        $this->assertSame($q['state'], session('gobiz_oauth.state'));
    }

    public function test_callback_exchanges_the_code_and_stores_encrypted_tokens(): void
    {
        $this->gobiz();
        Http::fake(['goauth.test/oauth2/token' => Http::response([
            'access_token' => 'AT-123', 'refresh_token' => 'RT-456', 'expires_in' => 3600, 'scope' => 'openid offline',
        ])]);
        $this->connect('gofood');
        $state = session('gobiz_oauth.state');

        $this->actingAs($this->owner)->get(route('integrations.gofood.callback', ['code' => 'abc', 'state' => $state]))
            ->assertRedirect(route('integrations.index'))->assertSessionHas('success');

        $row = ChannelIntegration::first();
        $this->assertSame('connected', $row->status);
        $this->assertSame('AT-123', $row->access_token);
        $this->assertNotNull($row->connected_at);

        // Di database harus berupa ciphertext, bukan token mentah.
        $raw = DB::table('channel_integrations')->first();
        $this->assertStringNotContainsString('AT-123', $raw->access_token);
        $this->assertStringNotContainsString('RT-456', $raw->refresh_token);
        $this->assertArrayNotHasKey('access_token', $row->toArray());

        Http::assertSent(fn ($r) => $r['grant_type'] === 'authorization_code' && $r['code'] === 'abc'
            && $r['client_id'] === 'gid' && $r['client_secret'] === 'gsecret');
    }

    public function test_callback_with_a_wrong_state_does_not_call_gobiz_or_connect(): void
    {
        $this->gobiz();
        Http::fake();
        $this->connect('gofood');

        $this->actingAs($this->owner)->get(route('integrations.gofood.callback', ['code' => 'abc', 'state' => 'forged']))
            ->assertSessionHasErrors('gofood');

        Http::assertNothingSent();
        $this->assertSame('pending', ChannelIntegration::first()->status);
    }

    public function test_callback_failure_keeps_the_row_pending_and_records_the_error(): void
    {
        $this->gobiz();
        Http::fake(['goauth.test/oauth2/token' => Http::response(['error' => 'invalid_grant'], 400)]);
        $this->connect('gofood');

        $this->actingAs($this->owner)->get(route('integrations.gofood.callback', ['code' => 'abc', 'state' => session('gobiz_oauth.state')]))
            ->assertSessionHasErrors('gofood');

        $row = ChannelIntegration::first();
        $this->assertSame('pending', $row->status);
        $this->assertNull($row->access_token);
        $this->assertNotNull($row->last_error);
    }

    public function test_a_user_of_another_business_cannot_disconnect_an_integration(): void
    {
        $this->connect('gofood');
        $other = $this->foreignUser();

        $this->actingAs($other)->delete(route('integrations.disconnect', ChannelIntegration::first()))->assertNotFound();
        $this->assertSame(1, ChannelIntegration::count());
    }

    public function test_cashiers_cannot_open_the_page(): void
    {
        $this->actingAs($this->cashier)->get(route('integrations.index'))->assertForbidden();
    }
}
