<?php

require_once "../../api/db.php";

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id) {
    exit("ID ไม่ถูกต้อง");
}


// =========================================================
// LOAD DATA
// =========================================================

$stmt = $pdo->prepare("
    SELECT *
    FROM academic_calendar_events
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    ':id' => $id
]);

$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    exit("ไม่พบข้อมูล");
}


// =========================================================
// FUNCTIONS
// =========================================================

function showValue($value)
{
    return (
        $value !== null &&
        trim((string)$value) !== ''
    )
        ? nl2br(
            htmlspecialchars(
                (string)$value
            )
        )
        : '-';
}


function thaiDate($date)
{
    if (!$date) {
        return '-';
    }

    $time = strtotime($date);

    return
        date('d/m/', $time)
        .
        (
            date('Y', $time)
            + 543
        );
}


function semesterName($value)
{
    return match ($value) {

        'first' =>
            'ภาคต้น',

        'second' =>
            'ภาคปลาย',

        'summer' =>
            'ภาคฤดูร้อน',

        default =>
            '-'
    };
}


function degreeName($value)
{
    return match ($value) {

        'bachelor' =>
            'ปริญญาตรี',

        'master' =>
            'ปริญญาโท',

        'doctoral' =>
            'ปริญญาเอก',

        default =>
            '-'
    };
}


function categoryName($value)
{
    return match ($value) {

        'registration' =>
            'ลงทะเบียนเรียน',

        'payment_deadline' =>
            'ชำระค่าลงทะเบียน',

        'semester_open' =>
            'เปิดภาคการศึกษา',

        'semester_close' =>
            'ปิดภาคการศึกษา',

        'midterm_exam' =>
            'สอบกลางภาค',

        'final_exam' =>
            'สอบปลายภาค',

        'withdraw_no_w' =>
            'ถอนรายวิชาไม่ติด W',

        'withdraw_w' =>
            'ถอนรายวิชาติด W',

        'section_change' =>
            'เปลี่ยนกลุ่มเรียน / ย้ายเซค',

        'major_change' =>
            'เปลี่ยนสาขา / ย้ายคณะ',

        'graduation_request' =>
            'แจ้งจบการศึกษา',

        'graduation_late' =>
            'แจ้งจบล่าช้า',

        'credit_transfer_new' =>
            'เทียบโอนผลการเรียนสำหรับนิสิตใหม่',

        'credit_transfer_move' =>
            'โอนผลการเรียนกรณีย้ายคณะหรือสาขา',

        'grade_submission' =>
            'ส่งผลการศึกษา / ส่งเกรด',

        'orientation' =>
            'ปฐมนิเทศนิสิตใหม่',

        'registration_cancel' =>
            'ยกเลิกผลการลงทะเบียน',

        default =>
            $value ?: '-'
    };
}


function eventStatusName($value)
{
    return match ($value) {

        'scheduled' =>
            'มีกิจกรรม',

        'no_activity' =>
            'ไม่มีกิจกรรม',

        default =>
            $value ?: '-'
    };
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
    รายละเอียดปฏิทินการศึกษา
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
    href="../assets/admin.css?v=<?= filemtime(
        __DIR__ .
        '/../assets/admin.css'
    ) ?>"
>


<link
    rel="stylesheet"
    href="style.css?v=<?= filemtime(
        __DIR__ .
        '/style.css'
    ) ?>"
>

</head>


<body>


<div class="admin-layout">


<?php

$activeMenu = 'calendar';
$basePath = '../';

include
    __DIR__ .
    '/../includes/sidebar.php';

?>


<div class="main-shell">


<!-- ======================================================
     TOPBAR
====================================================== -->

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

<strong>
    รายละเอียดปฏิทินการศึกษา
</strong>

</div>


<a
    href="index.php"
    class="header-back-btn"
>

    <i class="bi bi-arrow-left"></i>

    <span>
        ย้อนกลับ
    </span>

</a>


</header>


<!-- ======================================================
     CONTENT
====================================================== -->

<main class="content-area">


<!-- ======================================================
     HERO
====================================================== -->

<section class="page-hero">


<div>

<span class="hero-badge">

    <span></span>

    CALENDAR DETAIL

</span>


<h1>
    รายละเอียดกำหนดการ
</h1>


<p>
    ตรวจสอบข้อมูลปฏิทินการศึกษา
    วันที่ กลุ่มเป้าหมาย และรายละเอียดที่ใช้กับระบบ MBS UniWise AI
</p>

</div>


<div class="hero-decoration">
    MBS
</div>


</section>


<div class="page-section">


<!-- ======================================================
     PAGE ACTION
====================================================== -->

<div class="page-actions">

<div>

<span class="section-kicker">
    CALENDAR RECORD
</span>

<h2>
    <?= htmlspecialchars(
        $data['event_name']
    ) ?>
</h2>

<p>
    ID <?= (int)$data['id'] ?>
    • ปีการศึกษา
    <?= (int)$data['academic_year'] ?>
</p>

</div>


<a
    href="save.php?id=<?= (int)$data['id'] ?>"
    class="btn btn-warning"
