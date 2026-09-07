<?php

require_once "../../api/db.php";

$pageTitle = "จัดการปฏิทินการศึกษา";

$search = trim($_GET['search'] ?? '');
$year = trim($_GET['academic_year'] ?? '');
$semester = trim($_GET['semester'] ?? '');
$category = trim($_GET['category'] ?? '');
$status = trim($_GET['status'] ?? '');
$sort = trim($_GET['sort'] ?? 'id_asc');

$sql = "
    SELECT *
    FROM academic_calendar_events
    WHERE 1 = 1
";

$params = [];


// =========================================================
// SEARCH
// =========================================================

if ($search !== '') {

    $sql .= "
        AND (
            id = :search_id
            OR event_name LIKE :search
            OR category_code LIKE :search
            OR audience_text LIKE :search
            OR year_level LIKE :search
            OR student_code LIKE :search
            OR keywords LIKE :search
            OR phase_name LIKE :search
            OR location_or_channel LIKE :search
        )
    ";

    $params[':search_id'] =
        ctype_digit($search)
        ? (int)$search
        : -1;

    $params[':search'] =
        '%' . $search . '%';
}


// =========================================================
// ACADEMIC YEAR
// =========================================================

if ($year !== '') {

    $sql .= "
        AND academic_year = :academic_year
    ";

    $params[':academic_year'] =
        (int)$year;
}


// =========================================================
// SEMESTER
// =========================================================

$allowedSemesters = [
    'first',
    'second',
    'summer'
];

if (
    $semester !== '' &&
    in_array($semester, $allowedSemesters, true)
) {

    $sql .= "
        AND semester = :semester
    ";

    $params[':semester'] =
        $semester;
}


// =========================================================
// CATEGORY
// =========================================================

if ($category !== '') {

    $sql .= "
        AND category_code = :category
    ";

    $params[':category'] =
        $category;
}


// =========================================================
// STATUS
// =========================================================

if (
    $status === '1' ||
    $status === '0'
) {

    $sql .= "
        AND active = :active
    ";

    $params[':active'] =
        (int)$status;
}


// =========================================================
// SORT
// =========================================================

switch ($sort) {

    case 'id_desc':

        $sql .= "
            ORDER BY id DESC
        ";

        break;


    case 'latest':

        $sql .= "
            ORDER BY id DESC
        ";

        break;


    case 'date_asc':

        $sql .= "
            ORDER BY
                COALESCE(
                    start_date,
                    '9999-12-31'
                ) ASC,
                id ASC
        ";

        break;


    case 'date_desc':

        $sql .= "
            ORDER BY
                COALESCE(
                    start_date,
                    '0001-01-01'
                ) DESC,
                id DESC
        ";

        break;


    case 'year_desc':

        $sql .= "
            ORDER BY
                academic_year DESC,
                COALESCE(
                    start_date,
                    '9999-12-31'
                ) ASC,
                id ASC
        ";

        break;


    case 'id_asc':
    default:

        $sql .= "
            ORDER BY id ASC
        ";

        break;
}


$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$events =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// =========================================================
// CATEGORY OPTIONS
// =========================================================

