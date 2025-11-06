# Plan implementacji widoku Rekomendacje AI – formularz

## 1. Przegląd
Widok formularza rekomendacji AI zbiera od użytkownika co najmniej jedną pozycję wejściową (tytuł z autorem lub samo nazwisko autora), a następnie wywołuje endpoint `POST /api/ai/recommendations/generate`. Po pomyślnym utworzeniu zdarzenia (201) następuje przekierowanie do widoku wyników rekomendacji: `/ai/recommendations/{eventId}`.

Cel: uruchomienie procesu generowania rekomendacji zgodnie z PRD i US‑014/US‑015/US‑021, spójnie stylistycznie i technicznie z widokami `books`, `shelves`, `books/new` (Twig + Stimulus, reuse `createFormState`, `csrf-protection`, `Banner`, `FieldErrorSummary`).

## 2. Routing widoku
- Ścieżka: `GET /ai/recommendations`
- Kontroler Twig (UI): nowy kontroler np. `AiRecommendationsFormController` zwracający szablon `templates/ai/recommendations_form.html.twig`.
- Autoryzacja: wymaga zalogowania (jak pozostałe widoki aplikacji).
- Nawigacja: link istnieje w `components/_top_nav.html.twig` → „Rekomendacje AI”.

## 3. Struktura komponentów
- AIRecommendationsFormView (layout widoku)
  - BannerAlert (miejsce na komunikaty globalne – 502/504/inne)
  - FieldErrorSummary (zbiorcze błędy walidacji pól)
  - AIForm (formularz)
    - InputsList (min. 3 wiersze tekstowe; możliwość dodania kolejnych)
      - InputRow (pojedyncze pole + ew. przycisk usuń dla >3)
    - Examples (sekcja z przykładami wpisów)
    - CSRFHidden (ukryte pole z kontrolerem CSRF)
    - Submit (przycisk „Generuj rekomendacje” ze stanem `is-submitting` + spinner tekstowy „AI analizuje…”) 

## 4. Szczegóły komponentów
### AIRecommendationsFormView
- Opis: Kontener strony, nagłówek, banner, panel formularza.
- Główne elementy: `<main.ai-recommendations-view>`, nagłówek z tytułem i podtytułem, sekcja banner, panel formularza w karcie.
- Obsługiwane interakcje: brak bezpośrednio (logika w AIForm/Stimulus).
- Walidacja: n/d (renderuje wyniki walidacji z dziecka).
- Typy: `AIFormViewModel` (patrz: Typy), przekazywany w JS.
- Propsy: brak (strona root). 

### BannerAlert
- Opis: Pasek komunikatów globalnych (sukces/błąd/info), styl jak `.banner` (spójny z `books_create_controller`).
- Główne elementy: `<section class="ai-recommendations-view__banner" data-ai-reco-form-target="banner">`.
- Interakcje: przycisk zamknięcia; przy błędzie 502/504 przycisk „Spróbuj ponownie” (retry submit lub tylko zamknięcie, patrz Obsługa błędów).
- Walidacja: brak.
- Typy: `BannerState`.
- Propsy: sterowany z kontrolera Stimulus przez target.

### FieldErrorSummary
- Opis: Sekcja z listą błędów formularza (rola `status`, `aria-live="assertive"`, fokusowana po walidacji), spójna z `.field-error-summary` w `books/new`.
- Główne elementy: `<section class="field-error-summary" data-ai-reco-form-target="fieldSummary|fieldSummaryList">`.
- Interakcje: klik w link w summary przewija i fokusuje pole.
- Walidacja: renderuje błędy z `form-state.js` (klucze `inputs`, `inputs[0]`, …, `model` itp.).
- Typy: używa stanu z `createFormState`.
- Propsy: zarządzany przez kontroler.

### AIForm
- Opis: Główny formularz wysyłający `POST /api/ai/recommendations/generate`.
- Główne elementy HTML:
  - Lista pól wejściowych `inputs[]` (min. 3 inputy tekstowe, `maxlength=255`, placeholder „Wpisz tytuł książki lub autora”).
  - Przycisk „Dodaj kolejne pole” (dodaje nowy `InputRow`).
  - Sekcja Examples z krótką instrukcją i przykładami.
  - Ukryte pole `input[type=hidden][data-controller="csrf-protection"]`.
  - Przycisk Submit.
