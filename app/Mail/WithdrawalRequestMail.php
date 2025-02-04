<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WithdrawalRequestMail extends Mailable implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    private $user;
    private $transaction;

    public function __construct($user, $transaction)
    {
        $this->user = $user;
        $this->transaction = $transaction;

        // queue
        $this->queue = 'withdrawal_request_email';
    }

    public function build(): WithdrawalRequestMail
    {
        return $this->view('emails.withdrawal-request')
            ->with([
                'user' => $this->user,
                'transaction' => $this->transaction,
            ])
            ->subject('Withdrawal Request Confirmation');
    }

    public function attachments(): array
    {
        return [];
    }
}
