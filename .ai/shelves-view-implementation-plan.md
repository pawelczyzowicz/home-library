# Plan implementacji widoku Lista regałów (Shelves)

## 1. Przegląd
Widok zarządzania regałami pod ścieżką `/shelves`. Umożliwia:
- przegląd listy regałów w tabeli z kolumnami: nazwa, znacznik `isSystem`, daty utworzenia/aktualizacji;
- utworzenie nowego regału (walidacja: nazwa 1–50, unikalna, case-insensitive);
- usunięcie pustego regału (potwierdzenie); brak możliwości usunięcia regału systemowego „Do zakupu”; jeśli regał nie jest pusty, backend zwraca 409 i wyświetlamy banner z komunikatem z `detail` (bez UI przenoszenia w MVP).

Zgodność z PRD i US: US‑009 (tworzenie), US‑011 (usuwanie; w MVP tylko pusty), US‑012 (wyróżnienie i ochrona „Do zakupu”), US‑027 (ochrona przed usunięciem), US‑030 (duplikaty nazw).

## 2. Routing widoku
- Ścieżka: `/shelves`
- Kontroler/Twig: `GET /shelves` renderuje `templates/shelves/index.html.twig`.
- Link w nawigacji (np. w `base.html.twig`): „Regały”.

## 3. Struktura komponentów
- ShelvesView (Twig strona):
  - BannerAlert (komponent/fragment HTML) – globalne komunikaty (np. 409 przy DELETE, sukcesy)
  - ShelfCreateForm (formularz inline pod nagłówkiem)
  - ShelvesTable (tabela z listą regałów i akcjami)
  - ConfirmModal (prostokątny modal potwierdzenia usunięcia pustego regału)

Hierarchia (wysokopoziomowa):
- ShelvesView
  - BannerAlert
  - ShelfCreateForm
  - ShelvesTable
    - Row (Delete button | disabled dla systemowych)
  - ConfirmModal

## 4. Szczegóły komponentów
### ShelvesView
- Opis: Kontener strony, odpowiedzialny za inicjalizację danych i kompozycję UI.
- Główne elementy: nagłówek H1, kontener na banner, formularz tworzenia, tabela, modal.
- Obsługiwane interakcje: onLoad (pobranie listy), onRefresh (po create/delete), obsługa globalnych komunikatów.
- Obsługiwana walidacja: n/d (deleguje do dzieci); kontroluje stany ładowania i błędów globalnych.
- Typy: `ShelvesListVM`, `BannerMessage`.
- Propsy: n/d (to strona); przekazuje callbacki i dane dzieciom przez Stimulus targets/data-attributes.

### BannerAlert
- Opis: Pasek wiadomości nad treścią.
- Główne elementy: `<div role="alert">` z ikoną/kolorem, tekstem; typy: info/success/error/warning.
- Obsługiwane interakcje: zamknięcie (X), auto-clear po czasie (opcjonalnie).
- Obsługiwana walidacja: n/d.
- Typy: `BannerMessage`.
- Propsy: `type`, `message`, `dismissible`.

### ShelfCreateForm
- Opis: Formularz tworzenia nowego regału (inline).
- Główne elementy: `<form>` z jednym polem tekstowym `name` (maxlength=50), przycisk „Dodaj”. Ukryte pole CSRF z kontrolerem `csrf-protection`.
- Obsługiwane interakcje: submit -> POST `/api/shelves`; live client walidacja (min. 1 znak, trim; max 50); blokada double submit.
- Obsługiwana walidacja (frontend):
  - wymagane: `name.trim().length >= 1`
  - długość: `<= 50`
- Mapa walidacji (backend):
  - 422 -> pokaż komunikat pod polem; utrzymaj wpisaną wartość
  - 409 -> banner error: „Regał o nazwie '[nazwa]' już istnieje. Wybierz inną nazwę.”
- Typy: `CreateShelfRequest`, `ShelfDto`.
- Propsy: callback `onCreated(shelf: ShelfDto)` do odświeżenia listy.

### ShelvesTable
- Opis: Tabela listująca regały.
- Główne elementy: `<table>` z kolumnami: Nazwa, Systemowy (badge/ikona), Utworzono, Zaktualizowano, Akcje.
- Obsługiwane interakcje: klik „Usuń” na nie-systemowych; brak przycisku Delete dla `isSystem === true`.
- Obsługiwana walidacja: n/d.
- Typy: `ShelfRowVM[]`.
- Propsy: `items`, `onDeleteRequest(row: ShelfRowVM)`.

