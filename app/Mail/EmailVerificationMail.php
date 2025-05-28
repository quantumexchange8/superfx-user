<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    private $verificationUrl;

    /**
     * Create a new message instance.
     */
    public function __construct($verificationUrl)
    {
        $this->verificationUrl = $verificationUrl;
    }

    public function build(): EmailVerificationMail
    {
        return $this->view('emails.verification-email')
            ->with([
                'verificationUrl' => $this->verificationUrl,
            ])
            ->subject('Verify Email Address');
    }

    public function attachments(): array
    {
        return [];
    }
}
