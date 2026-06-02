---
project: home-library
checked_at: 2026-05-31T00:00:00Z
overall_health: good
---

## Health Check Summary

| Area | Status | Details |
|------|--------|---------|
| Security audit | ✅ OK | `composer audit` — no advisories found (verbose deprecation warnings from system Composer on PHP 8.4 are cosmetic) |
| Dependencies | ✅ Current | Symfony 7.3.* pinned, Doctrine ORM ^3.5.2, PHPUnit ^11.5.42 — all recent |
| CI pipeline | ✅ Configured | GitHub Actions `pull-request.yml` — build, static analysis (GrumPHP: CS Fixer + PHPMD + PHPStan), unit tests |
| Code quality | ✅ Enforced | GrumPHP pre-commit: phpcsfixer, phpmd, phpstan, yaml_lint, composer |
| Test suites | ✅ Structured | Unit, Integration, E2E (Panther) |
| .gitignore | ✅ Configured | `.idea/`, vendor, var, env files properly ignored |

## Dependency Versions (direct, production)

- **PHP**: >=8.2 (runtime: 8.4)
- **Symfony**: 7.3.* (latest stable)
- **Doctrine ORM**: ^3.5.2
- **Doctrine DBAL**: ^3.10.3
- **Twig**: ^3.21.1
- **Ramsey UUID Doctrine**: ~2.1.0

## Dependency Versions (direct, dev)

- **PHPUnit**: ^11.5.42
- **PHPStan**: ^2.1
- **PHP CS Fixer**: ^3.88
- **PHPMD**: ^2.15
- **GrumPHP**: ^2.12
- **Symfony Panther**: ^2.2

## CI Coverage

The `pull-request.yml` workflow covers:
1. **Docker image build** — builds app image from `docker/etc/Dockerfile`
2. **Static analysis** — GrumPHP (phpcsfixer, phpmd, phpstan)
3. **Unit tests** — PHPUnit unit suite
4. **Status comment** — PR comment on success

### CI Gaps (non-blocking recommendations)

- ❌ **Integration tests not in CI** — only Unit tests run; consider adding Integration suite
- ❌ **E2E tests not in CI** — Panther tests require browser; consider adding a separate E2E job with Chrome
- ❌ **No `composer audit` step** — add security scanning to CI
- ❌ **No dependency caching** — Docker image artifact is used but no Composer cache between runs

## Environment Notes

- System Composer (apt-installed) emits PHP 8.4 deprecation warnings due to outdated `composer/pcre` and `justinrainbow/json-schema` packages in system path. This does NOT affect project dependencies (which use vendored Composer via `vendor/bin/`).
- Consider using Composer via the Docker container for local commands to avoid system-level noise.

## .idea Files Status

`.idea/` is listed in `.gitignore` and is **not tracked** by git (not in the index). The files exist locally but will not be committed. No action needed.

## Recommendations

1. **Add `composer audit` to CI** — catches new CVEs on every PR
2. **Add Integration test suite to CI** — run with database (use docker-compose in CI)
3. **Raise PHPStan to level 6+** — incrementally improve type coverage
4. **Pin Composer version in Docker** — avoid system Composer deprecation noise

