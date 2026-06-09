# Plan Brief: M1 — Library entity + rejestracja z nową biblioteką

**Change:** M1  
**Phases:** 4  
**Risk:** Low

## TL;DR

Dodajemy encję Library (UUID, name unique, hashed password, timestamps), wiążemy User→Library (ManyToOne NOT NULL), rozszerzamy flow rejestracji o tworzenie biblioteki (tryb "create"), aktualizujemy UI.

## Phase Map

| # | Name | Key Files | Gate |
|---|------|-----------|------|
| 1 | Domain: Library entity + User relation | `Domain/Library/Library.php`, `LibraryName.php`, `LibraryPasswordHash.php`, `LibraryRepository.php`, `User.php` | PHPStan passes, lint:container OK |
| 2 | Infrastructure: Migration + Repository | `migrations/Version20260609120000.php`, `DoctrineLibraryRepository.php` | schema:validate, migration runs |
| 3 | Application: Command + Handler | `RegisterUserCommand.php`, `RegisterUserHandler.php`, `RegisterAction.php`, `ExceptionListener.php` + tests | Unit + Integration tests green |
| 4 | UI: Form fields | `register.html.twig`, `register.js` | Form renders, submit works, validation shows |

## Critical Contracts

- `Library(id: UUID, name: LibraryName, passwordHash: LibraryPasswordHash)` — aggregate root
- `LibraryRepository::existsByName(string): bool` — uniqueness check
- `RegisterUserCommand` adds: `libraryName`, `libraryPassword`, `libraryMode`
- `POST /api/auth/register` payload adds: `libraryName`, `libraryPassword`, `libraryMode`
- `LibraryAlreadyExistsException` → 422 via ExceptionListener

## Decisions

- libraryMode "join" deferred to M2
- Library password hashed via Symfony PasswordHasher (NativePasswordHasher)
- No silent defaults — libraryMode required in payload

