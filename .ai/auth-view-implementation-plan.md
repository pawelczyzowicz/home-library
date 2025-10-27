# Plan implementacji widoku Auth (Logowanie i Rejestracja)

## 1. Przegląd
Widoki Auth dostarczają interfejs do uwierzytelnienia użytkownika oraz utworzenia nowego konta, zgodnie z US‑001 i US‑002. Wersja web (Twig + Symfony) wykorzystuje sesje HTTP oraz CSRF. Po sukcesie oba widoki przekierowują do listy książek (`/books`).

## 2. Routing widoku
- Logowanie: `/auth/login`
- Rejestracja: `/auth/register`
- Po sukcesie: redirect 302 → `/books`

Uwaga: Ścieżki UI są niezależne od ścieżek API:
- `POST /api/auth/login`
- `POST /api/auth/register`

## 3. Struktura komponentów
- Page `AuthLoginPage`
  - `BannerAlert` (komunikaty globalne: 401/422/500)
  - `LoginForm`
    - `FormTextInput` (email)
    - `FormPasswordInput` (hasło)
    - Hidden: `CSRFField(authenticate)`
    - `SubmitButton`
    - Link: „Nie masz konta? Zarejestruj się” → `/auth/register`
- Page `AuthRegisterPage`
  - `BannerAlert`
  - `RegisterForm`
    - `FormTextInput` (email)
    - `FormPasswordInput` (hasło)
    - `FormPasswordInput` (potwierdzenie hasła)
    - `FormTextInput` (imię – opcjonalne; informacyjne)
    - `FieldErrorSummary` (mapa błędów 422)
    - Hidden: `CSRFField(authenticate)`
    - `SubmitButton`

## 4. Szczegóły komponentów
### AuthLoginPage
- Opis: Strona formularza logowania.
- Główne elementy: nagłówek h1, `BannerAlert`, `LoginForm`.
- Obsługiwane interakcje: render, przekierowanie po sukcesie.
- Walidacja: bazuje na komponencie `LoginForm`.
- Typy: `LoginViewModel`.
- Propsy: brak (samodzielna strona Twig z dołączonym JS kontrolującym formularz).

### LoginForm
- Opis: Formularz logowania (email, hasło) z neutralnym błędem 401.
- Główne elementy:
  - `<form id="login-form" novalidate>`
  - `<input type="email" name="email" autocomplete="email" autofocus>`
  - `<input type="password" name="password" autocomplete="current-password">`
  - Hidden: `<input type="hidden" name="_csrf_token" value="csrf-token-authenticate" data-controller="csrf-protection">` lub nagłówek z meta (patrz Integracja API)
  - `<button type="submit">Zaloguj</button>`
  - Link do `/auth/register`
- Obsługiwane interakcje:
  - `input`/`blur`: czyszczenie błędów dla pola
  - `submit`: `preventDefault`, walidacja minimalna, `fetch` do API, obsługa odpowiedzi
- Walidacja:
  - Email: wymagany, prosty wzorzec „@” i domena
  - Hasło: wymagane (bez wskazywania, które pole błędne dla 401)
- Typy: `LoginRequestDto`, `AuthUserDto`, `ApiProblem`, `ValidationErrors`.
- Propsy: brak (JS znajduje formularz po `#login-form`).

### AuthRegisterPage
- Opis: Strona rejestracji nowego konta.
- Główne elementy: nagłówek h1, `BannerAlert`, `RegisterForm`.
- Obsługiwane interakcje: render, redirect po sukcesie.
- Walidacja: bazuje na `RegisterForm`.
- Typy: `RegisterViewModel`.
- Propsy: brak.

### RegisterForm
- Opis: Formularz rejestracyjny z walidacją klienta i mapowaniem błędów 422/409.
- Główne elementy:
  - `<form id="register-form" novalidate>`
  - `<input type="email" name="email" autocomplete="email" autofocus>`
  - `<input type="password" name="password" autocomplete="new-password">`
  - `<input type="password" name="passwordConfirm" autocomplete="new-password">`
  - Opcjonalne: `<input type="text" name="firstName" autocomplete="given-name">`
  - `FieldErrorSummary` (lista błędów 422, zakotwiczona do pól przez `aria-describedby`)
  - Hidden: `CSRFField(authenticate)` lub nagłówek z meta
  - `<button type="submit">Utwórz konto</button>`
- Obsługiwane interakcje:
  - `input`/`blur`: walidacja na żywo i czyszczenie błędów dla pola
  - `submit`: `preventDefault`, walidacja klienta, `fetch` do API, obsługa odpowiedzi
