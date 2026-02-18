<?php
/* ===================================================
   QR Code Generator — Page Visit Tracker API
   POST /api/track.php  { page, title, screen_w, screen_h, timezone }
   Logs visitor data for the main website
   =================================================== */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$page     = isset($input['page']) ? mb_substr(trim($input['page']), 0, 500) : '/';
$title    = isset($input['title']) ? mb_substr(trim($input['title']), 0, 255) : '';
$screenW  = isset($input['screen_w']) ? (int) $input['screen_w'] : null;
$screenH  = isset($input['screen_h']) ? (int) $input['screen_h'] : null;
$timezone = isset($input['timezone']) ? mb_substr(trim($input['timezone']), 0, 100) : null;
$sid      = isset($input['session_id']) ? mb_substr(trim($input['session_id']), 0, 64) : null;

$ip       = getClientIP();
$ua       = $_SERVER['HTTP_USER_AGENT'] ?? '';
$referer  = $_SERVER['HTTP_REFERER'] ?? '';
$language = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
$lang     = $language ? explode(',', $language)[0] : '';

$browser    = parseBrowser($ua);
$os         = parseOS($ua);
$deviceType = parseDeviceType($ua);

// Skip bots
if ($deviceType === 'Bot') {
    echo json_encode(['ok' => true, 'skipped' => 'bot']);
    exit;
}

// Skip admin sessions
session_start();
if (!empty($_SESSION['admin_logged_in'])) {
    echo json_encode(['ok' => true, 'skipped' => 'admin']);
    exit;
}

$geo = getGeoData($ip);

try {
    $pdo = getDB();

    $stmt = $pdo->prepare('
        INSERT INTO page_visitors 
        (session_id, page_url, page_title, ip_address, country, city, region, latitude, longitude,
         user_agent, browser, browser_version, os, os_version, device_type, referer, language,
         screen_width, screen_height, timezone)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $sid,
        $page,
        $title,
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
        $screenW,
        $screenH,
        $timezone,
    ]);

    echo json_encode(['ok' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
