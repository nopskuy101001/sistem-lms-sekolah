<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StudentRegistrationLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $verificationUrl) {}

    public function build(): static
    {
        return $this->subject('Verifikasi Gmail - Registrasi Siswa RuangKelas')
            ->view('emails.student-registration-verification');
    }
}
