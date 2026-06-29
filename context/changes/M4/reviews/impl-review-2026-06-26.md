# Implementation Review — M4

- **change-id:** M4
- **reviewed:** 2026-06-26
- **status:** PASS with findings

---

## 1. Plan Adherence

| File Contract | Status | Notes |
|---|---|---|
| `E2ETestCase::registerUser()` MODIFY | ✅ OK | Parametry `?string $libraryName`, `?string $libraryPassword`, `string $libraryMode = 'create'` — zgodne z planem. Backward compatible via defaults. |
| `AuthFlowTest.php` VERIFY | ✅ OK | Nie zmieniony. Korzysta z domyślnych parametrów — backward compat potwierdzona. |
| `LibraryRegistrationCreateE2ETest.php` CREATE | ✅ OK | Pokrywa: create mode → 201, duplicate name → 422 (`library-conflict`), missing libraryMode → 422. |
| `LibraryRegistrationJoinE2ETest.php` CREATE | ✅ OK | Pokrywa: join flow (oba users mają to samo library.id), wrong password → 422 (`invalid-library-password`), non-existent library → 422 (`library-not-found`). |
| `LibraryEdgeCasesE2ETest.php` CREATE | ✅ OK | Pokrywa: DELETE, PUT, PATCH, GET na `/api/libraries` → 404/405. Dodatkowy test `change-library` → 404/405. SC-05 spełnione. |
| `LibraryIsolationE2ETest.php` CREATE | ✅ OK | Pokrywa: books isolation, shelves isolation, shared library visibility. |
| `LibrarySecurityE2ETest.php` CREATE | ✅ OK | Pokrywa: DELETE book cross-lib → 404, DELETE shelf cross-lib → 404, POST book z cudzym shelfId → 404/422, fake UUID → 404. |
| `AppFixtures.php` MODIFY | ✅ OK | 2 biblioteki, 3 users, shelves (system + user-defined), books z library_id i genres. |
| `services.yaml` | ✅ OK | DI `$libraryPasswordHasher` dla AppFixtures. |

**Verdict:** Wszystkie kontrakty pliku z planu spełnione. Brak pominięć.

---

## 2. Scope Discipline

| # | Finding | Severity | Impact |
|---|---------|----------|--------|
| S-01 | `LibraryEdgeCasesE2ETest` zawiera test `testUserCannotChangeLibraryAfterRegistration` (POST `/api/auth/change-library`), który nie był w planie. | info | low |
| S-02 | `LibraryRegistrationCreateE2ETest` zawiera test `testRegisterWithMissingLibraryModeReturns422`, nie wymieniony explicite w kontrakcie. | info | low |
| S-03 | `LibraryIsolationE2ETest` zawiera test `testUsersInSameLibraryShareData` (pozytywny test shared library) — nie w kontrakcie Phase 2 ale logiczne uzupełnienie izolacji. | info | low |
| S-04 | Plan Phase 2 mówi: "User B próbuje GET /api/books/{bookIdFromA} → 404" — w implementacji pominięte, bo endpoint `GET /api/books/{id}` nie istnieje. | info | none |

**Verdict:** Drobne rozszerzenia zakresu (S-01..S-03) to sensowne wzbogacenia pokrycia testowego, nie creep. S-04 — poprawna decyzja implementacyjna: plan zakładał endpoint, który nie istnieje.

---

## 3. Safety & Quality

