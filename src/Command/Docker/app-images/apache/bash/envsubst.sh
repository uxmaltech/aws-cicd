#!/bin/sh

set -e

CWD=/www

# 1. PHP-FPM default settings (php-fpm.conf)
XAUTHOR_EMAIL=author@email.com

if [[ -z "$UXMALTECH_AUTHOR_EMAIL" ]]; then export UXMALTECH_AUTHOR_EMAIL=$XAUTHOR_EMAIL; fi

envsubst < "$CWD/httpd.conf.stub" > "/etc/apache2/httpd.conf"

# 3. PHP default settings (default-php.ini)
# 3.1 [PHP]
XMEMORY_LIMIT=128M
XEXPOSE_PHP=On
# 3.2 [Session]
XGC_MAXLIFETIME=1440

if [[ -z "$PHP_MEMORY_LIMIT" ]]; then export PHP_MEMORY_LIMIT=$XMEMORY_LIMIT; fi
if [[ -z "$PHP_EXPOSE_PHP" ]]; then export PHP_EXPOSE_PHP=$XEXPOSE_PHP; fi
if [[ -z "$PHP_SESSION_GC_MAXLIFETIME" ]]; then export PHP_SESSION_GC_MAXLIFETIME=$XGC_MAXLIFETIME; fi

envsubst < "$CWD/php.ini.stub" > "/etc/php82/php.ini"

# 4. Environment variables for Laravel application
if [ ! -f "$CWD/.env" ]; then
    echo "Creating .env file..."
    source "$CWD/default-env-stub"
    envsubst < "$CWD/env-stub" > "$CWD/.env"
fi
