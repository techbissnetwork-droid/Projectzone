#!/usr/bin/env bash
# Runs the full verification suite. Exits non-zero if any check fails.
set -uo pipefail

cd "$(dirname "$0")/.."

status=0
divider() { printf '\n\033[1m%s\033[0m\n%s\n' "$1" "$(printf '=%.0s' {1..72})"; }

divider "Lint"
files=$(find app config database public bin tests build -name '*.php' -o -name 'techbiss' 2>/dev/null | sort)
lint_failed=0
for file in $files; do
  if ! out=$(php -l "$file" 2>&1); then
    echo "$out"
    lint_failed=1
  fi
done
if [ "$lint_failed" -eq 0 ]; then
  echo "  ✓ $(echo "$files" | wc -l | tr -d ' ') files parse cleanly"
else
  status=1
fi

divider "Route smoke tests"
php tests/smoke.php || status=1

divider "Functional flows"
php tests/flows.php || status=1

divider "Performance and SEO budgets"
php tests/performance.php || status=1

divider "Result"
if [ "$status" -eq 0 ]; then
  echo "  All suites passed."
else
  echo "  One or more suites failed."
fi
exit "$status"
