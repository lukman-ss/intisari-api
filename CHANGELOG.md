# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [v0.2.0] — 2026-07-10 (Security Release)

### Security
- **Draft visibility diperbaiki**: Mencegah kebocoran data. Permintaan ke `GET /api/posts?status=draft` oleh pengguna anonim (tamu) kini secara tegas ditolak dengan `401 Unauthorized`.
- **Token abilities diperketat**: Pembuatan token standar (login & register) tidak lagi memberikan wildcard `["*"]`. Token baru hanya diberikan subset *least privilege* sesuai katalog yang terdaftar.
- **Legacy Token Revocation**: Seluruh token lawas yang memiliki abilities `["*"]` telah **dicabut permanen** melalui database migration `20260710100000_revoke_legacy_wildcard_tokens.php`.
- **APP_DEBUG default dinonaktifkan**: Pengaturan bawaan diwajibkan `false` untuk mencegah kebocoran jejak *stack-trace* sensitif dalam respons error JSON ke publik.
- **CORS default diperketat**: Header CORS tidak akan direfleksikan secara liar; wildcard origin `*` kini dibatasi ketat dan skema pencocokan (*exact match*) diberlakukan.
- **Oversized payload protection**: Pembatasan ukuran maksimum JSON body ditambahkan untuk menangkis serangan kehabisan memori, kini mengembalikan `413 Payload Too Large`.
- **Security Headers Middleware**: `Cache-Control: no-store, private`, `X-Content-Type-Options`, `X-Frame-Options`, dan `Referrer-Policy` sekarang terpasang secara intrinsik di dalam `ApiResponse::build()`, memastikan seluruh respons *error* sensitif turut dilindungi tanpa di-cache publik.

### Changed
- **MySQL insecure fallback dihapus**: Kegagalan menyediakan konfigurasi `DB_USERNAME` dan `DB_PASSWORD` secara eksplisit sekarang akan memicu *fatal configuration error* alih-alih merosot (*fallback*) ke nilai 'root' dan kata sandi kosong.

### Added
- **Auth throttling ditambahkan**: Rute login dan pendaftaran kini dilindungi oleh batasan laju (*rate limiter*) ketat demi menahan pencacahan sandi dan *brute-force*.
- **DAST Smoke Test**: Ditambahkan skrip `scripts/dast_smoke.php` untuk menguji kerentanan API (throttling, CORS, oversized payload, dsb) secara dinamis di lingkungan paska-*deploy*.

### Breaking Changes (Backward Compatibility Notes)
- Kontrak respons *API (JSON Envelope, Pagination, Validation Format, Login/Register Success)* **tidak berubah** (100% kompatibel secara struktural).
- **Perubahan Perilaku (Behavioral Breaks):**
  - Klien yang mengandalkan sesi API lama dengan atribut `["*"]` akan seketika menerima `401 Unauthorized` karena seluruh token warisan tersebut telah dihanguskan.
  - Skrip pengujian atau klien lama yang mengirimkan *payload* ekstrem melampaui batas wajar JSON parser kini akan mementahkan *request* dengan `413` dan bukan `500`.
  - Akses `OPTIONS` dari *origin* tak terdaftar tidak akan lagi disetujui (CORS diperketat).

### Migration Notes
- **Admin / Operator**: Jalankan perintah `composer migrate` untuk menghapus token legacy secara rahasia. **PENTING:** Ini akan memaksa semua klien lama untuk **login ulang**. Strategi migrasi ini sengaja dipilih tanpa *compatibility bypass* karena mencoba merekonstruksi ulang hak khusus *super-admin* dari token *wildcard* tanpa izin pengguna justru dapat menghidupkan kembali kerentanan (*vulnerability*) privilese berlebih. Jangan berikan akses lewat belakang (*backdoor*).

---

## [v0.1.1] — 2026-07-09

