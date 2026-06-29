# Plan: M4 — Hardening — edge cases, walidacja, testy E2E

---
change-id: M4
status: done
phases: 3
---

## End State

Po implementacji M4:
- Metoda `E2ETestCase::registerUser()` zawiera pola biblioteki (libraryName, libraryPassword, libraryMode) — istniejące E2E testy przechodzą
- Nowe E2E testy pokrywają flow rejestracji z utworzeniem biblioteki (create) i dołączeniem (join)
- E2E test izolacji weryfikuje, że dwóch użytkowników z różnych bibliotek nie widzi nawzajem danych
- Brak endpointów do zmiany/usunięcia biblioteki (SC-05) — walidacja przez testy negatywne
- DataFixtures tworzą Library + przypisują dane poprawnie
- Security review: manipulacja ID w URL nie daje cross-library access

### Zależności

- **Phase 1** — wymaga M1 + M2 (oba DONE) ✓
- **Phase 2** — wymaga M3 (data isolation) ✓ DONE
- **Phase 3** — wymaga M3 (DataFixtures potrzebują library_id w books/shelves) ✓ DONE

---

## Phase 1 — Fix E2E infrastructure + testy rejestracji create/join

**Goal:** Naprawić złamany helper `registerUser()` w `E2ETestCase` o brakujące pola biblioteki. Dodać dedykowane E2E testy pokrywające pełny flow rejestracji w obu trybach (create/join). Upewnić się, że istniejące E2E testy przechodzą.

### File Contracts

| File | Action | Contract |
|------|--------|----------|
| `tests/E2E/E2ETestCase.php` | MODIFY | Rozszerzyć `registerUser()` o parametry `?string $libraryName = null`, `?string $libraryPassword = null`, `string $libraryMode = 'create'`. Generować unikalne defaults jeśli null. Dołączyć pola `libraryName`, `libraryPassword`, `libraryMode` do payloadu JSON rejestracji. |
| `tests/E2E/AuthFlowTest.php` | VERIFY | Istniejący test `testRegisterAutoLoginAndLogout` musi przechodzić bez zmian (korzysta z domyślnych wartości library). |
| `tests/E2E/LibraryRegistrationCreateE2ETest.php` | CREATE | E2E test (HttpBrowser): rejestracja z `libraryMode: "create"`, weryfikacja 201 + user ma przypisaną bibliotekę (via /api/auth/me response). Test duplikatu nazwy → 422. |
| `tests/E2E/LibraryRegistrationJoinE2ETest.php` | CREATE | E2E test (HttpBrowser): najpierw rejestracja user A z `libraryMode: "create"` tworząca bibliotekę, potem rejestracja user B z `libraryMode: "join"` do tej samej biblioteki. Weryfikacja: oba mają tę samą library. Test złego hasła → 422. Test nieistniejącej biblioteki → 422. |
| `tests/E2E/LibraryEdgeCasesE2ETest.php` | CREATE | E2E test: brak endpointów PUT/PATCH/DELETE na `/api/libraries`, `/api/libraries/{id}`. Requesty → 404/405. Walidacja SC-05 (brak możliwości zmiany biblioteki). |

### Verification

- [x] `bin/phpunit tests/E2E/AuthFlowTest.php` passes
- [x] `bin/phpunit tests/E2E/BookCreateApiTest.php` passes
- [x] `bin/phpunit tests/E2E/LibraryRegistrationCreateE2ETest.php` passes
- [x] `bin/phpunit tests/E2E/LibraryRegistrationJoinE2ETest.php` passes
- [x] `bin/phpunit tests/E2E/LibraryEdgeCasesE2ETest.php` passes
- [x] All existing E2E tests green

### Notes

- `registerUser()` domyślnie `libraryMode: 'create'` — backward compatible
- Unikalne nazwy bibliotek generowane via `bin2hex(random_bytes(4))` prefix — unikanie kolizji między testami
- Edge case test używa HttpBrowser (szybszy niż Panther) — weryfikuje brak route'ów po stronie serwera

---

## Phase 2 — E2E izolacja danych + security review (BLOCKED by M3)

**Goal:** Po implementacji M3, dodać E2E testy weryfikujące izolację danych między bibliotekami oraz testy bezpieczeństwa (ID manipulation).

### File Contracts

| File | Action | Contract |
|------|--------|----------|
| `tests/E2E/LibraryIsolationE2ETest.php` | CREATE | Dwa konta w dwóch różnych bibliotekach. User A tworzy książkę + półkę. User B nie widzi tych danych (GET /api/books, GET /api/shelves). User B próbuje GET /api/books/{bookIdFromA} → 404. |
| `tests/E2E/LibrarySecurityE2ETest.php` | CREATE | ID manipulation: user B próbuje DELETE /api/books/{bookIdFromA} → 404. User B próbuje DELETE /api/shelves/{shelfIdFromA} → 404. User B próbuje POST /api/books z shelfId z biblioteki A → 422/404. |

### Verification

- [x] `bin/phpunit tests/E2E/LibraryIsolationE2ETest.php` passes
- [x] `bin/phpunit tests/E2E/LibrarySecurityE2ETest.php` passes
- [x] Żaden cross-library access nie jest możliwy

### Notes

- BLOCKED: wymaga M3 (library_id w books/shelves + filtrowanie w repozytoriach)

---

## Phase 3 — DataFixtures + finalizacja (BLOCKED by M3)

**Goal:** Zaktualizować DataFixtures aby tworzyły Library + przypisywały dane. Upewnić się, że `doctrine:fixtures:load` działa poprawnie z nowym modelem danych.

### File Contracts

| File | Action | Contract |
|------|--------|----------|
| `src/DataFixtures/AppFixtures.php` | MODIFY | Tworzy 2 biblioteki (np. "Rodzinna Biblioteka", "Koło Czytelnicze"). Tworzy użytkowników przypisanych do bibliotek. Tworzy półki i książki z library_id. |

### Verification

- [x] `bin/console doctrine:fixtures:load --env=test` succeeds
- [x] Po załadowaniu fixtures, każda książka/półka ma poprawny library_id
- [x] CI pipeline zielony (unit + integration + E2E)

### Notes

- BLOCKED: wymaga M3 (library_id w books/shelves)
- Obecny AppFixtures jest pusty (placeholder) — możliwe że fixtures są ładowane inaczej

---

## Decisions

- `registerUser()` default libraryMode = 'create' — backward compatible, nie wymaga zmian w istniejących testach
- E2E testy izolacji/security używają HttpBrowser (nie Panther) — szybsze, testują logikę serwera
- Edge case walidacja (brak endpointów) jako osobne test case'y — jawna dokumentacja braku funkcjonalności
- Phase 2 i 3 BLOCKED by M3 — implementacja po merge M3

