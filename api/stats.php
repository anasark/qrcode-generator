<?php
/* ===================================================
   QR Code Generator — Analytics API
   GET /api/stats.php?code=abc123
   Returns scan analytics for a given short code
   =================================================== */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(204);
    exit;
}

$code = isset($_GET['code']) ? trim($_GET['code']) : '';

if (empty($code) || !preg_match('/^[a-zA-Z0-9]{4,10}$/', $code)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or missing code']);
    exit;
}

try {
    $pdo = getDB();

    // Get link info
    $stmt = $pdo->prepare('SELECT id, code, target_url, hits, created_at, last_hit_at FROM qr_links WHERE code = ? LIMIT 1');
    $stmt->execute([$code]);
    $link = $stmt->fetch();

    if (!$link) {
        http_response_code(404);
        echo json_encode(['error' => 'Link not found']);
        exit;
    }

    $linkId = $link['id'];

    // ─── Summary Stats ──────────────────────────
    // Total scans
    $totalScans = (int) $link['hits'];

    // Unique IPs (unique visitors)
    $stmt = $pdo->prepare('SELECT COUNT(DISTINCT ip_address) as uniq FROM qr_scans WHERE link_id = ?');
    $stmt->execute([$linkId]);
    $uniqueVisitors = (int) $stmt->fetch()['uniq'];

    // ─── Scans Per Day (last 30 days) ───────────
    $stmt = $pdo->prepare("
        SELECT DATE(scanned_at) as date, COUNT(*) as count
        FROM qr_scans
        WHERE link_id = ? AND scanned_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(scanned_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$linkId]);
    $scansPerDay = $stmt->fetchAll();

    // ─── Scans Per Hour (last 24h) ──────────────
    $stmt = $pdo->prepare("
        SELECT HOUR(scanned_at) as hour, COUNT(*) as count
        FROM qr_scans
        WHERE link_id = ? AND scanned_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY HOUR(scanned_at)
        ORDER BY hour ASC
    ");
    $stmt->execute([$linkId]);
    $scansPerHour = $stmt->fetchAll();

    // ─── Top Countries ──────────────────────────
    $stmt = $pdo->prepare("
        SELECT country, COUNT(*) as count
        FROM qr_scans
        WHERE link_id = ? AND country IS NOT NULL
        GROUP BY country
        ORDER BY count DESC
        LIMIT 20
    ");
    $stmt->execute([$linkId]);
    $topCountries = $stmt->fetchAll();

    // ─── Top Cities ─────────────────────────────
    $stmt = $pdo->prepare("
        SELECT city, region, country, COUNT(*) as count
        FROM qr_scans
        WHERE link_id = ? AND city IS NOT NULL
        GROUP BY city, region, country
        ORDER BY count DESC
        LIMIT 20
    ");
    $stmt->execute([$linkId]);
    $topCities = $stmt->fetchAll();

    // ─── Browsers ───────────────────────────────
    $stmt = $pdo->prepare("
        SELECT browser, COUNT(*) as count
        FROM qr_scans
        WHERE link_id = ? AND browser IS NOT NULL
        GROUP BY browser
        ORDER BY count DESC
        LIMIT 10
    ");
    $stmt->execute([$linkId]);
    $browsers = $stmt->fetchAll();

    // ─── Operating Systems ──────────────────────
    $stmt = $pdo->prepare("
        SELECT os, COUNT(*) as count
        FROM qr_scans
        WHERE link_id = ? AND os IS NOT NULL
        GROUP BY os
        ORDER BY count DESC
        LIMIT 10
    ");
    $stmt->execute([$linkId]);
    $operatingSystems = $stmt->fetchAll();

    // ─── Device Types ───────────────────────────
    $stmt = $pdo->prepare("
        SELECT device_type, COUNT(*) as count
        FROM qr_scans
        WHERE link_id = ? AND device_type IS NOT NULL
        GROUP BY device_type
        ORDER BY count DESC
    ");
    $stmt->execute([$linkId]);
    $devices = $stmt->fetchAll();

    // ─── Languages ──────────────────────────────
    $stmt = $pdo->prepare("
        SELECT language, COUNT(*) as count
        FROM qr_scans
        WHERE link_id = ? AND language IS NOT NULL AND language != ''
        GROUP BY language
        ORDER BY count DESC
        LIMIT 10
    ");
    $stmt->execute([$linkId]);
    $languages = $stmt->fetchAll();

    // ─── Referrers ──────────────────────────────
    $stmt = $pdo->prepare("
        SELECT referer, COUNT(*) as count
        FROM qr_scans
        WHERE link_id = ? AND referer IS NOT NULL AND referer != ''
        GROUP BY referer
        ORDER BY count DESC
        LIMIT 10
    ");
    $stmt->execute([$linkId]);
    $referrers = $stmt->fetchAll();

    // ─── Recent Scans (last 50) ─────────────────
    $stmt = $pdo->prepare("
        SELECT scanned_at, ip_address, country, city, browser, os, device_type, language, referer
        FROM qr_scans
        WHERE link_id = ?
        ORDER BY scanned_at DESC
        LIMIT 50
    ");
    $stmt->execute([$linkId]);
    $recentScans = $stmt->fetchAll();

    // ─── Response ───────────────────────────────
    echo json_encode([
        'link' => [
            'code'       => $link['code'],
            'target_url' => $link['target_url'],
            'short_url'  => SITE_URL . '/r/' . $link['code'],
            'created_at' => $link['created_at'],
            'last_hit_at'=> $link['last_hit_at'],
        ],
        'summary' => [
            'total_scans'     => $totalScans,
            'unique_visitors' => $uniqueVisitors,
        ],
        'scans_per_day'    => $scansPerDay,
        'scans_per_hour'   => $scansPerHour,
        'top_countries'    => $topCountries,
        'top_cities'       => $topCities,
        'browsers'         => $browsers,
        'operating_systems'=> $operatingSystems,
        'devices'          => $devices,
        'languages'        => $languages,
        'referrers'        => $referrers,
        'recent_scans'     => $recentScans,
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
