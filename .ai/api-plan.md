# REST API Plan

## 1. Resources
- Users → table: `users`
- Shelves → table: `shelves`
- Books → table: `books`
- Genres → table: `genres`
- Book–Genre links → table: `book_genre`
- AI Recommendation Events → table: `ai_recommendation_events`

Notes impacting API design:
- IDs: UUIDs for most entities; `genres.id` is integer; `ai_recommendation_events.id` is integer.
- Case-insensitive uniqueness: `users.email`, `shelves.name`, `genres.name` (lowercased unique indexes).
- Validation (DB level): lengths, `isbn` format 10 or 13 digits, `page_count` integer, enum `book_source_enum` in `books.source` with values: `manual`, `ai_recommendation`.
- Triggers auto-manage `updated_at` and block UPDATE/DELETE on system shelves (`is_system = true`).

## 2. Endpoints

Conventions
- Base path: `/api`
- Content type: `application/json; charset=utf-8`
- Timestamps: ISO 8601 (UTC), e.g. `2025-10-15T12:00:00Z`
- Error format: RFC 7807 Problem Details (`application/problem+json`)
- Pagination: `limit` (default 20, max 100), `offset` (default 0). Response includes `meta` object.
- Sorting: `sort` (field), `order` (`asc`|`desc`).
- CSRF: required for state-changing requests when using session cookies, via header `X-CSRF-Token`.
- Rate limit headers (indicative): `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`.

### 2.1 Authentication

Session-based auth over same-origin requests (Symfony Security). All responses set/expect secure, HTTP-only cookies with `SameSite=Lax`.

1) Register
- Method: POST
- Path: `/api/auth/register`
- Description: Create a user and start a session.
- Request JSON:
```json
{
  "email": "user@example.com",
  "password": "strong-password",
  "passwordConfirm": "strong-password"
}
```
- Response JSON (201):
```json
{
  "user": {
    "id": "uuid",
    "email": "user@example.com",
    "createdAt": "2025-10-15T12:00:00Z"
  }
}
```
- Success: 201 Created (Set-Cookie session)
- Errors:
  - 422 Unprocessable Entity (validation: email format/length, password length ≥ 8, mismatch)
  - 409 Conflict (email already registered)

2) Login
- Method: POST
- Path: `/api/auth/login`
- Description: Authenticate and start session.
- Request JSON:
```json
{ "email": "user@example.com", "password": "strong-password" }
```
- Response JSON (200): same as register.
- Errors: 401 Unauthorized (invalid credentials), 422 (validation)

3) Logout
- Method: POST
- Path: `/api/auth/logout`
- Description: End session.
- Success: 204 No Content (clears session cookie)

4) Current user
- Method: GET
- Path: `/api/auth/me`
- Description: Get current session user.
- Response JSON (200): same `user` object as above
- Errors: 401 Unauthorized

### 2.2 Genres (read-only)

1) List genres
- Method: GET
- Path: `/api/genres`
- Query: none
- Response JSON (200):
```json
{
  "data": [ { "id": 1, "name": "Fantasy" } ]
}
```

### 2.3 Shelves

Shelf JSON shape:
```json
{
  "id": "uuid",
  "name": "Salon lewy",
  "isSystem": false,
  "createdAt": "2025-10-15T12:00:00Z",
  "updatedAt": "2025-10-15T12:00:00Z"
}
```

1) List shelves
- Method: GET
- Path: `/api/shelves`
- Query:
  - `q` (optional string, case-insensitive name contains)
  - `includeSystem` (optional boolean, default true)
- Response JSON (200):
```json
{
  "data": [ { "id": "uuid", "name": "Do zakupu", "isSystem": true, "createdAt": "...", "updatedAt": "..." } ],
  "meta": { "total": 2 }
}
```

2) Create shelf
- Method: POST
- Path: `/api/shelves`
- Request JSON:
```json
{ "name": "Sypialnia górna półka" }
```
- Response JSON (201): Shelf JSON
- Errors:
  - 422 (empty/too long name: 1–50)
  - 409 (duplicate name case-insensitive)

3) Delete shelf
- Method: DELETE
- Path: `/api/shelves/{id}`
- Success:
  - 204 No Content (deleted)
  - 409 Conflict (if books exist) with body:
```json
{
  "type": "https://example.com/errors/shelf-not-empty",
  "title": "Shelf not empty",
  "status": 409,
  "detail": "Shelf contains 12 books.",
  "booksCount": 12
}
```
- Errors:
  - 403 (cannot delete system shelf)
  - 404

### 2.4 Books

