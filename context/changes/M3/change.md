# Change: M3 — Izolacja danych per biblioteka

---
change-id: M3
roadmap-ref: M3
status: in-progress
created: 2026-06-24
---

## Summary

Dodanie relacji `library_id` do encji Book i Shelf oraz filtrowanie wszystkich zapytań po bibliotece zalogowanego użytkownika. Użytkownik widzi wyłącznie książki i półki swojej biblioteki. Nowe rekordy automatycznie otrzymują `library_id` z sesji.

## User-Visible Outcome

Użytkownik widzi wyłącznie książki i półki swojej biblioteki. Manipulacja URL/ID nie pozwala na dostęp do danych innej biblioteki (zwraca 404).

## Scope (from roadmap)

1. Encje Book i Shelf — relacja ManyToOne do Library (`library_id` NOT NULL)
2. Migracja Doctrine — `library_id` FK + INDEX w `books` i `shelves`, UNIQUE composite `shelves(library_id, name)`
3. Interfejsy repozytoriów — `$libraryId` jako parametr w `search()`, `findById()`, `countBySearchTerm()`
4. Implementacje repozytoriów — filtrowanie `WHERE library_id = :libraryId` w każdym zapytaniu
5. Commands/Queries/Handlers — `$libraryId` propagowany z UI do domain
6. UI Actions — wyciągnięcie `$user->library()->id()` i przekazanie do commands/queries (trait `LibraryAwareTrait`)
7. AI recommendations — filtrowanie kontekstu książek per library
8. Testy izolacji — weryfikacja że user z libraryA nie widzi danych libraryB

## Success Criteria

- SC-01: Zapytania zwracają tylko dane z biblioteki użytkownika
- SC-02: Nowa książka/półka ma `library_id` automatycznie
- SC-03: Manipulacja URL/ID → 404 (nie wyciek)
- SC-04: Istniejące testy przechodzą po aktualizacji
- SC-05: Nowe testy izolacji pokrywają list/findById/create/delete

## Definition of Done

- `GET /api/books` zwraca tylko książki z biblioteki zalogowanego użytkownika
- `GET /api/shelves` zwraca tylko półki z biblioteki zalogowanego użytkownika
- `DELETE /api/books/{id}` z ID książki innej biblioteki → 404
- `DELETE /api/shelves/{id}` z ID półki innej biblioteki → 404
- `POST /api/books` tworzy książkę z `library_id` użytkownika
- `POST /api/shelves` tworzy półkę z `library_id` użytkownika
- Ta sama nazwa półki dozwolona w różnych bibliotekach
- `bin/phpunit` — all green (unit + integration)
- PHPStan, CS-Fixer, PHPMD — clean

## Dependencies

- M1 (Library entity + User.library_id)

## Unlocks

- M4 (hardening — E2E testy izolacji, security review)

## Progress

| Phase | Status | Notes |
|-------|--------|-------|
| 1 — Domain + interfaces | ✅ done | Book/Shelf relacja Library, repo interfaces z $libraryId, LibraryRepository::findById(), migration, infra repos, commands/queries/handlers, UI Actions + LibraryAwareTrait, testy zaktualizowane. PHPStan 0 errors, lint:container OK, 135 unit + 21 integration green. |
| 2 — Infrastructure | ✅ done | Zrealizowane razem z Phase 1 (migracja, DoctrineBookRepo, DoctrineShelfRepo z filtrowaniem) |
| 3 — Application | ✅ done | Zrealizowane razem z Phase 1 (Commands, Queries, Handlers z $libraryId) |
| 4 — UI | ✅ done | Zrealizowane razem z Phase 1 (LibraryAwareTrait + 6 Actions + AiRecommendationsResultsController) |
| 5 — Testy izolacji | ✅ done | BookIsolationTest (4 testy: list own, list other, delete cross-library 404, delete own), ShelfIsolationTest (5 testów: list own, list other, delete cross-library 404, delete own, same name across libraries). Naprawiono istniejące testy: CreateBookApiTest, ListShelvesApiTest (EM lifecycle), DeleteShelfHandlerTest (truncation), CS-Fixer EOF issues. |

