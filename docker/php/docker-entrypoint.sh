#!/bin/sh
set -e

cd /var/www/html

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

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
