<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\Outlet;
use App\Services\ShiftService;
use Illuminate\Http\Request;

class CashierShiftController extends Controller
{
    public function __construct(protected ShiftService $shiftService) {}

    public function index()
    {
        $user   = auth()->user();
        $shifts = CashierShift::forBusiness($user->business_id)
            ->with(['user', 'outlet'])
            ->when($user->outlet_id, fn($q) => $q->where('outlet_id', $user->outlet_id))
            ->latest()
            ->paginate(20);

        $activeShift = $user->activeShift()->first();

        return view('shifts.index', compact('shifts', 'activeShift'));
    }

    public function open()
    {
        $user = auth()->user();
        if ($user->activeShift()->exists()) {
            return redirect()->route('pos.index')
                ->with('info', 'Anda sudah memiliki shift aktif.');
        }

        $outlets = Outlet::forBusiness($user->business_id)->where('is_active', true)->get();
        return view('shifts.open', compact('outlets'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        if ($user->activeShift()->exists()) {
            return redirect()->route('pos.index');
        }

        $request->validate([
            'outlet_id'    => 'required|exists:outlets,id',
            'opening_cash' => 'required|numeric|min:0',
        ]);

        abort_if(
            !Outlet::where('id', $request->outlet_id)
                ->where('business_id', $user->business_id)
                ->exists(),
            403
        );

        $shift = $this->shiftService->openShift(
            $user,
            $request->outlet_id,
            $request->opening_cash
        );

        $user->update(['outlet_id' => $shift->outlet_id]);

        return redirect()->route('pos.index')
            ->with('success', 'Shift dibuka. Selamat bekerja!');
    }

    public function show(CashierShift $shift)
    {
        abort_if($shift->business_id !== auth()->user()->business_id, 403);
        $shift->load(['user', 'outlet', 'orders.items']);

        $totalSales   = $shift->orders->where('status', 'paid')->sum('grand_total');
        $totalCash    = $shift->orders->where('status', 'paid')->where('payment_method', 'cash')->sum('grand_total');
        $totalOrders  = $shift->orders->where('status', 'paid')->count();

        return view('shifts.show', compact('shift', 'totalSales', 'totalCash', 'totalOrders'));
    }

    public function close(CashierShift $shift)
    {
        abort_if($shift->business_id !== auth()->user()->business_id, 403);
        abort_if($shift->user_id !== auth()->id(), 403, 'Hanya pemilik shift yang dapat menutup shift.');
        abort_if($shift->status !== 'open', 422, 'Shift sudah ditutup.');

        $expectedCash = $shift->opening_cash + $shift->total_cash_sales;
        return view('shifts.close', compact('shift', 'expectedCash'));
    }

    public function closeStore(Request $request, CashierShift $shift)
    {
        abort_if($shift->business_id !== auth()->user()->business_id, 403);
        abort_if($shift->user_id !== auth()->id(), 403);
        abort_if($shift->status !== 'open', 422);

        $request->validate([
            'closing_cash_actual' => 'required|numeric|min:0',
            'notes'               => 'nullable|string|max:500',
        ]);

        $this->shiftService->closeShift($shift, $request->closing_cash_actual, $request->notes);

        return redirect()->route('shifts.show', $shift)
            ->with('success', 'Shift berhasil ditutup.');
    }
}
