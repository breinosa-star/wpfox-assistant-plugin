#!/bin/sh
# One-shot WordPress setup for the KBFox E2E test environment.
# Invoked by the 'setup' service in tests/docker-compose.yml.
#
# What it does:
#   1. Waits for the WordPress database to accept connections.
#   2. Runs `wp core install` if WordPress is not yet installed.
#   3. Activates the KBFox plugin.
#   4. Stores LLM provider, model, and API key (encrypted via grayfox_encrypt).
#
# Environment variables (set in docker-compose.yml, override via tests/.env):
#   GRAYFOX_ADMIN_USER    — wp-admin username (default: admin)
#   GRAYFOX_ADMIN_PASS    — wp-admin password (default: admin)
#   GRAYFOX_ADMIN_EMAIL   — wp-admin email   (default: admin@test.local)
#   GRAYFOX_LLM_PROVIDER  — openai | anthropic | gemini | groq (default: openai)
#   GRAYFOX_LLM_MODEL     — model name (default: gpt-4o-mini)
#   GRAYFOX_API_KEY       — API key for the selected provider (REQUIRED for chat)

set -e

WP_PATH=/var/www/html

echo "[setup] Waiting for the WordPress database..."

# Use PHP/MySQLi (not the mariadb binary) to avoid SSL certificate issues
# with the MariaDB 11.x client bundled in the WP-CLI image.
TRIES=0
MAX_TRIES=60
until php -r "
\$c = mysqli_connect(
    getenv('WORDPRESS_DB_HOST'),
    getenv('WORDPRESS_DB_USER'),
    getenv('WORDPRESS_DB_PASSWORD'),
    getenv('WORDPRESS_DB_NAME')
);
exit(\$c ? 0 : 1);
" > /dev/null 2>&1; do
    TRIES=$((TRIES + 1))
    if [ "$TRIES" -ge "$MAX_TRIES" ]; then
        echo "[setup] ERROR: Timed out waiting for the database after ${MAX_TRIES} attempts." >&2
        exit 1
    fi
    sleep 3
done

echo "[setup] Database is ready."

# Install WordPress core (idempotent — skipped if already installed).
if ! wp core is-installed --path="$WP_PATH" --allow-root 2>/dev/null; then
    echo "[setup] Installing WordPress..."
    wp core install \
        --path="$WP_PATH" \
        --url="http://localhost:8080" \
        --title="KBFox Test Site" \
        --admin_user="${GRAYFOX_ADMIN_USER:-admin}" \
        --admin_password="${GRAYFOX_ADMIN_PASS:-admin}" \
        --admin_email="${GRAYFOX_ADMIN_EMAIL:-admin@test.local}" \
        --skip-email \
        --allow-root
    echo "[setup] WordPress installed."
else
    echo "[setup] WordPress already installed — skipping core install."
fi

# Activate the KBFox plugin.
wp plugin activate kbfox --path="$WP_PATH" --allow-root
echo "[setup] KBFox plugin activated."

# Set LLM provider.
wp option update grayfox_llm_provider "${GRAYFOX_LLM_PROVIDER:-openai}" \
    --path="$WP_PATH" --allow-root

# Set LLM model.
wp option update grayfox_llm_model "${GRAYFOX_LLM_MODEL:-gpt-4o-mini}" \
    --path="$WP_PATH" --allow-root

# Store the API key encrypted via the plugin's own grayfox_encrypt() function.
# wp eval loads WordPress + the active plugin, so grayfox_encrypt is available.
if [ -n "${GRAYFOX_API_KEY}" ]; then
    wp eval \
        "update_option('grayfox_llm_api_key', grayfox_encrypt(getenv('GRAYFOX_API_KEY')));" \
        --path="$WP_PATH" \
        --allow-root
    echo "[setup] API key stored (encrypted)."
else
    echo "[setup] WARNING: GRAYFOX_API_KEY is not set."
    echo "         The chat widget will load but the bot will not respond."
    echo "         Set GRAYFOX_API_KEY in tests/.env and re-run setup."
fi

echo ""
echo "[setup] Done. You can now run the test suite:"
echo "  npm run test:e2e"
echo ""
