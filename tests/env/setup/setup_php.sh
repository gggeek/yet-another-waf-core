#!/bin/sh

# Has to be run as root

# @todo make it optional to install xdebug. It is fe. missing in sury's ppa for Xenial
# @todo make it optional to install fpm. It is not needed for the cd workflow
# @todo make it optional to disable xdebug ?
# @todo set the list of required php extensions in a variable, allow it to be overridden
# @todo allow to force usage of ondrej repos regardless of php version in use

set -e

echo "Installing PHP version '${1}'..."

PHP_VERSION="$1"

SCRIPT_DIR="$(dirname -- "$(readlink -f "$0")")"

export DEBIAN_FRONTEND=noninteractive

configure_php_ini() {
    # note: these settings are not required for cli config
    # shellcheck disable=SC2129
    echo "cgi.fix_pathinfo = 1" >> "${1}"
    echo "always_populate_raw_post_data = -1" >> "${1}"
    # make all errors visible - this will make tests fail which hit a php warning server-side
    echo "display_errors = 1" >> "${1}"
    echo "error_level = -1" >> "${1}"

    # we disable xdebug for speed for both cli and web mode
    # @todo make this optional
    if which phpdismod >/dev/null 2>/dev/null; then
        phpdismod xdebug
    elif [ -f "/etc/php/$PHP_VERSION/mods-available/xdebug.ini" ]; then
        mv "/etc/php/$PHP_VERSION/mods-available/xdebug.ini" "/etc/php/$PHP_VERSION/mods-available/xdebug.ini.bak"
    elif [ -f "/usr/local/php/$PHP_VERSION/etc/conf.d/20-xdebug.ini" ]; then
        mv "/usr/local/php/$PHP_VERSION/etc/conf.d/20-xdebug.ini" "/usr/local/php/$PHP_VERSION/etc/conf.d/20-xdebug.ini.bak"
    else
        echo "Could not disable loading of xdebug - xdebug.ini file not found" >&2
    fi
}

install_native() {
    echo "Using native PHP packages..."

    if [ "${DEBIAN_VERSION}" = jessie ] || [ "${DEBIAN_VERSION}" = precise ] || [ "${DEBIAN_VERSION}" = trusty ]; then
        PHPSUFFIX=5
    else
        PHPSUFFIX=
    fi
    # @todo check for mbstring presence in php5 (jessie) packages
    apt-get install -y \
        "php${PHPSUFFIX}" \
        "php${PHPSUFFIX}-cli" \
        "php${PHPSUFFIX}-dom" \
        "php${PHPSUFFIX}-curl" \
        "php${PHPSUFFIX}-fpm" \
        "php${PHPSUFFIX}-mbstring" \
        "php${PHPSUFFIX}-xdebug"
}

install_ondrej() {
    echo "Using PHP packages from ondrej/php..."

    # @todo... if ubuntu is version is 26 or greater, the installation instructions are different. See: https://codeberg.org/oerdnj/deb.sury.org/issues/91

    apt-get install -y language-pack-en-base software-properties-common
    LC_ALL=en_US.UTF-8 add-apt-repository ppa:ondrej/php
    apt-get update

    PHP_PACKAGES="php${PHP_VERSION} \
        php${PHP_VERSION}-cli \
        php${PHP_VERSION}-dom \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-fpm \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-xdebug"
    apt-get install -y ${PHP_PACKAGES}

    update-alternatives --set php "/usr/bin/php${PHP_VERSION}"
}

# install php
# `lsb-release` is not necessarily onboard. We parse /etc/os-release instead
DEBIAN_VERSION=$(grep 'VERSION_CODENAME=' /etc/os-release | sed 's/VERSION_CODENAME=//')
if [ -z "${DEBIAN_VERSION}" ]; then
    # Example strings:
    # VERSION="14.04.6 LTS, Trusty Tahr"
    # VERSION="8 (jessie)"
    DEBIAN_VERSION=$(grep 'VERSION=' /etc/os-release | grep 'VERSION=' | sed 's/VERSION=//' | sed 's/"[0-9.]\+ *(\?//' | sed 's/)\?"//' | tr '[:upper:]' '[:lower:]' | sed 's/lts, *//' | sed 's/ \+tahr//')
fi

# use native packages if requested for a specific version and that is the same as available in the os repos

