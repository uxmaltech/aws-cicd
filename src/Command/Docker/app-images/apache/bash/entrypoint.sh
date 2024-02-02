#!/bin/sh

set -e

echo "***Container configuration done, starting  $@ ***"

/envsubst.sh

exec "$@"
