# Plan implementacji widoku Rekomendacje AI – wyniki (3 karty)

## 1. Przegląd
Widok prezentuje wynik pojedynczego zdarzenia rekomendacji AI (3 propozycje książek), pozwalając na akceptację lub odrzucenie każdej z nich. Akceptacja tworzy książkę w regale systemowym „Do zakupu”, następnie aktualizuje event rekomendacji i wykonuje PRG redirect do listy książek z komunikatem potwierdzającym.

Cele:
- Wyświetlenie 3 kart z rekomendacjami zawierającymi tytuł, autora, krótkie uzasadnienie oraz listę gatunków wynikających z `genresId`.
- Umożliwienie akceptacji (z bezpiecznym, idempotentnym flow) i odrzucenia (lokalnie, bez API) propozycji.
- Płynna obsługa stanów: ładowanie, częściowo zaakceptowane, błędy.
- A11y: poprawne ogłaszanie stanów, fokus po redirect, klawiszologia, czytelne komunikaty błędów.


## 2. Routing widoku
- Ścieżka: `/ai/recommendations/{eventId}`
- Metoda: GET (SSR – Twig) renderuje stronę z kontenerem pod kontroler JS.
- Nazwa trasy (sugestia w Symfony): `ai_recommendations_show`
- Wejście: `eventId` (liczbowe ID eventu rekomendacji) – wymagane.


## 3. Struktura komponentów
- AIRecommendationsResultsPage (Twig, kontroler główny)
  - BannerAlert (Twig partial istniejący: `templates/components/_banner_alert.html.twig`)
  - RecommendationCards (blok/kontener kart)
    - RecommendationCard (x3)
      - SpinnerButton (logika stanu przycisku „Akceptuj”)
  - EmptyState (opcjonalnie, gdy nie ma nic do pokazania)

Warstwa JS (Stimulus):
- `ai_recommendations_results_controller.js` – kontroler strony wyników (ładowanie danych eventu, delegacja akcji akceptacji/odrzucenia, stany, komunikaty).
- użycie istniejącego `csrf_protection_controller.js` do nagłówka CSRF lub bezpośredni odczyt z meta.


## 4. Szczegóły komponentów
### AIRecommendationsResultsPage
- Opis: Strona wyników pojedynczego eventu rekomendacji. Odpowiada za A11y (fokus na nagłówku), inicjalne pobranie danych/encyklopedii gatunków (jeśli nie jest wstrzyknięta), render banera błędów/globalnych komunikatów.
- Główne elementy:
  - `<h1 id="ai-results-title" tabindex="-1">Rekomendacje AI</h1>` – cel fokusa po redirect.
  - Sekcja z licznikami i pomocniczym tekstem.
  - Kontener kart z atrybutami `data-controller="ai-recommendations-results"` i `data-*` z wartościami inicjalnymi (np. `data-event-id-value`).
  - BannerAlert (do błędów globalnych).
- Obsługiwane interakcje:
  - Na mount: fetch eventu (jeśli nie SSR) i katalogu gatunków (jeśli nie SSR/wstrzyknięty).
  - Obsługa komunikatów flash (jeśli przekazane).
- Walidacja:
  - `eventId` obecny i poprawny (liczba > 0); w razie braku → 404 UI + informacja.
- Typy: `RecommendationEventDto`, `GenresCatalogItemDto`, `PageState` (opis w sekcji Typy).
- Propsy (dane od rodzica/Twig): `eventId`, opcjonalnie `preloadedEventJson`, `genresCatalogJson`, `purchaseShelfId` (opcjonalna optymalizacja). Wszystko przekazywane jako `data-*` na kontener.

### RecommendationCards
- Opis: Renderuje listę kart (3) na podstawie danych eventu, filtrując odrzucone lokalnie lub już zaakceptowane.
- Główne elementy:
  - Lista/kontener kart `<div role="list">`.
  - Render 0–3 dzieci `RecommendationCard`.
- Obsługiwane interakcje:
  - Delegacja kliknięć „Akceptuj”/„Odrzuć” w dół.
  - Warunkowe ukrycie/oznaczenie kart zaakceptowanych lub odrzuconych.
