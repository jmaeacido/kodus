# Remediation Validation Report

## Scope

Post-remediation validation pass for the recent OWASP ZAP fixes in the KODUS codebase, using the current repository state as the validation source.

Validated areas:

- duplicate security headers
- centralized header application
- CSP, HSTS, and X-Frame-Options behavior
- obvious state-changing POST endpoints and CSRF coverage

## Validation Summary

The application-side remediation is in good shape.

- Security headers are now emitted from a single shared code path in `security.php`.
- The previous duplicate `X-Frame-Options` condition in `/forgot-password` appears resolved at the repo level.
- CSP is now present and intentionally permissive enough to avoid breaking the current AdminLTE, SweetAlert2, DataTables, and inline script/style usage.
- HSTS is emitted on HTTPS responses by application code.
- The previously real CSRF gap on the year-selection POST flow is now closed.
- `save_location_context.php` is now protected by both same-origin checks and CSRF token validation.

The main residual exposure is still server-level:

- Apache `Server` header/version leakage
- any upstream or php.ini-driven `X-Powered-By` leakage outside application control
- HTTPS enforcement and HSTS coverage for non-PHP/static responses

## Verification Boundary

This validation pass confirmed repository changes, PHP syntax, and live HTTP response headers from the current workspace.

Not performed from this workspace:

- authenticated browser-console verification in DevTools
- visual confirmation that Leaflet standard, satellite, and hybrid tile layers rendered in the browser

Those checks still require a manual authenticated browser session.

## Fixed Findings

### 1. Duplicate Security Headers

Status: fixed at the application/repo level.

Observed validation points:

- Only one code path in the repo now emits `X-Frame-Options`:
  - `security.php`
- Only one code path in the repo now emits `Content-Security-Policy`:
  - `security.php`
- Only one code path in the repo now emits `Strict-Transport-Security`:
  - `security.php`
- The previous overlapping Apache header block in `.htaccess` was removed, which eliminates the known app-plus-Apache duplication source for `/forgot-password`.

Expected result:

- `/forgot-password` should no longer return duplicate `X-Frame-Options` entries unless a higher Apache/vhost/reverse-proxy layer is still adding them.

### 2. Centralized Header Application

Status: fixed.

Observed validation points:

- `security_bootstrap_session()` now applies:
  - runtime hardening
  - HTTPS enforcement
  - shared security headers
- `header.php` uses `security_bootstrap_session()`
- public routes such as `/kodus/` and `/select_year` also call `security_bootstrap_session()` directly
- `auth_apply_security_headers()` now delegates to the shared `security_apply_response_headers()` helper instead of emitting its own copy

Expected result:

- routes that bootstrap through `security_bootstrap_session()` should get the same header set consistently
- authenticated and public PHP responses should behave the same for CSP, X-Frame-Options, and related headers

### 3. Content Security Policy

Status: fixed.

Observed policy in `security.php`:

- `default-src 'self'`
- `base-uri 'self'`
- `form-action 'self'`
- `frame-ancestors 'self'`
- `object-src 'none'`
- `img-src 'self' data: blob:`
- `font-src 'self' data:`
- `style-src 'self' 'unsafe-inline'`
- `script-src 'self' 'unsafe-inline' 'unsafe-eval'`
- `connect-src 'self' ws: wss:`
- `frame-src 'self'`
- `media-src 'self' data: blob:`
- `worker-src 'self' blob:`
- `upgrade-insecure-requests` on HTTPS

Assessment:

- This is a practical compatibility-first CSP.
- It is intentionally not a strict nonce/hash CSP, which matches the requirement to avoid breaking the current frontend stack.
- It should prevent the original “CSP header not set” finding.

### 4. HSTS

Status: fixed at the PHP application layer.

Observed validation points:

- `Strict-Transport-Security: max-age=31536000; includeSubDomains` is emitted in `security.php` when the request is HTTPS
- `.htaccess` now includes a redirect rule intended to force deployed hosts to HTTPS while exempting local development hosts

Assessment:

- PHP responses served over HTTPS should now include HSTS consistently.
- Full HSTS coverage still depends on the web server serving all HTTPS traffic through the same policy, including static/non-PHP responses.

### 5. X-Frame-Options

Status: fixed.

Observed validation points:

- `X-Frame-Options: SAMEORIGIN` is emitted centrally in `security.php`
- there are no remaining duplicate repo-level emitters

Assessment:

- the original duplicate-XFO issue is resolved in code
- if duplicates still appear in live traffic, the remaining source would be Apache/vhost/reverse-proxy configuration

### 6. CSRF Protection on Obvious State-Changing POST Endpoints

Status: materially improved and correct for the main reviewed routes.

Confirmed protected endpoints/forms:

- `/kodus/` login form includes `csrf_token`, and `login.php` requires CSRF
- `/forgot-password` form includes `csrf_token`, and `send-reset-link.php` requires CSRF
- `/select_year` form now includes `csrf_token`, and `select_year.php` requires CSRF on POST
- `save_location_context.php` now requires CSRF and also enforces same-origin checks

Broader validation:

- many state-changing POST handlers across admin, inbox, notifications, settings, pages, crossmatch, deduplication, implementation-status, and account/security flows call `security_require_csrf_token()`

Assessment:

- the obvious state-changing POST endpoints relevant to the ZAP findings are protected
- the prior real omission on the year-selection route is fixed

## Remaining Server-Level Items

These items are not fully solvable in application PHP alone and should still be considered open until verified in Apache/PHP configuration:

### 1. `Server` Header / Version Leakage

Still server-level.

