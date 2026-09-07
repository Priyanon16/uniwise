<?php

require_once "../../api/db.php";

/* =========================================================
   SIDEBAR
========================================================= */

$activeMenu = 'internship';
$basePath   = '../';


/* =========================================================
   SECTION
========================================================= */

$allowedSections = [
    'types',
    'periods',
    'steps',
    'documents',
    'applications'
];

$section = $_GET['section'] ?? 'types';

if (!in_array($section, $allowedSections, true)) {
    $section = 'types';
}


/* =========================================================
   EDIT
========================================================= */

$editId = isset($_GET['edit'])
    ? (int) $_GET['edit']
    : 0;

$editData = null;


/* =========================================================
   INTERNSHIP TYPES
========================================================= */

$typeStmt = $pdo->query("
    SELECT id, type_name_th
    FROM internship_types
    ORDER BY id
");

$internshipTypes = $typeStmt->fetchAll();


/* =========================================================
   TYPES
========================================================= */

if ($section === 'types') {

    $stmt = $pdo->query("
        SELECT *
        FROM internship_types
        ORDER BY id
    ");

    $rows = $stmt->fetchAll();

    if ($editId > 0) {

        $stmt = $pdo->prepare("
            SELECT *
            FROM internship_types
            WHERE id = ?
        ");

        $stmt->execute([$editId]);

        $editData = $stmt->fetch();
    }


/* =========================================================
   PERIODS
========================================================= */

} elseif ($section === 'periods') {

    $stmt = $pdo->query("
        SELECT
            p.*,
            t.type_name_th
        FROM internship_periods p

        LEFT JOIN internship_types t
            ON p.internship_type_id = t.id

        ORDER BY
            p.academic_year DESC,
            p.id DESC
    ");

    $rows = $stmt->fetchAll();

    if ($editId > 0) {

        $stmt = $pdo->prepare("
            SELECT *
            FROM internship_periods
            WHERE id = ?
        ");

        $stmt->execute([$editId]);

        $editData = $stmt->fetch();
    }


/* =========================================================
   STEPS
========================================================= */

} elseif ($section === 'steps') {

    $stmt = $pdo->query("
        SELECT
            s.*,
            t.type_name_th
        FROM internship_steps s

        LEFT JOIN internship_types t
            ON s.internship_type_id = t.id

        ORDER BY
            s.step_order ASC
    ");

    $rows = $stmt->fetchAll();

    if ($editId > 0) {

        $stmt = $pdo->prepare("
            SELECT *
            FROM internship_steps
            WHERE id = ?
        ");

        $stmt->execute([$editId]);

        $editData = $stmt->fetch();
    }


/* =========================================================
   DOCUMENTS
========================================================= */

} elseif ($section === 'documents') {

    $stmt = $pdo->query("
        SELECT *
        FROM internship_document_requirements
        ORDER BY
            display_order ASC,
            id ASC
    ");

    $rows = $stmt->fetchAll();

    if ($editId > 0) {

        $stmt = $pdo->prepare("
            SELECT *
            FROM internship_document_requirements
            WHERE id = ?
        ");

        $stmt->execute([$editId]);

        $editData = $stmt->fetch();
    }


/* =========================================================
   APPLICATION PERIODS
========================================================= */

} elseif ($section === 'applications') {

    $stmt = $pdo->query("
        SELECT
            a.*,
            t.type_name_th
        FROM application_periods a

        LEFT JOIN internship_types t
            ON a.internship_type_id = t.id

        ORDER BY
            a.academic_year DESC,
            a.id DESC
    ");

    $rows = $stmt->fetchAll();

    if ($editId > 0) {

        $stmt = $pdo->prepare("
            SELECT *
            FROM application_periods
            WHERE id = ?
        ");

        $stmt->execute([$editId]);

        $editData = $stmt->fetch();
    }
}


/* =========================================================
   HELPERS
========================================================= */

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function selected($value1, $value2)
{
    return (string) $value1 === (string) $value2
        ? 'selected'
        : '';
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
        MBS Internship Management
    </title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- Google Font -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >


    <!-- Bootstrap Icons -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >


    <!-- Shared Admin CSS -->

    <link
        rel="stylesheet"
        href="../assets/admin.css?v=<?= filemtime(__DIR__ . '/../assets/admin.css') ?>"
    >


    <!-- Internship CSS -->

    <link
        rel="stylesheet"
        href="assets/internship.css?v=<?= filemtime(__DIR__ . '/assets/internship.css') ?>"
    >

</head>


<body>


<div class="admin-layout">


    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <?php include "../includes/sidebar.php"; ?>



    <!-- =====================================================
         MAIN
    ====================================================== -->

    <div class="main-shell">


        <!-- =================================================
             TOPBAR
        ================================================== -->

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
                    MBS UNIWISE ADMIN
                </span>

                <strong>
                    ฝึกงานและสหกิจศึกษา
                </strong>

            </div>


            <div class="topbar-status">

                <span class="status-dot"></span>

                Faculty Admin System

            </div>


        </header>



        <!-- =================================================
             CONTENT
        ================================================== -->

        <main class="content-area">


            <!-- =================================================
                 HERO
            ================================================== -->

            <section class="internship-hero">


                <div class="internship-hero-copy">


                    <span class="internship-eyebrow">

                        <span></span>

                        MBS • MAHASARAKHAM UNIVERSITY

                    </span>


                    <h1>
                        ระบบจัดการข้อมูล<br>
                        การฝึกงานและสหกิจศึกษา
                    </h1>


                    <p>
                        คณะการบัญชีและการจัดการ
                        มหาวิทยาลัยมหาสารคาม
                    </p>


                    <div class="internship-hero-meta">

                        <span>

                            <i class="bi bi-database-check"></i>

                            จัดการข้อมูลส่วนกลาง

                        </span>


                        <span>

                            <i class="bi bi-shield-check"></i>

                            สำหรับผู้ดูแลระบบ

                        </span>

                    </div>


                </div>


            </section>



            <!-- =================================================
                 ALERT
            ================================================== -->

            <?php if (isset($_GET['success'])): ?>

                <div class="internship-alert">

                    <i class="bi bi-check-circle-fill"></i>

                    <span>
                        บันทึกข้อมูลเรียบร้อยแล้ว
                    </span>

                </div>

            <?php endif; ?>


            <?php if (isset($_GET['deleted'])): ?>

                <div class="internship-alert">

                    <i class="bi bi-check-circle-fill"></i>

                    <span>
                        ลบข้อมูลเรียบร้อยแล้ว
                    </span>

                </div>

            <?php endif; ?>



            <!-- =================================================
                 WORKSPACE
            ================================================== -->

            <div class="workspace-heading">


                <div>

                    <span class="workspace-kicker">
                        INTERNSHIP DATA CENTER
                    </span>

                    <h2>
                        จัดการข้อมูลภายในระบบ
                    </h2>

                    <p>
                        เลือกหมวดข้อมูลที่ต้องการเพิ่ม
                        แก้ไข หรือตรวจสอบ
                    </p>

                </div>


                <div class="workspace-chip">

                    <i class="bi bi-buildings"></i>

                    MBS MSU

                </div>


            </div>



            <!-- =================================================
                 SECTION NAVIGATION
            ================================================== -->

            <ul class="nav internship-nav mb-4">


                <li class="nav-item">

                    <a
                        class="nav-link <?= $section === 'types' ? 'active' : '' ?>"
                        href="?section=types"
                    >

                        <i class="bi bi-tags"></i>

                        ประเภทการฝึกงาน

                    </a>

                </li>


                <li class="nav-item">

                    <a
                        class="nav-link <?= $section === 'periods' ? 'active' : '' ?>"
                        href="?section=periods"
                    >

                        <i class="bi bi-calendar-range"></i>

                        ช่วงปฏิบัติงาน

                    </a>

                </li>


                <li class="nav-item">

                    <a
                        class="nav-link <?= $section === 'steps' ? 'active' : '' ?>"
                        href="?section=steps"
                    >

                        <i class="bi bi-list-check"></i>

                        ขั้นตอน

                    </a>

                </li>


                <li class="nav-item">

                    <a
                        class="nav-link <?= $section === 'documents' ? 'active' : '' ?>"
                        href="?section=documents"
                    >

                        <i class="bi bi-file-earmark-text"></i>

                        เอกสาร

                    </a>

                </li>


                <li class="nav-item">

                    <a
                        class="nav-link <?= $section === 'applications' ? 'active' : '' ?>"
                        href="?section=applications"
                    >

                        <i class="bi bi-calendar2-check"></i>

                        ช่วงรับสมัคร

                    </a>

                </li>


            </ul>



            <!-- =================================================
                 FORM CARD
            ================================================== -->

            <section class="internship-card mb-4">


                <div class="internship-card-header">


                    <div class="internship-card-title">


                        <span class="section-icon">

                            <i
                                class="bi <?= $editData
                                    ? 'bi-pencil-square'
                                    : 'bi-plus-lg' ?>"
                            ></i>

                        </span>


                        <span>

                            <strong>

                                <?= $editData
                                    ? 'แก้ไขข้อมูล'
                                    : 'เพิ่มข้อมูล' ?>

                            </strong>


                            <small>

                                <?= $editData
                                    ? 'ปรับปรุงรายละเอียดแล้วกดบันทึก'
                                    : 'กรอกข้อมูลที่ต้องการเพิ่มลงในระบบ' ?>

                            </small>

                        </span>


                    </div>


                </div>



                <div class="internship-card-body">


                    <form
                        action="save.php"
                        method="post"
                    >


                        <input
                            type="hidden"
                            name="section"
                            value="<?= e($section) ?>"
                        >


                        <input
                            type="hidden"
                            name="id"
                            value="<?= e($editData['id'] ?? '') ?>"
                        >



                        <!-- =====================================
                             TYPES
                        ====================================== -->

                        <?php if ($section === 'types'): ?>


                            <div class="row g-3">


                                <div class="col-md-4">

                                    <label class="form-label">
                                        รหัสประเภท
                                    </label>

                                    <input
                                        type="text"
                                        name="type_code"
                                        class="form-control"
                                        required
                                        placeholder="เช่น internship"
                                        value="<?= e($editData['type_code'] ?? '') ?>"
                                    >

                                </div>


                                <div class="col-md-4">

                                    <label class="form-label">
                                        ชื่อภาษาไทย
                                    </label>

                                    <input
                                        type="text"
                                        name="type_name_th"
                                        class="form-control"
                                        required
                                        value="<?= e($editData['type_name_th'] ?? '') ?>"
                                    >

                                </div>


                                <div class="col-md-4">

                                    <label class="form-label">
                                        ชื่อภาษาอังกฤษ
                                    </label>

                                    <input
                                        type="text"
                                        name="type_name_en"
                                        class="form-control"
                                        value="<?= e($editData['type_name_en'] ?? '') ?>"
                                    >

                                </div>


                                <div class="col-md-4">

                                    <label class="form-label">
                                        ระยะเวลา (เดือน)
                                    </label>

                                    <input
                                        type="number"
                                        name="duration_months"
                                        class="form-control"
                                        min="0"
                                        value="<?= e($editData['duration_months'] ?? '') ?>"
                                    >

                                </div>


                                <div class="col-md-4">

                                    <label class="form-label">
                                        ชั้นปี
                                    </label>

                                    <input
                                        type="number"
                                        name="study_year"
                                        class="form-control"
                                        min="1"
                                        value="<?= e($editData['study_year'] ?? '') ?>"
                                    >

                                </div>


                                <div class="col-md-4">

                                    <label class="form-label">
                                        ภาคเรียน
                                    </label>

                                    <input
                                        type="text"
                                        name="study_term"
                                        class="form-control"
                                        value="<?= e($editData['study_term'] ?? '') ?>"
                                    >

                                </div>


                                <div class="col-12">

                                    <label class="form-label">
                                        รายละเอียด
                                    </label>

                                    <textarea
                                        name="description"
                                        class="form-control"
                                        rows="4"
                                    ><?= e($editData['description'] ?? '') ?></textarea>

                                </div>


                            </div>



                        <!-- =====================================
                             PERIODS
                        ====================================== -->

                        <?php elseif ($section === 'periods'): ?>


                            <div class="row g-3">


                                <div class="col-md-3">

                                    <label class="form-label">
                                        ปีการศึกษา
                                    </label>

                                    <input
                                        type="number"
                                        name="academic_year"
                                        class="form-control"
                                        required
                                        value="<?= e($editData['academic_year'] ?? '') ?>"
                                    >

                                </div>


                                <div class="col-md-3">

                                    <label class="form-label">
                                        ภาคเรียน
                                    </label>

                                    <input
                                        type="text"
                                        name="semester"
                                        class="form-control"
                                        required
                                        value="<?= e($editData['semester'] ?? '') ?>"
                                    >

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label">
                                        ประเภท
                                    </label>

                                    <select
                                        name="internship_type_id"
                                        class="form-select"
                                    >

                                        <option value="">
                                            -- ไม่ระบุประเภท --
                                        </option>


                                        <?php foreach ($internshipTypes as $type): ?>

                                            <option
                                                value="<?= e($type['id']) ?>"
                                                <?= selected(
                                                    $editData['internship_type_id'] ?? '',
                                                    $type['id']
                                                ) ?>
                                            >

                                                <?= e($type['type_name_th']) ?>

                                            </option>

                                        <?php endforeach; ?>


                                    </select>

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label">
                                        วันที่เริ่ม
                                    </label>

                                    <input
                                        type="date"
                                        name="start_date"
                                        class="form-control"
                                        required
                                        value="<?= e($editData['start_date'] ?? '') ?>"
                                    >

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label">
                                        วันที่สิ้นสุด
                                    </label>

                                    <input
                                        type="date"
                                        name="end_date"
                                        class="form-control"
                                        required
                                        value="<?= e($editData['end_date'] ?? '') ?>"
                                    >

                                </div>


                                <div class="col-12">

                                    <label class="form-label">
                                        หมายเหตุ
                                    </label>

                                    <textarea
                                        name="note"
                                        class="form-control"
                                        rows="3"
                                    ><?= e($editData['note'] ?? '') ?></textarea>

                                </div>


                            </div>



                        <!-- =====================================
                             STEPS
                        ====================================== -->

                        <?php elseif ($section === 'steps'): ?>


                            <div class="row g-3">


                                <div class="col-md-6">

                                    <label class="form-label">
                                        ประเภท
                                    </label>

                                    <select
                                        name="internship_type_id"
                                        class="form-select"
                                    >

                                        <option value="">
                                            ใช้กับทุกประเภท
                                        </option>


                                        <?php foreach ($internshipTypes as $type): ?>

                                            <option
                                                value="<?= e($type['id']) ?>"
                                                <?= selected(
                                                    $editData['internship_type_id'] ?? '',
                                                    $type['id']
                                                ) ?>
                                            >

                                                <?= e($type['type_name_th']) ?>

                                            </option>

                                        <?php endforeach; ?>


                                    </select>

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label">
                                        ลำดับขั้นตอน
                                    </label>

                                    <input
                                        type="number"
                                        name="step_order"
                                        class="form-control"
                                        required
                                        min="1"
                                        value="<?= e($editData['step_order'] ?? '') ?>"
                                    >

                                </div>


                                <div class="col-12">

                                    <label class="form-label">
                                        ชื่อขั้นตอน
                                    </label>

                                    <input
                                        type="text"
                                        name="title"
                                        class="form-control"
                                        required
                                        value="<?= e($editData['title'] ?? '') ?>"
                                    >

                                </div>


                                <div class="col-12">

                                    <label class="form-label">
                                        รายละเอียด
                                    </label>

                                    <textarea
                                        name="description"
                                        class="form-control"
                                        rows="4"
                                        required
                                    ><?= e($editData['description'] ?? '') ?></textarea>

                                </div>


                            </div>



                        <!-- =====================================
                             DOCUMENTS
                        ====================================== -->

                        <?php elseif ($section === 'documents'): ?>


                            <div class="row g-3">


                                <div class="col-md-8">

                                    <label class="form-label">
                                        ชื่อเอกสาร
                                    </label>

                                    <input
                                        type="text"
                                        name="document_name"
                                        class="form-control"
                                        required
                                        value="<?= e($editData['document_name'] ?? '') ?>"
                                    >

                                </div>


                                <div class="col-md-4">

                                    <label class="form-label">
                                        ลำดับการแสดงผล
                                    </label>

                                    <input
                                        type="number"
                                        name="display_order"
                                        class="form-control"
                                        min="1"
                                        value="<?= e($editData['display_order'] ?? '') ?>"
                                    >

                                </div>


                                <div class="col-12">

                                    <label class="form-label">
                                        หมายเหตุ
                                    </label>

                                    <input
                                        type="text"
                                        name="note"
                                        class="form-control"
                                        value="<?= e($editData['note'] ?? '') ?>"
                                    >

                                </div>


                            </div>



                        <!-- =====================================
                             APPLICATIONS
                        ====================================== -->

                        <?php elseif ($section === 'applications'): ?>


                            <div class="row g-3">


                                <div class="col-md-3">

                                    <label class="form-label">
                                        ปีการศึกษา
                                    </label>

                                    <input
                                        type="number"
                                        name="academic_year"
                                        class="form-control"
                                        required
                                        value="<?= e($editData['academic_year'] ?? '') ?>"
                                    >

                                </div>


                                <div class="col-md-3">

                                    <label class="form-label">
                                        ภาคเรียน
                                    </label>

                                    <input
                                        type="text"
                                        name="semester"
                                        class="form-control"
                                        required
                                        value="<?= e($editData['semester'] ?? '') ?>"
                                    >

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label">
                                        ประเภท
                                    </label>

                                    <select
                                        name="internship_type_id"
                                        class="form-select"
                                    >

                                        <option value="">
                                            -- ไม่ระบุประเภท --
                                        </option>


                                        <?php foreach ($internshipTypes as $type): ?>

                                            <option
                                                value="<?= e($type['id']) ?>"
                                                <?= selected(
                                                    $editData['internship_type_id'] ?? '',
                                                    $type['id']
                                                ) ?>
                                            >

                                                <?= e($type['type_name_th']) ?>

                                            </option>

                                        <?php endforeach; ?>


                                    </select>

                                </div>


                                <div class="col-12">

                                    <label class="form-label">
                                        วิธีสมัคร
                                    </label>

                                    <input
                                        type="text"
                                        name="application_method"
                                        class="form-control"
                                        value="<?= e($editData['application_method'] ?? '') ?>"
                                    >

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label">
                                        วันเปิดรับสมัคร
                                    </label>

                                    <input
                                        type="date"
                                        name="start_date"
                                        class="form-control"
                                        value="<?= e($editData['start_date'] ?? '') ?>"
                                    >

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label">
                                        วันปิดรับสมัคร
                                    </label>

                                    <input
                                        type="date"
                                        name="end_date"
                                        class="form-control"
                                        value="<?= e($editData['end_date'] ?? '') ?>"
                                    >

                                </div>


                                <div class="col-12">

                                    <label class="form-label">
                                        หมายเหตุ
                                    </label>

                                    <textarea
                                        name="note"
                                        class="form-control"
                                        rows="3"
                                    ><?= e($editData['note'] ?? '') ?></textarea>

                                </div>


                            </div>


                        <?php endif; ?>



                        <!-- =====================================
                             FORM ACTION
                        ====================================== -->

                        <div class="mt-4 d-flex flex-wrap gap-2">


                            <button
                                type="submit"
                                class="btn btn-primary"
                            >

                                <i class="bi bi-check2-circle"></i>

                                <?= $editData
                                    ? 'บันทึกการแก้ไข'
                                    : 'เพิ่มข้อมูล' ?>

                            </button>


                            <?php if ($editData): ?>

                                <a
                                    href="?section=<?= e($section) ?>"
                                    class="btn btn-secondary"
                                >

                                    <i class="bi bi-x-lg"></i>

                                    ยกเลิก

                                </a>

                            <?php endif; ?>


                        </div>


                    </form>


                </div>


            </section>



            <!-- =================================================
                 DATA CARD
            ================================================== -->

            <section class="internship-card">


                <div class="internship-card-header">


                    <div class="internship-card-title">


                        <span class="section-icon">

                            <i class="bi bi-database"></i>

                        </span>


                        <span>

                            <strong>
                                รายการข้อมูล
                            </strong>

                            <small>
                                ข้อมูลทั้งหมดในหมวดที่เลือก
                            </small>

                        </span>


                    </div>


                    <span class="record-count">

                        <?= count($rows) ?> รายการ

                    </span>


                </div>



                <div class="internship-table-wrap">



                    <!-- =========================================
                         TYPES TABLE
                    ========================================== -->

                    <?php if ($section === 'types'): ?>


                        <table class="table internship-table">


                            <thead>

                                <tr>

                                    <th>ID</th>

                                    <th>Code</th>

                                    <th>ประเภท</th>

                                    <th>ภาษาอังกฤษ</th>

                                    <th>ระยะเวลา</th>

                                    <th>ชั้นปี</th>

                                    <th>ภาคเรียน</th>

                                    <th>จัดการ</th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php foreach ($rows as $row): ?>


                                <tr>


                                    <td>
                                        <?= e($row['id']) ?>
                                    </td>


                                    <td>
                                        <?= e($row['type_code']) ?>
                                    </td>


                                    <td>
                                        <?= e($row['type_name_th']) ?>
                                    </td>


                                    <td>
                                        <?= e($row['type_name_en']) ?>
                                    </td>


                                    <td>

                                        <?= e($row['duration_months']) ?>

                                        เดือน

                                    </td>


                                    <td>
                                        <?= e($row['study_year']) ?>
                                    </td>


                                    <td>
                                        <?= e($row['study_term']) ?>
                                    </td>


                                    <td>


                                        <a
                                            href="?section=types&edit=<?= e($row['id']) ?>"
                                            class="btn btn-warning btn-sm"
                                        >

                                            <i class="bi bi-pencil"></i>

                                            แก้ไข

                                        </a>


                                        <a
                                            href="delete.php?section=types&id=<?= e($row['id']) ?>"
                                            class="btn btn-danger btn-sm"
                                            onclick="return confirm('ยืนยันการลบข้อมูลนี้?')"
                                        >

                                            <i class="bi bi-trash3"></i>

                                            ลบ

                                        </a>


                                    </td>


                                </tr>


                            <?php endforeach; ?>


                            </tbody>


                        </table>



                    <!-- =========================================
                         PERIODS TABLE
                    ========================================== -->

                    <?php elseif ($section === 'periods'): ?>


                        <table class="table internship-table">


                            <thead>

                                <tr>

                                    <th>ปีการศึกษา</th>

                                    <th>ภาคเรียน</th>

                                    <th>ประเภท</th>

                                    <th>เริ่ม</th>

                                    <th>สิ้นสุด</th>

                                    <th>หมายเหตุ</th>

                                    <th>จัดการ</th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php foreach ($rows as $row): ?>


                                <tr>


                                    <td>
                                        <?= e($row['academic_year']) ?>
                                    </td>


                                    <td>
                                        <?= e($row['semester']) ?>
                                    </td>


                                    <td>
                                        <?= e($row['type_name_th'] ?? 'ไม่ระบุ') ?>
                                    </td>


                                    <td>
                                        <?= e($row['start_date']) ?>
                                    </td>


                                    <td>
                                        <?= e($row['end_date']) ?>
                                    </td>


                                    <td>
                                        <?= e($row['note']) ?>
                                    </td>


                                    <td>


                                        <a
                                            href="?section=periods&edit=<?= e($row['id']) ?>"
                                            class="btn btn-warning btn-sm"
                                        >

                                            <i class="bi bi-pencil"></i>

                                            แก้ไข

                                        </a>


                                        <a
                                            href="delete.php?section=periods&id=<?= e($row['id']) ?>"
                                            class="btn btn-danger btn-sm"
                                            onclick="return confirm('ยืนยันการลบข้อมูลนี้?')"
                                        >

                                            <i class="bi bi-trash3"></i>

                                            ลบ

                                        </a>


                                    </td>


                                </tr>


                            <?php endforeach; ?>


                            </tbody>


                        </table>



                    <!-- =========================================
                         STEPS TABLE
                    ========================================== -->

                    <?php elseif ($section === 'steps'): ?>


                        <table class="table internship-table">


                            <thead>

                                <tr>

                                    <th>ลำดับ</th>

                                    <th>ประเภท</th>

                                    <th>ขั้นตอน</th>

                                    <th>รายละเอียด</th>

                                    <th>จัดการ</th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php foreach ($rows as $row): ?>


                                <tr>


                                    <td>
                                        <?= e($row['step_order']) ?>
                                    </td>


                                    <td>
                                        <?= e($row['type_name_th'] ?? 'ทุกประเภท') ?>
                                    </td>


                                    <td>
                                        <?= e($row['title']) ?>
                                    </td>


                                    <td>
                                        <?= e($row['description']) ?>
                                    </td>


                                    <td>


                                        <a
                                            href="?section=steps&edit=<?= e($row['id']) ?>"
                                            class="btn btn-warning btn-sm"
                                        >

                                            <i class="bi bi-pencil"></i>

                                            แก้ไข

                                        </a>


                                        <a
                                            href="delete.php?section=steps&id=<?= e($row['id']) ?>"
                                            class="btn btn-danger btn-sm"
                                            onclick="return confirm('ยืนยันการลบข้อมูลนี้?')"
                                        >

                                            <i class="bi bi-trash3"></i>

                                            ลบ

                                        </a>


                                    </td>


                                </tr>


                            <?php endforeach; ?>


                            </tbody>


                        </table>



                    <!-- =========================================
                         DOCUMENTS TABLE
                    ========================================== -->

                    <?php elseif ($section === 'documents'): ?>


                        <table class="table internship-table">


                            <thead>

                                <tr>

                                    <th>ลำดับ</th>

                                    <th>ชื่อเอกสาร</th>

                                    <th>หมายเหตุ</th>

                                    <th>จัดการ</th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php foreach ($rows as $row): ?>


                                <tr>


                                    <td>
                                        <?= e($row['display_order']) ?>
                                    </td>


                                    <td>
                                        <?= e($row['document_name']) ?>
                                    </td>


                                    <td>
                                        <?= e($row['note']) ?>
                                    </td>


                                    <td>


                                        <a
                                            href="?section=documents&edit=<?= e($row['id']) ?>"
                                            class="btn btn-warning btn-sm"
                                        >

                                            <i class="bi bi-pencil"></i>

                                            แก้ไข

                                        </a>


                                        <a
                                            href="delete.php?section=documents&id=<?= e($row['id']) ?>"
                                            class="btn btn-danger btn-sm"
                                            onclick="return confirm('ยืนยันการลบข้อมูลนี้?')"
                                        >

                                            <i class="bi bi-trash3"></i>

                                            ลบ

                                        </a>


                                    </td>


                                </tr>


                            <?php endforeach; ?>


                            </tbody>


                        </table>



                    <!-- =========================================
                         APPLICATION TABLE
                    ========================================== -->

                    <?php elseif ($section === 'applications'): ?>


                        <table class="table internship-table">


                            <thead>

                                <tr>

                                    <th>ปี</th>

                                    <th>ภาคเรียน</th>

                                    <th>ประเภท</th>

                                    <th>วิธีสมัคร</th>

                                    <th>เปิดรับ</th>

                                    <th>ปิดรับ</th>

                                    <th>จัดการ</th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php foreach ($rows as $row): ?>


                                <tr>


                                    <td>
                                        <?= e($row['academic_year']) ?>
                                    </td>


                                    <td>
                                        <?= e($row['semester']) ?>
                                    </td>


                                    <td>
                                        <?= e($row['type_name_th'] ?? 'ไม่ระบุ') ?>
                                    </td>


                                    <td>
                                        <?= e($row['application_method']) ?>
                                    </td>


                                    <td>
                                        <?= e($row['start_date']) ?>
                                    </td>


                                    <td>
                                        <?= e($row['end_date']) ?>
                                    </td>


                                    <td>


                                        <a
                                            href="?section=applications&edit=<?= e($row['id']) ?>"
                                            class="btn btn-warning btn-sm"
                                        >

                                            <i class="bi bi-pencil"></i>

                                            แก้ไข

                                        </a>


                                        <a
                                            href="delete.php?section=applications&id=<?= e($row['id']) ?>"
                                            class="btn btn-danger btn-sm"
                                            onclick="return confirm('ยืนยันการลบข้อมูลนี้?')"
                                        >

                                            <i class="bi bi-trash3"></i>

                                            ลบ

                                        </a>


                                    </td>


                                </tr>


                            <?php endforeach; ?>


                            </tbody>


                        </table>


                    <?php endif; ?>


                </div>


            </section>



            <!-- =================================================
                 FOOTER
            ================================================== -->

            <footer class="internship-footer">


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
                    Internship & Cooperative Education Management
                </span>


            </footer>


        </main>


    </div>


</div>



<!-- =========================================================
     BOOTSTRAP
========================================================= -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js">
</script>



<!-- =========================================================
     SIDEBAR
========================================================= -->

<script>

document.addEventListener(
    "DOMContentLoaded",
    function () {


        const sidebar =
            document.getElementById("sidebar");


        const overlay =
            document.getElementById("sidebarOverlay");


        const mobileMenuBtn =
            document.getElementById("mobileMenuBtn");


        const sidebarToggle =
            document.getElementById("sidebarToggle");



        /* ==============================================
           RESTORE SIDEBAR STATE
        ============================================== */

        if (
            localStorage.getItem(
                "mbsSidebarCollapsed"
            ) === "1" &&
            window.innerWidth >= 992
        ) {

            document.body.classList.add(
                "sidebar-collapsed"
            );

        }



        /* ==============================================
           DESKTOP COLLAPSE
        ============================================== */

        if (sidebarToggle) {

            sidebarToggle.addEventListener(
                "click",
                function () {


                    document.body.classList.toggle(
                        "sidebar-collapsed"
                    );


                    const collapsed =
                        document.body.classList.contains(
                            "sidebar-collapsed"
                        );


                    localStorage.setItem(
                        "mbsSidebarCollapsed",
                        collapsed ? "1" : "0"
                    );


                }
            );

        }



        /* ==============================================
           MOBILE OPEN
        ============================================== */

        if (
            mobileMenuBtn &&
            sidebar &&
            overlay
        ) {

            mobileMenuBtn.addEventListener(
                "click",
                function () {

                    sidebar.classList.add(
                        "show"
                    );

                    overlay.classList.add(
                        "show"
                    );

                }
            );

        }



        /* ==============================================
           MOBILE CLOSE
        ============================================== */

        if (
            overlay &&
            sidebar
        ) {

            overlay.addEventListener(
                "click",
                function () {

                    sidebar.classList.remove(
                        "show"
                    );

                    overlay.classList.remove(
                        "show"
                    );

                }
            );

        }



        /* ==============================================
           RESIZE
        ============================================== */

        window.addEventListener(
            "resize",
            function () {

                if (
                    window.innerWidth >= 992 &&
                    sidebar &&
                    overlay
                ) {

                    sidebar.classList.remove(
                        "show"
                    );

                    overlay.classList.remove(
                        "show"
                    );

                }

            }
        );


    }
);

</script>


</body>

</html>