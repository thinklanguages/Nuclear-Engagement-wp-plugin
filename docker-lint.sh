#!/bin/bash

echo "=== Running PHP Linting in Docker ==="
echo ""

# Run linting in Docker with PHP and composer
docker run --rm \
  -v "$PWD":/work \
  -w /work \
  php:8.1-cli \
  bash -c "
    echo '=== Installing Composer ==='
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    
    echo '=== Installing dependencies ==='
    composer install --no-interaction --prefer-dist
    
    echo '=== Running PHP CodeSniffer ==='
    composer lint
  "