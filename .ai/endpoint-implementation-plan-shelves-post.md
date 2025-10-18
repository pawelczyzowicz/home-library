## API Endpoint Implementation Plan: POST /api/shelves

### 1. Przegląd punktu końcowego
Punkt końcowy służący do tworzenia nowej półki ("shelf") w systemie biblioteki domowej. Zwraca utworzony zasób oraz odpowiednie kody błędów w przypadku niepowodzeń.

### 2. Szczegóły żądania
- **Metoda HTTP**: POST
- **Struktura URL**: `/api/shelves`
- **Nagłówki**:
  - **Content-Type**: `application/json; charset=utf-8`
  - **Accept**: `application/json`
  - **Authorization**: wg konfiguracji `security.yaml` (np. Bearer JWT / sesja) – endpoint wymaga uwierzytelnienia
- **Parametry**:
  - **Wymagane**: `name` (string)
  - **Opcjonalne**: brak
- **Request body**:
```json
{ "name": "Sypialnia górna półka" }
```
- **Walidacja wejścia**:
  - `name`: wymagane, typ `string`, po `trim()` długość 1–50 (znaki), walidacja case-insensitive dla duplikatów

### 3. Szczegóły odpowiedzi
- **201 Created**: zwraca JSON nowo utworzonej półki
  - Przykład:
```json
{
  "id": "1c0f1a0e-9f2f-4a2d-8d0f-8d2c9e8a1b23",
  "name": "Sypialnia górna półka",
  "is_system": false,
  "created_at": "2025-10-17T12:00:00Z",
  "updated_at": "2025-10-17T12:00:00Z"
}
```
- **Błędy**:
  - **409 Conflict**: duplikat nazwy (case-insensitive)
  - **422 Unprocessable Entity**: puste / zbyt długie `name` (1–50)
  - **400 Bad Request**: błędny JSON / brak `Content-Type: application/json`
  - **401 Unauthorized**: brak poprawnego uwierzytelnienia
  - **500 Internal Server Error**: nieoczekiwany błąd serwera

### 4. Przepływ danych
1. Klient wysyła `POST /api/shelves` z JSON i nagłówkami.
2. Warstwa HTTP (Symfony Controller) parsuje i wstępnie waliduje JSON.
3. Dane przekazywane są do serwisu domenowego `ShelfService` w postaci DTO.
4. Serwis:
   - normalizuje wejście (trim),
   - wykonuje walidację (Symfony Validator),
   - sprawdza duplikaty (case-insensitive) via repozytorium/DB,
   - persistuje encję przez Doctrine,
   - obsługuje kolizje unikatowości (mapowanie wyjątku DB na 409).
5. Zwracany jest `JsonResponse` z 201 i danymi encji lub odpowiedni błąd.

### 5. Względy bezpieczeństwa
- **Uwierzytelnienie**: endpoint chroniony (np. `IS_AUTHENTICATED_FULLY`).
- **Autoryzacja**: brak różnic ról w specyfikacji; możliwa przyszła polityka RBAC.
- **CSRF**: nie dotyczy typowego API JSON (wyłączone dla ścieżek `/api/*`).
- **Rate limiting**: limit via `symfony/rate-limiter` dla ścieżki `/api/shelves`.
- **Walidacja Content-Type**: wymagaj `application/json`.
- **Ochrona DB**: unikatowy indeks na `lower(name)` (case-insensitive) w Postgres; obsługa wyjątku.
- **Logowanie**: Monolog (poziomy: info dla sukcesu, warning dla 4xx, error dla 5xx). Opcjonalnie audit log.

### 6. Obsługa błędów
- **Format błędów**: `application/problem+json` (RFC 7807) lub zunifikowany JSON:
```json
{
  "status": 422,
  "code": "validation_error",
  "message": "Invalid request",
  "errors": { "name": ["This value should not be blank."] }
}
```
- **Mapowanie błędów**:
  - Walidacja: 422 (lub 400 jeśli polityka projektu preferuje 400)
  - Duplikat: 409
  - Zły JSON / Content-Type: 400
  - Brak auth: 401
  - Nieoczekiwane: 500 (z korelacją `X-Request-Id`)
