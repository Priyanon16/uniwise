<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
mbs_admin_session();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method Not Allowed');
}
$token = (string)($_POST['csrf'] ?? '');
if ($token === '' || !hash_equals((string)($_SESSION['mbs_admin_logout_csrf'] ?? ''), $token)) {
    http_response_code(403);
    exit('CSRF verification failed');
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'], 'domain' => $p['domain'], 'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => $p['samesite']]);
}
session_destroy();
header('Cache-Control: no-store');
header('Location: ' . mbs_admin_url_base() . '/login.php', true, 303);
exit;
