---
project: "Home Library"
context_type: brownfield
created: 2025-01-27
updated: 2025-01-27
checkpoint:
current_phase: 8
phases_completed: [1, 2, 3, 4, 5, 6]
gray_areas_resolved:
- topic: "zabezpieczenie dołączania do biblioteki"
decision: "hasło biblioteki — ustalane przy tworzeniu, wymagane przy dołączaniu"
- topic: "zmiana biblioteki przez użytkownika"
decision: "niemożliwa — wymaga nowego konta"
- topic: "migracja danych"
decision: "brak — czysta baza"
- topic: "widoczność nazw bibliotek"
decision: "brak listy — użytkownik wpisuje nazwę samodzielnie"
frs_drafted: 4
quality_check_status: accepted
---

# Shape Notes — Home Library (multi-library authorization)

## Current System

- System do zarządzania domową biblioteką książek
- Stack: PHP + JavaScript + Composer
- Istniejący system logowania (autentykacja)
- Jedna wspólna biblioteka dla wszystkich zalogowanych użytkowników
- CRUD książek

## Problem Statement

Wszyscy zalogowani użytkownicy widzą tę samą bibliotekę. Potrzeba izolacji — każdy zespół/rodzina może mieć własną bibliotekę z własnymi książkami.

## Constraints & Preserved Behavior

- Mechanizm logowania — bez zmian
- CRUD książek — funkcjonalność bez zmian (tylko scope ograniczony do biblioteki)
- Baza wyczyszczona — brak potrzeby migracji danych
- Stack technologiczny — bez zmian

## Scope of Change

- [new] Encja "Library" (id, nazwa unikalna, hasło zahashowane)
- [new] Relacja user.library_id → library.id (N:1)
- [modified] Formularz rejestracji — radio "tworzę nową" / "dołączam do istniejącej" + pola: nazwa biblioteki, hasło biblioteki + walidacja
- [modified] Wszystkie zapytania danych — WHERE library_id = current_user.library_id
- [modified] CRUD książek — zapis z library_id
- [preserved] Logowanie — bez zmian
- [preserved] Funkcjonalność CRUD — bez zmian (scope ograniczony do biblioteki)

## User Stories

### US-01: Rejestracja z utworzeniem nowej biblioteki

- **Given** niezalogowany użytkownik na stronie rejestracji
- **When** wybierze "tworzę nową bibliotekę", wpisze nazwę i hasło biblioteki
- **Then** zostaje utworzone konto użytkownika + nowa biblioteka, user jest do niej przypisany

#### Walidacja
- Nazwa biblioteki musi być unikalna — jeśli zajęta, błąd
- Hasło biblioteki niepuste

### US-02: Rejestracja z dołączeniem do istniejącej biblioteki

- **Given** niezalogowany użytkownik na stronie rejestracji
- **When** wybierze "dołączam do istniejącej", wpisze nazwę i hasło biblioteki
- **Then** zostaje utworzone konto użytkownika przypisane do wskazanej biblioteki

#### Walidacja
- Biblioteka o podanej nazwie musi istnieć — jeśli nie, rejestracja odrzucona
- Hasło musi się zgadzać — jeśli nie, rejestracja odrzucona

### US-03: Izolacja danych biblioteki

- **Given** zalogowany użytkownik przypisany do biblioteki X
- **When** przegląda książki
- **Then** widzi tylko książki należące do biblioteki X

### US-04: Dodawanie książki do biblioteki

- **Given** zalogowany użytkownik przypisany do biblioteki X
- **When** dodaje nową książkę
- **Then** książka jest zapisana z przypisaniem do biblioteki X

## Access Control

- Brak ról — wszyscy użytkownicy w bibliotece mają pełne uprawnienia (CRUD)
- Użytkownik nie może zmienić biblioteki — wymaga nowego konta
- Użytkownik należy do dokładnie jednej biblioteki

## Business Logic Changes

Reguła domenowa: każdy rekord danych (książka) jest własnością biblioteki, nie użytkownika. Użytkownik dziedziczy dostęp do danych przez przynależność do biblioteki.

## Timeline

- ~1 tydzień pracy po godzinach

## Quality cross-check

Brak luk — wszystkie decyzje podjęte, flow jednoznaczny.
