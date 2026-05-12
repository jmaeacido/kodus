# Linux + Nginx Deployment Notes

Last reviewed: 2026-05-12

This guide reflects the current PHP/MySQL KODUS codebase. The app can run on Linux with Nginx and PHP-FPM, but Nginx must explicitly implement routing and deny rules because Apache `.htaccess` files are not applied by Nginx.

## 1. Expected Stack

- Linux server
- Nginx
- PHP-FPM
- MySQL or MariaDB
- Composer
- PHP CLI available for background workers
- SMTP access for PHPMailer
- Optional Socket.IO bridge service

Required PHP extensions usually include:

- `mysqli`
- `mbstring`
- `openssl`
- `json`
- `fileinfo`
- `curl`
- `zip`
- `gd` when image/avatar handling or server-side image tasks require it

## 2. Application Setup

1. Copy the reviewed KODUS package to the server, for example `/opt/apps/crg-kodus`.
2. Run `composer install --no-dev --optimize-autoloader`.
3. Create `.env` from `.env.example` directly on the server.
4. Configure database credentials, public URL/root, timezone, SMTP, optional SSO, optional Socket.IO bridge, and optional KODA settings.
5. Make the required runtime directories writable by the PHP-FPM user only where necessary.
6. Block direct public access to secrets, SQL dumps, private keys, logs, documentation, scratch folders, and executable uploads.
7. Confirm PHP CLI path works for background workers:
   - MEB import worker
   - deduplication worker
   - crossmatch worker
   - MEBIS LGU template worker
   - profile export worker

## 3. URL Layout

The app can be mounted at the web root or under a path such as `/kodus/`, but configuration must be consistent.

Relevant `.env` keys:

```dotenv
APP_URL=https://example.gov.ph/kodus/
APP_PUBLIC_ROOT=/kodus/
APP_PUBLIC_DIRECTORY=kodus
APP_TIMEZONE=Asia/Manila
```

If the filesystem folder differs from the browser path, set `APP_PUBLIC_DIRECTORY`.

## 4. Writable Runtime Paths

Review these before go-live:

- `crossmatch/uploads/`
- `deduplication/uploads/`
- `deduplication/logs/`
- `inbox/uploads/`
- `pages/uploads/`
- MEBIS output/job directories under `mebis-consolidator/` and `mebis-lgu-template/`
- profile export output/job directories used by `pages/profile_export_*`
- any configured `storage/` subdirectories

Rules:

- allow write access only to the web/PHP user where required
- deny script execution from uploads/outputs
- clean test files before production
- avoid storing logs inside web-accessible folders when possible

## 5. Example Nginx Configuration

Adjust paths, PHP-FPM socket, domain, and URL prefix for the target server.

```nginx
server {
    listen 80;
    server_name example.gov.ph;

    root /opt/apps;
    index index.php index.html;
    client_max_body_size 25m;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "camera=(), microphone=(), geolocation=(self)" always;

    location = /kodus {
        return 301 /kodus/;
    }

    location ~ ^/kodus/\.(?!well-known) {
        deny all;
    }

    location ~* ^/kodus/(?:composer\.(json|lock|phar)|composer-setup\.php|phpinfo\.php|info\.php|.*\.sql|.*\.pem|.*\.ppk|.*\.key|debug_log\.txt)$ {
        deny all;
    }

    location ~* ^/kodus/.+\.(?:sql(?:\..*)?|bak|old|orig|save|log|ini|env|pem|ppk|key)$ {
        deny all;
    }

    location ~* ^/kodus/(?:storage|artifacts|scratch|sql|screenshots|docs|\.git|\.tmp\.driveupload|deduplication/logs)/ {
        deny all;
    }

    location ~* ^/kodus/(?:uploads|crossmatch/uploads|deduplication/uploads|inbox/uploads|pages/uploads|dist/img|mebis-consolidator/outputs|mebis-lgu-template/outputs)/.*\.(?:php[0-9]?|phtml|phar|cgi|pl|exe|sh|bat|cmd|js|jsp|asp|aspx)$ {
        deny all;
    }

    location ~ ^/kodus(?<script_path>/.*\.php)$ {
        include snippets/fastcgi-php.conf;
        fastcgi_param SCRIPT_FILENAME /opt/apps/crg-kodus$script_path;
        fastcgi_param SCRIPT_NAME $script_path;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }

    location /kodus/ {
        rewrite ^/kodus(/.*)$ /crg-kodus$1 break;
        try_files $uri $uri/ @kodus_extensionless;
    }

    location @kodus_extensionless {
        if (-f /opt/apps/crg-kodus$uri.php) {
            rewrite ^ /kodus$uri.php last;
        }

        if (-f /opt/apps/crg-kodus$uri.html) {
            rewrite ^ /kodus$uri.html last;
        }

        return 404;
    }
}
```

## 6. Security Notes

- Do not add a second Nginx Content-Security-Policy unless intentionally replacing the policy emitted by `security.php`.
- Enable HTTPS and pass correct proxy headers if behind a load balancer.
- Confirm `.env`, `.git`, `docs`, SQL dumps, private keys, logs, and runtime worker files are not web-accessible.
- Confirm uploads cannot execute scripts.
- Confirm session cookies are secure when served over HTTPS.
- Confirm SMTP credentials and SSO secrets are only in `.env`.

## 7. Smoke Tests

After deployment:

- Open login page.
- Login as admin, editor, AA, and user test accounts.
- Select fiscal year.
- Open MEB list and validation pages.
- Run a small MEB import test.
- Export MEB and validation reports.
- Open baseline targets and program activities.
- Save a target and activity record in a test location.
- Open LAWA/BINHI summaries, project-location records, and maps.
- Run small deduplication and crossmatch jobs.
- Open payout and fund monitoring.
- Send an inbox/contact message with and without attachment.
- Confirm notifications and live refresh work.
- Confirm audit logs capture state changes.
- Confirm password reset and SMTP delivery.
- Confirm maintenance mode and custom error pages.
- Confirm blocked files return 403/404.

## 8. Backup and Operations

The repository contains guidance, but the actual production backup process must be supplied by the host/system owner. Confirm:

- database backup frequency
- upload/output directory backup scope
- encryption in transit and at rest
- retention period
- restoration test schedule
- responsible personnel
- log review schedule
