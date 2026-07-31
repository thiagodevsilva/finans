#!/bin/sh
set -e

cd /var/www/html

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

# Mescla o build novo sem apagar chunks antigos (evita 404 em clientes com HTML/JS em cache).
RELEASE_DIR=/opt/build-release
BUILD_DIR=/var/www/html/public/build
if [ -d "$RELEASE_DIR/assets" ]; then
  mkdir -p "$BUILD_DIR/assets"
  cp -a "$RELEASE_DIR/assets/." "$BUILD_DIR/assets/"
  if [ -f "$RELEASE_DIR/manifest.json" ]; then
    cp -a "$RELEASE_DIR/manifest.json" "$BUILD_DIR/manifest.json"
  fi
  # Remove só arquivos bem antigos para não crescer sem limite.
  find "$BUILD_DIR/assets" -type f -mtime +14 -delete 2>/dev/null || true
  chown -R www-data:www-data "$BUILD_DIR"
fi

if [ ! -f .env ]; then
  echo "ERRO: .env nao encontrado. Crie a partir do .env.example antes de subir."
  exit 1
fi

if ! grep -qE '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi

echo "Aguardando MySQL em ${DB_HOST:-mysql}:${DB_PORT:-3306}..."
i=0
until php -r "try { new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: '3306'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); exit(0); } catch (Throwable \$e) { exit(1); }" 2>/dev/null; do
  i=$((i + 1))
  if [ "$i" -gt 60 ]; then
    echo "ERRO: MySQL indisponivel."
    exit 1
  fi
  sleep 2
done
echo "MySQL OK."

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  php artisan migrate --force
fi

php artisan storage:link --force 2>/dev/null || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
