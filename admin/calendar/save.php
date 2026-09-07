<?php

require_once "../../api/db.php";
require_once "../admin_activity.php";

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

$error = '';
$isEdit = false;


// =========================================================
// DEFAULT DATA
// =========================================================

$data = [

    'academic_year' => 2569,

    'degree_level' => 'bachelor',

    'semester' => '',

    'category_code' => '',

    'event_name' => '',

    'phase_no' => '',

    'phase_name' => '',

    'year_level' => '',

    'student_code' => '',

    'audience_text' => '',

    'start_date' => '',

    'end_date' => '',

    'event_status' => 'scheduled',

    'location_or_channel' => '',

    'fee_note' => '',

    'note' => '',

    'keywords' => '',

    'sort_order' => 0,

    'active' => 1
];


// =========================================================
// CATEGORY OPTIONS
// =========================================================

$categoryOptions = [

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
        'ยกเลิกผลการลงทะเบียน'
];


// =========================================================
// LOAD EDIT DATA
// =========================================================

if ($id) {

    $stmt = $pdo->prepare("
        SELECT *
        FROM academic_calendar_events
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $id
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        exit("ไม่พบข้อมูลปฏิทิน");
    }

    $data = array_merge(
        $data,
        $row
    );

    $isEdit = true;
}


