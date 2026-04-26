<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailChangeRequestedNotice extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $newEmail) {}

    public function build()
    {
        $appName = config('app.name', 'Financial');

        return $this->subject("[{$appName}] Email change requested on your account")
            ->view('emails.email-change-requested-notice')
            ->with([
                'user' => $this->user,
                'newEmail' => $this->newEmail,
                'appName' => $appName,
            ]);
    }
}