- Walidacja (klient):
  - Email: format z „@” i domeną, długość 3–255
  - Hasło: min 8 znaków
  - Potwierdzenie: identyczne jak `password`
- Mapowanie błędów (serwer):
  - 422: pokazanie błędów przy polach i w `FieldErrorSummary`
  - 409: banner „Ten email jest już zarejestrowany”
- Typy: `RegisterRequestDto`, `AuthUserDto`, `ApiProblem`, `ValidationErrors`.
- Propsy: brak (JS znajduje formularz po `#register-form`).

### BannerAlert
- Opis: Komponent do komunikatów globalnych (success/error/info) nad formularzem.
- Elementy: `<div role="alert" aria-live="polite">` z wariantami stylów.
- Zdarzenia: pokaz/ukryj na podstawie stanu widoku.
- Walidacja: nie dotyczy.
- Typy: `BannerAlertProps` (message, variant, icon?).
- Propsy: `message: string`, `variant: 'success' | 'error' | 'info'`.

### FieldErrorSummary
- Opis: Lista błędów walidacyjnych 422 powiązana z polami.
- Elementy: `<ul>`; linki do pól (anchor `#field-id`).
- Zdarzenia: po otrzymaniu 422 – fokus na nagłówek błędów, `aria-live="assertive"`.
- Walidacja: pokazuje treść z `ValidationErrors`.
- Typy: `ValidationErrors`.
- Propsy: `errorsByField: Record<string, string[]>`.

### CSRFField(authenticate)
- Opis: Ukryte pole aktywujące mechanizm CSRF (alternatywa dla nagłówka z meta).
- Elementy: `<input type="hidden" data-controller="csrf-protection" value="csrf-token-authenticate">`.
- Zdarzenia: obsługiwane przez istniejący skrypt podczas `submit`/`turbo:submit-*`.
- Uwaga: dla żądań JSON rekomendowana jest ścieżka z nagłówkiem `X-CSRF-Token` (patrz Integracja API).

## 5. Typy
Przykładowe definicje (JSDoc) dla spójności w JS:
```ts
/** @typedef {{ email: string, password: string }} LoginRequestDto */
/** @typedef {{ email: string, password: string, passwordConfirm: string, firstName?: string }} RegisterRequestDto */
/** @typedef {{ id: string, email: string, createdAt: string }} AuthUserDto */
/** @typedef {{ status: number, title?: string, detail?: string, violations?: Array<{ propertyPath: string, message: string }> }} ApiProblem */
/** @typedef {Record<string, string[]>} ValidationErrors */
/** @typedef {{ message: string, variant: 'success'|'error'|'info' }} BannerAlertProps */
/** @typedef {{ submitting: boolean, banner?: BannerAlertProps, fieldErrors: ValidationErrors }} LoginViewModel */
/** @typedef {{ submitting: boolean, banner?: BannerAlertProps, fieldErrors: ValidationErrors }} RegisterViewModel */
```

## 6. Zarządzanie stanem
- Lokalny stan formularza w module JS (per widok):
  - `submitting: boolean` – blokada wielokrotnych submitów
  - `banner: BannerAlertProps | undefined` – globalny komunikat (401/409/500)
  - `fieldErrors: ValidationErrors` – błędy 422
- Brak globalnego store – sesja jest utrzymywana przez cookie HTTP po stronie backendu.
- Custom „hook”/helper w JS (funkcja fabrykująca) `createFormState()` do mutacji stanu i aktualizacji UI (dodawanie `aria-invalid`, `aria-describedby`, treści błędów, włączanie/wyłączanie przycisku).

## 7. Integracja API
- Wspólne:
  - `fetch(url, { method: 'POST', headers, body: JSON.stringify(dto), credentials: 'same-origin' })`
  - Nagłówki: `Accept: application/json`, `Content-Type: application/json`, `X-CSRF-Token: <token>`
  - CSRF token: odczyt z meta `<meta name="csrf-token-authenticate" content="...">` i wstaw do `X-CSRF-Token`.

Referencja istniejących meta tagów:
```14:16:templates/base.html.twig
        <meta name="csrf-token-authenticate" content="{{ csrf_token('authenticate') }}">
        <meta name="csrf-token-logout" content="{{ csrf_token('logout') }}">
```

- Login
  - Request: `LoginRequestDto`
  - Endpoint: `POST /api/auth/login`
  - 200 OK: `{ user: AuthUserDto }` → redirect `/books`
  - 401: banner „Nieprawidłowy email lub hasło” (bez wskazania pola)
  - 422: walidacje wejścia (rzadkie dla loginu), pokaż w `FieldErrorSummary`