// =========================================================
// SAVE
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedId = filter_input(
        INPUT_POST,
        'id',
        FILTER_VALIDATE_INT
    );


    $academicYear =
        (int)(
            $_POST['academic_year']
            ?? 0
        );


    $degreeLevel =
        trim(
            $_POST['degree_level']
            ?? ''
        );


    $semester =
        trim(
            $_POST['semester']
            ?? ''
        );


    $categoryCode =
        trim(
            $_POST['category_code']
            ?? ''
        );


    $eventName =
        trim(
            $_POST['event_name']
            ?? ''
        );


    $phaseNo =
        trim(
            $_POST['phase_no']
            ?? ''
        );


    $phaseName =
        trim(
            $_POST['phase_name']
            ?? ''
        );


    $yearLevel =
        trim(
            $_POST['year_level']
            ?? ''
        );


    $studentCode =
        trim(
            $_POST['student_code']
            ?? ''
        );


    $audienceText =
        trim(
            $_POST['audience_text']
            ?? ''
        );


    $startDate =
        trim(
            $_POST['start_date']
            ?? ''
        );


    $endDate =
        trim(
            $_POST['end_date']
            ?? ''
        );


    $eventStatus =
        trim(
            $_POST['event_status']
            ?? 'scheduled'
        );


    $location =
        trim(
            $_POST['location_or_channel']
            ?? ''
        );


    $feeNote =
        trim(
            $_POST['fee_note']
            ?? ''
        );


    $note =
        trim(
            $_POST['note']
            ?? ''
        );


    $keywords =
        trim(
            $_POST['keywords']
            ?? ''
        );


    $sortOrder =
        (int)(
            $_POST['sort_order']
            ?? 0
        );


    $active =
        isset($_POST['active'])
        ? 1
        : 0;


    // =====================================================
    // VALIDATE
    // =====================================================

    if (
        !$academicYear ||
        $categoryCode === '' ||
        $eventName === ''
    ) {

        $error =
            "กรุณากรอกข้อมูลที่จำเป็นให้ครบ";

    } elseif (
        !array_key_exists(
            $categoryCode,
            $categoryOptions
        )
    ) {

        $error =
            "หมวดปฏิทินไม่ถูกต้อง";

    } elseif (
        $startDate !== '' &&
        $endDate !== '' &&
        $endDate < $startDate
    ) {

        $error =
            "วันที่สิ้นสุดต้องไม่น้อยกว่าวันที่เริ่ม";

    } else {

        try {

            // =================================================
            // UPDATE
            // =================================================

            if ($postedId) {

                $sql = "
                    UPDATE academic_calendar_events
                    SET

                        academic_year = :academic_year,

                        degree_level = :degree_level,

                        semester = :semester,

                        category_code = :category_code,

                        event_name = :event_name,

                        phase_no = :phase_no,

                        phase_name = :phase_name,

                        year_level = :year_level,

                        student_code = :student_code,

                        audience_text = :audience_text,

                        start_date = :start_date,

                        end_date = :end_date,

                        event_status = :event_status,

                        location_or_channel = :location,

                        fee_note = :fee_note,

                        note = :note,

                        keywords = :keywords,

                        sort_order = :sort_order,

                        active = :active

                    WHERE id = :id
                ";

            } else {

                // =================================================
                // INSERT
                // =================================================

                $sql = "
                    INSERT INTO academic_calendar_events
                    (
                        academic_year,

                        degree_level,

                        semester,

                        category_code,

                        event_name,

                        phase_no,

                        phase_name,

                        year_level,

                        student_code,

                        audience_text,

                        start_date,

                        end_date,

                        event_status,

                        location_or_channel,

                        fee_note,

                        note,

                        keywords,

                        sort_order,

                        active
                    )

                    VALUES
                    (
                        :academic_year,

                        :degree_level,

                        :semester,

                        :category_code,

                        :event_name,

                        :phase_no,

                        :phase_name,

                        :year_level,

                        :student_code,

                        :audience_text,

                        :start_date,

                        :end_date,

                        :event_status,

                        :location,

                        :fee_note,

                        :note,

                        :keywords,

                        :sort_order,

                        :active
                    )
                ";
            }


            $stmt =
                $pdo->prepare($sql);


            $params = [

                ':academic_year' =>
                    $academicYear,

                ':degree_level' =>
                    $degreeLevel,

                ':semester' =>
                    $semester ?: null,

                ':category_code' =>
                    $categoryCode,

                ':event_name' =>
                    $eventName,

                ':phase_no' =>
                    $phaseNo !== ''
                    ? (int)$phaseNo
                    : null,

                ':phase_name' =>
                    $phaseName ?: null,

                ':year_level' =>
                    $yearLevel ?: null,

                ':student_code' =>
                    $studentCode ?: null,

                ':audience_text' =>
                    $audienceText ?: null,

                ':start_date' =>
                    $startDate ?: null,

                ':end_date' =>
                    $endDate ?: null,

                ':event_status' =>
                    $eventStatus,

                ':location' =>
                    $location ?: null,

                ':fee_note' =>
                    $feeNote ?: null,

                ':note' =>
                    $note ?: null,

                ':keywords' =>
                    $keywords ?: null,

                ':sort_order' =>
                    $sortOrder,

                ':active' =>
                    $active
            ];


            if ($postedId) {

                $params[':id'] =
                    $postedId;
            }


            $stmt->execute($params);


            // =================================================
            // ACTIVITY LOG
            // =================================================

            if ($postedId) {

                logAdminActivity(
                    $pdo,
                    'calendar',
                    'update',
                    $eventName,
                    'แก้ไขข้อมูลปฏิทินการศึกษา'
                );


                header(
                    "Location: index.php?success=edit"
                );

            } else {

                logAdminActivity(
                    $pdo,
                    'calendar',
                    'create',
                    $eventName,
                    'เพิ่มข้อมูลปฏิทินการศึกษา'
                );


                header(
                    "Location: index.php?success=create"
                );
            }


            exit;


        } catch (PDOException $e) {

            $error =
                "บันทึกไม่สำเร็จ: "
                .
                $e->getMessage();
        }
    }


    // ให้ข้อมูลที่กรอกค้างอยู่ในฟอร์ม
    // กรณี validation ไม่ผ่าน

    $data = array_merge(
        $data,
        [
            'academic_year' =>
                $academicYear,

            'degree_level' =>
                $degreeLevel,

            'semester' =>
                $semester,

            'category_code' =>
                $categoryCode,

            'event_name' =>
                $eventName,

            'phase_no' =>
                $phaseNo,

            'phase_name' =>
                $phaseName,

            'year_level' =>
                $yearLevel,

            'student_code' =>
                $studentCode,

            'audience_text' =>
                $audienceText,

            'start_date' =>
                $startDate,

            'end_date' =>
                $endDate,

            'event_status' =>
                $eventStatus,

            'location_or_channel' =>
                $location,

            'fee_note' =>
                $feeNote,

            'note' =>
                $note,

            'keywords' =>
                $keywords,

            'sort_order' =>
                $sortOrder,

            'active' =>
                $active
        ]
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
    <?= $isEdit
        ? 'แก้ไขปฏิทินการศึกษา'
        : 'เพิ่มปฏิทินการศึกษา'
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

    <?= $isEdit
        ? 'แก้ไขปฏิทินการศึกษา'
        : 'เพิ่มปฏิทินการศึกษา'
    ?>

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

    <?= $isEdit
        ? 'EDIT CALENDAR'
        : 'NEW CALENDAR'
    ?>

</span>


<h1>

    <?= $isEdit
        ? 'แก้ไขกำหนดการ'
        : 'เพิ่มกำหนดการ'
    ?>

</h1>


<p>

    <?= $isEdit
        ? 'ปรับปรุงข้อมูลปฏิทินการศึกษาให้ถูกต้องและพร้อมใช้งานกับระบบ MBS UniWise AI'
        : 'เพิ่มกิจกรรมและกำหนดการใหม่เข้าสู่ฐานข้อมูลปฏิทินการศึกษา'
    ?>

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
    CALENDAR FORM
</span>


<h2>

    <?= $isEdit
        ? 'แก้ไขข้อมูลปฏิทิน'
        : 'ข้อมูลกำหนดการใหม่'
    ?>

</h2>


<p>

<?php if ($isEdit): ?>

    ID <?= (int)$data['id'] ?>
    • แก้ไขข้อมูลกิจกรรมที่มีอยู่ในระบบ

<?php else: ?>

    กรอกข้อมูลกิจกรรมให้ครบถ้วน
    ก่อนบันทึกเข้าสู่ฐานข้อมูล

<?php endif; ?>

</p>

</div>


</div>


<?php if ($error): ?>

<div
    class="
        alert
        alert-danger
        alert-dismissible
        fade
        show
    "
>

    <i class="bi bi-exclamation-circle me-1"></i>

    <?= htmlspecialchars(
        $error
    ) ?>


<button
    type="button"
    class="btn-close"
    data-bs-dismiss="alert"
></button>

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


<!-- ======================================================
     GENERAL
====================================================== -->

<div class="card border-0 shadow-sm mb-4">

<div class="card-body p-4">


<div class="detail-section-title">

<div class="detail-section-icon">

    <i class="bi bi-calendar-event-fill"></i>

</div>


<div>

<h3>
    ข้อมูลทั่วไป
</h3>

<p>
    ระบุปี ระดับการศึกษา ภาคการศึกษา
    และประเภทของกิจกรรม
</p>

</div>

</div>


<div class="row g-3">


<div class="col-md-3">

<label class="form-label">
    ปีการศึกษา
    <span class="required-star">*</span>
</label>

<input
    type="number"
    name="academic_year"
    class="form-control"
    value="<?= htmlspecialchars(
        $data['academic_year']
    ) ?>"
    placeholder="เช่น 2569"
    required
