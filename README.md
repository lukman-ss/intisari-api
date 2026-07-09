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

Copy the example environment file and configure it:

```bash
cp .env.example .env
```

Ensure the database file exists:

```bash
touch database/database.sqlite
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

## Security Notes

- This starter is designed to be lightweight. The rate limiting provided by `RateLimitMiddleware` uses a file-based lock and is suited for low-traffic or development environments. For high-traffic production environments, consider replacing it with a Redis-based rate limiter or handling rate limits at the reverse proxy (e.g., Nginx).
- Always ensure `APP_DEBUG=false` in your `.env` when deploying to production to avoid exposing sensitive stack traces.
- Application logs (`storage/logs/app.log`) automatically mask sensitive keys (`password`, `token`, `authorization`, `secret`).

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
