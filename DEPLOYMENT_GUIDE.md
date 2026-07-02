# Production Deployment Guide — Ubuntu 24 Server

Complete step-by-step instructions to deploy the DMIMS (Laravel 13 + Filament 5) application on
**Ubuntu 24.04 LTS** with **PHP 8.4**, **MariaDB**, **Apache**, and a **Cloudflare Tunnel** (no
public IP / port-forwarding required, no Let's Encrypt needed — Cloudflare terminates TLS at the
edge). This is the reference production stack and matches the tested DMIMS deployment.

> MariaDB is MySQL wire-compatible, so Laravel's `DB_CONNECTION=mysql` driver and the `mysql` /
> `mysqldump` client binaries are used throughout — "MySQL" in commands below refers to those
> MariaDB-provided clients.
>
> **Read the [Deployment Lessons Learned](#deployment-lessons-learned) section at the end before
> you start** — it captures the mistakes that break a fresh install.

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
sudo apt install -y php8.4-cli php8.4-fpm php8.4-mysql php8.4-mbstring \
  php8.4-xml php8.4-bcmath php8.4-curl php8.4-zip php8.4-gd php8.4-intl \
  php8.4-sqlite3 php8.4-redis php8.4-memcached

# Make 8.4 the default `php` (so `php artisan` and Composer's post-scripts use it)
sudo update-alternatives --set php /usr/bin/php8.4
php -v   # must report 8.4.x

# Install Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
composer --version

# Install Apache (configured in Part 7)
sudo apt install -y apache2

# Install MariaDB (MySQL-compatible; provides the mysql/mysqldump clients)
sudo apt install -y mariadb-server

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

## **PART 2: DATABASE SETUP (MariaDB)**

```bash
# `sudo mysql` uses the MariaDB client shipped with mariadb-server
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

### 3.1 Create the app directory
```bash
sudo mkdir -p /var/www/dmims
sudo chown appuser:www-data /var/www/dmims
```

### 3.2 Copy from local machine (SCP)
Open PowerShell **on your Windows machine** and run:
```powershell
scp -r "C:\path\to\dmims\*" appuser@YOUR_SERVER_IP:/var/www/dmims/
```
See the **Quick Reference: SCP Copy Command** section below for a port-specific variant and for
copying only specific folders (useful for incremental updates).

Then SSH into the server and continue from there:
```bash
ssh appuser@YOUR_SERVER_IP
cd /var/www/dmims
```

### 3.3 Set permissions
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
#
# IMPORTANT: run `composer install` BEFORE any `php artisan` command. artisan
# boots from vendor/autoload.php; if vendor/ is missing every artisan call
# (key:generate, migrate, filament:assets, config:cache) fails with
# "Failed opening required '.../vendor/autoload.php'".
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

# Database (MariaDB — use the mysql driver; it is wire-compatible)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
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

# Trust Cloudflare's forwarded headers so HTTPS and the real client IP are
# detected correctly behind the tunnel (required for accurate audit logs)
TRUSTED_PROXIES=*

# This server is reached two ways: through the Cloudflare Tunnel (HTTPS,
# public hostname) AND directly via the machine's localhost/LAN IP (plain
# HTTP, Apache only — no cert installed locally). Because the site is not
# *always* HTTPS, leave SESSION_SECURE_COOKIE=false; otherwise the session
# cookie won't be sent on the plain-HTTP localhost path and login will
# silently fail there. Cloudflare's edge TLS still protects the public URL.
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax
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
> or scheduled via the cron job in Part 13. Ensure the `mysqldump` and `mysql`
> binaries are on the PATH of the user running PHP-FPM/queue workers.

---

## **PART 7: CONFIGURE APACHE**

Laravel ships a `public/.htaccess` with the rewrite rules, so Apache only needs
`mod_rewrite` plus the PHP-FPM proxy. Apache listens on plain **port 80 only** —
TLS is terminated by Cloudflare at the edge (Part 9), so there is no certificate
to install on this server.

### 7.1 Install Apache and enable modules
```bash
sudo a2enmod rewrite proxy proxy_fcgi setenvif headers expires
```

### 7.2 Create the virtual host
```bash
sudo nano /etc/apache2/sites-available/dmims.conf
```

**Paste this configuration:**
```apache
<VirtualHost *:80>
    # Cloudflare Tunnel forwards your public hostname here; the same vhost
    # also answers on localhost/the machine's LAN IP for local access.
    ServerName your-domain.com
    ServerAlias localhost 127.0.0.1
    DocumentRoot /var/www/dmims/public

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

### 7.3 Enable the site
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

## **PART 9: EXPOSE THE SITE VIA CLOUDFLARE TUNNEL (TLS)**

This server has no fixed public IP, so instead of port-forwarding + Let's Encrypt,
`cloudflared` opens an outbound connection to Cloudflare and proxies your public
hostname to Apache on `localhost:80`. Cloudflare issues and renews the TLS
certificate for you — nothing to install or renew on this server.

### 9.1 Install cloudflared
```bash
curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg | sudo gpg --yes --dearmor -o /usr/share/keyrings/cloudflare-main.gpg
echo "deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared $(lsb_release -cs) main" | sudo tee /etc/apt/sources.list.d/cloudflared.list
sudo apt update && sudo apt install -y cloudflared
```

### 9.2 Authenticate and create the tunnel
```bash
# Opens a browser link — log in to the Cloudflare account that owns your-domain.com
cloudflared tunnel login

# Creates a named tunnel and a credentials file under ~/.cloudflared/
cloudflared tunnel create dmims
```

### 9.3 Configure the tunnel
```bash
sudo mkdir -p /etc/cloudflared
sudo nano /etc/cloudflared/config.yml
```

**Paste (replace `TUNNEL_ID` with the UUID printed by `tunnel create`):**
```yaml
tunnel: TUNNEL_ID
credentials-file: /root/.cloudflared/TUNNEL_ID.json

ingress:
  - hostname: your-domain.com
    service: http://localhost:80
  - service: http_status:404
```

### 9.4 Route DNS and install as a service
```bash
# Creates the CNAME record in Cloudflare DNS for your-domain.com
cloudflared tunnel route dns dmims your-domain.com

# Run the tunnel as a persistent systemd service (auto-starts on boot)
sudo cloudflared service install
sudo systemctl enable --now cloudflared
sudo systemctl status cloudflared
```

> In the Cloudflare dashboard, set the SSL/TLS mode for the zone to **Full** (or
> **Flexible**, since Apache here serves plain HTTP) so the edge-to-tunnel hop
> isn't rejected. No certificate files are needed on this server either way.

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

### 10.2 Queue Worker (required — backups and exports run as queued jobs)
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

# Check Apache directly on this machine (plain HTTP, no cert needed)
curl http://localhost
curl http://127.0.0.1

# Check the public hostname through the Cloudflare Tunnel (TLS terminated at the edge)
curl -I https://your-domain.com   # Should return 200
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

# Backup database (put credentials in /root/.my.cnf instead of inline -p
# if you'd rather not have the password appear in `ps`/shell history)
mysqldump -u dmims_user -p'your_secure_password_here' dmims_production | gzip > $BACKUP_DIR/db_$DATE.sql.gz

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
scp -r "C:\path\to\dmims\*" appuser@YOUR_SERVER_IP:/var/www/dmims/

# Or with specific port (if SSH on non-standard port)
scp -r -P 2222 "C:\path\to\dmims\*" appuser@YOUR_SERVER_IP:/var/www/dmims/

# Copy just source code (exclude node_modules, vendor)
scp -r "C:\path\to\dmims\app" appuser@YOUR_SERVER_IP:/var/www/dmims/
scp -r "C:\path\to\dmims\config" appuser@YOUR_SERVER_IP:/var/www/dmims/
scp -r "C:\path\to\dmims\database" appuser@YOUR_SERVER_IP:/var/www/dmims/
scp -r "C:\path\to\dmims\routes" appuser@YOUR_SERVER_IP:/var/www/dmims/
scp -r "C:\path\to\dmims\resources" appuser@YOUR_SERVER_IP:/var/www/dmims/
scp -r "C:\path\to\dmims\public" appuser@YOUR_SERVER_IP:/var/www/dmims/
scp "C:\path\to\dmims\composer.json" appuser@YOUR_SERVER_IP:/var/www/dmims/
scp "C:\path\to\dmims\composer.lock" appuser@YOUR_SERVER_IP:/var/www/dmims/
scp "C:\path\to\dmims\package.json" appuser@YOUR_SERVER_IP:/var/www/dmims/
```

---

## **DEPLOYMENT CHECKLIST**

- [ ] Server prepared (PHP 8.4, Composer, Apache, MariaDB, Node 22, Supervisor installed)
- [ ] MariaDB database created with user/password
- [ ] Source code copied to `/var/www/dmims` via SCP
- [ ] Permissions set correctly
- [ ] Composer & npm dependencies installed; `filament:assets` published
- [ ] .env configured with production settings (MySQL, `SESSION_SECURE_COOKIE=false`, `TRUSTED_PROXIES=*`)
- [ ] Application key generated
- [ ] Database migrations & seeders run; first admin created
- [ ] Apache vhost configured (port 80 only) and enabled
- [ ] PHP-FPM restarted
- [ ] Cloudflare Tunnel installed, authenticated, routed to your-domain.com, running as a service
- [ ] Cron job added for scheduler
- [ ] Queue worker configured (if needed)
- [ ] Site accessible via `https://your-domain.com` (tunnel) **and** `http://localhost` (direct)
- [ ] Tests passing on server: `php artisan test`
- [ ] Backups configured

---

## **DEPLOYMENT LESSONS LEARNED**

Hard-won notes from real DMIMS installs. Ignoring these is what breaks a fresh
deployment most often.

1. **`composer install` before any `php artisan` command.** artisan boots from
   `vendor/autoload.php`. Run `composer install` first (Part 4); otherwise
   `key:generate`, `migrate`, `filament:assets` and `config:cache` all fail.

2. **`vendor/autoload.php` must exist on the server.** `vendor/` is git-ignored
   and never copied by SCP — it is created by `composer install` *on the
   server*. A "Failed opening required 'vendor/autoload.php'" error means
   dependencies were never installed there. Re-run
   `sudo -u appuser composer install --optimize-autoloader --no-dev`.

3. **`SESSION_SECURE_COOKIE=false` for local HTTP; `true` for HTTPS-only
   production.** This deployment is reached both over the Cloudflare Tunnel
   (HTTPS) and directly on localhost/LAN (plain HTTP), so it stays `false` — a
   secure cookie is never sent on the HTTP path and login silently fails. Only
   set `true` if the app is served over HTTPS on *every* path.

4. **Use short, explicit MySQL/MariaDB index names in migrations.** Auto-derived
   names on composite indexes (`table_col1_col2_col3_index`) can exceed the
   64-character identifier limit and abort `migrate`. Name them explicitly, e.g.
   `$table->index(['customer_id', 'movement_type'], 'stock_moves_cust_type_idx');`.

5. **`AssignRequestContext` must not use `withHeaders()`.** `withHeaders()` only
   exists on Illuminate responses; the middleware runs globally, so on the
   `StreamedResponse` / `BinaryFileResponse` returned by report/export/backup
   downloads it would fatal. Set the header on the shared bag instead:
   `$response->headers->set('X-Request-Id', $requestId);`.

6. **Publish Filament assets after every deploy.** `php artisan filament:assets`
   copies the admin-panel CSS/JS/fonts to `public/`. Skipping it leaves an
   unstyled panel. Re-run it (and `npm run build`) on every code update.

7. **PHP 8.4 must be the default `php`.** Ubuntu 24.04 ships 8.3; after adding
   the ondrej/php PPA run `sudo update-alternatives --set php /usr/bin/php8.4`
   so Composer's post-scripts and artisan use 8.4.

---

## **SUPPORT & TROUBLESHOOTING**

**Check error logs:**
```bash
tail -f /var/log/apache2/dmims_error.log
tail -f /var/www/dmims/storage/logs/laravel.log
journalctl -u cloudflared -f               # Cloudflare Tunnel connection issues
```

**Restart services:**
```bash
sudo systemctl restart apache2
sudo systemctl restart php8.4-fpm mariadb
sudo systemctl restart cloudflared
```

**SSH as appuser:**
```bash
ssh appuser@YOUR_SERVER_IP
```

---

**You're ready to deploy!** Copy-paste each section in order. 🚀
