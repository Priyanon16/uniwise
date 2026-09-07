<?php
require_once "../../api/db.php";

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function showValue($value): string
{
    if ($value === null || trim((string)$value) === '') {
        return '-';
    }
    return nl2br(e($value));
}

function typeLabel(string $type): string
{
    return match ($type) {
        'phone' => 'โทรศัพท์',
        'email' => 'อีเมล',
        'website' => 'เว็บไซต์',
        'facebook' => 'Facebook',
        'office_hours' => 'เวลาทำการ',
        default => $type
    };
}

function typeIcon(string $type): string
{
    return match ($type) {
        'phone' => 'bi-telephone-fill',
        'email' => 'bi-envelope-fill',
        'website' => 'bi-globe2',
        'facebook' => 'bi-facebook',
        'office_hours' => 'bi-clock-fill',
        default => 'bi-info-circle-fill'
    };
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    exit('ID ข้อมูลติดต่อไม่ถูกต้อง');
}

$stmt = $pdo->prepare("SELECT * FROM faculty_contacts WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$contact = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$contact) {
    exit('ไม่พบข้อมูลติดต่อ');
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>รายละเอียดข้อมูลติดต่อ</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/admin.css?v=<?= filemtime(__DIR__ . '/../assets/admin.css') ?>">
    <link rel="stylesheet" href="assets/contacts.css?v=<?= filemtime(__DIR__ . '/assets/contacts.css') ?>">
</head>
<body>
<div class="admin-layout">
<?php
$activeMenu = 'contacts';
$basePath = '../';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-shell">
<header class="topbar">
    <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="เปิดเมนู"><i class="bi bi-list"></i></button>
    <div class="topbar-title">
        <span class="topbar-kicker">MBS • MAHASARAKHAM UNIVERSITY</span>
        <strong>ข้อมูลติดต่อคณะ</strong>
    </div>
    <a href="javascript:history.back()" class="header-back-btn"><i class="bi bi-arrow-left"></i><span>ย้อนกลับ</span></a>
</header>

<main class="content-area">
<section class="page-hero">
    <div>
        <span class="hero-badge"><span></span> CONTACT DETAIL</span>
        <h1><?= e($contact['contact_label']) ?></h1>
        <p><?= e(typeLabel($contact['contact_type'])) ?> • ลำดับ <?= (int)$contact['sort_order'] ?></p>
    </div>
    <div class="hero-actions-inline">
        <a href="index.php" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i> กลับรายการ</a>
        <a href="save.php?id=<?= (int)$contact['id'] ?>" class="btn btn-mbs-yellow"><i class="bi bi-pencil-square"></i> แก้ไขข้อมูล</a>
    </div>
    <div class="hero-decoration">MBS</div>
</section>

<div class="page-section">
    <div class="card border-0 shadow-sm mb-5">
        <div class="card-body p-4">
            <div class="contact-detail-head mb-4">
                <div class="contact-detail-icon"><i class="bi <?= e(typeIcon($contact['contact_type'])) ?>"></i></div>
                <div>
                    <span class="section-kicker">FACULTY CONTACT</span>
                    <h2 class="h4 fw-bold mb-1"><?= e($contact['contact_label']) ?></h2>
                    <div class="text-secondary"><?= e(typeLabel($contact['contact_type'])) ?></div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-6">
                    <div class="detail-label">ข้อมูลติดต่อ</div>
                    <div class="fs-5 fw-semibold"><?= showValue($contact['contact_value']) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">ลำดับ</div>
                    <div><?= (int)$contact['sort_order'] ?></div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">สถานะ</div>
                    <div>
                        <?php if ((int)$contact['active'] === 1): ?>
                            <span class="badge rounded-pill text-bg-success">ใช้งาน</span>
                        <?php else: ?>
                            <span class="badge rounded-pill text-bg-secondary">ปิดใช้งาน</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-12">
                    <div class="detail-label">รายละเอียดเพิ่มเติม</div>
                    <div><?= showValue($contact['description']) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="detail-label">สร้างเมื่อ</div>
                    <div><?= !empty($contact['created_at']) ? e(date('d/m/Y H:i', strtotime($contact['created_at']))) : '-' ?></div>
                </div>
                <div class="col-md-6">
                    <div class="detail-label">อัปเดตล่าสุด</div>
                    <div><?= !empty($contact['updated_at']) ? e(date('d/m/Y H:i', strtotime($contact['updated_at']))) : '-' ?></div>
                </div>
            </div>
        </div>
    </div>
</div>
</main>

<footer class="admin-footer">
    <div><strong>MBS UniWise Admin</strong><span>คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</span></div>
    <span>Mahasarakham Business School</span>
</footer>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebarToggle = document.getElementById('sidebarToggle');
    function openSidebar() { sidebar?.classList.add('show'); sidebarOverlay?.classList.add('show'); }
    function closeSidebar() { sidebar?.classList.remove('show'); sidebarOverlay?.classList.remove('show'); }
    if (localStorage.getItem('mbsSidebarCollapsed') === '1' && window.innerWidth >= 992) document.body.classList.add('sidebar-collapsed');
    sidebarToggle?.addEventListener('click', function () {
        if (window.innerWidth < 992) return;
        document.body.classList.toggle('sidebar-collapsed');
        localStorage.setItem('mbsSidebarCollapsed', document.body.classList.contains('sidebar-collapsed') ? '1' : '0');
    });
    mobileMenuBtn?.addEventListener('click', openSidebar);
    sidebarOverlay?.addEventListener('click', closeSidebar);
    window.addEventListener('resize', function () {
        if (window.innerWidth >= 992) {
            closeSidebar();
            document.body.classList.toggle('sidebar-collapsed', localStorage.getItem('mbsSidebarCollapsed') === '1');
        } else document.body.classList.remove('sidebar-collapsed');
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
