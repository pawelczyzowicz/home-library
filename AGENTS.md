# Repository Guidelines

## Critical constraints

- Never edit existing files in `migrations/` — always create new ones
- Never commit `die(`, `var_dump(`, `console.log(` — GrumPHP blacklist rejects them
- All PHP commands run as `www-data` inside container `home-library-backend`
- Pre-commit gate: `vendor/bin/grumphp run` (config: @grumphp.yml)

## Architecture

Symfony 7.3 monolith, **layered DDD** under `src/HomeLibrary/`:

```
Domain/           — Entities, Value Objects, Repository interfaces (no framework deps)
Application/      — Use cases, DTOs, Command/Query handlers
Infrastructure/   — Doctrine repositories, OpenRouter HTTP client
UI/Api/           — JSON controllers
UI/Web/           — Twig controllers (Turbo/Stimulus)
UI/Cli/           — Console commands
```

Domain modules: `AI`, `Book`, `Genre`, `Shelf`, `User`, `Common`.

## Key conventions

- **Namespace**: `App\HomeLibrary\{Layer}\{Module}`
- **DTOs/ViewModels** inside their module, excluded from DI container
- **Repository interfaces** in `Domain/{Module}/`; implementations in `Infrastructure/Persistence/`
- **No `Entity/` directory** — entities live in `Domain/{Module}/`
- **Commit messages**: conventional commits (`feat:`, `fix:`, `chore:`)

## Frontend

- Stimulus controllers naming: `{feature}_{action}_controller.js`

## External services

- **AI**: OpenRouter API (`OPENROUTER_API_KEY`). Config: `OPENROUTER_*` env vars in @config/services.yaml

## Testing

- Unit: pure PHP, mock repositories — `vendor/bin/phpunit tests/Unit`
- Integration: real DB (`_test` suffix), fixtures in `src/DataFixtures/` — `vendor/bin/phpunit tests/Integration`
- E2E: `bash ./docker/run-e2e.sh` (Panther + Chromedriver in `drivers/`)

## Docker

- Start: `bash ./docker/run-dev.sh` → `http://127.0.0.1:8080`
- Container: `home-library-backend`

## Reference files

- @.ai/prd.md — product requirements
- @.ai/tech-stack.md — stack decisions
- @grumphp.yml — pre-commit pipeline config
- @phpmd_ruleset.xml — custom PHPMD rules
- @config/packages/security.yaml — auth/firewall