DEFAULT_PHP_VERSION=
if [ "${DEBIAN_VERSION}" = 'precise' ]; then
    # aka. ubuntu 12.04
    DEFAULT_PHP_VERSION=5.3
elif [ "${DEBIAN_VERSION}" = 'trusty' ]; then
    # aka. ubuntu 14.04
    DEFAULT_PHP_VERSION=5.5
elif [ "${DEBIAN_VERSION}" = 'xenial' ]; then
    # aka. ubuntu 16.04
    DEFAULT_PHP_VERSION=7.0
elif [ "${DEBIAN_VERSION}" = 'bionic' ]; then
    # aka. ubuntu 18.04
    DEFAULT_PHP_VERSION=7.2
elif [ "${DEBIAN_VERSION}" = 'focal' ]; then
    # aka. ubuntu 20.04
    DEFAULT_PHP_VERSION=7.4
elif [ "${DEBIAN_VERSION}" = 'jammy' ]; then
    # aka. ubuntu 22.04
    DEFAULT_PHP_VERSION=8.1
elif [ "${DEBIAN_VERSION}" = 'noble' ]; then
    # aka. ubuntu 24.04
    DEFAULT_PHP_VERSION=8.3
elif [ "${DEBIAN_VERSION}" = 'resolute' ]; then
    # aka. ubuntu 26.04
    DEFAULT_PHP_VERSION=8.5
fi

if [ "${PHP_VERSION}" = default ] || [ "${PHP_VERSION}" = "${DEFAULT_PHP_VERSION}" ]; then
    install_native
else
    # on GHA runners ubuntu version, php 7.4 and 8.0 seem to be preinstalled. Remove them if found
    for PHP_CURRENT in $(dpkg -l | grep -E 'php.+-common' | awk '{print $2}'); do
        if [ "${PHP_CURRENT}" != "php${PHP_VERSION}-common" ]; then
            apt-get purge -y "${PHP_CURRENT}"
        fi
    done

    # @todo test usage of ondrej packages for php 8.5
    if [ "${PHP_VERSION}" = 5.3 ] || [ "${PHP_VERSION}" = 5.4 ] || [ "${PHP_VERSION}" = 5.5 ] || \
        [ "${DEBIAN_VERSION}" = focal ] || [ "${DEBIAN_VERSION}" = bionic ] || [ "${DEBIAN_VERSION}" = xenial ] || [ "${DEBIAN_VERSION}" = trusty ]; then
        install_shivammatur
    else
        install_ondrej
    fi
fi

PHPVER=$(php -r 'echo implode(".",array_slice(explode(".",PHP_VERSION),0,2));' 2>/dev/null)

service "php${PHPVER}-fpm" stop || true

if [ -d "/etc/php/${PHPVER}/fpm" ]; then
    configure_php_ini "/etc/php/${PHPVER}/fpm/php.ini"
elif [ -f "/usr/local/php/${PHPVER}/etc/php.ini" ]; then
    configure_php_ini "/usr/local/php/${PHPVER}/etc/php.ini"
fi

# @todo shall we configure php-fpm?

# use a nice name for the php-fpm service, so that it does not depend on php version running. Try to make that work
# both for docker and VMs
if [ -f "/etc/init.d/php${PHPVER}-fpm" ]; then
    ln -s "/etc/init.d/php${PHPVER}-fpm" /etc/init.d/php-fpm
fi
if [ -f "/lib/systemd/system/php${PHPVER}-fpm.service" ]; then
    ln -s "/lib/systemd/system/php${PHPVER}-fpm.service" /lib/systemd/system/php-fpm.service
    if [ ! -f /.dockerenv ]; then
        systemctl daemon-reload
    fi
fi

service php-fpm start

# reconfigure apache (if installed). Sadly, php will switch on mod-php and mpm_prefork at install time...
if [ -n "$(dpkg --list | grep apache)" ]; then
    echo "Reconfiguring Apache..."
    if [ -n "$(ls /etc/apache2/mods-enabled/php* 2>/dev/null)" ]; then
        rm /etc/apache2/mods-enabled/php*
    fi
    a2dismod mpm_prefork
    a2enmod mpm_event
    a2enconf php${PHPVER}-fpm
    #service apache2 restart
fi

php -v
echo

echo "Done installing PHP"
