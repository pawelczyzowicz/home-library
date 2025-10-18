# Architektura UI dla HomeLibrary

## 1. Przegląd struktury UI

HomeLibrary wykorzystuje minimalistyczny, desktop‑first interfejs oparty o server‑side rendering (Twig) i pełne przeładowania stron. Kontrolery Twig pośredniczą w komunikacji z warstwą API (`/api/*`), przekazując sesję oraz nagłówek `X‑CSRF‑Token`. Brak globalnego stanu po stronie klienta; stan filtrów i sortowania utrzymywany jest w URL (query string).

- Architektura renderowania: SSR (Twig) + proste, progresywne wzmocnienia (np. dezaktywacja przycisków i spinner tekstowy podczas akcji).
- Nawigacja: stały top‑nav z pozycjami „Książki”, „Regały”, „Rekomendacje AI”, „Wyloguj”. Brak widoku „Konto”.
- Wzorzec PRG (Post‑Redirect‑Get): dla wszystkich akcji modyfikujących (create/update/delete/accept) w celu uniknięcia duplikacji i odświeżeń formularza.
- Bezpieczeństwo: CSRF w formularzach Twig (`{{ csrf_token('intent') }}`) i nagłówek `X‑CSRF‑Token` do `/api/*`; sesja HTTP‑only, SameSite=Lax. Dostęp do `/api/*` wymaga uwierzytelnienia; 401 skutkuje redirectem do logowania.
- Błędy i stany: mapowanie RFC 7807 na baner/komunikaty (422, 403, 404, 409, 502, 504). Walidacja po stronie backend; UI wyświetla listę błędów i zachowuje dane wejściowe.
- Dane referencyjne: gatunki (`GET /api/genres`) i regały (`GET /api/shelves`) ładowane serwerowo do selektorów.
- Wydajność: lista książek pełni rolę dashboardu; domyślne sortowanie `createdAt desc`; limit 50–100 rekordów na stronę, informacja „Wyświetlono X z Y”; prosta paginacja `limit/offset`.
- Idempotencja AI: serwer generuje UUID `Idempotency‑Key` dla akceptacji rekomendacji; UI blokuje przycisk do czasu odpowiedzi i stosuje PRG.
- Dostępność (minimum): semantyczne etykiety, czytelne komunikaty błędów, focus management po PRG (focus na nagłówku/komunikacie), banery o roli „alert”.

Zgodność z planem API: widoki i działania korzystają z zdefiniowanych endpointów (`/api/auth/*`, `/api/books`, `/api/shelves`, `/api/genres`, `/api/ai/recommendations/*`), parametry filtrów w query, walidacje jak w specyfikacji.

## 2. Lista widoków

Poniżej zdefiniowane widoki zawierają: ścieżkę, cel, kluczowe informacje, komponenty, powiązane akcje API, względy UX/A11y/Sec oraz powiązane historyjki użytkownika (US‑xxx) z PRD.

1) Widok: Logowanie
- Ścieżka widoku: `/auth/login`
- Główny cel: Uwierzytelnienie i rozpoczęcie sesji.
- Kluczowe informacje: formularz email/hasło, link do rejestracji, komunikaty błędów logowania.
- Kluczowe komponenty: Formularz logowania, BannerAlert (401/422), Link „Zarejestruj się”.
- Akcje API: `POST /api/auth/login`; sukces → redirect do `/books`.
- UX/A11y/Sec: autofocus na email; przy błędzie 401 neutralny komunikat; brak wskazania, które pole jest błędne; CSRF; blokada brute force po stronie backend (rate limiting).
- Powiązane US: US‑002.

2) Widok: Rejestracja
- Ścieżka widoku: `/auth/register`
- Główny cel: Utworzenie konta i automatyczne zalogowanie.
- Kluczowe informacje: email, hasło, potwierdzenie hasła, imię (opcjonalnie); komunikaty walidacji; potwierdzenie sukcesu.
- Kluczowe komponenty: Formularz rejestracji, FieldErrorSummary, BannerAlert.
- Akcje API: `POST /api/auth/register`; sukces → redirect do `/books`.
- UX/A11y/Sec: minimalne wskazówki dot. hasła (tekst), komunikaty 422; CSRF; po sukcesie komunikat powitalny (flash).
- Powiązane US: US‑001.

