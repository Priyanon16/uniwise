<?php

require_once "../../api/db.php";

$pageTitle = "จัดการกำหนดการรับสมัครนักศึกษา";

$search       = trim($_GET['search'] ?? '');
$academicYear = trim($_GET['academic_year'] ?? '');
$roundNumber  = trim($_GET['round_number'] ?? '');
$activityType = trim($_GET['activity_type'] ?? '');
$sort         = trim($_GET['sort'] ?? 'round_asc');

$sql = "
    SELECT
        id,
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
    FROM admission_schedules
    WHERE 1 = 1
";

$params = [];


// =========================================================
// SEARCH
// =========================================================

if ($search !== '') {

    $sql .= "
        AND (
            quota_type LIKE :search
            OR activity LIKE :search
            OR activity_type LIKE :search
            OR date_display LIKE :search
            OR channel_website LIKE :search
            OR notes LIKE :search
        )
    ";

    $params[':search'] = '%' . $search . '%';
}


// =========================================================
// FILTER
// =========================================================

if ($academicYear !== '' && ctype_digit($academicYear)) {
    $sql .= " AND academic_year = :academic_year";
    $params[':academic_year'] = (int)$academicYear;
}

if (
    $roundNumber !== '' &&
    in_array((int)$roundNumber, [1, 2, 3, 4], true)
) {
    $sql .= " AND round_number = :round_number";
    $params[':round_number'] = (int)$roundNumber;
}

if ($activityType !== '') {
    $sql .= " AND activity_type = :activity_type";
    $params[':activity_type'] = $activityType;
}


// =========================================================
// SORT
// =========================================================

switch ($sort) {

    case 'id_desc':
        $sql .= " ORDER BY id DESC";
        break;

    case 'date_asc':
        $sql .= "
            ORDER BY
                CASE WHEN start_date IS NULL THEN 1 ELSE 0 END ASC,
                start_date ASC,
                sort_order ASC,
                id ASC
        ";
        break;

    case 'date_desc':
        $sql .= "
            ORDER BY
                CASE WHEN start_date IS NULL THEN 1 ELSE 0 END ASC,
                start_date DESC,
                sort_order DESC,
                id DESC
        ";
        break;

    case 'sort_order':
        $sql .= "
            ORDER BY
                sort_order ASC,
                round_number ASC,
                quota_type ASC,
                id ASC
        ";
        break;

    case 'round_asc':
    default:
        $sql .= "
            ORDER BY
                round_number ASC,
                CASE
                    WHEN quota_type = 'ทุกโครงการ' THEN 0
                    WHEN quota_type LIKE 'รอบที่ % ทั้งหมด' THEN 2
                    ELSE 1
                END ASC,
                quota_type ASC,
                sort_order ASC,
                CASE WHEN start_date IS NULL THEN 1 ELSE 0 END ASC,
                start_date ASC,
                id ASC
        ";
        break;
}


$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


// =========================================================
// FILTER OPTIONS
// =========================================================

