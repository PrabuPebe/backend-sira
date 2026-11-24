#!/bin/bash
set -e

# Disable any duplicate MPM modules before starting Apache
a2dismod mpm_event mpm_worker 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true

# Support dynamic PORT from Railway/Render
if [ -n "$PORT" ]; then
    sed -ri "s/Listen [0-9]+/Listen $PORT/g" /etc/apache2/ports.conf
    sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost \*:$PORT>/g" /etc/apache2/sites-available/*.conf
fi

# Ensure storage directories exist with correct permissions
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs storage/app/public/reports/photos storage/app/public/reports/proofs

# Generate dummy placeholder images if not present
if [ ! -f "storage/app/public/reports/photos/dummy_damage.jpg" ]; then
    echo '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=' | base64 -d > storage/app/public/reports/photos/dummy_damage.jpg
fi
if [ ! -f "storage/app/public/reports/proofs/dummy_proof.jpg" ]; then
    echo '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=' | base64 -d > storage/app/public/reports/proofs/dummy_proof.jpg
fi

# Ensure public storage link exists cleanly
rm -rf public/storage
php artisan storage:link || true

chown -R www-data:www-data storage bootstrap/cache public/storage 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Caching for production if APP_KEY is present
if [ -n "$APP_KEY" ]; then
    php artisan config:cache || true
    php artisan route:cache || true
fi

exec "$@"
