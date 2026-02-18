<?php
/* ===================================================
   Auth Guard — Session-based admin authentication
   Include this in any protected API endpoint
   =================================================== */

session_start();

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
