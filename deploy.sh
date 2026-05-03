#!/bin/bash

# Altfon Booth Quick Deployment Script
# Run this inside your server project directory

echo "🚀 Starting Altfon Booth Deployment..."

# 1. Pull latest changes
git pull origin main

# 2. Install dependencies
composer install --no-dev --optimize-autoloader
npm install && npm run build

# 3. Optimization
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 4. Database migrations
php artisan migrate --force

# 5. Restart Services
sudo systemctl restart altfonbooth
sudo systemctl restart altfonbooth-worker

echo "✅ Deployment Successful!"
