# Public Deployment Guide

This project is a PHP + MySQL web app. It cannot run on GitHub Pages because GitHub Pages only serves static files.

## Recommended Hosting

Use any public host that supports:

- PHP 8+
- MySQL or MariaDB
- phpMyAdmin or MySQL import
- Environment variables or editable PHP files

Typical options are cPanel shared hosting, Hostinger, Namecheap shared hosting, InfinityFree, Railway with a PHP setup, or any VPS with Apache/Nginx + PHP + MySQL.

## Files to Upload

Upload everything in this repository except:

- `.git/`
- `vendor/` if your host can run `composer install`
- `exports/`
- `uploads/`
- `.env`

If your host cannot run Composer, upload `vendor/` too.

## Database Setup

1. Create a MySQL database on the host.
2. Create a database user and password.
3. Import `database.sql` into that database.

## Configure the App

Set these environment variables on the host if supported:

```text
APP_BASE_URL=
DB_HOST=your_mysql_host
DB_PORT=3306
DB_NAME=your_database_name
DB_USER=your_database_user
DB_PASS=your_database_password
```

For a domain root deployment, keep `APP_BASE_URL` empty.

For a subfolder deployment, set it to the subfolder path, for example:

```text
APP_BASE_URL=/atm-audit-liquidation-system
```

If your host does not support environment variables, edit `config/database.php` and replace the default values in the `define(...)` lines.

## Fixed Logins

- Admin: `ADMIN` / `Admin123`
- Kendra: `Kendra` / `Kendra123`
- Roberto: `Roberto` / `Roberto123`

Change these passwords after deployment if the site will be publicly accessible.
