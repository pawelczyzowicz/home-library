# API Endpoint Implementation Plan: Create Book (POST /api/books)

## 1. Przegląd punktu końcowego
Punkt końcowy tworzy nową książkę w systemie. Wymaga wskazania półki (`shelfId`) oraz 1–3 gatunków (`genreIds`). Pola `isbn` i `pageCount` są opcjonalne, a pole `source` jest ustawiane systemowo na `manual`. Zwracany jest obiekt Book wraz z powiązaną półką i gatunkami.

## 2. Szczegóły żądania
- Metoda HTTP: POST
- Struktura URL: /api/books
- Parametry:
  - Wymagane: `title` (string 1–255), `author` (string 1–255), `shelfId` (UUID), `genreIds` (int[]; rozmiar 1–3)
  - Opcjonalne: `isbn` (string 10 lub 13 cyfr), `pageCount` (int 1–50000)
- Request Body (application/json):
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
- Wymagany nagłówek: `Content-Type: application/json`

## 3. Wykorzystywane typy
- DTOs:
  - CreateBookPayloadDto (API warstwa, do walidacji i normalizacji danych wejściowych; właściwości: title, author, shelfId, genreIds, isbn?, pageCount?)
- Command modele:
  - CreateBookCommand (Application warstwa; właściwości: id (Uuid7), title, author, isbn?, pageCount?, shelfId (Uuid), genreIds (int[]), source = manual, recommendationId = null)
- Zasoby (Resource):
  - BookResource (już istnieje) do serializacji domenowej encji Book na JSON
  - ShelfResource, GenreResource (już istnieją) używane przez BookResource

## 3. Szczegóły odpowiedzi
- Sukces (201 Created): Book JSON zgodny z istniejącym `BookResource` (z `source = "manual"`, `recommendationId = null`).
- Błędy:
  - 400 Bad Request: nieprawidłowy Content-Type, błędny JSON
  - 401 Unauthorized: brak sesji użytkownika, jeśli endpoint chroniony
  - 404 Not Found: nie znaleziono półki lub któregokolwiek gatunku
  - 422 Unprocessable Entity: błędy walidacji (w formacie RFC 7807 `application/problem+json`), klucz `errors` z mapą pól → listy komunikatów
  - 500 Internal Server Error: nieoczekiwany błąd serwera

## 4. Przepływ danych
1) Kontroler `CreateBookAction` (UI/API):
   - Sprawdza `Content-Type` i parsuje JSON (z obsługą błędów 400).
   - Tworzy `CreateBookPayloadDto` i przekazuje do walidatora.
   - W przypadku błędów walidacji rzuca `ValidationException` (mapowane na 422 przez `ExceptionListener`).
   - Buduje `CreateBookCommand` (id = Uuid::uuid7(), source = manual, recommendationId = null) i wywołuje `CreateBookHandler`.
2) `CreateBookHandler` (Application):
   - Normalizuje i waliduje wartości zgodnie z kontraktami domeny (trim, długości, zakresy) – może użyć Symfony Validator i/lub Value Objects.
   - Pobiera `Shelf` przez `ShelfRepository::findById` – w razie braku rzuca `ShelfNotFoundException` (mapowane na 404 przez `ExceptionListener`).
   - Pobiera kolekcję `Genre` na podstawie `genreIds` (należy dodać `GenreRepository` o metodzie `findByIds(array $ids): Genre[]` i/lub walidator liczności 1–3; w razie braków gatunków rzuca 404).
   - Tworzy domenową encję `Book`:
     - `id = Uuid7`, `title = new BookTitle(title)`, `author = new BookAuthor(author)`, `isbn = new BookIsbn(isbn)`, `pageCount = new BookPageCount(pageCount)`, `source = BookSource::MANUAL`, `recommendationId = null`, `shelf`, `genres`.
   - Persistuje encję przez Doctrine (np. `EntityManager->persist($book); flush();`).
3) Kontroler zwraca `JsonResponse` z danymi z `BookResource` i statusem 201.

## 5. Względy bezpieczeństwa
- Uwierzytelnianie: endpoint powinien wymagać `IS_AUTHENTICATED_FULLY` (dopisać `access_control` jeśli potrzebne).
- CSRF: dla JSON API zwykle nie dotyczy; ścieżki `/api/*` obsługiwane są jako stateless pod kątem CSRF.
- Autoryzacja: brak rozróżnienia ról – MVP.
- Walidacja Content-Type: wymagaj `application/json`.
- Walidacja danych: szczegółowe reguły (patrz sekcja 6) i użycie Value Objects (BookTitle/Author/Isbn/PageCount).
- Rate limiting: opcjonalnie skonfigurować limiter dla POST `/api/books`.

