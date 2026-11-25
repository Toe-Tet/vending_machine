#!/bin/sh
set -e

# Hosts like Render tell the app which port to bind via $PORT.
if [ -n "$PORT" ] && [ "$PORT" != "80" ]; then
    sed -ri "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
    sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
fi

# Render mounts Secret Files (e.g. DB_SSL_CA=/etc/secrets/ca.pem) readable by root
# only, but Apache's PHP runs as www-data: hand it a readable copy.
if [ -n "${DB_SSL_CA:-}" ] && [ -f "$DB_SSL_CA" ]; then
    install -m 0644 "$DB_SSL_CA" /tmp/db-ca.pem
    export DB_SSL_CA=/tmp/db-ca.pem
fi

# Refuses to start (non-zero exit) on bad config, e.g. a weak JWT_SECRET in production.
php bin/migrate

exec apache2-foreground
