# Error Handling

The API returns consistent and structured JSON errors. All errors are accompanied by an appropriate HTTP status code.

## Standard Error Format

```json
{
  "success": false,
  "message": "Error description",
  "code": "ERROR_CODE",
  "errors": {}
}
```

## Common Error Codes

- `INTERNAL_ERROR` (500): General server errors.
- `VALIDATION_ERROR` (422): Input validation failed. Details will be in the `errors` object.
- `UNAUTHENTICATED` (401): Missing or invalid Bearer token.
- `FORBIDDEN` (403): You do not have permission (e.g., trying to edit someone else's post).
- `NOT_FOUND` (404): The requested resource or endpoint does not exist.
- `METHOD_NOT_ALLOWED` (405): The HTTP method is not supported for this endpoint.
- `INVALID_JSON` (400): The request body contains malformed JSON.
- `RATE_LIMITED` (429): You have exceeded the API rate limit (default 60 requests/minute).

## Example Validation Error

```json
{
  "success": false,
  "message": "Validation failed",
  "code": "VALIDATION_ERROR",
  "errors": {
    "email": [
      "The email field must be a valid email address."
    ]
  }
}
```