>

</div>


<div class="col-md-3">

<label class="form-label">
    ระดับการศึกษา
</label>

<select
    name="degree_level"
    class="form-select"
>

<option
    value="bachelor"
    <?= $data['degree_level']
        === 'bachelor'
        ? 'selected'
        : ''
    ?>
>
    ปริญญาตรี
</option>


<option
    value="master"
    <?= $data['degree_level']
        === 'master'
        ? 'selected'
        : ''
    ?>
>
    ปริญญาโท
</option>


<option
    value="doctoral"
    <?= $data['degree_level']
        === 'doctoral'
        ? 'selected'
        : ''
    ?>
>
    ปริญญาเอก
</option>

</select>

</div>


<div class="col-md-3">

<label class="form-label">
    ภาคการศึกษา
</label>

<select
    name="semester"
    class="form-select"
>

<option value="">
    ไม่ระบุ
</option>


<option
    value="first"
    <?= $data['semester']
        === 'first'
        ? 'selected'
        : ''
    ?>
>
    ภาคต้น
</option>


<option
    value="second"
    <?= $data['semester']
        === 'second'
        ? 'selected'
        : ''
    ?>
>
    ภาคปลาย
</option>


<option
    value="summer"
    <?= $data['semester']
        === 'summer'
        ? 'selected'
        : ''
    ?>
>
    ภาคฤดูร้อน
