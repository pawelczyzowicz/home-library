## API Endpoint Implementation Plan: Shelves (GET /api/shelves, DELETE /api/shelves/{id})

### 1. Przegląd punktu końcowego
- **Cel**: Udostępnienie listy półek oraz możliwość usunięcia półki zgodnie z regułami domenowymi.
- **Kontekst**: DDD z warstwami UI (HTTP), Application (Handlers/Commands/Queries), Domain (Encje/VO/Repo), Infrastructure (Doctrine).
- **Powiązania**: Istnieje już POST `/api/shelves` z `CreateShelfAction`, `ShelfResource`, globalny `ExceptionListener` i `ProblemJsonResponseFactory`.

### 2. Szczegóły żądania
- **GET /api/shelves**
  - **Parametry zapytania**:
    - `q` (opcjonalny string): case-insensitive contains dla `name`.
  - **Nagłówki**: `Accept: application/json`
  - **Walidacja wejścia**:
    - `q`: trim, długość 0–50 (spójnie z `ShelfName`), normalizacja do wyszukiwania `ILIKE`/`LOWER(name) LIKE ...`.

- **DELETE /api/shelves/{id}**
  - **Parametry ścieżki**:
    - `id` (wymagany, UUID v4/v7): walidacja formatu, konwersja do `UuidInterface`.
  - **Nagłówki**: `Accept: application/json`
  - **Reguły domenowe**:
    - Nie usuwać półek systemowych (`ShelfFlag::system`).
    - Nie usuwać, jeśli półka zawiera książki; zwrócić 409 z informacją, że są przypisane książki.

### 3. Wykorzystywane typy
- **DTO/Resource (UI)**:
  - Reuse: `App\HomeLibrary\UI\Api\Shelf\ShelfResource` (już mapuje `Shelf` → `{ id, name, isSystem, createdAt, updatedAt }`).
  - Nowe (UI Response Shapes):
    - Lista: `{ data: ShelfResource[], meta: { total: int } }`.
    - Problem JSON: z `ProblemJsonResponseFactory` (typy jak poniżej).

- **Application (CQRS)**:
  - Query: `ListShelvesQuery { ?string $q }`.
  - Query Handler: `ListShelvesHandler(ListShelvesQuery) : ListShelvesResult`.
  - Query Result: `ListShelvesResult { array $shelves, int $total }` (tablica encji `Shelf` lub lekkich projekcji).
  - Command: `DeleteShelfCommand { UuidInterface $id }`.
  - Command Handler: `DeleteShelfHandler(DeleteShelfCommand) : void`.

- **Domain**:
  - Reuse: `Shelf`, `ShelfName`, `ShelfFlag`, `TimestampableTrait`.
  - Repo:
    - Rozszerzyć istniejące `ShelfRepository`:
      - 'remove(Shelf): void`
      - `find(UuidInterface): ?Shelf, search(q): array, count(q): int`
  - Wyjątki domenowe/aplikacyjne:
    - `ShelfNotFoundException` (404)
    - `ShelfIsSystemException` (409 – konflikt biznesowy)
    - `ShelfNotEmptyException` (409 – zawiera książki)

- **Infrastructure (Doctrine)**:
  - `DoctrineShelfRepository` – filtr `q`, pobranie `total`.`remove`.
  - Serwis do liczenia książek na półce: `ShelfBooksCounter` (zapytanie SQL)
    - Implementacja: relacja 1–N (`books.shelf_id`).

### 4. Szczegóły odpowiedzi
- **GET /api/shelves** → `200 OK`
  - Body:
  ```json
  { "data": [ { "id": "uuid", "name": "Do zakupu", "isSystem": true, "createdAt": "...", "updatedAt": "..." } ], "meta": { "total": 2 } }
  ```

- **DELETE /api/shelves/{id}**
  - `204 No Content` – gdy usunięto
  - `409 Conflict` – gdy niepusta półka:
  ```json
  { "type": "https://example.com/errors/shelf-not-empty", "title": "Shelf not empty", "status": 409, "detail": "Shelf contains books." }
  ```
  - Inne kody: `400` (zły UUID), `404` (brak zasobu), `401` (brak autoryzacji), `500` (błąd serwera)

### 5. Przepływ danych
- **GET**
  1) `ListShelvesAction` (UI) parsuje query (`q`) i waliduje.
  2) Tworzy `ListShelvesQuery`; wywołuje `ListShelvesHandler`.
  3) Handler używa `ShelfRepository` do pobrania listy i `count` (z tymi samymi filtrami).
  4) UI mapuje encje `Shelf` przez `ShelfResource` → buduje `{ data, meta.total }` i zwraca 200.

