<?php

require_once "../../api/db.php";

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id) {
    exit("ID กำหนดการไม่ถูกต้อง");
}


$stmt = $pdo->prepare("
    SELECT *
    FROM admission_schedules
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    ':id' => $id
]);

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    exit("ไม่พบข้อมูลกำหนดการรับสมัคร");
}


function showValue($value): string
{
    if (
        $value === null ||
        trim((string)$value) === ''
    ) {
        return '-';
    }

    return nl2br(
        htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        )
    );
}


function activityTypeLabel(?string $type): string
{
    $labels = [
        'tcas_registration'     => 'ลงทะเบียน TCAS',
        'school_selection'      => 'โรงเรียนคัดเลือก / รับรอง',
        'application'           => 'รับสมัคร',
        'payment_check'         => 'ตรวจสอบการชำระเงิน / เอกสาร',
        'payment_deadline'      => 'วันสุดท้ายชำระเงิน',
        'score_check'           => 'ตรวจสอบคะแนน',
        'interview_eligible'    => 'ผู้มีสิทธิ์สัมภาษณ์',
        'interview'             => 'สอบสัมภาษณ์',
        'interview_result'      => 'ผลสัมภาษณ์',
        'ability_test_eligible' => 'ผู้มีสิทธิ์ทดสอบความสามารถ',
        'ability_test'          => 'ทดสอบความสามารถ',
        'screening_confirm'     => 'ยืนยันสิทธิ์คัดกรอง',
        'selection_result'      => 'ประกาศผล',
        'tcas_confirm'          => 'ยืนยันสิทธิ์ TCAS',
        'waiver'                => 'สละสิทธิ์',
        'admission_eligible'    => 'ผู้มีสิทธิ์เข้าศึกษา',
        'report'                => 'รายงานตัว',
    ];

    $type = trim((string)$type);

    if ($type === '') {
        return '-';
    }

    return $labels[$type] ?? $type;
}
?>
<!doctype html>
<html lang="th">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        รายละเอียดกำหนดการรับสมัคร
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="../assets/admin.css?v=<?= filemtime(__DIR__ . '/../assets/admin.css') ?>"
    >

    <link
        rel="stylesheet"
        href="assets/admissions.css?v=<?= filemtime(__DIR__ . '/assets/admissions.css') ?>"
    >

</head>


<body>

<div class="admin-layout">

<?php
$activeMenu = 'admissions';
$basePath   = '../';

include __DIR__ . '/../includes/sidebar.php';
?>


<div class="main-shell">

<header class="topbar">

    <button
        type="button"
        class="mobile-menu-btn"
        id="mobileMenuBtn"
        aria-label="เปิดเมนู"
    >
        <i class="bi bi-list"></i>
    </button>

    <div class="topbar-title">
        <span class="topbar-kicker">
            MBS • MAHASARAKHAM UNIVERSITY
        </span>
        <strong>รับสมัครนักศึกษา</strong>
    </div>

    <a
        href="javascript:history.back()"
        class="header-back-btn"
    >
        <i class="bi bi-arrow-left"></i>
        <span>ย้อนกลับ</span>
    </a>

</header>


<main class="content-area">

