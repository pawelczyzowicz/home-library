#!/usr/bin/env bash
set -euo pipefail

# Run E2E tests inside the application container

cd /var/www/html

# Ensure test env
export APP_ENV=${APP_ENV:-test}
export PANTHER_APP_ENV=${PANTHER_APP_ENV:-test}
# If DATABASE_URL not provided, default to test db service from docker-compose
export DATABASE_URL=${DATABASE_URL:-"postgresql://${DOCKER_POSTGRES_USER:-app}:${DOCKER_POSTGRES_PASSWORD:-app}@home-library-postgres-test:5432/${DOCKER_POSTGRES_TEST_DB:-app_test}"}

# Ensure drivers installed by bdi are on PATH and force Chromium
export PATH="/var/www/html/vendor/bin:${PATH}"
export PANTHER_CHROME_BINARY=${PANTHER_CHROME_BINARY:-/usr/bin/chromium}
export PANTHER_NO_SANDBOX=1
export PANTHER_CHROME_ARGUMENTS="--no-sandbox --disable-dev-shm-usage"

# Install vendors and ensure matching Chrome driver
composer install --no-interaction --prefer-dist
vendor/bin/bdi detect drivers

# Prepare test database
php bin/console doctrine:database:create --env=test --if-not-exists
php bin/console doctrine:migrations:migrate --env=test -n
# php bin/console doctrine:fixtures:load --env=test -n  # optional fixtures

# Execute tests (prefer named testsuite, fallback to directory)
vendor/bin/phpunit --testsuite e2e || vendor/bin/phpunit tests/E2E


