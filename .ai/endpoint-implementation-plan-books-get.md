## API Endpoint Implementation Plan: Books – GET /api/books

### 1. Przegląd punktu końcowego
- Cel: Dostarczenie listy książek z możliwością filtrowania, sortowania i paginacji. Każda pozycja zawiera wbudowane dane półki (`shelf`) oraz gatunków (`genres`) zgodnie ze specyfikacją.
- Architektura: DDD (warstwy UI → Application → Domain → Infrastructure). Doctrine ORM. Zwracanie odpowiedzi JSON według konwencji projektu (Problem Details dla błędów).

### 2. Szczegóły żądania
- Metoda HTTP: GET
- Ścieżka: `/api/books`
- Nagłówki: `Accept: application/json`
- Parametry zapytania:
  - `q` (opcjonalnie, string): case-insensitive wyszukiwanie po `title` LUB `author`. Normalizowane (trim). Długość 0–255; pusty string traktowany jak brak filtra.
  - `shelfId` (opcjonalnie, UUID): filtr po półce. Walidacja formatu UUID; nie weryfikujemy istnienia (brakująca półka ⇒ pusta lista).
  - `genreIds` (opcjonalnie, string): lista ID gatunków w formacie przecinkowym, np. `1,2,7`. Semantyka OR (książka pasuje, jeśli ma co najmniej jeden z podanych gatunków). Tokeny parsowane do unikalnych, dodatnich intów. Błędne tokeny ⇒ 400.
  - `limit` (opcjonalnie, int): domyślnie 20; zakres 1–100 (cięte do maks. 100).
  - `offset` (opcjonalnie, int): domyślnie 0; zakres ≥ 0.
  - `sort` (opcjonalnie, enum): jedno z `title`, `author`, `createdAt`. Domyślnie `createdAt`.
  - `order` (opcjonalnie, enum): `asc` lub `desc`. Domyślnie `desc`.

### 3. Wykorzystywane typy
- UI (DTO/Resource):
  - `BookResource` (NOWY): mapowanie `Book` → `{ id, title, author, isbn, pageCount, source, recommendationId, shelf, genres, createdAt, updatedAt }`.
  - `ShelfResource` (ISTNIEJĄCY): `{ id, name, isSystem, createdAt, updatedAt }` do osadzenia w `BookResource`.
  - `GenreResource` (NOWY): `{ id, name }`.
- Application (CQRS):
  - `ListBooksQuery` (NOWY): pola odpowiadające parametrom zapytania (`?string $q, ?UuidInterface $shelfId, int[] $genreIds, int $limit, int $offset, string $sort, string $order`).
  - `ListBooksResult` (NOWY): `Book[] $books, int $total, int $limit, int $offset`.
  - `ListBooksHandler` (NOWY): orkiestracja zapytania do repozytorium i zwrócenie wyniku.
- Domain:
  - `Book` (NOWA encja) z relacjami do `Shelf` (ManyToOne) i `Genre` (ManyToMany przez `book_genre`), atrybuty: `id`, `title`, `author`, `isbn?`, `pageCount?`, `source`, `recommendationId?`, `createdAt`, `updatedAt`.
  - Value Objects (NOWE): `BookTitle` (1–255), `BookAuthor` (1–255), `BookIsbn` (opcjonalny: 10 lub 13 cyfr), ewentualnie `BookPageCount` (1–50000) jako VO.
  - Enum (NOWY): `BookSource` (`manual`, `ai_recommendation`).
  - `Genre` (NOWA encja): `id` (int), `name` (varchar(100)).
- Infrastructure:
  - `BookRepository` (INTERFEJS): `search()` zwracające wynik z listą i totalem.
  - `DoctrineBookRepository` (NOWY): implementacja zapytań Doctrine.

### 4. Szczegóły odpowiedzi
- 200 OK
```
{
  "data": [
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
  ],
  "meta": { "total": 47, "limit": 20, "offset": 0 }
}
```
- Błędy: `application/problem+json` (patrz sekcje 5–6).

### 5. Przepływ danych
1) UI (HTTP)
- `ListBooksAction` pobiera i waliduje parametry query, buduje `ListBooksQuery` i wywołuje `ListBooksHandler`.
- Mapuje wynik przy użyciu `BookResource` (osadza `ShelfResource` i `GenreResource`).

2) Application
- `ListBooksHandler` deleguje do `BookRepository::search()` z parametrami i zwraca `ListBooksResult` z `books`, `total`, `limit`, `offset`.

3) Infrastructure (Doctrine)
- Zapytanie filtrowane po:
  - `q`: `LOWER(b.title) LIKE :q OR LOWER(b.author) LIKE :q`.
  - `shelfId`: `b.shelf = :shelfId`.
  - `genreIds`: semantyka OR, zastosować `EXISTS` z subzapytaniem po `book_genre` dla stabilnej paginacji, np. `EXISTS (SELECT 1 FROM book_genre bg WHERE bg.book_id = b.id AND bg.genre_id IN (:genreIds))`.
- Sortowanie: whitelist mapujący pola → kolumny (`title` → `b.title`, `author` → `b.author`, `createdAt` → `b.createdAt`). Kierunek `ASC|DESC`.
- Paginacja: `limit`, `offset`.
- Eager data:
  - Pobrać strony główne jako listę `bookId` (SELECT paginowane po `books`) ⇒ następnie dociągnąć:
    - `Shelf` przez JOIN FETCH (ManyToOne, bez zwielokrotnienia).
    - `genres` w drugim zapytaniu (lub użyć dedykowanej kwerendy do mapy `bookId → Genre[]`), aby uniknąć problemów Paginatora z kolekcjami.
