<?php

namespace App\Http\Controllers;

use App\Models\ChannelIntegration;
use App\Models\ChannelOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Endpoint yang DIPANGGIL ShopeeFood (arah Shopee ke Yoka), sesuai
 * "Vendor API Requirements" di https://developer.shopeefood.co.id/vendor/api-docs
 *
 * URL: /api/shopeefood/{indicator}/...  (indicator = SHOPEEFOOD_INDICATOR)
 * Sebelum SHOPEEFOOD_INBOUND_* diisi semua endpoint membalas 404.
 *
 * Balasan sukses: HTTP 200 {"code":0,"message":"success"}. Kegagalan memakai status HTTP
 * dan pesan, karena Shopee mengandalkannya untuk menangani error.
 */
class ShopeeFoodInboundController extends Controller
{
    private const PROVIDER = 'shopeefood';

    public function token(Request $request, string $indicator): JsonResponse
    {
        if ($r = $this->guardIndicator($indicator)) {
            return $r;
        }

        $id = (string) config('channels.shopeefood.inbound_client_id');
        $secret = (string) config('channels.shopeefood.inbound_client_secret');

        $ok = hash_equals($id, (string) $request->input('client_id'))
            & hash_equals($secret, (string) $request->input('client_secret'));

        if (! $ok) {
            return $this->fail(401, 'invalid client credentials', 401);
        }

        $ttl = (int) config('channels.shopeefood.inbound_token_ttl', 3600);
        $token = Str::random(64);
        Cache::put($this->tokenKey($token), true, $ttl);

        return response()->json(['access_token' => $token, 'expires_in' => $ttl]);
    }

    /** POST .../orders: Submit Order To Vendor. Idempoten per id pesanan. */
    public function order(Request $request, string $indicator): JsonResponse
    {
        if ($r = $this->guard($request, $indicator)) {
            return $r;
        }

        $order = $request->input('data.order');
        if (! is_array($order) || blank($order['id'] ?? null) || blank($order['store_id'] ?? null)) {
            return $this->fail(400, 'order.id and order.store_id are required');
        }

        $integration = $this->integrationForStore((string) $order['store_id']);
        if (! $integration) {
            return $this->fail(404, 'store is not linked');
        }

        ChannelOrder::updateOrCreate(
            ['provider' => self::PROVIDER, 'external_order_id' => (string) $order['id']],
            [
                'business_id'            => $integration->business_id,
                'outlet_id'              => $integration->outlet_id,
                'channel_integration_id' => $integration->id,
                'short_code'             => $order['order_short_code'] ?? null,
                'status'                 => (string) ($order['status'] ?? 'APPROVED'),
                'payload'                => $order,
                'received_at'            => now(),
            ]
        );

        return $this->ok();
    }

    /** POST .../order/status: Update Order Status To Vendor. */
    public function orderStatus(Request $request, string $indicator): JsonResponse
    {
        if ($r = $this->guard($request, $indicator)) {
            return $r;
        }

        if (blank($request->input('id')) || blank($request->input('status')) || blank($request->input('store_id'))) {
            return $this->fail(400, 'id, store_id and status are required');
        }

        $integration = $this->integrationForStore((string) $request->input('store_id'));
        $order = $integration
            ? ChannelOrder::where('provider', self::PROVIDER)
                ->where('channel_integration_id', $integration->id)
                ->where('external_order_id', (string) $request->input('id'))
                ->first()
            : null;

        if (! $order) {
            return $this->fail(404, 'order not found');
        }

        $order->update(['status' => (string) $request->input('status')]);

        return $this->ok();
    }

    /** POST .../menu/notification/result: hasil sinkron menu (0 sukses, 1 gagal). */
    public function menuResult(Request $request, string $indicator): JsonResponse
    {
        if ($r = $this->guard($request, $indicator)) {
            return $r;
        }

        $integration = $this->integrationForStore((string) $request->input('store_id'));
        if (! $integration) {
            return $this->fail(404, 'store is not linked');
        }

        $failed = (int) $request->input('result', 0) !== 0;
        $integration->update(['last_error' => $failed ? mb_substr('Sinkron menu gagal: '.$request->input('msg'), 0, 480) : null]);

        return $this->ok();
    }

    // ---------------------------------------------------------------------

    private function enabled(): bool
    {
        return filled(config('channels.shopeefood.inbound_client_id'))
            && filled(config('channels.shopeefood.inbound_client_secret'))
            && filled(config('channels.shopeefood.indicator'));
    }

    private function guardIndicator(string $indicator): ?JsonResponse
    {
        if (! $this->enabled() || ! hash_equals((string) config('channels.shopeefood.indicator'), $indicator)) {
            return $this->fail(404, 'not found', 404);
        }

        return null;
    }

    private function guard(Request $request, string $indicator): ?JsonResponse
    {
        if ($r = $this->guardIndicator($indicator)) {
            return $r;
        }

        $bearer = $request->bearerToken();
        if (! $bearer || ! Cache::has($this->tokenKey($bearer))) {
            return $this->fail(401, 'invalid or expired token', 401);
        }

        return null;
    }

    /**
     * Hanya integrasi berstatus "connected" yang menerima pesanan. ID toko dipetakan ke
     * satu tenant; status connected diberikan setelah tim Yoka memverifikasi kepemilikan toko.
     */
    private function integrationForStore(string $storeId): ?ChannelIntegration
    {
        if ($storeId === '') {
            return null;
        }

        return ChannelIntegration::where('provider', self::PROVIDER)
            ->where('merchant_id', $storeId)
            ->where('status', 'connected')
            ->first();
    }

    private function tokenKey(string $token): string
    {
        return 'shopeefood:inbound:'.hash('sha256', $token);
    }

    private function ok(): JsonResponse
    {
        return response()->json(['code' => 0, 'message' => 'success']);
    }

    /** Status HTTP sama dengan kode, kecuali ditentukan lain. */
    private function fail(int $code, string $message, ?int $status = null): JsonResponse
    {
        return response()->json(['code' => $code, 'message' => $message], $status ?? $code);
    }
}
