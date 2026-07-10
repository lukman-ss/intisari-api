# Security Release Checklist

Before tagging a new production release, ensure that all of the following security requirements are met and verified.

## Pre-Release Verification

- [ ] **Seluruh tests lulus**: Semua test dalam suite `composer test` harus hijau tanpa kegagalan.
- [ ] **Static analysis lulus**: Tidak ada isu yang dilaporkan oleh `phpstan` (`composer security:patterns`).
- [ ] **Composer audit lulus**: Tidak ada advisory keamanan yang belum ditangani dari dependensi via `composer audit --locked` (bagian dari `composer security:check`).
- [ ] **Tidak ada wildcard default abilities**: Pembuatan token standar (login/register) memberikan set hak istimewa terkecil yang dibutuhkan (Least Privilege), **bukan** `["*"]`.
- [ ] **APP_DEBUG default false**: File `APP_DEBUG` default untuk production **wajib** dikonfigurasi `false` untuk mencegah kebocoran *stack trace*.
- [ ] **CORS tidak wildcard**: `CORS_ALLOWED_ORIGINS` menggunakan asal (origin) eksplisit dan tidak pernah dikonfigurasi ke nilai berbahaya `*`.
- [ ] **Login dan registration dilindungi limiter**: Rute otentikasi wajib dikunci dengan *rate limiter* untuk menekan laju serangan *brute-force* atau pencacahan sandi.
- [ ] **Draft visibility regression test lulus**: Pastikan ada tes yang memastikan tamu (*guest* / *unauthenticated*) atau pengguna yang tidak berhak dilarang membaca post draf.
- [ ] **Tidak ada secret baru**: Pindai repositori untuk memastikan tidak ada kunci rahasia (*hardcoded secret*) baru yang tertinggal dalam *source code* via skrip `security_scan.php`.
- [ ] **Database configuration fail closed**: Konfigurasi koneksi MySQL/PostgreSQL wajib memicu kegagalan sistem (*fail closed*) bila sandi atau pengguna *database* tidak terisi, serta menolak melakukan kompromi *fallback* yang rapuh.
- [ ] **Documentation diperbarui**: File keamanan seperti `SECURITY.md`, `README.md`, dan panduan instalasi terbaru telah sinkron.

## Post-Release Monitoring
- Jika terdapat token dengan atribut kedaluwarsa (`abilities: ["*"]`), pastikan bahwa skema mitigasi (misal: pencabutan token masal atau translasi) telah dijalankan melalui *migration*.
