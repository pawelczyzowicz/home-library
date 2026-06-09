# Change: M1 — Library entity + rejestracja z nową biblioteką

---
change-id: M1
roadmap-ref: M1
status: planned
created: 2026-06-09
---

## Summary

Wprowadzenie encji Library (id, name, hashed password, created_at) oraz modyfikacja rejestracji użytkownika tak, aby przy tworzeniu konta zakładał nową bibliotekę. Po rejestracji użytkownik jest przypisany do tej biblioteki (User.library_id NOT NULL).

## User-Visible Outcome

Nowy użytkownik może zarejestrować się tworząc nową bibliotekę (nazwa + hasło). Po rejestracji jest przypisany do tej biblioteki.

## Scope (from roadmap)

1. Encja Library — `Domain/Library/Library.php` z polami: id (UUID), name (unique), password (hashed), created_at
2. Migracja Doctrine — tabela `libraries` + dodanie kolumny `library_id` (FK) do tabeli `users`
3. Modyfikacja encji User — relacja ManyToOne do Library, `library_id` NOT NULL
4. RegisterUserCommand / Handler — rozszerzenie o pola: `libraryName`, `libraryPassword`, `libraryMode` (create/join)
5. Formularz rejestracji (UI) — radio "tworzę nową bibliotekę" / "dołączam do istniejącej", pola nazwa + hasło biblioteki
6. RegisterAction (API) — przyjmuje nowe pola, tryb "create" tworzy Library + User
7. Walidacja — unikalna nazwa biblioteki, hasło niepuste
8. Testy Unit — walidacja komendy, tworzenie Library, hashowanie hasła
9. Testy Integration — API rejestracji z trybem "create"

## Success Criteria

- SC-01: Nowy użytkownik może zarejestrować się tworząc nową bibliotekę (nazwa + hasło)

## Definition of Done

- `POST /api/auth/register` z `libraryMode: "create"` tworzy użytkownika + bibliotekę
- User.library_id jest ustawiony
- Duplikat nazwy → 422 z komunikatem
- Istniejące testy rejestracji przechodzą (backward compat lub update)

## Dependencies

- Brak (zero dependencies — first milestone)

## Unlocks

- M2 (rejestracja z dołączeniem do istniejącej biblioteki)
- M3 (izolacja danych — książki i półki per biblioteka)

## Progress

<!-- Phases and commits will be tracked here after /10x-plan and /10x-implement -->

