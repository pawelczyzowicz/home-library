# Plan Brief: M4 — Hardening — edge cases, walidacja, testy E2E

---
change-id: M4
status: draft
full-plan: context/changes/M4/plan.md
---

## Cel

Pokrycie testami E2E flow rejestracji z biblioteką (create/join), walidacja izolacji danych między bibliotekami, security review (ID manipulation), oraz naprawa infrastruktury testowej E2E.

## Decyzje architektoniczne

- **`registerUser()` backward-compatible** — domyślny `libraryMode: 'create'`, istniejące testy nie wymagają zmian
- **HttpBrowser** (nie Panther) dla edge case i security testów — szybsze, testują logikę serwera
- **Unikalne nazwy bibliotek** via `bin2hex(random_bytes(4))` — brak kolizji między testami
- **Phase 2+3 BLOCKED by M3** — izolacja i fixtures wymagają `library_id` w books/shelves

## Fazy

| # | Zakres | Gotowe gdy | Status |
|---|--------|-----------|--------|
| 1 | Fix `registerUser()` + E2E testy rejestracji create/join + edge cases | Wszystkie E2E testy green | DO ZROBIENIA |
| 2 | E2E izolacja danych + security review (ID manipulation) | Brak cross-library access | BLOCKED by M3 |
| 3 | DataFixtures z Library + finalizacja | `doctrine:fixtures:load` + CI green | BLOCKED by M3 |

## Kluczowe pliki per faza

### Phase 1 — Fix E2E infrastructure + testy rejestracji
- `tests/E2E/E2ETestCase.php` — rozszerzenie `registerUser()` o pola biblioteki
- `tests/E2E/LibraryRegistrationCreateE2ETest.php` — E2E: rejestracja create, duplikat nazwy → 422
- `tests/E2E/LibraryRegistrationJoinE2ETest.php` — E2E: join do istniejącej, złe hasło → 422, brak biblioteki → 422
- `tests/E2E/LibraryEdgeCasesE2ETest.php` — brak PUT/PATCH/DELETE na `/api/libraries` → 404/405

### Phase 2 — Izolacja + security (BLOCKED)
- `tests/E2E/LibraryIsolationE2ETest.php` — user A vs user B w różnych bibliotekach
- `tests/E2E/LibrarySecurityE2ETest.php` — ID manipulation → 404 (nie wyciek)

### Phase 3 — DataFixtures (BLOCKED)
- `src/DataFixtures/AppFixtures.php` — 2 biblioteki + użytkownicy + książki/półki z `library_id`

## Ryzyko

| Zagrożenie | Mitygacja |
|-----------|-----------|
| Flaky E2E z powodu shared DB state | TRUNCATE w setUp, unikalne nazwy |
| Phase 2/3 blokowane przez M3 | Jasny podział faz; Phase 1 niezależna |
| Brak endpointów library CRUD wygląda jak bug | Jawny test negatywny dokumentuje intencję (SC-05) |

## Definition of Done

- [ ] `registerUser()` zawiera pola biblioteki — istniejące E2E przechodzą
- [ ] E2E testy pokrywają create + join flow rejestracji
- [ ] Edge case: brak endpointów CRUD dla bibliotek potwierdzone testami
- [ ] (po M3) Izolacja danych zweryfikowana E2E
- [ ] (po M3) Security: manipulacja ID → 404
- [ ] (po M3) DataFixtures ładują się poprawnie z nowym modelem