</option>

</select>

</div>


<div class="col-md-3">

<label class="form-label">
    หมวด
    <span class="required-star">*</span>
</label>


<select
    name="category_code"
    class="form-select"
    required
>

<option value="">
    เลือกหมวด
</option>


<?php foreach (
    $categoryOptions
    as $code => $label
): ?>

<option
    value="<?= htmlspecialchars(
        $code
    ) ?>"
    <?= $data['category_code']
        === $code
        ? 'selected'
        : ''
    ?>
>

    <?= htmlspecialchars(
        $label
    ) ?>

</option>

<?php endforeach; ?>


</select>


<div class="form-help">

    ระบบจะเก็บรหัสหมวดภาษาอังกฤษ
    เพื่อใช้กับ API และ Dify

</div>

</div>


<div class="col-12">

<label class="form-label">

    ชื่อกิจกรรม
    <span class="required-star">*</span>

</label>


<textarea
    name="event_name"
    class="form-control"
    rows="3"
    placeholder="เช่น ลงทะเบียนเรียน ภาคต้น ปีการศึกษา 2569"
    required
><?= htmlspecialchars(
    $data['event_name']
) ?></textarea>

</div>


</div>


</div>

</div>


<!-- ======================================================
     AUDIENCE
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
    กำหนดชั้นปี รหัสนิสิต
    และช่วงของกิจกรรม
</p>

</div>

</div>


<div class="row g-3">


<div class="col-md-2">

<label class="form-label">
    ช่วงที่
</label>

<input
    type="number"
    min="1"
    name="phase_no"
    class="form-control"
    value="<?= htmlspecialchars(
        $data['phase_no']
    ) ?>"
    placeholder="1"
>

</div>


<div class="col-md-10">

<label class="form-label">
    ชื่อช่วง
</label>

<input
    type="text"
    name="phase_name"
    class="form-control"
    value="<?= htmlspecialchars(
        $data['phase_name']
    ) ?>"
    placeholder="เช่น ลงทะเบียนเรียนด้วยตนเองผ่านเว็บไซต์ปกติ"
>

</div>


<div class="col-md-3">

<label class="form-label">
    ชั้นปี
</label>

<input
    type="text"
    name="year_level"
    class="form-control"
    value="<?= htmlspecialchars(
        $data['year_level']
    ) ?>"
    placeholder="เช่น 1, 2, 3, 4+, all"
>

</div>


<div class="col-md-3">

<label class="form-label">
    รหัสนิสิต
</label>

<input
    type="text"
    name="student_code"
    class="form-control"
    value="<?= htmlspecialchars(
        $data['student_code']
    ) ?>"
    placeholder="เช่น 69 หรือ 66 ลงไป"
>

</div>


<div class="col-md-6">

<label class="form-label">
    กลุ่มเป้าหมาย
</label>

<input
    type="text"
    name="audience_text"
    class="form-control"
    value="<?= htmlspecialchars(
        $data['audience_text']
    ) ?>"
    placeholder="เช่น ชั้นปีที่ 2 รหัส 68"
>

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
    วันและสถานะกิจกรรม
</h3>

<p>
    กำหนดวันเริ่ม วันสิ้นสุด
    และสถานะของกิจกรรม
</p>

</div>

</div>


<div class="row g-3">


<div class="col-md-3">

<label class="form-label">
    วันที่เริ่ม
</label>

<input
    type="date"
    name="start_date"
    class="form-control"
    value="<?= htmlspecialchars(
        $data['start_date']
    ) ?>"
>

</div>


<div class="col-md-3">

<label class="form-label">
    วันที่สิ้นสุด
</label>

<input
    type="date"
    name="end_date"
    class="form-control"
    value="<?= htmlspecialchars(
        $data['end_date']
    ) ?>"
>

</div>


<div class="col-md-3">

<label class="form-label">
    สถานะกิจกรรม
</label>

<select
    name="event_status"
    class="form-select"
>

<option
    value="scheduled"
    <?= $data['event_status']
        === 'scheduled'
        ? 'selected'
        : ''
    ?>
>
    มีกิจกรรม
</option>


<option
    value="no_activity"
    <?= $data['event_status']
        === 'no_activity'
        ? 'selected'
        : ''
    ?>
