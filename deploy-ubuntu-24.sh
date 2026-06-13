#!/usr/bin/env bash
set -euo pipefail

# Deploy script for Ubuntu Server 24.04 - Laravel + Vite + PWA
# Usage:
#   sudo ./deploy-ubuntu-24.sh --repo-dir /var/www/dmims-code --domain example.com --repo-url https://github.com/your/repo.git

REPO_DIR="/var/www/dmims-code"
REPO_URL=""
APP_DOMAIN=""
DB_DRIVER="mysql"
DB_HOST="127.0.0.1"
DB_PORT="3306"
DB_DATABASE="dmims"
DB_USERNAME="dmims"
DB_PASSWORD=""
SKIP_NGINX="false"
SKIP_QUEUE="false"

function usage() {
  cat <<EOF
Usage: sudo $0 [options]

Options:
  --repo-dir DIR       Repository directory (default: /var/www/dmims-code)
  --repo-url URL       Git repository URL (required if repo directory does not exist)
  --domain DOMAIN      Application domain (required)
  --db-driver DRIVER   Database driver (mysql|pgsql|sqlite) default: mysql
  --db-host HOST       Database host default: 127.0.0.1
  --db-port PORT       Database port default: 3306
  --db-database NAME   Database name default: dmims
  --db-username USER   Database user default: dmims
  --db-password PASS   Database password default: empty
  --skip-nginx         Do not configure nginx
  --skip-queue         Do not configure queue worker service
  -h, --help           Show this help
EOF
  exit 1
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --repo-dir) REPO_DIR="$2"; shift 2 ;; 
    --repo-url) REPO_URL="$2"; shift 2 ;; 
    --domain) APP_DOMAIN="$2"; shift 2 ;; 
    --db-driver) DB_DRIVER="$2"; shift 2 ;; 
    --db-host) DB_HOST="$2"; shift 2 ;; 
    --db-port) DB_PORT="$2"; shift 2 ;; 
    --db-database) DB_DATABASE="$2"; shift 2 ;; 
    --db-username) DB_USERNAME="$2"; shift 2 ;; 
    --db-password) DB_PASSWORD="$2"; shift 2 ;; 
    --skip-nginx) SKIP_NGINX="true"; shift ;; 
    --skip-queue) SKIP_QUEUE="true"; shift ;; 
    -h|--help) usage ;; 
    *) echo "Unknown option: $1" >&2; usage ;; 
  esac
done

if [[ -z "$APP_DOMAIN" ]]; then
  echo "ERROR: --domain is required." >&2
  usage
fi

if [[ ! -d "$REPO_DIR" && -z "$REPO_URL" ]]; then
  echo "ERROR: repository directory does not exist and --repo-url is required." >&2
  usage
fi

function install_packages() {
  apt-get update
  apt-get install -y nginx git curl unzip software-properties-common
  apt-get install -y php8.3 php8.3-fpm php8.3-cli php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath php8.3-sqlite3

  if [[ "$DB_DRIVER" == "mysql" ]]; then
    apt-get install -y mariadb-client
  elif [[ "$DB_DRIVER" == "pgsql" ]]; then
    apt-get install -y postgresql-client
  fi

  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
  apt-get install -y nodejs build-essential
}

function install_composer() {
  if ! command -v composer >/dev/null 2>&1; then
    echo "Installing Composer..."
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f composer-setup.php
  fi
}

function clone_repo() {
  if [[ ! -d "$REPO_DIR" ]]; then
    echo "Cloning repository into $REPO_DIR"
    git clone "$REPO_URL" "$REPO_DIR"
  fi
}

function install_app_dependencies() {
  cd "$REPO_DIR"
  install_composer
  composer install --no-dev --optimize-autoloader
  npm ci
  npm run build
}

function configure_env() {
  cd "$REPO_DIR"
  if [[ ! -f .env ]]; then
    cp .env.example .env
  fi

  php artisan key:generate --force

  sed -i "s|APP_ENV=.*|APP_ENV=production|" .env
  sed -i "s|APP_DEBUG=.*|APP_DEBUG=false|" .env
  sed -i "s|APP_URL=.*|APP_URL=https://$APP_DOMAIN|" .env
  sed -i "s|DB_CONNECTION=.*|DB_CONNECTION=$DB_DRIVER|" .env
  sed -i "s|DB_HOST=.*|DB_HOST=$DB_HOST|" .env
  sed -i "s|DB_PORT=.*|DB_PORT=$DB_PORT|" .env
  sed -i "s|DB_DATABASE=.*|DB_DATABASE=$DB_DATABASE|" .env
  sed -i "s|DB_USERNAME=.*|DB_USERNAME=$DB_USERNAME|" .env
  if [[ -n "$DB_PASSWORD" ]]; then
    sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=$DB_PASSWORD|" .env
  fi
}

function storage_and_db() {
  cd "$REPO_DIR"
  php artisan storage:link --force
  php artisan migrate --force
}

function optimize_app() {
  cd "$REPO_DIR"
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
}

function fix_permissions() {
  chown -R www-data:www-data "$REPO_DIR"
  find "$REPO_DIR/storage" -type d -exec chmod 2775 {} \;
  find "$REPO_DIR/bootstrap/cache" -type d -exec chmod 2775 {} \;
}

function nginx_config() {
  if [[ "$SKIP_NGINX" == "true" ]]; then
    echo "Skipping nginx config.";
    return
  fi

  local site_conf="/etc/nginx/sites-available/dmims"
  cat > "$site_conf" <<EOF
server {
    listen 80;
    server_name $APP_DOMAIN www.$APP_DOMAIN;
    root $REPO_DIR/public;
    index index.php index.html;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /service-worker.js { try_files \$uri =404; }
    location = /sw-register.js { try_files \$uri =404; }
    location = /manifest.webmanifest { try_files \$uri =404; }
    location = /offline.html { try_files \$uri =404; }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_index index.php;
    }

    location ~* \.(?:css|js|jpg|jpeg|gif|png|svg|webp|woff2|woff|ttf|ico)$ {
        try_files \$uri =404;
        access_log off;
        expires 7d;
        add_header Cache-Control "public, must-revalidate, proxy-revalidate";
    }
}
EOF

  ln -sf "$site_conf" /etc/nginx/sites-enabled/dmims
  nginx -t
  systemctl reload nginx
}

function queue_service() {
  if [[ "$SKIP_QUEUE" == "true" ]]; then
    echo "Skipping queue worker setup.";
    return
  fi

  local unit_file="/etc/systemd/system/dmims-worker.service"
  cat > "$unit_file" <<EOF
[Unit]
Description=Laravel Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php $REPO_DIR/artisan queue:work --sleep=3 --tries=3 --daemon
TimeoutStopSec=300

[Install]
WantedBy=multi-user.target
EOF

  systemctl daemon-reload
  systemctl enable --now dmims-worker
}

function main() {
  install_packages
  clone_repo
  install_app_dependencies
  configure_env
  storage_and_db
  optimize_app
  fix_permissions
  nginx_config
  queue_service

  echo "Deployment complete. Verify https://$APP_DOMAIN in your browser."
  echo "Remember to install TLS using certbot or another certificate manager."
}

main
