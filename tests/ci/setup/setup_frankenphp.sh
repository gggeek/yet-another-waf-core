#!/bin/sh

# Has to be run as root

set -e

echo "Installing FrankenPHP version '${1}'..."

VERSION="$1" # 82-85 available as of 2026//6/23

export DEBIAN_FRONTEND=noninteractive

curl "https://pkg.henderkes.com/api/packages/${VERSION}/debian/repository.key" -o "/etc/apt/keyrings/static-php${VERSION}.asc"
echo "deb [signed-by=/etc/apt/keyrings/static-php${VERSION}.asc] https://pkg.henderkes.com/api/packages/${VERSION}/debian php-zts main" > /etc/apt/sources.list.d/static-php${VERSION}.list
apt update
apt install -y frankenphp

# @todo... test: does this leave it started or not?
