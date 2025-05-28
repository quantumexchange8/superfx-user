<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    private $resetUrl;

    /**
     * Create a new message instance.
     */
    public function __construct($resetUrl)
    {
        $this->resetUrl = $resetUrl;
    }

    public function build(): ResetPasswordMail
    {
        return $this->view('emails.reset-password-mail')
            ->with([
                'resetUrl' => $this->resetUrl,
            ])
            ->subject('Reset Password Notification');
    }

    public function attachments(): array
    {
        return [];
    }
}
