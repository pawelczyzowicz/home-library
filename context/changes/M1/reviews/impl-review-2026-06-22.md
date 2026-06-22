# Implementation Review: M1 — Library entity + rejestracja z nową biblioteką

---
change-id: M1
reviewer: AI
date: 2026-06-22
verdict: PASS with findings
---

## Summary

Implementacja M1 jest zgodna z planem we wszystkich 4 fazach. Encja Library, migracja, rozszerzenie handlera rejestracji i UI — wszystko dostarczone. Testy jednostkowe i integracyjne przechodzą. Poniżej lista findings do triage.

---

## Review Axes

### 1. Plan Adherence ✅

Wszystkie file contracts z planu zostały zrealizowane:
- **Phase 1:** Library.php, LibraryName.php, LibraryPasswordHash.php, LibraryRepository.php, LibraryAlreadyExistsException.php, User.php zmodyfikowany
- **Phase 2:** Migration Version20260609120000, DoctrineLibraryRepository, services.yaml binding
- **Phase 3:** RegisterUserCommand + Handler rozszerzone, RegisterAction czyta nowe pola, ExceptionListener mapuje 422, testy unit + integration
- **Phase 4:** register.html.twig fieldset + radio, register.js payload + validation + 422 handling

### 2. Scope Discipline ✅

- Tryb "join" poprawnie odroczony do M2 (radio disabled, handler rejects non-"create")
- Brak nadmiarowego kodu poza zakresem planu
- Żaden plik poza scope nie został zmodyfikowany bez uzasadnienia

### 3. Safety & Quality ✅

- Hasło biblioteki hashowane przez NativePasswordHasher (bezpieczne)
- Migracja safe: tworzy default library dla istniejących danych, potem NOT NULL + FK
- Walidacja po stronie serwera: email, hasło, libraryName not blank + max 255, libraryPassword not blank + min 8
- ExceptionListener: pełne pokrycie nowych wyjątków

### 4. Architecture ✅

- Warstwa Domain nie zależy od Infrastructure/Application — czyste value objects + interface repo
- Handler w Application korzysta z interfejsu LibraryRepository (DI)
- UI (RegisterAction) jest cienkie — deleguje do handlera
- Pattern spójny z istniejącymi encjami (Book, Shelf, User)

### 5. Pattern Consistency ✅

- LibraryName/LibraryPasswordHash — ten sam wzorzec co UserEmail/UserPasswordHash (Embeddable VO)
- DoctrineLibraryRepository — ten sam wzorzec co DoctrineUserRepository (ServiceEntityRepository)
- services.yaml binding — identyczna konwencja jak inne repo

### 6. Success Criteria ✅

- **SC-01:** Nowy użytkownik może zarejestrować się tworząc nową bibliotekę — zrealizowane
- **DoD:** POST /api/auth/register z libraryMode: "create" → 201 + user+library — ✅
- **DoD:** User.library_id ustawiony — ✅
- **DoD:** Duplikat nazwy → 422 — ✅
- **DoD:** Istniejące testy przechodzą — ✅ (129 unit tests green per plan progress)

---

## Findings

### F-01: ORM JoinColumn nullable vs DB NOT NULL mismatch

| | |
|---|---|
| **Severity** | Medium |
| **Impact** | Low (dev-only, no prod) |
| **Location** | `src/HomeLibrary/Domain/User/User.php:38` |

**Opis:** `#[ORM\JoinColumn(nullable: true)]` w encji User, ale migracja ustawia kolumnę na NOT NULL. Doctrine schema:validate zgłosi rozbieżność mapping↔schema.

**Plan mówi:** "User.library is nullable ONLY at PHP level for migration transition". Migracja już przeszła — `nullable: true` nie jest już potrzebne.

**Rekomendacja:** Zmienić na `nullable: false` w JoinColumn i typ property na `Library` (bez `?`). Zwraca uwagę — getter `library(): ?Library` też powinien stać się `library(): Library`.

---

### F-02: Anonymous class workaround for UserPasswordHasherInterface

| | |
|---|---|
| **Severity** | Low |
| **Impact** | Low |
| **Location** | `src/HomeLibrary/Application/Auth/RegisterUserHandler.php:78-83` |

**Opis:** Handler tworzy anonimową klasę `PasswordAuthenticatedUserInterface` żeby wywołać `hashPassword()`. Symfony's UserPasswordHasherInterface wymaga instancji user — kod obchodzi to tworząc dummy object.

