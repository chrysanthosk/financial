<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ConfirmNewEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $token) {}

    public function build()
    {
        return $this->subject('Confirm your new email address')
            ->view('emails.confirm-new-email')
            ->with([
                'user' => $this->user,
                'confirmUrl' => route('profile.email.confirm', $this->token),
            ]);
    }
}
