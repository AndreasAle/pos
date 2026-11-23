<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AiCreditEntry;
use App\Models\AiCreditTopup;
use App\Models\Business;
use App\Models\PlatformActivityLog;
use App\Services\AiCredits;
use App\Services\AiCreditsReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Kredit Yoka AI lintas toko: pantau saldo, pembelian, pemakaian, dan penyesuaian manual. */
class AiCreditController extends Controller
{
    public function index(Request $request, AiCreditsReport $report)
    {
        abort_unless(auth('platform')->user()->canViewFinancials(), 403);
        $filter = in_array($request->query('filter'), ['empty', 'low'], true) ? $request->query('filter') : null;

        return view('platform.ai-credits', [
            'stats' => $report->platform(),
            'rows' => $report->businesses(200, $filter),
            'filter' => $filter,
            'topups' => AiCreditTopup::with('business:id,name')->latest('id')->limit(20)->get(),
            'adjustments' => AiCreditEntry::with('business:id,name')->where('kind', 'adjust')->latest('id')->limit(10)->get(),
            'facts' => AiCreditsReport::facts(),
            'businesses' => Business::orderBy('name')->get(['id', 'name']),
            'canAdjust' => auth('platform')->user()->isSuperAdmin(),
        ]);
    }

    public function adjust(Request $request, AiCredits $credits): RedirectResponse
    {
        $admin = auth('platform')->user();
        abort_unless($admin->isSuperAdmin(), 403);
        $data = $request->validate([
            'business_id' => ['required', 'exists:businesses,id'],
            'credits' => ['required', 'integer', 'between:-10000,10000', 'not_in:0'],
            'reason' => ['required', 'string', 'min:5', 'max:200'],
        ]);
        $business = Business::findOrFail($data['business_id']);
        $applied = $credits->adjust($business, (int) $data['credits'], $data['reason'], $admin->id);

        PlatformActivityLog::record('ai_credits.adjusted',
            ($applied >= 0 ? 'Menambah ' : 'Mengurangi ').abs($applied).' kredit Yoka AI toko '.$business->name.': '.$data['reason'],
            null, $business, ['requested' => (int) $data['credits'], 'applied' => $applied]);

        return back()->with('success', 'Kredit '.$business->name.' '.($applied >= 0 ? 'ditambah ' : 'dikurangi ').abs($applied).'.');
    }
}
