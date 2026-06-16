<?php

namespace App\Jobs;

use App\Mail\DailySummary;
use App\Models\Business;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendDailySummary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Business $business) {}

    public function handle(DashboardService $dashboard): void
    {
        $summary     = $dashboard->getSummary($this->business);
        $topProducts = $dashboard->getTopProducts($this->business, null, 5);
        $lowStock    = $dashboard->getLowStockIngredients($this->business);

        // Hanya kirim jika ada transaksi hari ini
        if ($summary['todayOrders'] === 0) return;

        $recipients = User::forBusiness($this->business->id)
            ->whereIn('role', ['owner'])
            ->where('is_active', true)
            ->whereNotNull('email')
            ->pluck('email');

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(new DailySummary(
                    $this->business,
                    $summary,
                    $topProducts,
                    $lowStock
                ));

                NotificationLog::create([
                    'business_id' => $this->business->id,
                    'type'        => 'daily_summary',
                    'channel'     => 'email',
                    'recipient'   => $email,
                    'subject'     => 'Ringkasan Penjualan ' . now()->format('d M Y'),
                    'sent'        => true,
                    'sent_at'     => now(),
                ]);
            } catch (\Throwable $e) {
                NotificationLog::create([
                    'business_id' => $this->business->id,
                    'type'        => 'daily_summary',
                    'channel'     => 'email',
                    'recipient'   => $email,
                    'sent'        => false,
                    'error'       => $e->getMessage(),
                ]);
            }
        }
    }
}
