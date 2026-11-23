<?php

namespace App\Http\Controllers;

use App\Jobs\DraftWhatsAppReply;
use App\Models\PlatformAiWhatsAppMessage;
use App\Services\PlatformWhatsApp;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Webhook pesan masuk ke nomor WA resmi Yoka, dari Fonnte atau dari Yoka WA Gateway sendiri
 * (wa-gateway/, format field sama). Tanpa login; diverifikasi lewat secret di URL
 * (YOKA_FONNTE_WEBHOOK_SECRET). Adit membalas sendiri dengan batas keras di DraftWhatsAppReply
 * (lihat AGENTS.md), dan gateway menambah rem anti-spam sendiri.
 */
class FonnteWebhookController extends Controller
{
    public function __invoke(Request $request, string $secret)
    {
        $expected = (string) config('services.yoka_fonnte.webhook_secret');
        abort_unless($expected !== '' && hash_equals($expected, $secret), 404);

        // Fonnte mengetes URL dengan GET; cukup jawab OK.
        if ($request->isMethod('get')) {
            return response()->json(['ok' => true]);
        }

        $sender = (string) $request->input('sender', '');
        $text = trim((string) ($request->input('message') ?? $request->input('text') ?? ''));
        $phone = PlatformWhatsApp::normalize($sender);
        $isGroup = str_contains($sender, '@g.us') || filled($request->input('member'));
        $ownDevice = $phone && $phone === PlatformWhatsApp::normalize((string) $request->input('device', ''));

        if (! $phone || $isGroup || $ownDevice || $text === '') {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        // Fonnte bisa mengirim ulang webhook yang sama: dedup pada pengirim + isi + menit.
        $fingerprint = hash('sha256', $phone.'|'.($request->input('inboxid') ?: now()->format('YmdHi')).'|'.hash('sha256', $text));
        try {
            $message = PlatformAiWhatsAppMessage::create([
                'direction' => 'inbound', 'phone' => $phone,
                'name' => Str::limit((string) ($request->input('name') ?? $request->input('pushname') ?? ''), 110, '') ?: null,
                'body' => Str::limit($text, 4000, ''), 'fingerprint' => $fingerprint,
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        // Gabung pesan beruntun (opsional): job ditunda lewat queue; pesan berhenti/minta orang tetap diproses segera.
        $window = min(15, max(0, (int) config('yoka_sales.bd21.coalesce_seconds', 0)));
        $urgent = (bool) preg_match(\App\Services\PlatformAgents\ProspectDesk::STOP_PATTERN, $text)
            || in_array(\App\Services\PlatformAgents\CustomerService\CustomerSignals::HUMAN, \App\Services\PlatformAgents\CustomerService\CustomerSignals::detect($text), true);
        $prospectDelay = $urgent ? 0 : self::prospectDelay($phone);
        if ($prospectDelay > 0 || ($window > 0 && ! $urgent)) {
            DraftWhatsAppReply::dispatch($message->id)->delay(now()->addSeconds(max($prospectDelay, $window)));
        } else {
            DraftWhatsAppReply::dispatchAfterResponse($message->id);
        }

        return response()->json(['ok' => true]);
    }

    /** Prospek (bukan pelanggan) dibalas dengan jeda acak; pelanggan CS tetap cepat. */
    private static function prospectDelay(string $phone): int
    {
        $min = max(0, (int) config('yoka_sales.bd21.prospect_reply_delay_min', 0));
        $max = max($min, (int) config('yoka_sales.bd21.prospect_reply_delay_max', 0));
        if ($max === 0) {
            return 0;
        }
        $p = \App\Models\PlatformAiProspect::where('phone_normalized', $phone)->first();
        if (! $p || $p->opted_out_at || in_array($p->status, ['trial', 'bayar'], true)) {
            return 0;
        }

        return random_int($min, $max);
    }
}
