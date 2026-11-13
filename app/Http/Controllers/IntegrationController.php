<?php

namespace App\Http\Controllers;

use App\Models\ChannelIntegration;
use App\Models\Outlet;
use Illuminate\Http\Request;

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
        ]);
    }

    public function connect(Request $request, string $provider)
    {
        abort_unless(isset(ChannelIntegration::PROVIDERS[$provider]), 404);
        $businessId = auth()->user()->business_id;

        $data = $request->validate([
            'outlet_id'     => 'required|integer',
            'merchant_id'   => 'required|string|max:100',
            'merchant_name' => 'nullable|string|max:150',
            'consent'       => 'accepted',
        ]);

        // Outlet harus milik bisnis ini.
        $outlet = Outlet::forBusiness($businessId)->findOrFail($data['outlet_id']);

        ChannelIntegration::updateOrCreate(
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

        return redirect()->route('integrations.index')
            ->with('success', ChannelIntegration::PROVIDERS[$provider]['name'] . ' untuk ' . $outlet->name . ' tercatat dan menunggu aktivasi.');
    }

    public function disconnect(ChannelIntegration $integration)
    {
        abort_unless($integration->business_id === auth()->user()->business_id, 404);
        $integration->delete();

        return redirect()->route('integrations.index')->with('success', 'Integrasi diputus.');
    }
}