3) Widok: Lista książek (Dashboard)
- Ścieżka widoku: `/books`
- Główny cel: Przegląd i filtrowanie kolekcji; centralny dashboard po zalogowaniu.
- Kluczowe informacje: liczba wyników, filtr `q`, `shelfId`, `genreIds`, sort (`title|author|createdAt`), `order`, paginacja `limit/offset`; tabela z kolumnami: tytuł, autor, gatunki, regał, (opcjonalnie ISBN, liczba stron), akcje.
- Kluczowe komponenty: FilterFormBooks (GET), BooksTable, PaginationControls, EmptyState, BannerAlert, Linki akcji („Szczegóły”, „Edytuj”, „Usuń”).
- Akcje API: `GET /api/books` (z query), `GET /api/genres`, `GET /api/shelves` do danych referencyjnych.
- UX/A11y/Sec: stan „Wyświetlono X z Y”; utrzymanie filtrów w URL; whitelist sortowania; brak JS live‑search (full reload); 404 gdy brak zasobu przy wejściu z nieaktualnym URL; 401 → redirect do logowania.
- Powiązane US: US‑013, US‑014, US‑015, US‑016, US‑017, US‑023, US‑028.

4) Widok: Dodaj książkę
- Ścieżka widoku: `/books/new`
- Główny cel: Utworzenie nowej książki i przypisanie do regału.
- Kluczowe informacje: pola: tytuł, autor (wymagane), `shelfId` (select), `genreIds` (multi‑select 1–3), ISBN (opc.), liczba stron (opc.).
- Kluczowe komponenty: BookForm (Create), FieldErrorSummary, BannerAlert.
- Akcje API: `POST /api/books` (source=manual); po 201 → PRG redirect do `/books` lub `/books/{id}`.
- UX/A11y/Sec: walidacje 422 wyświetlane nad formularzem; zachowanie inputów; CSRF; selecty wypełnione z `GET /api/shelves` i `GET /api/genres`.
- Powiązane US: US‑004, US‑029.

5) Widok: Edytuj książkę
- Ścieżka widoku: `/books/{id}/edit`
- Główny cel: Aktualizacja pól książki, w tym zmiana regału i gatunków.
- Kluczowe informacje: te same pola co Create, wypełnione aktualnymi danymi.
- Kluczowe komponenty: BookForm (Edit), FieldErrorSummary, BannerAlert.
- Akcje API: `PATCH /api/books/{id}`; po 200 → PRG redirect do `/books/{id}` lub `/books`.
- UX/A11y/Sec: 404 jeśli książka nie istnieje (np. równoległe usunięcie — US‑026); CSRF; komunikat „Zmiany zapisane”.
- Powiązane US: US‑005, US‑006, US‑026.

6) Widok: Szczegóły książki
- Ścieżka widoku: `/books/{id}`
- Główny cel: Wgląd w pełne dane książki z metadanymi pochodzenia.
- Kluczowe informacje: tytuł, autor, gatunki, regał, ISBN (jeśli podany), liczba stron (jeśli podana), `source`, `recommendationId` (jeśli dotyczy); akcje: „Edytuj”, „Usuń”, „Powrót”.
- Kluczowe komponenty: BookDetails, ActionButtons (Edit/Delete), BannerAlert.
- Akcje API: `GET /api/books/{id}`; Delete inicjowane z poziomu tego widoku.
- UX/A11y/Sec: 404 dla nieistniejącego ID; Delete jako formularz POST/DELETE z CSRF i stroną potwierdzenia.
- Powiązane US: US‑008, US‑020 (metadane), US‑021 (konsekwencje odrzucenia nie zapisują się — informacyjnie).

7) Widok: Lista regałów
- Ścieżka widoku: `/shelves`
- Główny cel: Przegląd i zarządzanie regałami; tworzenie nowych; usuwanie pustych.
- Kluczowe informacje: tabela: nazwa, znacznik `isSystem`, daty utw./akt.; akcje: „Dodaj”, „Usuń” (gdy dozwolone).
- Kluczowe komponenty: ShelvesTable, ShelfCreateForm (inline lub osobna strona), BannerAlert.
- Akcje API: `GET /api/shelves`; `POST /api/shelves`; `DELETE /api/shelves/{id}`.
- UX/A11y/Sec: regał „Do zakupu” bez akcji Delete; przy `409` (niepusty) wyświetlić banner z `detail` i zablokować operację (bez UI przenoszenia w MVP); CSRF w akcjach.
- Powiązane US: US‑009, US‑011, US‑012, US‑027, US‑030.

