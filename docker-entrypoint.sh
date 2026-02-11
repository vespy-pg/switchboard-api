#!/bin/bash
set -e

# Wait for database to be ready (optional, but recommended)
# Uncomment if you need to wait for the database
# until pg_isready -h $DB_HOST -p $DB_PORT -U $DB_USERNAME; do
#   echo "Waiting for database..."
#   sleep 2
# done

# Clear and warm up cache
php bin/console cache:clear --no-warmup
php bin/console cache:warmup

# Run migrations (optional)
# php bin/console doctrine:migrations:migrate --no-interaction

# Execute the main container command
exec "$@"
