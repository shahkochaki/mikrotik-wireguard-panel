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

## نصب

### ۱. فعال‌سازی API میکروتیک

در Winbox یا ترمینال:

```
/ip service enable api
```

مطمئن شوید IP پنل شما اجازه اتصال دارد:

```
/ip service set api address=<IP-PANEL>/32
```

### ۲. آماده‌سازی دیتابیس

```bash
mysql -u root -p < sql/database.sql
```

### ۳. تنظیم config

فایل `includes/config.php` را باز کرده و مقادیر دیتابیس را ویرایش کنید:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'wireguard_panel');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
```

### ۴. اجرا

فایل‌ها را روی وب‌سرور (Apache / Nginx) آپلود کنید.

### ۵. ورود اول

- **URL:** `http://your-server/wireguard-panel/`
- **Username:** `admin`
- **Password:** `admin123`

> ⚠️ بلافاصله پس از ورود رمز عبور را تغییر دهید.

---

## تنظیمات اولیه (صفحه Settings)

| فیلد                   | توضیح                                    |
| ---------------------- | ---------------------------------------- |
| آدرس IP روتر           | IP داخلی میکروتیک (معمولاً ۱۹۲.۱۶۸.۸۸.۱) |
| نام کاربری میکروتیک    | user با دسترسی API                       |
| نام اینترفیس WireGuard | مثلاً `wireguard1`                       |
| Public Key سرور        | کلید عمومی اینترفیس WireGuard روتر       |
| Endpoint               | IP عمومی یا دامنه روتر برای کلاینت‌ها    |

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
