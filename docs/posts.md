# Posts API

Manage your blog posts through the API.

## List Posts (GET `/api/posts`)

Fetch all published posts. You can paginate, search, or filter.

```bash
curl -X GET "http://localhost:8000/api/posts?page=1&per_page=15&status=published&search=hello" \
  -H "Accept: application/json"
```

## Get Single Post (GET `/api/posts/{id}`)

```bash
curl -X GET http://localhost:8000/api/posts/1 \
  -H "Accept: application/json"
```
*Note: Draft posts are only accessible by their owner.*

## Create Post (POST `/api/posts`)

**Requires Authentication.**

```bash
curl -X POST http://localhost:8000/api/posts \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "My Awesome Post",
    "content": "This is the content.",
    "status": "published"
  }'
```

## Update Post (PUT/PATCH `/api/posts/{id}`)

**Requires Authentication.** (You must be the owner of the post).

```bash
curl -X PATCH http://localhost:8000/api/posts/1 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "draft"
  }'
```

## Delete Post (DELETE `/api/posts/{id}`)

**Requires Authentication.** (You must be the owner of the post).

```bash
curl -X DELETE http://localhost:8000/api/posts/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```
