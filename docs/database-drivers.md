# Dukungan Database dan Driver

Proyek **Intisari API Starter** dirancang untuk ringan dan menggunakan koneksi *database* berbasis PDO yang disediakan oleh *layer database* Intisari.

## SQLite sebagai Default

Secara *default*, sistem telah diatur untuk menggunakan **SQLite**. Konfigurasi bawaan tidak memerlukan proses peladen (*server-less*), sehingga cocok untuk kecepatan dan kesederhanaan *development*.

File *database* secara *default* berlokasi di `database/api.sqlite`.

**Contoh Konfigurasi `.env` untuk SQLite:**
```ini
DB_CONNECTION=sqlite
DB_DATABASE=database/api.sqlite
DB_FOREIGN_KEYS=true
```
*(Catatan: Anda bisa mengubah lokasi `DB_DATABASE` menggunakan jalur absolut jika diperlukan).*

## Dukungan MySQL/PostgreSQL

Lapisan *database* berbasis PDO milik Intisari secara teori dapat terkoneksi dengan MySQL maupun PostgreSQL (sejauh ekstensi `pdo_mysql` atau `pdo_pgsql` aktif di server Anda). Anda dapat memeriksa dukungan koneksi tersebut di file konfigurasi `config/database.php`.

Jika ingin menggunakan MySQL, konfigurasi sudah tersedia dan Anda hanya perlu menyesuaikan `.env`.

**Contoh Konfigurasi `.env` untuk MySQL:**
```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=intisari_api
DB_USERNAME=root
DB_PASSWORD=secret
```

**Contoh Konfigurasi `.env` untuk PostgreSQL (Jika Didukung):**
```ini
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=intisari_api
DB_USERNAME=postgres
DB_PASSWORD=secret
```

## Batasan Migrasi Database Saat Ini

Meskipun sistem mendukung penggunaan relasional *database* lain, **skrip *migration* saat ini dikembangkan dengan pendekatan SQLite-first**. 

File yang ada di direktori `database/migrations/` disusun memprioritaskan fungsi *built-in* milik SQLite (seperti spesifikasi tipe data teks panjang, *default datetime*, autoincrement dll yang diadaptasi khusus agar stabil di SQLite).

**Peringatan Penting:** 
Jika Anda berencana memigrasi ke MySQL atau PostgreSQL untuk tahap *production*, Anda mungkin perlu merevisi atau menulis ulang skrip *migration* mentah (`*.php` migration dengan `CREATE TABLE`) agar sintaks tabel dan tipe kolom kompatibel dengan spesifikasi mesin SQL dari MySQL/PostgreSQL.

*(Tidak ada driver atau package baru yang ditambahkan di atas DB layer bawaan Intisari)*
