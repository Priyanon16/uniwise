<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
mbs_admin_session();
$cfg = mbs_admin_config();
header('Cache-Control: no-store');
$next = mbs_admin_safe_next((string)($_GET['next'] ?? $_POST['next'] ?? 'index.php'));
if (mbs_admin_is_logged_in($cfg)) {
    header('Location: ' . mbs_admin_url_base() . '/' . $next, true, 303);
    exit;
}
if (!isset($_SESSION['mbs_admin_login_csrf'])) {
    $_SESSION['mbs_admin_login_csrf'] = bin2hex(random_bytes(24));
}
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $token = (string)($_POST['csrf'] ?? '');
    $user = (string)($_POST['username'] ?? '');
    $pass = (string)($_POST['password'] ?? '');
    $failures = (int)($_SESSION['mbs_admin_failures'] ?? 0);
    $blockedUntil = (int)($_SESSION['mbs_admin_blocked_until'] ?? 0);
    if ($blockedUntil > time()) {
        $error = 'ทดลองเข้าสู่ระบบหลายครั้งเกินไป กรุณารอ 5 นาที';
    } elseif ($token === '' || !hash_equals((string)$_SESSION['mbs_admin_login_csrf'], $token)) {
        $error = 'แบบฟอร์มหมดอายุ กรุณารีเฟรชแล้วลองใหม่';
    } elseif (hash_equals((string)$cfg['admin_user'], $user) && password_verify($pass, (string)$cfg['admin_password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['mbs_admin_user'] = $user;
        $_SESSION['mbs_admin_last'] = time();
        unset($_SESSION['mbs_admin_failures'], $_SESSION['mbs_admin_blocked_until']);
        header('Location: ' . mbs_admin_url_base() . '/' . $next, true, 303);
        exit;
    } else {
        $failures++;
        $_SESSION['mbs_admin_failures'] = $failures;
        if ($failures >= 5) $_SESSION['mbs_admin_blocked_until'] = time() + 300;
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
}
function h(string $x): string { return htmlspecialchars($x, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?><!doctype html>
<html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>เข้าสู่ระบบ | MBS UniWise Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
body{min-height:100vh;display:grid;place-items:center;background:linear-gradient(135deg,#062b54,#0b64a3);font-family:'Noto Sans Thai',sans-serif;padding:20px}
.login-card{max-width:440px;width:100%;border-radius:20px;background:white;box-shadow:0 20px 80px #001a334d;padding:32px}
.brand{font-weight:800;color:#07518f;font-size:24px}.muted{color:#5d6f80;font-size:14px}
</style></head><body><main class="login-card">
<div class="brand mb-1">MBS UniWise</div><div class="muted mb-4">Admin Management — เข้าสู่ระบบครั้งเดียว ใช้ได้ทุกเมนู</div>
<?php if ($error): ?><div class="alert alert-danger"><?=h($error)?></div><?php endif; ?>
<form method="post" autocomplete="on">
<input type="hidden" name="csrf" value="<?=h((string)$_SESSION['mbs_admin_login_csrf'])?>">
<input type="hidden" name="next" value="<?=h($next)?>">
<div class="mb-3"><label class="form-label fw-semibold" for="username">ชื่อผู้ใช้</label><input class="form-control" name="username" id="username" autocomplete="username" required></div>
<div class="mb-4"><label class="form-label fw-semibold" for="password">รหัสผ่าน</label><input type="password" class="form-control" name="password" id="password" autocomplete="current-password" required></div>
<button class="btn btn-primary w-100 py-2" type="submit">เข้าสู่ระบบ Admin</button></form>
<div class="mt-3 text-center"><a href="../" class="muted">กลับหน้าเว็บไซต์</a></div>
</main></body></html>
