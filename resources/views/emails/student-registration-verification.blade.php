<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><title>Verifikasi Registrasi RuangKelas</title></head>
<body style="font-family:Arial,sans-serif;color:#1f2937;line-height:1.6">
    <h2>Verifikasi alamat Gmail</h2>
    <p>Ada permintaan registrasi akun siswa RuangKelas menggunakan alamat Gmail ini.</p>
    <p>Untuk melanjutkan, buka tautan berikut dalam 30 menit:</p>
    <p><a href="{{ $verificationUrl }}" style="background:#4338ca;color:white;padding:12px 18px;text-decoration:none;border-radius:8px">Verifikasi Gmail saya</a></p>
    <p>Jika tombol tidak terbuka, salin alamat berikut:</p>
    <p style="word-break:break-all">{{ $verificationUrl }}</p>
    <p>Jika Anda tidak meminta registrasi, abaikan email ini. Akun tidak akan dibuat.</p>
    <p>Jangan kirimkan tautan ini kepada orang lain.</p>
</body>
</html>
