# WireGuard Panel

A web-based WireGuard VPN management panel for MikroTik routers, built with PHP and MySQL.  
Manage peers, monitor bandwidth, enforce data quotas and expiry dates — all from a clean dashboard.

---

## Features

- **Peer management** — add, edit, enable/disable, and delete WireGuard peers via MikroTik API
- **Automatic keypair generation** — uses `php-sodium`, `wg` CLI, `openssl`, PHP GMP, or the router itself as fallback
- **Downloadable `.conf` files** — generate ready-to-import WireGuard client configs
- **Speed limiting** — per-user upload/download limits enforced via MikroTik Simple Queues
- **Data quota & expiry** — set GB limits and expiry dates; expired/over-quota users are disabled automatically
- **Live stats** — real-time RX/TX bytes and last-handshake time pulled from the router
- **Dashboard** — router identity, CPU load, memory usage, uptime, and peer count at a glance
- **Diagnostics** — step-by-step connectivity test (TCP reachability → API login → WireGuard interface check)
- **Bulk import** — import multiple peers at once
- **CSRF protection** — all state-changing forms are CSRF-token guarded

---

## Requirements

| Component         | Minimum version         |
| ----------------- | ----------------------- |
| PHP               | 8.0                     |
| MySQL / MariaDB   | 5.7 / 10.3              |
| MikroTik RouterOS | 7.x (WireGuard support) |
| Web server        | Apache 2.4+ or Nginx    |

### Required PHP extensions

| Extension   | Purpose                                      |
| ----------- | -------------------------------------------- |
| `pdo_mysql` | Database access                              |
| `openssl`   | TLS / key parsing                            |
| `sodium`    | WireGuard keypair generation _(recommended)_ |
| `gmp`       | Fallback keypair generation via pure PHP     |

> **Keypair generation priority:** `php-sodium` → `wg` CLI → `openssl` CLI → PHP+GMP → PHP openssl extension → Router API.  
> At least one of `sodium` or `gmp` should be available for fully offline key generation.

### MikroTik requirements

- RouterOS **7.x** or later (WireGuard interface support)
- **API service** enabled on port `8728`
- Admin user with full API access
- A configured WireGuard interface (e.g. `wireguard1`)

---

## Installation

### 1. Enable MikroTik API

In Winbox terminal or SSH:

```
/ip service enable api
/ip service set api port=8728
```

Restrict API access to your panel server IP for security:

```
/ip service set api address=<PANEL-SERVER-IP>/32
```

### 2. Create the WireGuard Interface (if not already done)

```
/interface wireguard add name=wireguard1 listen-port=51820
/interface wireguard print
```

Note the `public-key` shown — you will need it in Settings.

### 3. Set up the database

Create a database and user, then import the schema:

```bash
mysql -u root -p -e "CREATE DATABASE wireguard_panel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p wireguard_panel < sql/database.sql
```

If upgrading from v1, also run:

```bash
mysql -u root -p wireguard_panel < sql/migration_v2.sql
```

### 4. Configure the application

Open `includes/config.php` and set your database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'wireguard_panel');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
```

Adjust the timezone if needed (default is `Asia/Tehran`):

```php
date_default_timezone_set('Asia/Tehran');
```

### 5. Deploy to your web server

Upload all files to your web server's document root or a subdirectory.

**Apache** — make sure `mod_rewrite` is enabled and `AllowOverride All` is set.  
**Nginx** — add a `try_files $uri $uri/ /index.php?$query_string;` rule.

Protect sensitive directories by adding to your web server config (or `.htaccess`):

```apache
# .htaccess in project root
<FilesMatch "\.(php)$">
    # allow access only to the panel/ entry points
</FilesMatch>

# Block direct access to includes/ and lib/
<DirectoryMatch "^.*(includes|lib)$">
    Require all denied
</DirectoryMatch>
```

### 6. First login

| Field    | Value                                 |
| -------- | ------------------------------------- |
| URL      | `http://your-server/wireguard-panel/` |
| Username | `admin`                               |
| Password | `admin123`                            |

> ⚠️ **Change the default password immediately** after your first login via the Settings page.

---

## Settings Page

After logging in, go to **Settings** to connect the panel to your router.

| Field               | Description                                                               |
| ------------------- | ------------------------------------------------------------------------- |
| Router IP           | Internal IP of the MikroTik (e.g. `192.168.88.1`)                         |
| API Port            | RouterOS API port (default `8728`)                                        |
| Username            | MikroTik user with API access                                             |
| Password            | MikroTik user password                                                    |
| WireGuard Interface | Interface name on the router (e.g. `wireguard1`)                          |
| Server Public Key   | Public key of the WireGuard interface (from `/interface wireguard print`) |
| Endpoint            | Public IP or domain + port clients will connect to (e.g. `1.2.3.4:51820`) |
| DNS                 | DNS server pushed to clients (e.g. `1.1.1.1`)                             |
| Subnet              | IP pool for peers (e.g. `10.0.0.0/24`)                                    |

Use the **Test Connection** button to run a step-by-step diagnostic (TCP → login → WireGuard interface check).

---

## Cron Job (انقضا خودکار)

```bash
* * * * * php /var/www/html/wireguard-panel/cron/check_expiry.php >> /var/log/wg_expiry.log 2>&1
```

---

## امنیت

- فایل `.htaccess` برای محدود کردن دسترسی به پوشه `includes/` و `lib/` اضافه کنید
- از HTTPS استفاده کنید
- رمز پیش‌فرض را تغییر دهید

---

## ساختار پروژه

```
wireguard-panel/
├── assets/
│   ├── css/style.css
│   └── js/main.js
├── includes/
│   ├── config.php      ← تنظیمات دیتابیس
│   ├── db.php          ← اتصال PDO
│   ├── auth.php        ← ورود / خروج / CSRF
│   ├── mikrotik.php    ← API میکروتیک
│   └── functions.php   ← توابع کمکی
├── lib/
│   └── RouterosAPI.php ← کلاینت RouterOS API
├── templates/
│   ├── header.php
│   └── footer.php
├── sql/
│   └── database.sql
├── cron/
│   └── check_expiry.php
├── index.php           ← صفحه ورود
├── dashboard.php
├── users.php
├── user_add.php
├── user_edit.php
├── user_delete.php
├── user_toggle.php
├── user_config.php     ← دانلود .conf کلاینت
├── settings.php
└── logout.php
```
