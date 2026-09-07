<?php

require_once "../../api/db.php";
require_once "../admin_activity.php";


// =========================================================
// Helpers
// =========================================================

function cleanString($value): string
{
    return trim((string)($value ?? ''));
}


function defaultRoundName(int $roundNumber): string
{
    return match ($roundNumber) {
        1 => 'Portfolio',
        2 => 'Quota',
        3 => 'Admission',
        4 => 'Direct Admission',
        default => ''
    };
}


// =========================================================
// Initial
// =========================================================

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

$isEdit = false;
$error = '';

$data = [
    'academic_year'   => 2570,
    'round_number'    => 1,
    'round_name'      => 'Portfolio',
    'quota_type'      => '',
    'activity'        => '',
    'activity_type'   => '',
    'start_date'      => '',
    'end_date'        => '',
    'date_display'    => '',
    'channel_website' => '',
    'notes'           => '',
    'sort_order'      => 0,
];


// =========================================================
// Load edit data
// =========================================================

if ($id) {

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

    $data = array_merge(
        $data,
        $row
    );

    $isEdit = true;
}


// =========================================================
// Save
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedId = filter_input(
        INPUT_POST,
        'id',
        FILTER_VALIDATE_INT
    );

    $academicYear =
        (int)($_POST['academic_year'] ?? 2570);

    $roundNumber =
        (int)($_POST['round_number'] ?? 0);

    $roundName =
        cleanString($_POST['round_name'] ?? '');

    $quotaType =
        cleanString($_POST['quota_type'] ?? '');

    $activity =
        cleanString($_POST['activity'] ?? '');

    $activityType =
        cleanString($_POST['activity_type'] ?? '');

    $startDate =
        cleanString($_POST['start_date'] ?? '');

    $endDate =
        cleanString($_POST['end_date'] ?? '');

    $dateDisplay =
        cleanString($_POST['date_display'] ?? '');

    $channelWebsite =
        cleanString($_POST['channel_website'] ?? '');

    $notes =
        cleanString($_POST['notes'] ?? '');

    $sortOrder =
        (int)($_POST['sort_order'] ?? 0);


    // -----------------------------------------------------
    // Validate
    // -----------------------------------------------------

    if ($academicYear < 2500 || $academicYear > 2700) {

        $error = "ปีการศึกษาไม่ถูกต้อง";

    } elseif (
        !in_array(
            $roundNumber,
            [1, 2, 3, 4],
            true
        )
    ) {

        $error = "กรุณาเลือกรอบรับสมัครให้ถูกต้อง";

    } elseif ($quotaType === '') {

        $error = "กรุณาระบุโครงการ / โควตา";

    } elseif ($activity === '') {

        $error = "กรุณาระบุกิจกรรม";

    } elseif ($dateDisplay === '') {

        $error = "กรุณาระบุข้อความกำหนดการ";

    } elseif (
        $startDate !== '' &&
        $endDate !== '' &&
        $endDate < $startDate
    ) {

        $error = "วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น";

    } else {

        if ($roundName === '') {
            $roundName =
                defaultRoundName($roundNumber);
        }

        try {

            $pdo->beginTransaction();


            if ($postedId) {

                $stmt = $pdo->prepare("
                    UPDATE admission_schedules
                    SET
                        academic_year = :academic_year,
                        round_number = :round_number,
                        round_name = :round_name,
                        quota_type = :quota_type,
                        activity = :activity,
                        activity_type = :activity_type,
                        start_date = :start_date,
                        end_date = :end_date,
                        date_display = :date_display,
                        channel_website = :channel_website,
                        notes = :notes,
                        sort_order = :sort_order
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':academic_year'   => $academicYear,
                    ':round_number'    => $roundNumber,
                    ':round_name'      => $roundName,
                    ':quota_type'      => $quotaType,
                    ':activity'        => $activity,
                    ':activity_type'   => $activityType ?: null,
                    ':start_date'      => $startDate ?: null,
                    ':end_date'        => $endDate ?: null,
                    ':date_display'    => $dateDisplay,
                    ':channel_website' => $channelWebsite ?: null,
                    ':notes'           => $notes ?: null,
                    ':sort_order'      => $sortOrder,
                    ':id'              => $postedId
                ]);

                $recordId = (int)$postedId;

            } else {

                $stmt = $pdo->prepare("
                    INSERT INTO admission_schedules
                    (
                        academic_year,
                        round_number,
                        round_name,
                        quota_type,
                        activity,
                        activity_type,
                        start_date,
                        end_date,
                        date_display,
                        channel_website,
                        notes,
                        sort_order
                    )
                    VALUES
                    (
                        :academic_year,
                        :round_number,
                        :round_name,
                        :quota_type,
                        :activity,
                        :activity_type,
                        :start_date,
                        :end_date,
                        :date_display,
                        :channel_website,
                        :notes,
                        :sort_order
                    )
                ");

                $stmt->execute([
                    ':academic_year'   => $academicYear,
                    ':round_number'    => $roundNumber,
                    ':round_name'      => $roundName,
                    ':quota_type'      => $quotaType,
                    ':activity'        => $activity,
                    ':activity_type'   => $activityType ?: null,
                    ':start_date'      => $startDate ?: null,
                    ':end_date'        => $endDate ?: null,
                    ':date_display'    => $dateDisplay,
                    ':channel_website' => $channelWebsite ?: null,
                    ':notes'           => $notes ?: null,
                    ':sort_order'      => $sortOrder
                ]);

                $recordId =
                    (int)$pdo->lastInsertId();
            }


            // -------------------------------------------------
            // Activity log
            // -------------------------------------------------

            $itemLabel =
                'รอบ ' .
                $roundNumber .
                ' • ' .
                $quotaType .
                ' • ' .
                $activity;

            logAdminActivity(
                $pdo,
                'admission',
                $postedId ? 'update' : 'create',
                $itemLabel,
                ($postedId ? 'แก้ไข' : 'เพิ่ม') .
                    'กำหนดการรับสมัคร ปีการศึกษา ' .
                    $academicYear .
                    ' (' .
                    $dateDisplay .
                    ')'
            );


            $pdo->commit();


            header(
                "Location: index.php?success=" .
                (
                    $postedId
                    ? 'edit'
                    : 'create'
                )
            );

            exit;


        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if (
                $e instanceof PDOException &&
                $e->getCode() === '23000'
            ) {

                $error =
                    "ข้อมูลซ้ำกับรายการเดิมในระบบ กรุณาตรวจสอบปี รอบ โครงการ กิจกรรม และกำหนดการ";

            } else {

                $error =
                    "ไม่สามารถบันทึกข้อมูลได้: " .
                    $e->getMessage();
            }
        }
    }


    $data = [
        'academic_year'   => $academicYear,
        'round_number'    => $roundNumber,
        'round_name'      => $roundName,
        'quota_type'      => $quotaType,
        'activity'        => $activity,
        'activity_type'   => $activityType,
        'start_date'      => $startDate,
        'end_date'        => $endDate,
        'date_display'    => $dateDisplay,
        'channel_website' => $channelWebsite,
        'notes'           => $notes,
        'sort_order'      => $sortOrder,
    ];
}


