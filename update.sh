#!/usr/bin/env bash
# Update the live site after pushing to GitHub. Run in cPanel Terminal:
#
#   bash ~/repositories/nexus/update.sh
#
# 1. git pull: the PHP code is read straight from this checkout.
# 2. publish: copies CSS, JS, fonts and icons into ~/public_html/nexus. Without
#    this step pages show new HTML with old styles (it is easy to forget,
#    hence this script).
# 3. lists database migrations not yet imported (import them in phpMyAdmin).
#
# Set NEXUS_PHP to force a PHP 8.2+ CLI; otherwise one is found below.
set -euo pipefail

# Everything runs inside main, so bash has read the whole file before
# git pull replaces it.
main() {
  local app php version
  app="$(cd "$(dirname "$0")" && pwd)"
  cd "$app"

  # The account's default `php` is 8.1; the publish script needs 8.2+.
  php=""
  for candidate in "${NEXUS_PHP:-}" /opt/alt/php84/usr/bin/php /opt/alt/php83/usr/bin/php /opt/alt/php82/usr/bin/php php; do
    [ -n "$candidate" ] || continue
    command -v "$candidate" >/dev/null 2>&1 || continue
    if "$candidate" -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);' 2>/dev/null; then
      php="$candidate"
      break
    fi
  done
  if [ -z "$php" ]; then
    echo "No PHP 8.2+ CLI found. Run with NEXUS_PHP=/path/to/php bash update.sh" >&2
    exit 1
  fi
  version="$("$php" -r 'echo PHP_VERSION;')"

  echo "== Pulling the latest code"
  git pull --ff-only origin main

  echo "== Publishing CSS, JS and icons (PHP $version)"
  "$php" bin/publish-cpanel.php

  echo "== Database migrations ([ ] = still to import in phpMyAdmin)"
  if ! "$php" bin/migrate.php --status; then
    echo "(Could not read the database; check .env. The site files are updated.)"
  fi

  echo "== Done. Reload the site (Ctrl+F5 if styles look old)."
}
main "$@"
exit