- Register
  - Request: `RegisterRequestDto`
  - Endpoint: `POST /api/auth/register`
  - 201 Created: `{ user: AuthUserDto }` + Set-Cookie → redirect `/books` i wyświetlenie komunikatu powitalnego (flash po stronie serwera)
  - 409: banner „Ten email jest już zarejestrowany”
  - 422: mapowanie błędów do pól + `FieldErrorSummary`

## 8. Interakcje użytkownika
- Logowanie
  - Użytkownik wypełnia email i hasło → `Submit`
  - Sukces: przekierowanie do `/books`
  - Błąd 401: `BannerAlert(error)` i fokus powraca na email
  - Błąd sieci/500: `BannerAlert(error)` z tekstem technicznym neutralnym
- Rejestracja
  - Użytkownik wypełnia email, hasło, potwierdzenie (i ewentualnie imię) → `Submit`
  - Klient weryfikuje: format email, min 8, zgodność haseł
  - Sukces (201): redirect do `/books`
  - 409: `BannerAlert("Ten email jest już zarejestrowany")`
  - 422: błędy przypięte do pól + `FieldErrorSummary`, fokus na pierwszy błąd

## 9. Warunki i walidacja
- Logowanie (UI):
  - Email: wymagany; prosty regex: `/.+@.+\..+/`
  - Hasło: wymagane
  - 401 (serwer): zawsze neutralny komunikat globalny; nie oznaczamy konkretnego pola jako błędnego
- Rejestracja (UI):
  - Email: wymagany, 3–255, `/.+@.+\..+/`
  - Hasło: min 8 znaków
  - Potwierdzenie: identyczne z hasłem
- A11y:
  - `autofocus` na email (oba widoki)
  - Przy błędzie: `aria-invalid="true"`, `aria-describedby` do komunikatu błędu
  - `FieldErrorSummary` z `role="alert"` i `tabindex="-1"` — fokus po 422

## 10. Obsługa błędów
- 401 Unauthorized (login): komunikat neutralny „Nieprawidłowy email lub hasło”. Brak wskazania, które pole błędne.
- 409 Conflict (register): banner z komunikatem z PRD.
- 422 Unprocessable Entity: mapuj `violations[*].propertyPath` → `errorsByField[name]`.
- 429 Too Many Requests (backend throttling): banner „Zbyt wiele prób. Spróbuj ponownie za chwilę.”
- 400/500/Network: banner ogólny „Wystąpił błąd. Spróbuj ponownie.”, log do konsoli (dev) lub `console.error` bez PII.

## 11. Kroki implementacji
1) Routing UI: dodaj kontroler/akcje Twig (np. `AuthController::login`, `AuthController::register`) z trasami `/auth/login`, `/auth/register` renderujące odpowiednie szablony.
2) Szablony Twig:
   - `templates/auth/login.html.twig` i `templates/auth/register.html.twig`; dziedziczą z `base.html.twig`.
   - Sekcja body: nagłówek, `BannerAlert` (opcjonalnie jako include), formularz z polami i ukrytym `CSRFField(authenticate)` lub bez, jeśli używamy tylko nagłówka metatagu.
3) JS – API helper `assets/auth/api.js`:
   - `postJson(url, dto, csrfMetaName = 'csrf-token-authenticate')` → `fetch` z `X-CSRF-Token` (odczyt z meta), `credentials: 'same-origin'`.
4) JS – Logika formularzy:
   - `assets/auth/login.js`: obsługa `#login-form` (walidacja podstawowa, 401 → banner, 200 → redirect `/books`).
   - `assets/auth/register.js`: obsługa `#register-form` (walidacja klienta, 409/422 → błędy, 201 → redirect `/books`).
   - Rejestracja modułów w `assets/app.js` (import plików, nasłuch DOMContentLoaded na przypięcie handlerów).
5) CSRF:
   - nagłówek `X-CSRF-Token` z meta `csrf-token-authenticate`.
6) A11y/UX:
   - `autofocus` na email; fokus na pierwszy błąd po walidacji/422; `aria-*` atrybuty.
   - Przy `submitting=true`: disable przycisku submit, spinner (opcjonalnie), zapobieganie wielokrotnym submitom.
7) Style: podstawowe klasy CSS w `assets/styles/app.css` dla `BannerAlert`, błędów pól i stanu disabled.
8) Redirect po sukcesie: po 200/201 wywołaj `window.location.assign('/books')`.
9) Testy E2E (Panther):
   - Logowanie: błędne dane → 401 banner; poprawne → redirect `/books` i ustawione cookie sesji.
   - Rejestracja: weryfikacja 422 (krótkie hasło), 409 (duplikat), 201 → redirect.

