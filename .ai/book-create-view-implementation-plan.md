## Plan implementacji widoku Dodaj książkę

## 1. Przegląd
- Cel: umożliwić utworzenie nowej książki i przypisanie jej do regału z walidacjami po stronie klienta i serwera.
- Redirekcja po sukcesie (PRG): po 201 z API przekierowanie do `/books?notice=book-created` i prezentacja komunikatu sukcesu.
- Zgodność z istniejącymi widokami: stylistyka formularzy (`form-field`), banerów (`banner`), wzorce Stimulus (jak w `shelves`).

## 2. Routing widoku
- Ścieżka: `/books/new` (strona wymagająca zalogowania).
- Serwer (UI/Web): kontroler np. `CreateBookController` (IsGranted('ROLE_USER')) renderujący `templates/books/new.html.twig` bez danych listowych (ładowane przez JS przez `/api/shelves`, `/api/genres`).
- Po utworzeniu: redirect klienta do `/books?notice=book-created`.

## 3. Struktura komponentów
- BooksCreateView (widok-strona; Stimulus: `books-create`)
  - BannerAlert (kontener pod banery wynikowe z JS; jak w `shelves`)
  - FieldErrorSummary (lista błędów pól z nawigacją do inputów)
  - BookForm (Create)
    - ShelfSelect (select z `/api/shelves`)
    - GenreCheckboxGroup (checkboxy z `/api/genres` z limitem 1–3)
    - Pola tekstowe: `title`, `author`, `isbn`, `pageCount`
    - Hidden CSRF input (token id: `submit`)

## 4. Szczegóły komponentów
### BooksCreateView (Stimulus: `books-create`)
- Opis: Orkiestracja widoku. Ładuje listy regałów i gatunków, zarządza stanem formularza, walidacją i komunikatami.
- Główne elementy:
  - `<main class="books-create-view" data-controller="books-create">`
  - `<section data-books-create-target="banner"></section>` (baner)
  - `<section data-books-create-target="fieldSummary">` + `<ul data-books-create-target="fieldSummaryList">`
  - `<form data-books-create-target="form">` i pola/targets wewnątrz
- Zdarzenia:
  - connect: równoległe GET `/api/shelves` i `/api/genres`, render opcji
  - submit: walidacja klienta → POST `/api/books` → redirect na sukces
  - input/blur/change: walidacje, czyszczenie błędów pól
- Walidacja: delegowana do BookForm; agregacja błędów w FieldErrorSummary.
- Typy: `ShelfOption`, `GenreOption`, `FieldErrorsMap`, `BannerVm`, `BookFormVm`.
- Propsy: brak; komponent stronowy sam ładuje dane i steruje stanem.

### BookForm (Create)
- Opis: Formularz tworzenia książki zgodny z PRD/US-004/US-024.
- Główne elementy HTML:
  - Tytuł: `<input type="text" name="title" id="book-title" required maxlength="255">`
  - Autor: `<input type="text" name="author" id="book-author" required maxlength="255">`
  - Regał: `<select name="shelfId" id="book-shelf" required>` (opcja placeholder „Wybierz regał”)
  - Gatunki: `<div>` z listą `<label class="checkbox"><input type="checkbox" name="genreIds[]" value="ID">Nazwa</label>` (limit 1–3)
  - ISBN (opc.): `<input type="text" name="isbn" id="book-isbn" inputmode="numeric" maxlength="13" pattern="^[0-9]{10}([0-9]{3})?$">`
  - Liczba stron (opc.): `<input type="number" name="pageCount" id="book-pageCount" min="1" max="50000" step="1">`
  - Hidden CSRF: `<input type="hidden" data-controller="csrf-protection" value="submit">`
  - Submit: `<button data-books-create-target="submit">Zapisz</button>`
- Komponenty dzieci/targets (przykładowe):
  - `titleInput`, `authorInput`, `isbnInput`, `pageCountInput`, `shelfSelect`, `genresContainer`, `submitButton`, `fieldSummary`, `fieldSummaryList`, `banner`.
- Obsługiwane interakcje:
  - `input`/`blur` dla tytułu i autora (wymagane, długość 1–255)
  - `change` dla `shelfId` (wymagane)
  - `change` dla `genreIds[]` (limit 1–3; komunikat przy przekroczeniu; blokowanie 4. wyboru)
  - `input` dla `isbn` (puste lub 10/13 cyfr)
  - `input` dla `pageCount` (puste lub [1, 50000], całkowite)
  - `submit` (wysyłka JSON z walidacją klienta i CSRF)
