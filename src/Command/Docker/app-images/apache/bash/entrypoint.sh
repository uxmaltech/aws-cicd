#!/bin/sh

set -e

echo "***Container configuration done, starting  $@ ***"

/www/envsubst.sh

exec "$@"
