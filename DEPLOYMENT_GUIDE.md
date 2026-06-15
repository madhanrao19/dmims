# Production Deployment Guide — Ubuntu 24 Server

Complete step-by-step instructions to deploy the DMIMS (Laravel 13 + Filament 5) application.

---

## **PART 1: SERVER PREPARATION (Run on Ubuntu 24 server as root)**

### 1. Update system and install dependencies
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl wget git nano vim htop software-properties-common

# Ubuntu 24.04 ships PHP 8.3; this app requires PHP 8.4 (Laravel 13 + Filament 5).
# Add the ondrej/php PPA, which provides php8.4 packages.
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update

# Install PHP 8.4 + extensions (sqlite3 for local/testing; fileinfo is bundled)
sudo apt install -y php8.4-cli php8.4-fpm php8.4-mysql php8.4-pgsql php8.4-mbstring \
  php8.4-xml php8.4-bcmath php8.4-curl php8.4-zip php8.4-gd php8.4-intl \
  php8.4-sqlite3 php8.4-redis php8.4-memcached

# Make 8.4 the default `php` (so `php artisan` and Composer's post-scripts use it)
sudo update-alternatives --set php /usr/bin/php8.4
php -v   # must report 8.4.x

# Install Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
composer --version

# Install a web server — choose ONE (configured in Part 7):
sudo apt install -y nginx          # Option A (default in this guide)
# sudo apt install -y apache2      # Option B (see Part 7, Option B)

# Install PostgreSQL (if using PostgreSQL) OR MySQL
# Option A: PostgreSQL
sudo apt install -y postgresql postgresql-contrib
# Option B: MySQL
# sudo apt install -y mysql-server

# Install Node.js & npm (Vite 8 requires Node 20.19+/22+; use the 22 LTS line)
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs

# Install Supervisor (for queue/cron)
sudo apt install -y supervisor

# Create application user
sudo useradd -m -s /bin/bash -d /home/appuser appuser
sudo usermod -aG www-data appuser
```

---

## **PART 2: DATABASE SETUP**

### Option A: PostgreSQL (Recommended for Laravel)
```bash
# Switch to postgres user
sudo su - postgres

# Create database and user
psql << EOF
CREATE DATABASE dmims_production;
CREATE USER dmims_user WITH PASSWORD 'your_secure_password_here';
ALTER ROLE dmims_user SET client_encoding TO 'utf8';
ALTER ROLE dmims_user SET default_transaction_isolation TO 'read committed';
ALTER ROLE dmims_user SET default_transaction_deferrable TO on;
ALTER ROLE dmims_user SET default_transaction_read_committed TO on;
GRANT ALL PRIVILEGES ON DATABASE dmims_production TO dmims_user;
\q
EOF

exit  # Exit postgres user
```

### Option B: MySQL
```bash
sudo mysql -u root << EOF
CREATE DATABASE dmims_production;
CREATE USER 'dmims_user'@'localhost' IDENTIFIED BY 'your_secure_password_here';
GRANT ALL PRIVILEGES ON dmims_production.* TO 'dmims_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
EOF
```

---

## **PART 3: DEPLOY APPLICATION CODE**

### 3.1 Clone/Copy application
```bash
# Create app directory
sudo mkdir -p /var/www/dmims
sudo chown appuser:www-data /var/www/dmims
cd /var/www/dmims

# Option A: Clone from Git (if repo is public/you have SSH keys)
sudo -u appuser git clone https://your-repo-url.git .

# Option B: Copy from local machine (SCP from your Windows machine)
# Open PowerShell on your local machine and run:
# scp -r "d:\Dev\IMS\Source Code\dmims-code\*" appuser@YOUR_SERVER_IP:/var/www/dmims/

# Then SSH into the server and continue:
ssh appuser@YOUR_SERVER_IP
cd /var/www/dmims
```

### 3.2 Set permissions
```bash
sudo chown -R appuser:www-data /var/www/dmims
sudo chmod -R 755 /var/www/dmims
sudo chmod -R 775 /var/www/dmims/storage
sudo chmod -R 775 /var/www/dmims/bootstrap/cache
```

---

## **PART 4: INSTALL COMPOSER DEPENDENCIES**

```bash
cd /var/www/dmims