- **DELETE**
  1) `DeleteShelfAction` (UI) waliduje `id` (UUID), buduje `DeleteShelfCommand`.
  2) `DeleteShelfHandler` pobiera `Shelf` przez `ShelfRepository` lub `find` z write repo; jeśli brak → 404.
  3) Odmawia dla systemowej (`ShelfFlag::isSystem()`): rzuca `ShelfIsSystemException` → 409.
  4) Liczy książki przez `ShelfBooksCounter`. Jeśli `> 0` → `ShelfNotEmptyException` → mapowane do 409 (bez liczby książek).
  5) `ShelfWriteRepository->remove($shelf)`; flush; UI zwraca `204`.
  6) Wszystkie wyjątki przechwytuje `ExceptionListener` i mapuje do Problem+JSON.

### 6. Względy bezpieczeństwa
- **Autentykacja/Autoryzacja**: zastosować istniejącą konfigurację Symfony Security. Wymagać zalogowania (`401` w razie braku). Ograniczyć `DELETE` do ról uprawnionych (np. `ROLE_LIBRARIAN`).
- **Uprawnienia biznesowe**: zakaz usuwania półek systemowych niezależnie od roli → 409.
- **Walidacja wejścia**: ścisła walidacja `UUID`, sanitizacja `q`, ograniczenie długości.
- **Nagłówki**: `Content-Type` odpowiedzi `application/json` / `application/problem+json`.
- **CSRF**: nie dotyczy API z tokenem/autoryzacją nagłówkową.
- **Rate limiting / bruteforce**: rozważyć `RateLimiter` dla wrażliwych operacji (DELETE).
- **Rejestrowanie**: logować próby usunięcia niepustych/systemowych półek (poziom `info`/`warning`).

### 7. Obsługa błędów
- **400 Bad Request**: nieprawidłowe `UUID`.
- **401 Unauthorized**: brak sesji/tokena.
- **404 Not Found**: brak półki o `id`.
- **409 Conflict**:
  - `type: https://example.com/errors/shelf-not-empty`.
  - `type: https://example.com/errors/cannot-delete-system-shelf`.
- **422 Unprocessable Entity**: (zachować istniejące mapowanie dla walidacji, jeśli dotyczy innych akcji).
- **500 Internal Server Error**: nieoczekiwane błędy (mapuje `ExceptionListener`).

### 8. Kroki implementacji
1) UI – Listowanie
   - Utwórz `src/HomeLibrary/UI/Api/Shelf/ListShelvesAction.php` z atrybutem `#[Route(path: '/api/shelves', methods: ['GET'])]`.
   - Parsuj `q` z `Request->query`. Waliduj (długość `q`).
   - Buduj `ListShelvesQuery`, wywołaj handler. Zmapuj wynik przy użyciu `ShelfResource` do `data`, dodaj `meta.total`. Zwróć `200`.

2) UI – Usuwanie
   - Utwórz `src/HomeLibrary/UI/Api/Shelf/DeleteShelfAction.php` z atrybutem `#[Route(path: '/api/shelves/{id}', methods: ['DELETE'])]`.
   - Waliduj `id` (Ramsey `Uuid::isValid`). Zbuduj `DeleteShelfCommand` i wywołaj handler. Zwróć `204` po sukcesie.

3) Application – Queries/Commands
   - Dodaj `src/HomeLibrary/Application/Shelf/Query/ListShelvesQuery.php` i `ListShelvesHandler.php`.
   - Dodaj `src/HomeLibrary/Application/Shelf/Command/DeleteShelfCommand.php` i `DeleteShelfHandler.php`.
   - Handlery jako `__invoke(...)` wstrzykiwane przez DI.

4) Domain – Repozytoria i wyjątki
   - Rozszerz `ShelfRepository`:
     - `find(UuidInterface $id): ?Shelf`
     - `search(?string $q): array{Shelf...}`
     - `count(?string $q): int`
     - `remove(Shelf $shelf): void`
   - Dodaj wyjątki: `ShelfNotFoundException`, `ShelfIsSystemException`, `ShelfNotEmptyException`.

5) Infrastructure – Doctrine
   - Rozbuduj `DoctrineShelfRepository` (dodaj komentarz aby docelowo w 'DoctrineBookRepository').
   - Zaimplementuj `ShelfBooksCounter` korzystający z DBAL/QueryBuilder:
     - Jeśli 1–N: `SELECT COUNT(*) FROM books WHERE shelf_id = :id`.

6) UI – Problem Details i ExceptionListener
   - Rozszerz `ExceptionListener` o mapowanie:
     - `ShelfNotFoundException` → `404` (`type: https://example.com/problems/not-found`).
     - `ShelfIsSystemException` → `409` (`type: https://example.com/errors/cannot-delete-system-shelf`).
     - `ShelfNotEmptyException` → `409` (`type: https://example.com/errors/shelf-not-empty`).

7) Rejestracja serwisów
   - Jeśli autowire nie wykryje repo/handlerów automatycznie, zarejestruj w `config/services.yaml` odpowiednie bindy i aliasy interfejsów na implementacje Doctrine.


### 10. Mapowanie kodów statusu (konkret)
- `GET /api/shelves`: 200, 400, 401, 500
- `DELETE /api/shelves/{id}`: 204, 400, 401, 404, 409, 500


