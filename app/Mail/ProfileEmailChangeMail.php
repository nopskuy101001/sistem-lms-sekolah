<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProfileEmailChangeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $newEmail,
        public string $verificationUrl,
    ) {}

    public function build(): self
    {
        return $this->subject('Konfirmasi Email Baru - LMS Sekolah')
            ->view('emails.profile-email-change');
    }
}
