<?php
/* ===================================================
   Auth Guard — Session-based admin authentication
   Include this in any protected API endpoint
   =================================================== */

// Keep session data alive for 1 year on server side
ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 365);

session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 365, // 1 year
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Extend session lifetime on every request
if (!empty($_SESSION['admin_logged_in'])) {
    setcookie(session_name(), session_id(), time() + 60 * 60 * 24 * 365, '/');
}

require_once __DIR__ . '/config.php';

/**
 * Check if current session is authenticated.
 * For API endpoints: returns 401 JSON if not.
 */
function requireAuth() {
    if (empty($_SESSION['admin_logged_in'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized', 'login_required' => true]);
        exit;
    }
}

/**
 * Verify login credentials.
 */
function verifyLogin($user, $pass) {
    return $user === ADMIN_USER && $pass === ADMIN_PASS;
}

/**
 * Check if session is authenticated (boolean, no exit).
 */
function isAuthenticated() {
    return !empty($_SESSION['admin_logged_in']);
}
