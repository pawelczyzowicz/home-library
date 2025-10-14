#!/usr/bin/env bash

NETWORK="local-network"

SCRIPTPATH="$( cd "$(dirname "$0")" >/dev/null 2>&1 ; pwd -P )"
BASEDIR="$SCRIPTPATH/../"
cd "$BASEDIR" || exit
BASEDIR=$(pwd)
echo "$BASEDIR"

safe_copy() {
    if [ ! -f "$2" ]; then
        cp "$1" "$2"
    fi
}

safe_copy "$BASEDIR/.env.dist" "$BASEDIR/.env"

# Load project .env so its values take precedence over defaults and export them
if [ -f "$BASEDIR/.env" ]; then
    set -a
    . "$BASEDIR/.env"
    set +a
fi

# Defaults

export DOCKER_NGINX_PORT="${DOCKER_NGINX_PORT:-8080}"
export DOCKER_POSTGRES_PORT="${DOCKER_POSTGRES_PORT:-5433}"
export DOCKER_POSTGRES_TEST_PORT="${DOCKER_POSTGRES_TEST_PORT:-5434}"

export DATABASE_USER="${DATABASE_USER:-app}"
export DATABASE_PASSWORD="${DATABASE_PASSWORD:-app}"
export DATABASE_NAME="${DATABASE_NAME:-app}"

export DOCKER_POSTGRES_USER="${DOCKER_POSTGRES_USER:-$DATABASE_USER}"
export DOCKER_POSTGRES_PASSWORD="${DOCKER_POSTGRES_PASSWORD:-$DATABASE_PASSWORD}"
export DOCKER_POSTGRES_TEST_DB="${DOCKER_POSTGRES_TEST_DB:-app_test}"

if [ "$(docker images -q home-library:2.0 2>/dev/null)" = "" ]; then
  echo "Building base php+nginx image..."
  docker build --tag home-library:2.0 --no-cache --network=host . -f docker/etc/Dockerfile
fi

# if network doesn't exist - create it
if [ ! $(docker network ls --filter name="^$NETWORK$" --format="{{.ID}}") ]; then
    echo "Creating local network"
    docker network create $NETWORK
fi

# Clean up postgres logs directory - it can grow very large
rm -rf $BASEDIR/var/postgres_test_log/*

env -u DOCKER_NGINX_PORT -u DOCKER_POSTGRES_PORT -u DOCKER_POSTGRES_TEST_PORT docker compose stop
env -u DOCKER_NGINX_PORT -u DOCKER_POSTGRES_PORT -u DOCKER_POSTGRES_TEST_PORT docker compose build
env -u DOCKER_NGINX_PORT -u DOCKER_POSTGRES_PORT -u DOCKER_POSTGRES_TEST_PORT docker compose up -d --remove-orphans

docker exec --user www-data home-library-backend composer install
docker exec --user www-data home-library-backend bin/console d:d:c --if-not-exists --env=dev
docker exec --user www-data home-library-backend bin/console doctrine:migrations:migrate -n
docker exec --user www-data home-library-backend bin/console d:d:c --if-not-exists --env=test
