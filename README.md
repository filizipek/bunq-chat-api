# Chat API (PHP + Slim + SQLite) — bunq Backend Engineer Assignment
**Author:** Filiz Ipek Oktay
**Date:** January 2026

A RESTful chat backend built with **PHP (Slim)** and **SQLite**.  
Users can create public groups, join them, and exchange messages.  
There is no registration/login flow — users are identified by a token included in requests and stored in the database.

---

## Assignment scope

- Public chat groups (any user can join any group)
- Create groups, join groups, send messages, list messages in a group
- SQLite storage
- RESTful JSON API over HTTP
- No authentication flow; users are identified by token/ID in requests and DB
- Slim for routing
- Include tests

---

## What's implemented

- **User identity token**: `POST /auth/register` issues a token used to identify the user
- **Groups**
  - Create group (creator auto-joins)
  - List groups (pagination)
  - Join group (idempotent)
- **Messages**
  - Post message to a group (membership required)
  - List messages in a group (membership required, pagination)
- **Persistence**
  - SQLite via PDO
  - Tables: `users`, `groups`, `group_members`, `messages`
- **Developer experience**
  - Dockerized setup (**API + Swagger UI**)
  - OpenAPI spec served via Swagger UI
  - PHPUnit tests covering core flows

---

## Tech stack

- PHP 8.x (Docker container)
- Slim Framework (routing + middleware)
- SQLite (PDO)
- Swagger UI (OpenAPI)
- PHPUnit

---

## Services & ports

- API: `http://localhost:8080`
- Swagger UI: `http://localhost:8081`

---

## Quickstart
```bash
docker compose up -d --build
docker compose exec app php scripts/migrate.php
docker compose exec app ./vendor/bin/phpunit --testdox
```

---

## Run the project

### Requirements
Docker Desktop (includes Docker Compose)

### Start containers
```bash
docker compose up -d --build
```

### Run migrations
Creates required tables:
```bash
docker compose exec app php scripts/migrate.php
```

**Note:** SQLite is stored in a Docker volume (`/app/storage`) for reliability.
This avoids `database is locked` issues commonly seen with bind mounts on macOS/Windows.

---

## User identification (token)

The assignment does not require registration/login functionality.

This project identifies users by a token sent with each request:
```
Authorization: Bearer <token>
```

A token is issued by:
```
POST /auth/register
```

This endpoint exists only to generate an identity token for API usage/testing.

---

## Test manually with Swagger UI

1. Open Swagger UI: `http://localhost:8081`

2. Call `POST /auth/register` with:
```json
   { "name": "Alice" }
```

3. Copy the returned token.

4. Click **Authorize** (top-right) and paste:
```
   Bearer <token>
```

5. Test protected endpoints:
   - `POST /groups`
   - `GET /groups`
   - `POST /groups/{groupId}/join`
   - `POST /groups/{groupId}/messages`
   - `GET /groups/{groupId}/messages`

---

## Curl examples

### Health
```bash
curl -s http://localhost:8080/health
```

### Create a token
```bash
curl -s -X POST http://localhost:8080/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Alice"}'
```

Set token:
```bash
TOKEN="PASTE_TOKEN_HERE"
```

### Create a group
```bash
curl -s -X POST http://localhost:8080/groups \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Public Group 1","description":"general"}'
```

### List groups
```bash
curl -s "http://localhost:8080/groups?limit=20&offset=0" \
  -H "Authorization: Bearer $TOKEN"
```

### Join a group
```bash
curl -s -X POST http://localhost:8080/groups/1/join \
  -H "Authorization: Bearer $TOKEN"
```

### Post a message
```bash
curl -s -X POST http://localhost:8080/groups/1/messages \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"content":"Hello world"}'
```

### List messages
```bash
curl -s "http://localhost:8080/groups/1/messages?limit=50&offset=0" \
  -H "Authorization: Bearer $TOKEN"
```

---

## Run tests
```bash
docker compose exec app ./vendor/bin/phpunit --testdox
```

### Expected output
The test suite should pass and show something like:
```
OK (5 tests, 31 assertions)
```

### Test approach
- Uses an isolated SQLite DB for tests: `/tmp/chat_test.sqlite`
- Runs migrations before executing tests
- Covers:
  - Auth required for protected endpoints
  - Group creation & listing
  - Membership enforcement
  - Join → post → list message flow

---

## Inspect the SQLite database

SQLite lives inside a Docker volume. Inspect it directly from the container.

### List tables
```bash
docker compose exec app sqlite3 /app/storage/chat.sqlite ".tables"
```

### Show groups
```bash
docker compose exec app sqlite3 /app/storage/chat.sqlite \
"SELECT id,name,created_at FROM groups ORDER BY id DESC;"
```

### Show latest messages (with user + group)
```bash
docker compose exec app sqlite3 /app/storage/chat.sqlite \
"SELECT m.id, g.name AS group_name, u.name AS user_name, m.content, m.created_at
 FROM messages m
 JOIN users u ON u.id = m.user_id
 JOIN groups g ON g.id = m.group_id
 ORDER BY m.id DESC
 LIMIT 20;"
```

### Snapshot DB to host for inspection tools (optional)
If you want to open the DB with a tool (VS Code SQLite viewer / DB Browser), copy it out:
```bash
docker cp chat-api-app-1:/app/storage/chat.sqlite ./storage/chat.local.sqlite
```

Open `storage/chat.local.sqlite` locally. **Do not commit DB files.**

Recommended `.gitignore` entries:
```gitignore
storage/*.sqlite
storage/*.db
storage/chat.local.sqlite
```

---

## Project structure
```
.
├── app/
│   ├── routes.php                  # API routes
│   ├── middleware.php              # middleware wiring
│   ├── settings.php                # settings
│   ├── dependencies.php            # DI setup
│   └── repositories.php            # repository wiring (if used)
├── public/
│   └── index.php                   # app bootstrap + CORS
├── scripts/
│   └── migrate.php                 # creates SQLite tables
├── src/
│   ├── Database/Connection.php     # PDO connection (supports SQLITE_PATH override for tests)
│   └── Middleware/AuthMiddleware.php
├── tests/
│   ├── Application/Actions/Chat/   # Groups + Messages tests
│   ├── TestCase.php                # test harness
│   └── bootstrap.php               # sets test DB + runs migrations
├── openapi.yaml                    # OpenAPI specification
├── docker-compose.yml
├── Dockerfile
└── README.md
```

---

## Notes & decisions

### SQLite locking
SQLite can produce `database is locked` more often when DB files live on host bind mounts.
To avoid this, `/app/storage` is backed by a Docker volume.

### CORS for Swagger UI
Swagger UI runs at `http://localhost:8081` while the API runs at `http://localhost:8080`.
CORS headers and an OPTIONS handler are configured so Swagger can call the API from the browser.

---

## License
For assignment/review purposes.