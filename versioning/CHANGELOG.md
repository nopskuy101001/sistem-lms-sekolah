# LMS Sekolah - Changelog

## v0.1.0 - 2026-11-04
- Baseline resmi dari source ZIP (6), tanpa perubahan fitur.

## v0.2.0 - 2026-11-04
- Registrasi siswa melalui Gmail: signed-link, token sementara, kode undangan sekolah, anti replay, no schema change.

## v0.3.0 - 2026-11-04
- Registrasi siswa dua tahap: akun siswa_pending lalu verifikasi OTP Gmail terpisah, tanpa form kode sekolah.

## v0.4.0 - 2026-11-04
- Tutup registrasi publik dan OTP pada route + controller (termasuk cache lama); admin tetap membuat NIS; SMTP tidak berubah; akun siswa_pending tidak dihapus.

## v0.5.0 - 2026-11-04
- Tombol tambah/ganti email semua role via Profil. Konfirmasi password dan email baru; NIS dan registrasi publik tetap aman.
