#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
FLOWS_DIR="$ROOT/e2e"

if ! command -v maestro >/dev/null 2>&1; then
  echo "Maestro CLI is required. Install it from https://maestro.mobile.dev" >&2
  exit 1
fi

maestro test "$FLOWS_DIR" "$@"
