## HomeLibrary

[![PHP](https://img.shields.io/badge/PHP-8.2-777bb3?logo=php&labelColor=555)](https://www.php.net/releases/8.2/)
[![Symfony](https://img.shields.io/badge/Symfony-7.3-000000?logo=symfony&labelColor=555)](https://symfony.com/releases/7.3)
[![License](https://img.shields.io/badge/License-Proprietary-informational.svg)](#license)

### Spis treści
- [Project name](#homelibrary)
- [Project description](#project-description)
- [Tech stack](#tech-stack)
- [Getting started locally](#getting-started-locally)
- [E2E tests](#e2e-tests-docker--panther)
- [Available scripts](#available-scripts)
- [Project scope](#project-scope)
- [Project status](#project-status)
- [License](#license)

## Project description
HomeLibrary to aplikacja webowa do zarządzania domową kolekcją książek i odkrywania nowych pozycji z pomocą AI. System umożliwia:
- zarządzanie książkami (dodawanie, edycja, usuwanie, przenoszenie między regałami),
- system regałów z wbudowanym regałem specjalnym „Do zakupu”,
- predefiniowane gatunki (1–3 gatunki na książkę),
- widok listy/tabeli z wyszukiwaniem i filtrami (regał, gatunek),
- rekomendacje AI na podstawie podanych tytułów/autorów (3 propozycje, akceptacja/odrzucenie),
- współdzieloną bibliotekę dla wielu użytkowników jednego gospodarstwa domowego (bez ról),
- podstawowe analytics do pomiaru skuteczności rekomendacji AI.

Szczegółowe wymagania znajdują się w pliku `./.ai/prd.md`.

## Tech stack
- **Frontend**: Symfony
- **Backend**: Symfony 7.3 (m.in. `framework-bundle`, `security-bundle`, `validator`, `serializer`, `twig-bundle`, `ux-turbo`, `stimulus-bundle`, `asset-mapper`)
- **Baza danych**: Doctrine ORM 3.5 + Doctrine Migrations + PostgreSQL
- **AI**: integracja przez OpenRouter (dostęp do wielu modeli, limity kosztów)
- **Inne**: Monolog, HttpClient
- **Dev/QA**: Testy jednostkowe — PHPUnit; Testy integracyjne — PHPUnit + Doctrine ORM + Doctrine Migrations + PostgreSQL (osobna baza testowa); dodatkowo: PHP-CS-Fixer, PHPStan (+ rozszerzenia), PHPMD, Web Profiler, Maker Bundle
- **CI/CD**: GitHub Actions

Więcej informacji: `./.ai/tech-stack.md`.

## Getting started locally

### Szybki start (Docker — zalecane)
- Wymagania: Docker + Docker Compose plugin (polecenie `docker compose`)

1) Uruchom środowisko deweloperskie:
```bash
bash ./docker/run-dev.sh
```

2) Aplikacja będzie dostępna pod adresem:
```text
http://127.0.0.1:8080
```
Port można zmienić zmienną `DOCKER_NGINX_PORT` (patrz niżej).

3) Logi i zarządzanie usługami:
```bash
# logi wszystkich usług
docker compose logs -f

# zatrzymanie usług
docker compose stop

# zatrzymanie i usunięcie (wraz z wolumenami)
docker compose down -v
```

4) Konsola i testy wewnątrz kontenera backendu (`home-library-backend`):
```bash
# wejście do kontenera
docker exec -it home-library-backend bash

# wybrane polecenia (bezpośrednio, bez wchodzenia do środka)
docker exec --user www-data home-library-backend bin/console about
docker exec --user www-data home-library-backend vendor/bin/phpunit tests/Unit
docker exec --user www-data home-library-backend vendor/bin/phpunit tests/Integration
```

5) Zmienne środowiskowe dla Docker (ustaw w pliku `.env` w katalogu projektu):
```bash
# Porty i bazy (wartości domyślne)
DOCKER_NGINX_PORT=8080
DOCKER_POSTGRES_PORT=5433
DOCKER_POSTGRES_TEST_PORT=5434

# Dane dostępowe do DB (domyślne wartości używane w kontenerach)
DATABASE_USER=app
DATABASE_PASSWORD=app
DATABASE_NAME=app
DOCKER_POSTGRES_USER=${DATABASE_USER}
DOCKER_POSTGRES_PASSWORD=${DATABASE_PASSWORD}
DOCKER_POSTGRES_TEST_DB=app_test
```

Skrypt `docker/run-dev.sh` automatycznie:
- kopiuje `.env.dist` do `.env` (jeśli `.env` nie istnieje),
- buduje bazowy obraz `home-library:2.0` z `docker/etc/Dockerfile` (jeśli brak),
- tworzy sieć Docker `local-network` (jeśli brak),
- uruchamia `docker compose build` i `docker compose up -d`,
- wykonuje `composer install` oraz tworzy i migruje bazy `dev` i `test` w kontenerze `home-library-backend`.

## E2E tests (Docker + Panther)

Uruchamianie testów E2E wewnątrz kontenera `home-library-backend` z użyciem headless Chromium:

1) Zbuduj i uruchom środowisko (jeśli nie działa):
```bash
bash ./docker/run-dev.sh
```

2) Uruchom testy E2E:
```bash
docker compose run --rm home-library-backend bash -lc "bash docker/run-e2e.sh"
```

### API Auth (register/login/logout/me)

- `POST /api/auth/register` — rejestruje użytkownika, waliduje dane (CSRF token `authenticate`), automatycznie loguje i zwraca obiekt `user`.
- `POST /api/auth/login` — logowanie JSON (CSRF `authenticate`), w odpowiedzi zwraca `user`.
- `POST /api/auth/logout` — wylogowanie (CSRF `logout`), status 204.
- `GET /api/auth/me` — bieżący użytkownik (401 gdy brak sesji).

Przykład (curl):

```
# pobierz tokeny CSRF z meta tagów
curl -c cookies.txt http://127.0.0.1:8080/

# rejestracja
curl -b cookies.txt -c cookies.txt \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: <token-authenticate>" \
  -d '{"email":"user@example.com","password":"SecurePass1","passwordConfirm":"SecurePass1"}' \
  http://127.0.0.1:8080/api/auth/register

# bieżący użytkownik
curl -b cookies.txt http://127.0.0.1:8080/api/auth/me

# logout
curl -b cookies.txt -c cookies.txt \
  -H "X-CSRF-Token: <token-logout>" \
  -X POST http://127.0.0.1:8080/api/auth/logout
```

3) Debug (opcjonalnie): wyłącz headless
```bash
docker compose run --rm \
  -e PANTHER_NO_HEADLESS=1 \
  home-library-backend bash -lc "bash docker/run-e2e.sh"
```

Uwagi techniczne:
- Obraz `home-library:2.0` zawiera Chromium i wymagane biblioteki, a `docker-compose.yml` ustawia `PANTHER_NO_SANDBOX` oraz `PANTHER_CHROME_ARGUMENTS` i zwiększa `shm_size`.
- Skrypt `docker/run-e2e.sh`:
  - wykonuje `composer install` (dev),
  - pobiera sterownik przez `vendor/bin/bdi detect drivers`,
  - przygotowuje bazę testową (`doctrine:database:create`, `migrations:migrate`),
  - uruchamia testy: `--testsuite e2e` (fallback do `tests/E2E`).
- Domyślnie testy korzystają z wbudowanego serwera Symfony uruchamianego przez Panther. Zmienna `PANTHER_APP_ENV` jest ustawiana na `test` w skrypcie.
- Testy POST używają `createHttpBrowserClient()` dla zapytań innych niż GET.

Uwaga: `composer install` uruchamia automatycznie `cache:clear`, `assets:install` i `importmap:install` dzięki skryptom Composer.

## Available scripts

### Skrypty Composer (zdefiniowane)
- `post-install-cmd` → auto-scripts: `cache:clear`, `assets:install %PUBLIC_DIR%`, `importmap:install`
- `post-update-cmd` → auto-scripts: jw.

Przydatne komendy konsolowe:
```bash
# Baza i migracje
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate -n

# Cache i zasoby
php bin/console cache:clear
php bin/console assets:install --symlink

# Serwer dev (Symfony CLI)
symfony server:start -d
symfony server:stop
```

### Narzędzia deweloperskie
```bash
# Testy jednostkowe
vendor/bin/phpunit tests/Unit

# Formatowanie wg .php-cs-fixer.dist.php
vendor/bin/php-cs-fixer fix --diff

# Analiza statyczna (przykład – dostosuj poziom)
vendor/bin/phpstan analyse src --level=max

# PHPMD (przykład)
vendor/bin/phpmd src text cleancode,codesize,controversial,design,naming,unusedcode
```

## Project scope

### W zakresie (MVP)
- Książki: dodawanie, edycja, usuwanie, przenoszenie między regałami; walidacje (tytuł/autor 1–255, ISBN 10/13 cyfr, liczba stron 1–50000).
- Regały: tworzenie/edycja/usuwanie; specjalny regał systemowy „Do zakupu” (nieusuwalny, wizualnie wyróżniony).
- Gatunki: predefiniowana lista (10–15); 1–3 na książkę.
- Wyszukiwanie i filtrowanie: live search po tytule/autorze; filtry regału i gatunków; logiczne AND między różnymi filtrami.
- Rekomendacje AI: wejście tytuły/autorzy; 3 propozycje (tytuł, autor, uzasadnienie); akceptacja dodaje do „Do zakupu”; odrzucenie ukrywa bez zapisu.
- Użytkownicy: rejestracja, logowanie, wylogowanie; wspólna biblioteka bez ról.
- Analytics: eventy `ai_recommendation_generated`, `book_accepted` (PostgreSQL); metryka sukcesu MVP.

### Poza zakresem (MVP)
- Role użytkowników, wiele bibliotek, import danych z zewnętrznych API (Google Books, OpenLibrary, Goodreads), drag&drop, oceny/recenzje, automatyczne rekomendacje na podstawie całej biblioteki, monetyzacja/affiliate, zaawansowane dashboardy analityczne, aplikacje mobilne native.

Pełna specyfikacja: `./.ai/prd.md`.

## Project status
- Etap: MVP w toku (repo zawiera konfigurację Symfony 7.3 i zestaw zależności pod implementację wymagań z PRD).
- CI/CD: planowane pipeline’y GitHub Actions.

## License
- Licencja: proprietary (zgodnie z `composer.json`).
- Skontaktuj się z autorami projektu w sprawie praw/licencji przed użyciem lub dystrybucją.

Dokumentacja dodatkowa:
- PRD: `./.ai/prd.md`
- Stos technologiczny: `./.ai/tech-stack.md`


