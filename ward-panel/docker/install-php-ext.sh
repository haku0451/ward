#!/bin/sh
set -eu

# PHP 8.5 images already ship some extensions (opcache, pcntl, bcmath, sqlite).
# docker-php-ext-install then fails with: cp: cannot stat 'modules/*'
install_ext() {
  name="$1"
  if php -m | grep -qi "^${name}$"; then
    echo "skip ${name} (already loaded)"
    return 0
  fi
  docker-php-ext-install -j"$(nproc)" "$name"
}

install_ext pdo_pgsql
install_ext zip
install_ext intl
