# KODUS

KODUS stands for `KliMalasakit Online Document Updating System`. This repository contains the PHP-based web application prepared for formal GitLab submission and departmental review.

## Project Summary

KODUS is a role-based internal web application for document updating, tracking, reporting, and administrative management. The codebase is structured as a server-rendered PHP application with shared helper modules, MySQL connectivity, and spreadsheet-based import or export utilities.

Current application metadata from [`app_meta.php`](/c:/laragon/www/kodus/app_meta.php:1):

- Version: `2.4.6`
- Codename: `Control Center`
- Release date: `2026-03-30`

## Implemented Modules

The following items are based on modules and entry points present in this repository:

- User authentication with session handling, remember-me support, password recovery, and password reset flows
- Optional Caraga Connect SSO integration
- Optional two-factor authentication with QR setup, verification, recovery codes, and admin reset actions
- Role-aware access control and administration features for user management, classification, restore, deactivation, and audit logs
- Mailbox and internal messaging screens with attachments, read or trash state, and reply handling
- Application notification feed and notification history
- Document and data tracking pages for incoming, outgoing, and MEB-related records
- Beneficiary profile, sectoral summary, and PWD summary pages with export support
- Calendar and event scheduling pages
- Payout and fund monitoring pages
- Implementation status pages for baseline targets, program activities, project location records, project location maps, and summary templates
- Crossmatching utilities for uploaded datasets
- Deduplication utilities and template generation
- Spreadsheet import and export workflows powered by PhpSpreadsheet
- Maintenance mode controls and shared security helpers

## Technical Notes

- Application stack: PHP with MySQL or MariaDB
- Frontend pattern: server-rendered pages using AdminLTE, Bootstrap, jQuery, and bundled plugins
- Dependency management: Composer is used for PHP packages; `package.json` is present for frontend asset tooling inherited from the AdminLTE-based project structure
- Environment loading: `.env` values are read through `vlucas/phpdotenv`
- Hosting target for submission: Linux with Nginx and PHP-FPM

Primary PHP dependencies declared in [`composer.json`](/c:/laragon/www/kodus/composer.json:1):

- `phpoffice/phpspreadsheet`
- `phpmailer/phpmailer`
- `vlucas/phpdotenv`
- `pragmarx/google2fa`
- `bacon/bacon-qr-code`

## Repository Layout

- [`index.php`](/c:/laragon/www/kodus/index.php:1): login entry page
- [`config.php`](/c:/laragon/www/kodus/config.php:1): environment loading, database connection, schema bootstrapping, and runtime safeguards
- [`header.php`](/c:/laragon/www/kodus/header.php:1): authenticated page bootstrap, shared guards, and common assets
- [`admin/`](/c:/laragon/www/kodus/admin): administrative pages
- [`pages/`](/c:/laragon/www/kodus/pages): primary transaction and reporting pages
- [`inbox/`](/c:/laragon/www/kodus/inbox): mailbox and messaging features
- [`notifications/`](/c:/laragon/www/kodus/notifications): notification history and actions
- [`crossmatch/`](/c:/laragon/www/kodus/crossmatch): dataset crossmatching tools
- [`deduplication/`](/c:/laragon/www/kodus/deduplication): deduplication tools and template generation
- [`implementation-status/`](/c:/laragon/www/kodus/implementation-status): implementation status and target-tracking pages
- [`docs/LINUX_NGINX_DEPLOYMENT.md`](/c:/laragon/www/kodus/docs/LINUX_NGINX_DEPLOYMENT.md:1): deployment notes already maintained in the repository
- [`deployment/nginx/crg-kodus.conf.example`](/c:/laragon/www/kodus/deployment/nginx/crg-kodus.conf.example:1): example Nginx site configuration

## Environment Configuration

Use [`.env.example`](/c:/laragon/www/kodus/.env.example:1) as the reference for local or server configuration. The example file currently includes settings for:

- database connection
- application base path
- SMTP mail delivery
- Caraga Connect SSO
- optional socket bridge integration
- optional KODA scene settings

The repository does not include live secrets and `.env` should remain untracked.

## Hosting Notes

This repository already includes Linux and Nginx deployment notes. Based on the current codebase and documentation:

- the intended deployment model is PHP on Linux with Nginx and PHP-FPM
- the app currently assumes deployment under a `/kodus` URL path in the documented Nginx setup
- Apache `.htaccess` files are present in the repository, but Nginx rules must be configured separately

Refer to [`docs/LINUX_NGINX_DEPLOYMENT.md`](/c:/laragon/www/kodus/docs/LINUX_NGINX_DEPLOYMENT.md:1) for the existing server-side notes.

## Submission Notes

- This repository should track source code, reviewed static assets, and supporting documentation
- Runtime uploads, generated outputs, local caches, SQL dumps, and secrets should not be committed
- The included GitLab CI configuration is limited to safe validation checks and does not perform deployment
