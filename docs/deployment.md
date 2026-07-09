# Panduan Deployment (Production)

Dokumen ini berisi daftar periksa (checklist) dan panduan esensial yang harus diperhatikan sebelum mengorbitkan **Intisari API** ke lingkungan *production*.

## 1. Document Root Wajib ke `public/`
Demi alasan keamanan mutlak, arahkan *Document Root* pada web server Anda secara spesifik ke direktori `public/`. Jangan jadikan *root project* (direktori utama yang berisi `.env` dan `vendor/`) sebagai document root karena akan berpotensi membocorkan konfigurasi rahasia.

## 2. Environment Variables
Pastikan Anda mengubah variabel lingkungan ini pada file `.env` di *production*:
```ini
APP_ENV=production
APP_DEBUG=false
```
> **Catatan:** Jangan pernah melakukan `git commit` terhadap file `.env`. Gunakan `.env.example` sebagai referensi struktur, lalu buat file `.env` baru di *server production*.

## 3. Hak Akses (Permissions) File dan Folder
Jika Anda menggunakan **SQLite** dan fitur *logger* internal, pastikan *web server* (misal `www-data` atau `nginx`) memiliki izin (permission) untuk menulis (*write access*) pada folder berikut:
- `database/` (Serta file `api.sqlite` di dalamnya)
- `storage/` (Dan sub-direktorinya seperti `storage/logs/`)

```bash
chown -R www-data:www-data database/ storage/
chmod -R 775 database/ storage/
```

## 4. Keamanan CORS di Production
Jika API Anda diakses oleh Frontend (SPA/PWA) dan Anda menggunakan *credentials* (cookie, token, dsb), **dilarang menggunakan wildcard `*`** pada konfigurasi `CORS_ALLOWED_ORIGINS`.

**Contoh `.env` yang benar:**
```ini
CORS_ALLOWED_ORIGINS=https://app.domainanda.com,https://admin.domainanda.com
```

## 5. Web Server / Reverse Proxy
PHP Built-in Server (`php -S`) **hanya boleh digunakan untuk development**. Pada environment *production*, gunakan web server berkinerja tinggi.

### Contoh Konfigurasi Minimal Nginx
```nginx
server {
    listen 80;
    server_name api.domainanda.com;
    root /var/www/intisari-api/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock; # Sesuaikan dengan versi PHP
    }

    location ~ /\.ht {
        deny all;
    }
}
```

### Contoh Konfigurasi Minimal Caddy
```caddy
api.domainanda.com {
    root * /var/www/intisari-api/public
    php_fastcgi unix//run/php/php8.2-fpm.sock
    file_server
}
```

## 6. Backup Database SQLite
Karena SQLite disimpan dalam wujud file statis, Anda dapat melakukan rutin *backup* dengan cara menyalin (copy) file tersebut ke lokasi eksternal yang aman secara berkala (misal menggunakan cronjob dan rsync/S3).
```bash
# Contoh cron backup sederhana
0 2 * * * cp /var/www/intisari-api/database/api.sqlite /backup/database/api_$(date +\%F).sqlite
```

## 7. Log Rotation
*File log* aplikasi Anda akan terus bertumbuh dan membesar di `storage/logs/app.log`. Anda harus mengonfigurasi *log rotation* di *server* (misal melalui *logrotate* pada Linux) untuk menghindarinya memenuhi seluruh kapasitas memori disk Anda.

Contoh konfigurasi `/etc/logrotate.d/intisari-api`:
```text
/var/www/intisari-api/storage/logs/app.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0660 www-data www-data
}
```
