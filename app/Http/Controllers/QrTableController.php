<?php

namespace App\Http\Controllers;

use App\Models\DiningArea;
use App\Models\DiningTable;
use App\Models\Outlet;
use App\Models\TableSession;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class QrTableController extends Controller
{
    public function index(Request $request)
    {
        $businessId = auth()->user()->business_id;
        $outlets = Outlet::forBusiness($businessId)->where('is_active', true)->orderBy('name')->get();
        $outletId = (int) ($request->integer('outlet') ?: auth()->user()->outlet_id ?: $outlets->first()?->id);
        $areas = DiningArea::forBusiness($businessId)->where('outlet_id', $outletId)->withCount('tables')->orderBy('sort_order')->get();
        $tables = DiningTable::forBusiness($businessId)->where('outlet_id', $outletId)->with(['area', 'openSession.orders'])->orderBy('name')->get();

        return view('qr-tables.index', compact('outlets', 'outletId', 'areas', 'tables'));
    }

    public function storeArea(Request $request)
    {
        $data = $request->validate(['outlet_id' => 'required|integer', 'name' => 'required|string|max:80']);
        $outlet = Outlet::forBusiness(auth()->user()->business_id)->findOrFail($data['outlet_id']);
        DiningArea::firstOrCreate(['business_id' => auth()->user()->business_id, 'outlet_id' => $outlet->id, 'name' => trim($data['name'])]);

        return back()->with('success', 'Area meja berhasil ditambahkan.');
    }

    public function storeTable(Request $request)
    {
        $data = $request->validate(['outlet_id' => 'required|integer', 'dining_area_id' => 'nullable|integer', 'name' => 'required|string|max:40']);
        $outlet = Outlet::forBusiness(auth()->user()->business_id)->findOrFail($data['outlet_id']);
        if (! empty($data['dining_area_id'])) {
            DiningArea::forBusiness(auth()->user()->business_id)->where('outlet_id', $outlet->id)->findOrFail($data['dining_area_id']);
        }
        DiningTable::create(['business_id' => auth()->user()->business_id, 'outlet_id' => $outlet->id, 'dining_area_id' => $data['dining_area_id'] ?: null, 'name' => trim($data['name']), 'public_token' => Str::random(48)]);

        return back()->with('success', 'Meja dan QR publik berhasil dibuat.');
    }

    public function rotate(DiningTable $table)
    {
        $this->own($table);
        $table->update(['public_token' => Str::random(48)]);

        return back()->with('success', 'Tautan QR diperbarui. QR lama sudah tidak berlaku.');
    }

    public function toggle(DiningTable $table)
    {
        $this->own($table);
        $table->update(['is_active' => ! $table->is_active]);

        return back()->with('success', 'Status meja diperbarui.');
    }

    public function qr(DiningTable $table)
    {
        $this->own($table);
        $result = (new Builder(writer: new SvgWriter, data: route('self-order.menu', $table->public_token), errorCorrectionLevel: ErrorCorrectionLevel::Medium, size: 360, margin: 12))->build();

        return response($result->getString())->header('Content-Type', 'image/svg+xml')->header('Cache-Control', 'no-store');
    }

    public function print(DiningTable $table)
    {
        $this->own($table);

        return view('qr-tables.print', compact('table'));
    }

    public function closeSession(TableSession $session)
    {
        abort_if($session->business_id !== auth()->user()->business_id, 403);
        $hasUnpaid = $session->orders()->where('acceptance_status', 'accepted')->where('payment_status', '!=', 'paid')->exists();
        abort_if($hasUnpaid, 422, 'Selesaikan pembayaran sebelum menutup sesi meja.');
        $session->update(['status' => 'closed', 'closed_at' => now()]);
        $session->table()->update(['status' => 'available']);

        return back()->with('success', 'Sesi ditutup. Meja kembali tersedia.');
    }

    private function own(DiningTable $table): void
    {
        abort_if($table->business_id !== auth()->user()->business_id, 403);
    }
}