>
    ไม่มีกิจกรรม
</option>

</select>

</div>


<div class="col-md-3">

<label class="form-label">
    ลำดับแสดงผล
</label>

<input
    type="number"
    name="sort_order"
    class="form-control"
    value="<?= htmlspecialchars(
        $data['sort_order']
    ) ?>"
>

</div>


</div>


</div>

</div>


<!-- ======================================================
     EXTRA
====================================================== -->

<div class="card border-0 shadow-sm mb-4">

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
    สถานที่ ค่าใช้จ่าย หมายเหตุ
    และคำค้นสำหรับระบบ
</p>

</div>

</div>


<div class="row g-3">


<div class="col-md-6">

<label class="form-label">
    สถานที่ / ช่องทาง
</label>

<input
    type="text"
    name="location_or_channel"
    class="form-control"
    value="<?= htmlspecialchars(
        $data['location_or_channel']
    ) ?>"
    placeholder="เช่น เว็บไซต์ลงทะเบียนเรียน หรือ กองทะเบียนและประมวลผล"
>

</div>


<div class="col-md-6">

<label class="form-label">
    ค่าปรับ / ค่าใช้จ่าย
</label>

<input
    type="text"
    name="fee_note"
    class="form-control"
    value="<?= htmlspecialchars(
        $data['fee_note']
    ) ?>"
    placeholder="เช่น มีค่าปรับ"
>

</div>


<div class="col-12">

<label class="form-label">
    หมายเหตุ
</label>

<textarea
    name="note"
    class="form-control"
    rows="4"
    placeholder="รายละเอียดเพิ่มเติมของกิจกรรม"
><?= htmlspecialchars(
    $data['note']
) ?></textarea>

</div>


<div class="col-12">

<label class="form-label">
    Keywords สำหรับค้นหา
</label>

<textarea
    name="keywords"
    class="form-control"
    rows="3"
    placeholder="เช่น ลงทะเบียน เทอม 1 ภาคต้น ปี 2"
><?= htmlspecialchars(
    $data['keywords']
) ?></textarea>


<div class="form-help">

    ใช้ช่วยให้ API และระบบ AI
    ค้นหารายการนี้ได้ง่ายขึ้น

</div>

</div>


</div>


</div>

</div>


<!-- ======================================================
     ACTIVE / ACTION
====================================================== -->

<div class="card border-0 shadow-sm mb-5">

<div class="card-body p-4">


<div
    class="
        d-flex
        flex-column
        flex-lg-row
        justify-content-between
        align-items-lg-center
        gap-4
    "
>


<div>


<div class="form-check form-switch">

<input
    type="checkbox"
    name="active"
    id="active"
    class="form-check-input"
    <?= (int)$data['active']
        === 1
        ? 'checked'
        : ''
    ?>
>


<label
    for="active"
    class="form-check-label fw-semibold"
>

    เปิดใช้งานข้อมูลนี้

</label>

</div>


<div class="form-help ms-1 mt-2">

    เมื่อปิดใช้งาน API ที่ใช้
    active=1 จะไม่ส่งรายการนี้ให้ Dify

</div>


</div>


<div
    class="
        d-flex
        gap-2
        flex-wrap
    "
>


<a
    href="index.php"
    class="btn btn-outline-secondary px-4"
>

    <i class="bi bi-x-lg"></i>

    ยกเลิก

</a>


<button
    type="submit"
    class="btn btn-mbs-primary px-5"
>

    <i class="bi bi-check-lg"></i>

    <?= $isEdit
        ? 'บันทึกการแก้ไข'
        : 'บันทึกข้อมูล'
    ?>

</button>


</div>


</div>


</div>

</div>


</form>


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
                sidebarOverlay.classList.add(
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
                sidebarOverlay.classList.remove(
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

            document.body.classList.add(
                'sidebar-collapsed'
            );
        }


        if (sidebarToggle) {

            sidebarToggle.addEventListener(
                'click',
                function () {

                    if (
                        window.innerWidth < 992
                    ) {
                        return;
                    }


                    document.body.classList.toggle(
                        'sidebar-collapsed'
                    );


                    const collapsed =
                        document.body.classList.contains(
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