// =========================================================
// Activity types
// =========================================================

$activityTypeOptions = [
    'tcas_registration'     => 'ลงทะเบียน TCAS',
    'school_selection'      => 'โรงเรียนคัดเลือก / รับรอง',
    'application'           => 'รับสมัคร',
    'payment_check'         => 'ตรวจสอบการชำระเงิน / เอกสาร',
    'payment_deadline'      => 'วันสุดท้ายชำระเงิน',
    'score_check'           => 'ตรวจสอบคะแนน',
    'interview_eligible'    => 'ประกาศผู้มีสิทธิ์สัมภาษณ์',
    'interview'             => 'สอบสัมภาษณ์',
    'interview_result'      => 'ประกาศผลสัมภาษณ์',
    'ability_test_eligible' => 'ประกาศผู้มีสิทธิ์ทดสอบความสามารถ',
    'ability_test'          => 'ทดสอบความสามารถ',
    'screening_confirm'     => 'ยืนยันสิทธิ์คัดกรอง',
    'selection_result'      => 'ประกาศผล / ผ่านการคัดเลือก',
    'tcas_confirm'          => 'ยืนยันสิทธิ์ TCAS',
    'waiver'                => 'สละสิทธิ์',
    'admission_eligible'    => 'ประกาศผู้มีสิทธิ์เข้าศึกษา',
    'report'                => 'รายงานตัวเข้าศึกษา',
];

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
        <?= $isEdit
            ? 'แก้ไขกำหนดการรับสมัคร'
            : 'เพิ่มกำหนดการรับสมัคร'
        ?>
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
            ADMISSION EDITOR
        </span>

        <h1>
            <?= $isEdit
                ? 'แก้ไขกำหนดการรับสมัคร'
                : 'เพิ่มกำหนดการรับสมัคร'
            ?>
        </h1>

        <p>
            กรอกข้อมูลให้ตรงกับประกาศรับสมัคร
            เพื่อให้ระบบเรียงและค้นข้อมูลได้ถูกต้อง
        </p>

    </div>

    <div class="hero-decoration">
        MBS
    </div>

