<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WithdrawalRequestUsdtMail extends Mailable implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    private $user;
    private $meta_login;
    private $amount;
    private $created_at;
    private $wallet_address;
    private $hashed_token;
    private $transaction_number;

    public function __construct($user, $meta_login, $amount, $created_at, $wallet_address, $transaction_number, $hashed_token)
    {
        $this->user = $user;
        $this->meta_login = $meta_login;
        $this->amount = $amount;
        $this->created_at = $created_at;
        $this->wallet_address = $wallet_address;
        $this->transaction_number = $transaction_number;
        $this->hashed_token = $hashed_token;

        // queue
        $this->queue = 'withdrawal_request_usdt_email';
    }

    public function build(): WithdrawalRequestUsdtMail
    {
        return $this->view('emails.withdrawal-request-usdt')
            ->with([
                'user' => $this->user,
                'meta_login' => $this->meta_login,
                'amount' => $this->amount,
                'created_at' => $this->created_at,
                'wallet_address' => $this->wallet_address,
                'transaction_number' => $this->transaction_number,
                'hashed_token' => $this->hashed_token,
            ])
            ->subject('Withdrawal Request Confirmation');
    }

    public function attachments(): array
    {
        return [];
    }
}
