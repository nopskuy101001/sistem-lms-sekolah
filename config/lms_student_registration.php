<?php

return [
    // Setelah registrasi, akun berstatus siswa_pending sampai OTP Gmail benar.
    // CACHE_STORE disarankan database/redis/file, bukan array untuk penggunaan normal.
    'otp_minutes' => 10,
];
