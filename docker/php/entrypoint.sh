#!/usr/bin/env bash

# Remove the entrypoint done file if any
if [ -f /var/www/testtaker/entrypoint_done ]; then
    rm /var/www/testtaker/entrypoint_done
fi

# Setup PHP
echo "=============================================================="
echo "== Setup PHP"
echo "=============================================================="
sudo locale-gen

export LANG=en_US.UTF-8
export LANGUAGE=en_US:en
export LC_ALL=en_US.UTF-8
export HOME=/home/testtaker

if [ ! -d ~/.npm ]; then
    mkdir ~/.npm
fi

# Set permissions
echo "=============================================================="
echo "== Setup permissions"
echo "=============================================================="
sudo chown -R testtaker /var/www/testtaker
sudo chown -R testtaker ~/.npm

# Export .env variables to environment
echo "=============================================================="
echo "== Export environment variables"
echo "=============================================================="
ls -a .env || cp .env.example .env
export $(cat .env | grep -v '^[ ]*#' | grep . | xargs)

# Start redis
sudo service redis-server start

# Download and install php modules
echo "=============================================================="
echo "== Composer install"
echo "=============================================================="
if [ "${APP_ENV}" != 'dev' ] && [ "${APP_ENV}" != 'testing' ]; then
    composer install --no-interaction --no-dev
else
    composer install --no-interaction
fi

echo "=============================================================="
echo "== Composer update"
echo "=============================================================="
if [ "${APP_ENV}" != 'dev' ] && [ "${APP_ENV}" != 'testing' ]; then
    composer update --no-dev
else
    composer update
fi

# Download and install node modules
echo "=============================================================="
echo "== Composer dump autoload"
echo "=============================================================="
composer dump-autoload

echo "=============================================================="
echo "== npm install"
echo "=============================================================="
if [ -f ~/package-lock.json ]; then
    rm package-lock.json
fi
npm install

echo "=============================================================="
echo "== npm update"
echo "=============================================================="
npm update

# Generate a key if none exists
if [ -z "${APP_KEY}" ]; then
    echo "=============================================================="
    echo "== Generate application key"
    echo "=============================================================="
    npm run genkey
fi

# Setup the database
echo "=============================================================="
echo "== Composer dump autoload"
echo "=============================================================="
composer dump-autoload

echo "=============================================================="
echo "== Migrate database"
echo "=============================================================="
php artisan migrate

# Compile public assets
echo "=============================================================="
echo "== Compile assets"
echo "=============================================================="
npm run ${APP_ENV}

# Run the PHP development server if we're serving on local host
if [ "${APP_URL}" = "http://localhost" ]; then
    echo "=============================================================="
    echo "== Run development HTTP server"
    echo "=============================================================="
    sudo php artisan serve --host=0.0.0.0 --port=80 > /dev/null 2>&1 &
fi

# Inform gitlab CI that the script is done executing
touch /var/www/testtaker/entrypoint_done

# Unset all environment variables, otherwise it will override .env* Filesystem
echo "=============================================================="
echo "== Unset environment variables"
echo "=============================================================="
unset $(cat .env | grep -v '^[ ]*#' | grep . | cut -d= -f1)

# Also run bash
echo "=============================================================="
echo "== Run bash"
echo "=============================================================="
/bin/bash --init-file <(echo "export http_proxy $http_proxy && export https_proxy $https_proxy && export HTTP_PROXY $HTTP_PROXY && export HTTPS_PROXY $HTTPS_PROXY")
