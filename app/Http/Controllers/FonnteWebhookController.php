<?php

namespace App\Http\Controllers;

use App\Jobs\DraftWhatsAppReply;
use App\Models\PlatformAiWhatsAppMessage;
use App\Services\PlatformWhatsApp;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Webhook pesan masuk dari device Fonnte resmi tim Yoka. Tanpa login; diverifikasi lewat secret
 * di URL (YOKA_FONNTE_WEBHOOK_SECRET). Pesan hanya disimpan lalu Adit membuat DRAF balasan;
 * tidak ada yang terkirim tanpa "Setujui & kirim".
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

        DraftWhatsAppReply::dispatchAfterResponse($message->id);

        return response()->json(['ok' => true]);
    }
}
