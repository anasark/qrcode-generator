<?php
/* ===================================================
   Login API
   POST /api/login.php  — authenticate
   GET  /api/login.php  — check session status
   DELETE /api/login.php — logout
   =================================================== */

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// GET — check auth status
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['authenticated' => isAuthenticated()]);
    exit;
}

// DELETE — logout
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $_SESSION = [];
    session_destroy();
    echo json_encode(['ok' => true]);
    exit;
}

// POST — login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $user = trim($input['username'] ?? '');
    $pass = $input['password'] ?? '';

    if (verifyLogin($user, $pass)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user'] = $user;
        $_SESSION['login_time'] = time();
        echo json_encode(['ok' => true, 'authenticated' => true]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
