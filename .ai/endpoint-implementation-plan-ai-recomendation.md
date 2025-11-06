## API Endpoint Implementation Plan: AI Recommendations

## 1. Przegląd punktu końcowego
- Cel: dostarczyć rekomendacje książek na podstawie wejściowych tytułów/autorów oraz umożliwić akceptację wybranych propozycji poprzez powiązanie z istniejącymi rekordami książek.
- Endpoints:
  - POST `/api/ai/recommendations/generate` — generuje i zapisuje zdarzenie rekomendacji, zwraca dokładnie 3 propozycje (mock; brak realnego połączenia z OpenRouter).
  - POST `/api/ai/recommendations/{eventId}/accept` — dodaje `bookId` do `acceptedBookIds` dla wskazanego eventu, po uprzednim utworzeniu książki przez POST `/api/books` z `source = "ai_recommendation"` i `recommendationId = eventId`.

## 2. Szczegóły żądania
### 2.1 Generate Recommendations
- **Metoda**: POST
- **URL**: `/api/ai/recommendations/generate`
- **Nagłówki**:
  - `Content-Type: application/json`
  - `Authorization: Bearer ...` lub inny mechanizm sesji wg konfiguracji Symfony Security
- **Parametry**: brak parametrów ścieżki ani query
- **Body (JSON)**:
```json
{
  "inputs": ["Wiedźmin Andrzej Sapkowski", "Ursula Le Guin"],
  "excludeTitles": ["Wiedźmin"],
  "model": "openrouter/openai/gpt-4o-mini"
}
```
- **Wymagane**:
  - `inputs`: non-empty array of non-empty strings
- **Opcjonalne**:
  - `excludeTitles`: array of strings (domyślnie pusty)
  - `model`: string (przechowywany/audytowany, ale nie używany do realnego wywołania)
- **Ograniczenia**:
  - Maksymalny czas wykonania: 30s (w przypadku mocka i tak egzekwujemy timeout na warstwie serwisu)
  - Deduplikacja i trimming wejść po stronie walidacji serwisowej

### 2.2 Accept Recommendation
- **Metoda**: POST
- **URL**: `/api/ai/recommendations/{eventId}/accept`
- **Parametry ścieżki**:
  - `eventId`: integer > 0
- **Nagłówki**:
  - `Content-Type: application/json`
  - `Authorization: Bearer ...`
  - `Idempotency-Key: <string>` (opcjonalny; zapobiega podwójnym dopisaniom przy retry)
- **Body (JSON)**:
```json
{
  "bookId": "uuid"
}
```
- **Wymagane**:
  - `bookId`: UUID istniejącej książki, która została utworzona przez POST `/api/books` z `source = "ai_recommendation"` i `recommendationId = eventId`

## 3. Wykorzystywane typy
### 3.1 DTOs (Request/Response)
- `GenerateRecommendationsRequestDTO`:
  - `array<string> inputs`
  - `array<string> excludeTitles|null`
  - `string|null model`
- `RecommendationProposalDTO`:
  - `string tempId`
  - `string title`
  - `string author`
  - `array<int> genresId` (1–3 wartości; zakres 1–15)
  - `string reason`
- `RecommendationEventResponseDTO` (201 na generate):
  - `int id`
  - `string createdAt` (ISO8601, UTC)
  - `string|null userId` (UUID)
  - `array<string> inputTitles`
  - `array<RecommendationProposalDTO> recommended` (zawsze 3)
  - `array<string> acceptedBookIds` (UUID)
- `AcceptRecommendationRequestDTO`:
  - `string bookId` (UUID)
- `AcceptRecommendationResponseDTO` (200 na accept):
  - `object event { id: int, acceptedBookIds: array<string> }`

### 3.2 Command modele (Application layer)
- `GenerateRecommendationsCommand`:
  - `string|null userId`
  - `array<string> inputTitles`
  - `array<string> excludeTitles`
  - `string|null model`
- `AcceptRecommendationCommand`:
  - `int eventId`
  - `string bookId`
  - `string|null idempotencyKey`
  - `string|null userId`

### 3.3 Domain
- Encja `AiRecommendationEvent` (aggregate root):
  - `int id`
  - `\DateTimeImmutable createdAt`
  - `UuidInterface|null userId`
  - `array<string> inputTitles`
  - `array<RecommendationProposal> recommended` (dokładnie 3 elementy)
  - `array<UuidInterface> acceptedBookIds`
  - Metody: `static create(...)`, `acceptBook(UuidInterface $bookId)`, strażniki niezmienników
- Value Object `RecommendationProposal` (tempId/title/author/reason)
  - zawiera również `genresId: array<int>` (1–3 wartości; zakres 1–15)
- Interfejs repozytorium `RecommendationEventRepository`

