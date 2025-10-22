## Plan testów dla projektu Home Library (Symfony + Twig + Doctrine + PostgreSQL)

### 1. Wprowadzenie i cele testowania
- **Cel ogólny**: Zapewnienie stabilności, poprawności i wydajności aplikacji „Home Library” w architekturze warstwowej (Domain, Application, UI/Infrastructure), z naciskiem na funkcjonalności półek (shelves), przepływy HTTP, warstwę domenową i integrację z bazą danych (PostgreSQL).
- **Cele szczegółowe**:
  - **Poprawność domeny**: weryfikacja reguł i niezmienników domenowych.
  - **Spójność aplikacyjna**: poprawna orkiestracja przypadków użycia i dostęp do repozytoriów.
  - **Poprawność UI/HTTP**: routing, kontrolery, CSRF, walidacje, szablony Twig, zachowanie Turbo/Stimulus.
  - **Niezawodność danych**: zgodność encji i migracji z oczekiwaną strukturą bazy.
  - **Jakość i regresja**: testy zautomatyzowane w CI, z bramkami jakości (PHPStan, PHP-CS-Fixer, GrumPHP).

### 2. Zakres testów
- **Warstwa Domain (`src/HomeLibrary/Domain/`)**: encje, wartości, serwisy domenowe, niezmienniki, logika biznesowa.
- **Warstwa Application (`src/HomeLibrary/Application/`)**: przypadki użycia, orkiestracja logiki domenowej, transakcje.
- **Warstwa UI (`src/HomeLibrary/UI/`, `src/Controller/`, `templates/`)**: kontrolery HTTP, routing, widoki Twig, CSRF, walidacje, pliki `templates/shelves/*`.
- **Warstwa Infrastructure (`src/HomeLibrary/Infrastructure/`, `src/Repository/`)**: repozytoria Doctrine, konfiguracja połączeń, dostęp do DB.
- **Frontend (`assets/`)**: kontrolery Stimulus (np. `shelves_controller.js`, `csrf_protection_controller.js`), integracje Turbo.
- **Migracje (`migrations/`)**: zgodność schematu, migracje w górę/w dół.
- **Konfiguracja (`config/`)**: bezpieczeństwo, routing, cache, translator, profiler.
- **API (jeśli używane: `src/Controller/Api/`, `src/Http/Api/`)**: kontrakty, walidacje, statusy HTTP.
- **Integracje zewnętrzne (AI/Openrouter)**: kontrakty usług, odporność na błędy, limity kosztów/stawek.

### 3. Typy testów do przeprowadzenia
- **Testy jednostkowe (PHPUnit)**:
  - Domain: niezmienniki, walidacje, zdarzenia, wartości.
  - Application: logika przypadków użycia, scenariusze sukcesu/błędu z repozytoriami „stub/fake”.
- **Testy integracyjne (PHPUnit + Doctrine)**:
  - Repozytoria Doctrine z PostgreSQL (lub SQLite in-memory, jeśli zgodne).
  - Migracje: poprawność schematu, migracje up/down na czystej bazie.
  - Integracje z konfiguracją Symfony (np. walidator, translator).
- **Testy API (jeśli wystawione)**:
  - Kontrakty JSON, walidacje, statusy, błędy, paginacja/filtry.
- **Testy E2E (Symfony Panther)**:
  - Zachowanie Turbo/Stimulus (nawigacja bez przeładowania, interakcje formularzy).
- **Testy jakości statycznej**:
  - PHPStan, PHP-CS-Fixer, PHPMD, GrumPHP bramki w CI.

### 4. Scenariusze testowe dla kluczowych funkcjonalności
- **Domena – półki (Shelves)**
  - Utworzenie półki z poprawnymi danymi: tworzy obiekt w stanie ważnym.
  - Próba utworzenia z niepoprawnymi danymi: rzucenie konkretnego wyjątku domenowego (np. puste nazwy).
  - Zmiana nazwy/atrybutów: reguły walidacyjne, brak naruszenia niezmienników.
  - Unikalność (jeśli dotyczy): odrzucenie duplikatów zgodnie z regułami domeny.
- **Application – przypadki użycia**
  - createShelf: sprawdzenie walidacji wejścia, zapis do repozytorium, zwrot ID.
  - updateShelf: aktualizacja istniejącego rekordu, błąd dla nieistniejącego ID.
  - deleteShelf: usunięcie istniejącej półki, idempotencja lub błąd (zgodnie z wymaganiami).
  - listShelves: sortowanie, paginacja/filtry (jeśli istnieją).
- **Repozytoria/Migracje**
  - save/findById/findAll: poprawny mapping pól, transakcje (jeśli stosowane).
  - Migracje: migracja w górę na czystej bazie, migracja w dół bez utraty krytycznych danych (o ile przewidziane).
- **HTTP/UI – kontrolery i szablony**
  - GET /shelves: 200 OK, render poprawnego widoku, elementy UI obecne (np. lista, formularz).
  - POST /shelves (create): poprawny CSRF, redirect po sukcesie, komunikat flash, rekord w DB.
  - Walidacje formularzy: komunikaty błędów, zachowanie danych.
  - Edycja/Usuwanie: poprawne trasy, autoryzacja (jeśli istnieje).
  - i18n: render tłumaczeń z `translations/` (przynajmniej sanity-check na kluczach).