Book JSON shape (with embedded shelf and genres for client convenience):
```json
{
  "id": "uuid",
  "title": "Wiedźmin",
  "author": "Andrzej Sapkowski",
  "isbn": "9781234567890",
  "pageCount": 384,
  "source": "manual",
  "recommendationId": null,
  "shelf": { "id": "uuid", "name": "Do zakupu", "isSystem": true },
  "genres": [ { "id": 1, "name": "Fantasy" } ],
  "createdAt": "2025-10-15T12:00:00Z",
  "updatedAt": "2025-10-15T12:00:00Z"
}
```

1) List books
- Method: GET
- Path: `/api/books`
- Query:
  - `q` (string; case-insensitive search on `title` OR `author`)
  - `shelfId` (UUID)
  - `genreIds` (comma-separated integers; OR semantics)
  - `limit`, `offset` (pagination)
  - `sort` in { `title`, `author`, `createdAt` }, `order` in { `asc`, `desc` } (default: `createdAt desc`)
- Response JSON (200):
```json
{
  "data": [ { /* Book JSON */ } ],
  "meta": { "total": 47, "limit": 20, "offset": 0 }
}
```
- Notes:
  - Walidacja parametrów (`ListBooksParameterValidator`) odrzuca niepoprawne wartości i zwraca RFC 7807 z listą błędów.
  - Zapytania OR filtrują po `shelfId`, `q`, `genreIds` i zwracają metadane paginacji.
  - Wynik zawiera zagnieżdżone `shelf` oraz `genres` dzięki `BookResource`.
- Errors:
  - 400 (Problem Details) dla błędnych parametrów (`Invalid query parameter`).
  - 401 brak autoryzacji.

2) Create book
- Method: POST
- Path: `/api/books`
- Request JSON:
```json
{
  "title": "Wiedźmin",
  "author": "Andrzej Sapkowski",
  "shelfId": "uuid",
  "genreIds": [1, 12],
  "isbn": "9781234567890",
  "pageCount": 384
}
```
- Response JSON (201): Book JSON (with `source = "manual"`)
- Errors:
  - 422 (validation: required fields, title/author length 1–255; `genreIds` size 1–3; `isbn` format 10 or 13 digits; `pageCount` 1–50000)
  - 404 (shelf or genre not found)
3) Delete book
- Method: DELETE
- Path: `/api/books/{id}`
- Success: 204 No Content
- Errors: 404

### 2.5 AI Recommendations

Recommendation Event JSON (storage and response):
```json
{
  "id": 123,
  "createdAt": "2025-10-15T12:00:00Z",
  "userId": "uuid",
  "inputTitles": ["Wiedźmin Andrzej Sapkowski", "Ursula Le Guin"],
  "recommended": [
    { "tempId": "r1", "title": "...", "author": "...", "genresId": [1, 12], "reason": "1–2 sentence justification" }
  ],
  "acceptedBookIds": ["uuid"]
}
```

1) Generate recommendations
- Method: POST
- Path: `/api/ai/recommendations/generate`
- Description: Calls OpenRouter provider, persists event, returns 3 proposals.
- Request JSON:
```json
{
  "inputs": ["Wiedźmin Andrzej Sapkowski", "Ursula Le Guin"],
  "excludeTitles": ["Wiedźmin"],
  "model": "openrouter/openai/gpt-4o-mini"
}
```
- Response JSON (201): Recommendation Event JSON with exactly 3 `recommended` items.
- Errors:
  - 422 (no inputs)
  - 502 Bad Gateway (provider error)
  - 504 Gateway Timeout (>30s)

2) Accept a recommendation (update acceptedBookIds only)
- Method: POST
- Path: `/api/ai/recommendations/{eventId}/accept`
- Description: Adds a book ID to acceptedBookIds for a given recommendation event. Book creation must be done up front using the standard POST `/api/books` endpoint (with proper fields including `source = "ai_recommendation"` and `recommendationId = eventId`). This endpoint only updates the recommendation event after successful book creation.
- Request JSON:
```json
{
  "bookId": "uuid"  // The ID of the newly created book (added via POST /api/books)
}
```
- Response JSON (200): `{ "event": { "id": 123, "acceptedBookIds": ["uuid", "..."] } }`
- Idempotency: Supported via `Idempotency-Key` header to prevent duplicate additions on retries.
- Errors:
  - 404 (event not found, or bookId not found, or book does not reference the correct eventId)
  - 409 (already added this bookId to acceptedBookIds with this idempotency key)
  - 400 (book does not match original recommendation or is not linked with correct source/recommendationId)

<!-- ### 2.6 Analytics

1) MVP success metric
- Method: GET
- Path: `/api/analytics/ai/mvp-success`
- Query:
  - `from` (ISO timestamp, optional)
  - `to` (ISO timestamp, optional)