$years = $pdo->query("
    SELECT DISTINCT academic_year
    FROM admission_schedules
    ORDER BY academic_year DESC
")->fetchAll(PDO::FETCH_COLUMN);

$activityTypes = $pdo->query("
    SELECT DISTINCT activity_type
    FROM admission_schedules
    WHERE activity_type IS NOT NULL
      AND activity_type <> ''
    ORDER BY activity_type ASC
")->fetchAll(PDO::FETCH_COLUMN);


// =========================================================
// HELPERS
// =========================================================

function roundLabel(int $roundNumber, string $roundName): string
{
    if ($roundName !== '') {
        return 'รอบ ' . $roundNumber . ' • ' . $roundName;
    }

    return 'รอบ ' . $roundNumber;
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
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

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

    <!-- CSS กลางของระบบ Admin เดิม -->
    <link
        rel="stylesheet"
        href="../assets/admin.css?v=<?= filemtime(__DIR__ . '/../assets/admin.css') ?>"
    >

    <!-- CSS เฉพาะหน้า Admission -->
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
            ADMISSION MANAGEMENT
        </span>

        <h1>จัดการกำหนดการรับสมัครนักศึกษา</h1>

        <p>
            จัดการข้อมูลปีการศึกษา รอบรับสมัคร โครงการ กิจกรรม
            วันดำเนินการ เว็บไซต์ และหมายเหตุ
        </p>
    </div>

    <div class="hero-decoration">
        MBS
    </div>

</section>


<div class="page-section">

    <div class="page-actions">

        <div>
            <span class="section-kicker">
                ADMISSION DATABASE
            </span>

            <h2>รายการกำหนดการรับสมัคร</h2>

            <p>
                ค้นหา ตรวจสอบ เพิ่ม แก้ไข และลบข้อมูลในตาราง admission_schedules
            </p>
        </div>

        <a
            href="save.php"
            class="btn btn-mbs-primary"
        >
            <i class="bi bi-plus-lg"></i>
            เพิ่มกำหนดการ
        </a>

    </div>


    <?php if (isset($_GET['success'])): ?>

        <div class="alert alert-success alert-dismissible fade show">

            <?php
            switch ($_GET['success']) {
                case 'create':
                    echo "เพิ่มข้อมูลกำหนดการเรียบร้อยแล้ว";
                    break;
                case 'edit':
                    echo "แก้ไขข้อมูลกำหนดการเรียบร้อยแล้ว";
                    break;
                case 'delete':
                    echo "ลบข้อมูลกำหนดการเรียบร้อยแล้ว";
                    break;
                default:
                    echo "ดำเนินการเรียบร้อยแล้ว";
            }
            ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <?php if (isset($_GET['error'])): ?>

        <div class="alert alert-danger alert-dismissible fade show">

            <?= htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body">

            <form
                method="get"
                class="row g-3"
            >

                <div class="col-lg-4">

                    <label class="form-label">
                        ค้นหา
                    </label>

                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="โครงการ กิจกรรม ประเภท เว็บไซต์ หรือหมายเหตุ"
                    >

                </div>


                <div class="col-md-4 col-lg-2">

                    <label class="form-label">
                        ปีการศึกษา
                    </label>

                    <select
                        name="academic_year"
                        class="form-select"
                    >

                        <option value="">
                            ทุกปี
                        </option>

                        <?php foreach ($years as $year): ?>

                            <option
                                value="<?= (int)$year ?>"
                                <?= (string)$academicYear === (string)$year
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= (int)$year ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="col-md-4 col-lg-2">

                    <label class="form-label">
                        รอบ
                    </label>

                    <select
                        name="round_number"
                        class="form-select"
                    >

                        <option value="">
                            ทุกรอบ
                        </option>

                        <?php for ($i = 1; $i <= 4; $i++): ?>

                            <option
                                value="<?= $i ?>"
                                <?= (string)$roundNumber === (string)$i
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                รอบ <?= $i ?>
                            </option>

                        <?php endfor; ?>

                    </select>

                </div>


                <div class="col-md-4 col-lg-2">

                    <label class="form-label">
                        ประเภทกิจกรรม
                    </label>

                    <select
                        name="activity_type"
                        class="form-select"
                    >

                        <option value="">
                            ทุกประเภท
                        </option>

                        <?php foreach ($activityTypes as $type): ?>

                            <option
                                value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>"
                                <?= $activityType === $type
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= htmlspecialchars(activityTypeLabel($type), ENT_QUOTES, 'UTF-8') ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="col-md-4 col-lg-2">

                    <label class="form-label">
                        เรียงตาม
                    </label>

                    <select
                        name="sort"
                        class="form-select"
                        onchange="this.form.submit()"
                    >

                        <option
                            value="round_asc"
                            <?= $sort === 'round_asc' ? 'selected' : '' ?>
                        >
                            รอบ / โครงการ
                        </option>

                        <option
                            value="date_asc"
                            <?= $sort === 'date_asc' ? 'selected' : '' ?>
                        >
                            วันที่เก่า → ใหม่
                        </option>

                        <option
                            value="date_desc"
                            <?= $sort === 'date_desc' ? 'selected' : '' ?>
                        >
                            วันที่ใหม่ → เก่า
                        </option>

                        <option
                            value="sort_order"
                            <?= $sort === 'sort_order' ? 'selected' : '' ?>
                        >
                            Sort Order
                        </option>

                        <option
                            value="id_desc"
                            <?= $sort === 'id_desc' ? 'selected' : '' ?>
                        >
                            ID มาก → น้อย
                        </option>

                    </select>

                </div>


                <div class="col-12 d-flex gap-2">

                    <button
                        type="submit"
                        class="btn btn-mbs-primary"
                    >
                        <i class="bi bi-search"></i>
                        ค้นหา
                    </button>

                    <a
                        href="index.php"
                        class="btn btn-outline-secondary"
                    >
                        ล้าง
                    </a>

                </div>

            </form>

        </div>

    </div>


    <div class="mb-3">

        <span class="text-secondary">
            พบ
        </span>

        <strong>
            <?= count($rows) ?>
        </strong>

        <span class="text-secondary">
            รายการ
        </span>

    </div>


    <div class="card border-0 shadow-sm">

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr>
                            <th class="ps-3">ID</th>
                            <th>ปี</th>
                            <th>รอบ</th>
                            <th>โครงการ / โควตา</th>
                            <th>กิจกรรม</th>
                            <th>ประเภท</th>
                            <th>กำหนดการ</th>
                            <th>เว็บไซต์</th>
                            <th>ลำดับ</th>
                            <th class="text-center" style="min-width: 220px;">
                                จัดการ
                            </th>
                        </tr>

                    </thead>


                    <tbody>

                    <?php if (!$rows): ?>

                        <tr>
                            <td
                                colspan="10"
                                class="text-center text-secondary py-5"
                            >
                                ยังไม่มีข้อมูลกำหนดการรับสมัคร
                            </td>
                        </tr>

                    <?php endif; ?>


                    <?php foreach ($rows as $row): ?>

                        <tr>

                            <td class="ps-3">
                                <?= (int)$row['id'] ?>
                            </td>

                            <td>
                                <?= (int)$row['academic_year'] ?>
                            </td>

                            <td style="min-width: 135px;">

                                <span
                                    class="badge round-badge round-<?= (int)$row['round_number'] ?>"
                                >
                                    <?= htmlspecialchars(
                                        roundLabel(
                                            (int)$row['round_number'],
                                            (string)$row['round_name']
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                            </td>

                            <td style="min-width: 250px;">

                                <div class="fw-semibold">
                                    <?= htmlspecialchars($row['quota_type'], ENT_QUOTES, 'UTF-8') ?>
                                </div>

                            </td>

                            <td style="min-width: 270px;">

                                <div class="fw-semibold">
                                    <?= htmlspecialchars($row['activity'], ENT_QUOTES, 'UTF-8') ?>
                                </div>

                                <?php if (!empty($row['notes'])): ?>

                                    <div class="program-alias mt-1">
                                        <?= htmlspecialchars($row['notes'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>

                                <?php endif; ?>

                            </td>

                            <td>

                                <span class="badge text-bg-light border">
                                    <?= htmlspecialchars(
                                        activityTypeLabel($row['activity_type']),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                            </td>

                            <td style="min-width: 180px;">
                                <?= htmlspecialchars($row['date_display'], ENT_QUOTES, 'UTF-8') ?>
                            </td>

                            <td>
                                <?= !empty($row['channel_website'])
                                    ? htmlspecialchars($row['channel_website'], ENT_QUOTES, 'UTF-8')
                                    : '<span class="text-secondary">-</span>'
                                ?>
                            </td>

                            <td>
                                <?= (int)$row['sort_order'] ?>
                            </td>

                            <td>

                                <div class="d-flex justify-content-center gap-2 flex-wrap">

                                    <a
                                        href="detail.php?id=<?= (int)$row['id'] ?>"
                                        class="btn btn-outline-primary btn-sm"
                                    >
                                        <i class="bi bi-eye"></i>
                                        ดูข้อมูล
                                    </a>

                                    <a
                                        href="save.php?id=<?= (int)$row['id'] ?>"
                                        class="btn btn-warning btn-sm"
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
                                            class="btn btn-danger btn-sm"
                                        >
                                            <i class="bi bi-trash3"></i>
                                            ลบ
                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

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
