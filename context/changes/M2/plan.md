# Plan: M2 — Rejestracja z dołączeniem do istniejącej biblioteki

---
change-id: M2
status: planned
phases: 4
---

## End State

Po implementacji M2:
- `POST /api/auth/register` z `libraryMode: "join"` wyszukuje bibliotekę po nazwie, weryfikuje hasło (constant-time via `password_verify`), tworzy użytkownika przypisanego do istniejącej biblioteki
- Nieistniejąca biblioteka → 422 z typem `library-not-found`
- Złe hasło biblioteki → 422 z typem `invalid-library-password`
- Brak endpointu listującego biblioteki (użytkownik wpisuje nazwę ręcznie)
- UI: radio "dołączam do istniejącej" aktywne, te same pola (nazwa + hasło biblioteki), obsługa nowych komunikatów błędów
- Istniejące testy "create" mode przechodzą bez zmian
- Nowe testy unit + integration pokrywają "join" mode (happy path + error paths)

---

## Phase 1 — Domain: New exceptions

**Goal:** Dodać wyjątki domenowe dla błędów trybu "join" (biblioteka nie znaleziona, złe hasło).

### File Contracts

| File | Action | Contract |
|------|--------|----------|
| `src/HomeLibrary/Domain/Library/Exception/LibraryNotFoundException.php` | CREATE | Domain exception extending `\DomainException`. Static factory `forName(string $name): self` z komunikatem "Library with name \"X\" not found." |
| `src/HomeLibrary/Domain/Library/Exception/InvalidLibraryPasswordException.php` | CREATE | Domain exception extending `\DomainException`. Static factory `forName(string $name): self` z komunikatem "Invalid password for library \"X\"." |

### Verification

- [ ] `bin/console lint:container` passes
- [ ] No PHPStan errors at configured level
- [ ] Klasy loadowalne via autoload (namespace matches directory)

### Notes

- Oba wyjątki wzorowane na istniejącym `LibraryAlreadyExistsException`
- Komunikaty w exception messages służą do logowania — w response API trafiają via ExceptionListener (Phase 2)

---

## Phase 2 — Application: Handler "join" mode + ExceptionListener mapping

**Goal:** Rozszerzyć `RegisterUserHandler` o obsługę trybu "join" oraz zmapować nowe wyjątki w ExceptionListener.

### File Contracts

| File | Action | Contract |
|------|--------|----------|
| `src/HomeLibrary/Application/Auth/RegisterUserHandler.php` | MODIFY | 1) `validateLibraryMode()` — akceptuje "create" i "join" (inne → ValidationException). 2) Nowa metoda `joinLibrary(RegisterUserCommand): Library` — wywołuje `validateLibraryFields()`, potem `libraryRepository->findByName()`, jeśli null → throw `LibraryNotFoundException::forName()`, potem `libraryPasswordHasher->verify($storedHash, $plainPassword)`, jeśli false → throw `InvalidLibraryPasswordException::forName()`, zwraca znalezioną Library. 3) W `__invoke()` — rozdzielenie: if mode "create" → `createLibrary()`, else → `joinLibrary()`. |
| `src/HomeLibrary/UI/Api/ExceptionListener.php` | MODIFY | Dodać dwa nowe case w `createProblemResponseFor()`: `LibraryNotFoundException` → 422, type `https://example.com/problems/library-not-found`, title "Library not found". `InvalidLibraryPasswordException` → 422, type `https://example.com/problems/invalid-library-password`, title "Invalid library password". |

### Verification

- [ ] `bin/console lint:container` passes
- [ ] No PHPStan errors
- [ ] `php-cs-fixer` — no violations
- [ ] `phpmd` — no violations
- [ ] Manual test: `curl POST /api/auth/register` z `libraryMode: "join"` i prawidłowymi danymi → 201
- [ ] Manual test: nieistniejąca biblioteka → 422 `library-not-found`
- [ ] Manual test: złe hasło → 422 `invalid-library-password`

### Notes

- `PasswordHasherInterface::verify(string $hashedPassword, string $plainPassword): bool` — constant-time comparison (Symfony NativePasswordHasher uses `password_verify()`)
- `joinLibrary()` reużywa `validateLibraryFields()` — te same reguły walidacji (not blank, length) w obu trybach
- Nie tworzymy nowej Library w trybie "join" — nie wywołujemy `libraryRepository->save()`
- RegisterAction i RegisterUserCommand nie wymagają zmian — payload `libraryMode: "join"` już jest parsowany i przekazywany

---

## Phase 3 — UI: Enable "join" radio + error handling

**Goal:** Aktywować radio "dołączam do istniejącej" w formularzu oraz dodać obsługę nowych typów błędów backend.

### File Contracts

| File | Action | Contract |
|------|--------|----------|
| `templates/auth/register.html.twig` | MODIFY | 1) Usunąć `disabled` z input radio "join". 2) Usunąć badge `<span class="form-field__badge">wkrótce</span>`. 3) Usunąć klasę `form-field__radio--disabled` z label wrapping radio "join". |
| `assets/auth/register.js` | MODIFY | W `handleFailure()` dodać obsługę nowych typów w bloku `case 422`: `library-not-found` → ustawić fieldError na `libraryName` z komunikatem "Biblioteka o takiej nazwie nie istnieje." + focusField('libraryName'). `invalid-library-password` → ustawić fieldError na `libraryPassword` z komunikatem "Nieprawidłowe hasło biblioteki." + focusField('libraryPassword'). Kolejność: library-not-found, invalid-library-password, library-conflict, fallback validation. |

