<?php

require_once "../../api/db.php";

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    exit('ID รายวิชาไม่ถูกต้อง');
}

$stmt = $pdo->prepare("\n    SELECT\n        cd.*,\n        ap.program_code,\n        ap.curriculum_name AS program_curriculum_name,\n        ap.major_name AS program_major_name,\n        ap.major_name_en AS program_major_name_en,\n        ap.degree_level AS program_degree_level\n    FROM course_descriptions cd\n    LEFT JOIN academic_programs ap\n        ON ap.id = cd.program_id\n    WHERE cd.id = :id\n    LIMIT 1\n");
$stmt->execute([':id' => $id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    exit('ไม่พบคำอธิบายรายวิชา');
}

$programId = (int)$course['program_id'];

function degreeName(string $degree): string
{
    return match ($degree) {
        'bachelor' => 'ปริญญาตรี',
        'master' => 'ปริญญาโท',
        'doctoral' => 'ปริญญาเอก',
        default => '-'
    };
}

function showValue($value): string
{
    if ($value === null || trim((string)$value) === '') {
        return '-';
    }
    return nl2br(htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'));
}

function typeLabel(?string $type): string
{
    $type = trim((string)$type);
    return match ($type) {
        'required' => 'วิชาบังคับ',
        'elective' => 'วิชาเลือก',
        'thesis' => 'วิทยานิพนธ์',
        'independent_study' => 'การค้นคว้าอิสระ',
        'foundation' => 'วิชาพื้นฐาน',
        'seminar' => 'สัมมนา',
        '' => '-',
        default => $type
    };
}

?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>รายละเอียดคำอธิบายรายวิชา</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/admin.css?v=<?= filemtime(__DIR__ . '/../assets/admin.css') ?>">
    <link rel="stylesheet" href="assets/programs.css?v=<?= filemtime(__DIR__ . '/assets/programs.css') ?>">
</head>
<body>
<div class="admin-layout">
<?php
$activeMenu = 'programs';
$basePath = '../';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-shell">
<header class="topbar">
    <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="เปิดเมนู">
        <i class="bi bi-list"></i>
    </button>

    <div class="topbar-title">
        <span class="topbar-kicker">MBS • MAHASARAKHAM UNIVERSITY</span>
        <strong>คำอธิบายรายวิชา</strong>
    </div>

    <a href="course_descriptions.php?program_id=<?= $programId ?>" class="header-back-btn">
        <i class="bi bi-arrow-left"></i>
        <span>ย้อนกลับ</span>
    </a>
</header>

<main class="content-area">
<section class="page-hero course-hero">
    <div>
        <span class="hero-badge"><span></span> COURSE DESCRIPTION DETAIL</span>
        <h1><?= htmlspecialchars($course['course_name_th'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p>
            <?= htmlspecialchars($course['course_code'], ENT_QUOTES, 'UTF-8') ?>
            • <?= degreeName((string)$course['program_degree_level']) ?>
        </p>
    </div>

    <div class="hero-actions-inline">
        <a href="course_descriptions.php?program_id=<?= $programId ?>" class="btn btn-light-soft">
            <i class="bi bi-arrow-left"></i>
            กลับรายการรายวิชา
        </a>
        <a href="course_description_save.php?id=<?= (int)$course['id'] ?>" class="btn btn-mbs-yellow">
            <i class="bi bi-pencil-square"></i>
            แก้ไขข้อมูล
        </a>
    </div>

    <div class="hero-decoration">COURSE</div>
</section>

<div class="page-section">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h2 class="h5 fw-bold mb-4">ข้อมูลรายวิชา</h2>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="detail-label">รหัสวิชา</div>
                    <div class="fw-semibold"><?= showValue($course['course_code']) ?></div>
                </div>

                <div class="col-md-4">
                    <div class="detail-label">หน่วยกิต</div>
                    <div><?= showValue($course['credits']) ?></div>
                </div>

                <div class="col-md-4">
                    <div class="detail-label">ลำดับการแสดงผล</div>
                    <div><?= (int)$course['sort_order'] ?></div>
                </div>

                <div class="col-md-6">
                    <div class="detail-label">ชื่อวิชาภาษาไทย</div>
                    <div><?= showValue($course['course_name_th']) ?></div>
                </div>

                <div class="col-md-6">
                    <div class="detail-label">ชื่อวิชาภาษาอังกฤษ</div>
                    <div><?= showValue($course['course_name_en']) ?></div>
                </div>

                <div class="col-md-4">
                    <div class="detail-label">กลุ่มวิชา</div>
                    <div><?= showValue($course['course_group']) ?></div>
                </div>

                <div class="col-md-4">
                    <div class="detail-label">ประเภทวิชา</div>
                    <div><?= htmlspecialchars(typeLabel($course['course_type']), ENT_QUOTES, 'UTF-8') ?></div>
                </div>

                <div class="col-md-4">
                    <div class="detail-label">นับหน่วยกิต</div>
                    <div>
                        <?php if ((int)$course['credit_counted'] === 1): ?>
                            <span class="badge text-bg-success">นับหน่วยกิต</span>
                        <?php else: ?>
                            <span class="badge text-bg-warning">ไม่นับหน่วยกิต</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="detail-label">ใช้กับแผน</div>
                    <div><?= showValue($course['plan_applicability']) ?></div>
                </div>

                <div class="col-md-6">
                    <div class="detail-label">การประเมินผล</div>
                    <div><?= showValue($course['assessment_type']) ?></div>
                </div>

                <div class="col-12">
                    <div class="detail-label">รายวิชาบังคับก่อน</div>
                    <div><?= showValue($course['prerequisite']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h2 class="h5 fw-bold mb-4">หลักสูตรที่เชื่อมโยง</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="detail-label">รหัสหลักสูตร</div>
                    <div><?= showValue($course['program_code']) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="detail-label">ระดับการศึกษา</div>
                    <div><?= degreeName((string)$course['program_degree_level']) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="detail-label">สาขาวิชา</div>
                    <div><?= showValue($course['program_major_name']) ?></div>
                </div>
                <div class="col-12">
                    <a href="detail.php?id=<?= $programId ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-box-arrow-up-right"></i>
                        เปิดรายละเอียดหลักสูตร
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h2 class="h5 fw-bold mb-4">คำอธิบายรายวิชาภาษาไทย</h2>
            <div class="course-description-content">
                <?= showValue($course['description_th']) ?>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h2 class="h5 fw-bold mb-4">คำอธิบายรายวิชาภาษาอังกฤษ</h2>
            <div class="course-description-content">
                <?= showValue($course['description_en']) ?>
            </div>
        </div>
    </div>
</div>
</main>

<footer class="admin-footer">
    <div>
        <strong>MBS UniWise Admin</strong>
        <span>คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</span>
    </div>
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

    function openSidebar() {
        if (sidebar) sidebar.classList.add('show');
        if (sidebarOverlay) sidebarOverlay.classList.add('show');
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('show');
        if (sidebarOverlay) sidebarOverlay.classList.remove('show');
    }

    if (localStorage.getItem('mbsSidebarCollapsed') === '1' && window.innerWidth >= 992) {
        document.body.classList.add('sidebar-collapsed');
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            if (window.innerWidth < 992) return;
            document.body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('mbsSidebarCollapsed', document.body.classList.contains('sidebar-collapsed') ? '1' : '0');
        });
    }

    if (mobileMenuBtn) mobileMenuBtn.addEventListener('click', openSidebar);
    if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

    window.addEventListener('resize', function () {
        if (window.innerWidth >= 992) {
            closeSidebar();
            document.body.classList.toggle('sidebar-collapsed', localStorage.getItem('mbsSidebarCollapsed') === '1');
        } else {
            document.body.classList.remove('sidebar-collapsed');
        }
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
