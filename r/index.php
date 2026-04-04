<?php
/* ===================================================
   QR Code Generator — Redirect Handler
   Looks up short code, logs scan data, and redirects.
   Uses HTML+JS redirect instead of 302 to bypass
   nginx proxy caching on shared hosting.
   =================================================== */

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/helpers.php';

// Prevent ALL cache layers
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0, s-maxage=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Accel-Expires: 0');
header('X-LiteSpeed-Cache-Control: no-cache');

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

    $targetUrl = $link['target_url'];
    $linkId    = (int) $link['id'];

    // ─── Log scan server-side (works even if JS is disabled) ────
    // Update hit counter
    $pdo->prepare('UPDATE qr_links SET hits = hits + 1, last_hit_at = NOW() WHERE id = ?')
         ->execute([$linkId]);

    // Log scan details
    try {
        $ip       = getClientIP();
        $ua       = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $referer  = $_SERVER['HTTP_REFERER'] ?? '';
        $language = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $lang     = $language ? explode(',', $language)[0] : '';

        $browser    = parseBrowser($ua);
        $os         = parseOS($ua);
        $deviceType = parseDeviceType($ua);
        $geo        = getGeoData($ip);

        $stmt = $pdo->prepare('
            INSERT INTO qr_scans 
            (link_id, ip_address, country, city, region, latitude, longitude, 
             user_agent, browser, browser_version, os, os_version, device_type, referer, language)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $linkId, $ip,
            $geo['country'] ?? null, $geo['city'] ?? null, $geo['region'] ?? null,
            $geo['lat'] ?? null, $geo['lon'] ?? null,
            mb_substr($ua, 0, 500),
            $browser['name'] ?? null, $browser['version'] ?? null,
            $os['name'] ?? null, $os['version'] ?? null,
            $deviceType, mb_substr($referer, 0, 500), $lang,
        ]);
    } catch (Exception $e) {
        error_log('QR scan logging failed: ' . $e->getMessage());
    }

    // ─── Output an HTML page that redirects instantly ────────────
    // This avoids the 302 response that nginx proxy caches.
    // The meta-refresh + JS redirect ensures it works everywhere.
    $safeUrl = htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8');
    
    echo '<!DOCTYPE html><html><head>';
    echo '<meta http-equiv="refresh" content="0;url=' . $safeUrl . '">';
    echo '<title>Redirecting...</title>';
    echo '</head><body>';
    echo '<script>window.location.replace("' . addslashes($targetUrl) . '");</script>';
    echo '<noscript><p>Redirecting to <a href="' . $safeUrl . '">' . $safeUrl . '</a></p></noscript>';
    echo '</body></html>';
    exit;

} catch (Exception $e) {
    header('Location: ' . SITE_URL);
    exit;
}
