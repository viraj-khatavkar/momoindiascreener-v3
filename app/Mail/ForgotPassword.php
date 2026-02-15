<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ForgotPassword extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public $token, public User $user)
    {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset Password Notification',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.forgot_password',
            with: [
                'url' => url('/reset-password?token='.$this->token),
                'user' => $this->user,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
