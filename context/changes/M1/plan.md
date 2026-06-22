# Plan: M1 — Library entity + rejestracja z nową biblioteką

---
change-id: M1
status: done
phases: 4
---

## End State

Po implementacji M1:
- Istnieje encja `Library` z polami: id (UUID), name (unique), password (hashed), created_at/updated_at
- Tabela `libraries` w DB + kolumna `library_id` (FK NOT NULL) w tabeli `users`
- Rejestracja (`POST /api/auth/register`) przyjmuje dodatkowe pola: `libraryName`, `libraryPassword`, `libraryMode`
- Tryb `create` tworzy nową bibliotekę + użytkownika przypisanego do niej
- Duplikat nazwy biblioteki → 422 z komunikatem
- Formularz UI zawiera radio + pola biblioteki (widoczne dla obu trybów, ale w M1 obsługujemy tylko "create")
- Istniejące testy przechodzą (zaktualizowane o nowe pola)

---

## Phase 1 — Domain: Library entity + User relation

**Goal:** Zdefiniować encję Library w warstwie Domain oraz zmodyfikować User o relację do Library.

### File Contracts

| File | Action | Contract |
|------|--------|----------|
| `src/HomeLibrary/Domain/Library/Library.php` | CREATE | Entity: id(UUID), name(string unique), passwordHash(string), timestamps. Constructor + getters. |
| `src/HomeLibrary/Domain/Library/LibraryName.php` | CREATE | Value object: embeddable, varchar(255), validation (not empty, max 255). |
| `src/HomeLibrary/Domain/Library/LibraryPasswordHash.php` | CREATE | Value object: embeddable, text, non-empty validation. |
| `src/HomeLibrary/Domain/Library/LibraryRepository.php` | CREATE | Interface: `save(Library)`, `existsByName(string): bool`, `findByName(string): ?Library`. |
| `src/HomeLibrary/Domain/Library/Exception/LibraryAlreadyExistsException.php` | CREATE | Domain exception for duplicate library name. |
| `src/HomeLibrary/Domain/User/User.php` | MODIFY | Add `private ?Library $library` field (ManyToOne), `library(): Library` getter, `assignToLibrary(Library)` method. Constructor still accepts optional null for backward compat during migration. |

### Verification

- [ ] `bin/console lint:container` passes
- [ ] No PHPStan errors at configured level
- [ ] `php-cs-fixer` — no violations (single_blank_line_at_eof, etc.)
- [ ] `phpmd` — no violations (or suppressed with justification)
- [ ] Entity unit test for Library (constructor, value objects) passes

### Notes

- Library.name stored as-is (case-sensitive uniqueness w DB)
- LibraryPasswordHash reuses same pattern as UserPasswordHash
- User.library is nullable ONLY at PHP level for migration transition — DB will be NOT NULL after Phase 2

---

## Phase 2 — Infrastructure: Migration + Repository

**Goal:** Dodać migrację DB oraz implementację DoctrineLibraryRepository.

### File Contracts

| File | Action | Contract |
|------|--------|----------|
| `migrations/Version20260609120000.php` | CREATE | Creates `libraries` table (id UUID PK, name VARCHAR(255) UNIQUE NOT NULL, password_hash TEXT NOT NULL, created_at TIMESTAMPTZ NOT NULL, updated_at TIMESTAMPTZ NOT NULL). Adds `library_id UUID` to `users` (nullable initially for data compat). Creates default library + assigns all existing users. Alters `library_id` to NOT NULL + FK. |
| `src/HomeLibrary/Infrastructure/Persistence/DoctrineLibraryRepository.php` | CREATE | Implements LibraryRepository: save(), existsByName(), findByName(). Extends ServiceEntityRepository. |
| `config/services.yaml` | VERIFY | Autowiring should handle binding LibraryRepository → DoctrineLibraryRepository (check if explicit bind needed). |

### Verification

- [ ] `bin/console doctrine:migrations:migrate` runs without error
- [ ] `bin/console doctrine:schema:validate` passes
- [ ] `php-cs-fixer` — no violations
- [ ] `phpmd` — no violations
- [ ] Integration test: DoctrineLibraryRepository can save and find a Library

### Notes

- Migration is safe for empty DB (no real prod data)
- Default library created to satisfy NOT NULL for any existing test fixtures
- Index on `users.library_id` added in migration

---

## Phase 3 — Application: RegisterUserCommand + Handler extension

**Goal:** Rozszerzyć flow rejestracji o tworzenie biblioteki (tryb "create").

### File Contracts