# Install PHP dependencies (as appuser). This installs everything from
# composer.lock, including the barcode (picqer), PDF (dompdf) and Excel
# (openspout) libraries that power scannable labels and PDF/Excel reports —
# no extra `composer require` is needed.
sudo -u appuser composer install --optimize-autoloader --no-dev

# Install Node dependencies (for frontend assets)
sudo -u appuser npm install
sudo -u appuser npm run build

# Publish Filament's admin-panel assets (CSS/JS/fonts) — required for the UI
sudo -u appuser php artisan filament:assets
```

---

## **PART 5: ENVIRONMENT CONFIGURATION**

```bash
# Copy .env file (modify as needed)
sudo -u appuser cp .env.example .env

# Edit .env with production settings
sudo nano /var/www/dmims/.env
```

**Key .env settings to update:**
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=pgsql  # or mysql
DB_HOST=127.0.0.1
DB_PORT=5432         # 5432 for PostgreSQL, 3306 for MySQL
DB_DATABASE=dmims_production
DB_USERNAME=dmims_user
DB_PASSWORD=your_secure_password_here

# Mail
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email@domain.com
MAIL_PASSWORD=your-email-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com

# Session & Cache (use Redis if available)
SESSION_DRIVER=file
CACHE_DRIVER=file
QUEUE_CONNECTION=database

# Session cookie hardening (the site is served over HTTPS)
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

# Trust Cloudflare/Nginx forwarded headers so HTTPS and the real client IP are
# detected correctly (required for secure cookies and accurate audit logs)
TRUSTED_PROXIES=*
```

> **Mail is required for password resets.** The admin panel exposes a
> "Forgot password" flow. With `MAIL_MAILER=log` (the default), reset emails are
> only written to `storage/logs` and never delivered. Configure a real `smtp`
> mailer with valid credentials, then verify with
> `php artisan tinker --execute="Mail::raw('test', fn(\$m) => \$m->to('you@example.com')->subject('test'));"`.

### Generate application key
```bash
cd /var/www/dmims
sudo -u appuser php artisan key:generate
```

---

## **PART 6: RUN MIGRATIONS & SEEDERS**

```bash
cd /var/www/dmims

# Run migrations
sudo -u appuser php artisan migrate --force

# Seed the roles & permissions the access control depends on (no demo data)
sudo -u appuser php artisan db:seed --class=RolesAndPermissionsSeeder --force

# Create the first platform administrator (prompts for a password if omitted)
sudo -u appuser php artisan dmims:create-admin admin@your-domain.com --name="Administrator"
```

> The full `php artisan db:seed` also creates a demo customer and a default
> `admin@example.com` / `password` login — use it only for evaluation, never on
> a production install.

> Database backups can be taken from the admin panel (Platform → Backups → "Run Database Backup")
> or scheduled via the cron job in Part 13. For MySQL, ensure the `mysqldump` and `mysql`
> binaries are on the PATH of the user running PHP-FPM/queue workers.

---

## **PART 7: CONFIGURE WEB SERVER**

Use **either** Nginx (Option A) or Apache (Option B) — not both. Both serve the
app's `public/` directory and proxy PHP to PHP-FPM 8.4.

### Option A — Nginx

#### 7.1 Create Nginx server block
```bash
sudo nano /etc/nginx/sites-available/dmims
```