| # | Finding | Severity | Impact |
|---|---------|----------|--------|
| Q-01 | `AppFixtures::createGenres()` duplikuje listę gatunków z migracji `Version20251028120001`. Źródło prawdy jest w dwóch miejscach — zmiana w migracji wymaga ręcznej synchronizacji z fixtures. | medium | medium |
| Q-02 | `AppFixtures` — `doctrine:fixtures:load` domyślnie purguje bazę, więc genres z migracji zostaną usunięte i zastąpione przez fixtures. To działa poprawnie, ale w środowisku `--append` mogłoby dać conflict na unique name. Standardowy tryb — nie jest problem. | info | none |
| Q-03 | `LibraryIsolationE2ETest::createBook()` — helper jest prywatny i powtarza logikę z `LibrarySecurityE2ETest::setupTwoLibrariesWithData()`. Duplikacja tworzenia książki w dwóch plikach testowych. | low | low |
| Q-04 | PHPStan level max — ✅ czyste. CS-Fixer — ✅ czyste. PHPMD — ✅ czyste. `lint:container` — ✅ czyste. Unit tests — ✅ 135 green. | pass | — |

---

## 4. Architecture & Pattern Consistency

| # | Finding | Severity | Impact |
|---|---------|----------|--------|
| A-01 | `AppFixtures` używa `PasswordHasherInterface` (natywny hasher) tak samo jak `RegisterUserHandler` — spójne z istniejącym wzorcem DI. | pass | — |
| A-02 | E2E testy używają `HttpBrowser` (nie Panther) — zgodne z konwencją projektu (`BookCreateApiTest`, `AuthFlowTest`). | pass | — |
| A-03 | Randomizacja danych testowych via `bin2hex(random_bytes())` — zgodne z istniejącymi testami (`AuthFlowTest`, `BookCreateApiTest`). Brak kolizji. | pass | — |
| A-04 | `createBook()` helper mógłby zostać promowany do `E2ETestCase` (analogicznie do `createShelf()`) — ale zakres M4 tego nie wymaga. | low | low |

---

## 5. Success Criteria Traceability

| End State (plan) | Covered by |
|---|---|
| `registerUser()` z polami library — istniejące testy przechodzą | `E2ETestCase.php` + `AuthFlowTest` |
| E2E create/join registration flow | `LibraryRegistrationCreateE2ETest`, `LibraryRegistrationJoinE2ETest` |
| E2E izolacja danych | `LibraryIsolationE2ETest` |
| Brak endpointów do zmiany/usunięcia biblioteki (SC-05) | `LibraryEdgeCasesE2ETest` |
| DataFixtures z Library | `AppFixtures` |
| Security: manipulacja ID → 404 | `LibrarySecurityE2ETest` |

**Verdict:** Wszystkie 6 end-state objectives pokryte implementacją.

---

## 6. Findings Summary for Triage

| ID | Severity | Impact | Finding | Suggested outcome |
|---|---|---|---|---|
| Q-01 | medium | medium | Duplikacja listy genres między `AppFixtures` a migracją `Version20251028120001`. | **fix** or **accept as risk** — fixtures purgują bazę, więc muszą re-tworzyć genres. Można wyciągnąć wspólną stałą, ale dodaje coupling. Pragmatycznie: accept. |
| Q-03 | low | low | `createBook()` helper zduplikowany między `LibraryIsolationE2ETest` a `LibrarySecurityE2ETest`. | **skip** — promowanie do `E2ETestCase` to osobny refactor poza scope M4. |
| A-04 | low | low | `createBook()` nie jest w `E2ETestCase` choć `createShelf()` jest. | **skip** — j.w. |
| S-01..S-03 | info | low | Drobne rozszerzenia zakresu (dodatkowe test cases). | **skip** — wartościowe wzbogacenia, nie scope creep. |
| S-04 | info | none | Plan mówił o `GET /api/books/{id}` ale endpoint nie istnieje. | **skip** — poprawna decyzja implementacyjna, plan nieaktualny w tym punkcie. |

---

## Verdict

**PASS.** Implementacja M4 jest zgodna z planem, bezpieczna, spójna z architekturą projektu i pokrywa wszystkie success criteria. Jedyne finding o medium severity (Q-01 — duplikacja genres) jest świadomą konsekwencją sposobu działania doctrine:fixtures:load i nie wymaga natychmiastowej akcji.