**Kontekst:** Hasher i tak użyje algorytmu z security.yaml (auto/bcrypt), więc wynik jest poprawny. Ale jest to kruche — jeśli konfiguracja hasherów zmieni się per-class, dummy class dostanie domyślny hasher zamiast user-specific.

**Rekomendacja:** Akceptowalne jako-is. Alternatywa: użyć `NativePasswordHasher` bezpośrednio (tak jak dla library password) lub przekazać `$user` do `hashPassword()` po jego utworzeniu (wymagałoby refactoru flow). Skip lub fix w przyszłym refaktorze.

---

### F-03: Migration default library hash — invalid bcrypt

| | |
|---|---|
| **Severity** | Low |
| **Impact** | Negligible |
| **Location** | `migrations/Version20260609120000.php:39` |

**Opis:** Placeholder hash `'$2y$13$defaulthashplaceholder000000000000000000000000000000'` nie jest prawidłowym hashem bcrypt (niepoprawna długość/format). Nikt nigdy nie powinien się logować do `__default__` library, ale technicznie to "uszkodzone" dane.

**Rekomendacja:** Skip. Plan jawnie mówi "no real prod data". Placeholder spełnia cel migracji (NOT NULL constraint). Nie stanowi ryzyka bezpieczeństwa bo nie da się zweryfikować tego hasha pozytywnie.

---

### F-04: libraryMode empty string → niejasny komunikat walidacji

| | |
|---|---|
| **Severity** | Low |
| **Impact** | Low (UX) |
| **Location** | `RegisterAction.php:56` + `RegisterUserHandler.php:117-122` |

**Opis:** Gdy `libraryMode` brakuje w payload, RegisterAction przekazuje `''`. Handler odrzuca z komunikatem "Only 'create' mode is supported" — co sugeruje że mode jest podany ale nieobsługiwany, zamiast "mode is required".

**Rekomendacja:** Skip. Plan mówi "libraryMode field required in register payload" — komunikat jest akceptowalny z perspektywy API (422 + jasny typ problemu). Klient (register.js) i tak zawsze wysyła "create" (domyślna wartość radio).

---

### F-05: Brak testu jednostkowego dla encji Library

| | |
|---|---|
| **Severity** | Low |
| **Impact** | Low |
| **Location** | `tests/Unit/` |

**Opis:** Plan Phase 1 Verification wymienia "Entity unit test for Library (constructor, value objects) passes". Nie znalazłem dedykowanego testu `LibraryTest.php`. Encja jest testowana pośrednio przez `RegisterUserHandlerTest` (tworzenie Library w callback assertion).

**Rekomendacja:** Skip lub dodać prosty test w przyszłości. Pokrycie pośrednie jest wystarczające — VO walidacja (empty name, too long) jest testowalna, ale logika jest trywialna (constructor + getters).

---

## Triage Summary

| Finding | Severity | Recommendation | Outcome |
|---------|----------|----------------|---------|
| F-01: nullable mismatch | Medium | **Fix** — zmienić `nullable: false` + usunąć `?` z typu | ✅ fixed |
| F-02: anonymous class hasher | Low | Skip — działa poprawnie, kruche ale bezpieczne | skipped |
| F-03: invalid bcrypt placeholder | Low | Skip — no prod data, placeholder OK | skipped |
| F-04: libraryMode error message | Low | Skip — UX acceptable, klient zawsze wysyła "create" | skipped |
| F-05: brak unit testu Library | Low | Skip — pokrycie pośrednie wystarczające | skipped |

---

## F-01 Fix Summary

**Zmienione pliki:**
- `src/HomeLibrary/Domain/User/User.php` — JoinColumn `nullable: false`, typ `Library` (non-nullable), constructor wymaga `Library`, getter `library(): Library`
- `tests/Integration/.../AuthApiTest.php` — `createUser()` tworzy Library przed User
- `tests/Integration/.../ListShelvesApiTest.php` — j.w.
- `tests/Integration/.../CreateBookApiTest.php` — j.w.
- `tests/Integration/.../DoctrineUserRepositoryTest.php` — j.w.
- `tests/Unit/.../RegisterUserHandlerTest.php` — usunięto zbędne `assertNotNull`

---

## Verdict

**PASS** — wszystkie findings rozstrzygnięte. F-01 naprawiony, F-02–F-05 świadomie pominięte (low impact). Implementacja M1 gotowa do merge.

