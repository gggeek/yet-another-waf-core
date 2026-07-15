#!/bin/sh

# Uses env vars: APT_PACKAGE_PROXY, PHP_VERSION

set -e

#if [ ! -f /.dockerenv ]; then touch /.dockerenv; fi

# Allow the user to specify a proxy for speeding up downloading of apt packages
if [ "${APT_PACKAGE_PROXY}" != "none" ]; then
    printf "Acquire::http::Proxy \"${APT_PACKAGE_PROXY}\";\nAcquire::https::Proxy \"DIRECT\";\n" > /etc/apt/apt.conf.d/00proxy
fi

# @todo allow the user to specify an ubuntu mirror for speeding up downloading of apt packages

apt-get update --allow-releaseinfo-change

cd /root/setup

./setup_php.sh  "$PHP_VERSION" "$1"

# @todo move the php extensions in a folder which has a fixed name? instead of, say, /usr/lib/php/20250925/ ?