### Fixed
- Removed `database/api.sqlite` (SQLite runtime file) from version control.
- Removed `storage/framework/rate-limit/*.json` (rate-limit cache files) from version control.
- Updated `.gitignore` to exclude `/database/*.sqlite`, `/database/*.sqlite-shm`, `/database/*.sqlite-wal`, and `/storage/framework/rate-limit/` going forward.

---

## [v0.1.0] — 2026-07-09

### Added

#### Core Infrastructure
- Lightweight PHP 8.2+ API starter built on `lukman-ss/intisari` — no heavy framework or ORM.
- PDO-based database layer with SQLite as default, MySQL structure available in `config/database.php`.
- Autoloading via Composer PSR-4 for `App\` and `Database\` namespaces.
- Bootstrap (`bootstrap/app.php`) with service container bindings.
- Console command runner (`intisari` CLI script) with `migrate`, `migrate:fresh`, `db:seed`, and `env:check` commands.
- `migrate:fresh` requires `--force` flag outside of `testing` environment — prevents accidental data loss.

#### API Contract & Response
- `App\Support\ApiResponse` as the single response standard for all API responses.
- Response shapes: `success`, `error`, `validation`, `paginated`, `created`, `noContent`.
- RFC 7807 Problem Details support via `API_ERROR_FORMAT=problem` env variable (default: standard format).
- `X-Request-Id` header on every response via `RequestIdMiddleware` for request traceability.

#### Middleware Stack
- `ForceJsonResponseMiddleware` — enforces `Content-Type: application/json` on all responses.
- `CorsMiddleware` — configurable via `CORS_ALLOWED_ORIGINS`, `CORS_ALLOWED_METHODS`, `CORS_ALLOW_CREDENTIALS`.
- `SecurityHeadersMiddleware` — sets `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`.
- `RateLimitMiddleware` — file-based rate limiter (60 req/min default), with `X-RateLimit-Limit` and `X-RateLimit-Remaining` headers.
- `AuthTokenMiddleware` — Bearer token authentication via hashed token lookup.
- `RequestIdMiddleware` — reads or generates `X-Request-Id` per request; propagates to logger context.

#### Authentication
- `POST /api/auth/register` — register user with name, email, password, password_confirmation.
- `POST /api/auth/login` — login and receive Bearer token.
- `GET /api/auth/me` — return authenticated user (auth required).
- `POST /api/auth/logout` — revoke current token.
- `POST /api/auth/refresh` — revoke current token and issue new token. Old token is immediately invalid.
- Passwords stored as bcrypt hash (`password_hash`). Never exposed in any response.
- Plain token returned **only once** at register/login/create/refresh — never stored, only its SHA-256 hash stored.

#### API Token Management
- `GET /api/tokens` — list all tokens for authenticated user (no `token_hash` in response).
- `POST /api/tokens` — create named token with optional abilities array.
- `DELETE /api/tokens/{id}` — revoke specific token belonging to authenticated user.

#### Posts CRUD
- `GET /api/posts` — paginated posts list (public: published only; drafts visible to owner).
- `GET /api/posts/{id}` — single post (draft protected by ownership check).
- `POST /api/posts` — create post (auth required), auto-generates slug.
- `PUT /api/posts/{id}` — full update (auth + ownership required).
- `PATCH /api/posts/{id}` — partial update (auth + ownership required).
- `DELETE /api/posts/{id}` — soft delete via `deleted_at` (auth + ownership required).
- Filtering: `search`, `status`.
- Sorting: `sort` (id, title, created_at, updated_at) + `direction` (asc, desc) — whitelist-enforced, no raw injection.
- Pagination: `page`, `per_page` (capped at 100), returns `meta` with `total`, `last_page`, `has_more`.

#### Data Transformation (Resources)
- `App\Support\JsonResource` — minimal base resource with `::make()` and `::collection()`.
- `App\Resources\UserResource` — strips `password_hash` unconditionally from all user responses.
- `App\Resources\PostResource` — consistent field types; omits `deleted_at` from output.

#### Validation
- `App\Support\RequestValidator` wrapping Intisari's validation layer.
- `App\Exceptions\ApiValidationException` — throws structured 422 response with field-level errors.

#### Error Handling
- `App\Support\Handler` — catches all throwables, returns structured JSON. Stack traces only exposed when `APP_DEBUG=true`.
- Named exception classes: `ApiException`, `ApiValidationException`, `NotFoundException`, `ForbiddenException`.
- 500 errors are logged to `storage/logs/app.log` with exception class, file, and line — never exposed to client.

#### Logging
- `App\Support\Logger` — append-only JSON logger to `storage/logs/app.log`.
- Sensitive keys (`password`, `token`, `authorization`, `secret`, `api_key`) are automatically masked (`********`) in context.
- `request_id` correlation included in every log entry when set by `RequestIdMiddleware`.

#### Configuration
- `config/app.php`, `config/database.php`, `config/cors.php`, `config/logging.php`.
- `.env.example` documents all supported environment variables.
- `env:check` CLI command validates required env vars and directory writability.

#### Database Migrations
- `2024_01_01_000001_create_users_table` — users with bcrypt hash, email unique, is_active flag.
- `2024_01_01_000002_create_api_tokens_table` — token name, SHA-256 hash, abilities JSON, expiry, user FK.
- `2024_01_01_000003_create_posts_table` — posts with slug, status, soft-delete `deleted_at`.

#### Tests
- 130 tests, 489 assertions — all green (PHPUnit 10.5, PHP 8.2).
- Feature tests: `AuthApiTest`, `TokensApiTest`, `PostsApiTest`, `PostsPaginationTest`.
- Unit tests: `ResourceTest`, `LoggerTest`, `PasswordHasherTest`, `SluggerTest`, `RequestIdMiddlewareTest`, `ProblemDetailsTest`.

#### Documentation
- `docs/openapi.yaml` — full OpenAPI 3.0 spec covering all endpoints.
- `README.md` — quick start, endpoint listing, architecture overview.
- `docs/getting-started.md`, `docs/authentication.md`, `docs/database.md`, `docs/errors.md`, `docs/posts.md`.
- `docs/database-drivers.md` — SQLite default, MySQL/PostgreSQL config examples, migration limitations.
- `docs/deployment.md` — document root, permissions, Nginx/Caddy config, CORS production, backup, log rotation.
- `docs/release-checklist.md` — pre-release checklist for maintainers.

#### CI/CD
- `.github/workflows/ci.yml` — GitHub Actions matrix: PHP 8.2, 8.3, 8.4.
- Steps: checkout → setup PHP → composer install → validate → source:check → docs:check → test.
- No secrets required. No automatic publishing.

---

### Known Limitations

- **SQLite-first migrations**: The SQL migration files are written for SQLite. Switching to MySQL or PostgreSQL requires manually adapting column types and syntax.
- **File-based rate limiter**: The `RateLimitMiddleware` uses local filesystem state. It is not suitable for high-traffic multi-server deployments — use Nginx rate limiting or a Redis-backed solution instead.
- **No role-based access control (RBAC)**: Token abilities are stored but enforcement is minimal (`AbilityMiddleware` checks presence only). Fine-grained RBAC is not included.
- **No email verification**: User registration does not send verification emails or enforce email confirmation.
- **No password reset flow**: There is no forgot-password or reset-password endpoint.
- **No refresh token rotation audit log**: Token refresh revokes the old token but there is no audit trail of previous sessions.
- **Single database driver at runtime**: Only one DB connection is active per request (no read replicas or connection pooling).

---

[v0.1.1]: https://github.com/lukman-ss/intisari-api/releases/tag/v0.1.1
[v0.1.0]: https://github.com/lukman-ss/intisari-api/releases/tag/v0.1.0