**Paste this configuration:**
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com www.your-domain.com;
    root /var/www/dmims/public;
    index index.php index.html;

    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name your-domain.com www.your-domain.com;
    root /var/www/dmims/public;
    index index.php index.html index.htm;

    # SSL Certificate (use Let's Encrypt after setup)
    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;

    # Performance
    client_max_body_size 100M;
    fastcgi_read_timeout 300s;

    # Logs
    access_log /var/log/nginx/dmims_access.log;
    error_log /var/log/nginx/dmims_error.log;

    # Laravel rewrite
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
    }

    # Static assets caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    location ~ /composer.json {
        deny all;
    }
}
```

#### 7.2 Enable the site
```bash
sudo ln -s /etc/nginx/sites-available/dmims /etc/nginx/sites-enabled/
sudo nginx -t  # Test configuration
sudo systemctl restart nginx
```

### Option B — Apache

Laravel ships a `public/.htaccess` with the rewrite rules, so Apache only needs
`mod_rewrite` plus the PHP-FPM proxy.

#### 7.3 Install Apache and enable modules
```bash
sudo apt install -y apache2
sudo a2enmod rewrite proxy proxy_fcgi setenvif ssl headers expires http2
# Free port 80/443 if Nginx was installed earlier:
sudo systemctl disable --now nginx 2>/dev/null || true
```

#### 7.4 Create the virtual host
```bash
sudo nano /etc/apache2/sites-available/dmims.conf
```

**Paste this configuration:**
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    ServerAlias www.your-domain.com
    # Redirect HTTP to HTTPS
    Redirect permanent / https://your-domain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName your-domain.com
    ServerAlias www.your-domain.com
    DocumentRoot /var/www/dmims/public

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/your-domain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/your-domain.com/privkey.pem

    <Directory /var/www/dmims/public>
        AllowOverride All           # required so public/.htaccess handles routing
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    # Send PHP to PHP-FPM 8.4 via the Unix socket
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.4-fpm.sock|fcgi://localhost"
    </FilesMatch>

    # Static asset caching
    <IfModule mod_expires.c>
        ExpiresActive On
        <FilesMatch "\.(jpg|jpeg|png|gif|ico|css|js|svg|woff2?|ttf|eot)$">
            ExpiresDefault "access plus 30 days"
            Header set Cache-Control "public, immutable"
        </FilesMatch>
    </IfModule>

    ErrorLog ${APACHE_LOG_DIR}/dmims_error.log
    CustomLog ${APACHE_LOG_DIR}/dmims_access.log combined
</VirtualHost>
```

#### 7.5 Enable the site
```bash
sudo a2dissite 000-default.conf
sudo a2ensite dmims.conf
sudo apache2ctl configtest   # Test configuration
sudo systemctl restart apache2
```

> Upload size for Apache + PHP-FPM is governed by PHP's `upload_max_filesize` /
> `post_max_size` (set in Part 8) — no Apache-specific limit is required.

---

## **PART 8: CONFIGURE PHP-FPM**

```bash
sudo nano /etc/php/8.4/fpm/php.ini
```

**Key settings to update:**
```
max_execution_time = 300
memory_limit = 512M
upload_max_filesize = 100M
post_max_size = 100M
```

```bash
# Restart PHP-FPM
sudo systemctl restart php8.4-fpm
```

---

## **PART 9: SETUP SSL CERTIFICATE (Let's Encrypt)**

**If using Nginx (Option A):**
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com -d www.your-domain.com
sudo nginx -t && sudo systemctl reload nginx
```

**If using Apache (Option B):**
```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d your-domain.com -d www.your-domain.com
sudo apache2ctl configtest && sudo systemctl reload apache2
```

**Auto-renewal (both):**
```bash
sudo systemctl enable certbot.timer
sudo systemctl start certbot.timer
sudo certbot renew --dry-run   # verify renewal works
```

---

## **PART 10: SETUP CRON & QUEUE JOBS**

### 10.1 Laravel Scheduler (runs every minute)
```bash
sudo crontab -e -u appuser
```

Add this line:
```
* * * * * cd /var/www/dmims && php artisan schedule:run >> /dev/null 2>&1
```

### 10.2 Queue Worker (if using queues)
Create supervisor config:
```bash
sudo nano /etc/supervisor/conf.d/dmims-worker.conf
```

**Paste:**
```ini
[program:dmims-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/dmims/artisan queue:work database --sleep=3 --tries=3
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/dmims-worker.log
user=appuser
```

Start supervisor:
```bash
sudo systemctl enable supervisor
sudo systemctl start supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start dmims-queue-worker:*
```

---

## **PART 11: SETUP MONITORING & LOGGING**

```bash
# Create log rotation
sudo nano /etc/logrotate.d/dmims
```

**Paste:**
```
/var/www/dmims/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 appuser www-data
}
```

---

## **PART 12: VERIFY DEPLOYMENT**

```bash
# Check Laravel status
cd /var/www/dmims
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Test the application
php artisan tinker
# In tinker: DB::connection()->getPdo(); (should not error)
# exit;

