# KODUS Apache Production Checklist

Date: 2026-04-07

Use this checklist before exposing KODUS on the company server.

## 1. Deployment Package

- Deploy only the application files required to run KODUS.
- Do not deploy these files into the public document root:
  - `.env`
  - `kodus-key_pair.pem`
  - `kodus-key_pair.ppk`
  - `kodus_db.sql`
  - `sql/`
  - `docs/`
  - `screenshots/`
  - `scratch/`
  - `artifacts/`
  - `composer-setup.php`
  - `info.php`
  - `phpinfo.php`
  - `__diag_password_policy.php`
  - `__https_debug.php`
- Keep backups, exports, and SQL dumps outside the web root.

## 2. Apache Virtual Host

- Point the site to the intended KODUS directory only.
- Confirm `AllowOverride All` is enabled so [`.htaccess`](/C:/laragon/www/kodus/.htaccess) rules are enforced.
- Disable directory listing with `Options -Indexes`.
- Confirm `mod_rewrite` is enabled.
- Confirm `mod_headers` is enabled.
- Restart Apache after any config change.

Recommended Apache expectations:

```apache
<Directory "C:/path/to/kodus">
    AllowOverride All
    Require all granted
    Options -Indexes
</Directory>
```

## 3. HTTPS

- Install a valid TLS certificate on the company server.
- Redirect all HTTP traffic to HTTPS.
- Confirm cookies are sent only over HTTPS in production.
- After HTTPS is working, add HSTS at the vhost or proxy layer.

Example redirect:

```apache
<VirtualHost *:80>
    ServerName your-kodus-host
    Redirect permanent / https://your-kodus-host/
</VirtualHost>
```

Example HSTS header:

```apache
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

Only enable HSTS after HTTPS is fully working.

## 4. Secrets

- Store production secrets in a production-only `.env` file.
- Prefer keeping the production `.env` outside the public document root if your setup allows it.
- Rotate all credentials before go-live:
  - database password
  - SMTP password
  - any API keys
  - any private/public key pairs previously stored in the repo or web root
- Use production-specific credentials that are different from development.

## 5. PHP Production Settings

- Set `display_errors = Off`.
- Set `display_startup_errors = Off`.
- Set `log_errors = On`.
- Send PHP error logs to a protected server-side location.
- Disable dangerous or unnecessary modules and tools that are not needed in production.
- Keep PHP updated to a supported version.

Recommended minimum settings:

```ini
display_errors = Off
display_startup_errors = Off
log_errors = On
expose_php = Off
session.cookie_httponly = 1
session.use_strict_mode = 1
upload_max_filesize = 20M
post_max_size = 25M
```

## 6. File Permissions

- Grant the Apache service account write access only where needed.
- Writable directories should be limited to app upload/log locations only if they must remain on disk.
- Do not use world-writable permissions such as `0777`.
- Ensure private keys, env files, and logs are readable only by administrators and the service account where required.

Review these writable areas carefully:

- `crossmatch/uploads/`
- `deduplication/uploads/`
- `deduplication/logs/`
- `inbox/uploads/`
- `pages/uploads/`

## 7. Upload Safety

- Confirm the upload directories are not allowed to execute scripts.
- Confirm Apache denies direct access to dangerous extensions in upload paths.
- If possible, serve uploads from a separate non-executable storage path.
- If possible, block inline rendering for risky file classes and force download behavior where appropriate.

## 8. Database

- Use a dedicated production database user with least privilege.
- Do not use the root database account for the app.
- Limit the DB user to only the KODUS database.
- Confirm database backups are stored outside the web root.
- Confirm test users and pentest data are not present in production.

## 9. Mail and Background Jobs

- Verify SMTP settings use the production mail account only.
- Confirm outbound mail is restricted to the intended sender account.
- Review any background worker execution permissions.
- Ensure worker logs are stored in protected server-side paths if they must remain enabled.

## 10. Final Validation Before Go-Live

- Load the login page over HTTPS.
- Log in with a normal user account.
- Confirm session cookies are present and marked `HttpOnly`.
- Confirm direct requests to blocked files return `403` or `404`.
  - `.env`
  - `phpinfo.php`
  - `sql/kodus_db.sql`
  - `docs/KODUS_DOCUMENTATION.md`
- Confirm these app flows still work after deployment:
  - login/logout
  - inbox send/reply
  - calendar create/update/delete
  - document tracking create/update
  - crossmatch upload/results/export
  - deduplication upload/results/cancel
- Confirm file uploads still work for allowed file types only.
- Confirm a request without CSRF is rejected on state-changing endpoints.

## 11. Recommended Go-Live Decision

Ready for company-hosted deployment when all of the following are true:

- secrets and dumps are removed from the deployment artifact
- HTTPS is enabled
- Apache `.htaccess` rules are active
- PHP production settings are applied
- writable directories are minimized
- blocked files and directories are inaccessible over HTTP
- smoke tests pass after deployment