$categories =
    $pdo->query("
        SELECT DISTINCT
            category_code
        FROM academic_calendar_events
        WHERE category_code IS NOT NULL
          AND category_code <> ''
        ORDER BY category_code ASC
    ")
    ->fetchAll(PDO::FETCH_COLUMN);


// =========================================================
// FUNCTIONS
// =========================================================

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


function semesterBadge($value)
{
    return match ($value) {

        'first' =>
            'primary',

        'second' =>
            'success',

        'summer' =>
            'warning',

        default =>
            'secondary'
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


// =========================================================
// CATEGORY NAME THAI
// =========================================================

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
            $value
    };
}


function thaiDate($date)
{
    if (!$date) {
        return '-';
    }

    $timestamp =
        strtotime($date);

    return
        date('d/m/', $timestamp)
        .
        (
            date('Y', $timestamp)
            + 543
        );
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
    <?= htmlspecialchars($pageTitle) ?>
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
    ปฏิทินการศึกษา
</strong>

</div>


<a
    href="javascript:history.back()"
    class="header-back-btn"
>

    <i class="bi bi-arrow-left"></i>

    <span>
        ย้อนกลับ
    </span>

</a>


</header>


<main class="content-area">


<section class="page-hero">


<div>

<span class="hero-badge">

    <span></span>

    ACADEMIC CALENDAR

</span>


<h1>
    จัดการปฏิทินการศึกษา
</h1>


<p>
    จัดการวันลงทะเบียน เปิดภาคการศึกษา
    วันสอบ ถอนรายวิชา แจ้งจบ
    และกำหนดการทางการศึกษาสำหรับระบบ MBS UniWise AI
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
    CALENDAR DATABASE
</span>

<h2>
    รายการปฏิทินการศึกษา
</h2>

<p>
    ค้นหา ตรวจสอบ เพิ่ม
    และแก้ไขกำหนดการทางการศึกษาในระบบ
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

<div
    class="
        alert
        alert-success
        alert-dismissible
        fade
        show
    "
>

<?php

switch ($_GET['success']) {

    case 'create':

        echo
            "เพิ่มข้อมูลปฏิทินเรียบร้อยแล้ว";

        break;


    case 'edit':

        echo
            "แก้ไขข้อมูลปฏิทินเรียบร้อยแล้ว";

        break;


    case 'delete':

        echo
            "ลบข้อมูลปฏิทินเรียบร้อยแล้ว";

        break;


    default:

        echo
            "ดำเนินการเรียบร้อยแล้ว";
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

<div
    class="
        alert
        alert-danger
        alert-dismissible
        fade
        show
    "
>

    <?= htmlspecialchars(
        $_GET['error']
    ) ?>

<button
    type="button"
    class="btn-close"
    data-bs-dismiss="alert"
></button>

</div>

<?php endif; ?>


<div
    class="
        card
        border-0
        shadow-sm
        mb-4
    "
>

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
    value="<?= htmlspecialchars(
        $search
    ) ?>"
    placeholder="ค้นหา ID กิจกรรม หมวด ชั้นปี รหัสนิสิต หรือคำค้น"
>

</div>


<div class="col-md-4 col-lg-2">

<label class="form-label">
    ปีการศึกษา
</label>

<input
    type="number"
    name="academic_year"
    class="form-control"
    value="<?= htmlspecialchars(
        $year
    ) ?>"
    placeholder="เช่น 2569"
>

</div>


<div class="col-md-4 col-lg-2">

<label class="form-label">
    ภาคการศึกษา
</label>

<select
    name="semester"
    class="form-select"
>

<option value="">
    ทุกภาค
</option>


<option
    value="first"
    <?= $semester === 'first'
        ? 'selected'
        : ''
    ?>
>
    ภาคต้น
</option>


<option
    value="second"
    <?= $semester === 'second'
        ? 'selected'
        : ''
    ?>
>
    ภาคปลาย
</option>


<option
    value="summer"
    <?= $semester === 'summer'
        ? 'selected'
        : ''
    ?>
>
    ภาคฤดูร้อน
</option>


</select>

</div>


<div class="col-md-4 col-lg-2">

<label class="form-label">
    สถานะ
</label>

<select
    name="status"
    class="form-select"
>

<option value="">
    ทุกสถานะ
</option>

<option
    value="1"
    <?= $status === '1'
        ? 'selected'
        : ''
    ?>
>
    เปิดใช้งาน
</option>

<option
    value="0"
    <?= $status === '0'
        ? 'selected'
        : ''
    ?>
>
    ปิดใช้งาน
</option>

</select>

</div>


<div class="col-md-4 col-lg-2">

<label class="form-label">
    หมวด
</label>

<select
    name="category"
    class="form-select"
>

<option value="">
    ทุกหมวด
</option>


<?php foreach ($categories as $item): ?>

<option
    value="<?= htmlspecialchars(
        $item
    ) ?>"
    <?= $category === $item
        ? 'selected'
        : ''
    ?>
