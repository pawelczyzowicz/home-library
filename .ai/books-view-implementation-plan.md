## Plan implementacji widoku Lista książek (Dashboard)

## 1. Przegląd
- **Cel**: Przegląd i filtrowanie kolekcji książek po zalogowaniu. Widok jest dashboardem po stronie klienta opartym o SSR (Twig) z lekkimi ulepszeniami JS (Stimulus) i pełnym odświeżaniem strony między zmianami filtrów.
- **Zakres**: Lista/tabela książek, filtrowanie po tytule/autorze (`q`), regale (`shelfId`), gatunkach (`genreIds`), sortowanie (`title|author|createdAt` + `order`), paginacja (`limit`, `offset`), licznik wyników „Wyświetlono X z Y”, akcja „Usuń”.
- **Źródła**: Zob. @prd.md (US‑009..US‑013, US‑019, US‑023) i @tech-stack.md.

## 2. Routing widoku
- **Ścieżka**: `/books` (GET)
- **Dostęp**: wymaga zalogowania (401 → redirect do logowania zgodnie z polityką aplikacji).
- **Parametry zapytania** (whitelist): `q`, `shelfId`, `genreIds`, `limit`, `offset`, `sort`, `order`.
- **SSR**: Kontroler serwera pobiera referencje (`/api/shelves`, `/api/genres`) oraz listę książek (`/api/books`) z przekazaniem danych do Twig. Wszystkie filtry utrzymywane są w URL (GET).

## 3. Struktura komponentów
- `templates/books/index.html.twig` (strona)
  - `components/_banner_alert.html.twig` (opcjonalny alert błędów/komunikatów)
  - `books/_filter_form.html.twig` (formularz GET z polami filtrów)
  - `books/_results_bar.html.twig` (tekst „Wyświetlono X z Y”, licznik i skrót aktywnych filtrów)
  - `books/_empty_state.html.twig` (stan pustej listy)
  - `books/_table.html.twig` (lista/tabela wyników)
  - `components/_pagination.html.twig` (paginacja limit/offset)
- Stimulus (assets/controllers):
  - `books_autosubmit_controller.js` – debounced autosubmit pola `q` i zmian filtrów (pełny reload)
  - `books_clear_controller.js` – czyszczenie wszystkich filtrów i submit
  - `confirm_delete_controller.js` (shared) – potwierdzenie „Usuń” i submit formularza

## 4. Szczegóły komponentów
### BooksIndexPage (Twig)
- **Opis**: Główny szablon strony `/books`. Renderuje formularz filtrów, pasek wyników, tabelę lub pusty stan, paginację oraz baner alertów.
- **Elementy**: wrapper, nagłówek strony, sekcja filtrów, sekcja wyników, paginacja.
- **Zdarzenia**: brak własnych; reaguje na submity formularza (GET) i wyniki API.
- **Walidacja**: na poziomie UI – whitelist opcji sort/order/limit; pozostałe waliduje backend.
- **Typy (wejście)**: `ListBooksViewModel` (patrz sekcja 5).
- **Propsy**: dane do renderu z kontrolera (VM): `books`, `meta`, `filters`, `shelves`, `genres`, `errors`.

### BannerAlert
- **Opis**: Prezentuje błędy 400 (RFC 7807) lub informacyjne komunikaty (np. „Filtry wyczyszczone”).
- **Elementy**: div.alert (role="alert").
- **Zdarzenia**: zamknięcie (opcjonalnie), brak JS wymagane.
- **Walidacja**: renderuje listę błędów z problem details (pole `errors[]` lub `detail`).
- **Typy**: `ProblemDetails`.
- **Propsy**: `problem?: ProblemDetails`, `message?: string`.

### FilterFormBooks
- **Opis**: Formularz GET utrzymujący stan w URL. Zapewnia wyszukiwanie (`q`), filtr po regale (`shelfId`), filtry gatunków (`genreIds[]`), sortowanie (`sort`, `order`), limit (rozmiar strony) i offset (hidden).
- **Elementy**: `<form method="GET" action="/books">`, pola: input text `q`, select `shelfId`, multi-select/checkbox `genreIds[]`, select `sort`, select `order`, select `limit`, hidden `offset`; przyciski „Szukaj” i „Wyczyść”.
- **Zdarzenia**:
  - `input` na `q` → debounced autosubmit (Stimulus `books_autosubmit`)
  - `change` na `shelfId`, `genreIds[]`, `sort`, `order`, `limit` → submit i reset `offset=0`
  - klik „Wyczyść wszystkie filtry” → reset formularza, submit (Stimulus `books_clear`)