- Walidacja:
  - Każdy element `recommended` musi mieć `tempId`, `title`, `author`, `genresId` (1–3), `reason`.
  - Nieznane `genresId` → wyświetl „Nieznany gatunek (id: X)”.
- Typy: `RecommendationProposalDto`, `RecommendationCardViewModel`.
- Propsy/dane: `proposals`, `acceptedBookIds`, `dismissedTempIds`, `genresCatalog`, `eventId`, `purchaseShelfId` (opcjonalnie).

### RecommendationCard
- Opis: Pojedyncza karta książki z tytułem, autorem, uzasadnieniem, listą gatunków (chip/tag) i przyciskami akcji.
- Główne elementy:
  - Nagłówek z tytułem `<h2>` i autorem.
  - Krótkie uzasadnienie `<p>`.
  - Lista tagów gatunków (na podstawie mapowania `genresId -> name`).
  - Przyciski: „Akceptuj” (priorytetowy) i „Odrzuć” (tekstowy/secondary).
  - Region statusu dla komunikatów akcji (ARIA live polite).
- Obsługiwane interakcje:
  - Klik „Akceptuj”: 2‑krokowy flow API (tworzenie książki → akceptacja eventu) z blokadą przycisku i spinnerem („Przetwarzanie…”).
  - Klik „Odrzuć”: lokalne ukrycie karty (fadeout), bez wywołań API.
- Walidacja:
  - Zabezpieczenie przed wielokrotnym kliknięciem („debounce” / blokada) + nagłówek `Idempotency-Key` w drugim kroku.
  - Nie pozwól akceptować, jeśli pozycja już zaakceptowana (po ID książki w `acceptedBookIds` lub po stanie lokalnym `accepted`).
- Typy: `AcceptActionState`, `BookCreateRequestDto`, `AcceptRecommendationRequestDto`, `RecommendationProposalDto`.
- Propsy/dane: `proposal`, `eventId`, `genresCatalog`, `purchaseShelfId`, `onAccepted(proposal, bookId)`, `onError(error, proposal)`.

### SpinnerButton
- Opis: Stanowy przycisk z tekstowym spinnerem „Przetwarzanie…”. Zapewnia blokadę, odpowiednie ARIA (`aria-busy="true"`), a11y tekst statusu.
- Główne elementy: `<button>` + `<span class="spinner">` (CSS) + `<span class="label">`.
- Interakcje: Rozpoczęcie/przerwanie stanu ładowania, uniemożliwienie ponownego kliknięcia.
- Walidacja: Brak (sterowane przez rodzica).
- Typy: `SpinnerState`.
- Propsy: `loadingText`, `disabled`, `onClick`.

### BannerAlert (istniejący)
- Opis: Komponent do wyświetlania komunikatów o błędach/stanach informacyjnych. Używany globalnie u góry strony oraz inline przy kartach (opcjonalnie).
- Główne elementy: wrapper alertu, ikona, treść, przycisk zamknięcia (opcjonalnie).
- Interakcje: Zamknięcie alertu, fokus do początku strony (jeśli krytyczny błąd).
- Walidacja: Treść niepusta.
- Typy: `BannerAlertViewModel`.
- Propsy: `type` (error/success/info), `message`, `dismissible`.

### EmptyState (opcjonalny)
- Opis: Gdy wszystkie 3 propozycje zostały odrzucone lub zaakceptowane – wyświetla CTA do ponownego generowania (`/ai/recommendations`).
- Główne elementy: tytuł, krótki opis, link/przycisk.
- Interakcje: Link do formularza.
- Walidacja: Brak.
- Typy: brak.
- Propsy: brak.


## 5. Typy
Pseudotypy (TypeScript‑like) dla czytelności – implementacja w JS (JSDoc) lub PHP/Twig jako JSON.