>

    <?= htmlspecialchars(
        categoryName($item)
    ) ?>

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
    value="id_asc"
    <?= $sort === 'id_asc'
        ? 'selected'
        : ''
    ?>
>
    ID น้อย → มาก
</option>


<option
    value="latest"
    <?= $sort === 'latest'
        ? 'selected'
        : ''
    ?>
>
    เพิ่มล่าสุด
</option>


<option
    value="date_asc"
    <?= $sort === 'date_asc'
        ? 'selected'
        : ''
    ?>
>
    วันที่เก่า → ใหม่
</option>


<option
    value="date_desc"
    <?= $sort === 'date_desc'
        ? 'selected'
        : ''
    ?>
>
    วันที่ใหม่ → เก่า
</option>


<option
    value="year_desc"
    <?= $sort === 'year_desc'
        ? 'selected'
        : ''
    ?>
>
    ปีการศึกษาล่าสุด
</option>


</select>

</div>


<div
    class="
        col-md-8
        col-lg-4
        d-flex
        align-items-end
        gap-2
    "
>

<button
    type="submit"
    class="
        btn
        btn-mbs-primary
        flex-fill
    "
>

    <i class="bi bi-search"></i>

    ค้นหา

</button>


<a
    href="index.php"
    class="
        btn
        btn-outline-secondary
    "
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
    <?= count($events) ?>
</strong>

<span class="text-secondary">
    รายการ
</span>

</div>


<div
    class="
        card
        border-0
        shadow-sm
    "
>


<div class="card-body p-0">


<div class="table-responsive">


<table
    class="
        table
        table-hover
        align-middle
        mb-0
    "
>


<thead class="table-light">

<tr>

<th class="ps-3">
    ID
</th>

<th>
    ปีการศึกษา
</th>

<th>
    ระดับ
</th>

<th>
    ภาค
</th>

<th>
    กิจกรรม
</th>

<th>
    กลุ่มเป้าหมาย
</th>

<th>
    ช่วง
</th>

<th>
    วันที่
</th>

<th>
    สถานะ
</th>

<th
    class="text-center"
    style="min-width: 260px;"
>
    จัดการ
</th>

</tr>

</thead>


<tbody>


<?php if (!$events): ?>

<tr>

<td
    colspan="10"
    class="
        text-center
        text-secondary
        py-5
    "
>

    ยังไม่มีข้อมูลปฏิทินการศึกษา

</td>

</tr>

<?php endif; ?>


<?php foreach ($events as $row): ?>

<tr>


<td class="ps-3">

<span class="calendar-id">

    <?= (int)$row['id'] ?>

</span>

</td>


<td>

<strong>

    <?= (int)$row[
        'academic_year'
    ] ?>

</strong>

</td>


<td>

<span
    class="
        badge
        text-bg-light
        border
    "
>

    <?= degreeName(
        $row['degree_level']
    ) ?>

</span>

</td>


<td>

<?php if ($row['semester']): ?>

<span
    class="
        badge
        text-bg-<?= semesterBadge(
            $row['semester']
        ) ?>
    "
>

    <?= semesterName(
        $row['semester']
    ) ?>

</span>

<?php else: ?>

<span class="text-secondary">
    -
</span>

<?php endif; ?>

</td>


<td style="min-width: 300px;">

<div class="fw-semibold calendar-event-name">

    <?= htmlspecialchars(
        $row['event_name']
    ) ?>

</div>


<div class="calendar-meta mt-1">

    <span>

        <i class="bi bi-tag"></i>

        <?= htmlspecialchars(
            categoryName(
                $row['category_code']
            )
        ) ?>

    </span>


    <?php if (
        !empty(
            $row['student_code']
        )
    ): ?>

    <span>

        <i class="bi bi-person-vcard"></i>

        รหัส
        <?= htmlspecialchars(
            $row['student_code']
        ) ?>

    </span>

    <?php endif; ?>