- **Frontend – Stimulus/Turbo**
  - Akcje kontrolerów (np. `shelves_controller.js`): działanie handlerów zdarzeń (klik, submit).
  - CSRF meta/param: obecność i przesyłanie tokenów (np. `csrf_protection_controller.js`) w żądaniach.
  - Nawigacja Turbo: częściowe przeładowania, zachowanie historii (sanity E2E).
- **API (jeśli wystawione)**
  - Kontrakty: poprawne statusy (200/201/400/404/422), format błędów, pola wymagane.
  - Negatywne scenariusze: brak pól, niepoprawne typy, CSRF (jeśli dotyczy).
- **Bezpieczeństwo**
  - Dostęp do stron chronionych bez logowania: redirect/403.
  - Brak tokenu CSRF: odrzucenie żądania modyfikującego stan.
  - Nagłówki bezpieczeństwa przez Nginx/PHP (sanity check – środowisko dockerowe).
- **Integracje zewnętrzne – AI (Openrouter) (jeśli zaimplementowane)**
  - Mock HTTP: poprawne żądanie (nagłówki, klucze), obsługa limitów i błędów 4xx/5xx.
  - Ochrona kosztów: respektowanie limitów finansowych, bez wycieków danych w logach.

### 5. Środowisko testowe
- **Konfiguracje**:
  - `APP_ENV=test`, osobna baza (PostgreSQL) dla testów.
  - Automatyczne uruchamianie migracji dla środowiska testowego.
- **Baza danych**:
  - Dedykowana baza testowa; alternatywnie SQLite in-memory dla unit/integration bez różnic typów.
- **Kontenery/Docker**:
  - Testy odpalane w kontenerze PHP FPM z zainstalowanymi zależnościami `vendor/`.
  - Usługa DB w `docker-compose.yml` dla testów.
- **Dane testowe**:
  - Fabryki/test builders dla domeny (bez ciężkich fixtur).
  - Fixtury DB tylko dla integracyjnych/functional (małe, deterministyczne).
- **Logi/Profiler**:
  - Włączony profiler w testach funkcjonalnych (jeśli potrzebny), logi w `var/log/test.log`.

### 6. Narzędzia do testowania
- **PHPUnit**: testy unit/integration/functional, raporty JUnit/XML.
- **Doctrine**: testy repozytoriów, migracji.
- **E2E**: Symfony Panther.
- **Statyczna analiza/jakość**: PHPStan, PHP-CS-Fixer, PHPMD, GrumPHP (hooki pre-commit).

### 7. Kryteria akceptacji testów
- **Pokrycie**:
  - Domain ≥ 85%, Application ≥ 80%, całość ≥ 70% (bez wymuszania na UI/Twig).
- **Stabilność**:
  - 100% zielonych testów na gałęzi głównej.
  - Brak niestabilnych (flaky) testów przez 3 kolejne pipeline’y.
- **Jakość**:
  - PHPStan poziom zgodny z konfiguracją repo (bez błędów).
  - PHP-CS-Fixer/PHPMD bez krytycznych naruszeń.
- **Bezpieczeństwo/zgodność**:
  - CSRF pokryte testami funkcjonalnymi.
  - Migracje stosują się na czystej bazie bez błędów.
- **API (jeśli dotyczy)**:
  - Zdefiniowane i przetestowane kontrakty (statusy, schemat odpowiedzi, błędy).

### 8. Procedury raportowania błędów
- **Zgłoszenie**:
  - Tytuł, środowisko, kroki reprodukcji, oczekiwany vs. rzeczywisty rezultat, logi (`var/log/test.log`), zrzuty ekranu (UI), payloady API.
- **Klasyfikacja**:
  - Krytyczność (Critical/High/Medium/Low), komponent (Domain/Application/UI/Infra/API), typ (regresja/nowa).
- **Obsługa**:
  - Triaging w 24h, przypisanie do właściciela komponentu.
  - Wymóg dodania testu regresyjnego przed zamknięciem.
- **Śledzenie**:
  - Integracja z CI: link do nieudanych jobów i raportów JUnit.
  - Przegląd cykliczny błędów powracających i refaktoryzacja źródeł.

### Załączniki i praktyczne wskazówki
- **Konfiguracja test DB**: osobny DSN dla `APP_ENV=test`; automatyczne `doctrine:migrations:migrate --env=test` przed zestawem integracyjnym.
- **Fabryki danych**: buildery PHP dla encji domenowych zamiast ciężkich fixtur; fixtury tylko gdy konieczna zgodność DB.
- **Szybkość**: izolować testy jednostkowe od DB; testy integracyjne uruchamiać równolegle (jeśli możliwe) na niezależnych schematach.
- **Stabilność UI**: minimalizować testy E2E do scenariuszy krytycznych (happy path + 1 negatywny), resztę pokrywać funkcjonalnymi.