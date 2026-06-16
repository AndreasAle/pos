<?php

namespace App\Mail;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailySummary extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Business $business,
        public array $summary,
        public array $topProducts,
        public array $lowStock
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[' . $this->business->name . '] 📊 Ringkasan Penjualan ' . now()->format('d M Y'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.daily-summary');
    }
}
