# Roadmap — Home Library: Multi-Library Authorization

---
project: home-library
created: 2026-06-08
source: context/foundation/prd.md
status: accepted
slicing_strategy: vertical-first
---

## Milestones Overview

| ID | Milestone | Type | Depends On | Risk |
|----|-----------|------|------------|------|
| M1 | Library entity + rejestracja z nową biblioteką | vertical | — | low |
| M2 | Rejestracja z dołączeniem do istniejącej biblioteki | vertical | M1 | low |
| M3 | Izolacja danych — książki i półki per biblioteka | vertical | M1 | medium |
| M4 | Hardening — edge cases, walidacja, testy E2E | vertical | M2, M3 | low |

---

## M1 — Library entity + rejestracja z nową biblioteką

**User-visible outcome:** Nowy użytkownik może zarejestrować się tworząc nową bibliotekę (nazwa + hasło). Po rejestracji jest przypisany do tej biblioteki.

**Success Criteria:** SC-01
**Powiązane US:** US-01 | **FR:** FR-001, FR-003, FR-006, FR-007

**Scope:**

1. **Encja Library** — `Domain/Library/Library.php` z polami: id (UUID), name (unique), password (hashed), created_at
2. **Migracja Doctrine** — tabela `libraries` + dodanie kolumny `library_id` (FK) do tabeli `users`
3. **Modyfikacja encji User** — relacja ManyToOne do Library, `library_id` NOT NULL
4. **RegisterUserCommand / Handler** — rozszerzenie o pola: `libraryName`, `libraryPassword`, `libraryMode` (create/join)
5. **Formularz rejestracji (UI)** — radio "tworzę nową bibliotekę" / "dołączam do istniejącej", pola nazwa + hasło biblioteki
6. **RegisterAction (API)** — przyjmuje nowe pola, tryb "create" tworzy Library + User
7. **Walidacja** — unikalna nazwa biblioteki, hasło niepuste
8. **Testy Unit** — walidacja komendy, tworzenie Library, hashowanie hasła
9. **Testy Integration** — API rejestracji z trybem "create"

**Bounded foundation (enabler):** Encja Library + migracja + relacja User→Library. Uzasadnienie: nie istnieje user-visible flow bez tej struktury. Odblokowanie: M2, M3.

**Unknowns:** Brak

**Definition of Done:**
- `POST /api/auth/register` z `libraryMode: "create"` tworzy użytkownika + bibliotekę
- User.library_id jest ustawiony
- Duplikat nazwy → 422 z komunikatem
- Istniejące testy rejestracji przechodzą (backward compat lub update)

---

## M2 — Rejestracja z dołączeniem do istniejącej biblioteki

**User-visible outcome:** Nowy użytkownik może zarejestrować się podając nazwę i hasło istniejącej biblioteki, zostając do niej przypisany.

**Success Criteria:** SC-02
**Powiązane US:** US-02 | **FR:** FR-002, FR-003, FR-008

**Scope:**

1. **RegisterUserHandler** — tryb "join": walidacja istnienia biblioteki + weryfikacja hasła
2. **UI formularza** — przełączenie radio na "dołączam do istniejącej" pokazuje te same pola, ale z inną logiką walidacji
3. **Walidacja** — biblioteka nie istnieje → błąd, złe hasło → błąd, brak listy bibliotek (user wpisuje ręcznie)
4. **Testy Unit** — weryfikacja hasła biblioteki, obsługa błędów
5. **Testy Integration** — API rejestracji z trybem "join" (happy path + error paths)

**Depends on:** M1 (encja Library musi istnieć)

**Risk: LOW** — Timing attacks przy weryfikacji hasła — mitygacja: constant-time comparison via `password_verify` / Symfony PasswordHasher.

**Unknowns:** Brak

**Definition of Done:**
- `POST /api/auth/register` z `libraryMode: "join"` przypisuje użytkownika do istniejącej biblioteki
- Złe hasło → 422
- Nieistniejąca biblioteka → 422
- Brak endpointu listującego biblioteki

---

## M3 — Izolacja danych — książki i półki per biblioteka

**User-visible outcome:** Zalogowany użytkownik widzi wyłącznie książki i półki należące do jego biblioteki. Nowe rekordy automatycznie przypisywane do jego biblioteki.

**Success Criteria:** SC-03, SC-04
**Powiązane US:** US-03, US-04 | **FR:** FR-004, FR-005 | **NFR:** NFR-002, NFR-003

