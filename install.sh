#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")"
if [ ! -f .env ]; then cp .env.example .env; fi
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true
if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  docker compose up -d --build
  printf '\nEnverif containers are running. Open http://localhost:8080/install to finish setup.\n'
else
  printf 'Docker Compose was not found. See README.md for the native PHP installation path.\n' >&2
  exit 1
fi