8) Widok: Rekomendacje AI – formularz
- Ścieżka widoku: `/ai/recommendations`
- Główny cel: Zebranie wejść dla rekomendacji; uruchomienie generowania.
- Kluczowe informacje: min. 1 pole tekstowe (z możliwością dodania kolejnych w przyszłości), przykłady, przycisk „Generuj rekomendacje”.
- Kluczowe komponenty: AIForm, FieldErrorSummary, BannerAlert (provider 502/504), Submit z prostym spinnerem tekstowym („AI analizuje…”).
- Akcje API: `POST /api/ai/recommendations/generate`; sukces (201) → redirect do `#/ai/recommendations/{eventId}` (PRG).
- UX/A11y/Sec: 422 gdy brak danych; 504/502 komunikat „AI niedostępne, spróbuj ponownie”; CSRF; fokus na nagłówku wyników po redirect.
- Powiązane US: US‑018, US‑019, US‑025.

9) Widok: Rekomendacje AI – wyniki (3 karty)
- Ścieżka widoku: `/ai/recommendations/{eventId}`
- Główny cel: Prezentacja 3 propozycji z uzasadnieniem i akcjami akceptacji/odrzucenia.
- Kluczowe informacje: 3 karty: tytuł, autor, krótkie uzasadnienie; przyciski „Akceptuj” i „Odrzuć”.
- Kluczowe komponenty: RecommendationCards (x3), SpinnerButton („Przetwarzanie…” przy akceptacji), BannerAlert.
- Akcje API (akceptacja):
  1) `POST /api/books` z `source = "ai_recommendation"`, `recommendationId = {eventId}`, `shelfId = id regału systemowego "Do zakupu"` (wyszukany przez kontroler w `GET /api/shelves?includeSystem=true`).
  2) `POST /api/ai/recommendations/{eventId}/accept` z `bookId` oraz nagłówkiem `Idempotency‑Key` (UUID generowany na serwerze).
  Sukces → PRG redirect do `/books` z komunikatem o dodaniu.
- UX/A11y/Sec: przy „Akceptuj” dezaktywować przycisk i pokazać spinner tekstowy do czasu zakończenia; 400/404/409 mapować na baner; 502/504 dla providera AI komunikować podczas generowania (formularz).
- Uwaga: API wymaga `genreIds` przy tworzeniu książki; w MVP UI akceptacja dodaje książkę z minimalnymi danymi (tytuł, autor) – należy doprecyzować walidację po stronie API (poluzowanie lub domyślne gatunki). Kontroler Twig powinien zająć się brakującymi polami zgodnie z decyzją backendu.
- Powiązane US: US‑019, US‑020, US‑021, US‑022, US‑031.

## 3. Mapa podróży użytkownika

Główne przepływy end‑to‑end z przejściami między widokami:

1) Rejestracja i start
- `/auth/register` → (POST) → [PRG] → `/books`
- Efekt: sesja aktywna; systemowy regał „Do zakupu” istnieje; EmptyState jeśli brak książek (US‑028).

2) Dodanie książki
- `/books` → klik „Dodaj książkę” → `/books/new` → (POST `POST /api/books`) → [PRG] → `/books` (flash „Książka została dodana”).

3) Edycja i przeniesienie na inny regał
- `/books` lub `/books/{id}` → „Edytuj” → `/books/{id}/edit` → (PATCH) → [PRG] → `/books/{id}` lub `/books` (flash „Zmiany zapisane”).
- Filtry i sortowanie utrzymane w URL po powrocie na listę.

4) Usunięcie książki
- `/books/{id}` → „Usuń” → `/books/{id}/delete` (confirm) → (DELETE) → [PRG] → `/books` (flash „Książka została usunięta”).

5) Zarządzanie regałami
- Lista: `/shelves` (GET)
- Dodanie: `/shelves` (inline) lub `/shelves/new` → (POST) → [PRG] → `/shelves` (flash „Regał został utworzony”).
- Usunięcie: klik „Usuń” → (DELETE) → przy 204 [PRG] → `/shelves`; przy 409 banner „Shelf not empty” (bez przenoszenia w UI MVP).

6) Rekomendacje AI – generowanie i akceptacja
- Formularz: `/ai/recommendations` → (POST generate) → [PRG] → `/ai/recommendations/{eventId}` z 3 kartami.
- Akceptacja: na karcie klik „Akceptuj” → UI blokuje przycisk i pokazuje „Przetwarzanie…” → serwer wykonuje sekwencję: `POST /api/books` (source=ai_recommendation, shelf="Do zakupu") → `POST /api/ai/recommendations/{eventId}/accept` (Idempotency‑Key) → [PRG] → `/books` (flash „Dodano do regału 'Do zakupu'”).
- Odrzucenie: klik „Odrzuć” → odświeżony widok bez tej karty; po odrzuceniu wszystkich – link „Wygeneruj nowe rekomendacje”.

