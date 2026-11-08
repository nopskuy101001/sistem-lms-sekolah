# Reverse proxy dan base path

Laravel tetap berjalan di `127.0.0.1:8101`; Nginx adalah satu-satunya gateway. Tunnel harus diarahkan ke listener Nginx, bukan ke port Laravel.

## Konfigurasi aplikasi

Pertahankan `APP_URL` sebagai URL kanonis untuk Artisan, queue, dan email yang dibuat tanpa request HTTP. Host web tidak dipaksa ke nilai ini saat ada request.

```dotenv
APP_ENV=production
APP_URL=http://lms.smkn1cerme.test
TRUSTED_PROXIES=127.0.0.1,::1
TRUSTED_HOSTS=localhost,127.0.0.1,::1,lms.smkn1cerme.test
SESSION_COOKIE=lms_sekolah_session
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SAME_SITE=lax
```

Biarkan `SESSION_SECURE_COOKIE` tidak terdefinisi agar cookie mengikuti skema request yang sudah divalidasi dari proxy. Gunakan `true` hanya jika seluruh akses, termasuk lokal, selalu HTTPS.

Daftarkan hostname tunnel exact-match tanpa mengubah source atau membersihkan config cache:

```bash
php artisan lms:trusted-host https://random-name.trycloudflare.com
php artisan lms:trusted-host another-name.ngrok-free.app
php artisan lms:trusted-host --list
php artisan lms:trusted-host old-name.trycloudflare.com --remove
```

Registry disimpan di `storage/app/private/trusted-hosts.json`. User service (`alloxy`) harus dapat menulis direktori tersebut. Wildcard seperti `*.trycloudflare.com` ditolak.

## Contoh Nginx

Contoh berikut mempertahankan skema HTTPS yang diberikan agent tunnel hanya ketika agent tersebut terhubung dari loopback. Sesuaikan upstream PHP/Laravel yang sudah digunakan AppCtl; jangan menjalankan tunnel langsung ke `8101`.

```nginx
geo $lms_loopback_ingress {
    default   0;
    127.0.0.1 1;
    ::1       1;
}

map "$lms_loopback_ingress:$http_x_forwarded_proto" $lms_public_scheme {
    default   $scheme;
    "1:http"  http;
    "1:https" https;
}

map $lms_public_scheme $lms_public_port {
    default $server_port;
    http    80;
    https   443;
}

server {
    # Gunakan default_server hanya pada listener yang didedikasikan untuk LMS.
    listen 80 default_server;
    server_name lms.smkn1cerme.test _;

    location = /lms {
        return 308 /lms/;
    }

    location /lms/ {
        proxy_pass http://127.0.0.1:8101/;
        proxy_set_header Host               $host;
        proxy_set_header X-Forwarded-Host   $host;
        proxy_set_header X-Forwarded-Proto  $lms_public_scheme;
        proxy_set_header X-Forwarded-Port   $lms_public_port;
        proxy_set_header X-Forwarded-Prefix /lms;
        proxy_set_header X-Forwarded-For    $proxy_add_x_forwarded_for;
    }

    location / {
        proxy_pass http://127.0.0.1:8101;
        proxy_set_header Host              $host;
        proxy_set_header X-Forwarded-Host  $host;
        proxy_set_header X-Forwarded-Proto $lms_public_scheme;
        proxy_set_header X-Forwarded-Port  $lms_public_port;
        proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
    }
}
```

`proxy_pass` dengan trailing slash pada blok `/lms/` menghapus prefix sebelum request mencapai Laravel. Nginx kemudian mengirim `X-Forwarded-Prefix: /lms`, sehingga `route()`, `url()`, `asset()`, redirect, form action, dan endpoint fetch membangunnya kembali.

## Deployment dan verifikasi

```bash
composer install
npm install
npm run build
php artisan test --compact
php artisan config:clear
php artisan config:cache
php artisan queue:restart
```

Verifikasi lokal `http://lms.smkn1cerme.test/login`, lalu tunnel `https://HOST/lms/login`. Host yang belum didaftarkan harus menghasilkan HTTP 400. Setelah mengganti quick tunnel, cukup daftarkan host baru dan hapus host lama; source dan `.env` tidak perlu diubah. Jika `APP_URL` kanonis berubah, bangun ulang config cache dan jalankan `php artisan queue:restart` agar worker lama memakai nilai baru.

Rollback kode dapat dilakukan dengan me-revert commit/branch ini. Registry runtime dapat dinonaktifkan dengan memindahkan `storage/app/private/trusted-hosts.json`; konfigurasi lama kembali aktif setelah rollback dan `php artisan config:clear`. Tidak ada migrasi atau perubahan skema database pada fitur ini.
