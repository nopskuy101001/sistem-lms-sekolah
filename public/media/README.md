# Background video halaman autentikasi

Taruh video background di folder ini dengan salah satu nama berikut:

- `login-background.mp4` (disarankan: H.264)
- `login-background.webm` (opsional, lebih hemat ukuran pada browser yang mendukung)

Spesifikasi yang disarankan:

- Resolusi: 1280 × 720 (16:9)
- Durasi: 8–20 detik dan dibuat seamless loop
- Tanpa audio; video otomatis diputar dalam keadaan mute
- Ukuran ideal: di bawah 5 MB agar halaman login tetap cepat
- Frame rate: 24 atau 30 FPS
- MP4 memakai H.264 dengan opsi web optimized/fast start

Setelah file disalin, lakukan hard refresh pada browser (`Ctrl+F5`). Aplikasi mendeteksi file secara otomatis. Jika file belum ada atau pengguna memilih pengurangan animasi, gradient bawaan tetap digunakan.

Untuk mengubah tingkat gelap atau blur, edit `.lms-auth-backdrop` di `public/css/lms-ui.css`. Untuk mengubah posisi video, edit `object-position` pada `.lms-auth-video`.
