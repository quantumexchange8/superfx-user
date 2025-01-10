<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TransferMoneySuccessMail extends Mailable implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    private $user;
    private $from_meta_login;
    private $to_meta_login;
    private $amount;

    public function __construct($user, $from_meta_login, $to_meta_login, $amount)
    {
        $this->user = $user;
        $this->from_meta_login = $from_meta_login;
        $this->to_meta_login = $to_meta_login;
        $this->amount = $amount;

        // queue
        $this->queue = 'transfer_money_success_email';
    }

    public function build(): TransferMoneySuccessMail
    {
        return $this->view('emails.transfer-money-success')
            ->with([
                'user' => $this->user,
                'from_meta_login' => $this->from_meta_login,
                'to_meta_login' => $this->to_meta_login,
                'amount' => $this->amount,
            ])
            ->from('info@superforexs.com')
            ->subject('Transfer Money Successfully');
    }

    public function attachments(): array
    {
        return [];
    }
}