### ConfirmModal
- Opis: Prosty modal potwierdzenia usunięcia pustego regału.
- Główne elementy: tytuł, treść „Czy na pewno chcesz usunąć regał [nazwa]?”, przyciski Anuluj/Usuń.
- Obsługiwane interakcje: potwierdzenie -> wywołanie DELETE; anulowanie -> zamknięcie.
- Obsługiwana walidacja: n/d.
- Typy: `ConfirmState`.
- Propsy: `open`, `title`, `message`, `onConfirm`, `onCancel`.

## 5. Typy
- ShelfDto
  - `id: string`
  - `name: string`
  - `isSystem: boolean`
  - `createdAt: string` (ISO)
  - `updatedAt: string` (ISO)
- ListShelvesResponse
  - `data: ShelfDto[]`
  - `meta: { total: number }`
- CreateShelfRequest
  - `name: string`
- DeleteShelfConflictError (409)
  - `type: string`
  - `title: string`
  - `status: 409`
  - `detail: string`
  - `booksCount: number`
- BannerMessage
  - `type: 'success'|'info'|'warning'|'error'`
  - `text: string`
- ShelfRowVM (dla tabeli)
  - `id: string`
  - `name: string`
  - `isSystem: boolean`
  - `createdAtLabel: string`
  - `updatedAtLabel: string`
  - `canDelete: boolean` (=`!isSystem`)
- ShelvesListVM
  - `items: ShelfRowVM[]`
  - `total: number`
- ConfirmState
  - `open: boolean`
  - `shelfId?: string`
  - `shelfName?: string`

## 6. Zarządzanie stanem
- Technologia: Stimulus controller `shelves_controller.js` zarządzający:
  - `state.loadingList: boolean`
  - `state.creating: boolean`
  - `state.deletingId: string|null`
  - `state.confirm: ConfirmState`
  - `state.banner: BannerMessage|null`
  - `state.vm: ShelvesListVM`
- Źródło prawdy: backend; po udanych operacjach odświeżamy listę (GET).
- Opcjonalnie: lokalna aktualizacja listy (optimistic) – w MVP preferowany pełny refresh po 201/204 dla prostoty i spójności.

## 7. Integracja API
- List shelves
  - GET `/api/shelves` (opcjonalnie `q`, `includeSystem`=true)
  - Odpowiedź 200: `ListShelvesResponse`
- Create shelf
  - POST `/api/shelves`
  - Body: `CreateShelfRequest`
  - 201: `ShelfDto`
  - 422: walidacja (pokaż błędy formularza)
  - 409: duplikat (banner error, treść komunikatu z PRD)
- Delete shelf
  - DELETE `/api/shelves/{id}`
  - 204: sukces -> odśwież listę + banner success
  - 403: system shelf -> banner error „Regał 'Do zakupu' jest regałem systemowym i nie może być usunięty”
  - 404: banner error „Nie znaleziono regału”
  - 409: body `DeleteShelfConflictError` -> banner warning z `detail` (np. „Shelf contains 12 books.”)
- CSRF:
  - Form create korzysta z ukrytego inputu inicjalizującego token (kontroler `csrf-protection` zapewnia cookie+header dla submitu Turbo).
  - Dla `fetch` (DELETE): z formularza/elementu strony pobrać nagłówki poprzez helper `generateCsrfHeaders(formElement)` i dołączyć do żądania.

Nagłówki wspólne:
- `Accept: application/json`
- `Content-Type: application/json` (dla POST)
- CSRF header z `generateCsrfHeaders` (dla POST/DELETE jeśli nie via Turbo submit).

## 8. Interakcje użytkownika
- „Dodaj regał” (submit):
  - Walidacja client-side (1–50); przy błędach -> informacja pod polem.
  - Po 201: czyść pole, banner success „Regał został utworzony”, odśwież listę.
  - Po 409: wyświetl banner error z treścią „Regał o nazwie '[nazwa]' już istnieje. Wybierz inną nazwę.”, pozostaw wartość w polu.
  - Po 422: pokaż błędy walidacji (np. „Pole 'Nazwa regału' jest wymagane”, „maks. 50 znaków”).
