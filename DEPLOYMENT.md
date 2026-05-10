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

## Persistent Hosting: Railway (Recommended)

This repository is now deployment-ready for Railway using Docker.

### 1) Create Railway Project

1. Go to Railway dashboard and create a new project from GitHub.
2. Select this repository.
3. Railway will detect `railway.toml` and `Dockerfile`.

### 2) Add MySQL Service

1. In the same Railway project, add a MySQL service.
2. Open MySQL service variables and copy:
	- host
	- port
	- database
	- user
	- password

### 3) Configure Web Service Variables

Set these variables in the web service:

```text
APP_BASE_URL=
DB_HOST=<mysql host>
DB_PORT=<mysql port>
DB_NAME=<mysql database>
DB_USER=<mysql user>
DB_PASS=<mysql password>
```

Use empty `APP_BASE_URL` for root-domain deployment.

### 4) Import Database

Use Railway MySQL connect string in a local client and import:

1. `database.sql`
2. `seed.sql`

### 5) Deploy and Open URL

1. Trigger deploy from Railway.
2. Open generated Railway domain.
3. Verify login page at `/auth/login.php`.

## Persistent Hosting: Shared Hosting (cPanel)

1. Create MySQL database and user.
2. Import `database.sql` then `seed.sql` via phpMyAdmin.
3. Upload project files to `public_html` (or subfolder).
4. Set `APP_BASE_URL` to empty for root deploy, or `/subfolder` if needed.
5. Set DB credentials in environment manager or `config/database.php`.
