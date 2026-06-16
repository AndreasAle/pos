<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WithdrawalRequest;
use App\Services\BalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WithdrawalAdminController extends Controller
{
    public function __construct(protected BalanceService $balanceService) {}

    public function index(Request $request)
    {
        $query = WithdrawalRequest::with('business')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest();

        $withdrawals = $query->paginate(20)->withQueryString();
        $stats = [
            'pending'    => WithdrawalRequest::where('status', 'pending')->count(),
            'processing' => WithdrawalRequest::where('status', 'processing')->count(),
            'total_pending_amount' => WithdrawalRequest::where('status', 'pending')->sum('amount'),
        ];

        return view('admin.withdrawals.index', compact('withdrawals', 'stats'));
    }

    public function approve(WithdrawalRequest $wd)
    {
        abort_if(!$wd->isPending(), 422);

        $wd->update(['status' => 'processing']);

        return back()->with('success', 'WD #' . $wd->id . ' sedang diproses.');
    }

    public function complete(Request $request, WithdrawalRequest $wd)
    {
        $request->validate(['admin_notes' => 'nullable|string|max:500']);

        abort_if($wd->status !== 'processing', 422);

        try {
            $this->balanceService->debitForWithdrawal($wd);

            $wd->update([
                'status'       => 'completed',
                'admin_notes'  => $request->admin_notes,
                'processed_at' => now(),
            ]);

            Log::info('Withdrawal completed', ['wd_id' => $wd->id, 'business_id' => $wd->business_id, 'amount' => $wd->amount]);

            return back()->with('success', 'WD #' . $wd->id . ' selesai. Saldo ' . $wd->business->name . ' dikurangi Rp ' . number_format($wd->amount, 0, ',', '.'));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function reject(Request $request, WithdrawalRequest $wd)
    {
        $request->validate(['admin_notes' => 'required|string|max:500']);

        abort_if(!in_array($wd->status, ['pending', 'processing']), 422);

        $wd->update([
            'status'       => 'rejected',
            'admin_notes'  => $request->admin_notes,
            'processed_at' => now(),
        ]);

        return back()->with('success', 'WD #' . $wd->id . ' ditolak.');
    }
}