- Obsługiwane interakcje:
  - `input`/`blur` na polach `inputs[]` (walidacja on‑blur, czyszczenie błędów on‑input).
  - `click` „Dodaj kolejne pole” (dodaje nowy input pod koniec listy, ustawia fokus).
  - `click` „Usuń” przy wierszu (gdy jest >3 pól; usuwa wiersz i czyści ewentualny błąd `inputs[i]`).
  - `submit` – walidacja i wysłanie JSON.
- Walidacja (szczegółowa):
  - Co najmniej 1 wypełnione pole wśród `inputs[]` (trim ≠ pusty).
  - Każda niepusta wartość: długość 1–255 (frontend; backend nie ogranicza, ale ujednolicamy UX jak w PRD).
  - `model` (opcjonalne ustawienie stałe po stronie FE): string ≤ 191 (jeśli używane; domyślnie pusty → nie wysyłamy klucza).
- Typy: `GenerateRecommendationsRequest`, `ProblemJson`, `RecommendationEventResponse`.
- Propsy: brak (samodzielny komponent pod kontrolerem Stimulus `ai-recommendations-form`).

### InputRow
- Opis: Pojedyncze pole w InputsList.
- Elementy: `<label>`, `<input name="inputs[]" type="text" maxlength="255">`, `<p.form-field__error>` oraz przycisk „Usuń” (dla >3 pól).
- Interakcje: `input`/`blur`, `click` na „Usuń”.
- Walidacja: jw. (delegowana z kontrolera – mapowanie błędów `inputs[i]`).
- Typy: używa kluczy indeksowanych `inputs[i]` w `form-state`.
- Propsy: indeks, selektory id dla a11y (`id="ai-input-0"` itd.).

### Examples
- Opis: Stała sekcja z krótką instrukcją + przykłady.
- HTML: nagłówek, paragrafy, lista przykładów: „Np. ‘Wiedźmin Andrzej Sapkowski’ lub ‘Andrzej Sapkowski’”.
- Interakcje: brak.
- Walidacja: brak.
- Typy: n/d.
- Propsy: n/d.

### Submit
- Opis: Przycisk wysyłki; w stanie submitting wyświetla „AI analizuje Twoje preferencje…”, `aria-busy=true`, formularz dostaje klasę `.is-submitting`.
- Elementy: `<button type="submit" class="button button--primary">`.
- Interakcje: `click` (submit), blokada podczas submitting.
- Walidacja: odpala walidację formularza.
- Typy: n/d.
- Propsy: sterowane przez `createFormState` + kontroler.

## 5. Typy
Nowe/wykorzystane typy (JSDoc lub TS pseudo‑typy w opisie):

```ts
type GenerateRecommendationsRequest = {
  inputs: string[];              // min. 1 pozycja niepusta (trim)
  model?: string | null;         // opcjonalnie; np. "openrouter/openai/gpt-4o-mini"
};

type RecommendationProposal = {
  tempId: string;
  title: string;
  author: string;
  genresId: number[];            // 1–3 wartości z katalogu 1–15
  reason: string;                // 1–2 zdania
};

type RecommendationEventResponse = {
  id: number;
  createdAt: string;             // ISO8601 (UTC)
  userId: string | null;
  inputTitles: string[];
  recommended: RecommendationProposal[]; // dokładnie 3 elementy
  acceptedBookIds: string[];     // UUID
};

type ProblemJson = {
  type: string;
  title: string;
  status: number;                // 422/502/504/...
  detail?: string;
  errors?: Record<string, string[]>; // np. { "inputs": [..], "inputs[0]": [..] }
};

type BannerState = {
  type: 'success' | 'error' | 'warning' | 'info';
  text: string;
  action?: { label: string; handler: () => void } | null;
  autoHide?: boolean;            // domyślnie true
};

type AIFormViewModel = {
  inputs: string[];              // aktualne wartości pól inputs[]
  submitting: boolean;
};
```