- `COUNT(*)` wykonany w osobnym zapytaniu z tymi samymi filtrami (bez `limit/offset`).

### 6. Względy bezpieczeństwa
- Uwierzytelnianie: endpoint pod ochroną firewalla API (wymagany zalogowany użytkownik). Brak CSRF dla GET.
- Walidacja i twarde limity wejścia: długość `q`, whitelist `sort`, cap `limit`, parsowanie `genreIds` i walidacja UUID `shelfId`.
- Ochrona przed SQL Injection: wyłącznie bindowane parametry, biała lista pól sortowania/kierunku.
- Informacje zwracane: brak danych wrażliwych; UUID i nazwy publiczne.
- Rate limiting (opcjonalnie): nagłówki limitów; integracja na poziomie reverse proxy lub middleware.

### 7. Obsługa błędów
- 400 Bad Request: nieprawidłowe parametry (`shelfId` nie-UUID, `limit` poza zakresem, `genreIds` z nieprawidłowymi tokenami, `sort/order` poza whitelistą). Treść: Problem Details z `type = https://example.com/problems/invalid-query-parameter` lub specyficznym `type` per parametr.
- 401 Unauthorized: niezalogowany użytkownik (kontrola przez firewall). Treść: Problem Details.
- 404 Not Found: nie dotyczy listy (brak wyników ⇒ `data: []`, `total: 0`).
- 500 Internal Server Error: nieoczekiwane błędy. Logowane przez Monolog. Treść: Problem Details z generycznym komunikatem.
- Logowanie błędów do tabeli (opcjonalnie): jeśli wymagane, dodać `monolog` handler do DB (Doctrine) lub własny `ErrorLogRepository`; domyślnie wystarczy Monolog do plików.

### 8. Rozważania dotyczące wydajności
- Indeksy:
  - `books (shelf_id)` – już w planie DB.
  - Indeksy na `book_genre (book_id)`, `(genre_id)` (już w planie DB).
- Kwerendy:
  - Użyć `EXISTS` dla `genreIds` OR, aby uniknąć zwiększania kardynalności i zduplikowanych wierszy.
  - Dwuetapowe ładowanie: najpierw `bookId` z filtrami/sortowaniem, potem dociągnięcie encji i `genres` – eliminuje problemy Paginatora z kolekcjami.
- Paginacja: limit maks. 100; wspierana przez odpowiedź `meta { total, limit, offset }`.

### 9. Etapy wdrożenia
1) Domain
- Dodać `BookSource` (enum: `manual`, `ai_recommendation`).
- Dodać VO: `BookTitle`, `BookAuthor`, `BookIsbn` (walidacje jak wyżej).
- Utworzyć encję `Book` (`#[ORM@Entity]`, `#[ORM\Table(name: 'books')]`, `#[ORM\HasLifecycleCallbacks]`, `use TimestampableTrait`).
  - Pola: `id: UuidInterface`, `title: BookTitle (Embedded)`, `author: BookAuthor (Embedded)`, `isbn?: BookIsbn (Embedded|nullable)`, `pageCount?: int`, `source: BookSource`, `recommendationId?: int`, `shelf: Shelf (ManyToOne, not null)`, `genres: Collection<Genre> (ManyToMany, joinTable 'book_genre')`.
- Utworzyć `Genre` (`id: int`, `name: string`), opcjonalny VO `GenreName`.

2) Infrastructure
- Skonfigurować mapping typu DB:
  - `doctrine.dbal.mapping_types.book_source_enum: string` LUB własny `DBAL Type` mapujący enum Postgres ↔️ PHP enum.
- Dodać `BookRepository` oraz `DoctrineBookRepository`:
  - Metoda `search(?string $q, ?UuidInterface $shelfId, int[] $genreIds, int $limit, int $offset, string $sort, string $order): ListBooksResult`.
  - Implementacja filtrów, sortowania, `COUNT(*)`, dwuetapowego pobierania, prefetch `Shelf`, dogranie `genres`.

3) Application
- Dodać `ListBooksQuery`, `ListBooksResult`, `ListBooksHandler` (analogicznie do `ListShelves*`).

4) UI (HTTP)
- Dodać `BookResource` i `GenreResource` (mapowania zgodne ze specyfikacją JSON).
- Dodać `ListBooksAction` z trasą `#[Route('/api/books', methods: ['GET'])]`:
  - Parsowanie i walidacja parametrów, tworzenie `ListBooksQuery`.
  - Obsługa błędów wejściowych z `ProblemJsonResponseFactory` (400).
  - Zwrócenie `{ data, meta: { total, limit, offset } }` (200).

5) Konfiguracja i migracje
- Jeśli nie istnieją: migracje dla `books`, `genres`, `book_genre`, oraz typ `book_source_enum` (Postgres). Upewnić się, że `updated_at` jest automatycznie aktualizowany przez `TimestampableTrait` (`PrePersist`/`PreUpdate`).

6) Testy
- Statyczna analiza kodu komenda `vendor/bin/grumphp run --tasks=phpcsfixer,phpmd,phpstan`.
- Unit: VO (`BookTitle`, `BookAuthor`, `BookIsbn`), enum mapowanie.
- Integracyjne: `DoctrineBookRepository::search()` – przypadki filtrów, sortów, paginacji i OR dla `genreIds`.
- E2E (Panther): GET `/api/books` – scenariusze z różnymi parametrami i walidacją 400.

7) Monitoring i DX
- Monolog: poziomy INFO/ERROR, korelacja żądań przez `X-Request-Id` (opcjonalnie).
- Dokumentacja: uzupełnić `.ai/api-plan.md` sekcji Books o szczegóły implementacyjne oraz przykłady zapytań.
