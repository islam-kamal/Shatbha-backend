<?php

namespace App\Mail;

use App\Models\Party;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartyCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Party $party,
        public string $email,
        public string $plainPassword,
        public string $roleLabel,
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
            text: 'emails.party_credentials_plain',
            html: 'emails.party_credentials',
        );
    }
}
