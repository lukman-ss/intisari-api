# Intisari API

A lightweight RESTful API starter built on top of IntisariPHP.

## Requirements

- PHP 8.2 or higher
- SQLite PDO extension
- Composer

## Installation

Create a new project using Composer:

```bash
composer create-project lukman-ss/intisari-api your-project-name
cd your-project-name
```

## Environment Setup

Copy the example environment file:

```bash
cp .env.example .env
```

**Important**: The default `.env.example` is secured for production. To run the API locally, open your `.env` file, comment out the production database and CORS settings, and uncomment the `LOCAL DEVELOPMENT OVERRIDES` section at the bottom.

Ensure the local SQLite database file exists (if using local development overrides):

```bash
touch database/api.sqlite
```

*(Alternatively, you can run `make install` if you are on a Unix-based system to handle both setup steps.)*

## Run Development Server

To start the built-in PHP development server, use the composer script:

```bash
composer serve
```

The API will be available at `http://localhost:8000`.

## Database Migration

Run the database migrations to set up the default tables (`users`, `posts`, `api_tokens`):

```bash
composer migrate
```

If you need to refresh the database (drop and re-run all migrations) and optionally seed initial data:

```bash
composer fresh
composer seed
```

## Authentication Flow

This project uses a simple Bearer Token authentication mechanism. 

1. **Register**: Send a `POST` request to `/api/auth/register` with `name`, `email`, and `password`. You will receive a token in the response.
2. **Login**: Send a `POST` request to `/api/auth/login` with `email` and `password` to obtain a new token.
3. **Usage**: Include the token in the `Authorization` header of subsequent requests:
   ```
   Authorization: Bearer YOUR_TOKEN_HERE
   ```
4. **Logout**: Send a `POST` request to `/api/auth/logout` to revoke your current token.

## Example API Request

Here is an example of creating a new post:

```bash
curl -X POST http://localhost:8000/api/posts \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "My First Post",
    "content": "Hello World",
    "status": "published"
  }'
```

## Testing

The project is fully tested using PHPUnit, with automatic in-memory SQLite isolation for feature tests.

To run the test suite:

```bash
composer test
```

To run syntax checks and code validation:

```bash
composer source:check
```

## Project Structure

- `app/`: Core application code (Controllers, Middleware, Exceptions, Support, Repositories).
- `bootstrap/`: Application and container bootstrapping.
- `config/`: Application configuration files.
- `database/`: SQLite database file and migration scripts.
- `docs/`: OpenAPI specifications and Markdown documentation.
- `public/`: Web server entry point (`index.php`).
- `routes/`: Route definitions (`api.php`, `console.php`).
- `tests/`: Feature and Unit tests.
- `scripts/`: Development and maintenance scripts.

## Secure Deployment

To ensure this API operates securely in a production environment, strictly adhere to the following baseline requirements. For complete details, see our [SECURITY.md](SECURITY.md).

- **Environment Config**: Never commit `.env` to Git. Ensure `APP_DEBUG=false` to prevent sensitive stack trace leaks.
- **HTTPS & Proxy**: Production traffic **must** run over HTTPS. If placed behind a reverse proxy (e.g., Nginx), ensure headers like `X-Forwarded-For` are trusted securely.
- **Database Credentials**: MySQL/PostgreSQL require explicit, strong credentials. Do not fallback to root or empty passwords.
- **CORS Allowlist**: Do not use `*` for `CORS_ALLOWED_ORIGINS` in production. Specify exact frontend domains explicitly.
- **Rate Limiting**: The built-in file-based rate limiter is for low-traffic/local use. For production, switch to a robust Redis/Memcached backend or handle throttling at the reverse proxy.
- **Token Least-Privilege**: Grant API tokens only the absolute minimum abilities required. Never default to wildcard (`*`) abilities for user-facing tokens.
- **Storage Permissions**: Ensure that `storage/` and any SQLite database directories are writable by the web server but **never** accessible directly via the public web root.
- **Composer Audit**: Regularly run `composer security:check` (which includes `composer audit --locked`) in your CI pipeline to catch vulnerable dependencies.
- **Logging & Secrets**: Use the built-in redaction for sensitive keys (`password`, `token`, etc.) in `storage/logs/app.log`. Manage secrets using secure environment managers or vaults.
- **Migrations**: Run `composer migrate` cautiously in production. Use `--force` mechanisms appropriately to prevent accidental destructive migrations.
- **Backup**: Regularly back up your database and `.env` securely off-site.

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
