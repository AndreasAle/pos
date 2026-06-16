<?php

namespace App\Jobs;

use App\Mail\ShiftUnclosedAlert;
use App\Models\Business;
use App\Models\CashierShift;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendShiftUnclosedAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Business $business) {}

    public function handle(): void
    {
        // Cari shift yang sudah terbuka lebih dari 12 jam
        $unclosed = CashierShift::where('business_id', $this->business->id)
            ->where('status', 'open')
            ->where('opened_at', '<=', now()->subHours(12))
            ->with(['user', 'outlet'])
            ->get();

        if ($unclosed->isEmpty()) return;

        $recipients = User::forBusiness($this->business->id)
            ->whereIn('role', ['owner', 'admin'])
            ->where('is_active', true)
            ->whereNotNull('email')
            ->pluck('email');

        if ($recipients->isEmpty()) return;

        foreach ($unclosed as $shift) {
            // Cek sudah pernah kirim notif hari ini untuk shift ini
            $alreadySent = NotificationLog::where('business_id', $this->business->id)
                ->where('type', 'shift_unclosed')
                ->where('subject', 'like', '%shift_' . $shift->id . '%')
                ->whereDate('created_at', today())
                ->exists();

            if ($alreadySent) continue;

            foreach ($recipients as $email) {
                try {
                    Mail::to($email)->send(new ShiftUnclosedAlert($this->business, $shift));

                    NotificationLog::create([
                        'business_id' => $this->business->id,
                        'type'        => 'shift_unclosed',
                        'channel'     => 'email',
                        'recipient'   => $email,
                        'subject'     => 'shift_' . $shift->id . '_' . $shift->user->name,
                        'sent'        => true,
                        'sent_at'     => now(),
                    ]);
                } catch (\Throwable $e) {
                    NotificationLog::create([
                        'business_id' => $this->business->id,
                        'type'        => 'shift_unclosed',
                        'channel'     => 'email',
                        'recipient'   => $email,
                        'sent'        => false,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
