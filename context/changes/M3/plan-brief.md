# Plan Brief: M3 — Izolacja danych per biblioteka

---
change-id: M3
status: draft
full-plan: context/changes/M3/plan.md
---

## Cel

Użytkownik widzi wyłącznie książki i półki swojej biblioteki. Nowe rekordy automatycznie przypisywane do jego library.

## Decyzje architektoniczne

- **Jawne `UuidInterface $libraryId`** w Commands/Queries (nie Doctrine SQL Filter)
- **Book ma własne `library_id`** (redundancja z shelf — celowa: performance + security)
- **UNIQUE** `shelves(name)` → `shelves(library_id, name)`
- **Genre** — bez `library_id` (globalne)
- **Trait `LibraryAwareTrait`** w kontrolerach (~8 powtórzeń)

## Fazy

| # | Zakres | Gotowe gdy |
|---|--------|-----------|
| 1 | Domain: `Book`/`Shelf` + `Library` relacja, interfejsy repo z `$libraryId` | `lint:container` + PHPStan pass |
| 2 | Infra: migracja (`library_id` FK + INDEX + UNIQUE), repo impl z filtrowaniem | `doctrine:schema:validate` pass |
| 3 | App: Commands/Queries/Handlers rozszerzone o `$libraryId` | Unit testy handlerów pass |
| 4 | UI: Actions wyciągają `$user->library()->id()` i przekazują dalej | Integration API tests pass |
| 5 | Testy: update istniejących + nowe `BookIsolationTest`, `ShelfIsolationTest` | `bin/phpunit` all green |

## Kluczowe pliki per faza

### Phase 1 — Domain
- `src/HomeLibrary/Domain/Book/Book.php` — +`Library $library` pole/konstruktor/getter
- `src/HomeLibrary/Domain/Shelf/Shelf.php` — j.w.
- `src/HomeLibrary/Domain/Book/BookRepository.php` — `search($libraryId, ...)`, `findById($id, ?$libraryId)`
- `src/HomeLibrary/Domain/Shelf/ShelfRepository.php` — j.w. + `countBySearchTerm($libraryId, ...)`

### Phase 2 — Infrastructure
- `migrations/Version20260624120000.php` — ADD `library_id` + FK + INDEX + UNIQUE composite
- `DoctrineBookRepository.php` — `WHERE library_id`
- `DoctrineShelfRepository.php` — `WHERE library_id`
- `DbalShelfBooksCounter.php` — `AND library_id`
- `DoctrineBookReadRepository.php` + `BookReadRepository.php` — `$libraryId` w `find()`
- `ShelfBooksCounter.php` — `$libraryId` w `countForShelf()`

### Phase 3 — Application
- `Create/Delete BookCommand` — +`$libraryId`
- `Create/Delete ShelfCommand` — +`$libraryId`
- `ListBooksQuery`, `ListShelvesQuery` — +`$libraryId`
- Wszystkie handlery — resolve Library, filtruj, waliduj przynależność
- `LibraryRepository` — +`findById()`

### Phase 4 — UI
- 8 Actions: `CreateBook`, `DeleteBook`, `ListBooks`, `CreateShelf`, `DeleteShelf`, `ListShelves`, `GenerateRecommendations`, `AcceptRecommendation`
- Pattern: `$this->currentLibraryId()` via trait

### Phase 5 — Testy
- UPDATE: `CreateBookHandlerTest`, `CreateShelfHandlerTest`, repo integration tests, API tests
- CREATE: `BookIsolationTest`, `ShelfIsolationTest`

## Ryzyko

| Zagrożenie | Mitygacja |
|-----------|-----------|
| Pominięte filtrowanie → wyciek | `grep 'FROM books\|FROM shelves'` audit + testy izolacji |
| Łamanie sygnatur w wielu plikach | PHPStan wyłapie; fazy sekwencyjne |

## Definition of Done

- [ ] Zapytania zwracają tylko dane z biblioteki użytkownika
- [ ] Nowa książka/półka ma `library_id` automatycznie
- [ ] Manipulacja URL/ID → 404 (nie wyciek)
- [ ] Istniejące testy przechodzą
- [ ] Nowe testy izolacji pokrywają list/findById/create/delete

