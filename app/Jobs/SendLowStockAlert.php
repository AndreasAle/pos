<?php

namespace App\Jobs;

use App\Mail\LowStockAlert;
use App\Models\Business;
use App\Models\Ingredient;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendLowStockAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Business $business) {}

    public function handle(): void
    {
        $lowStockItems = Ingredient::forBusiness($this->business->id)
            ->lowStock()
            ->where('is_active', true)
            ->get();

        if ($lowStockItems->isEmpty()) return;

        // Kirim ke owner & admin aktif yang punya email
        $recipients = User::forBusiness($this->business->id)
            ->whereIn('role', ['owner', 'admin'])
            ->where('is_active', true)
            ->whereNotNull('email')
            ->pluck('email');

        if ($recipients->isEmpty()) return;

        $ingredients = $lowStockItems->toArray();

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(new LowStockAlert($this->business, $ingredients));

                NotificationLog::create([
                    'business_id' => $this->business->id,
                    'type'        => 'low_stock',
                    'channel'     => 'email',
                    'recipient'   => $email,
                    'subject'     => 'Peringatan Stok Menipis - ' . count($ingredients) . ' bahan',
                    'sent'        => true,
                    'sent_at'     => now(),
                ]);
            } catch (\Throwable $e) {
                NotificationLog::create([
                    'business_id' => $this->business->id,
                    'type'        => 'low_stock',
                    'channel'     => 'email',
                    'recipient'   => $email,
                    'sent'        => false,
                    'error'       => $e->getMessage(),
                ]);
            }
        }
    }
}
