<implementation_approach>
Realizuj maksymalnie 3 kroki planu implementacji, podsumuj krótko co zrobiłeś i opisz plan na 3 kolejne działania - zatrzymaj w tym momencie pracę i czekaj na mój feedback.
</implementation_approach>

### Cel
Uruchamianie lokalnych testów E2E (Symfony Panther) wewnątrz Dockera na istniejącym obrazie z `docker/`, bez modyfikowania logiki aplikacji.

### Architektura (proponowana – prosta i stabilna)
- Uruchamiamy Panther w kontenerze aplikacji (PHP), korzystając z headless Chromium.
- Sterownik Chrome pobieramy automatycznie przez bdi w czasie uruchamiania testów.
- Alternatywa (opcjonalnie): osobny kontener z Selenium Standalone Chrome i zdalny WebDriver.

Wykonaj następujące kroki
### Krok 1: Zależności PHP (dev)
Uruchom na hoście lub w kontenerze PHP:
```bash
composer require --dev symfony/panther dbrekelmans/bdi
# (opcjonalnie) dane testowe
composer require --dev doctrine/doctrine-fixtures-bundle
```

### Krok 2: Dockerfile – dodanie Chromium i bibliotek
Edytuj `docker/etc/Dockerfile` tak, by:
- doinstalować Chromium i wymagane biblioteki,
- ustawić zmienne dla Panther (no-sandbox, args),
- (opcjonalnie) wskazać binarkę przeglądarki.

Przykładowe dopiski (dopasuj do bazowego obrazu):
```Dockerfile
# --- E2E: Chromium + zależności
RUN apt-get update && apt-get install -y --no-install-recommends \
    chromium \
    ca-certificates \
    fonts-liberation \
    libasound2 \
    libatk-bridge2.0-0 \
    libatk1.0-0 \
    libcups2 \
    libdrm2 \
    libgbm1 \
    libgtk-3-0 \
    libnspr4 \
    libnss3 \
    libx11-xcb1 \
    libxcomposite1 \
    libxdamage1 \
    libxfixes3 \
    libxkbcommon0 \
    libxrandr2 \
    wget unzip xdg-utils \
 && rm -rf /var/lib/apt/lists/*

# Panther: bez sandboxa i z ograniczonym /dev/shm
ENV PANTHER_NO_SANDBOX=1
ENV PANTHER_CHROME_ARGUMENTS="--no-sandbox --disable-dev-shm-usage"
# (opcjonalnie, jeśli Chromium nie jest w PATH lub ma inną nazwę)
ENV PANTHER_CHROME_BINARY=/usr/bin/chromium
```

### Krok 3: docker-compose – zasoby dla Chrome
W `docker-compose.yml`:
- powiększ pamięć współdzieloną (Chrome bywa wrażliwy),
- upewnij się, że testy odpalasz w `APP_ENV=test`.

Przykład (w sekcji serwisu PHP):
```yaml
services:
  php:
    shm_size: "2gb"
    environment:
      APP_ENV: test
      # (opcjonalnie) PANTHER_CHROME_BINARY: /usr/bin/chromium
      PANTHER_NO_SANDBOX: "1"
      PANTHER_CHROME_ARGUMENTS: "--no-sandbox --disable-dev-shm-usage"
```

### Krok 4: Konfiguracja środowiska testowego Symfony
- Zapewnij `.env.test` lub zmienne środowiskowe dla `APP_ENV=test`.
- Przygotuj bazę testową (wewnątrz kontenera), jest gotowy kontener home-library-postgres-test na potrzeby testowej bazy postgres:
```bash
php bin/console doctrine:database:create --env=test --if-not-exists
php bin/console doctrine:migrations:migrate --env=test -n
# (opcjonalnie) zasilenie danymi:
php bin/console doctrine:fixtures:load --env=test -n
```

