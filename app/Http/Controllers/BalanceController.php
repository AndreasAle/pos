<?php

namespace App\Http\Controllers;

use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;

class BalanceController extends Controller
{
    public function index()
    {
        $business     = auth()->user()->business;
        $transactions = $business->balanceTransactions()->latest()->paginate(20);
        $pendingWd    = $business->withdrawalRequests()->where('status', 'pending')->sum('amount');

        return view('balance.index', compact('business', 'transactions', 'pendingWd'));
    }

    public function requestWithdrawal(Request $request)
    {
        $business = auth()->user()->business;

        $data = $request->validate([
            'amount'         => 'required|numeric|min:50000',
            'bank_name'      => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'account_name'   => 'required|string|max:100',
            'notes'          => 'nullable|string|max:500',
        ]);

        $pending = $business->withdrawalRequests()->where('status', 'pending')->sum('amount');
        $available = $business->balance - $pending;

        if ($data['amount'] > $available) {
            return back()->withErrors(['amount' => 'Jumlah melebihi saldo tersedia (Rp ' . number_format($available, 0, ',', '.') . ')']);
        }

        WithdrawalRequest::create([
            'business_id'    => $business->id,
            'amount'         => $data['amount'],
            'bank_name'      => $data['bank_name'],
            'account_number' => $data['account_number'],
            'account_name'   => $data['account_name'],
            'notes'          => $data['notes'] ?? null,
            'status'         => 'pending',
        ]);

        return back()->with('success', 'Permintaan penarikan sebesar Rp ' . number_format($data['amount'], 0, ',', '.') . ' berhasil diajukan. Akan diproses dalam 1-2 hari kerja.');
    }

    public function cancelWithdrawal(WithdrawalRequest $wd)
    {
        $business = auth()->user()->business;
        abort_if($wd->business_id !== $business->id, 403);
        abort_if(!$wd->isPending(), 422, 'Hanya permintaan pending yang bisa dibatalkan.');

        $wd->update(['status' => 'rejected', 'admin_notes' => 'Dibatalkan oleh merchant']);

        return back()->with('success', 'Permintaan penarikan berhasil dibatalkan.');
    }
}
