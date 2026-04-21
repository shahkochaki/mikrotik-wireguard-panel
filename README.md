# WireGuard Panel — راهنمای نصب

پنل مدیریت WireGuard برای میکروتیک RB951 با PHP و MySQL.

---

## پیش‌نیازها

- PHP 8.0 یا بالاتر + extension های `pdo_mysql`، `openssl`
- MySQL / MariaDB
- فعال بودن **API** میکروتیک روی پورت `8728`
- فعال بودن WireGuard روی میکروتیک

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
* * * * * php /var/www/html/wireguard-panel/check_expiry.php >> /var/log/wg_expiry.log 2>&1
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
├── index.php           ← صفحه ورود
├── dashboard.php
├── users.php
├── user_add.php
├── user_edit.php
├── user_delete.php
├── user_toggle.php
├── user_config.php     ← دانلود .conf کلاینت
├── settings.php
├── check_expiry.php
└── logout.php
```