- Walidacja (szczegółowa):
  - title: required; 1–255 znaków
  - author: required; 1–255 znaków
  - shelfId: required; musi istnieć na liście opcji
  - genreIds: min 1, max 3; ID z listy gatunków
  - isbn: opcjonalne; dokładnie 10 lub 13 cyfr (bez separatorów)
  - pageCount: opcjonalne; całkowita 1–50000
- Typy: `CreateBookRequest`, `FieldErrorsMap`, `BannerVm`, `BookFormVm`.
- Propsy: sterowane przez `books-create` (targets + dataset), bez zewnętrznych propsów.

### FieldErrorSummary
- Opis: Sekcja z listą pierwszych błędów dla każdego pola, nad formularzem.
- Elementy: `<section role="status" tabindex="-1" data-books-create-target="fieldSummary" hidden>` + `<ul data-books-create-target="fieldSummaryList">` z linkami do `#id` pól.
- Zdarzenia: click na link błędu → focus na polu.
- Walidacja: prezentacja na podstawie `FieldErrorsMap`.
- Typy: korzysta z mapy błędów formularza.
- Propsy: dane pochodzą z kontrolera (stan lokalny).

### BannerAlert (klientowy)
- Opis: Prosty kontener do komunikatów wynikowych (success/error/warning), zgodny ze stylem `shelves`.
- Elementy: `<section data-books-create-target="banner"></section>`; renderowany `<div class="banner banner--{type}" role="alert">`.
- Zdarzenia: automatyczne ukrywanie po czasie; przycisk zamknięcia.
- Walidacja: nie dotyczy.
- Typy: `BannerVm`.
- Propsy: brak.

## 5. Typy
- CreateBookRequest (DTO żądania):
  - `title: string`
  - `author: string`
  - `shelfId: string` (UUID)
  - `genreIds: number[]` (1–3 elementy)
  - `isbn?: string` (10 lub 13 cyfr)
  - `pageCount?: number` (1–50000, int)
- BookResponse (DTO odpowiedzi 201 – nieużywany w UI, bo redirect, ale do testów):
  - `id: string`, `title: string`, `author: string`, `shelfId: string`, `genreIds: number[]`, `isbn?: string`, `pageCount?: number`, `source: 'manual'`, `createdAt: string`
- ApiProblemDetails (422/4xx):
  - `title?: string`, `detail?: string`, `violations?: { propertyPath: string, message: string }[]`
- FieldErrorsMap (ViewModel): `{ [field: string]: string[] }`
- BannerVm: `{ type: 'success' | 'error' | 'warning', text: string }`
- ShelfOption: `{ id: string, name: string, isSystem: boolean }`
- GenreOption: `{ id: number, name: string }`
- BookFormVm: `{ shelves: ShelfOption[], genres: GenreOption[], selectedShelfId?: string, selectedGenreIds: number[] }`

## 6. Zarządzanie stanem
- Lokalny stan w Stimulus `books-create`:
  - `loadingShelves: boolean`, `loadingGenres: boolean`
  - `submitting: boolean`
  - `banner: BannerVm | null`
  - `fieldErrors: FieldErrorsMap`
  - `vm: BookFormVm` (listy i zaznaczenia)
- Reużycie utila walidacyjnego: wzorzec jak `assets/auth/form-state.js` (focus na pierwszym błędzie, ARIA, lista błędów). Można:
  - albo użyć istniejącego `createFormState` (import + mapowanie pól),
  - albo zaimplementować minimalnie te same zachowania lokalnie w kontrolerze (preferowane: reużycie w celu spójności).
- Blokada podwójnego submitu: `submitting` blokuje przycisk i dodaje `aria-busy`.

## 7. Integracja API
- Preload danych:
  - GET `/api/shelves` → `data: { id, name, isSystem, ... }[]` → mapuj do `ShelfOption[]`
  - GET `/api/genres` → `data: { id, name }[]` → mapuj do `GenreOption[]`
- Wysyłka formularza:
  - POST `/api/books` z `Accept: application/json`, `Content-Type: application/json`
  - CSRF: ukryty input `data-controller="csrf-protection" value="submit"` + wywołanie `generateCsrfHeaders(form)` i dołączenie do nagłówków fetch (jak w `shelves`)
  - Body: `CreateBookRequest`
- Obsługa odpowiedzi:
  - 201 → `window.location.assign('/books?notice=book-created')`
  - 422 → mapowanie `violations` → `FieldErrorsMap`, render FieldErrorSummary + błędy przy polach
  - 404 → banner error: „Nie znaleziono wybranego regału lub gatunku. Odśwież listy i spróbuj ponownie.” + automatyczne przeładowanie list (ponowny GET)
  - 401/403 → redirect do logowania lub banner z prośbą o ponowne zalogowanie