- „Usuń” w wierszu (dla `!isSystem`):
  - Otwórz ConfirmModal. Po potwierdzeniu: DELETE.
  - 204: zamknij modal, banner success „Regał został usunięty”, odśwież listę.
  - 409: zamknij modal, banner warning z `detail` (np. „Shelf contains 12 books.”); operacja zablokowana (MVP – bez przenoszenia).
  - 403/404: banner error z odpowiednim komunikatem.
- Regał systemowy „Do zakupu”:
  - Brak przycisku Usuń; wyróżniony ikoną koszyka i kolorem.

## 9. Warunki i walidacja
- Create:
  - `name.trim().length` 1–50 (frontend), 422 (backend)
  - duplikat nazwy (case-insensitive) -> 409 (banner)
- Delete:
  - system shelf -> 403 (banner) i brak przycisku w UI
  - niepusty shelf -> 409 (banner warning z `detail`), brak dalszej akcji
- A11y:
  - `role="alert"` dla BannerAlert
  - przyciski z `aria-label` (np. „Usuń regał [nazwa]”)
  - fokus przenoszony do bannera po błędzie, do modala po otwarciu

## 10. Obsługa błędów
- Sieć/500: banner error „Wystąpił błąd. Spróbuj ponownie.”
- 422: podpole w formularzu + opis
- 409 (create): banner error „Regał o nazwie '[nazwa]' już istnieje. Wybierz inną nazwę.”
- 409 (delete): banner warning z `detail` i `booksCount`
- 403 (delete system): banner error z komunikatem PRD
- 404: banner error „Nie znaleziono regału”
- Logowanie w konsoli dev (opcjonalnie), bez wycieków w UI.

## 11. Kroki implementacji
1) Routing i szablon
- Dodaj route `GET /shelves` w kontrolerze Symfony i utwórz `templates/shelves/index.html.twig` z kontenerami: banner, formularz, tabela, modal. Dołącz `assets/app.js` oraz zainicjalizuj kontroler Stimulus `data-controller="shelves"`.

2) Komponent BannerAlert (Twig fragment lub prosty blok DIV)
- Struktura `role="alert"`, klasy dla typów (success/info/warning/error), przycisk zamknięcia.

3) ShelfCreateForm
- Formularz z `input[name="name"]`, `maxlength=50`, `required`.
- Ukryty input z `data-controller="csrf-protection"` w celu wygenerowania tokenu.
- Obsługa submitu przez Stimulus: walidacja, POST, obsługa 201/409/422, zarządzanie `creating`.

4) ShelvesTable
- Tabela z kolumnami i mapowaniem `ShelfDto` -> `ShelfRowVM` (formatowanie dat na etykiety locale).
- Wiersz: ikona koszyka i styl dla `isSystem`; ukryj/wyłącz „Usuń”.

5) ConfirmModal
- Prosty modal (HTML + klasy) sterowany przez Stimulus: open/close, onConfirm -> DELETE.

6) Stimulus controller: `assets/controllers/shelves_controller.js`
- Targets: banner, form, nameInput, tableBody, modal, modalMessage.
- Actions: `connect` -> `loadShelves()`. Metody: `loadShelves`, `createShelf`, `openDeleteConfirm`, `confirmDelete`, `showBanner`, `clearBanner`.
- Integracja CSRF: użyj `generateCsrfHeaders(formElement)` z `csrf_protection_controller.js` dla POST/DELETE fetch.

7) Style/CSS
- Dodaj badge/ikonę koszyka dla systemowego regału; style bannera i modala zgodne z istniejącym CSS.

8) Integracja z resztą UI
- Link „Regały” w nawigacji (np. w `base.html.twig`).

9) Hardening
- Debounce blokady wielokrotnego submitu; disabled przycisków w stanie oczekiwania; obsługa retry przy błędach sieci.

---
Uwaga techniczna: Backend zwraca JSON; stosuj `Accept: application/json` i obsługę statusów wg specyfikacji endpointów. CSRF zgodnie z istniejącym `csrf_protection_controller.js` – formularz inicjuje token, a żądania JS dołączają nagłówki zwrócone przez `generateCsrfHeaders`. 