## 6. Obsługa błędów
- Format błędów: `application/problem+json` (RFC 7807) generowany przez `ProblemJsonResponseFactory` i `ExceptionListener`.
- Mapowanie wyjątków:
  - `ValidationException` → 422 + `{ errors: { field: ["msg"] } }`
  - `ShelfNotFoundException` → 404
  - `GenreNotFoundException` (nowy) → 404 (zawiera np. brakujące id lub listę brakujących)
  - `BadRequestHttpException` (z JSON/Content-Type) → 400
  - Inne nieoczekiwane → 500 (logowane przez `ExceptionListener`)
- Przykłady:
  - 422: błędy pól `title`, `author`, `genreIds`, `isbn`, `pageCount`
  - 404: `shelfId` nie istnieje lub któryś `genreId` nie istnieje

## 7. Rozważania dotyczące wydajności
- Indeksy: `books.shelf_id`, `book_genre.book_id`, `book_genre.genre_id` – już wymagane.

## 8. Etapy wdrożenia
1. Typy i repozytoria
   - Dodać `GenreRepository` (Domain + Infrastructure):
     - `findByIds(int[] $ids): Genre[]`
     - Opcjonalnie `existsByIds(int[] $ids): bool` lub zwracanie brakujących `ids`.
   - Upewnić się, że `BookRepository` posiada metodę `save(Book $book): void`.
   - Rejestracja DI w `services.yaml` (mapowanie Domain → Infrastructure repo dla `GenreRepository`).
2. Walidacja i DTO
   - Utworzyć `CreateBookPayloadDto` i `CreateBookPayloadValidator` (Application/Service):
     - Reguły:
       - `title`, `author`: required, trim, długość 1–255
       - `shelfId`: required, string UUID, parsowanie do `UuidInterface`
       - `genreIds`: required, tablica intów, rozmiar 1–3, bez duplikatów
       - `isbn`: optional, jeśli podane – 10 lub 13 cyfr (po odfiltrowaniu znaków innych niż cyfry)
       - `pageCount`: optional, jeśli podane – int w zakresie 1–50000
     - W razie naruszeń → `ValidationException::withIssues([...])`.
3. Command i Handler
   - Dodać `CreateBookCommand` (Application\Book\Command) z polami: id, title, author, isbn?, pageCount?, shelfId, genreIds, source, recommendationId.
   - Utworzyć `CreateBookHandler` (Application\Book):
     - Parsuje i waliduje command (lub przyjmuje już znormalizowane wartości z walidatora DTO).
     - Pobiera `Shelf` przez `ShelfRepository::findById` – jeśli null → `ShelfNotFoundException::withId($shelfId)`.
     - Pobiera `Genre[]` przez `GenreRepository::findByIds($genreIds)` – jeśli zwrócona liczba < liczba żądanych → rzuca `GenreNotFoundException` z listą brakujących.
     - Tworzy `Book` przez konstruktor domenowy:
       - `new Book($id, new BookTitle($title), new BookAuthor($author), new BookIsbn($isbn), new BookPageCount($pageCount), BookSource::MANUAL, null, $shelf, $genres)`
     - Zapisuje encję (EntityManager->persist/flush lub `BookRepository->save`).
4. Kontroler API
   - Dodać `CreateBookAction` w `App\HomeLibrary\UI\Api\Book` z trasą `#[Route(path: '/api/books', methods: ['POST'])]`.
   - Sprawdza `Content-Type`, parsuje JSON z `JSON_THROW_ON_ERROR`, waliduje DTO, tworzy Command, wywołuje Handler.
   - Zwraca `JsonResponse` 201 z `BookResource->toArray($book)`.
5. Obsługa błędów
   - Rozszerzyć `ExceptionListener` o mapowanie `GenreNotFoundException` → 404 (typ `https://example.com/problems/not-found`).
   - 422 dla `ValidationException` – już istnieje.
   - 400 dla `BadRequestHttpException` – zostanie zwrócone przez kernel (poza match w listenerze), ewentualnie dodać jawne mapowanie jeśli chcemy spójny format problem+json.
6. Testy
   - Testy jednostkowe walidatora DTO (szczęśliwe ścieżki i każda reguła 422).
   - Testy jednostkowe handlera: 404 dla braku półki, 404 dla brakujących gatunków, sukces.
   - Testy integracyjne: zapis do DB, poprawne powiązania `book_genre`, wartości `source` i `recommendation_id = null`.
   - Testy E2E (Panther): POST `/api/books` → 201 i pełny shape JSON zgodny z `BookResource`.
7. Konfiguracja DI
   - `services.yaml`: powiązać `App\HomeLibrary\Domain\Genre\GenreRepository` → `App\HomeLibrary\Infrastructure\Persistence\DoctrineGenreRepository`.
8. Monitorowanie i logowanie
   - Dodać logi na poziomie INFO przy sukcesie utworzenia (id książki), WARN dla 4xx (walidacje), ERROR dla 5xx.
