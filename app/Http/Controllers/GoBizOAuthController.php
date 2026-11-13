<?php

namespace App\Http\Controllers;

use App\Models\ChannelIntegration;
use App\Services\Channels\GoBizAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoBizOAuthController extends Controller
{
    public const SESSION_KEY = 'gobiz_oauth';

    /** Redirect URI yang didaftarkan ke Gojek. Merchant kembali ke sini setelah login GoBiz. */
    public function callback(Request $request, GoBizAuth $auth)
    {
        $pending = $request->session()->pull(self::SESSION_KEY);
        $back = redirect()->route('integrations.index');

        if (! $pending || ! is_string($request->query('state')) || ! hash_equals($pending['state'], $request->query('state'))) {
            return $back->withErrors(['gofood' => 'Sesi login GoBiz tidak valid atau sudah kedaluwarsa. Coba hubungkan lagi.']);
        }

        // Business dari sesi login, bukan dari parameter URL.
        $integration = ChannelIntegration::where('business_id', auth()->user()->business_id)
            ->where('provider', 'gofood')
            ->find($pending['integration_id']);

        if (! $integration) {
            return $back->withErrors(['gofood' => 'Data integrasi tidak ditemukan.']);
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            $integration->update(['last_error' => 'Login GoBiz dibatalkan atau ditolak.']);

            return $back->withErrors(['gofood' => 'Login GoBiz dibatalkan atau ditolak.']);
        }

        try {
            $token = $auth->exchange((string) $request->query('code'));
        } catch (Throwable $e) {
            Log::warning('GoBiz token exchange failed', ['integration' => $integration->id, 'error' => $e->getMessage()]);
            $integration->update(['last_error' => mb_substr($e->getMessage(), 0, 480)]);

            return $back->withErrors(['gofood' => 'GoBiz menolak penautan. Coba lagi, atau hubungi support Yoka.']);
        }

        $integration->update([
            'status'           => 'connected',
            'access_token'     => $token['access_token'],
            'refresh_token'    => $token['refresh_token'],
            'token_expires_at' => now()->addSeconds($token['expires_in']),
            'scopes'           => $token['scope'],
            'connected_at'     => now(),
            'last_error'       => null,
        ]);

        return $back->with('success', 'GoFood tersambung untuk '.$integration->outlet->name.'.');
    }
}