```ts
type RecommendationProposalDto = {
  tempId: string;
  title: string;         // 1–255
  author: string;        // 1–255
  genresId: number[];    // 1–3 wartości z katalogu gatunków
  reason: string;        // 1–2 zdania
};

type RecommendationEventDto = {
  id: number;
  createdAt: string;     // ISO
  userId: string;        // UUID
  inputTitles: string[];
  recommended: RecommendationProposalDto[];
  acceptedBookIds: string[]; // UUID[]
};

type GenresCatalogItemDto = { id: number; name: string };

type ShelfDto = {
  id: string;            // UUID
  name: string;
  isSystem?: boolean;
  code?: string;         // np. 'PURCHASE' – jeśli backend tak wystawia
};

type BookCreateRequestDto = {
  title: string;
  author: string;
  genreIds: number[];    // 1–3
  shelfId: string;       // UUID 'Do zakupu'
  source: 'ai_recommendation';
  recommendationId: number;
};

type BookCreateResponseDto = { id: string };

type AcceptRecommendationRequestDto = { bookId: string };
type AcceptRecommendationResponseDto = { event: { id: number; acceptedBookIds: string[] } };

type RecommendationCardViewModel = RecommendationProposalDto & {
  genreNames: string[];
  isAccepted: boolean;
  isDismissed: boolean;
};

type AcceptActionState = 'idle' | 'accepting' | 'accepted' | 'error';

type PageState = {
  isLoading: boolean;
  loadError: string | null;
  event: RecommendationEventDto | null;
  genresCatalog: GenresCatalogItemDto[];
  purchaseShelfId: string | null;
  dismissedTempIds: Set<string>;
  actionStateByTempId: Record<string, AcceptActionState>;
  globalAlert: { type: 'error' | 'success' | 'info'; message: string } | null;
};
```


## 6. Zarządzanie stanem
- Stan trzymany w kontrolerze Stimulus `ai_recommendations_results_controller.js` w polach klasy.
- Inicjalizacja:
  - Ustaw `isLoading = true` → pobierz event (jeśli nie SSR JSON) i katalog gatunków (jeśli brak) → pobierz/potwierdź `purchaseShelfId` (z `/api/shelves?includeSystem=true` lub z wstrzykniętego `data-purchase-shelf-id-value`) → `isLoading = false`.
  - Po renderze: fokus na `#ai-results-title` (`element.focus({ preventScroll: false })`).
- Działania:
  - `accept(proposal)` – zmienia `actionStateByTempId[tempId] = 'accepting'`, blokuje przycisk; wykonuje sekwencję API (patrz Integracja API); w sukcesie `acceptedBookIds` uzupełnione i stan `'accepted'`, a potem PRG redirect do `/books`.
  - `reject(proposal)` – dodaje `tempId` do `dismissedTempIds`, animuje i usuwa kartę.
  - `setGlobalAlert(type, message)` – wyświetla baner błędu/sukcesu.
- Odporność na duplikaty:
  - Po stronie UI – blokada wielokrotnego kliknięcia.
  - Po stronie API – nagłówek `Idempotency-Key` dla drugiego kroku.


## 7. Integracja API
### Pobranie eventu (GET – zalecane, jeśli nie SSR JSON)
- Endpoint (przykładowy): `GET /api/ai/recommendations/{eventId}` → `RecommendationEventDto`
- Błędy: `404` → wyświetl BannerAlert „Nie znaleziono zestawu rekomendacji”.
- A11y: Po sukcesie – fokus na nagłówku.

### Pobranie katalogu gatunków
- Endpoint: `GET /api/genres` → `GenresCatalogItemDto[]`
- Cache po stronie kontrolera (przechowuj w stanie).
- Gdy brak – mapuj gatunki jako „Nieznany gatunek (id: X)”.

### Ustalenie regału „Do zakupu”
- Opcja A (preferowana): Twig wstrzykuje `purchaseShelfId` (np. z backendu).
- Opcja B: `GET /api/shelves?includeSystem=true` → znajdź rekord o kodzie/nazwie odpowiadającej „Do zakupu”.
  - Błędy: brak regału → BannerAlert „Brak regału systemowego ‘Do zakupu’ – skontaktuj się z administratorem”.

### Akceptacja propozycji – sekwencja
1) Utwórz książkę
   - `POST /api/books` (JSON `BookCreateRequestDto`)
   - Odpowiedź: `201` + `{ id: string }`
   - Błędy: `400` (walidacja pól), `404` (brak regału), inne → BannerAlert przy karcie.