- Response JSON (200):
```json
{
  "successPercent": 78.5,
  "generatedCount": 28,
  "acceptedAtLeastOneCount": 22,
  "window": { "from": "2025-10-01T00:00:00Z", "to": "2025-10-31T23:59:59Z" }
}
``` -->

## 3. Authentication and Authorization

- Mechanism: Symfony session-based auth with secure, HTTP-only cookies; CSRF required for state-changing requests via `X-CSRF-Token` header.
- Passwords: stored using modern password hashing (e.g., bcrypt/argon2id) in `password_hash`.
- Access control: All API endpoints require authentication except `/api/auth/*` and read-only `/api/genres`.
- Multi-user model: No roles in MVP; all authenticated users have equal privileges across shared library.
- CORS: Not required for same-origin Twig frontend; if exposed cross-origin, restrict origins/methods and require CSRF double-submit.
- Rate limiting:
  - Default: 300 req/min per authenticated user and IP.
  - AI endpoints: 30 req/min per user; hard timeouts at 30s with graceful error messages.

## 4. Validation and Business Logic

Validation (enforced in API layer; DB constraints backstop):

- Users
  - `email`: required; length 3–255; valid format; unique (case-insensitive).
  - `password`: required on register; length ≥ 8; `passwordConfirm` must match.

- Shelves
  - `name`: required; length 1–50; unique case-insensitive.
  - Cannot UPDATE/DELETE shelves where `isSystem = true` → return 403.
  - DELETE behavior: if shelf has books provided → 409 with `booksCount`.

- Books
  - `title`, `author`: required; length 1–255.
  - `shelfId`: required; must reference existing shelf.
  - `genreIds`: required on create; 1–3 integers referencing existing genres; duplicates rejected.
  - `isbn`: optional; must be 10 or 13 digits if provided.
  - `pageCount`: optional; integer 1–50000 if provided.
  - `source`: set by system (`manual` on normal create; `ai_recommendation` on acceptance).

- AI Recommendation Events
  - `inputs`: array of ≥ 1 non-empty strings.
  - Do providera przekazywany jest katalog gatunków `{ id, name }` w celach audytowych/deskrypcyjnych (bez zewnętrznego połączenia), a odpowiedzi rekomendacji zawierają `genresId` (1–3 wartości 1–15).
  - Provider prompt must exclude titles already in library.
  - On accept: create `books` with `source = 'ai_recommendation'` and `recommendation_id = {eventId}`; append created `book.id` to `accepted_book_ids` (jsonb array) atomically.
  - Default shelf for acceptance is the system shelf named "Do zakupu"; ensure it exists (seeded) or create if missing.

Business Logic Mapping
- System shelf protection: attempts to update/delete → 403, message: "Shelf is system-protected and cannot be updated/deleted".
- Genre linking: manage `book_genre` rows on create; on book delete, relations cascade (DB `ON DELETE CASCADE`).
- Search: `q` applies to `books.title ILIKE '%q%' OR books.author ILIKE '%q%'`; recommend pg_trgm index for performance.
- Sorting whitelist to avoid SQL injection; default `created_at DESC`.
<!-- - Analytics: compute success metric per PRD definition with SQL equivalent and optional date bounds. -->

Error Responses (examples)
```json
{
  "type": "https://example.com/errors/validation",
  "title": "Validation Failed",
  "status": 422,
  "errors": {
    "title": ["This field is required"],
    "genreIds": ["Select between 1 and 3 genres"]
  }
}
```

Security & Performance Considerations
- Use database indexes:
  - `books (shelf_id)` for shelf filters.
  - `book_genre (book_id)`, `(genre_id)` for joins.
  - `users (lower(email))`, `shelves (lower(name))`, `genres (lower(name))` unique indexes.
  - Optional `pg_trgm` GIN indexes on `books.title` and `books.author` to accelerate search.
- Enforce rate limits, especially on AI endpoints; circuit-break on provider failures; exponential backoff on 429s from provider.
- Idempotency for AI acceptance to prevent duplicate book creation on retries.
- Wrap multi-step operations in DB transactions (shelf delete with transfer, AI accept book + event update).
- Return ETags for GET responses; support `If-None-Match` for cache efficiency (optional enhancement).

Response Field Reference
- User: `{ id, email, createdAt }`
- Shelf: `{ id, name, isSystem, createdAt, updatedAt }`
- Genre: `{ id, name }`
- Book: `{ id, title, author, isbn, pageCount, source, recommendationId, shelf, genres, createdAt, updatedAt }`
- Recommendation Event: `{ id, createdAt, userId, inputTitles, recommended[{ tempId, title, author, genresId[], reason }], acceptedBookIds }`


