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
    private $account_no;
    private $account_type;

    public function __construct($user, $meta_login, $amount, $created_at, $account_no, $account_type)
    {
        $this->user = $user;
        $this->meta_login = $meta_login;
        $this->amount = $amount;
        $this->created_at = $created_at;
        $this->account_no = $account_no;
        $this->account_type = $account_type;

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
                'account_no' => $this->account_no,
                'account_type' => $this->account_type,
            ])
            ->from('info@superforexs.com')
            ->subject('Withdrawal Request Confirmation');
    }

    public function attachments(): array
    {
        return [];
    }
}
