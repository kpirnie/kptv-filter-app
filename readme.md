# KPTV Filter App (KPTV Stream Manager)

A self-hosted PHP app for managing IPTV providers, filtering streams, and exporting playlists—either as M3U **or** via an **Xtream Codes–compatible API** endpoint for popular IPTV clients.

Repo: <https://github.com/kpirnie/kptv-filter-app>

> **Disclaimer / Intent**
>
> This project is a stream *manager* and playlist/API *exporter*. It does **not** host content. Use it for legitimate IPTV purposes only.

---

## Features

- **Multi-provider support**
  - Xtream Codes API providers (XC)
  - Direct M3U playlist URL providers
- **Stream library management**
  - Organize streams into types (Live / Series / VOD / Other)
  - Toggle streams active/inactive
  - Browser-based playback testing (HLS `.m3u8` / MPEG-TS `.ts`)
- **Filtering engine (sync-time)**
  - Include name (regex)
  - Exclude name (simple substring)
  - Exclude name (regex)
  - Exclude stream URL (regex)
  - Exclude group/category (regex)
- **Export**
  - **M3U playlist export** (per-user / per-provider)
  - **Xtream Codes API emulation** (`/player_api.php` + stream redirect endpoints)
- **CLI sync toolkit**
  - Pull streams from providers → store in DB
  - Missing stream checks, metadata fixups, cleanup helpers
- **Optional maintenance mode**
  - Toggle via `.maintenance.json`

---

## Requirements

- **PHP 8.4+** (required by `composer.json`)
- PHP extensions typically needed:
  - `openssl`, `curl`, `json`, `mbstring`, `pdo` (+ driver: `pdo_sqlite` or `pdo_mysql`)
- **Composer** (recommended even if `vendor/` exists)
- Web server:
  - Nginx or Apache (HTTPS strongly recommended)
- Database:
  - **SQLite** (simple/local) or **MySQL/MariaDB**

---

## Quick start

### 1) Clone the repo

```bash
git clone https://github.com/kpirnie/kptv-filter-app.git
cd kptv-filter-app
```

### 2) Install PHP dependencies

```bash
composer install --no-dev --optimize-autoloader
```

> `vendor/` may already be present, but running Composer ensures dependencies match your environment.

### 3) Create `config.json`

This app expects a `config.json` in the project root (same directory as `index.php`).

Create `config.json` with values appropriate for your deployment. Example:

```json
{
  "appname": "KPTV Stream Manager",
  "debug_app": false,

  "mainuri": "https://your-domain.example/",
  "xcuri": "https://your-domain.example/xc",

  "mainkey": "CHANGE_ME_TO_A_LONG_RANDOM_STRING",
  "mainsecret": "CHANGE_ME_TO_ANOTHER_LONG_RANDOM_STRING",

  "database": {
    "driver": "sqlite",
    "path": "/var/lib/kptv/kptv.sqlite",
    "tbl_prefix": "kptv_"
  },

  "smtp": {
    "debug": false,
    "server": "smtp.your-domain.example",
    "security": "tls",
    "port": 587,
    "username": "smtp-user",
    "password": "smtp-pass",
    "fromemail": "no-reply@your-domain.example",
    "fromname": "KPTV Stream Manager",
    "forcehtml": true
  }
}
```

**Notes**

- `mainkey` and `mainsecret` are used for encrypting/decrypting IDs used in exported URLs and app auth tokens. Use strong random values.
- The login cookie is set with `secure: true`, so **HTTPS is expected**.

### 4) Initialize the database

#### Option A: SQLite

