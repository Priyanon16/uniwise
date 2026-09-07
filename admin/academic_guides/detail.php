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

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    exit('ID คู่มือไม่ถูกต้อง');
}

$stmt = $pdo->prepare("SELECT * FROM academic_guides WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$guide = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$guide) {
    exit('ไม่พบข้อมูลคู่มือการเรียน');
}

$stmt = $pdo->prepare("SELECT * FROM academic_guide_steps WHERE guide_id = :guide_id ORDER BY step_order ASC, id ASC");
$stmt->execute([':guide_id' => $id]);
$steps = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM academic_guide_documents WHERE guide_id = :guide_id ORDER BY sort_order ASC, id ASC");
$stmt->execute([':guide_id' => $id]);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM academic_guide_periods WHERE guide_id = :guide_id ORDER BY sort_order ASC, start_date ASC, id ASC");
$stmt->execute([':guide_id' => $id]);
$periods = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>รายละเอียดคู่มือการเรียน</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/admin.css?v=<?= filemtime(__DIR__ . '/../assets/admin.css') ?>">
    <link rel="stylesheet" href="assets/guides.css?v=<?= filemtime(__DIR__ . '/assets/guides.css') ?>">
</head>
<body>
<div class="admin-layout">
<?php
$activeMenu = 'academic_guides';
$basePath = '../';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-shell">
<header class="topbar">
    <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="เปิดเมนู"><i class="bi bi-list"></i></button>
    <div class="topbar-title">
        <span class="topbar-kicker">MBS • MAHASARAKHAM UNIVERSITY</span>
        <strong>คู่มือการเรียน</strong>
    </div>
    <a href="javascript:history.back()" class="header-back-btn"><i class="bi bi-arrow-left"></i><span>ย้อนกลับ</span></a>
</header>

<main class="content-area">
<section class="page-hero">
    <div>
        <span class="hero-badge"><span></span> ACADEMIC GUIDE DETAIL</span>
        <h1><?= e($guide['title']) ?></h1>
        <p><?= e($guide['topic_code']) ?> • ลำดับ <?= (int)$guide['sort_order'] ?></p>
    </div>
    <div class="hero-actions-inline">
        <a href="index.php" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i> กลับรายการ</a>
        <a href="save.php?id=<?= (int)$guide['id'] ?>" class="btn btn-mbs-yellow"><i class="bi bi-pencil-square"></i> แก้ไขข้อมูล</a>
    </div>
    <div class="hero-decoration">MBS</div>
</section>

<div class="page-section">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h2 class="h5 fw-bold mb-4">ข้อมูลทั่วไป</h2>
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="detail-label">Topic code</div>
                    <div><span class="topic-code"><?= e($guide['topic_code']) ?></span></div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">ลำดับ</div>
                    <div><?= (int)$guide['sort_order'] ?></div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">สถานะ</div>
                    <div>
                        <?php if ((int)$guide['active'] === 1): ?>
                            <span class="badge rounded-pill text-bg-success">ใช้งาน</span>
                        <?php else: ?>
                            <span class="badge rounded-pill text-bg-secondary">ปิดใช้งาน</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">อัปเดตล่าสุด</div>
                    <div><?= !empty($guide['updated_at']) ? e(date('d/m/Y H:i', strtotime($guide['updated_at']))) : '-' ?></div>
                </div>
                <div class="col-12">
                    <div class="detail-label">สรุป</div>
                    <div><?= showValue($guide['summary']) ?></div>
                </div>
                <div class="col-12">
                    <div class="detail-label">Keywords</div>
                    <div><?= showValue($guide['keywords']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h2 class="h5 fw-bold mb-4">ขั้นตอน</h2>
            <?php if (!$steps): ?>
                <p class="text-secondary mb-0">ไม่มีข้อมูลขั้นตอน</p>
            <?php else: ?>
                <div class="timeline-list">
                    <?php foreach ($steps as $step): ?>
                        <div class="timeline-item">
                            <div class="timeline-number"><?= (int)$step['step_order'] ?></div>
                            <div>
                                <?php if (!empty($step['step_title'])): ?>
                                    <div class="fw-bold mb-1"><?= e($step['step_title']) ?></div>
                                <?php endif; ?>
                                <div><?= showValue($step['description']) ?></div>
                                <?php if (!empty($step['url'])): ?>
                                    <a href="<?= e($step['url']) ?>" target="_blank" rel="noopener noreferrer" class="small d-inline-flex gap-1 align-items-center mt-2">
                                        <i class="bi bi-box-arrow-up-right"></i> เปิดลิงก์
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h2 class="h5 fw-bold mb-4">เอกสารที่เกี่ยวข้อง</h2>
            <?php if (!$documents): ?>
                <p class="text-secondary mb-0">ไม่มีข้อมูลเอกสาร</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>ลำดับ</th>
                            <th>ชื่อเอกสาร</th>
                            <th>ลิงก์</th>
                            <th>หมายเหตุ</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($documents as $document): ?>
                            <tr>
                                <td><?= (int)$document['sort_order'] ?></td>
                                <td><?= e($document['document_name']) ?></td>
                                <td>
                                    <?php if (!empty($document['document_url'])): ?>
                                        <a href="<?= e($document['document_url']) ?>" target="_blank" rel="noopener noreferrer">เปิดเอกสาร</a>
                                    <?php else: ?>-
                                    <?php endif; ?>
                                </td>
                                <td><?= showValue($document['note']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-5">
        <div class="card-body p-4">
            <h2 class="h5 fw-bold mb-4">กำหนดการ / ช่วงเวลา</h2>
            <?php if (!$periods): ?>
                <p class="text-secondary mb-0">ไม่มีข้อมูลกำหนดการ</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>ลำดับ</th>
                            <th>ชื่อกำหนดการ</th>
                            <th>ปี/ภาค</th>
                            <th>ชั้นปี/รหัส</th>
                            <th>ช่วงวันที่</th>
                            <th>ข้อความวันที่</th>
                            <th>หมายเหตุ</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($periods as $period): ?>
                            <tr>
                                <td><?= (int)$period['sort_order'] ?></td>
                                <td><?= e($period['period_name']) ?></td>
                                <td>
                                    <?= !empty($period['academic_year']) ? e($period['academic_year']) : '-' ?>
                                    <?= !empty($period['semester']) ? '<div class="small text-secondary">' . e($period['semester']) . '</div>' : '' ?>
                                </td>
                                <td>
                                    <?= !empty($period['year_level']) ? e($period['year_level']) : '-' ?>
                                    <?= !empty($period['student_code']) ? '<div class="small text-secondary">รหัส ' . e($period['student_code']) . '</div>' : '' ?>
                                </td>
                                <td>
                                    <?= !empty($period['start_date']) ? e($period['start_date']) : '-' ?>
                                    <?= !empty($period['end_date']) ? ' ถึง ' . e($period['end_date']) : '' ?>
                                </td>
                                <td><?= showValue($period['date_text']) ?></td>
                                <td><?= showValue($period['note']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
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
