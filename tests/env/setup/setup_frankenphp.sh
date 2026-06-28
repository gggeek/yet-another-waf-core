#!/bin/sh

# Has to be run as root

set -e

echo "Installing FrankenPHP version '${1}'..."

SCRIPT_DIR="$(dirname -- "$(readlink -f "$0")")"

VERSION="$1" # 82-85 available as of 2026/6/23

if [ -z "$VERSION" ]; then
    echo "PHP version (82 .. 85) is required as 1st argument to this script when running on Github" >&2
    exit 1
fi

export DEBIAN_FRONTEND=noninteractive

curl "https://pkg.henderkes.com/api/packages/${VERSION}/debian/repository.key" -o "/etc/apt/keyrings/static-php${VERSION}.asc"
echo "deb [signed-by=/etc/apt/keyrings/static-php${VERSION}.asc] http://pkg.henderkes.com/api/packages/${VERSION}/debian php-zts main" > /etc/apt/sources.list.d/static-php${VERSION}.list
apt-get update
apt-get install -y frankenphp

if [ ! -d /etc/frankenphp ]; then
    mkdir /etc/frankenphp
fi
if [ ! -d /etc/frankenphp/Caddyfile.d/ ]; then
    mkdir /etc/frankenphp/Caddyfile.d/
fi
if [ ! -d /var/log/frankenphp ]; then
    mkdir /var/log/frankenphp
fi
# Allow non-root to listen frankenphp log files, same as it is possible for nginx
chmod 755 /var/log/frankenphp
chown root:adm /var/log/frankenphp
if [ ! -d /run/frankenphp ]; then
    mkdir /run/frankenphp
fi
chown frankenphp:frankenphp /run/frankenphp

# Note: this does not leave frankenphp auto-starting, as it installs a systemd unit to manage that (and no systemd in containers)

# configure virtual hosts

cp -f "$SCRIPT_DIR/../config/frankenphp_caddyfile" /etc/frankenphp/Caddyfile

if [ -n "${GITHUB_ACTIONS}" ]; then
    TESTS_ROOT_DIR="$(pwd)"
    sed -e "s|^ *root .*|    root ${TESTS_ROOT_DIR}/tests/public|g" --in-place /etc/frankenphp/Caddyfile
fi
