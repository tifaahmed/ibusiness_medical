#!/bin/bash

# ASH Healthcare - Nginx Cache Configuration Update Script
# This script updates the nginx configuration to enable caching and compression

echo "================================================"
echo "ASH Healthcare - Nginx Configuration Update"
echo "================================================"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "❌ This script must be run as root (use sudo)"
    exit 1
fi

NGINX_CONFIG="/etc/nginx/sites-available/ashhealthcare-eg.com"
BACKUP_CONFIG="/etc/nginx/sites-available/ashhealthcare-eg.com.backup.$(date +%Y%m%d_%H%M%S)"

# Backup existing configuration
echo "📋 Creating backup of current nginx configuration..."
cp "$NGINX_CONFIG" "$BACKUP_CONFIG"
echo "✅ Backup created at: $BACKUP_CONFIG"
echo ""

# Create new configuration with caching
echo "📝 Updating nginx configuration with caching and compression..."

cat > "$NGINX_CONFIG" << 'EOF'
# HTTP only (will be updated by certbot)
server {
    server_name ashhealthcare-eg.com www.ashhealthcare-eg.com;
    root /var/www/ashhealthcare-eg.com/ashhealthcare/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml text/javascript application/json application/javascript application/xml+rss application/rss+xml font/truetype font/opentype application/vnd.ms-fontobject image/svg+xml;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Cache static images
    location ~* \.(jpg|jpeg|png|gif|ico|webp|svg)$ {
        expires 1y;
        add_header Cache-Control "public, max-age=31536000, immutable";
        add_header X-Frame-Options "SAMEORIGIN";
        add_header X-Content-Type-Options "nosniff";
        access_log off;
    }

    # Cache fonts
    location ~* \.(woff|woff2|ttf|otf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, max-age=31536000, immutable";
        add_header X-Frame-Options "SAMEORIGIN";
        add_header X-Content-Type-Options "nosniff";
        access_log off;
    }

    # Cache CSS and JS from build directory
    location ~* ^/build/.*\.(css|js)$ {
        expires 1M;
        add_header Cache-Control "public, max-age=2592000";
        add_header X-Frame-Options "SAMEORIGIN";
        add_header X-Content-Type-Options "nosniff";
        access_log off;
    }

    # Cache other CSS and JS
    location ~* \.(css|js)$ {
        expires 1M;
        add_header Cache-Control "public, max-age=2592000";
        add_header X-Frame-Options "SAMEORIGIN";
        add_header X-Content-Type-Options "nosniff";
        access_log off;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    listen [::]:443 ssl; # managed by Certbot
    listen 443 ssl; # managed by Certbot
    ssl_certificate /etc/letsencrypt/live/ashhealthcare-eg.com/fullchain.pem; # managed by Certbot
    ssl_certificate_key /etc/letsencrypt/live/ashhealthcare-eg.com/privkey.pem; # managed by Certbot
    include /etc/letsencrypt/options-ssl-nginx.conf; # managed by Certbot
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem; # managed by Certbot


}

server {
    if ($host = www.ashhealthcare-eg.com) {
        return 301 https://$host$request_uri;
    } # managed by Certbot


    if ($host = ashhealthcare-eg.com) {
        return 301 https://$host$request_uri;
    } # managed by Certbot


    listen 80;
    listen [::]:80;
    server_name ashhealthcare-eg.com www.ashhealthcare-eg.com;
    return 404; # managed by Certbot




}
EOF

echo "✅ Configuration updated"
echo ""

# Test nginx configuration
echo "🧪 Testing nginx configuration..."
if nginx -t; then
    echo "✅ Nginx configuration test passed"
    echo ""

    # Reload nginx
    echo "🔄 Reloading nginx..."
    if systemctl reload nginx; then
        echo "✅ Nginx reloaded successfully"
        echo ""
        echo "================================================"
        echo "✅ SUCCESS! Cache configuration is now active"
        echo "================================================"
        echo ""
        echo "Next steps:"
        echo "1. Clear your browser cache (Ctrl+Shift+Delete)"
        echo "2. Visit your website and check Network tab in DevTools"
        echo "3. Verify Cache-Control headers are present"
        echo ""
        echo "Backup location: $BACKUP_CONFIG"
    else
        echo "❌ Failed to reload nginx"
        echo "Restoring backup..."
        cp "$BACKUP_CONFIG" "$NGINX_CONFIG"
        exit 1
    fi
else
    echo "❌ Nginx configuration test failed"
    echo "Restoring backup..."
    cp "$BACKUP_CONFIG" "$NGINX_CONFIG"
    exit 1
fi
