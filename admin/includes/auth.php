<?php
declare(strict_types=1);

/** MBS UniWise Admin: one shared PHP session for all admin modules. */
function mbs_admin_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (session_status() !== PHP_SESSION_NONE) {
        http_response_code(503);
        exit('PHP Session is unavailable');
    }
    session_name('MBS_UNIWISE_ADMIN');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => mbs_admin_url_base() . '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    if (!isset($_SESSION['mbs_admin_logout_csrf'])) {
        $_SESSION['mbs_admin_logout_csrf'] = bin2hex(random_bytes(24));
    }
}
function mbs_admin_url_base(): string
{
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/uniwise/admin/index.php');
    $i = strpos($script, '/admin/');
    return $i === false ? '/uniwise/admin' : substr($script, 0, $i) . '/admin';
}
function mbs_admin_config(): array
{
    // Reuse the config.php already set up for data_segments; don't overwrite it.
    $file = __DIR__ . '/../data_segments/config.php';
    if (!is_file($file)) {
        http_response_code(503);
        exit('กรุณาสร้าง admin/data_segments/config.php ก่อนเปิดใช้งาน Login กลาง');
    }
    $cfg = require $file;
    if (!is_array($cfg)) {
        http_response_code(503);
        exit('config.php ไม่ถูกต้อง');
    }
    $user = (string)($cfg['admin_user'] ?? '');
    $hash = (string)($cfg['admin_password_hash'] ?? '');
    if ($user === '' || ($hash === '') || str_contains($hash, 'PASTE_') || password_get_info($hash)['algo'] === null) {
        http_response_code(503);
        exit('กรุณาตั้ง admin_user และ admin_password_hash ใน config.php');
    }
    return $cfg;
}
function mbs_admin_is_logged_in(array $cfg): bool
{
    return isset($_SESSION['mbs_admin_user'], $_SESSION['mbs_admin_last'])
        && hash_equals((string)$cfg['admin_user'], (string)$_SESSION['mbs_admin_user'])
        && (time() - (int)$_SESSION['mbs_admin_last'] < 7200); // 2h idle timeout
}
function mbs_admin_relative_request(): string
{
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $path = parse_url($uri, PHP_URL_PATH);
    $base = mbs_admin_url_base() . '/';
    if (is_string($path) && str_starts_with($path, $base)) {
        $query = parse_url($uri, PHP_URL_QUERY);
        $rel = substr($path, strlen($base));
        return $rel . (is_string($query) ? '?' . $query : '');
    }
    return 'index.php';
}
function mbs_admin_safe_next(string $next): string
{
    $path = parse_url($next, PHP_URL_PATH);
    if (!is_string($path) || str_contains($next, '\\') || str_contains($path, '..') || str_contains($path, '//')) {
        return 'index.php';
    }
    $allowed = ['academic_guides','admissions','cafes','calendar','contacts','data_segments','internship_admin','programs'];
    $parts = explode('/', $path);
    if (count($parts) === 1 && $path === 'index.php') {
        return 'index.php';
    }
    if (count($parts) === 2 && in_array($parts[0], $allowed, true) && preg_match('/^[a-z_][a-z0-9_.]*\.php$/i', $parts[1])) {
        return $path; // deliberately discard the original query string
    }
    return 'index.php';
}
function mbs_admin_require_login(): void
{
    mbs_admin_session();
    $cfg = mbs_admin_config();
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    if (!mbs_admin_is_logged_in($cfg)) {
        unset($_SESSION['mbs_admin_user'], $_SESSION['mbs_admin_last']);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(401);
            exit('Session หมดอายุ โปรดเข้าสู่ระบบใหม่ก่อนส่งข้อมูลซ้ำ');
        }
        $next = mbs_admin_safe_next(mbs_admin_relative_request());
        header('Location: ' . mbs_admin_url_base() . '/login.php?next=' . rawurlencode($next), true, 302);
        exit;
    }
    $_SESSION['mbs_admin_last'] = time();
}