| File | Action | Contract |
|------|--------|----------|
| `src/HomeLibrary/Application/Auth/Command/RegisterUserCommand.php` | MODIFY | Add fields: `libraryName` (string), `libraryPassword` (string), `libraryMode` (string, default: 'create'). New getters. |
| `src/HomeLibrary/Application/Auth/RegisterUserHandler.php` | MODIFY | Inject LibraryRepository + PasswordHasherInterface. In mode "create": validate libraryName (not blank, max 255), check uniqueness, hash libraryPassword, create Library, assign to User before save. Throw LibraryAlreadyExistsException on duplicate. |
| `src/HomeLibrary/UI/Api/Auth/RegisterAction.php` | MODIFY | Read `libraryName`, `libraryPassword`, `libraryMode` from payload. Pass to RegisterUserCommand constructor. |
| `src/HomeLibrary/UI/Api/ExceptionListener.php` | MODIFY | Add mapping for `LibraryAlreadyExistsException` → 422 problem response with type `https://example.com/problems/library-conflict`. |
| `tests/Unit/HomeLibrary/Application/Auth/RegisterUserHandlerTest.php` | MODIFY | Update existing tests to pass library fields. Add new tests: successful library creation, duplicate library name rejection. |
| `tests/Integration/HomeLibrary/UI/Api/Auth/AuthApiTest.php` | MODIFY | Update existing test payloads with library fields. Add test: register with new library returns 201 + library info. Add test: duplicate library name returns 422. |

### Verification

- [ ] Unit tests pass: `bin/phpunit tests/Unit/HomeLibrary/Application/Auth/`
- [ ] Integration tests pass: `bin/phpunit tests/Integration/HomeLibrary/UI/Api/Auth/`
- [ ] `php-cs-fixer` — no violations
- [ ] `phpmd` — no violations
- [ ] Manual test: `curl POST /api/auth/register` with libraryMode "create" creates user+library
- [ ] Duplicate library name returns 422

### Notes

- `libraryMode` accepts "create" only in M1 — "join" deferred to M2
- Library password is hashed using Symfony's PasswordHasherInterface (native hasher, not UserPasswordHasherInterface which requires a user instance)
- Backward compat: if libraryMode is missing from payload, default to "create" (or reject — TBD in Phase 3)

---

## Phase 4 — UI: Registration form with library fields

**Goal:** Dodać pola biblioteki do formularza rejestracji (front-end).

### File Contracts

| File | Action | Contract |
|------|--------|----------|
| `templates/auth/register.html.twig` | MODIFY | Add radio group (name="libraryMode"): "Tworzę nową bibliotekę" (value="create") / "Dołączam do istniejącej" (value="join", disabled in M1). Add inputs: libraryName, libraryPassword. |
| `assets/auth/register.js` | MODIFY | Read libraryMode, libraryName, libraryPassword from form. Include in POST payload. Add client-side validation: libraryName required + max 255, libraryPassword required. Handle 422 error mapping for library fields. |

### Verification

- [ ] Page renders without JS errors
- [ ] Form submits with library fields
- [ ] Client-side validation shows errors for empty library fields
- [ ] Server-side duplicate library name shows error in UI
- [ ] `php-cs-fixer` — no violations (jeśli zmienione pliki PHP)
- [ ] `phpmd` — no violations (jeśli zmienione pliki PHP)
- [ ] E2E smoke: full registration flow works

### Notes

- Radio "dołączam do istniejącej" visible but functionally disabled (greyed out or hidden) — deferred to M2
- UX: library fields appear below user fields, separated by heading "Biblioteka"

---

## Progress

| Phase | Status | Commit SHA | Notes |
|-------|--------|------------|-------|
| 1 | ✅ done | (HEAD) | Library entity, value objects, repo interface, exception, User relation. 129 unit tests green. |
| 2 | ✅ done | 8b10dbb | Migration, DoctrineLibraryRepository, services.yaml binding, integration test. PHPStan/CS/PHPMD clean, 129 unit tests green. |
| 3 | ✅ done | — | RegisterUserCommand + Handler rozszerzone o library fields, RegisterAction czyta pola z payload, ExceptionListener mapuje LibraryAlreadyExistsException→422, unit test (RegisterUserHandlerTest) + integration test (AuthApiTest) z library fields. |
| 4 | ✅ done | — | register.html.twig: fieldset "Biblioteka" z radio create/join (join disabled), pola libraryName + libraryPassword. register.js: payload rozszerzony, client-side validation, handleFailure obsługuje library-conflict 422. CSS: style fieldset, radio, badge. |

---

## Risk Register

| Risk | Mitigation |
|------|-----------|
| Existing tests break due to new NOT NULL library_id | Migration creates default library; test fixtures updated |
| PasswordHasher coupling (UserPasswordHasherInterface needs User instance) | Use native `PasswordHasherFactoryInterface->getPasswordHasher('library')` or direct `password_hash()` via NativePasswordHasher |
| Large diff in Phase 3 (handler + tests) | Keep scope tight: only "create" mode, "join" is M2 |

## Decisions

- Library.name is case-sensitive at DB level (UNIQUE constraint). UI trims whitespace.
- Library password hashed with same algorithm as user passwords (via Symfony PasswordHasher component).
- `libraryMode` field required in register payload starting M1 — no silent default to avoid confusion.
- ExceptionListener maps LibraryAlreadyExistsException to 422 (not 409) — it's a validation concern, not resource conflict.