### 3.4 Provider (mock only)
- `IRecommendationProvider` z metodą `generate(array $inputs, array $excludeTitles): array<RecommendationProposal>`
- `MockOpenRouterRecommendationProvider` — deterministycznie zwraca 3 propozycje (każda z `genresId` 1–3 w zakresie 1–15); bez zewnętrznych wywołań

### 3.5 Repositories (read/support)
- `BookReadRepository` — odczyt książki po `bookId` i walidacja `source`/`recommendationId`
- `IdempotencyRepository` (Infra) — zapis/odczyt kluczy idempotencyjnych

## 4. Szczegóły odpowiedzi
### 4.1 Generate (201)
```json
{
  "id": 123,
  "createdAt": "2025-10-15T12:00:00Z",
  "userId": "uuid",
  "inputTitles": ["Wiedźmin Andrzej Sapkowski", "Ursula Le Guin"],
  "recommended": [
    { "tempId": "r1", "title": "...", "author": "...", "genresId": [1, 12], "reason": "..." },
    { "tempId": "r2", "title": "...", "author": "...", "genresId": [3], "reason": "..." },
    { "tempId": "r3", "title": "...", "author": "...", "genresId": [5, 7, 9], "reason": "..." }
  ],
  "acceptedBookIds": []
}
```

### 4.2 Accept (200)
```json
{ "event": { "id": 123, "acceptedBookIds": ["uuid", "..."] } }
```

### 4.3 Błędy (przykłady)
- 400: `{ "error": "Invalid input", "details": [...] }`
- 401: `{ "error": "Unauthorized" }`
- 404: `{ "error": "Not found" }`
- 409: `{ "error": "Conflict" }`
- 502: `{ "error": "Provider error" }`
- 504: `{ "error": "Timeout" }`
- 500: `{ "error": "Server error", "id": "traceId" }`

## 5. Przepływ danych
### 5.1 Generate
1) Kontroler parsuje DTO i waliduje dane (inputs ≥ 1, strings). 2) Serwis `AiRecommendationService` wywołuje `IRecommendationProvider` (mock) z timeoutem 30s. 3) Tworzy `AiRecommendationEvent` z 3 propozycjami (każda zawiera `genresId`). 4) Zapis przez `RecommendationEventRepository` (Doctrine → `ai_recommendation_events`). 5) Zwraca 201 z pełnym eventem.

### 5.2 Accept
1) Kontroler parsuje `bookId`, odczytuje `eventId` i `Idempotency-Key`. 2) Odczyt eventu; weryfikacja własności przez `userId`. 3) `BookReadRepository` potwierdza istnienie książki i dopasowanie `source="ai_recommendation"` oraz `recommendationId=eventId`. 4) Sprawdzenie idempotency: jeśli `Idempotency-Key` już zarejestrowany dla (eventId, bookId) → 409. 5) `AiRecommendationEvent->acceptBook(bookId)` (unikalność listy). 6) Zapis eventu + rejestracja idempotency (transakcja). 7) Zwraca 200 z `{ event: { id, acceptedBookIds } }`.

## 6. Względy bezpieczeństwa
- **Uwierzytelnianie**: wymagane (`IS_AUTHENTICATED_FULLY`).
- **Autoryzacja zasobów**: event dostępny wyłącznie dla właściciela (`userId`). Przy braku zgodności zwracamy 404, by nie ujawniać istnienia zasobu.
- **Rate limiting**: limiter dla `POST /generate` i `POST /{eventId}/accept` (np. 10/min per user/IP) w `RateLimiter` Symfony.
- **Walidacja i sanityzacja**: trimming, ograniczenia długości, blokada pustych stringów, kontrola rozmiaru JSON.
- **Idempotency**: nagłówek `Idempotency-Key` plus trwałe przechowywanie kluczy dla par (eventId, bookId).
- **Audyt/Logging**: Monolog (kanał `ai`) z `eventId`, `userId`, `traceId`.

## 7. Obsługa błędów
- 400 Bad Request: puste `inputs`, niepoprawny `bookId`, brak dopasowania `source/recommendationId`, nieprawidłowe typy.
- 401 Unauthorized: brak poprawnej sesji/tokena.
- 404 Not Found: brak eventu lub książki (albo brak własności zasobu przez usera).
- 409 Conflict: już zaakceptowano ten `bookId` dla eventu (duplikat lub ten sam `Idempotency-Key`).
- 502 Bad Gateway: błąd provider (mock może symulować awarie na życzenie testów).
- 504 Gateway Timeout: przekroczony limit 30s.
- 500 Internal Server Error: pozostałe wyjątki — log + generyczny komunikat.

## 8. Rozważania dotyczące wydajności
- **Baza**: indeksy na `ai_recommendation_events(user_id, created_at)`; ewentualnie `GIN` dla pól `jsonb` jeśli potrzebne filtrowanie.
- **Transakcje**: akceptacja w transakcji (update eventu + zapis idempotency).
- **Serializacja**: lekkie DTO, brak zbędnych pól. `recommended` zawsze 3 elementy.
- **Timeouty**: twardy limit 30s na generowanie nawet dla mocka (przyszła wymienialność providera).
- **Konkurencja**: optymistyczna kontrola wersji eventu lub unikalność na (event_id, book_id) po stronie idempotency.

