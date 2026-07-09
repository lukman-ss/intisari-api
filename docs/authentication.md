# Authentication

The API uses token-based authentication via **Bearer Tokens**.

## Register a New User

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

**Success Response (201 Created):**
```json
{
  "success": true,
  "message": "Registered",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "is_active": 1,
      "created_at": "2026-07-09T12:00:00Z"
    },
    "token": "plaintext_token_here..."
  }
}
```

## Login

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password123"
  }'
```

## Using the Bearer Token

For any protected routes (like creating a post or fetching your profile), attach the token in the `Authorization` header:

```bash
curl -X GET http://localhost:8000/api/auth/me \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"
```

## Logout

```bash
curl -X POST http://localhost:8000/api/auth/logout \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```
