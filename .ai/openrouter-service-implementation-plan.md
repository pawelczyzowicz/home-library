## Usługa OpenRouter – plan wdrożenia (Home Library)

### 1. Opis usługi
- **Cel**: Zastąpić `MockOpenRouterRecommendationProvider` realnym klientem OpenRouter, który generuje 3 propozycje książek na podstawie wejściowych tytułów/autorów i zwraca tablicę dokładnie trzech obiektów `RecommendationProposal` (z polami: `tempId`, `title`, `author`, `genresId[1..3]`, `reason`).
- **Kontekst**: Integracja w warstwie Infrastructure Symfony 7.3 z wykorzystaniem `symfony/http-client`. Klucz `OPENROUTER_API_KEY` jest dostępny w `.env`. Usługa będzie używana przez `AiRecommendationService`, który oczekuje implementacji `IRecommendationProvider::generate(array $inputs): array` i sam obsługuje zapisywanie eventu rekomendacji.
- **Wymagania domenowe**: 
  - Zwróć dokładnie 3 propozycje.
  - `genresId` to 1–3 unikalnych wartości z zakresu 1–15.
  - Wszystkie pola muszą być niepuste.


### 2. Opis konstruktora
Nowa klasa: `App\HomeLibrary\Infrastructure\AI\OpenRouterRecommendationProvider` implementująca `IRecommendationProvider`.

Zalecane zależności konstruktora (wstrzykiwane przez DI):
- `HttpClientInterface $httpClient`: Klient HTTP Symfony.
- `ListGenresHandler $listGenresHandler`: Dostarcza katalog gatunków `{ id, name }` do promptu.
- `string $apiKey`: Z `.env` → `OPENROUTER_API_KEY`.
- `string $baseUrl`: Domyślnie `https://openrouter.ai/api/v1`.
- `string $defaultModel`: Np. `openai/gpt-4o-mini` (konfigurowalne).
- `array $defaultParams`: Domyślne parametry modelu (np. `temperature`, `top_p`, `max_tokens`, opcjonalnie `seed`).
- `?string $appReferer`, `?string $appTitle`: Opcjonalne nagłówki rekomendowane przez OpenRouter.


### 3. Publiczne metody i pola
- `generate(array $inputs): array` (z `IRecommendationProvider`)
  - Waliduje/normalizuje wejście (powierzone `AiRecommendationService`), przygotowuje prompt i `response_format` (JSON Schema), wywołuje OpenRouter Chat Completions, mapuje odpowiedź na `RecommendationProposal[]`, generując własne stabilne `tempId`.
  - Zwraca: dokładnie 3 instancje `RecommendationProposal`.

Przykładowy szkielet implementacji (fragmenty kluczowe):

```php
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\HomeLibrary\Application\AI\IRecommendationProvider;
use App\HomeLibrary\Application\Genre\Query\ListGenresHandler;
use App\HomeLibrary\Domain\AI\RecommendationProposal;

final class OpenRouterRecommendationProvider implements IRecommendationProvider
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ListGenresHandler $listGenresHandler,
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $defaultModel,
        /** @var array<string, mixed> */
        private readonly array $defaultParams = [],
        private readonly ?string $appReferer = null,
        private readonly ?string $appTitle = null,
    ) {}

    public function generate(array $inputs): array
    {
        $genresCatalog = $this->fetchGenresCatalog(); // [[id, name]...]
        $responseFormat = $this->buildResponseFormatSchema();
        $messages = [
            ['role' => 'system', 'content' => $this->buildSystemMessage()],
            ['role' => 'user',   'content' => $this->buildUserMessage($inputs, $genresCatalog)],
        ];

        $payload = array_filter([
            'model' => $this->defaultModel,
            'messages' => $messages,
            'response_format' => $responseFormat,
        ] + $this->defaultParams);

        $data = $this->callOpenRouter($payload);
        $parsed = $this->extractParsed($data);

        $recommendations = $parsed['recommendations'] ?? null;
        $this->assertThree($recommendations);

        $proposals = [];
        foreach (array_values($recommendations) as $i => $item) {
            $title = (string)($item['title'] ?? '');
            $author = (string)($item['author'] ?? '');
            $genres = $this->coerceGenresIds($item['genresId'] ?? []);
            $reason = (string)($item['reason'] ?? '');

            $proposals[] = new RecommendationProposal(
                $this->makeTempId($inputs, $i),
                $title,
                $author,
                $genres,
                $this->adaptReason($reason, $inputs)
            );
        }

        return $proposals;
    }

    // ... metody prywatne opisane niżej ...
}
```