</div>

</td>


<td style="min-width: 180px;">

<?= !empty(
    $row['audience_text']
)
    ? htmlspecialchars(
        $row['audience_text']
    )
    : '<span class="text-secondary">-</span>'
?>

</td>


<td>

<?php if ($row['phase_no']): ?>

<div class="fw-semibold">

    ช่วงที่
    <?= (int)$row['phase_no'] ?>

</div>


<?php if (
    !empty(
        $row['phase_name']
    )
): ?>

<div
    class="
        small
        text-secondary
        phase-name
    "
>

    <?= htmlspecialchars(
        $row['phase_name']
    ) ?>

</div>

<?php endif; ?>


<?php else: ?>

<span class="text-secondary">
    -
</span>

<?php endif; ?>

</td>


<td style="min-width: 190px;">


<?php if (
    $row['event_status']
    === 'no_activity'
): ?>


<span class="calendar-no-activity">

    <i class="bi bi-dash-circle"></i>

    ไม่มีกิจกรรม

</span>


<?php else: ?>


<div class="calendar-date">

<i class="bi bi-calendar3"></i>


<span>

    <?= thaiDate(
        $row['start_date']
    ) ?>


    <?php if (
        $row['end_date'] &&
        $row['end_date']
        !== $row['start_date']
    ): ?>

        <br>

        <span class="date-to">
            ถึง
        </span>

        <?= thaiDate(
            $row['end_date']
        ) ?>

    <?php endif; ?>

</span>

</div>


<?php endif; ?>


</td>


<td>


<?php if (
    (int)$row['active']
    === 1
): ?>


<span
    class="
        badge
        rounded-pill
        text-bg-success
    "
>

    ใช้งาน

</span>


<?php else: ?>


<span
    class="
        badge
        rounded-pill
        text-bg-secondary
    "
>

    ปิดใช้งาน

</span>


<?php endif; ?>


</td>


<td>


<div
    class="
        d-flex
        justify-content-center
        gap-2
        flex-wrap
    "
>


<a
    href="detail.php?id=<?= (int)$row['id'] ?>"
    class="
        btn
        btn-outline-primary
        btn-sm
    "
>

    <i class="bi bi-eye"></i>

    ดูข้อมูล

</a>


<a
    href="save.php?id=<?= (int)$row['id'] ?>"
    class="
        btn
        btn-warning
        btn-sm
    "
>

    <i class="bi bi-pencil"></i>

    แก้ไข

</a>


<form
    method="post"
    action="delete.php"
    class="m-0"
    onsubmit="
        return confirm(
            'ยืนยันการลบกำหนดการนี้หรือไม่?'
        );
    "
>


<input
    type="hidden"
    name="id"
    value="<?= (int)$row['id'] ?>"
>


<button
    type="submit"
    class="
        btn
        btn-danger
        btn-sm
    "
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


                        localStorage
                            .setItem(
                                'mbsSidebarCollapsed',
                                collapsed
                                    ? '1'
                                    : '0'
                            );
                    }
                );
        }


        if (mobileMenuBtn) {

            mobileMenuBtn
                .addEventListener(
                    'click',
                    openSidebar
                );
        }


        if (sidebarOverlay) {

            sidebarOverlay
                .addEventListener(
                    'click',
                    closeSidebar
                );
        }


        window.addEventListener(
            'resize',
            function () {

                if (
                    window.innerWidth
                    >= 992
                ) {

                    closeSidebar();


                    if (
                        localStorage
                            .getItem(
                                'mbsSidebarCollapsed'
                            )
                        === '1'
                    ) {

                        document.body
                            .classList.add(
                                'sidebar-collapsed'
                            );

                    } else {

                        document.body
                            .classList.remove(
                                'sidebar-collapsed'
                            );
                    }

                } else {

                    document.body
                        .classList.remove(
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