2) Zaktualizuj event rekomendacji
   - `POST /api/ai/recommendations/{eventId}/accept`
   - Body: `{ "bookId": "uuid" }`
   - Nagłówek: `Idempotency-Key: <uuid>` (generuj w przeglądarce `crypto.randomUUID()`)
   - Odpowiedź: `200` + `{ event: { id, acceptedBookIds: [...] } }`
   - Błędy: `404` (event/book nieprawidłowy), `409` (już zaakceptowano z tym kluczem), `400` (book niepowiązany z eventem) → BannerAlert.

3) PRG redirect
   - Po sukcesie obu kroków: `window.location.assign('/books?addedFromAi=true&title=' + encodeURIComponent(title))`
   - Komunikat flash w `/books` (po stronie backend) lub z query param.

### Błędy provider AI 502/504
- Dotyczy etapu generowania (formularz), nie wyników. Na wynikach mogą pojawić się tylko błędy własnych endpointów (`/api/books`, `/api/ai/.../accept`). W razie otrzymania 5xx → BannerAlert globalny.

### CSRF
- Każdy POST do API – nagłówek `X-CSRF-TOKEN`. Pobieraj z meta `<meta name="csrf-token" content="...">` lub użyj istniejącego `csrf_protection_controller.js` (jeśli zapewnia automatyczne dodanie nagłówka do fetch).


## 8. Interakcje użytkownika
- „Akceptuj”:
  - Oczekiwany efekt: spinner „Przetwarzanie…”, przycisk nieaktywny → po sukcesie redirect do `/books` z komunikatem.
  - W razie błędu: odblokowanie przycisku, BannerAlert przy karcie z treścią błędu.
- „Odrzuć”:
  - Oczekiwany efekt: animacja wygaszenia i usunięcie karty bez API.
  - Gdy brak kart po odrzuceniu wszystkich – EmptyState + link do `/ai/recommendations`.
- Nawigacja klawiaturą:
  - Fokus po wejściu na `h1`.
  - Tab order: karty z przyciskami dostępne w naturalnej kolejności.
- Czytelność:
  - Krótkie uzasadnienie, wyraźne przyciski działań, tagi gatunków.


## 9. Warunki i walidacja
- `eventId` jest wymagany i liczbowy (>0). Brak → 404 UI + link powrotny.
- Dla każdej propozycji:
  - `title`, `author`: 1–255 znaków (walidacja backend; UI ufa danym z eventu).
  - `genresId`: 1–3 wartości (nieznane ID → etykieta zastępcza).
- Akceptacja:
  - Blokada przycisku podczas przetwarzania.
  - Nie ponawiać kroku 2 bez kroku 1.
  - Nagłówek `Idempotency-Key` w kroku 2.
- Visibility:
  - Jeżeli `acceptedBookIds` zawiera element odpowiadający karcie → karta oznaczona jako zaakceptowana lub ukryta.


## 10. Obsługa błędów
- Globalne (u góry):
  - `404` eventu → „Nie znaleziono zestawu rekomendacji” + link do formularza.
  - `5xx` → „Wystąpił błąd. Spróbuj ponownie później.”
- Lokalnie (na karcie):
  - `400` (walidacja `/api/books` lub niespójność z eventem): pokaż szczegół (np. „Nieprawidłowe dane książki”).
  - `404` (event/book): „Nie znaleziono zasobu – odśwież stronę.”
  - `409` (duplikat idempotencyjny): „Ta propozycja została już zaakceptowana.”
  - Sieć/timeout: „Brak połączenia – spróbuj ponownie.”
- Retry:
  - Przy błędach przejściowych pozwól użytkownikowi ponowić kliknięcie „Akceptuj”.
- Telemetria (opcjonalnie):
  - Loguj niekrytyczne błędy w konsoli lub przez logger JS (jeśli dostępny).


