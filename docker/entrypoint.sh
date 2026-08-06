#!/bin/sh
set -e

# Guarded: `composer install` must be runnable before vendor/ exists.
if [ -f vendor/autoload.php ]; then
    php artisan storage:link --force
fi

if [ "$#" -eq 0 ]; then
    set -- php artisan serve --host=0.0.0.0 --port=8000
fi

# Bare artisan sub-commands ("migrate", "tinker") keep working; anything that
# looks like a real command (composer, vendor/bin/pest, sh) runs verbatim.
case "$1" in
    php|composer|sh|bash|npm|npx|vendor/bin/*|/*) ;;
    *) set -- php artisan "$@" ;;
esac

exec "$@"
