<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DepositSuccessMail extends Mailable implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    private $user;
    private $meta_login;
    private $amount;
    private $created_at;

    public function __construct($user, $meta_login, $amount, $created_at)
    {
        $this->user = $user;
        $this->meta_login = $meta_login;
        $this->amount = $amount;
        $this->created_at = $created_at;

        // queue
        $this->queue = 'deposit_success_email';
    }

    public function build(): DepositSuccessMail
    {
        return $this->view('emails.deposit-success')
            ->with([
                'user' => $this->user,
                'meta_login' => $this->meta_login,
                'amount' => $this->amount,
                'created_at' => $this->created_at,
            ])
            ->from('info@superforex.global')
            ->subject('Deposit Successful');
    }

    public function attachments(): array
    {
        return [];
    }
}