1) Create the directory for the DB file and make it writable by your PHP-FPM/web user.
2) Create/import schema using [`assets/schema_sqlite.sql`](https://github.com/kpirnie/kptv-filter-app/blob/main/assets/schema_sqlite.sql):

```bash
mkdir -p /var/lib/kptv
sqlite3 /var/lib/kptv/kptv.sqlite < assets/schema_sqlite.sql
```

#### Option B: MySQL/MariaDB

1) Create database + user.
2) Import schema from [`assets/schema.sql`](https://github.com/kpirnie/kptv-filter-app/blob/main/assets/schema.sql).

```bash
mysql -u root -p < assets/schema.sql
```

> The MySQL schema includes stored procedures used by the CLI cleanup action.

### 5) Point your web server at the project root

Your **document root** should be the repo root (the folder containing `index.php`).

---

## Web server setup

### Nginx

A sample include file is provided: [`.nginx.conf`](https://github.com/kpirnie/kptv-filter-app/blob/main/.nginx.conf)

Typical pattern (high level):

- `try_files $uri $uri/ /index.php?$query_string;`
- Block sensitive paths (like `config.json`, `.cache/`, etc.)

> Use the repo’s `.nginx.conf` as your starting point and merge it into your site/server block as appropriate.

### Apache

You’ll need rewrite rules to route requests to `index.php` for “pretty” URLs. A minimal example:

```apacheconf
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

---

## Using the app

### 1) Create an account and log in

- Register at `/users/register`
- Activate via email (SMTP settings required)
- Log in at `/users/login`

### 2) Add providers

Go to: **Your Streams → Your Providers**

Supported provider types:

- Xtream Codes API (XC)
- M3U URL

### 3) Add filters (optional)

Go to: **Your Streams → Your Filters**

Filters apply during sync (CLI). If a provider has filtering disabled, all its streams are processed.

### 4) Sync streams from your providers (CLI)

The sync tool is: [`sync/kptv-sync.php`](https://github.com/kpirnie/kptv-filter-app/blob/main/sync/kptv-sync.php)

```bash
php sync/kptv-sync.php sync
```

Helpful actions:

```bash
php sync/kptv-sync.php testmissing
php sync/kptv-sync.php fixup
php sync/kptv-sync.php cleanup
```

Common options:

```bash
php sync/kptv-sync.php sync --user-id 1
php sync/kptv-sync.php sync --provider-id 32
php sync/kptv-sync.php testmissing --check-all
php sync/kptv-sync.php fixup --ignore logo,channel
```

### 5) Automate sync with cron (example)

Run every 6 hours:

```cron
0 */6 * * * /usr/bin/php /var/www/kptv-filter-app/sync/kptv-sync.php sync >/dev/null 2>&1
```

---

## Exporting to IPTV apps

KPTV exposes an Xtream Codes–compatible API and stream redirect endpoints.

### Xtream Codes API endpoints

You can use any of these:

- `/player_api.php` (common XC endpoint)
- `/xc` (short endpoint)
- `/api/xtream` (legacy endpoint)

Examples:

- `https://your-domain.example/player_api.php`
- `https://your-domain.example/xc`

### IPTV app login mapping (IMPORTANT)

KPTV authenticates like this:

- **Username** = **Provider ID** (numeric)
- **Password** = **Encrypted User Token** (your user ID encrypted via `mainkey/mainsecret`)

In most IPTV apps that support Xtream Codes:

- **Server/Host/URL**: `https://your-domain.example`  
  (apps that auto-append `/player_api.php` will work against KPTV’s `/player_api.php`)
- **Username**: provider ID (from the Providers page)
- **Password**: your encrypted user token (from the app UI)

> If your IPTV app allows specifying the full API path, you can set the server URL to `https://your-domain.example/xc` instead.

### Stream redirect endpoints

These are used by IPTV clients automatically:

- `/live/{username}/{password}/{streamId}`
- `/movie/{username}/{password}/{streamId}`
- `/series/{username}/{password}/{streamId}`

KPTV redirects (302) to the actual provider stream URL stored in your database.

---

## M3U playlist export

The app also supports direct M3U exports via routes under:

- `/playlist/{user}/{which}`
- `/playlist/{user}/{provider}/{which}`

Where:

- `{user}` is your encrypted user token
- `{provider}` is a provider ID (numeric)
- `{which}` is one of: `live`, `series`, `vod`

---

## Built-in stream proxy (for browser playback / CORS)

The app includes a lightweight proxy endpoint:

- `/proxy/stream?url=<encoded-stream-url>`

It can rewrite HLS playlists (`.m3u8`) to proxy segment URLs, helping browser playback where CORS would otherwise block requests.

**Security note:** the proxy can be abused if left wide open. Review `controllers/kptv-proxy.php` and consider restricting allowed domains.

---

## Maintenance mode

A sample file exists: [`.maintenance.json`](https://github.com/kpirnie/kptv-filter-app/blob/main/.maintenance.json)

If enabled, the router returns a `503` with a JSON payload unless the client IP matches an allowed CIDR/IP.

---

## Front-end assets (optional)

This repo includes a basic build pipeline (Tailwind + PostCSS). If you want to rebuild assets:

```bash
npm install
npm run build
```

See [`package.json`](https://github.com/kpirnie/kptv-filter-app/blob/main/package.json) for scripts.

---

## Troubleshooting

- **Blank pages / 500 errors**
  - Ensure `config.json` exists and is readable by PHP.
  - Verify PHP version is 8.4+ and required extensions are installed.
- **Can’t log in / cookies not sticking**
  - The auth cookie is marked `secure`, so you must serve over **HTTPS**.
- **No streams appear after adding providers**
  - Add providers in the UI, then run the CLI sync: `php sync/kptv-sync.php sync`
- **SMTP/activation emails fail**
  - Double-check the `smtp` object in `config.json` and firewall access to your SMTP host.

---

## Contributing

PRs/issues are welcome:

- Issues: <https://github.com/kpirnie/kptv-filter-app/issues>

---

## License

This project is licensed under the **MIT License** — see the [LICENSE](LICENSE) file for details.  
GitHub: <https://github.com/kpirnie/kptv-filter-app/blob/main/LICENSE>