- **Walidacja (UI)**:
  - `sort` ∈ {`title`,`author`,`createdAt`}; `order` ∈ {`asc`,`desc`}
  - `limit` ∈ {10,20,50}; `offset` ≥ 0 (ustawiane z URL)
  - `q` długość ≤ 255, trim przy autosubmit; `genreIds[]` to liczby całkowite; `shelfId` wygląda jak UUID
- **Typy**: `ListBooksQuery`.
- **Propsy**: `filters: ListBooksQuery`, `shelves: Shelf[]`, `genres: Genre[]`.

### ResultsBar
- **Opis**: Prezentuje „Wyświetlono X z Y”, gdzie `X = min(meta.total, meta.offset + data.length)`. Pokazuje aktywne filtry skrótem.
- **Elementy**: tekst, badge z liczbą wyników.
- **Zdarzenia**: brak.
- **Walidacja**: jeśli `meta.total=0`, nie pokazuje „Wyświetlono...”, a `EmptyState`.
- **Typy**: `ListBooksMeta` + długość danych.
- **Propsy**: `meta`, `shownCount`, `filters`.

### EmptyState
- **Opis**: Gdy brak wyników lub biblioteka pusta po zalogowaniu.
- **Elementy**: komunikat „Twoja biblioteka jest pusta. Dodaj pierwszą książkę!” oraz CTA (jeśli istnieje ścieżka dodawania).
- **Zdarzenia**: klik CTA (opcjonalnie).
- **Walidacja**: render gdy `meta.total===0`.
- **Typy**: brak dedykowanych.
- **Propsy**: `ctaHref?`.

### BooksTable
- **Opis**: Tabela z kolumnami: Tytuł, Autor, Gatunki, Regał, (opcjonalnie) ISBN, Liczba stron, Akcje.
- **Elementy**: `<table>` z `<thead>` i `<tbody>`. Kolumny ISBN/Strony renderowane warunkowo, jeśli którakolwiek książka posiada daną wartość.
- **Zdarzenia**: klik „Usuń” w wierszu → confirm → submit formularza (lub wywołanie endpointu) → reload.
- **Walidacja**: a11y nagłówki kolumn, responsywność (na mobile – lista kart z tymi samymi danymi).
- **Typy**: `Book`.
- **Propsy**: `books: Book[]`, `showIsbn: boolean`, `showPages: boolean`.

### PaginationControls
- **Opis**: Nawigacja po stronach oparta o `limit`/`offset` i metadane `total`.
- **Elementy**: przyciski „Poprzednia”, „Następna”, numery stron (opcjonalne), select `limit`.
- **Zdarzenia**: zmiana strony ustawia `offset = pageIndex * limit` i submit; zmiana `limit` resetuje `offset=0`.
- **Walidacja**: `offset` nie może wyjść poza zakres; przy braku poprzedniej/następnej – disable.
- **Typy**: `ListBooksMeta`.
- **Propsy**: `meta`, `filters` (do budowy URL-i).

## 5. Typy
Poniżej typy (w stylu TS) na potrzeby dokumentacji i JSDoc w Stimulus. Implementacja frontu może pozostać w JS.