## 11. Kroki implementacji
1) Routing i szablon
   - Dodaj trasę `ai_recommendations_show` → `/ai/recommendations/{eventId}`.
   - Utwórz `templates/ai/recommendations_results.html.twig` z:
     - Nagłówkiem `h1#ai-results-title` z `tabindex="-1"`.
     - Kontenerem `data-controller="ai-recommendations-results"` i `data-event-id-value`.
     - Wstrzykniętymi danymi (opcjonalnie): `preloadedEventJson`, `genresCatalogJson`, `purchaseShelfId` (jako `data-*` lub `<script type="application/json">`).
     - Miejscem na `BannerAlert` (komponent istniejący).

2) Kontroler Stimulus
   - Dodaj plik `assets/controllers/ai_recommendations_results_controller.js`.
   - Implementuj stan z sekcji „Zarządzanie stanem” (ładowanie, event, katalog gatunków, shelf „Do zakupu”, akcje akceptacji i odrzucenia).
   - Zapewnij fokus na `#ai-results-title` po mount.
   - Zapewnij dodanie CSRF do POST (meta/istniejący kontroler).

3) Render kart
   - W Twig przygotuj markup kart (pętla po `recommended`, fallback gdy brak).
   - Mapowanie `genresId -> name` po stronie JS (na podstawie `genresCatalog`).
   - Dodaj klasy/stany CSS (np. `.is-accepting`, `.is-dismissed` z animacją fadeout).

4) Integracja API – akceptacja
   - Implementuj sekwencję: `POST /api/books` → `POST /api/ai/recommendations/{eventId}/accept` (z `Idempotency-Key`).
   - Obsłuż błędy 400/404/409 lokalnie w karcie, 5xx globalnie.
   - Po sukcesie: redirect do `/books` (PRG).

5) Katalog gatunków
   - Preferuj SSR wstrzyknięcie katalogu gatunków do HTML.
   - Jeśli brak: `GET /api/genres` na starcie i cache w stanie.

6) Regał „Do zakupu”
   - Preferuj SSR wstrzyknięcie `purchaseShelfId`.
   - Jeśli brak: `GET /api/shelves?includeSystem=true` i wyszukanie odpowiedniego regału.

7) A11y i UX
   - SpinnerButton z tekstem „Przetwarzanie…” i `aria-busy`.
   - Focus management: `h1` po wejściu; przeniesienie fokusa na baner przy krytycznym błędzie.
   - Przyciski z właściwymi etykietami i `aria-disabled` podczas akcji.

8) Style
   - Dodaj klasy CSS dla kart, tagów gatunków, stanów (`.is-accepting`, `.is-accepted`, `.is-dismissed`).
   - Zadbaj o responsywność (3→1 kolumna na mobile).

9) Testy E2E (Panther)
   - Scenariusz: wejście na `/ai/recommendations/{eventId}`, akceptacja jednej propozycji → redirect do `/books` z komunikatem.
   - Scenariusz błędu: `409` na akceptacji → brak redirectu, baner błędu.
   - Scenariusz odrzucenia wszystkich 3 kart → EmptyState z linkiem do formularza.

10) Twarde przypadki brzegowe
   - `acceptedBookIds` niepuste na starcie → odpowiednia karta zablokowana/oznaczona.
   - Brak zgodności `genresId` → etykiety zastępcze.
   - Brak `purchaseShelfId` w API → baner globalny i brak aktywacji przycisków „Akceptuj”.

11) Przegląd dostępności i copy
   - Przejrzyj treści banerów (PL), etykiet i komunikatów, dopasuj do PRD.


## Załącznik: wywołania API (przykłady)
### POST /api/books
Request:
```json
{
  "title": "Diuna",
  "author": "Frank Herbert",
  "genreIds": [5, 12],
  "shelfId": "f6697a8b-...",
  "source": "ai_recommendation",
  "recommendationId": 123
}
```
Response (201):
```json
{ "id": "0f7612bd-..." }
```

### POST /api/ai/recommendations/{eventId}/accept
Headers: `Idempotency-Key: 5c24e5b8-...`
Request:
```json
{ "bookId": "0f7612bd-..." }
```
Response (200):
```json
{ "event": { "id": 123, "acceptedBookIds": ["0f7612bd-..."] } }
```


