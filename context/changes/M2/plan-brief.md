# Plan Brief: M2 — Rejestracja z dołączeniem do istniejącej biblioteki

**Change:** M2  
**Phases:** 4  
**Risk:** Low  
**Status:** done

## TL;DR

Rozszerzamy flow rejestracji o tryb "join" — użytkownik podaje nazwę i hasło istniejącej biblioteki, handler weryfikuje dane (constant-time `password_verify`), tworzy konto przypisane do tej biblioteki. UI aktywuje radio "dołączam do istniejącej" i obsługuje nowe komunikaty błędów.

## Phase Map

| # | Name | Key Files | Gate |
|---|------|-----------|------|
| 1 | Domain: New exceptions | `Domain/Library/Exception/LibraryNotFoundException.php`, `InvalidLibraryPasswordException.php` | PHPStan passes, lint:container OK |
| 2 | Application: Handler "join" + ExceptionListener | `RegisterUserHandler.php`, `ExceptionListener.php` | Manual curl: join→201, not-found→422, wrong-pass→422 |
| 3 | UI: Enable join radio + error handling | `register.html.twig`, `register.js` | Radio active, new error messages display correctly |
| 4 | Tests: Unit + Integration | `RegisterUserHandlerTest.php`, `AuthApiTest.php` | All tests green, no regressions |

## Critical Contracts

- `LibraryRepository::findByName(string): ?Library` — lookup for join mode (already exists from M1)
- `PasswordHasherInterface::verify(storedHash, plain): bool` — constant-time password check
- `LibraryNotFoundException` → 422 type `library-not-found`
- `InvalidLibraryPasswordException` → 422 type `invalid-library-password`
- `RegisterUserHandler::joinLibrary()` — findByName → verify → return existing Library (no save)
- `validateLibraryMode()` accepts "create" | "join" (was: only "create")

## Decisions

- Both errors map to 422 (registration validation, not auth/resource)
- Separate problem types for UX precision — LOW info-leakage risk (library names not secret)
- No dummy password_verify for non-existent library — timing diff minimal
- RegisterAction / RegisterUserCommand unchanged — payload already supports "join"
- No library listing endpoint — user must know the name (FR-008)