```ts
type UUID = string;

export type Genre = {
  id: number;
  name: string;
};

export type Shelf = {
  id: UUID;
  name: string;
  isSystem?: boolean; // np. „Do zakupu”
};

export type Book = {
  id: UUID;
  title: string;
  author: string;
  genres: Genre[];
  shelf: Shelf;
  isbn?: string | null;
  pages?: number | null;
  createdAt: string; // ISO
};

export type ListBooksQuery = {
  q?: string;
  shelfId?: UUID;
  genreIds?: number[]; // OR semantics
  sort?: 'title' | 'author' | 'createdAt';
  order?: 'asc' | 'desc';
  limit?: number; // 10|20|50
  offset?: number; // >=0
};

export type ListBooksMeta = {
  total: number;
  limit: number;
  offset: number;
};

export type ListBooksResponse = {
  data: Book[];
  meta: ListBooksMeta;
};

export type ProblemDetails = {
  type?: string;
  title?: string;
  status?: number;
  detail?: string;
  instance?: string;
  errors?: Array<{ field?: string; message: string }>; // rozszerzenie
};

export type ListBooksViewModel = {
  filters: ListBooksQuery;
  shelves: Shelf[];
  genres: Genre[];
  books: Book[];
  meta: ListBooksMeta;
  problem?: ProblemDetails;
};
```

## 6. Zarządzanie stanem
- **Źródło prawdy**: URL (parametry GET) + dane z API przekazane do Twig.
- **JS (Stimulus)**: lokalny stan debouncera (timer) w `books_autosubmit_controller.js` i prosty reset w `books_clear_controller.js`.
- **Brak globalnego store**: stan nie jest utrzymywany poza URL/SSR.

## 7. Integracja API
- **Endpoint**: `GET /api/books`
  - Query: `q`, `shelfId`, `genreIds` (comma-separated), `limit`, `offset`, `sort`∈{title,author,createdAt}, `order`∈{asc,desc}
  - Przykład: `GET /api/books?q=Sapkowski&genreIds=1,5&shelfId=3f1c...&limit=20&offset=0&sort=createdAt&order=desc`
  - Odpowiedź 200: `{ data: Book[], meta: { total, limit, offset } }`
  - Błędy: 400 (Problem Details – walidacja parametrów), 401 (unauthorized)

- **Endpointy referencyjne**: `GET /api/genres`, `GET /api/shelves`
  - Użycie: do zasilenia dropdownów/checkboxów w formularzu filtrów.

- **Uwagi implementacyjne**:
  - `genreIds` w UI przechowywane jako tablica → serializacja do CSV w URL.
  - Whitelist na froncie dla `sort`, `order`, `limit`; pozostałe wartości bezpiecznie delegowane do backendu (zachowując błędy 400 → BannerAlert).
  - „Wyświetlono X z Y”: `X = min(meta.total, meta.offset + data.length)`; `data.length` może być < `limit` na ostatniej stronie.

## 8. Interakcje użytkownika
- **Wpisywanie w „Szukaj” (q)**: po krótkim opóźnieniu (np. 300–500 ms) formularz submittuje się automatycznie; strona przeładowuje się z nowymi wynikami.
- **Zmiana regału (shelfId)**: natychmiastowy submit, reset `offset=0`.
- **Zaznaczanie gatunków (genreIds[])**: natychmiastowy submit, reset `offset=0` (OR semantics).
- **Zmiana sort/order**: natychmiastowy submit, reset `offset=0`.
- **Zmiana limitu**: natychmiastowy submit, reset `offset=0`.
- **Paginacja**: klik następna/poprzednia strona aktualizuje `offset`; pełny reload.
- **Wyczyść wszystkie filtry**: reset pól, `offset=0`, submit; BannerAlert lub snackbar informuje o czyszczeniu (opcjonalnie).
- **Usuń (akcja w wierszu)**: potwierdzenie → submit formularza (metoda POST z `_method=DELETE` lub call API) → po sukcesie reload i BannerAlert „Książka została usunięta”.

## 9. Warunki i walidacja
- **Front (prewencja/UX)**:
  - `sort` i `order` ograniczone do dozwolonych wartości (selecty).
  - `limit` tylko 10/20/50 (select); `offset` ukryte – przepisywane z URL.
  - `q` max 255 znaków, trim, placeholder „Szukaj po tytule lub autorze”.
  - `genreIds[]` – liczby całkowite; `shelfId` w formacie UUID (best‑effort).
- **API (egzekucja)**:
  - 400 przy nieprawidłowych wartościach (wyświetlane w BannerAlert).
  - 401 → redirect do logowania przez middleware/aplikację.
  - 404 przy odwołaniu do nieistniejącego `shelfId`/`genreId` (wejście z nieaktualnym URL) → render strony z BannerAlert 404 lub dedykowany ekran błędu.
