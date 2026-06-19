#!/bin/sh

# @todo make sure this works across all ubuntu versions (precise to resolute)

echo "Installing Nginx..."

set -e

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

# This can not be done, as the default vhost file is templatized
#service nginx restart

echo "Done installing Nginx"