## 8. Interakcje użytkownika
- Wejście na `/books/new` → widok ładuje listy regałów i gatunków; przy błędzie ładowania pokazuje baner i przycisk ponów.
- Użytkownik wypełnia: tytuł, autor, wybiera regał, zaznacza 1–3 gatunki, opcjonalnie ISBN i liczbę stron.
- Błędy pól są pokazywane inline pod polami oraz agregowane nad formularzem (FieldErrorSummary) – fokus na pierwszym błędzie.
- Próba zaznaczenia >3 gatunków: pokaż komunikat i nie pozwól przekroczyć limitu (odznacz ostatnie kliknięcie lub zablokuj pozostałe checkboxy do max = 3).
- Klik „Zapisz” → walidacja klienta → POST → na sukces redirect do listy z komunikatem sukcesu.

## 9. Warunki i walidacja
- HTML atrybuty:
  - `required` dla `title`, `author`, `shelfId`
  - `maxlength="255"` dla `title`, `author`
  - `maxlength="13"`, `inputmode="numeric"`, `pattern="^[0-9]{10}([0-9]{3})?$"` dla `isbn`
  - `type="number"`, `min="1"`, `max="50000"`, `step="1"` dla `pageCount`
- Walidacja JS (na `input`/`blur`/`change`):
  - `title`, `author`: wymagane, 1–255
  - `shelfId`: wymagane, musi być w opcjach
  - `genreIds`: co najmniej 1, maks 3
  - `isbn`: puste lub 10/13 cyfr
  - `pageCount`: puste lub int w [1, 50000]
- A11y: ustawianie `aria-invalid`, `aria-describedby` dla błędów; FieldErrorSummary ma `role="status"` i focus po aktualizacji; baner ma `role="alert"`.

## 10. Obsługa błędów
- Błędy ładowania list (GET): baner error z możliwością ponowienia (przycisk, ponowny fetch).
- 422 z serwera: mapowanie `violations` → pola. Zachowaj wartości inputów, podświetl błędy (jak w `auth/register`).
- 404 (stare opcje): baner error + auto-refresh list i reset invalid selection.
- Timeout/połączenie: baner „Wystąpił błąd. Spróbuj ponownie.”, odblokowanie przycisku.
- Ochrona przed wielokrotną wysyłką: `submitting` blokuje button, `aria-busy`.

## 11. Kroki implementacji
1. Routing/UI: dodać kontroler strony `/books/new` (IsGranted) i szablon `templates/books/new.html.twig` z sekcjami: nagłówek, `<section data-books-create-target="banner">`, FieldErrorSummary, formularz (pola i hidden CSRF `value="submit"`).
2. Stimulus: utworzyć `assets/controllers/books_create_controller.js`:
   - Targets: `banner, form, submit, fieldSummary, fieldSummaryList, titleInput, authorInput, shelfSelect, genresContainer, isbnInput, pageCountInput`.
   - `connect()`: równoległe fetch `/api/shelves` i `/api/genres`, render opcji.
   - Walidatory per pole; kontrola limitu gatunków 1–3; czyszczenie błędów na input.
   - `submit` handler: walidacja → `generateCsrfHeaders(form)` → POST `/api/books` → switch po statusie (201/422/404/default).
3. Rejestracja kontrolera: w `assets/bootstrap.js` dodać `app.register('books-create', () => import('./controllers/books_create_controller.js'));`.
4. Baner: użyć tego samego mechanizmu renderowania co w `shelves` (klasy `banner`, auto-hide).
5. FieldErrorSummary: dodać sekcję nad formularzem (ukryta dopóki brak błędów); po 422/fail walidacji klienta – wypełnić listę i ustawić focus.
6. HTML atrybuty walidacyjne: ustawić `required`, `maxlength`, `pattern`, `min/max/step` zgodnie z sekcją 9.
7. PRG: po 201 → `window.location.assign('/books?notice=book-created')`.
8. Lista książek: w `BooksController::resolveNotice` dodać case `book-created` → status `success`, message „Książka została dodana.”, by pokazać baner na `/books` (jak dla usunięcia).
9. Testy E2E:
   - Scenariusz sukcesu (wypełnienie poprawne → redirect + baner)
   - 422 (puste pola, niepoprawny ISBN, >3 gatunki)
   - 404 (manipulacja opcjami)
10. Dostępność: sprawdzić focus na FieldErrorSummary i banerze; `aria-*` dla błędów pól; etykiety `<label for=...>`.


