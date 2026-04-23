# Linux + Nginx Deployment Notes

This project currently runs well on PHP for Linux, but the repository includes Apache `.htaccess` files that do not apply on Nginx. When deploying to a Linux server, the web server rules must be recreated in the Nginx site configuration.

## Important current assumption

Many templates and links in the app currently build URLs like `/kodus/...`.

That means the safest deployment layout today is:

- domain root serves the parent directory
- this app is available at `/kodus`

Example:

- `https://example.com/kodus/`

If the company wants the app to live at the domain root instead, the codebase will need a follow-up cleanup to remove or centralize those hardcoded `/kodus` segments.

## Server requirements

- Linux
- Nginx
- PHP-FPM
- MySQL or MariaDB
- PHP extensions typically needed by this app:
  - `mysqli`
  - `mbstring`
  - `openssl`
  - `json`
  - `fileinfo`
  - `curl`
  - `zip`
  - `gd` if image processing is needed by the server setup

## App setup checklist

1. Copy the project to the Linux server.
2. Run `composer install --no-dev --optimize-autoloader`.
3. Copy `.env.example` to `.env` and set real database and SMTP values.
4. If the filesystem folder name differs from the public URL segment, set `APP_PUBLIC_DIRECTORY` in `.env`.
5. Point Nginx to the parent directory that contains the `kodus` folder.
6. Make sure PHP-FPM is enabled and the `fastcgi_param SCRIPT_FILENAME` value is correct.
7. Give the web server write access only to folders that truly need it.
8. Keep `.env`, SQL dumps, key files, logs, and upload-executable file types blocked at the web-server level.

## Linux-specific checks

- File and folder names are case-sensitive on Linux. A path that works on Windows can fail on Linux if the casing does not match exactly.
- PHP includes in this repo mostly use relative paths and `__DIR__`, which is good for Linux portability.
- Do not rely on `.htaccess`; Nginx ignores it entirely.
- Confirm that writable directories such as upload or storage locations have the right owner/group for the Nginx and PHP-FPM user.

## Suggested Nginx config

This version matches the current deployment target:

- app files in `/opt/apps/crg-kodus`
- public URL `http://172.31.240.55/kodus/`
- nginx site file `/etc/nginx/sites-available/crg-kodus.conf`
- `.env` should include `APP_PUBLIC_DIRECTORY=kodus` when the filesystem folder is `crg-kodus`

Adjust the PHP-FPM socket only if your server uses a different PHP version.

```nginx
server {
    listen 80;
    server_name 172.31.240.55;

    client_max_body_size 25m;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "camera=(), microphone=(), geolocation=(self)" always;

    error_page 400 /kodus/400;
    error_page 401 /kodus/401;
    error_page 403 /kodus/403;
    error_page 404 /kodus/404;
    error_page 405 /kodus/405;
    error_page 408 /kodus/408;
    error_page 429 /kodus/429;
    error_page 500 /kodus/500;
    error_page 502 /kodus/502;
    error_page 503 /kodus/503;
    error_page 504 /kodus/504;

    location = /kodus {
        return 301 /kodus/;
    }

    location = /kodus/index.php {
        return 301 /kodus/;
    }

    location ~ ^/kodus/\.(?!well-known) {
        deny all;
    }

    location ~* ^/kodus/(?:composer\.(json|lock|phar)|composer-setup\.php|phpinfo\.php|info\.php|kodus_db\.sql|kodus-key_pair\.(pem|ppk)|debug_log\.txt|__diag_password_policy\.php|__https_debug\.php)$ {
        deny all;
    }

    location ~* ^/kodus/.+\.(?:sql(?:\..*)?|bak|old|orig|save|log|ini|env|pem|ppk|key)$ {
        deny all;
    }

    location ~* ^/kodus/(?:storage|artifacts|scratch|sql|screenshots|docs|\.git|\.tmp\.driveupload|deduplication/logs)/ {
        deny all;
    }

    location ~* ^/kodus/(?:uploads|inbox/uploads|dist/img)/.*\.(?:php[0-9]?|phtml|phar|cgi|pl|exe|sh|bat|cmd)$ {
        deny all;
    }

    location ~* ^/kodus/(?:crossmatch/uploads|deduplication/uploads|inbox/uploads|pages/uploads)/.*\.(?:php[0-9]?|phtml|phar|cgi|pl|exe|sh|bat|cmd|js|jsp|asp|aspx)$ {
        deny all;
    }

    # Execute real PHP entry points from /opt/apps/crg-kodus.
    location ~ ^/kodus(?<script_path>/.*\.php)$ {
        include snippets/fastcgi-php.conf;
        fastcgi_param SCRIPT_FILENAME /opt/apps/crg-kodus$script_path;
        fastcgi_param SCRIPT_NAME $script_path;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }

    # Serve static files and directories after stripping the public /kodus prefix.
    location /kodus/ {
        rewrite ^/kodus(/.*)$ $1 break;
        root /opt/apps/crg-kodus;
        index index.php index.html;
        try_files $uri $uri/ @kodus_extensionless;
    }

    # Resolve extensionless routes to .php or .html files from the app root.
    location @kodus_extensionless {
        if (-f /opt/apps/crg-kodus$uri.php) {
            rewrite ^ $uri.php last;
        }

        if (-f /opt/apps/crg-kodus$uri.html) {
            rewrite ^ $uri.html last;
        }

        return 404;
    }

    location / {
        return 404;
    }
}
```

Install it as:

```bash
sudo cp /opt/apps/crg-kodus/deployment/nginx/crg-kodus.conf.example /etc/nginx/sites-available/crg-kodus.conf
sudo ln -s /etc/nginx/sites-available/crg-kodus.conf /etc/nginx/sites-enabled/crg-kodus.conf
sudo nginx -t
sudo systemctl reload nginx
```

If the app lives on disk at `/opt/apps/crg-kodus` but is served at `/kodus/`,
set this in `/opt/apps/crg-kodus/.env`:

```dotenv
APP_PUBLIC_DIRECTORY=kodus
```

Do not add a separate Nginx `Content-Security-Policy` header unless you intend
to fully replace the policy emitted by PHP. The application already sends CSP
from [`security.php`](/c:/laragon/www/kodus/security.php:129), and an extra
server-level CSP can block inline scripts used by pages such as
[`select_year.php`](/c:/laragon/www/kodus/select_year.php:63).

## Notes about real-world deployment

- If the app sits behind another proxy or load balancer, confirm forwarded HTTPS and client IP headers are passed correctly.
- The application timezone is currently set in `config.php` to `Asia/Manila`. Change that if the production server should use a different business timezone.
- Test login, logout, password reset, file upload, and every pretty URL after deployment because those are the areas most affected by the move from Apache to Nginx.
- If you enable maintenance mode from `Administration > Maintenance Mode`, make sure Nginx can still execute the PHP status routes above so branded `503` responses render instead of generic server pages.

## Recommended pre-deployment smoke test

- Open `https://example.com/kodus/`
- Open one page with an extensionless route such as `/kodus/home`
- Submit a POST form
- Upload a file if that feature is used
- Verify mail sending from the server
- Confirm `.env` is not accessible from the browser