- **Rejestracja błędów**: Monolog + kanał API. Jeśli istnieje tabela błędów, dodać listener doktrynalny, który zapisuje kluczowe metadane (route, payload hash, status, trace id).

### 7. Rozważania dotyczące wydajności
- Indeks unikatowy na `lower(name)` eliminuje koszt dodatkowego SELECT (można polegać wyłącznie na DB i obsłudze wyjątku).
- Krótka transakcja INSERT -> minimalna blokada.
- Przewidzieć ponawianie żądania przez klienta: błędy idempotencji ograniczone przez 409.
- Logi skompresowane (async handler) w środowisku prod.

### 8. Kroki implementacji
1. **Encja i repozytorium** (jeśli brak):
   - Dodać encję `Shelf` z polami: `id` (uuid), `name` (string 50), `is_system` (bool, domyślnie false), `createdAt`, `updatedAt` (lifecycle callbacks).
   - Repozytorium `ShelfRepository` z metodami pomocniczymi (opcjonalnie).
2. **Migracja bazy**: unikatowość case-insensitive
   - SQL (PostgreSQL):
```sql
CREATE UNIQUE INDEX IF NOT EXISTS shelves_name_ci_unique ON shelves (lower(name));
```
   - Dodać migrację Doctrine generującą powyższy indeks (i `DROP INDEX` w down()).
3. **DTO / Request model**: `CreateShelfRequest`
   - Pola: `name: string`
   - Adnotacje walidatora: `NotBlank`, `Type("string")`, `Length(min=1, max=50)`
   - Normalizacja: `trim()` w fabryce/konstruktorze DTO lub w serwisie
4. **Serwis domenowy**: `ShelfService`
   - `public function create(string $name): Shelf`
   - Kroki: `trim` -> walidacja (ValidatorInterface) -> `persist` + `flush`
   - Złap `UniqueConstraintViolationException` i rzuca domenowy `DuplicateShelfNameException`
5. **Kontroler API**: `Api\ShelfController::create()`
   - Route: `#[Route('/api/shelves', name: 'api_shelves_create', methods: ['POST'])]`
   - Parsowanie: `Request::toArray()` z obsługą `JsonException`
   - Wywołanie serwisu: `ShelfService->create($dto->name)`
   - Zwróć `JsonResponse($serializer->normalize($shelf), 201)` lub manualne mapowanie
6. **Mapowanie błędów**:
   - `ExceptionListener`/`KernelExceptionListener` mapuje:
     - `ValidationException` -> 422
     - `DuplicateShelfNameException` -> 409
     - `JsonException`/`BadRequestHttpException` -> 400
     - inne -> 500
   - Zwraca `application/problem+json`
7. **Bezpieczeństwo**:
   - Dodać reguły w `security.yaml` dla `/api/*` (wymóg auth)
   - (Opcjonalnie) rate-limiter na klucz IP/użytkownika
8. **Serializacja**:
   - Skonfigurować `Serializer` (Symfony) lub zwracać z mapowania do tablicy
   - Upewnić się, że daty są w ISO 8601 (UTC)
9. **Obsługa logów i obserwowalności**:
    - Monolog: kanał `api`
    - Dodanie `X-Request-Id` i korelacja w logach

### Założenia i zgodność ze stackiem
- Backend: Symfony + Doctrine + PostgreSQL, walidacja: Symfony Validator, logowanie: Monolog.
- CI/CD: GitHub Actions buduje i uruchamia testy.
- Zgodność z zasadami: separacja logiki (Controller cienki, serwis domenowy), unikamy logiki w kontrolerze, walidacja wejścia, mapowanie błędów na kody HTTP.