### 4. Prywatne metody i pola
Poniżej sugerowany zestaw metod prywatnych wraz z ich zakresem obowiązków, potencjalnymi wyzwaniami i strategiami radzenia sobie z nimi:

1) `fetchGenresCatalog(): array`
   - **Funkcjonalność**: Pobiera katalog gatunków (tablica `{ id: int, name: string }`) przez `ListGenresHandler`.

2) `buildSystemMessage(): string`
   - **Funkcjonalność**: Twarde zasady: zwróć 3 polecenia, każde z `title`, `author`, `genresId[1..3]`, `reason`. Dopasuj `genresId` do katalogu (1..15). Maks. długość `reason` np. 280 znaków.
   - **Wyzwania**:
     1. Dryf promptu/prompt injection.
     2. Przekroczenie długości reason.
   - **Rozwiązania**:
     1. Krótki, nakazowy ton + `response_format.strict=true`.
     2. Wprost wymusić limit w system prompt i w schemacie (maxLength).

3) `buildUserMessage(array $inputs, array $genresCatalog): string`
   - **Funkcjonalność**: Umieszcza wejściowe tytuły/autorów i katalog gatunków (id, name) jako kontekst.

4) `buildResponseFormatSchema(): array`
   - **Funkcjonalność**: Zwraca obiekt `response_format` dla OpenRouter w trybie JSON Schema.
   - **Wyzwania**:
     1. Model bez wsparcia `json_schema`.
   - **Rozwiązania**:
     1. W planie obsługi błędów przewidzieć fallback do „plain JSON” (patrz sekcja 5).

5) `callOpenRouter(array $payload): array`
   - **Funkcjonalność**: Wysyła żądanie `POST {baseUrl}/chat/completions` z nagłówkami i kluczem API, zwraca zdekodowane JSON.
   - **Wyzwania**:
     1. 4xx/5xx, 429, timeouty, błędy sieci.
   - **Rozwiązania**:
     1. Retry z backoff na `429/5xx` (ograniczony), sensowne time‑outy, mapowanie wyjątków (sekcja 5).

6) `extractParsed(array $data): array`
   - **Funkcjonalność**: Pobiera wynik z `choices[0].message.parsed` (jeśli istnieje), wpp. dekoduje `choices[0].message.content` jako JSON.
   - **Wyzwania**:
     1. Nietrywialny format odpowiedzi.
   - **Rozwiązania**:
     1. Dwustopniowy parser: najpierw `parsed`, potem `content`→`json_decode` z walidacją kluczy.

7) `coerceGenresIds(mixed $value): array`
   - **Funkcjonalność**: Rzutuje elementy do int, wymusza zakres 1..15, unikalność, 1–3 elementy.

8) `makeTempId(array $inputs, int $index): string`
   - **Funkcjonalność**: Stabilny tempId (np. na bazie CRC32/seed + index): `or-<seed>-<1..3>`.

9) `adaptReason(string $reason, array $inputs): string`
   - **Funkcjonalność**: Opcjonalne dopasowanie stylistyczne (np. „Inspired by "X".”), zgodnie z zachowaniem mocka.


### Elementy OpenRouter – konfiguracja i przykłady
Poniższe elementy należy uwzględnić w implementacji i konfiguracji żądania:

