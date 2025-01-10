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
    private $meta_login;
    private $amount;
    private $created_at;
    private $wallet_address;

    public function __construct($user, $meta_login, $amount, $created_at, $wallet_address)
    {
        $this->user = $user;
        $this->meta_login = $meta_login;
        $this->amount = $amount;
        $this->created_at = $created_at;
        $this->wallet_address = $wallet_address;

        // queue
        $this->queue = 'withdrawal_request_email';
    }

    public function build(): WithdrawalRequestMail
    {
        return $this->view('emails.withdrawal-request')
            ->with([
                'user' => $this->user,
                'meta_login' => $this->meta_login,
                'amount' => $this->amount,
                'created_at' => $this->created_at,
                'wallet_address' => $this->wallet_address,
            ])
            ->from('info@superforexs.com')
            ->subject('Withdrawal Request Confirmation');
    }

    public function attachments(): array
    {
        return [];
    }
}
