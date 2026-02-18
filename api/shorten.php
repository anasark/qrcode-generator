<?php
/* ===================================================
   QR Code Generator — Shorten URL API
   POST /api/shorten.php  { "url": "https://..." }
   Returns { "short_url": "https://qrcode.anasabdur.com/r/abc123", "code": "abc123" }
   =================================================== */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
$url = isset($input['url']) ? trim($input['url']) : '';

// Validate URL
if (empty($url)) {
    http_response_code(400);
    echo json_encode(['error' => 'URL is required']);
    exit;
}

if (!filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid URL format']);
    exit;
}

// Only allow http/https
$scheme = parse_url($url, PHP_URL_SCHEME);
if (!in_array(strtolower($scheme), ['http', 'https'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Only http and https URLs are allowed']);
    exit;
}

try {
    $pdo = getDB();

    // Check if URL already exists
    $stmt = $pdo->prepare('SELECT code FROM qr_links WHERE target_url = ? LIMIT 1');
    $stmt->execute([$url]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Return existing short URL
        echo json_encode([
            'short_url' => SITE_URL . '/r/' . $existing['code'],
            'code' => $existing['code'],
            'is_new' => false,
        ]);
        exit;
    }

    // Generate unique short code
    $code = generateCode();
    $attempts = 0;
    while ($attempts < 10) {
        $stmt = $pdo->prepare('SELECT id FROM qr_links WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);
        if (!$stmt->fetch()) break;
        $code = generateCode();
        $attempts++;
    }

    // Insert new record
    $stmt = $pdo->prepare('INSERT INTO qr_links (code, target_url) VALUES (?, ?)');
    $stmt->execute([$code, $url]);

    echo json_encode([
        'short_url' => SITE_URL . '/r/' . $code,
        'code' => $code,
        'is_new' => true,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

/**
 * Generate a random alphanumeric code (6 chars)
 */
function generateCode($length = 6) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}