1) **Komunikat systemowy (system message) – przykład**

```text
Jesteś asystentem literackim. Zwróć dokładnie 3 rekomendacje książek jako JSON 
zgodny ze schematem. Każda pozycja musi mieć: title, author, genresId (1–3 id z zakresu 1–15), 
reason (maks. 280 znaków). Dobieraj genresId na podstawie katalogu gatunków (id → name) 
przekazanego w wiadomości użytkownika. Nie dodawaj pól spoza schematu. Nie tłumacz, nie komentuj.
```

2) **Komunikat użytkownika (user message) – przykład**

```text
Wejściowe tytuły/autorzy:
- "Dune" Frank Herbert
- "The Hobbit" J.R.R. Tolkien

Katalog gatunków (id → name):
1: kryminał, 2: fantasy, 3: sensacja, 4: romans, 5: sci-fi, 6: horror, 7: biografia, 8: historia,
9: popularnonaukowa, 10: literatura piękna, 11: religia, 12: thriller, 13: dramat, 14: poezja, 15. komiks

Zwróć 3 propozycje dopasowane do powyższych preferencji.
```

3) **Ustrukturyzowane odpowiedzi – `response_format` (JSON Schema) – przykład**

```json
{
  "type": "json_schema",
  "json_schema": {
    "name": "ai_recommendations",
    "strict": true,
    "schema": {
      "type": "object",
      "additionalProperties": false,
      "required": ["recommendations"],
      "properties": {
        "recommendations": {
          "type": "array",
          "minItems": 3,
          "maxItems": 3,
          "items": {
            "type": "object",
            "additionalProperties": false,
            "required": ["title", "author", "genresId", "reason"],
            "properties": {
              "title": { "type": "string", "minLength": 1 },
              "author": { "type": "string", "minLength": 1 },
              "genresId": {
                "type": "array",
                "minItems": 1,
                "maxItems": 3,
                "uniqueItems": true,
                "items": { "type": "integer", "minimum": 1, "maximum": 15 }
              },
              "reason": { "type": "string", "minLength": 1, "maxLength": 280 }
            }
          }
        }
      }
    }
  }
}
```

4) **Nazwa modelu – przykłady**
- Domyślnie: `openai/gpt-4o-mini` (niski koszt, dobre wsparcie JSON).
- Alternatywa: `anthropic/claude-3.5-sonnet` (świetne rozumowanie; w razie problemów z `json_schema` włączyć fallback).

5) **Parametry modelu – przykłady**

```json
{
  "temperature": 0.6,
  "top_p": 0.95,
  "max_tokens": 600,
  "seed": 123456789
}
```

Uwaga: `seed` można deterministycznie wyliczyć z wejścia (np. CRC32 JSON‑a), aby zbliżyć zachowanie do mocka.


### Wywołanie OpenRouter – przykład (PHP)

```php
$headers = array_filter([
    'Authorization' => 'Bearer ' . $this->apiKey,
    'Content-Type'  => 'application/json',
    'HTTP-Referer'  => $this->appReferer,
    'X-Title'       => $this->appTitle,
]);

$response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/chat/completions', [
    'headers' => $headers,
    'json'    => $payload,
    'timeout' => $this->defaultParams['timeout'] ?? 20,
]);

$data = $response->toArray(false); // nie rzucaj automatycznie
// Oczekiwane pola: choices[0].message.parsed lub choices[0].message.content (JSON)
```


