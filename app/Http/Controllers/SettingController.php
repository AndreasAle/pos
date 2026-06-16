<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function business()
    {
        $business = auth()->user()->business;
        return view('settings.business', compact('business'));
    }

    public function updateBusiness(Request $request)
    {
        $request->validate([
            'name'    => 'required|string|max:255',
            'phone'   => 'nullable|string|max:20',
            'email'   => 'nullable|email',
            'address' => 'nullable|string|max:500',
            'logo'    => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
        ]);

        $business = auth()->user()->business;
        $data     = $request->only('name', 'phone', 'email', 'address');

        if ($request->hasFile('logo')) {
            if ($business->logo) \Storage::disk('public')->delete($business->logo);
            $data['logo'] = $request->file('logo')->store('business', 'public');
        }

        $business->update($data);
        return back()->with('success', 'Profil bisnis berhasil diperbarui.');
    }

    public function outlet()
    {
        $outlets = Outlet::forBusiness(auth()->user()->business_id)->get();
        return view('settings.outlet', compact('outlets'));
    }

    public function updateOutlet(Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|exists:outlets,id',
            'name'      => 'required|string|max:255',
            'phone'     => 'nullable|string|max:20',
            'address'   => 'nullable|string|max:500',
        ]);

        $outlet = Outlet::findOrFail($request->outlet_id);
        abort_if($outlet->business_id !== auth()->user()->business_id, 403);
        $outlet->update($request->only('name', 'phone', 'address', 'code'));

        return back()->with('success', 'Outlet diperbarui.');
    }

    public function receipt()
    {
        $business = auth()->user()->business;
        return view('settings.receipt', compact('business'));
    }

    public function updateReceipt(Request $request)
    {
        $business = auth()->user()->business;

        $settings = array_merge($business->settings ?? [], [
            'receipt_header'       => $request->receipt_header,
            'receipt_footer'       => $request->receipt_footer,
            'receipt_size'         => $request->receipt_size ?? '80mm',
            'enable_tax'           => $request->boolean('enable_tax'),
            'tax_percent'          => (float) ($request->tax_percent ?? 10),
            'enable_service'       => $request->boolean('enable_service'),
            'service_percent'      => (float) ($request->service_percent ?? 5),
            'allow_negative_stock' => $request->boolean('allow_negative_stock'),
            'points_per_rupiah'    => (float) ($request->points_per_rupiah ?? 0),
            'point_value_rupiah'   => (float) ($request->point_value_rupiah ?? 1),
            'enable_wa_receipt'    => $request->boolean('enable_wa_receipt'),
            'fonnte_token'         => $request->fonnte_token ?? '',
            'wa_default_phone'     => $request->wa_default_phone ?? '',
        ]);

        $business->update(['settings' => $settings]);
        return back()->with('success', 'Pengaturan struk disimpan.');
    }

    // ── QRIS ──────────────────────────────────────────────────────────────────

    public function qris()
    {
        $business = auth()->user()->business;
        return view('settings.qris', compact('business'));
    }

    public function updateQris(Request $request)
    {
        $request->validate([
            'qris_image'         => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
            'qris_merchant_name' => 'nullable|string|max:255',
            'qris_nmid'          => 'nullable|string|max:50',
        ]);

        $business = auth()->user()->business;
        $data     = $request->only('qris_merchant_name', 'qris_nmid');

        if ($request->hasFile('qris_image')) {
            if ($business->qris_image) {
                \Storage::disk('public')->delete($business->qris_image);
            }
            $data['qris_image'] = $request->file('qris_image')->store('qris', 'public');
        }

        $business->update($data);
        return back()->with('success', 'Pengaturan QRIS berhasil disimpan.');
    }
}
