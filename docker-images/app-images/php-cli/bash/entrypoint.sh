#!/bin/sh

set -e

# Replace environment variables if `ENV_SUBSTITUTION_ENABLE=true`
/envsubst.sh

echo 'Running Apache HTTP Server...'
exec "httpd -D FOREGROUND"
