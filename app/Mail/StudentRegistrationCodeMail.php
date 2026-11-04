<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StudentRegistrationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $verificationCode,
        public readonly int $expiryMinutes,
    ) {}

    public function build(): static
    {
        return $this->subject('Kode Verifikasi Akun Siswa - RuangKelas SMK')
            ->view('emails.student-registration-code');
    }
}
