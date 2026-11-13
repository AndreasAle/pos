<?php

namespace App\Http\Controllers;

use App\Models\ChannelIntegration;
use App\Models\Outlet;
use App\Services\Channels\GoBizAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class IntegrationController extends Controller
{
    public function index()
    {
        $businessId = auth()->user()->business_id;

        $outlets = Outlet::forBusiness($businessId)->orderBy('name')->get(['id', 'name']);
        $links = ChannelIntegration::with('outlet:id,name')
            ->where('business_id', $businessId)
            ->orderBy('created_at')
            ->get()
            ->groupBy('provider');

        return view('integrations.index', [
            'providers' => ChannelIntegration::PROVIDERS,
            'outlets'   => $outlets,
            'links'     => $links,
            'gobizLive' => app(GoBizAuth::class)->enabled(),
            'sheetFeed' => \App\Models\SheetFeed::where('business_id', $businessId)->first(),
        ]);
    }

    public function connect(Request $request, string $provider, GoBizAuth $gobiz)
    {
        abort_unless(isset(ChannelIntegration::PROVIDERS[$provider]), 404);
        $businessId = auth()->user()->business_id;

        $data = $request->validate([
            'outlet_id'     => 'required|integer',
            // Satu ID hanya boleh dipakai satu outlet per platform. Tanpa batas ini, tenant
            // lain bisa mendaftarkan ID toko orang lain dan menerima pesanannya.
            'merchant_id'   => [
                'required', 'string', 'max:100',
                Rule::unique('channel_integrations', 'merchant_id')
                    ->where('provider', $provider)
                    ->where(fn ($q) => $q->where('outlet_id', '!=', (int) $request->input('outlet_id'))),
            ],
            'merchant_name' => 'nullable|string|max:150',
            'consent'       => 'accepted',
        ], [
            'merchant_id.unique' => 'ID ini sudah terdaftar di outlet lain. Hubungi support Yoka bila ini toko Anda.',
        ]);

        // Outlet harus milik bisnis ini.
        $outlet = Outlet::forBusiness($businessId)->findOrFail($data['outlet_id']);

        $integration = ChannelIntegration::updateOrCreate(
            ['outlet_id' => $outlet->id, 'provider' => $provider],
            [
                'business_id'   => $businessId,
                'merchant_id'   => $data['merchant_id'],
                'merchant_name' => $data['merchant_name'] ?? null,
                'status'        => 'pending',
                'consented_at'  => now(),
                'consented_by'  => auth()->id(),
            ]
        );

        // GoFood dengan kredensial resmi: lanjut ke login GoBiz (nomor HP + OTP).
        if ($provider === 'gofood' && $gobiz->enabled()) {
            $state = Str::random(40);
            $request->session()->put(GoBizOAuthController::SESSION_KEY, [
                'state'          => $state,
                'integration_id' => $integration->id,
            ]);

            return redirect()->away($gobiz->authorizeUrl($state));
        }

        return redirect()->route('integrations.index')
            ->with('success', ChannelIntegration::PROVIDERS[$provider]['name'].' untuk '.$outlet->name.' tercatat dan menunggu aktivasi.');
    }

    public function disconnect(ChannelIntegration $integration)
    {
        abort_unless($integration->business_id === auth()->user()->business_id, 404);
        $integration->delete();

        return redirect()->route('integrations.index')->with('success', 'Integrasi diputus.');
    }
}
