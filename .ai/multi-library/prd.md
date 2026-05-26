# PRD — Home Library: Multi-Library Authorization

## Vision

Rozbudowa systemu Home Library o izolację danych per biblioteka. Każdy zespół/rodzina może posiadać własną bibliotekę z własnymi książkami, zabezpieczoną hasłem. Użytkownicy wybierają lub tworzą bibliotekę podczas rejestracji.

## Persona

**Użytkownik domowy** — osoba zarządzająca kolekcją książek w ramach rodziny lub grupy znajomych. Chce mieć prywatną przestrzeń na swoje książki, oddzieloną od innych użytkowników systemu. Nie potrzebuje złożonych ról ani uprawnień.

## Success Criteria

- SC-01: Nowy użytkownik może zarejestrować się tworząc nową bibliotekę (nazwa + hasło)
- SC-02: Nowy użytkownik może zarejestrować się dołączając do istniejącej biblioteki (nazwa + hasło)
- SC-03: Zalogowany użytkownik widzi wyłącznie książki swojej biblioteki
- SC-04: Wszystkie operacje CRUD dotyczą wyłącznie książek w bibliotece użytkownika
- SC-05: Nie istnieje możliwość zmiany biblioteki bez utworzenia nowego konta

## User Stories

### US-01: Rejestracja z utworzeniem nowej biblioteki

- **Given** niezalogowany użytkownik na stronie rejestracji
- **When** wybierze opcję "tworzę nową bibliotekę", wpisze unikalną nazwę i hasło biblioteki
- **Then** zostaje utworzone konto użytkownika + nowa biblioteka, user jest do niej przypisany

**Acceptance criteria:**
- Nazwa biblioteki musi być unikalna w systemie
- Hasło biblioteki jest wymagane (niepuste)
- Hasło jest przechowywane w formie zahashowanej
- W przypadku duplikatu nazwy — komunikat błędu, rejestracja odrzucona

### US-02: Rejestracja z dołączeniem do istniejącej biblioteki

- **Given** niezalogowany użytkownik na stronie rejestracji
- **When** wybierze opcję "dołączam do istniejącej", wpisze nazwę i hasło biblioteki
- **Then** zostaje utworzone konto użytkownika przypisane do wskazanej biblioteki

**Acceptance criteria:**
- Biblioteka o podanej nazwie musi istnieć — jeśli nie, rejestracja odrzucona
- Hasło musi się zgadzać z hasłem biblioteki — jeśli nie, rejestracja odrzucona
- Brak listy istniejących bibliotek — użytkownik musi znać nazwę

### US-03: Izolacja danych biblioteki

- **Given** zalogowany użytkownik przypisany do biblioteki X
- **When** przegląda książki, półki lub inne zasoby
- **Then** widzi wyłącznie dane należące do biblioteki X

**Acceptance criteria:**
- Wszystkie zapytania o dane filtrowane po library_id aktualnego użytkownika
- Brak możliwości dostępu do danych innej biblioteki przez manipulację URL/API

### US-04: Dodawanie książki do biblioteki

- **Given** zalogowany użytkownik przypisany do biblioteki X
- **When** dodaje nową książkę
- **Then** książka jest zapisana z przypisaniem do biblioteki X

**Acceptance criteria:**
- library_id ustawiany automatycznie na podstawie sesji użytkownika
- Użytkownik nie może wybrać innej biblioteki docelowej

## Functional Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-001 | System umożliwia utworzenie nowej biblioteki (nazwa unikalna + hasło) podczas rejestracji | must |
| FR-002 | System umożliwia dołączenie do istniejącej biblioteki (nazwa + hasło) podczas rejestracji | must |
| FR-003 | Formularz rejestracji zawiera radio "tworzę nową" / "dołączam do istniejącej" + pola: nazwa, hasło biblioteki | must |
| FR-004 | Wszystkie zapytania o dane (książki, półki) filtrowane po library_id użytkownika | must |
| FR-005 | Nowe rekordy (książki) automatycznie przypisywane do library_id użytkownika | must |
| FR-006 | Hasło biblioteki przechowywane w formie zahashowanej | must |
| FR-007 | Walidacja unikalności nazwy biblioteki przy tworzeniu | must |
| FR-008 | Walidacja istnienia biblioteki i poprawności hasła przy dołączaniu | must |

## Non-Functional Requirements

| ID | Requirement |
|----|-------------|
| NFR-001 | Hasło biblioteki hashowane tym samym algorytmem co hasła użytkowników (bcrypt/argon2) |
| NFR-002 | Izolacja danych wymuszana na poziomie zapytań — brak możliwości cross-library access |
| NFR-003 | Zmiana nie wpływa na wydajność dla istniejących operacji (dodanie indeksu na library_id) |

## Business Logic

**Reguła domenowa**: każdy rekord danych (książka, półka) jest własnością biblioteki, nie użytkownika. Użytkownik dziedziczy dostęp do danych przez przynależność do biblioteki.

**Reguły walidacji rejestracji:**
- Tryb "nowa biblioteka": nazwa unikalna AND hasło niepuste → sukces
- Tryb "dołączam": biblioteka istnieje AND hasło zgadza się → sukces
- W przeciwnym razie → rejestracja odrzucona z komunikatem błędu

## Data Model

### Nowa encja: Library

| Pole | Typ | Ograniczenia |
|------|-----|--------------|
| id | UUID/int | PK, auto |
| name | varchar(255) | UNIQUE, NOT NULL |
| password | varchar(255) | NOT NULL (hashed) |
| created_at | datetime | NOT NULL |

### Modyfikacja encji: User

| Pole | Typ | Ograniczenia |
|------|-----|--------------|
| library_id | FK → Library.id | NOT NULL |

### Modyfikacja encji: Book (i inne zasoby)

| Pole | Typ | Ograniczenia |
|------|-----|--------------|
| library_id | FK → Library.id | NOT NULL, INDEX |

## Access Control

- Brak ról — wszyscy użytkownicy w bibliotece mają pełne uprawnienia (CRUD)
- Użytkownik należy do dokładnie jednej biblioteki (N:1)
- Zmiana biblioteki niemożliwa — wymaga nowego konta
- Izolacja danych zapewniona przez filtrowanie na poziomie zapytań

## Testing Strategy

- **Unit**: walidacja rejestracji (oba tryby), hashowanie hasła biblioteki, przypisanie library_id
- **Integration**: formularz rejestracji end-to-end, filtrowanie danych per biblioteka, CRUD z izolacją
- **Edge cases**: próba dołączenia do nieistniejącej biblioteki, złe hasło, duplikat nazwy

## Deployment & CI/CD

- Nowa migracja Doctrine dla encji Library + modyfikacji User/Book
- Baza wyczyszczona — brak migracji danych produkcyjnych
- Wdrożenie: migracja DB → deploy kodu

## Non-Goals

- Zmiana biblioteki przez użytkownika (wymaga nowego konta)
- Role i uprawnienia wewnątrz biblioteki
- Lista/wyszukiwarka istniejących bibliotek
- Zaproszenia / kody jednorazowe
- Multi-library per user

## Open Questions

Brak — wszystkie decyzje podjęte podczas shapingu.

