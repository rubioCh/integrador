#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

echo "==> Running Integrador pre-production checks"

echo "==> 1) Application preflight"
php artisan system:preflight --strict

echo "==> 2) Test suite"
php artisan test

echo "==> 3) Frontend build"
npm run build

echo "==> 4) Scheduler dry run command"
php artisan events:search-schedule

echo "==> Pre-production checks completed."
