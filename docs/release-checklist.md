# Release Checklist

Gunakan daftar periksa (*checklist*) ini sebagai panduan standar sebelum melakukan rilis versi baru ke repositori dan Packagist.

## Pra-Rilis
- [ ] Pastikan dependensi dapat diinstal dari awal tanpa masalah: `composer install` (harus sukses bersih).
- [ ] Pastikan seluruh test lulus tanpa error: `composer test` (warna hijau / OK).
- [ ] Pastikan kode lulus inspeksi keamanan statis dan *linter*: `composer source:check` (validasi *strict* dan *no hardcoded secret*).
- [ ] Pastikan dokumentasi lengkap dan spesifikasi OpenAPI lulus pengecekan: `composer docs:check`.

## Konten & Repositori
- [ ] Pastikan instruksi pada `README.md` selaras dengan keadaan fitur aktual (tidak melebih-lebihkan atau menyebut fitur yang belum tersedia).
- [ ] Perbarui file `CHANGELOG.md` untuk merefleksikan perubahan fitur, perbaikan bug, atau instruksi rilis pada versi ini.
- [ ] Pastikan file rahasia/konfigurasi temporer (seperti `.env`, file *database* SQLite `database.sqlite`, dan file hasil *upload* pada direktori `storage/`) **tidak** ikut ter-commit ke dalam sistem kontrol versi (telah diabaikan oleh `.gitignore`).

## Publikasi
- [ ] Buat dan rilis tag versi (contoh untuk rilis pertama: `v0.1.0`).
- [ ] Verifikasi bahwa sistem pembaruan otomatis Webhook di Packagist aktif dan berhasil menarik rilis tag terbaru secara sinkron.