Powiązanie z historyjkami PRD (wybrane):
- US‑001/002/003: logowanie/rejestracja/wylogowanie.
- US‑004/005/006/007/008: CRUD i detale książki.
- US‑009/011/012/027/030: zarządzanie regałami i ograniczenia systemowe.
- US‑013/014/015/016/017: lista, wyszukiwanie, filtrowanie, sortowanie.
- US‑018/019/020/021/022/025/031: rekomendacje AI i obsługa błędów.
- US‑023/024/026/028/029: współdzielenie, skrajne przypadki, puste stany, walidacje.

## 4. Układ i struktura nawigacji

- Top‑nav (stały):
  - „Książki” → `/books` (aktywny jako dashboard po zalogowaniu)
  - „Regały” → `/shelves`
  - „Rekomendacje AI” → `/ai/recommendations`
  - „Wyloguj” → (POST do `/api/auth/logout` wykonywany przez kontroler Twig; PRG → `/auth/login`)

- Sekundarne elementy nawigacyjne:
  - Breadcrumbs: opcjonalne; minimalnie link „Powrót do listy” na stronach szczegółów/edycji.
  - Paginacja: kontrolki `limit/offset` w stopce listy książek.
  - Sortowanie: kontrola w formularzu filtrów; utrzymane w query string.

- Wzorce nawigacyjne:
  - PRG po każdej akcji modyfikującej (spójne komunikaty flash, uniknięcie ponownych POST).
  - Utrzymywanie stanu filtrów przez linki akcji listy (np. powrót do `/books` odtwarza ostatni `q/shelfId/genreIds/sort/order/limit/offset`).

## 5. Kluczowe komponenty

- LayoutShell (Twig base): wspólny układ, top‑nav, slot na banery i treść.
- BannerAlert: render błędów RFC 7807 i komunikatów flash; role="alert", semantyczne kolory.
- FieldErrorSummary: lista błędów 422 nad formularzem, z odnośnikami do pól.
- FilterFormBooks: formularz GET z polami `q`, `shelfId`, `genreIds`, `sort`, `order`, `limit`.
- BooksTable: tabela wyników z kolumnami, linkami akcji i informacją „Wyświetlono X z Y”.
- PaginationControls: linki `Poprzednia/Następna`, wskaźnik strony; parametry w query string.
- BookForm: wspólny formularz create/edit z walidacją backend; select regałów i multi‑select gatunków.
- BookDetails: sekcja szczegółów z metadanymi `source`, `recommendationId` i akcjami.
- ConfirmForm: uniwersalny szablon potwierdzeń destruktywnych (np. Delete book) z CSRF.
- ShelvesTable: tabela regałów z oznaczeniem `isSystem` i akcjami.
- ShelfCreateForm: prosty formularz dodania regału (inline lub osobny widok).
- AIForm: formularz wejściowy do generowania rekomendacji; prosty spinner tekstowy podczas submitu.
- RecommendationCards: 3 karty z danymi propozycji i przyciskami „Akceptuj/Odrzuć”.
- SpinnerButton: przycisk, który po kliknięciu dezaktywuje się i pokazuje etykietę „Przetwarzanie…”.
- EmptyState: komponent pustych stanów (np. po rejestracji, brak wyników wyszukiwania).
- ErrorPages (403/404): dedykowane szablony stron stanu z jasnym CTA „Wróć”.

Uwagi integracyjne z API (zgodność):
- Autoryzacja: kontrolery Twig obsługują `/api/auth/*`; wszystkie pozostałe endpointy wymagają sesji.
- Filtry: `q` działa na tytuł/autor (OR), `shelfId` to UUID, `genreIds` to lista ID integer (OR), łączenie filtrów wg AND między grupami.
- Sortowanie: whitelist pól (`title`, `author`, `createdAt`) i `order` (`asc|desc`).
- Walidacje: długości pól, format ISBN (10/13 cyfr), `pageCount` 1–50000; UI polega na odpowiedzi 422 i wyświetla komunikaty.
- Regał „Do zakupu”: kontroler Twig wyszukuje po `isSystem=true` i przekazuje `shelfId` podczas akceptacji AI.
- Idempotencja: `Idempotency‑Key` (UUID) wymagany przy akceptacji rekomendacji.
- Błędy krytyczne AI: `502/504` renderowane jako banner z możliwością ponownej próby.