- PHP can attempt `header_remove('Server')`, but Apache usually controls this header.
- If the live response still shows `Server: Apache/...`, that is expected until Apache/vhost/proxy config is hardened.

### 2. `X-Powered-By` Leakage

Mostly server-level.

- Application code now calls `header_remove('X-Powered-By')` and sets `expose_php=0` at runtime.
- The definitive fix is still `expose_php = Off` in `php.ini` or equivalent server configuration.
- Upstream layers could still inject their own technology headers.

### 3. Full HTTPS/HSTS Coverage

Still partially server-level.

- The repo now redirects HTTP to HTTPS in `.htaccess` for non-local hosts.
- PHP routes that pass through `security_bootstrap_session()` also enforce HTTPS.
- Static assets and any response path outside this PHP bootstrap still depend on Apache/vhost behavior.

### 4. Header Duplication From Upstream Infrastructure

Still server-level.

- The repo-level duplicate source was removed.
- If duplicate headers still appear, the remaining source is likely Apache global config, vhost config, or a reverse proxy/load balancer.

## Expected Headers

These are the expected application-level headers after the remediation, assuming the route is served through the current PHP/bootstrap path and no upstream layer overrides them.

### 1. `/kodus/`

Expected on HTTPS:

- `X-Frame-Options: SAMEORIGIN`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: geolocation=(self), microphone=(), camera=()`
- `X-Permitted-Cross-Domain-Policies: none`
- `Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; img-src 'self' data: blob:; font-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; connect-src 'self' ws: wss:; frame-src 'self'; media-src 'self' data: blob:; worker-src 'self' blob:; upgrade-insecure-requests`
- `Strict-Transport-Security: max-age=31536000; includeSubDomains`

Expected on HTTP:

- redirect to HTTPS for deployed hosts
- no HSTS expected on a plain HTTP response

### 2. `/forgot-password`

Expected on HTTPS:

- same header set as `/kodus/`
- exactly one `X-Frame-Options` header

Expected on HTTP:

- redirect to HTTPS for deployed hosts

### 3. Year-selection Route

Route:

- `/select_year`

Expected on HTTPS:

- same header set as `/kodus/`
- POST form contains `csrf_token`

Expected POST behavior:

- valid token: normal year selection flow
- missing/invalid token: request rejected

### 4. `save_location_context.php`

Expected on HTTPS:

- same shared security headers as other PHP routes
- response content type should still be JSON

Expected request requirements:

- method must be `POST`
- same-origin checks apply
- CSRF token required through `X-CSRF-Token` or `csrf_token`

Expected on HTTP:

- redirect to HTTPS for deployed hosts

## Manual Verification Steps

### Header checks

1. Request `GET https://<host>/kodus/`
2. Request `GET https://<host>/kodus/forgot-password`
3. Request `GET https://<host>/kodus/select_year`
4. Inspect response headers for each route

Confirm:

- exactly one `X-Frame-Options`
- exactly one `Content-Security-Policy`
- exactly one `Strict-Transport-Security` on HTTPS
- no duplicate `Referrer-Policy`, `Permissions-Policy`, or `X-Content-Type-Options`

### HTTPS enforcement checks

1. Request `http://<host>/kodus/`
2. Request `http://<host>/kodus/forgot-password`
3. Request `http://<host>/kodus/select_year`
4. Request `http://<host>/kodus/save_location_context.php`

Confirm:

- deployed hosts redirect to HTTPS
- no state-changing content remains directly usable on HTTP

### CSRF checks

1. Open `/kodus/` and inspect the login form
2. Open `/forgot-password` and inspect the reset-request form
3. Open `/select_year` and inspect the year-selection form

Confirm:

- each form includes a hidden `csrf_token`

Then:

1. submit each POST form without the token
2. submit each POST form with an invalid token

Confirm:

- requests are rejected

For `save_location_context.php`:

1. capture the browser request in DevTools
2. confirm it sends `X-CSRF-Token`
3. replay the request without `X-CSRF-Token`

Confirm:

- request is rejected without the CSRF token

### CSP checks

1. Load `/kodus/`, `/forgot-password`, and `/select_year`
2. Check the browser console for CSP violations
3. Exercise AdminLTE, SweetAlert2, DataTables, and current inline-script behavior on the main app

Confirm:

- no functional regressions
- no blocking CSP violations in normal use

## Likely Residual ZAP Findings

These are the findings most likely to remain in a fresh ZAP run, depending on the live server configuration rather than the repo code:

### Likely to remain if Apache/PHP config is unchanged

- `Server Leaks Version Information via "Server" HTTP Response Header Field`
- `Server Leaks Information via "X-Powered-By" HTTP Response Header Field(s)` if php.ini/server config still exposes it

### Possibly remain depending on infrastructure or scan path

- `Strict-Transport-Security Header Not Set` on responses not handled by the PHP bootstrap, such as static assets or non-HTTPS requests
- `HTTPS Content Available via HTTP` if Apache/vhost rules are bypassed, overridden, or inconsistent across hosts
- duplicate-header findings if an upstream layer is still injecting the same headers

### Low-confidence false-positive candidates

- anti-CSRF findings on routes/forms if ZAP misses token handling due to routing or scanner heuristics, even though `csrf_token` is now present and validated on the reviewed forms

## Overall Conclusion

The code-level remediation appears successful.

- duplicate security headers are resolved in the repository
- header emission is centralized
- CSP, HSTS, and X-Frame-Options behavior is correctly implemented at the PHP layer
- the obvious state-changing POST endpoints reviewed in this pass have CSRF protection

Any remaining high-confidence ZAP findings after redeploy are now most likely to be caused by Apache, php.ini, vhost, or proxy configuration rather than missing application changes.
