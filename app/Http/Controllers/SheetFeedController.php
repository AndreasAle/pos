<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SheetFeed;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SheetFeedController extends Controller
{
    private const MAX_DAYS = 366;
    private const MAX_ROWS = 20000;

    /** Pemilik membuat atau membuat ulang tautan (tautan lama langsung mati). */
    public function generate()
    {
        SheetFeed::issueFor(auth()->user()->business_id);

        return redirect()->route('integrations.index')
            ->with('success', 'Tautan Google Sheets dibuat. Salin rumusnya ke spreadsheet Anda.');
    }

    public function revoke()
    {
        SheetFeed::where('business_id', auth()->user()->business_id)->delete();

        return redirect()->route('integrations.index')->with('success', 'Tautan Google Sheets dicabut.');
    }

    /** Endpoint publik bertoken, dipanggil Google Sheets. Hanya baca. */
    public function csv(Request $request, string $token, string $dataset): StreamedResponse
    {
        $feed = SheetFeed::findByToken($token);
        abort_unless($feed && in_array($dataset, ['penjualan', 'item'], true), 404);

        $days = max(1, min(self::MAX_DAYS, (int) $request->query('hari', 31)));
        $from = now()->subDays($days - 1)->startOfDay();

        $feed->forceFill(['last_accessed_at' => now()])->save();

        $businessId = $feed->business_id;

        return response()->stream(function () use ($dataset, $businessId, $from) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            $dataset === 'item'
                ? $this->writeItems($out, $businessId, $from)
                : $this->writeOrders($out, $businessId, $from);

            fclose($out);
        }, 200, [
            'Content-Type'           => 'text/csv; charset=UTF-8',
            'Cache-Control'          => 'no-store',
            'X-Robots-Tag'           => 'noindex, nofollow',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function writeOrders($out, int $businessId, $from): void
    {
        fputcsv($out, ['Tanggal', 'No Pesanan', 'Outlet', 'Tipe', 'Platform', 'Pembayaran',
            'Subtotal', 'Diskon', 'Pajak', 'Layanan', 'Total', 'Status']);

        Order::with('outlet:id,name')
            ->where('business_id', $businessId)
            ->whereIn('status', ['paid', 'refunded'])
            ->where('created_at', '>=', $from)
            ->orderBy('created_at')
            ->limit(self::MAX_ROWS)
            ->get()
            ->each(function (Order $o) use ($out) {
                fputcsv($out, [
                    ($o->ordered_at ?? $o->created_at)?->format('Y-m-d H:i:s'),
                    self::text($o->order_number),
                    self::text($o->outlet->name ?? ''),
                    self::text($o->order_type),
                    self::text($o->delivery_platform),
                    self::text($o->payment_method),
                    (float) $o->subtotal, (float) $o->discount_amount, (float) $o->tax_amount,
                    (float) $o->service_amount, (float) $o->grand_total,
                    $o->status,
                ]);
            });
    }

    private function writeItems($out, int $businessId, $from): void
    {
        fputcsv($out, ['Tanggal', 'No Pesanan', 'Outlet', 'Produk', 'Varian', 'Harga', 'Jumlah', 'Subtotal']);

        OrderItem::with('order.outlet:id,name')
            ->whereHas('order', fn ($q) => $q->where('business_id', $businessId)
                ->where('status', 'paid')->where('created_at', '>=', $from))
            ->orderBy('id')
            ->limit(self::MAX_ROWS)
            ->get()
            ->each(function (OrderItem $i) use ($out) {
                $o = $i->order;
                fputcsv($out, [
                    ($o->ordered_at ?? $o->created_at)?->format('Y-m-d H:i:s'),
                    self::text($o->order_number),
                    self::text($o->outlet->name ?? ''),
                    self::text($i->product_name),
                    self::text($i->variant_name),
                    (float) $i->price, (float) $i->qty, (float) $i->subtotal,
                ]);
            });
    }

    /** Cegah teks diperlakukan sebagai rumus bila CSV dibuka di spreadsheet. */
    private static function text(?string $v): string
    {
        $v = (string) $v;

        return $v !== '' && str_contains("=+-@\t\r", $v[0]) ? "'".$v : $v;
    }
}
