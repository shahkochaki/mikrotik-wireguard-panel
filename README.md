# WireGuard Panel

![Version](https://img.shields.io/badge/version-2.0.0-blue)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php)
![License](https://img.shields.io/badge/license-MIT-green)

A web-based WireGuard VPN management panel for MikroTik routers, built with PHP and MySQL.  
Manage peers, monitor bandwidth, enforce data quotas and expiry dates — all from a clean dashboard.

> **Changelog:** see [CHANGELOG.md](CHANGELOG.md) for a full version history.

---

## Features

- **Peer management** — add, edit, enable/disable, and delete WireGuard peers via MikroTik API
- **Automatic keypair generation** — uses `php-sodium`, `wg` CLI, `openssl`, PHP GMP, or the router itself as fallback
- **Downloadable `.conf` files** — generate ready-to-import WireGuard client configs
- **Speed limiting** — per-user upload/download limits enforced via MikroTik Simple Queues
- **Data quota & expiry** — set GB limits and expiry dates; expired/over-quota users are disabled automatically
- **Live stats** — real-time RX/TX bytes, last-handshake time, and current client IP pulled from the router
- **Dashboard** — router identity, CPU load, memory usage, uptime, and peer count at a glance
- **Diagnostics** — step-by-step connectivity test (TCP reachability → API login → WireGuard interface check)
- **Bulk import** — discover and import existing peers from the router
- **Multilingual UI** — Persian (فارسی, RTL) and English (LTR) with a language switcher; preference saved in a browser cookie
- **CSRF protection** — all state-changing forms and AJAX calls are CSRF-token guarded

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
    # allow access only to the administrator/ entry points
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

## WireGuard Setup Tutorial

This section shows a typical MikroTik WireGuard setup before you start managing peers from the panel.

### 1. Create the WireGuard interface on MikroTik

Use Winbox terminal or SSH:

```rsc
/interface wireguard add name=wireguard1 listen-port=51820 mtu=1420
```

Verify the interface and copy its public key:

```rsc
/interface wireguard print detail
```

You will use this `public-key` value in the panel's **Server Public Key** field.

### 2. Assign an IP address to the WireGuard interface

Pick a VPN subnet that does not overlap with your LAN. Example:

```rsc
/ip address add address=10.10.10.1/24 interface=wireguard1 comment="WireGuard subnet"
```

In this example:

- Router WireGuard IP: `10.10.10.1`
- Client IP pool: `10.10.10.0/24`

Set the same subnet in the panel if your Settings page includes a subnet or address pool field.

### 3. Allow WireGuard traffic through the firewall

Open the WireGuard UDP port on the WAN interface:

```rsc
/ip firewall filter add chain=input action=accept protocol=udp dst-port=51820 comment="Allow WireGuard"
```

If your firewall is strict, make sure established and related traffic is already allowed.

### 4. Enable internet access for VPN clients

If clients should reach the internet through the router, add a masquerade rule:

```rsc
/ip firewall nat add chain=srcnat action=masquerade src-address=10.10.10.0/24 out-interface-list=WAN comment="WG NAT"
```

If you do not use interface lists, replace `out-interface-list=WAN` with your actual WAN interface name.

### 5. Add routes or LAN access rules if needed

If clients must access internal subnets behind the MikroTik, ensure those subnets are permitted by your firewall. A common case is allowing access from the WireGuard subnet to the LAN:

```rsc
/ip firewall filter add chain=forward action=accept src-address=10.10.10.0/24 dst-address=192.168.88.0/24 comment="WG to LAN"
```

Adjust both subnets to match your environment.

### 6. Configure the panel settings

After the router is ready, open **Settings** in the panel and enter:

| Field               | Example                                           |
| ------------------- | ------------------------------------------------- |
| Router IP           | `192.168.88.1`                                    |
| API Port            | `8728`                                            |
| Username            | `admin` or another API-enabled user               |
| WireGuard Interface | `wireguard1`                                      |
| Server Public Key   | output from `/interface wireguard print detail`   |
| Endpoint            | `vpn.example.com:51820` or `YOUR_PUBLIC_IP:51820` |
| DNS                 | `1.1.1.1` or your internal DNS                    |
| Subnet              | `10.10.10.0/24`                                   |

Use **Test Connection** to verify API connectivity and confirm that the WireGuard interface exists.

### 7. Create the first peer from the panel

Once settings are saved:

1. Open the users page.
2. Add a new user/peer.
3. Assign an IP such as `10.10.10.2/32`.
4. Set optional speed, quota, and expiry limits.
5. Download the generated client configuration.

The panel will:

- generate the keypair,
- add the peer to MikroTik,
- optionally create a Simple Queue,
- and provide a ready-to-import `.conf` file.

### 8. Import the client configuration

On the client device:

1. Install the official WireGuard application.
2. Create a new tunnel.
3. Import the generated `.conf` file from the panel.
4. Activate the tunnel.

Typical client config values look like this:

```ini
[Interface]
PrivateKey = <client-private-key>
Address = 10.10.10.2/32
DNS = 1.1.1.1

[Peer]
PublicKey = <server-public-key>
AllowedIPs = 0.0.0.0/0, ::/0
Endpoint = vpn.example.com:51820
PersistentKeepalive = 25
```

Use `AllowedIPs = 0.0.0.0/0, ::/0` for full-tunnel VPN traffic, or restrict it to internal subnets for split tunneling.

### 9. Verify the tunnel

On MikroTik:

```rsc
/interface wireguard peers print detail
```

Check these values:

- `last-handshake` should update after the client connects
- RX/TX counters should increase when traffic passes
- the peer should not be disabled

You can also confirm the same information from the panel's live stats and dashboard widgets.

### 10. Common deployment notes

- If the router is behind another modem/router, forward UDP `51820` to the MikroTik.
- If your public IP changes, use a DNS record or dynamic DNS hostname in the panel's **Endpoint** field.
- Keep the VPN subnet separate from your LAN subnet to avoid routing conflicts.
- For mobile clients, `PersistentKeepalive = 25` is usually recommended.

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

## Cron Job (Auto-expiry & Quota Enforcement)

The cron script syncs bandwidth stats from the router and automatically disables users that have:

- exceeded their data quota, or
- passed their expiry date.

Add this line to your crontab (`crontab -e`):

```bash
* * * * * php /var/www/html/wireguard-panel/cron/check_expiry.php >> /var/log/wg_expiry.log 2>&1
```

The script only runs from CLI. Direct HTTP access is blocked with a 403 response.

---

## Security Recommendations

- **Change the default `admin` password** on first login
- Serve the panel over **HTTPS** (Let's Encrypt / your own certificate)
- Restrict MikroTik API access to the panel server IP only
- Deny direct HTTP access to `includes/` and `lib/` directories
- Keep PHP, MySQL, and RouterOS up to date
- Consider placing the panel behind a VPN or firewall so it is not exposed to the public internet

---

## Project Structure

```
wireguard-panel/
├── administrator/
│   ├── index.php              ← Login page
│   ├── dashboard.php          ← Overview & live router stats
│   ├── users.php              ← Peer list
│   ├── user_add.php           ← Add new peer
│   ├── user_edit.php          ← Edit peer
│   ├── user_delete.php        ← Delete peer
│   ├── user_toggle.php        ← Enable / disable peer
│   ├── user_config.php        ← Download client .conf file
│   ├── user_import.php        ← Bulk peer import
│   ├── settings.php           ← Panel & router settings
│   ├── logout.php             ← Session logout
│   ├── wireguard_guide.php    ← Step-by-step MikroTik WireGuard guide
│   ├── ajax_actions.php       ← AJAX endpoint (general)
│   ├── ajax_peer_stats.php    ← AJAX endpoint (live peer stats)
│   ├── ajax_router_info.php   ← AJAX endpoint (router system info)
│   └── ajax_test_router.php   ← AJAX endpoint (diagnostics)
├── assets/
│   ├── css/style.css          ← Stylesheet
│   └── js/main.js             ← Front-end scripts
├── cron/
│   └── check_expiry.php       ← Expiry / quota enforcement (CLI only)
├── includes/
│   ├── config.php             ← App & database configuration
│   ├── db.php                 ← PDO connection + helpers
│   ├── auth.php               ← Login / logout / CSRF
│   ├── lang.php               ← i18n loader & language helpers
│   ├── mikrotik.php           ← MikroTik API wrapper (peers, queues, stats)
│   └── functions.php          ← Shared helper functions
├── lang/
│   ├── en.php                 ← English translations
│   └── fa.php                 ← Persian (فارسی) translations
├── lib/
│   └── RouterosAPI.php        ← Low-level RouterOS API client
├── sql/
│   ├── database.sql           ← Initial schema
│   └── migration_v2.sql       ← Schema upgrade from v1.x
├── templates/
│   ├── header.php             ← Shared HTML header / nav
│   └── footer.php             ← Shared HTML footer
└── CHANGELOG.md               ← Version history
```

---

## Troubleshooting

| Symptom                       | Likely cause                       | Fix                                                 |
| ----------------------------- | ---------------------------------- | --------------------------------------------------- |
| "MikroTik connection failed"  | API service disabled or wrong port | Run `/ip service enable api` on the router          |
| "Port closed or unreachable"  | Firewall blocking port 8728        | Allow TCP 8728 from the panel server IP             |
| "Login error"                 | Wrong username or password         | Check credentials in Settings                       |
| WireGuard interface not found | Wrong interface name               | Verify with `/interface wireguard print`            |
| Keypair generation fails      | No suitable PHP extension          | Install `php-sodium` or `php-gmp`                   |
| Client can't connect          | Wrong endpoint / public key        | Re-check Endpoint and Server Public Key in Settings |

---

## Multilingual Support

The panel ships with **Persian (فارسی)** and **English** translations.  
Switch language from the top-right globe icon — the preference is saved in a browser cookie for 1 year.

| Language | Code | Direction |
| -------- | ---- | --------- |
| English  | `en` | LTR       |
| فارسی    | `fa` | RTL       |

To add a new language, copy `lang/en.php` to `lang/xx.php`, translate the strings, and register the locale in `LANG_SUPPORTED` inside `includes/lang.php`.

---

## License

MIT — see [LICENSE](LICENSE) for details.
