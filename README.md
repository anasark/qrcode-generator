# QR Code Generator

A full-featured, beautifully designed QR code generator with URL shortening, scan analytics, and visitor tracking. Built with **Vue 3**, **Tailwind CSS**, and **PHP + MySQL** — easy to deploy on any hosting environment.

![QR Code Generator](https://img.shields.io/badge/Vue%203-CDN-42b883?logo=vuedotjs) ![Tailwind CSS](https://img.shields.io/badge/Tailwind%20CSS-CDN-06b6d4?logo=tailwindcss) ![PHP](https://img.shields.io/badge/PHP-7.4+-777bb4?logo=php) ![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479a1?logo=mysql)

**Live Demo:** [https://qrcode.anasabdur.com](https://qrcode.anasabdur.com/)

## Features

- **6 QR Code Types** — URL, Text, Email, Phone, WiFi, vCard
- **Customization** — Colors, size, margin, error correction level, center logo overlay
- **Color Presets** — One-click professional color schemes
- **Multiple Download Formats** — PNG, SVG, JPEG, WebP
- **Copy to Clipboard** — Instant clipboard copy
- **Batch Generation** — Generate multiple QR codes at once
- **History** — Automatically saves generated QR codes (localStorage)
- **URL Shortening** — Trackable short links via `yoursite.com/r/abc123`
- **Scan Analytics** — Dashboard with charts for scan tracking (Chart.js)
- **Visitor Tracking** — Page visitor analytics with geo, browser, OS, device data
- **Auth Protection** — Login-protected analytics dashboards
- **Dark Mode** — Toggle between light and dark themes
- **Glassmorphism Design** — Modern glass-card UI with animated orbs
- **SEO Optimized** — Meta tags, Open Graph, Twitter Cards, JSON-LD, sitemap
- **PWA Ready** — Manifest and favicon included
- **Responsive** — Works on all screen sizes

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | Vue 3 (CDN), Tailwind CSS (CDN), Chart.js (CDN) |
| QR Library | [node-qrcode](https://github.com/soldair/node-qrcode) v1.4.4 (self-hosted UMD) |
| Backend | PHP 7.4+ |
| Database | MySQL 5.7+ / MariaDB 10.3+ |
| Server | Apache with mod_rewrite (or Nginx) |

## Project Structure

```
qrcode-generator/
├── index.html              # Main QR generator page
├── login.html              # Auth login page
├── stats.html              # Scan analytics dashboard
├── visitors.html           # Visitor analytics dashboard
├── .htaccess               # Apache rewrites, HTTPS, security rules
├── robots.txt              # Search engine crawl rules
├── sitemap.xml             # SEO sitemap
├── favicon.svg             # SVG favicon
├── manifest.json           # PWA manifest
├── css/
│   └── style.css           # Glassmorphism styles & animations
├── js/
│   ├── app.js              # Vue 3 application logic
│   ├── qrcode.min.js       # QRCode library (self-hosted UMD v1.4.4)
│   └── tracker.js          # Page visitor tracking script
├── api/
│   ├── config.example.php  # Configuration template (copy to config.php)
│   ├── setup.php           # Database table creation
│   ├── auth.php            # Session-based auth guard
│   ├── login.php           # Login/logout/check API
│   ├── helpers.php         # Shared helper functions (IP, geo, browser parsing)
│   ├── shorten.php         # URL shortening API
│   ├── stats.php           # Scan analytics API (auth-protected)
│   ├── track.php           # Visitor tracking API
│   └── visitor-stats.php   # Visitor analytics API (auth-protected)
└── r/
    └── index.php           # Short URL redirect handler with scan logging
```

## Installation

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache with `mod_rewrite` enabled (or Nginx with equivalent rewrite rules)

### Step 1 — Clone & Upload

```bash
git clone https://github.com/anasabdur/qrcode-generator.git
```

Copy the project files to your web server's document root:

- **Shared Hosting** — Upload via File Manager or FTP to `public_html`
- **VPS / Dedicated** — Copy to your Apache/Nginx web root (e.g., `/var/www/html/`)
- **Local Development** — Use PHP's built-in server: `php -S localhost:8000`

### Step 2 — Create MySQL Database

Create a database and user via your preferred method:

**Via MySQL CLI:**
```sql
CREATE DATABASE qrcode_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'qrcode_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON qrcode_db.* TO 'qrcode_user'@'localhost';
FLUSH PRIVILEGES;
```

**Via hosting panel** — Use your panel's MySQL/Database section to create a database and user with full privileges.

### Step 3 — Configure

```bash
cp api/config.example.php api/config.php
```

Edit `api/config.php` with your actual credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');       // Your database name
define('DB_USER', 'your_database_user');       // Your database user
define('DB_PASS', 'your_database_password');   // Your database password

define('SITE_URL', 'https://your-domain.com');

define('ADMIN_USER', 'change_this_user');     // Dashboard login username
define('ADMIN_PASS', 'change_this_password'); // Dashboard login password
```

### Step 4 — Create Database Tables

Visit this URL once in your browser:

```
https://your-domain.com/api/setup.php
```

This creates three tables:
- `qr_links` — shortened URLs
- `qr_scans` — scan tracking data
- `page_visitors` — visitor tracking data

### Step 5 — Verify

Open `https://your-domain.com` — you should see the QR code generator.

Try generating a QR code for any URL to confirm everything works.

## Usage

### Generate QR Codes

1. Select a QR type (URL, Text, Email, Phone, WiFi, vCard)
2. Enter the content
3. Customize colors, size, margin, and error correction
4. Download as PNG, SVG, JPEG, or WebP — or copy to clipboard

### URL Shortening & Tracking

1. Select **URL** type and enter a URL
2. Check **"Use trackable short link"**
3. The QR code will point to `yoursite.com/r/abc123`
4. Every scan is logged with IP, location, browser, OS, device, and referrer

### Analytics Dashboards

- **Scan Analytics** — `https://your-domain.com/stats.html`
- **Visitor Analytics** — `https://your-domain.com/visitors.html`

Both dashboards require login with the admin credentials from `config.php`.

## Security Notes

- `api/config.php` is blocked from direct web access via `.htaccess` (Apache) — for Nginx, add an equivalent `location` block
- `api/auth.php` is also blocked from direct access
- Analytics APIs require session authentication
- `api/config.php` is excluded from Git via `.gitignore`
- Always use HTTPS in production

### Nginx Configuration (if not using Apache)

If you use Nginx instead of Apache, add these rules to your server block:

```nginx
# Block config & auth files
location ~ /(api/(config|auth)\.php) {
    deny all;
}

# Short URL rewrite
location /r/ {
    rewrite ^/r/([a-zA-Z0-9]+)$ /r/index.php?code=$1 last;
}
```

## Updating

When updating files, bump the version query strings in `index.html` to bust browser cache:

```html
<script src="js/qrcode.min.js?v=1.4.4"></script>
<script src="js/app.js?v=2.2"></script>
```

## License

MIT

## Author

**Anas Abdur** — [me.anasabdur.com](https://me.anasabdur.com)
