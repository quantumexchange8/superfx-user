<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateAccountMail extends Mailable implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    private $user;
    private $mainPassword;
    private $investorPassword;
    private $meta_login;
    private $server;
    private $platform;

    public function __construct($user, $mainPassword, $investorPassword, $meta_login, $server, $platform)
    {
        $this->user = $user;
        $this->mainPassword = $mainPassword;
        $this->investorPassword = $investorPassword;
        $this->meta_login = $meta_login;
        $this->server = $server;
        $this->platform = $platform;

        // queue
        $this->queue = 'create_account_email';
    }

    public function build(): CreateAccountMail
    {
        return $this->view('emails.create-account')
            ->with([
                'user' => $this->user,
                'mainPassword' => $this->mainPassword,
                'investorPassword' => $this->investorPassword,
                'meta_login' => $this->meta_login,
                'server' => $this->server,
                'platform' => $this->platform,
            ])
            ->subject('Welcome to SuperFin');
    }

    public function attachments(): array
    {
        return [];
    }
}
