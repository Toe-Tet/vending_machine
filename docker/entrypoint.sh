#!/bin/sh
set -e

# Hosts like Render tell the app which port to bind via $PORT.
if [ -n "$PORT" ] && [ "$PORT" != "80" ]; then
    sed -ri "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
    sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
fi

# Refuses to start (non-zero exit) on bad config, e.g. a weak JWT_SECRET in production.
php bin/migrate

exec apache2-foreground
