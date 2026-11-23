<?php

namespace App\Http\Controllers;

use App\Models\AiCreditEntry;
use App\Models\AiCreditTopup;
use App\Services\AiCredits;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Saldo dan top-up kredit Tanya Yoka. */
class AiCreditController extends Controller
{
    public function __construct(private AiCredits $credits, private MidtransService $midtrans) {}

    public function index(Request $request)
    {
        abort_unless($this->credits->enabled(), 404);
        $business = $request->user()->business;

        $user = $request->user();
        $balance = $this->credits->balance($business);

        return view('ai-credits.index', [
            // Sidebar Yoka AI (percakapan, insight, menu akun) supaya halaman ini tetap di dalam Yoka AI.
            'conversations' => \App\Models\AssistantConversation::where('business_id', $user->business_id)->where('user_id', $user->id)->latest('updated_at')->limit(40)->get(),
            'activeConversation' => null,
            'businessInsights' => app(\App\Services\InsightService::class)->visible($business, 5),
            'intelligenceAgents' => config('pos.features.intelligence_agents') ? \App\Models\IntelligenceAgent::where('business_id', $business->id)->orderBy('agent_key')->get() : collect(),
            'credits' => $balance,
            'balance' => $balance,
            'packs' => config('yoka_ai_credits.packs'),
            'entries' => AiCreditEntry::where('business_id', $business->id)->latest('id')->limit(30)->get(),
            'topups' => AiCreditTopup::where('business_id', $business->id)->where('status', 'pending')->latest('id')->limit(5)->get(),
            'midtransConfigured' => $this->midtrans->isConfigured(),
            'isSandbox' => ! config('midtrans.is_production'),
        ]);
    }

    public function checkout(Request $request)
    {
        abort_unless($this->credits->enabled(), 404);
        $packs = config('yoka_ai_credits.packs');
        $data = $request->validate(['pack' => ['required', 'string', 'in:'.implode(',', array_keys($packs))]]);
        $pack = $packs[$data['pack']];

        if (! $this->midtrans->isConfigured()) {
            return redirect()->route('ai-credits.index')->with('error', 'Top-up belum tersedia karena pembayaran Midtrans belum aktif.');
        }

        $business = $request->user()->business;
        $orderId = 'AIC-'.$business->id.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(5));
        $topup = AiCreditTopup::create([
            'business_id' => $business->id, 'user_id' => $request->user()->id, 'pack' => $data['pack'],
            'credits' => (int) $pack['credits'], 'amount' => (int) $pack['price'], 'payment_token' => $orderId,
        ]);

        try {
            $result = $this->midtrans->createSnapToken($orderId, (int) $pack['price'], $business->name,
                $request->user()->email ?? 'owner@yokapos.com', 'Kredit Yoka AI '.$pack['credits'].' kredit', route('ai-credits.index'));
            if (empty($result['redirect_url'])) {
                throw new \RuntimeException('Midtrans tidak mengembalikan URL pembayaran.');
            }
            $topup->update(['payment_url' => $result['redirect_url']]);

            return redirect()->away($result['redirect_url']);
        } catch (\Throwable $e) {
            $topup->update(['status' => 'cancelled']);
            Log::warning('Midtrans AI credit checkout failed', ['business_id' => $business->id, 'topup_id' => $topup->id, 'error' => $e->getMessage()]);

            return redirect()->route('ai-credits.index')->with('error', 'Pembayaran belum berhasil dibuat. Coba lagi sebentar.');
        }
    }
}