## 9. Etapy wdrożenia
1) **Baza danych (Doctrine Migrations)**
   - Tabela `ai_recommendation_events`:
     - `id SERIAL PRIMARY KEY`
     - `created_at timestamptz NOT NULL DEFAULT now()`
     - `user_id uuid NULL REFERENCES users(id)`
     - `input_titles jsonb NOT NULL`
     - `recommended_book_ids jsonb NOT NULL DEFAULT '[]'::jsonb` (przechowuje 3 obiekty propozycji)
     - `accepted_book_ids jsonb NOT NULL DEFAULT '[]'::jsonb`
     - Indeksy: `(user_id, created_at)`
   - Tabela `ai_recommendation_accept_requests` (idempotency):
     - `id SERIAL PRIMARY KEY`
     - `event_id int NOT NULL REFERENCES ai_recommendation_events(id) ON DELETE CASCADE`
     - `book_id uuid NOT NULL`
     - `idempotency_key varchar(128) NOT NULL`
     - `created_at timestamptz NOT NULL DEFAULT now()`
     - Unikalny indeks: `(event_id, idempotency_key)` oraz `(event_id, book_id)`

2) **Domain (`src/HomeLibrary/Domain/AI/`)**
   - `AiRecommendationEvent.php` (encja/aggregate)
   - `RecommendationProposal.php` (VO)
   - `RecommendationEventRepository.php` (interfejs)

3) **Application (`src/HomeLibrary/Application/AI/`)**
   - `GenerateRecommendationsCommand.php`, `AcceptRecommendationCommand.php`
   - `AiRecommendationService.php` (metody: `generate(GenerateRecommendationsCommand)`, `accept(AcceptRecommendationCommand)`)
   - DTOs: `GenerateRecommendationsRequestDTO.php`, `AcceptRecommendationRequestDTO.php`, `RecommendationProposalDTO.php`, `RecommendationEventResponseDTO.php`, `AcceptRecommendationResponseDTO.php`
   - `IRecommendationProvider.php`

4) **Infrastructure (`src/HomeLibrary/Infrastructure/AI/`)**
   - Doctrine mapping/encja dla `AiRecommendationEvent` (attribs `#[ORM\...]`)
   - `DoctrineRecommendationEventRepository.php`
   - `MockOpenRouterRecommendationProvider.php` (zwraca deterministycznie 3 propozycje, bez zewnętrznych wywołań)
   - `DoctrineIdempotencyRepository.php` dla `ai_recommendation_accept_requests`

5) **UI (API Controller) (`src/HomeLibrary/UI/AI/`)**
   - `ApiAiRecommendationsController.php` z dwoma akcjami:
     - `generate()` → mapowanie POST `/api/ai/recommendations/generate`
     - `accept(int $eventId)` → mapowanie POST `/api/ai/recommendations/{eventId}/accept`
   - Walidacja przez Symfony Validator (constraints na DTO) i manualne strażniki biznesowe.
   - Budowa odpowiedzi JSON (201/200) i mapowanie wyjątków → kody 4xx/5xx.

6) **Konfiguracja**
   - `config/routes.yaml` lub atrybuty Route w kontrolerze.
   - `config/packages/security.yaml`: zabezpieczenie ścieżek `/api/ai/recommendations/**` (wymóg `IS_AUTHENTICATED_FULLY`).
   - `services.yaml`: autowiring dla serwisów, bindowanie `IRecommendationProvider` → `MockOpenRouterRecommendationProvider` (środowiska `dev`, `test`, `prod` — zawsze mock jak w wymaganiach).
   - `config/packages/framework.yaml`: `rate_limiter` (per user/IP) dla obu endpointów.

7) **Obsługa błędów i logowanie**
   - Monolog kanał `ai`: logowanie start/koniec generowania, błędów provider (mock), timeoutów i akceptacji (z eventId/userId/bookId).
   - Maper wyjątków do HTTP (np. `HttpExceptionInterface` lub `ProblemDetails`).

8) **Testy**
   - Unit (Domain): niezmienniki `AiRecommendationEvent` (3 rekomendacje, unikalność `acceptedBookIds`).
   - Unit (Application): serwis generowania (mock provider), akceptacja (walidacja idempotency, book link).
   - Integration (Infra): repozytoria Doctrine, migracje.
   - Integration (UI): testy kontrolera (201 generate, 200 accept, scenariusze 400/401/404/409/502/504).

9) **Uwaga krytyczna (wymóg)**
- Nie realizować połączenia z OpenRouter. Provider ma być mockiem zwracającym 3 propozycje — deterministycznie lub losowo, ale zawsze lokalnie i szybko. Pole `model` zapisywać tylko audytowo.


