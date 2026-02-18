<?php
/* ===================================================
   Page Visitor Analytics API
   GET /api/visitor-stats.php
   Returns aggregated stats for page visitors
   =================================================== */

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

requireAuth();

try {
    $pdo = getDB();

    // ─── Summary ─────────────────────────────────
    $total = $pdo->query('SELECT COUNT(*) FROM page_visitors')->fetchColumn();
    $today = $pdo->query("SELECT COUNT(*) FROM page_visitors WHERE DATE(visited_at) = CURDATE()")->fetchColumn();
    $uniqueIPs = $pdo->query('SELECT COUNT(DISTINCT ip_address) FROM page_visitors')->fetchColumn();
    $uniqueSessions = $pdo->query('SELECT COUNT(DISTINCT session_id) FROM page_visitors WHERE session_id IS NOT NULL')->fetchColumn();

    // ─── Visits per day (last 30 days) ───────────
    $perDay = $pdo->query("
        SELECT DATE(visited_at) as date, COUNT(*) as count
        FROM page_visitors
        WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(visited_at) ORDER BY date
    ")->fetchAll();

    // ─── Visits per hour ─────────────────────────
    $perHour = $pdo->query("
        SELECT HOUR(visited_at) as hour, COUNT(*) as count
        FROM page_visitors GROUP BY HOUR(visited_at) ORDER BY hour
    ")->fetchAll();

    // ─── Top pages ───────────────────────────────
    $topPages = $pdo->query("
        SELECT page_url as page, COUNT(*) as count
        FROM page_visitors GROUP BY page_url ORDER BY count DESC LIMIT 10
    ")->fetchAll();

    // ─── Top countries ───────────────────────────
    $topCountries = $pdo->query("
        SELECT COALESCE(country, 'Unknown') as country, COUNT(*) as count
        FROM page_visitors GROUP BY country ORDER BY count DESC LIMIT 10
    ")->fetchAll();

    // ─── Top cities ──────────────────────────────
    $topCities = $pdo->query("
        SELECT COALESCE(city, 'Unknown') as city, COALESCE(country, '') as country, COUNT(*) as count
        FROM page_visitors GROUP BY city, country ORDER BY count DESC LIMIT 10
    ")->fetchAll();

    // ─── Browsers ────────────────────────────────
    $browsers = $pdo->query("
        SELECT COALESCE(browser, 'Unknown') as name, COUNT(*) as count
        FROM page_visitors GROUP BY browser ORDER BY count DESC LIMIT 8
    ")->fetchAll();

    // ─── Operating Systems ───────────────────────
    $oses = $pdo->query("
        SELECT COALESCE(os, 'Unknown') as name, COUNT(*) as count
        FROM page_visitors GROUP BY os ORDER BY count DESC LIMIT 8
    ")->fetchAll();

    // ─── Devices ─────────────────────────────────
    $devices = $pdo->query("
        SELECT COALESCE(device_type, 'Unknown') as type, COUNT(*) as count
        FROM page_visitors GROUP BY device_type ORDER BY count DESC
    ")->fetchAll();

    // ─── Screen resolutions ──────────────────────
    $screens = $pdo->query("
        SELECT CONCAT(screen_width, 'x', screen_height) as resolution, COUNT(*) as count
        FROM page_visitors WHERE screen_width IS NOT NULL
        GROUP BY screen_width, screen_height ORDER BY count DESC LIMIT 10
    ")->fetchAll();

    // ─── Top referrers ───────────────────────────
    $referrers = $pdo->query("
        SELECT COALESCE(NULLIF(referer, ''), 'Direct') as referer, COUNT(*) as count
        FROM page_visitors GROUP BY referer ORDER BY count DESC LIMIT 10
    ")->fetchAll();

    // ─── Languages ───────────────────────────────
    $languages = $pdo->query("
        SELECT COALESCE(NULLIF(language, ''), 'Unknown') as language, COUNT(*) as count
        FROM page_visitors GROUP BY language ORDER BY count DESC LIMIT 10
    ")->fetchAll();

    // ─── Timezones ───────────────────────────────
    $timezones = $pdo->query("
        SELECT COALESCE(NULLIF(timezone, ''), 'Unknown') as timezone, COUNT(*) as count
        FROM page_visitors WHERE timezone IS NOT NULL
        GROUP BY timezone ORDER BY count DESC LIMIT 10
    ")->fetchAll();

    // ─── Recent visitors ─────────────────────────
    $recent = $pdo->query("
        SELECT visited_at, page_url, ip_address, country, city, browser, browser_version, os, device_type, referer, screen_width, screen_height
        FROM page_visitors ORDER BY visited_at DESC LIMIT 50
    ")->fetchAll();

    echo json_encode([
        'summary' => [
            'total_visits'    => (int) $total,
            'today_visits'    => (int) $today,
            'unique_visitors' => (int) $uniqueIPs,
            'unique_sessions' => (int) $uniqueSessions,
        ],
        'per_day'       => $perDay,
        'per_hour'      => $perHour,
        'top_pages'     => $topPages,
        'top_countries'  => $topCountries,
        'top_cities'     => $topCities,
        'browsers'       => $browsers,
        'oses'           => $oses,
        'devices'        => $devices,
        'screens'        => $screens,
        'referrers'      => $referrers,
        'languages'      => $languages,
        'timezones'      => $timezones,
        'recent'         => $recent,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
