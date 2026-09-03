<?php

namespace App\Mail;

use App\Models\ClientAccount;
use App\Models\Party;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Party $party,
        public ClientAccount $account,
        public string $plainPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'بيانات الدخول — شطبها',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.client_credentials_plain',
            html: 'emails.client_credentials',
        );
    }
}
