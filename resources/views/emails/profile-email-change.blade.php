<!doctype html>
<html lang="id">
<head><meta charset="UTF-8"><title>Konfirmasi email</title></head>
<body style="font-family:Arial,sans-serif;line-height:1.65;color:#1e293b">
    <h2>Verifikasi perubahan email LMS Sekolah</h2>
    <p>Halo {{ $recipientName }},</p>
    <p>Ada permintaan untuk menggunakan alamat <strong>{{ $newEmail }}</strong> pada akun LMS sekolah.</p>
    <p><a href="{{ $verificationUrl }}">Klik untuk konfirmasi email baru</a></p>
    <p>Tautan hanya berlaku 30 menit. Buka tautan setelah login ke akun LMS yang meminta perubahan ini.</p>
    <p>Jika Anda tidak meminta perubahan ini, abaikan pesan. Email yang lama tidak berubah.</p>
</body></html>
