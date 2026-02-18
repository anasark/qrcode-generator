<?php
/* ===================================================
   QR Code Generator — Redirect Handler
   Looks up short code, logs scan data, and redirects
   =================================================== */

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/helpers.php';

// Get the code from the URL path
$code = isset($_GET['code']) ? trim($_GET['code']) : '';

if (empty($code) || !preg_match('/^[a-zA-Z0-9]{4,10}$/', $code)) {
    http_response_code(404);
    header('Location: ' . SITE_URL);
    exit;
}

try {
    $pdo = getDB();

    // Look up the target URL
    $stmt = $pdo->prepare('SELECT id, target_url FROM qr_links WHERE code = ? LIMIT 1');
    $stmt->execute([$code]);
    $link = $stmt->fetch();

    if (!$link) {
        http_response_code(404);
        header('Location: ' . SITE_URL);
        exit;
    }

    // Update hit counter
    $stmt = $pdo->prepare('UPDATE qr_links SET hits = hits + 1, last_hit_at = NOW() WHERE id = ?');
    $stmt->execute([$link['id']]);

    // ─── Log Scan Details ────────────────────────
    $ip = getClientIP();
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $language = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    // Take first language tag: "en-US,en;q=0.9" → "en-US"
    $lang = $language ? explode(',', $language)[0] : '';

    // Parse user agent
    $browser = parseBrowser($ua);
    $os = parseOS($ua);
    $deviceType = parseDeviceType($ua);

    // Geo lookup (free ip-api.com, max 45 req/min)
    $geo = getGeoData($ip);

    // Insert scan record
    $stmt = $pdo->prepare('
        INSERT INTO qr_scans 
        (link_id, ip_address, country, city, region, latitude, longitude, 
         user_agent, browser, browser_version, os, os_version, device_type, referer, language)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $link['id'],
        $ip,
        $geo['country'] ?? null,
        $geo['city'] ?? null,
        $geo['region'] ?? null,
        $geo['lat'] ?? null,
        $geo['lon'] ?? null,
        mb_substr($ua, 0, 500),
        $browser['name'] ?? null,
        $browser['version'] ?? null,
        $os['name'] ?? null,
        $os['version'] ?? null,
        $deviceType,
        mb_substr($referer, 0, 500),
        $lang,
    ]);

    // 301 redirect to target URL
    http_response_code(301);
    header('Location: ' . $link['target_url']);
    exit;

} catch (Exception $e) {
    // On error, still redirect
    header('Location: ' . SITE_URL);
    exit;
}
