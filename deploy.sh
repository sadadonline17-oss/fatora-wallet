#!/bin/bash
# Fatora Wallet - VPS Deployment Script
# Usage: ./deploy.sh

set -e

echo "🚀 Deploying Fatora Wallet..."

# Configuration
APP_DIR="/var/www/fatora-wallet"
GIT_REPO="https://github.com/sadadonline17-oss/fatora-wallet.git"
BRANCH="main"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() { echo -e "${GREEN}[INFO]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    warn "Please run as root or with sudo"
    exit 1
fi

# Install dependencies
log "Installing dependencies..."
apt-get update
apt-get install -y curl git unzip nginx php8.3-fpm php8.3-cli php8.3-mysql php8.3-redis php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-gd composer

# Create app directory
log "Setting up application..."
mkdir -p $APP_DIR
cd $APP_DIR

# Pull latest code
if [ -d ".git" ]; then
    log "Pulling latest changes..."
    git pull origin $BRANCH
else
    log "Cloning repository..."
    git clone $GIT_REPO .
    git checkout $BRANCH
fi

# Install dependencies
log "Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Create .env if not exists
if [ ! -f ".env" ]; then
    log "Creating .env file..."
    cp .env.example .env
    php artisan key:generate --force
fi

# Run migrations
log "Running migrations..."
php artisan migrate --force

# Storage link
php artisan storage:link

# Permissions
log "Setting permissions..."
chown -R www-data:www-data $APP_DIR/storage $APP_DIR/bootstrap/cache
chmod -R 775 $APP_DIR/storage $APP_DIR/bootstrap/cache

# Nginx configuration
log "Configuring Nginx..."
cat > /etc/nginx/sites-available/fatora-wallet << 'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name _;
    
    root /var/www/fatora-wallet/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    client_max_body_size 100M;
}
EOF

ln -sf /etc/nginx/sites-available/fatora-wallet /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Test and reload Nginx
nginx -t && systemctl reload nginx

# PHP-FPM
systemctl enable php8.3-fpm
systemctl restart php8.3-fpm

# Queue worker (optional - run with systemd)
cat > /etc/systemd/system/fatora-queue.service << 'EOF'
[Unit]
Description=Fatora Wallet Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/fatora-wallet
ExecStart=/usr/bin/php /var/www/fatora-wallet/artisan queue:work redis --sleep=3 --tries=3
Restart=always

[Install]
WantedBy=multi-user.target
EOF

systemctl enable fatora-queue
systemctl restart fatora-queue

log "✅ Deployment complete!"
log "📋 Next steps:"
log "   1. Configure your .env file with real API keys"
log "   2. Set up SSL: certbot --nginx"
log "   3. Update APP_URL in .env to your domain"