**Scope:**

1. **Migracja Doctrine** — dodanie kolumny `library_id` (FK) do tabel `books` i `shelves` + INDEX
2. **Modyfikacja encji Book** — relacja ManyToOne do Library
3. **Modyfikacja encji Shelf** — relacja ManyToOne do Library
4. **DoctrineBookRepository** — filtrowanie `WHERE library_id = :libraryId` we wszystkich zapytaniach
5. **DoctrineShelfRepository** — filtrowanie `WHERE library_id = :libraryId` we wszystkich zapytaniach
6. **DbalShelfBooksCounter** — filtrowanie per library
7. **Book creation flow** — automatyczne ustawianie `library_id` z sesji użytkownika
8. **Shelf creation flow** — automatyczne ustawianie `library_id` z sesji użytkownika
9. **Shelf system flag** — półki systemowe tworzone per biblioteka (nie globalne)
10. **AI recommendations** — filtrowanie kontekstu (book read repository) per library
11. **Testy Integration** — izolacja: user z lib A nie widzi danych lib B
12. **Testy Unit** — automatyczne przypisanie library_id

**Depends on:** M1 (Library entity + User→Library relacja)

**Risk: MEDIUM** — Wymaga modyfikacji wielu repozytoriów i zapytań. Ryzyko pominięcia filtrowania w jednym z zapytań → wyciek danych. Mitygacja: centralny mechanizm filtrowania (np. Doctrine SQL Filter lub base query method) + audyt wszystkich endpointów + uruchamianie pełnego test suite po każdej zmianie.

**Unknowns:**
- Czy DataFixtures potrzebują aktualizacji o library_id? (Prawdopodobnie tak — do zbadania)
- Czy Genre wymaga library_id? (Wstępnie nie — gatunki są globalne, nie per-library)

**Definition of Done:**
- Zapytania o książki/półki zwracają tylko dane z biblioteki użytkownika
- Nowa książka/półka ma library_id ustawiany automatycznie
- Manipulacja URL/API nie daje dostępu do danych innej biblioteki
- Istniejące testy Unit/Integration przechodzą

---

## M4 — Hardening — edge cases, walidacja, testy E2E

**User-visible outcome:** System jest odporny na edge cases. Pełny flow end-to-end działa poprawnie w przeglądarce.

**Success Criteria:** SC-05 + ogólna stabilność

**Scope:**

1. **E2E test: rejestracja create** — Panther test: formularz → nowa biblioteka → widok książek
2. **E2E test: rejestracja join** — Panther test: formularz → dołączenie → widok współdzielonych książek
3. **E2E test: izolacja** — dwa konta, dwie biblioteki, wzajemna niewidoczność danych
4. **Edge case: zmiana biblioteki** — upewnienie się, że brak endpointu/UI do zmiany (SC-05)
5. **Edge case: usunięcie biblioteki** — decyzja: brak funkcjonalności (non-goal) → walidacja braku takiego endpointu
6. **Security review** — brak cross-library access przez ID manipulation w URL
7. **Aktualizacja DataFixtures** — fixtures tworzą Library + przypisują dane

**Depends on:** M2, M3

**Definition of Done:**
- Wszystkie testy E2E przechodzą
- Brak sposobu na cross-library data access
- DataFixtures działają z nowym modelem danych
- CI pipeline zielony (unit + integration)

---

## Dependencies Graph

```
M1 (Library + rejestracja create)
├── M2 (rejestracja join)
└── M3 (izolacja danych)
    └── M4 (hardening + E2E)
         ↑ also depends on M2
```

## Backlog Handoff

Każdy milestone jest gotowy do realizacji jako osobny change (`context/changes/<change-id>/`). Zalecana kolejność implementacji:

1. **M1** → implementacja od razu (zero dependencies)
2. **M2** → po merge M1
3. **M3** → po merge M1 (może być równolegle z M2 jeśli branch strategy pozwala)
4. **M4** → po merge M2 + M3

## Assumptions

- Baza danych jest czysta (brak migracji danych produkcyjnych) — potwierdzone w PRD
- Genre NIE wymaga library_id — gatunki są współdzielone globalnie
- Shelf WYMAGA library_id — potwierdzone przez użytkownika
- Book WYMAGA library_id — wynika z PRD
- Hasło biblioteki hashowane Argon2 (ten sam mechanizm co hasła userów via Symfony PasswordHasher)

