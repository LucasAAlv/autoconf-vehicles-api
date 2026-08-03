#!/bin/sh
set -e

# Create the public storage symlink so uploaded files are served at /storage/...
php artisan storage:link --force

exec php artisan "$@"