## 6. Zarządzanie stanem
- Kontroler Stimulus: `ai-recommendations-form`
  - Targets: `banner`, `fieldSummary`, `fieldSummaryList`, `form`, `submit`, `inputsContainer` (kontener listy pól), `exampleSection`.
  - Integruje `createFormState` (jak `books_create_controller`):
    - `setSubmitting(true)` → blokada formularza, `aria-busy` na przycisku, klasa `.is-submitting`.
    - `setFieldErrors` + `focusFirstError()` → integracja z `FieldErrorSummary`.
  - Własne zmienne:
    - `vm = { inputs: string[] }` (co najmniej 3 elementy – puste stringi na start).
    - `touched: boolean[]` dla poszczególnych pól (on‑blur).
    - `submitting: boolean` (lokalnie + w `formState`).
  - Metody:
    - `connect()` → inicjalizacja 3 inputów, render, czyszczenie bannerów.
    - `addInput()` / `removeInput(index)` → modyfikacja DOM i `formState` mapowania błędów.
    - `onInputChange(index)` / `onInputBlur(index)` → walidacja jednostkowa.
    - `submit(event)` → walidacja i wysyłka fetch.
    - `handleSubmitResponse(response)` → 201 → redirect; 401/403 → login; 422 → mapowanie błędów; 502/504 → Banner.
    - `mapProblemErrors(problem)` → zgodnie z `books_create_controller` (obsługa `errors`/`violations`).

## 7. Integracja API
- Endpoint: `POST /api/ai/recommendations/generate`
- Nagłówki: `Accept: application/json`, `Content-Type: application/json` + CSRF (z `generateCsrfHeaders`).
- Żądanie:
```json
{
  "inputs": ["Wiedźmin Andrzej Sapkowski", "Ursula Le Guin"],
  "model": "openrouter/openai/gpt-4o-mini"
}
```
- Odpowiedź (201): `RecommendationEventResponse` (dokładnie 3 obiekty w `recommended`).
- Błędy:
  - 422 (walidacja) – `application/problem+json`, `errors: Record<string, string[]>` (np. `inputs`, `inputs[0]`).
  - 502 (provider error) – komunikat banner: „Nie udało się wygenerować rekomendacji. Spróbuj ponownie.”
  - 504 (timeout) – komunikat banner: „Generowanie rekomendacji trwa dłużej niż zwykle. Spróbuj ponownie.”

## 8. Interakcje użytkownika
- Wpisywanie w pola `inputs[]`: natychmiastowe czyszczenie błędu po modyfikacji, walidacja na blur.
- Klik „Dodaj kolejne pole”: dodaje nowy wiersz (focus w nowym polu). Minimalnie 3 wiersze zawsze obecne.
- Klik „Usuń” przy wierszu: usuwa wiersz, ale nigdy poniżej 3.
- Klik „Generuj rekomendacje”: 
  - Walidacja; przy błędach wyświetla `FieldErrorSummary` i banner „Popraw błędy w formularzu…”.
  - Wysyłka żądania; przycisk zmienia etykietę na „AI analizuje Twoje preferencje…”, jest zablokowany.
  - Sukces (201): redirect do `/ai/recommendations/{id}`.

## 9. Warunki i walidacja
- Min. jeden niepusty `inputs[]` (trim ≠ pusty). Błąd globalny `inputs` + przypisanie do pierwszego pustego wiersza, jeśli backend zwróci `inputs[0]`.
- Każda niepusta wartość `inputs[i]`: 1–255 znaków (frontend), `maxlength=255` w HTML.
- `model`: opcjonalnie wbudowane (konfiguracja w JS); jeśli ustawimy, kontrola długości ≤191.
- Po walidacji błędów fokus na `FieldErrorSummary` (zgodnie z `form-state.js`), a następnie na pierwszy błędny input po kliknięciu w link.

