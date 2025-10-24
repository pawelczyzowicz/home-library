# API Endpoint Implementation Plan: Auth (Register, Login, Logout, Me)

## 0. Encja wraz z migracją do bazy danych

- Baza: PostgreSQL
- Tabela: `users`
- Klucz: `uuid` (ramsey/uuid-doctrine)
- Indeksy: unikalność e-mail case-insensitive (unikalny indeks na `lower(email)`), indeks po `created_at`
- Hasła: `password_hash` (algorytm zgodny z `security.password_hashers: auto` – bcrypt/argon2*)

Proponowana struktura tabeli (PostgreSQL):
```sql
CREATE TABLE users (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  email VARCHAR(255) NOT NULL CHECK (char_length(email) BETWEEN 3 AND 255),
  password_hash TEXT NOT NULL,
  roles JSONB NOT NULL DEFAULT '[]'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Unikalność case-insensitive (login po lower(email))
CREATE UNIQUE INDEX users_email_lower_uindex ON users (lower(email));
CREATE INDEX users_created_at_idx ON users (created_at);
```

Encja (DDD, Doctrine ORM 3) – `App\HomeLibrary\Domain\User\User`:
- Implementuje `Symfony\Component\Security\Core\User\UserInterface` i `PasswordAuthenticatedUserInterface`
- Pola: `id` (UuidInterface), `email` (string – przechowywany i porównywany w lowercase), `passwordHash` (string), `roles` (array – domyślnie `['ROLE_USER']`), `createdAt`, `updatedAt`
- Inwarianty: poprawny email, min. wymagania dla hasła spełnione na etapie rejestracji, `roles` zawiera co najmniej `ROLE_USER`

Migracja Doctrine: `migrations/VersionYYYYMMDDHHMMSS.php`
- Tworzy tabelę `users` i indeksy jak powyżej
- Jeżeli potrzeba: `CREATE EXTENSION IF NOT EXISTS pgcrypto;` (dla `gen_random_uuid()`)

## 1. Przegląd punktu końcowego

- Autentykacja i autoryzacja oparte o Symfony Security – sesyjne, same-origin
- Ciasteczka sesyjne: Secure (auto), HttpOnly, SameSite=Lax
- Endpoints:
  - POST `/api/auth/register` – rejestracja + start sesji (201)
  - POST `/api/auth/login` – logowanie JSON (200)
  - POST `/api/auth/logout` – wylogowanie (204)
  - GET `/api/auth/me` – bieżący użytkownik (200; 401 gdy brak sesji)

## 2. Szczegóły żądania

- Wspólne wymagania
  - `Content-Type: application/json` dla POST
  - CSRF: nagłówek `X-CSRF-Token` z tokenem o `token_id: authenticate` (dla `register`, `login`) i `logout` (token_id: `logout`) – zgodnie z `config/packages/csrf.yaml`
  - Sesja: cookie ustawiane przez Symfony (HttpOnly, SameSite=Lax)

- Register (POST `/api/auth/register`)
  - Wymagane parametry: `email` (string), `password` (string, min 8), `passwordConfirm` (string)
  - Walidacje: format i długość e-mail; hasło min 8; zgodność `password` i `passwordConfirm`

- Login (POST `/api/auth/login`)
  - Wymagane parametry: `email` (string), `password` (string)
  - Walidacje: obecność pól, format e-mail (lekka walidacja), brak dodatkowych pól

- Logout (POST `/api/auth/logout`)
  - Bez body; wymaga aktywnej sesji i CSRF

- Me (GET `/api/auth/me`)
  - Bez body; wymaga aktywnej sesji

- Wykorzystywane typy (DTO/Command, Application)
  - `RegisterUserCommand` (id: UuidInterface, email: string, password: string, passwordConfirm: string)
  - `UserView` (id: uuid, email: string, createdAt: string)

## 3. Szczegóły odpowiedzi

- Sukces
  - Register (201) oraz Login (200):
    ```json
    {
      "user": {
        "id": "uuid",
        "email": "user@example.com",
        "createdAt": "2025-10-15T12:00:00Z"
      }
    }
    ```
  - Logout (204): puste body
  - Me (200): jak powyżej

- Błędy
  - 400 Bad Request – zły `Content-Type` lub nieprawidłowy JSON
  - 401 Unauthorized – niepoprawne dane logowania lub brak sesji (dla `/me`)
  - 409 Conflict – e-mail już zajęty (rejestracja)
  - 422 Unprocessable Entity – błędy walidacji wejścia
  - 500 Internal Server Error – błąd serwera

## 4. Przepływ danych

- Register
  1) `RegisterAction` (UI/API) sprawdza `Content-Type`, CSRF i parsuje JSON
  2) Tworzy `RegisterUserCommand` i wywołuje `RegisterUserHandler`
  3) Handler: normalizuje e-mail do lowercase, waliduje, hashuje hasło (`UserPasswordHasherInterface`), zapisuje przez `UserRepository`
  4) Programowy login użytkownika po sukcesie i 201 z `UserResource`