>

    <i class="bi bi-pencil"></i>

    แก้ไขข้อมูล

</a>

</div>


<!-- ======================================================
     MAIN INFO
====================================================== -->

<div class="card border-0 shadow-sm mb-4">

<div class="card-body p-4">


<div class="detail-section-title">

<div class="detail-section-icon">

    <i class="bi bi-calendar-event-fill"></i>

</div>

<div>

<h3>
    ข้อมูลกำหนดการ
</h3>

<p>
    รายละเอียดหลักของกิจกรรมในปฏิทินการศึกษา
</p>

</div>

</div>


<div class="detail-grid">


<div class="detail-item">

<span class="detail-label">
    ID
</span>

<strong class="detail-value">
    <?= (int)$data['id'] ?>
</strong>

</div>


<div class="detail-item">

<span class="detail-label">
    ปีการศึกษา
</span>

<strong class="detail-value">
    <?= (int)$data['academic_year'] ?>
</strong>

</div>


<div class="detail-item">

<span class="detail-label">
    ระดับการศึกษา
</span>

<div class="detail-value">

<span class="badge text-bg-light border">

    <?= htmlspecialchars(
        degreeName(
            $data['degree_level']
        )
    ) ?>

</span>

</div>

</div>


<div class="detail-item">

<span class="detail-label">
    ภาคการศึกษา
</span>

<strong class="detail-value">

    <?= htmlspecialchars(
        semesterName(
            $data['semester']
        )
    ) ?>

</strong>

</div>


<div class="detail-item">

<span class="detail-label">
    หมวด
</span>

<strong class="detail-value">

    <?= htmlspecialchars(
        categoryName(
            $data['category_code']
        )
    ) ?>

</strong>

<div class="detail-code">

    <?= htmlspecialchars(
        $data['category_code']
    ) ?>

</div>

</div>


<div class="detail-item detail-item-wide">

<span class="detail-label">
    ชื่อกิจกรรม
</span>

<strong class="detail-value">

    <?= htmlspecialchars(
        $data['event_name']
    ) ?>

</strong>

</div>


</div>


</div>

</div>


<!-- ======================================================
     TARGET / PHASE
====================================================== -->

<div class="card border-0 shadow-sm mb-4">

<div class="card-body p-4">


<div class="detail-section-title">

<div class="detail-section-icon">

    <i class="bi bi-people-fill"></i>

</div>

<div>

<h3>
    กลุ่มเป้าหมายและช่วงกิจกรรม
</h3>

<p>
    ชั้นปี รหัสนิสิต และรายละเอียดของช่วงการดำเนินกิจกรรม
</p>

</div>

</div>


<div class="detail-grid">


<div class="detail-item">

<span class="detail-label">
    ช่วงที่
</span>

<strong class="detail-value">

<?php if ($data['phase_no']): ?>

    <?= (int)$data['phase_no'] ?>

<?php else: ?>

    -

<?php endif; ?>

</strong>

</div>


<div class="detail-item detail-item-wide">

<span class="detail-label">
    รายละเอียดช่วง
</span>

<div class="detail-value">

    <?= showValue(
        $data['phase_name']
    ) ?>

</div>

</div>


<div class="detail-item">

<span class="detail-label">
    ชั้นปี
</span>

<strong class="detail-value">

    <?= showValue(
        $data['year_level']
    ) ?>

</strong>

</div>


<div class="detail-item">

<span class="detail-label">
    รหัสนิสิต
</span>

<strong class="detail-value">

    <?= showValue(
        $data['student_code']
    ) ?>

</strong>

</div>


<div class="detail-item detail-item-wide">

<span class="detail-label">
    กลุ่มเป้าหมาย
</span>

<div class="detail-value">

    <?= showValue(
        $data['audience_text']
    ) ?>

</div>

</div>


</div>


</div>

</div>


<!-- ======================================================
     DATE
====================================================== -->

<div class="card border-0 shadow-sm mb-4">

<div class="card-body p-4">


<div class="detail-section-title">

<div class="detail-section-icon">

    <i class="bi bi-calendar3"></i>

</div>

<div>

<h3>
    วันและช่วงเวลา
</h3>

<p>
    วันที่เริ่มต้นและวันที่สิ้นสุดของกิจกรรม
</p>

</div>

</div>


<div class="detail-grid">


<div class="detail-item">

<span class="detail-label">
    วันที่เริ่ม
</span>

<strong class="detail-value">

    <?= thaiDate(
        $data['start_date']
    ) ?>

</strong>

</div>


<div class="detail-item">

<span class="detail-label">
    วันที่สิ้นสุด
</span>

<strong class="detail-value">

    <?= thaiDate(
        $data['end_date']
    ) ?>

</strong>

</div>


<div class="detail-item">

<span class="detail-label">
    สถานะกิจกรรม
</span>

<div class="detail-value">


<?php if (
    $data['event_status']
    === 'no_activity'
): ?>

<span class="badge text-bg-danger">

    <?= htmlspecialchars(
        eventStatusName(
            $data['event_status']
        )
    ) ?>

