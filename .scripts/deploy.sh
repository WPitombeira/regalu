#!/bin/bash

# Maintenance mode
(php artisan down) || true

# Update codebase
git pull origin master

# Install dependencies
composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader

# Migrate database
php artisan migrate --force

# Clear cache
php artisan cache:clear

# Clear config cache
php artisan config:cache

# Clear route cache
php artisan route:cache

# Clear view cache
php artisan view:clear

# Clear compiled views
php artisan view:clear

# Restart queue worker
php artisan queue:restart

# Optimize
php artisan optimize

# Compile assets
export NVM_DIR=~/.nvm
source ~/.nvm/nvm.sh

npm install -y

npm run build

# Enter production mode
php artisan up