# Changelog

All notable changes to **WireGuard Panel** are documented here.  
This project follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and [Semantic Versioning](https://semver.org/).

---

## [2.0.0] — 2026-04-22

### Added

- **Multilingual support** — full Persian (فارسی) and English UI with automatic RTL/LTR Bootstrap layout switching.
- **Language switcher** in the top navigation bar; selection persists across sessions and browser restarts via a 1-year browser cookie.
- **Localized WireGuard setup guide** (`wireguard_guide.php`) — step-by-step bilingual instructions for configuring MikroTik WireGuard from scratch.
- **Current IP column** in the users table — shows the live client endpoint address synced from the router.
- **Last Handshake column** in the users table with human-readable relative time (e.g. "3 min ago").
- **Bandwidth tracking columns** in `migration_v2.sql`: `rx_bytes`, `tx_bytes`, `last_handshake`, `endpoint_address`, `endpoint_port`, `current_endpoint_address`, `current_endpoint_port`.
- **Pure PHP WireGuard keypair generation** via X25519 scalar multiplication using the GMP extension — no external binaries required.
- **Extended keypair fallback chain**: `php-sodium` → `wg` CLI → `openssl` CLI → PHP + GMP → PHP openssl extension → Router API.
- `lang/en.php` and `lang/fa.php` translation files covering all pages (login, dashboard, users, settings, import, guide).

### Changed

- Progress bar height for CPU and memory indicators on the dashboard increased from 8 px to 18 px for better readability.
- Last-handshake parsing consolidated to a single, reliable format; legacy format variants removed.
- `check_expiry.php` relocated to the `cron/` directory; all documentation and path references updated accordingly.
- All page titles now use translation functions (`__('page_*')`) instead of hard-coded strings.
- Code structure refactored across multiple files for improved readability and maintainability.

### Fixed

- Output buffer flushed before sending `.conf` files to eliminate BOM characters that corrupted WireGuard imports.
- Error message formatting corrected in keypair generation failure paths.
- `require_once` paths in `cron/check_expiry.php` corrected after directory relocation.
- Language switcher dropdown alignment corrected; user expiry badge display indentation fixed.

### Migration

If upgrading from v1.x, run the migration script once:

```bash
mysql -u root -p wireguard_panel < sql/migration_v2.sql
```

---

## [1.2.0] — 2026-04-21

> Internal release; no feature changes over v1.1.0.

---

## [1.1.0] — 2026-04-21

### Added

- **Peer management** — add, edit, enable/disable, and delete WireGuard peers via the MikroTik RouterOS API.
- **Dashboard** — live router stats card (identity, uptime, RouterOS version, board name, peer count, CPU load, memory usage) loaded asynchronously so the page never blocks.
- **Stat cards** — total users, active users, and expired users counts at a glance.
- **Recent users table** — last 5 added peers with status and quick-edit link on the dashboard.
- **User management pages** — `user_add.php`, `user_edit.php`, `user_delete.php`, `user_toggle.php`.
- **Bulk import** (`user_import.php`) — discover existing WireGuard peers on the router and import them into the panel database.
- **Downloadable client config** (`user_config.php`) — generates a ready-to-import `.conf` file for each peer.
- **Speed limiting** — per-user upload/download limits enforced via MikroTik Simple Queues.
- **Data quota & expiry** — set GB limits and expiry dates; the cron job disables users automatically when limits are exceeded.
- **Cron job** (`cron/check_expiry.php`) — syncs RX/TX bytes from the router and disables expired or over-quota users. CLI-only (direct HTTP access returns 403).
- **Settings page** — router IP, API port, credentials, WireGuard interface name, server public key, endpoint, DNS, and subnet; includes a step-by-step **Test Connection** diagnostic.
- **CSRF protection** — all state-changing forms and AJAX calls are CSRF-token guarded.
- **AJAX endpoints** — `ajax_actions.php` (toggle), `ajax_peer_stats.php` (live RX/TX), `ajax_router_info.php` (system info), `ajax_test_router.php` (diagnostics).
- **Flash messages** — success/error alerts that auto-dismiss after 4 seconds.
- **Sidebar navigation** with mobile toggle support.
- Initial database schema (`sql/database.sql`) with `admins`, `wg_users`, and `settings` tables.
- Default admin account: username `admin`, password `admin123`.

---

## [1.0.0] — 2026-04-20

- Initial private release.