## 10. Obsługa błędów
- 401/403: natychmiastowe przekierowanie do `/auth/login`.
- 422: zmapowanie `problem.errors` na pola:
  - `inputs`: komunikat zbiorczy (np. „At least one value must be provided.”) → pokaż w summary i nad pierwszym inputem.
  - `inputs[0]`, `inputs[1]`… → przypięcie do konkretnych wierszy; jeśli wiersza brak (usunięty), zignorować/ponownie wyrenderować listę i przypisać, jeżeli możliwe.
- 502: BannerAlert `error`, tekst: „Nie udało się wygenerować rekomendacji. Spróbuj ponownie.”, akcja: „Spróbuj ponownie” (ponowna próba submit).
- 504: BannerAlert `warning`, tekst: „Generowanie rekomendacji trwa dłużej niż zwykle. Spróbuj ponownie.”, akcja retry.
- Sieć/nieoczekiwane: BannerAlert `error`, tekst ogólny „Wystąpił błąd. Spróbuj ponownie.”.
- Content-Type niepoprawny (400): zapewnić poprawny nagłówek w fetch; w praktyce nie powinno wystąpić.

## 11. Kroki implementacji
1. Routing UI:
   - Dodać `GET /ai/recommendations` do kontrolera UI (np. `AiRecommendationsFormController`) i zwrócić Twig `templates/ai/recommendations_form.html.twig`.
2. Szablon Twig `templates/ai/recommendations_form.html.twig`:
   - Struktura analogiczna do `books/new` i `shelves/index`:
     - `<main class="ai-recommendations-view" data-controller="ai-recommendations-form">`
     - Nagłówek: `h1` „Rekomendacje AI”, podtytuł z krótkim opisem.
     - Sekcja banner: `<section class="ai-recommendations-view__banner" data-ai-reco-form-target="banner" aria-live="assertive"></section>`
     - Panel karty: kontener z `FieldErrorSummary` (targets `fieldSummary` i `fieldSummaryList`).
     - Formularz: lista `inputs[]` (3 startowe), przycisk „Dodaj kolejne pole”, sekcja Examples, hidden input `data-controller="csrf-protection"`, przycisk Submit.
     - Atrybuty e2e (np. `data-e2e="ai-reco-form"`, `data-e2e="ai-reco-input-0"`, `data-e2e="ai-reco-submit"`).
3. Kontroler Stimulus `assets/controllers/ai_recommendations_form_controller.js`:
   - Wzorować się na `books_create_controller.js` (import `createFormState`, `generateCsrfHeaders`).
   - Zaimplementować targets i metody opisane w sekcjach 6–10 (walidacja, submit, obsługa błędów, banner, error summary).
   - Ustawić domyślny `model` (np. `openrouter/openai/gpt-4o-mini`) jako stałą.
4. Rejestracja kontrolera:
   - `assets/bootstrap.js`: `app.register('ai-recommendations-form', () => import('./controllers/ai_recommendations_form_controller.js'));`
5. Style:
   - Reuse istniejących utility i komponentów (`.books-create-view__panel`, `.field-error-summary`, `.banner`, `.form-field`, `.button`).
   - Dodać minimalne klasy przestrzeni nazw: `.ai-recommendations-view`, `.ai-recommendations-view__banner`, `.ai-recommendations-view__panel`, `.ai-recommendations-view__actions` (opcjonalnie; można użyć tych z books/shelves dla spójności).
6. Integracja API – wywołanie endpointu:
   - Serializować `inputs` (po trim i filtracji pustych wartości) oraz opcjonalny `model`; brak dodatkowych pól w żądaniu.
7. A11y/UX:
   - `aria-live` dla banner, `tabindex="-1"` + `focus()` dla `FieldErrorSummary`.
   - Submit: zmiana labelu na „AI analizuje Twoje preferencje…”, `aria-busy=true`.
   - Po sukcesie: redirect do `/ai/recommendations/{id}`; widok wyników powinien po załadowaniu ustawić fokus na `h1`.
8. Testy ręczne/e2e (minimalny zakres):
   - Walidacja min. 1 input, błąd 422 mapowany do summary i pola.
   - Ścieżka sukcesu 201 → redirect.
   - 502/504 → Banner z retry.
   - 401/403 → redirect do login.