<section class="page-hero">

    <div>

        <span class="hero-badge">
            <span></span>
            ADMISSION DETAIL
        </span>

        <h1>
            <?= htmlspecialchars(
                $row['activity'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </h1>

        <p>
            รอบ <?= (int)$row['round_number'] ?>
            •
            <?= htmlspecialchars(
                $row['round_name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
            • ปีการศึกษา <?= (int)$row['academic_year'] ?>
        </p>

    </div>


    <div class="hero-actions-inline">

        <a
            href="index.php"
            class="btn btn-light-soft"
        >
            <i class="bi bi-arrow-left"></i>
            กลับหน้ารายการ
        </a>

        <a
            href="save.php?id=<?= (int)$row['id'] ?>"
            class="btn btn-mbs-yellow"
        >
            <i class="bi bi-pencil-square"></i>
            แก้ไขข้อมูล
        </a>

    </div>


    <div class="hero-decoration">
        MBS
    </div>

</section>


<div class="page-section">


    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body p-4">

            <h2 class="h5 fw-bold mb-4">
                ข้อมูลกำหนดการ
            </h2>


            <div class="row g-4">


                <div class="col-md-4">

                    <div class="detail-label">
                        ID
                    </div>

                    <div>
                        <?= (int)$row['id'] ?>
                    </div>

                </div>


                <div class="col-md-4">

                    <div class="detail-label">
                        ปีการศึกษา
                    </div>

                    <div>
                        <?= (int)$row['academic_year'] ?>
                    </div>

                </div>


                <div class="col-md-4">

                    <div class="detail-label">
                        รอบ
                    </div>

                    <div>
                        รอบ <?= (int)$row['round_number'] ?>
                        •
                        <?= showValue($row['round_name']) ?>
                    </div>

                </div>


                <div class="col-md-6">

                    <div class="detail-label">
                        โครงการ / โควตา
                    </div>

                    <div>
                        <?= showValue($row['quota_type']) ?>
                    </div>

                </div>


                <div class="col-md-6">

                    <div class="detail-label">
                        ประเภทกิจกรรม
                    </div>

                    <div>
                        <?= htmlspecialchars(
                            activityTypeLabel($row['activity_type']),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </div>

                </div>


                <div class="col-12">

                    <div class="detail-label">
                        กิจกรรม
                    </div>

                    <div>
                        <?= showValue($row['activity']) ?>
                    </div>

                </div>


                <div class="col-md-4">

                    <div class="detail-label">
                        วันที่เริ่มต้น
                    </div>

                    <div>
                        <?= showValue($row['start_date']) ?>
                    </div>

                </div>


                <div class="col-md-4">

                    <div class="detail-label">
                        วันที่สิ้นสุด
                    </div>

                    <div>
                        <?= showValue($row['end_date']) ?>
                    </div>

                </div>


                <div class="col-md-4">

                    <div class="detail-label">
                        Sort Order
                    </div>

                    <div>
                        <?= (int)$row['sort_order'] ?>
                    </div>

                </div>


                <div class="col-12">

                    <div class="detail-label">
                        ข้อความกำหนดการ
                    </div>

                    <div class="fs-5 fw-bold">
                        <?= showValue($row['date_display']) ?>
                    </div>

                </div>


                <div class="col-12">

                    <div class="detail-label">
                        เว็บไซต์ / ช่องทาง
                    </div>

                    <div>
                        <?= showValue($row['channel_website']) ?>
                    </div>

                </div>


                <div class="col-12">

                    <div class="detail-label">
                        หมายเหตุ
                    </div>

                    <div>
                        <?= showValue($row['notes']) ?>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <div class="d-flex justify-content-end gap-2 mb-5">

        <a
            href="index.php"
            class="btn btn-outline-secondary"
        >
            กลับรายการ
        </a>

        <a
            href="save.php?id=<?= (int)$row['id'] ?>"
            class="btn btn-warning"
        >
            <i class="bi bi-pencil"></i>
            แก้ไข
        </a>

        <form
            method="post"
            action="delete.php"
            class="m-0"
            onsubmit="return confirm('ยืนยันการลบกำหนดการนี้หรือไม่?');"
        >

            <input
                type="hidden"
                name="id"
                value="<?= (int)$row['id'] ?>"
            >

            <button
                type="submit"
                class="btn btn-danger"
            >
                <i class="bi bi-trash3"></i>
                ลบ
            </button>

        </form>

    </div>

</div>

</main>


<footer class="admin-footer">

    <div>
        <strong>MBS UniWise Admin</strong>
        <span>
            คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม
        </span>
    </div>

    <span>
        Mahasarakham Business School
    </span>

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
        if (sidebar) {
            sidebar.classList.add('show');
        }

        if (sidebarOverlay) {
            sidebarOverlay.classList.add('show');
        }
    }

    function closeSidebar() {
        if (sidebar) {
            sidebar.classList.remove('show');
        }

        if (sidebarOverlay) {
            sidebarOverlay.classList.remove('show');
        }
    }

    if (
        localStorage.getItem('mbsSidebarCollapsed') === '1' &&
        window.innerWidth >= 992
    ) {
        document.body.classList.add('sidebar-collapsed');
    }

    if (sidebarToggle) {

        sidebarToggle.addEventListener('click', function () {

            if (window.innerWidth < 992) {
                return;
            }

            document.body.classList.toggle('sidebar-collapsed');

            const collapsed =
                document.body.classList.contains('sidebar-collapsed');

            localStorage.setItem(
                'mbsSidebarCollapsed',
                collapsed ? '1' : '0'
            );
        });
    }

    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', openSidebar);
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }

    window.addEventListener('resize', function () {

        if (window.innerWidth >= 992) {

            closeSidebar();

            if (
                localStorage.getItem('mbsSidebarCollapsed') === '1'
            ) {
                document.body.classList.add('sidebar-collapsed');
            } else {
                document.body.classList.remove('sidebar-collapsed');
            }

        } else {

            document.body.classList.remove('sidebar-collapsed');
        }
    });

});
</script>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

</body>
</html>