### Krok 5: Struktura i konfiguracja testów
- Umówmy się na katalog `tests/E2E`.
- (Opcjonalnie) w `phpunit.xml(.dist)` dodaj osobny testsuite:
```xml
<testsuite name="e2e">
  <directory>tests/E2E</directory>
</testsuite>
```
- Minimalny test „smoke” (przykład):
```php
<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Component\Panther\PantherTestCase;

final class HomePageTest extends PantherTestCase
{
    public function testHomePageLoads(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/');

        $this->assertPageTitleNotContains('Error');
        $this->assertSelectorExists('body');
    }
}
```

### Krok 6: Skrypt uruchamiający testy w kontenerze
Dodaj skrypt `docker/run-e2e.sh` i nadaj mu prawa wykonania:
```bash
#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html/home-library

# Instalacja vendorów i drivera
composer install --no-interaction --prefer-dist
vendor/bin/bdi detect drivers

# Przygotowanie DB testowej
php bin/console doctrine:database:create --env=test --if-not-exists
php bin/console doctrine:migrations:migrate --env=test -n
# php bin/console doctrine:fixtures:load --env=test -n  # opcjonalnie

# Uruchomienie testów E2E
APP_ENV=test vendor/bin/phpunit --testsuite e2e || vendor/bin/phpunit tests/E2E
```

Uruchomienie na hoście:
```bash
docker compose run --rm php bash -lc "bash docker/run-e2e.sh"
```

### Krok 7: Debug i tryb nie-headless
Aby zobaczyć okno przeglądarki:
```bash
docker compose run --rm -e PANTHER_NO_HEADLESS=1 php bash -lc "bash docker/run-e2e.sh"
```
Jeśli GUI nie jest możliwe w kontenerze, pozostań przy headless i korzystaj z `sleep()`/logów.

### Krok 8: Alternatywa – Selenium jako osobny serwis (opcjonalnie)
Jeśli wolisz zdalny WebDriver:
```yaml
services:
  selenium:
    image: selenium/standalone-chrome:latest
    shm_size: "2gb"
    healthcheck:
      test: ["CMD", "bash", "-lc", "curl -s http://localhost:4444/status | jq -e '.value.ready == true'"]
      interval: 10s
      timeout: 5s
      retries: 10

  php:
    environment:
      APP_ENV: test
      PANTHER_NO_CLIENT: "1"
      PANTHER_REMOTE_DRIVER_DSN: "http://selenium:4444/wd/hub"
      # Jeśli nie używasz wbudowanego serwera Symfony:
      # PANTHER_EXTERNAL_BASE_URI: "http://nginx"  # gdy masz serwis nginx w compose
```
- W tym wariancie możesz kierować żądania na istniejący serwis `nginx` przez `PANTHER_EXTERNAL_BASE_URI`, albo zostawić serwer wbudowany Symfony (wtedy nie ustawiaj `PANTHER_EXTERNAL_BASE_URI`).

### Krok 9: Typowe problemy i szybkie fixy
- Niezgodny Chrome/driver: uruchom `vendor/bin/bdi detect drivers` w kontenerze przed testami.
- Crashe Chrome: zwiększ `shm_size`, użyj `--disable-dev-shm-usage`, pozostaw headless.
- Brak Chromium: upewnij się, że pakiet `chromium` jest w obrazie, a `PANTHER_CHROME_BINARY` wskazuje właściwą ścieżkę.
- Problemy z DB: migracje/fixtures uruchamiaj w `--env=test` na osobnej bazie.

### Krok 10: Integracja z CI (na później)
- W jobie CI uruchom analogiczny skrypt; doinstaluj Chromium i uruchom `bdi` albo użyj kontenera Selenium.

- W tym planie masz komplet kroków: zależności, zmiany w `docker/etc/Dockerfile`, parametry w `docker-compose.yml`, skrypt `docker/run-e2e.sh`, przykładowy test i opcję z Selenium. Po wdrożeniu tych elementów testy E2E powinny działać w Dockerze i być łatwe do uruchamiania jednym poleceniem.