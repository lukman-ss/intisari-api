# Panduan AI Coding Agent (AGENTS.md)

Dokumen ini berisi instruksi dan aturan wajib bagi AI Coding Agent yang berkontribusi pada repositori **Intisari API**. Harap baca dan patuhi seluruh panduan ini sebelum melakukan perubahan kode.

## 1. Aturan Bahasa
- **Penjelasan & Komunikasi**: Selalu gunakan **Bahasa Indonesia** saat memberikan penjelasan, komentar di pull request, atau saat berinteraksi dengan pengguna.
- **Kode**: Semua penamaan dalam kode (seperti nama kelas, *method*, variabel, konstanta, nama file, dll) wajib menggunakan **Bahasa Inggris** yang standar dan deskriptif.

## 2. Aturan Kode PHP
- **Versi PHP**: Repositori ini berjalan pada **PHP >= 8.2**. Manfaatkan fitur-fitur modern PHP 8.2 jika relevan (misalnya *readonly classes*, tipe data yang ketat, dll).
- **Strict Types**: Deklarasi `declare(strict_types=1);` **wajib** diletakkan di baris pertama setelah tag `<?php` pada setiap file PHP yang berada di dalam direktori `app/` maupun `tests/`.
- **Ekosistem**: Proyek ini dibangun dari nol (IntisariPHP). **Jangan** mengimpor atau menggunakan framework eksternal (seperti Laravel, Symfony, dll) untuk memecahkan masalah. Pertahankan agar tetap *lightweight* menggunakan utilitas bawaan (di dalam folder `app/Support/`).

## 3. Workflow Perubahan
- **Fokus**: Jangan mengubah file yang tidak relevan dengan tugas yang sedang dikerjakan. Lakukan perbaikan sebatas pada lingkup masalah (scope) yang diinstruksikan.
- **Validasi Wajib**: Setelah melakukan perubahan apa pun, agen wajib memverifikasi kualitas kodenya dengan menjalankan dua perintah berikut sebelum melaporkan tugas selesai:
  1. `composer test` (Memastikan tidak ada fitur yang *break*)
  2. `composer source:check` (Memastikan lulus *linter* dan pengecekan keamanan statis)

## 4. Standar Response API
- **Konsistensi JSON**: Semua *response* API wajib berformat JSON yang konsisten dengan standar struktur berikut (menggunakan utilitas bawaan `ApiResponse` atau method dari base `Controller`):
  - *Sukses*: `{ "success": true, "message": "...", "data": {...} }`
  - *Error*: `{ "success": false, "message": "...", "code": "...", "errors": [...] }`

## 5. Keamanan Data (Security)
- **Eksposur Rahasia**: **Jangan pernah** meng-expose data rahasia (*secret*, *token*, atau *password*) pada *response* API, *error message*, stack trace, ataupun ke dalam log. Pastikan semuanya disaring (*masked*) atau di-*unset* dari *array/object* sebelum di-*return*.
- **Visibilitas Token**: Token mentah (misal: *plain text token*) **hanya boleh** dimunculkan (di-*return* ke pengguna) sekali saja pada saat proses registrasi (*register*), proses masuk (*login*), atau pada saat *endpoint* pembuatan token itu sendiri dijalankan. Di luar skenario tersebut, token dilarang muncul.
