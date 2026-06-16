<?php

namespace App\Mail;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LowStockAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Business $business,
        public array $ingredients
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[' . $this->business->name . '] ⚠ Peringatan Stok Menipis - ' . count($this->ingredients) . ' Bahan',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.low-stock',
        );
    }
}