- **A11y/UX**:
  - Formularz z labelami `for`, odpowiednie role ARIA dla alertów.
  - Tabela z poprawnymi nagłówkami `<th scope="col">` i czytelnym focus state.

## 10. Obsługa błędów
- **400 (Problem Details)**: render listy błędów nad formularzem; zachowaj wartości pól użytkownika.
- **401**: przekierowanie do logowania; po zalogowaniu powrót do `/books`.
- **404**: jeśli parametry odwołują się do nieistniejących zasobów, pokaż 404 (lub BannerAlert z jasnym komunikatem) i link „Wyczyść filtry”.
- **Pusta lista**: render `EmptyState` zamiast tabeli; brak paginacji.
- **Błędy sieci**: ogólny komunikat „Nie udało się pobrać danych. Spróbuj ponownie.” i link do odświeżenia.
- **Usuwanie**: przy błędzie API wyświetl BannerAlert z komunikatem i nie usuwaj wiersza.

## 11. Kroki implementacji
1. **Routing SSR**: Dodaj trasę `GET /books` (tylko zalogowani). Zaciągnij parametry GET do struktury `ListBooksQuery` (z domyślnymi wartościami: `sort=createdAt`, `order=desc`, `limit=20`, `offset=0`).
2. **Pobranie danych**: Wywołaj `GET /api/shelves`, `GET /api/genres`, następnie `GET /api/books` zbudowane z `ListBooksQuery`. Obsłuż 400/401/404 zgodnie z sekcją 10.
3. **ViewModel**: Złóż `ListBooksViewModel` (books, meta, filters, shelves, genres, problem?). Wylicz `showIsbn`/`showPages` na podstawie danych.
4. **Twig – szkielet**: Utwórz `templates/books/index.html.twig` z sekcjami: BannerAlert, FilterFormBooks, ResultsBar, EmptyState/BooksTable, PaginationControls.
5. **Partial: FilterFormBooks**: Stwórz `books/_filter_form.html.twig` z polami i selectami (whitelist dla sort/order/limit), hidden `offset`, przyciski „Szukaj” i „Wyczyść”.
6. **Stimulus**: Dodaj `books_autosubmit_controller.js` (debounce 300–500 ms, reset offset) i `books_clear_controller.js` (reset formularza, submit). Zastosuj `data-controller` i `data-action` do pól.
7. **ResultsBar**: Utwórz `books/_results_bar.html.twig` z „Wyświetlono X z Y” i skrótem aktywnych filtrów.
8. **Tabela**: Utwórz `books/_table.html.twig` z kolumnami, warunkowym renderowaniem ISBN/Strony, przyciskiem „Usuń” (form POST z `_method=DELETE` i CSRF) lub linkiem sterowanym `confirm_delete_controller.js`.
9. **EmptyState**: Utwórz `books/_empty_state.html.twig` z komunikatem i (opcjonalnie) CTA „Dodaj książkę”.
10. **Paginacja**: Utwórz `components/_pagination.html.twig` obliczając `currentPage`, `totalPages`, generując URL-e z odpowiednim `offset` i aktualnymi filtrami.
11. **BannerAlert**: Wspólny komponent do renderu błędów z Problem Details; stylizacja spójna z aplikacją.
12. **A11y/Responsywność**: Zapewnij czytelność tabeli na mobile (stacked cards lub poziome przewijanie), poprawne `aria` dla alertów.
13. **Testy E2E (opcjonalnie)**: scenariusze z US (lista, wyszukiwanie, filtry, czyszczenie, paginacja, usuwanie, pusta biblioteka).
14. **Drobne UX**: Po „Wyczyść wszystkie filtry” i „Usuń” pokaż BannerAlert z potwierdzeniem.

---
Powiązania z PRD i US: US‑009 (lista), US‑010 (wyszukiwanie – autosubmit, pełny reload), US‑011 (filtr regału), US‑012 (filtry gatunków – OR), US‑013 (czyszczenie), US‑019 (współdzielenie – SSR), US‑023 (empty state). Zgodność z @tech-stack.md: Twig + Symfony + Stimulus, SSR + pełne przeładowania, bez SPA.


