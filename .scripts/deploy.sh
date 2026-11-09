#!/bin/bash

# Maintenance mode
(php artisan down) || true

# Update codebase
git pull origin master

# Install dependencies
composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader

# Migrate database
php artisan migrate --force

# Refresh the public uploads symlink without replacing a non-symlink path
php artisan storage:link --force

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

# Optimize
php artisan optimize

# Restart long-running workers after configuration caches are refreshed
php artisan queue:restart

# Compile assets
export NVM_DIR=~/.nvm
source ~/.nvm/nvm.sh
nvm install
nvm use
npm install --global npm@12.2.2

npm ci

npm run build

# Enter production mode
php artisan up