# Change: M2 — Rejestracja z dołączeniem do istniejącej biblioteki

---
change-id: M2
roadmap-ref: M2
status: done
created: 2026-06-23
---

## Summary

Rozszerzenie flow rejestracji o tryb "join" — użytkownik podaje nazwę i hasło istniejącej biblioteki, handler weryfikuje dane (constant-time password_verify), tworzy konto przypisane do tej biblioteki. UI aktywuje radio "dołączam do istniejącej" i obsługuje nowe komunikaty błędów.

## User-Visible Outcome

Nowy użytkownik może zarejestrować się podając nazwę i hasło istniejącej biblioteki, zostając do niej przypisany.

## Scope (from roadmap)

1. RegisterUserHandler — tryb "join": walidacja istnienia biblioteki + weryfikacja hasła
2. UI formularza — przełączenie radio na "dołączam do istniejącej" (aktywne, te same pola)
3. Walidacja — biblioteka nie istnieje → błąd, złe hasło → błąd, brak listy bibliotek
4. Testy Unit — weryfikacja hasła biblioteki, obsługa błędów
5. Testy Integration — API rejestracji z trybem "join" (happy path + error paths)

## Success Criteria

- SC-02: Nowy użytkownik może zarejestrować się dołączając do istniejącej biblioteki (nazwa + hasło)

## Definition of Done

- `POST /api/auth/register` z `libraryMode: "join"` przypisuje użytkownika do istniejącej biblioteki
- Złe hasło → 422
- Nieistniejąca biblioteka → 422
- Brak endpointu listującego biblioteki

## Dependencies

- M1 (encja Library + rejestracja "create" mode) — done

## Unlocks

- M4 (hardening + E2E — partially, together with M3)

## Progress

| Phase | Status | Notes |
|-------|--------|-------|
| 1 — Domain: New exceptions | ✅ done | LibraryNotFoundException, InvalidLibraryPasswordException |
| 2 — Application: Handler + ExceptionListener | ✅ done | join mode, exception mapping, test regression fixed |
| 3 — UI: Enable join radio + error handling | ✅ done | join radio enabled, library-not-found + invalid-library-password errors handled |
| 4 — Tests: Unit + Integration | ✅ done | 3 unit tests (join happy path + not found + wrong password), 3 integration tests (require DB) |

