#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

wp i18n make-pot nuclear-engagement nuclear-engagement/languages/nuclear-engagement.pot