# Check Nginx
curl http://localhost
curl -I https://your-domain.com  # Should return 200
```

---

## **PART 13: BACKUP STRATEGY**

```bash
# Create daily backup script
sudo nano /usr/local/bin/backup-dmims.sh
```

**Paste:**
```bash
#!/bin/bash
BACKUP_DIR="/backups/dmims"
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup database
pg_dump -U dmims_user dmims_production | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Backup uploads/storage
tar -czf $BACKUP_DIR/storage_$DATE.tar.gz /var/www/dmims/storage/app

# Keep only last 30 days
find $BACKUP_DIR -name "*.gz" -mtime +30 -delete

echo "Backup completed: $DATE"
```

```bash
sudo chmod +x /usr/local/bin/backup-dmims.sh

# Add to crontab (3 AM daily)
sudo crontab -e
# Add: 0 3 * * * /usr/local/bin/backup-dmims.sh >> /var/log/dmims-backup.log 2>&1
```

---

## **QUICK REFERENCE: SCP COPY COMMAND** (Run from Windows PowerShell)

```powershell
# Copy entire project to Ubuntu server
scp -r "d:\Dev\IMS\Source Code\dmims-code\*" appuser@YOUR_SERVER_IP:/var/www/dmims/

# Or with specific port (if SSH on non-standard port)
scp -r -P 2222 "d:\Dev\IMS\Source Code\dmims-code\*" appuser@YOUR_SERVER_IP:/var/www/dmims/

# Copy just source code (exclude node_modules, vendor)
scp -r "d:\Dev\IMS\Source Code\dmims-code\app" appuser@YOUR_SERVER_IP:/var/www/dmims/
scp -r "d:\Dev\IMS\Source Code\dmims-code\config" appuser@YOUR_SERVER_IP:/var/www/dmims/
scp -r "d:\Dev\IMS\Source Code\dmims-code\database" appuser@YOUR_SERVER_IP:/var/www/dmims/
scp -r "d:\Dev\IMS\Source Code\dmims-code\routes" appuser@YOUR_SERVER_IP:/var/www/dmims/
scp -r "d:\Dev\IMS\Source Code\dmims-code\resources" appuser@YOUR_SERVER_IP:/var/www/dmims/
scp -r "d:\Dev\IMS\Source Code\dmims-code\public" appuser@YOUR_SERVER_IP:/var/www/dmims/
scp "d:\Dev\IMS\Source Code\dmims-code\composer.json" appuser@YOUR_SERVER_IP:/var/www/dmims/
scp "d:\Dev\IMS\Source Code\dmims-code\composer.lock" appuser@YOUR_SERVER_IP:/var/www/dmims/
scp "d:\Dev\IMS\Source Code\dmims-code\package.json" appuser@YOUR_SERVER_IP:/var/www/dmims/
```

---

## **DEPLOYMENT CHECKLIST**

- [ ] Server prepared (PHP, Composer, Nginx, DB installed)
- [ ] Database created with user/password
- [ ] Source code copied to `/var/www/dmims`
- [ ] Permissions set correctly
- [ ] Composer dependencies installed
- [ ] .env configured with production settings
- [ ] Application key generated
- [ ] Database migrations run
- [ ] Nginx configured and SSL enabled
- [ ] PHP-FPM restarted
- [ ] Cron job added for scheduler
- [ ] Queue worker configured (if needed)
- [ ] Site accessible via HTTPS
- [ ] Tests passing on server: `php artisan test`
- [ ] Backups configured

---

## **SUPPORT & TROUBLESHOOTING**

**Check error logs:**
```bash
tail -f /var/log/nginx/dmims_error.log      # Nginx (Option A)
tail -f /var/log/apache2/dmims_error.log    # Apache (Option B)
tail -f /var/www/dmims/storage/logs/laravel.log
```

**Restart services:**
```bash
# Web server — whichever you installed:
sudo systemctl restart nginx        # Option A
sudo systemctl restart apache2      # Option B
sudo systemctl restart php8.4-fpm postgresql
```

**SSH as appuser:**
```bash
ssh appuser@YOUR_SERVER_IP
```

---

**You're ready to deploy!** Copy-paste each section in order. 🚀