</span>

<?php else: ?>

<span class="badge text-bg-success">

    <?= htmlspecialchars(
        eventStatusName(
            $data['event_status']
        )
    ) ?>

</span>

<?php endif; ?>


</div>

</div>


<div class="detail-item">

<span class="detail-label">
    สถานะการใช้งาน
</span>

<div class="detail-value">


<?php if (
    (int)$data['active']
    === 1
): ?>

<span class="badge text-bg-success">
    เปิดใช้งาน
</span>

<?php else: ?>

<span class="badge text-bg-secondary">
    ปิดใช้งาน
</span>

<?php endif; ?>


</div>

</div>


</div>


</div>

</div>


<!-- ======================================================
     EXTRA INFO
====================================================== -->

<div class="card border-0 shadow-sm mb-5">

<div class="card-body p-4">


<div class="detail-section-title">

<div class="detail-section-icon">

    <i class="bi bi-info-circle-fill"></i>

</div>

<div>

<h3>
    รายละเอียดเพิ่มเติม
</h3>

<p>
    ช่องทาง ค่าใช้จ่าย หมายเหตุ และคำค้นสำหรับระบบ
</p>

</div>

</div>


<div class="detail-grid">


<div class="detail-item detail-item-wide">

<span class="detail-label">
    สถานที่ / ช่องทาง
</span>

<div class="detail-value">

    <?= showValue(
        $data['location_or_channel']
    ) ?>

</div>

</div>


<div class="detail-item detail-item-wide">

<span class="detail-label">
    ค่าปรับ / ค่าใช้จ่าย
</span>

<div class="detail-value">

    <?= showValue(
        $data['fee_note']
    ) ?>

</div>

</div>


<div class="detail-item detail-item-full">

<span class="detail-label">
    หมายเหตุ
</span>

<div class="detail-value detail-text">

    <?= showValue(
        $data['note']
    ) ?>

</div>

</div>


<div class="detail-item detail-item-full">

<span class="detail-label">
    Keywords
</span>

<div class="detail-value detail-text">

    <?= showValue(
        $data['keywords']
    ) ?>

</div>

</div>


</div>


</div>

</div>


</div>


</main>


<!-- ======================================================
     FOOTER
====================================================== -->

<footer class="admin-footer">

<div>

<strong>
    MBS UniWise Admin
</strong>

<span>
    คณะการบัญชีและการจัดการ
    มหาวิทยาลัยมหาสารคาม
</span>

</div>


<span>
    Mahasarakham Business School
</span>

</footer>


</div>

</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const sidebar =
            document.getElementById(
                'sidebar'
            );

        const sidebarOverlay =
            document.getElementById(
                'sidebarOverlay'
            );

        const mobileMenuBtn =
            document.getElementById(
                'mobileMenuBtn'
            );

        const sidebarToggle =
            document.getElementById(
                'sidebarToggle'
            );


        function openSidebar() {

            if (sidebar) {
                sidebar.classList.add(
                    'show'
                );
            }

            if (sidebarOverlay) {
                sidebarOverlay
                    .classList.add(
                        'show'
                    );
            }
        }


        function closeSidebar() {

            if (sidebar) {
                sidebar.classList.remove(
                    'show'
                );
            }

            if (sidebarOverlay) {
                sidebarOverlay
                    .classList.remove(
                        'show'
                    );
            }
        }


        if (
            localStorage.getItem(
                'mbsSidebarCollapsed'
            ) === '1'
            &&
            window.innerWidth >= 992
        ) {

            document.body
                .classList.add(
                    'sidebar-collapsed'
                );
        }


        if (sidebarToggle) {

            sidebarToggle
                .addEventListener(
                    'click',
                    function () {

                        if (
                            window.innerWidth
                            < 992
                        ) {
                            return;
                        }


                        document.body
                            .classList.toggle(
                                'sidebar-collapsed'
                            );


                        const collapsed =
                            document.body
                                .classList
                                .contains(
                                    'sidebar-collapsed'
                                );


                        localStorage.setItem(
                            'mbsSidebarCollapsed',
                            collapsed
                                ? '1'
                                : '0'
                        );
                    }
                );
        }


        if (mobileMenuBtn) {

            mobileMenuBtn.addEventListener(
                'click',
                openSidebar
            );
        }


        if (sidebarOverlay) {

            sidebarOverlay.addEventListener(
                'click',
                closeSidebar
            );
        }


        window.addEventListener(
            'resize',
            function () {

                if (
                    window.innerWidth >= 992
                ) {

                    closeSidebar();


                    if (
                        localStorage.getItem(
                            'mbsSidebarCollapsed'
                        ) === '1'
                    ) {

                        document.body.classList.add(
                            'sidebar-collapsed'
                        );

                    } else {

                        document.body.classList.remove(
                            'sidebar-collapsed'
                        );
                    }

                } else {

                    document.body.classList.remove(
                        'sidebar-collapsed'
                    );
                }
            }
        );

    }
);

</script>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


</body>

</html>