### 5. Obsługa błędów
Potencjalne scenariusze i reakcje warstwy provider:
1) **401/403 (brak/niepoprawny klucz)**: Rzuć `\RuntimeException('Unauthorized')`; serwis aplikacyjny opakuje w `RecommendationProviderException`.
2) **429 (rate limit)**: Jednorazowy retry z opóźnieniem (np. 500–800 ms). Jeśli ponownie 429 → wyjątek.
3) **5xx (błąd serwera)**: Ograniczony retry (np. do 2 prób). Log ostrzegawczy z korelacją.
4) **Timeout/Network**: Przerwij i rzuć wyjątek z czytelnym komunikatem; nie ponawiaj bez kontroli.
5) **Model nie wspiera `json_schema`**: Fallback: usuń `response_format`, doprecyzuj „ZWRÓĆ WYŁĄCZNIE JSON” w system prompt, spróbuj ponownie jedną próbę.
6) **Nieparsowalny JSON / brak pól**: Waliduj i rzuć wyjątek. Nie próbuj heurystyk ponad jedną próbę (aby uniknąć halucynacji).
7) **Niespełnienie reguł domeny**: Jeśli `recommendations !== 3`, `genresId` poza zakresem lub puste pola → wyjątek.
8) **Duży rozmiar promptu**: Skracaj katalog gatunków do `{id, name}`, tnij nazwy > 100 znaków, ogranicz liczbę wejściowych tytułów do sensownego limitu 3.


### 6. Kwestie bezpieczeństwa
- **Tajne**: Nie logować `OPENROUTER_API_KEY`. Klucz tylko w env.
- **PII**: Minimalizować dane użytkownika w promptach; anonimizować, gdy to możliwe.
- **Rate limiting**: Szanuj 429, wprowadź backoff i ew. budżet zapytań.
- **Validation**: Walidować każde pole po stronie serwera (w tym zakresy `genresId`).
- **Deterministyczność**: Opcjonalny `seed` z wejścia – przewidywalne wyniki, lepsze debugowanie.
- **Observability**: Korrelacja requestów (np. UUID w logach); nie logować treści odpowiedzi, jedynie metadane.
- **Circuit breaker**: Przy dłuższych awariach – wyłącznik i powrót do mocka/komunikat UI.


### 7. Plan wdrożenia krok po kroku
1) **Dodaj nową klasę providera**
   - Plik: `src/HomeLibrary/Infrastructure/AI/OpenRouterRecommendationProvider.php`.
   - Implementuj `IRecommendationProvider` zgodnie ze szkieletem powyżej i metodami prywatnymi z sekcji 4.

2) **Konfiguracja DI (`config/services.yaml`)**

```yaml
services:
  App\HomeLibrary\Application\AI\IRecommendationProvider:
    alias: App\HomeLibrary\Infrastructure\AI\OpenRouterRecommendationProvider

  App\HomeLibrary\Infrastructure\AI\OpenRouterRecommendationProvider:
    arguments:
      $httpClient: '@http_client'
      $listGenresHandler: '@App\HomeLibrary\Application\Genre\Query\ListGenresHandler'
      $apiKey: '%env(OPENROUTER_API_KEY)%'
      $baseUrl: '%env(default::OPENROUTER_BASE_URL)%'
      $defaultModel: '%env(string:OPENROUTER_MODEL)%'
      $defaultParams:
        temperature: '%env(float:OPENROUTER_TEMPERATURE)%'
        top_p: '%env(float:OPENROUTER_TOP_P)%'
        max_tokens: '%env(int:OPENROUTER_MAX_TOKENS)%'
        seed: '%env(int:OPENROUTER_SEED)%' # opcjonalnie
        timeout: '%env(int:OPENROUTER_TIMEOUT)%'
      $appReferer: '%env(default::OPENROUTER_REFERER)%'
      $appTitle: '%env(default::OPENROUTER_TITLE)%'
```

3) **Zmienne środowiskowe (`.env`, `.env.dev`, `.env.test`)**

```dotenv
# już istnieje
OPENROUTER_API_KEY=...

# zalecane
OPENROUTER_BASE_URL=https://openrouter.ai/api/v1
OPENROUTER_MODEL=openai/gpt-4o-mini
OPENROUTER_TEMPERATURE=0.6
OPENROUTER_TOP_P=0.95
OPENROUTER_MAX_TOKENS=600
OPENROUTER_TIMEOUT=20
# deterministyczność – ustaw puste by wyłączyć
OPENROUTER_SEED=

# nagłówki (opcjonalne, ale rekomendowane przez OpenRouter)
OPENROUTER_REFERER=http://localhost
OPENROUTER_TITLE=Home Library
```

