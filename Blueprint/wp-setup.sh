#!/usr/bin/env bash
# WordPress initial setup — runs inside the wpt-wordpress container
set -euo pipefail

WP="wp --allow-root --path=/var/www/html"

# Wait for WordPress files to be ready
echo "Waiting for WordPress files..."
until [ -f /var/www/html/wp-includes/version.php ]; do
    sleep 1
done

# Install mysql client if missing (needed for wp db check)
if ! command -v mysqlcheck &>/dev/null; then
    echo "Installing mysql client..."
    apt-get update -qq && apt-get install -y -qq default-mysql-client >/dev/null 2>&1
fi

# Wait for database
echo "Waiting for database..."
until $WP db check >/dev/null 2>&1; do
    sleep 2
done

# Install WordPress if not already installed
if ! $WP core is-installed 2>/dev/null; then
    echo "Installing WordPress..."
    $WP core install \
        --url="http://wpfaker-test.dv" \
        --title="WPFaker Test" \
        --admin_user=admin \
        --admin_password=admin \
        --admin_email=admin@wpfaker-test.dv \
        --skip-email
fi

# Set permalink structure
$WP rewrite structure '/%postname%/' --hard

# Remove default plugins and themes
$WP plugin delete akismet hello 2>/dev/null || true
$WP theme delete twentytwentythree twentytwentytwo 2>/dev/null || true

echo "WordPress setup complete!"
