<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ChangePasswordMail extends Mailable implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    private $user;
    private $passwordType;
    private $password;
    private $meta_login;
    private $platform;

    public function __construct($user, $passwordType, $password, $meta_login, $platform)
    {
        $this->user = $user;
        $this->passwordType = $passwordType;
        $this->password = $password;
        $this->meta_login = $meta_login;
        $this->platform = $platform;

        // queue
        $this->queue = 'change_password_email';
    }

    public function build(): ChangePasswordMail
    {
        return $this->view('emails.change-password')
            ->with([
                'user' => $this->user,
                'passwordType' => $this->passwordType,
                'password' => $this->password,
                'meta_login' => $this->meta_login,
                'platform' => $this->platform,
            ])
            ->subject('Change Password Successful');
    }

    public function attachments(): array
    {
        return [];
    }
}
