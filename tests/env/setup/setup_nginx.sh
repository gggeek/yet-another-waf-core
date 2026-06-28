#!/bin/sh

# @todo handle the case where we get passed PHP_VERSION `default`
# @todo test that this works across all ubuntu versions (precise to resolute)

set -e

echo "Installing Nginx..."

PHP_VERSION="$1"

SCRIPT_DIR="$(dirname -- "$(readlink -f "$0")")"

export DEBIAN_FRONTEND=noninteractive

apt-get install -y --no-install-recommends nginx ssl-cert

# disable unused nginx modules
if [ -n "$(ls /etc/nginx/modules-enabled/*.conf 2>/dev/null)" ]; then
  rm /etc/nginx/modules-enabled/*.conf
fi

# configure virtual hosts

# this overwrites the default vhost
cp -f "$SCRIPT_DIR/../config/nginx_vhost" /etc/nginx/sites-available/default

if [ -n "${GITHUB_ACTIONS}" ]; then
    if [ -z "$PHP_VERSION" ]; then
        echo "PHP version is required as 1st argument to this script when running on Github" >&2
        exit 1
    fi
    PHP_FPM_SOCKET="unix:/run/php/php${PHP_VERSION}-fpm.sock"
    TESTS_ROOT_DIR="$(pwd)"
    sed -e "s?^ *set \\\$tests_root_dir .*?    set \$tests_root_dir ${TESTS_ROOT_DIR}/tests/public;?g" --in-place /etc/nginx/sites-available/default
    sed -e "s?^ *set \\\$php_fpm_socket .*?    set \$php_fpm_socket ${PHP_FPM_SOCKET};?g" --in-place /etc/nginx/sites-available/default
fi

#service nginx restart

echo "Done installing Nginx"