### Verification

- [ ] Page renders without JS errors
- [ ] Radio "join" jest klikalny
- [ ] Formularz z mode "join" wysyła poprawny payload
- [ ] Błąd "library-not-found" wyświetla komunikat przy polu nazwy
- [ ] Błąd "invalid-library-password" wyświetla komunikat przy polu hasła biblioteki
- [ ] Tryb "create" nadal działa bez regresji
- [ ] Client-side validation działa identycznie dla obu trybów

### Notes

- Nie zmieniamy logiki client-side validation — reguły pól biblioteki (nazwa required, hasło min 8) są identyczne w obu trybach
- Nie dodajemy listy bibliotek ani autocomplete — user wpisuje nazwę ręcznie (FR-008)
- Submit button label "Utwórz konto" pozostaje (dotyczy konta użytkownika, nie biblioteki)

---

## Phase 4 — Tests: Unit + Integration

**Goal:** Pokryć tryb "join" testami unit i integration.

### File Contracts

| File | Action | Contract |
|------|--------|----------|
| `tests/Unit/HomeLibrary/Application/Auth/RegisterUserHandlerTest.php` | MODIFY | 1) Zmienić test `itFailsWhenLibraryModeIsNotCreate` na `itFailsWhenLibraryModeIsInvalid` — testować z mode "invalid" zamiast "join". 2) Dodać test `itRegistersUserByJoiningExistingLibrary` — mock findByName zwraca Library, mock verify zwraca true, assert: user.library === found Library, libraryRepository->save() NIE wywoływane. 3) Dodać test `itFailsWhenJoiningNonExistentLibrary` — mock findByName zwraca null → expectException LibraryNotFoundException. 4) Dodać test `itFailsWhenJoiningLibraryWithWrongPassword` — mock findByName zwraca Library, mock verify zwraca false → expectException InvalidLibraryPasswordException. |
| `tests/Integration/HomeLibrary/UI/Api/Auth/AuthApiTest.php` | MODIFY | 1) Dodać test `itRegistersUserByJoiningExistingLibrary` — rejestracja "create", potem drugi user z "join" same library name/password → 201. 2) Dodać test `itRejectsJoinWhenLibraryDoesNotExist` — join z nieistniejącą nazwą → 422 type `library-not-found`. 3) Dodać test `itRejectsJoinWhenLibraryPasswordIsWrong` — create library, join z błędnym hasłem → 422 type `invalid-library-password`. |

### Verification

- [ ] `bin/phpunit tests/Unit/HomeLibrary/Application/Auth/RegisterUserHandlerTest.php` — all green
- [ ] `bin/phpunit tests/Integration/HomeLibrary/UI/Api/Auth/AuthApiTest.php` — all green
- [ ] Istniejące testy "create" mode nadal przechodzą (brak regresji)
- [ ] `php-cs-fixer` — no violations
- [ ] `phpmd` — no violations

### Notes

- Test join happy path musi weryfikować, że `libraryRepository->save()` NIE jest wywoływane (nie tworzymy nowej biblioteki)
- Integration test join happy path powinien potwierdzić, że oba users mają ten sam library (via weryfikacja response lub DB)
- Istniejący test `itFailsWhenLibraryModeIsNotCreate` musi być zmieniony — "join" jest teraz poprawnym mode

---

## Progress

| Phase | Status | Commit SHA | Notes |
|-------|--------|------------|-------|
| 1 | ✅ done | — | LibraryNotFoundException + InvalidLibraryPasswordException. lint:container OK, PHPStan clean. |
| 2 | ✅ done | — | Handler join mode + ExceptionListener mapping. PHPStan/CS/PHPMD clean. Test regression fixed (itFailsWhenLibraryModeIsInvalid). |
| 3 | ⬜ planned | — | |
| 4 | ⬜ planned | — | |

---

## Risk Register

| Risk | Mitigation |
|------|-----------|
| Timing attack: różnica czasu "nie znaleziono" vs "złe hasło" | `password_verify()` via Symfony PasswordHasher jest constant-time. Nazwy bibliotek nie są sekretem — ryzyko akceptowalne (LOW) |
| Information leakage: osobne typy błędów ujawniają istnienie biblioteki | Akceptowane — nazwy nie są tajne, brak endpointu enumeracji |
| Regresja: zmiana validateLibraryMode łamie test "join→fail" | Test zmieniony w Phase 4 — testuje "invalid" mode |

## Decisions

- Oba błędy (not-found, wrong-password) mapują na 422 (nie 404/401) — to walidacja danych rejestracji, nie auth/resource lookup
- Osobne typy problemów (`library-not-found` vs `invalid-library-password`) — priorytet UX nad security-through-obscurity (LOW risk)
- `joinLibrary()` reużywa `validateLibraryFields()` — te same reguły walidacyjne w obu trybach
- Nie dodajemy dummy `password_verify()` dla nieistniejącej biblioteki — timing difference minimalny, nazwy niesekretne
- RegisterAction i RegisterUserCommand bez zmian — already support libraryMode "join" in payload