</section>


<div class="page-section">

    <div class="editor-toolbar">

        <a
            href="index.php"
            class="btn btn-outline-secondary"
        >
            <i class="bi bi-arrow-left"></i>
            กลับหน้ารายการ
        </a>

        <span>
            <?= $isEdit
                ? 'กำลังแก้ไขข้อมูลเดิม'
                : 'กำลังสร้างกำหนดการใหม่'
            ?>
        </span>

    </div>


    <?php if ($error): ?>

        <div class="alert alert-danger">

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <form method="post">

        <?php if ($isEdit): ?>

            <input
                type="hidden"
                name="id"
                value="<?= (int)$data['id'] ?>"
            >

        <?php endif; ?>


        <div class="card border-0 shadow-sm mb-4">

            <div class="card-body p-4">

                <div class="required-note">
                    ช่องที่มีเครื่องหมาย
                    <span>*</span>
                    จำเป็นต้องกรอก
                </div>


                <h2 class="h5 fw-bold mb-4">
                    1. ข้อมูลรอบรับสมัคร
                </h2>


                <div class="row g-3">


                    <div class="col-md-4">

                        <label class="form-label fw-semibold">
                            ปีการศึกษา
                            <span class="required-star">*</span>
                        </label>

                        <input
                            type="number"
                            name="academic_year"
                            class="form-control"
                            min="2500"
                            max="2700"
                            value="<?= htmlspecialchars((string)$data['academic_year'], ENT_QUOTES, 'UTF-8') ?>"
                            required
                        >

                    </div>


                    <div class="col-md-4">

                        <label class="form-label fw-semibold">
                            รอบที่
                            <span class="required-star">*</span>
                        </label>

                        <select
                            name="round_number"
                            id="round_number"
                            class="form-select"
                            required
                        >

                            <option
                                value="1"
                                <?= (int)$data['round_number'] === 1 ? 'selected' : '' ?>
                            >
                                รอบ 1 - Portfolio
                            </option>

                            <option
                                value="2"
                                <?= (int)$data['round_number'] === 2 ? 'selected' : '' ?>
                            >
                                รอบ 2 - Quota
                            </option>

                            <option
                                value="3"
                                <?= (int)$data['round_number'] === 3 ? 'selected' : '' ?>
                            >
                                รอบ 3 - Admission
                            </option>

                            <option
                                value="4"
                                <?= (int)$data['round_number'] === 4 ? 'selected' : '' ?>
                            >
                                รอบ 4 - Direct Admission
                            </option>

                        </select>

                    </div>


                    <div class="col-md-4">

                        <label class="form-label fw-semibold">
                            ชื่อรอบ
                            <span class="required-star">*</span>
                        </label>

                        <input
                            type="text"
                            name="round_name"
                            id="round_name"
                            class="form-control"
                            value="<?= htmlspecialchars((string)$data['round_name'], ENT_QUOTES, 'UTF-8') ?>"
                            required
                        >

                    </div>


                    <div class="col-12">

                        <label class="form-label fw-semibold">
                            โครงการ / โควตา
                            <span class="required-star">*</span>
                        </label>

                        <input
                            type="text"
                            name="quota_type"
                            class="form-control"
                            value="<?= htmlspecialchars((string)$data['quota_type'], ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="เช่น โควตาเด็กดีมีที่เรียน หรือ รอบที่ 1 ทั้งหมด"
                            required
                        >

                    </div>

                </div>

            </div>

        </div>


        <div class="card border-0 shadow-sm mb-4">

            <div class="card-body p-4">

                <h2 class="h5 fw-bold mb-4">
                    2. กิจกรรมและประเภทข้อมูล
                </h2>


                <div class="row g-3">


                    <div class="col-md-8">

                        <label class="form-label fw-semibold">
                            กิจกรรม
                            <span class="required-star">*</span>
                        </label>

                        <input
                            type="text"
                            name="activity"
                            class="form-control"
                            value="<?= htmlspecialchars((string)$data['activity'], ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="เช่น รับสมัครและชำระเงิน"
                            required
                        >

                    </div>


                    <div class="col-md-4">

                        <label class="form-label">
                            ประเภทกิจกรรม
                        </label>

                        <select
                            name="activity_type"
                            class="form-select"
                        >

                            <option value="">
                                -- ไม่ระบุ --
                            </option>

                            <?php foreach ($activityTypeOptions as $value => $label): ?>

                                <option
                                    value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
                                    <?= (string)$data['activity_type'] === $value
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <div class="form-text">
                            ใช้เป็นรหัสหัวข้อสำหรับ API และ Dify
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <div class="card border-0 shadow-sm mb-4">

            <div class="card-body p-4">

                <h2 class="h5 fw-bold mb-4">
                    3. วันที่และช่องทาง
                </h2>


                <div class="row g-3">


                    <div class="col-md-4">

                        <label class="form-label">
                            วันที่เริ่มต้น
                        </label>

                        <input
                            type="date"
                            name="start_date"
                            class="form-control"
                            value="<?= htmlspecialchars((string)$data['start_date'], ENT_QUOTES, 'UTF-8') ?>"
                        >

                    </div>


                    <div class="col-md-4">

                        <label class="form-label">
                            วันที่สิ้นสุด
                        </label>

                        <input
                            type="date"
                            name="end_date"
                            class="form-control"
                            value="<?= htmlspecialchars((string)$data['end_date'], ENT_QUOTES, 'UTF-8') ?>"
                        >

                    </div>


                    <div class="col-md-4">

                        <label class="form-label fw-semibold">
                            ข้อความกำหนดการ
                            <span class="required-star">*</span>
                        </label>

                        <input
                            type="text"
                            name="date_display"
                            class="form-control"
                            value="<?= htmlspecialchars((string)$data['date_display'], ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="เช่น 15 ส.ค. - 5 ก.ย. 69"
                            required
                        >

                    </div>


                    <div class="col-md-8">

                        <label class="form-label">
                            เว็บไซต์ / ช่องทาง
                        </label>

                        <input
                            type="text"
                            name="channel_website"
                            class="form-control"
                            value="<?= htmlspecialchars((string)$data['channel_website'], ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="เช่น admission.msu.ac.th"
                        >

                    </div>


                    <div class="col-md-4">

                        <label class="form-label">
                            Sort Order
                        </label>

                        <input
                            type="number"
                            name="sort_order"
                            class="form-control"
                            min="0"
                            value="<?= htmlspecialchars((string)$data['sort_order'], ENT_QUOTES, 'UTF-8') ?>"
                        >

                        <div class="form-text">
                            เช่น 10 ลงทะเบียน, 30 สมัคร, 80 สัมภาษณ์, 150 รายงานตัว
                        </div>

                    </div>


                    <div class="col-12">

                        <label class="form-label">
                            หมายเหตุ
                        </label>

                        <textarea
                            name="notes"
                            class="form-control"
                            rows="4"
                            placeholder="รายละเอียดเพิ่มเติม"
                        ><?= htmlspecialchars((string)$data['notes'], ENT_QUOTES, 'UTF-8') ?></textarea>

                    </div>

                </div>

            </div>

        </div>


        <div class="d-flex justify-content-end gap-2 mb-5">

            <a
                href="index.php"
                class="btn btn-outline-secondary px-4"
            >
                ยกเลิก
            </a>

            <button
                type="submit"
                class="btn btn-primary px-5"
            >
                <i class="bi bi-floppy"></i>
                บันทึกข้อมูล
            </button>

        </div>

    </form>

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

const roundNames = {
    1: 'Portfolio',
    2: 'Quota',
    3: 'Admission',
    4: 'Direct Admission'
};

const roundSelect =
    document.getElementById('round_number');

const roundNameInput =
    document.getElementById('round_name');

if (roundSelect && roundNameInput) {

    roundSelect.addEventListener(
        'change',
        function () {

            roundNameInput.value =
                roundNames[this.value] || '';
        }
    );
}

</script>


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
