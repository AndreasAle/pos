<?php

namespace App\Mail;

use App\Models\Business;
use App\Models\CashierShift;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ShiftUnclosedAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Business $business,
        public CashierShift $shift
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[' . $this->business->name . '] ⚠ Shift Kasir Belum Ditutup — ' . $this->shift->user->name,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.shift-unclosed');
    }
}