- Login
  1) Firewall `json_login` obsługuje body `{email, password}` + CSRF
  2) Provider ładuje użytkownika po `lower(email)`; hasher weryfikuje hasło
  3) `success_handler` zwraca 200 + `{ user: ... }`, sesja ustawiona w cookie

- Logout
  1) `logout` w firewallu – unieważnia sesję i czyści cookie
  2) Zwraca 204

- Me
  1) Akcja odczytuje `Security/TokenStorage` i zwraca `user` lub 401

## 5. Względy bezpieczeństwa

- Sesje: `framework.session` skonfigurowana z `cookie_secure: auto`, `cookie_httponly: true`, `cookie_samesite: lax`
- CSRF: tokeny (`authenticate`, `logout`) – nagłówek `X-CSRF-Token`
- Brute force: `login_throttling` (np. `max_attempts: 5`, `interval: '1 minute'`)
- Hashowanie: `password_hashers: auto` + `UserPasswordHasherInterface`
- Dostęp: `access_control` – `/api/auth/me` wymaga `IS_AUTHENTICATED_FULLY`
- Dane: normalizacja e-mail do lowercase; nie logujemy haseł i ich hashy

## 6. Obsługa błędów

- Reuse istniejącego `App\HomeLibrary\UI\Api\ExceptionListener` – dodać mapowania:
  - `DuplicateEmailException` → 409 Conflict
  - `ValidationException` → 422 Unprocessable Entity (już jest)
  - `AuthenticationException` (jeśli nie obsłuży Security) → 401 Unauthorized
- Treść błędów w formacie problem+json (jak w projekcie)
- Logowanie: Monolog (plik).

## 7. Wydajność

- Indeksy: `lower(email)` (unikalny), `created_at`
- Ograniczenie kosztów: throttling logowania, brak nadmiarowych zapytań
- Minimalne payloady JSON; brak PII w logach

## 8. Kroki implementacji

1) Domain (DDD)
- Dodaj `src/HomeLibrary/Domain/User/User.php` (encja Doctrine) oraz `UserRepository` (interfejs)
- VO `UserEmail` z walidacją formatu i normalizacją

2) Infrastructure
- Implementuj `DoctrineUserRepository` z metodami: `save`, `findByEmail(string $emailLower)`, `existsByEmail(string $emailLower)`
- Zapewnij zapisywanie `email` w lowercase

3) Application
- Utwórz `RegisterUserCommand` i `RegisterUserHandler`
  - Walidacje: format e-mail, długość (3–255), hasło min 8, zgodność `passwordConfirm`
  - Konflikt: jeśli `existsByEmail` → rzuć `DuplicateEmailException`
  - Hashowanie: `UserPasswordHasherInterface` → `password_hash`

4) UI (API)
- Folder `src/HomeLibrary/UI/Api/Auth/`
  - `RegisterAction` (POST `/api/auth/register`): walidacja `Content-Type`, CSRF, JSON → handler → programowy login → 201 `{ user: ... }`
  - `MeAction` (GET `/api/auth/me`): zwraca `user` z `Security` lub 401
  - `UserResource`: mapowanie encji `User` na strukturę JSON wymaganą przez spec

5) Security (config/packages/security.yaml)
- `providers.app_user_provider: entity: { class: App\HomeLibrary\Domain\User\User, property: email }`
- `firewalls.main`:
  - `lazy: true`
  - `provider: app_user_provider`
  - `login_throttling: { max_attempts: 5, interval: '1 minute' }`
  - `json_login`:
    - `check_path: /api/auth/login`
    - `username_path: email`, `password_path: password`
    - `csrf_token_id: authenticate`
    - `success_handler: App\HomeLibrary\UI\Api\Auth\JsonLoginSuccessHandler`
    - `failure_handler: App\HomeLibrary\UI\Api\Auth\JsonLoginFailureHandler`
  - `logout: { path: /api/auth/logout, invalidate_session: true }`
- `access_control`:
  - `- { path: ^/api/auth/me$, roles: IS_AUTHENTICATED_FULLY }`

6) Framework (config/packages/framework.yaml)
- Ustawienia sesji: `cookie_secure: auto`, `cookie_httponly: true`, `cookie_samesite: lax`

7) CSRF (config/packages/csrf.yaml)
- Potwierdź `stateless_token_ids: [authenticate, logout]`

8) Handlery logowania (UI)
- `JsonLoginSuccessHandler` – 200 i `{ user: ... }` (z `UserResource`), sesja ustawiona przez Security
- `JsonLoginFailureHandler` – 401 z problem+json

9) Migracje
- Wygeneruj migrację `users` + indeks `lower(email)` i `created_at`
- Uruchom w `dev` i `test`

10) Testy
- Unit: walidacje w `RegisterUserHandler`
- Integracyjne: repozytorium (unikalność e-mail), hashowanie
- E2E (Panther): rejestracja→auto-login, logowanie, `/me`, wylogowanie (statusy, cookie)

11) Dokumentacja
- Zaktualizuj `README` (CSRF – `X-CSRF-Token`, kontrakty JSON, statusy)