4) **HTTP Client – time‑outy (opcjonalnie)**
   - W `framework.http_client` możesz ustawić domyślne `timeout`, ewentualnie osobny scentralizowany klient do OpenRouter.

5) **Implementacja logiki**
   - `buildSystemMessage`, `buildUserMessage`, `buildResponseFormatSchema` dokładnie wg przykładów (sekcja „Elementy OpenRouter”).
   - `callOpenRouter` – obsłuż nagłówki `Authorization`, `HTTP-Referer`, `X-Title`.
   - `extractParsed` – preferuj `message.parsed`, fallback do JSON w `message.content`.
   - Walidacja i mapowanie do `RecommendationProposal` – zgodnie z domeną.

6) **Testy**
   - Test jednostkowy providera: stub `HttpClientInterface`, zwróć kontrolowane `choices[0].message.parsed` i sprawdź:
     - dokładnie 3 elementy,
     - `genresId` w 1..15, unikalne, 1–3 szt.,
     - brak pustych pól, maks. 280 znaków dla `reason`,
     - stabilne `tempId` z `seed`.
   - Testi integracyjne/e2e: ścieżka UI „Generuj rekomendacje” i render 3 kart.

7) **Konfiguracja modelu i fallback**
   - Zacznij od `openai/gpt-4o-mini`. Jeśli model nie wspiera `json_schema`, włącz fallback bez `response_format` z twardymi instrukcjami „ZWRÓĆ WYŁĄCZNIE JSON”.

8) **Gotowość produkcyjna**
   - Zweryfikuj brak logowania sekretów.
   - Skontroluj time‑outy, retry, backoff i komunikaty UI dla błędów.
   - Upewnij się, że `AiRecommendationService` rejestruje event zgodnie z PRD.


### Załącznik: definicje pomocnicze (przykłady prywatnych metod)

```php
private function buildResponseFormatSchema(): array
{
    return [
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'ai_recommendations',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['recommendations'],
                'properties' => [
                    'recommendations' => [
                        'type' => 'array',
                        'minItems' => 3,
                        'maxItems' => 3,
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['title', 'author', 'genresId', 'reason'],
                            'properties' => [
                                'title' => ['type' => 'string', 'minLength' => 1],
                                'author' => ['type' => 'string', 'minLength' => 1],
                                'genresId' => [
                                    'type' => 'array',
                                    'minItems' => 1,
                                    'maxItems' => 3,
                                    'uniqueItems' => true,
                                    'items' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 15],
                                ],
                                'reason' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 280],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

private function buildSystemMessage(): string
{
    return <<<TXT
Jesteś asystentem literackim. Zwróć dokładnie 3 rekomendacje książek jako JSON 
zgodny z dostarczonym schematem. Każda rekomendacja: title, author, genresId (1–3 wartości 1..15), 
reason (<= 280 znaków). genresId dobierz względem katalogu (id → name). 
Nie dodawaj pól poza schematem. Nie tłumacz i nie komentuj.
TXT;
}

private function buildUserMessage(array $inputs, array $genresCatalog): string
{
    $lines = [];
    $lines[] = "Wejściowe tytuły/autorzy:";
    foreach ($inputs as $in) {
        $lines[] = "- " . $in;
    }
    $lines[] = "";
    $lines[] = "Katalog gatunków (id → name):";
    $pairs = array_map(fn ($g) => sprintf("%d: %s", $g['id'], $g['name']), $genresCatalog);
    $lines[] = implode(", ", $pairs);
    $lines[] = "";
    $lines[] = "Zwróć 3 propozycje dopasowane do powyższych preferencji.";

    return implode("\n", $lines);
}
```


— Koniec —


