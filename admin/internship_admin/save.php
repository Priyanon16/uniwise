<?php

require_once "../../api/db.php";
require_once "../admin_activity.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$section = $_POST['section'] ?? '';
$id = isset($_POST['id']) && $_POST['id'] !== ''
    ? (int)$_POST['id']
    : 0;

function nullable($value)
{
    return isset($value) && $value !== '' ? $value : null;
}

function internshipActivityLabel(string $section): string
{
    return match ($section) {
        'types' => trim((string)($_POST['type_name_th'] ?? '')) ?: 'ประเภทการฝึกงาน',
        'periods' => 'ช่วงปฏิบัติงาน ปีการศึกษา ' .
            trim((string)($_POST['academic_year'] ?? '')) .
            ' ภาคเรียน ' . trim((string)($_POST['semester'] ?? '')),
        'steps' => trim((string)($_POST['title'] ?? '')) ?: 'ขั้นตอนการฝึกงาน',
        'documents' => trim((string)($_POST['document_name'] ?? '')) ?: 'เอกสารฝึกงาน',
        'applications' => 'ช่วงรับสมัคร ปีการศึกษา ' .
            trim((string)($_POST['academic_year'] ?? '')) .
            ' ภาคเรียน ' . trim((string)($_POST['semester'] ?? '')),
        default => 'ข้อมูลฝึกงานและสหกิจศึกษา'
    };
}

function internshipSectionLabel(string $section): string
{
    return match ($section) {
        'types' => 'ประเภทการฝึกงาน',
        'periods' => 'ช่วงปฏิบัติงาน',
        'steps' => 'ขั้นตอนการฝึกงาน',
        'documents' => 'เอกสารฝึกงาน',
        'applications' => 'ช่วงรับสมัคร',
        default => 'ข้อมูลฝึกงานและสหกิจศึกษา'
    };
}

/* ========================
   TYPES
======================== */

if ($section === 'types') {

    $data = [
        $_POST['type_code'],
        $_POST['type_name_th'],
        nullable($_POST['type_name_en']),
        nullable($_POST['duration_months']),
        nullable($_POST['study_year']),
        nullable($_POST['study_term']),
        nullable($_POST['description'])
    ];

    if ($id > 0) {

        $sql = "
            UPDATE internship_types
            SET type_code = ?,
                type_name_th = ?,
                type_name_en = ?,
                duration_months = ?,
                study_year = ?,
                study_term = ?,
                description = ?
            WHERE id = ?
        ";

        $data[] = $id;

    } else {

        $sql = "
            INSERT INTO internship_types
            (
                type_code,
                type_name_th,
                type_name_en,
                duration_months,
                study_year,
                study_term,
                description
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
}

/* ========================
   PERIODS
======================== */

elseif ($section === 'periods') {

    $data = [
        $_POST['academic_year'],
        $_POST['semester'],
        nullable($_POST['internship_type_id']),
        $_POST['start_date'],
        $_POST['end_date'],
        nullable($_POST['note'])
    ];

    if ($id > 0) {

        $sql = "
            UPDATE internship_periods
            SET academic_year = ?,
                semester = ?,
                internship_type_id = ?,
                start_date = ?,
                end_date = ?,
                note = ?
            WHERE id = ?
        ";

        $data[] = $id;

    } else {

        $sql = "
            INSERT INTO internship_periods
            (
                academic_year,
                semester,
                internship_type_id,
                start_date,
                end_date,
                note
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
}

/* ========================
   STEPS
======================== */

elseif ($section === 'steps') {

    $data = [
        nullable($_POST['internship_type_id']),
        $_POST['step_order'],
        $_POST['title'],
        $_POST['description']
    ];

    if ($id > 0) {

        $sql = "
            UPDATE internship_steps
            SET internship_type_id = ?,
                step_order = ?,
                title = ?,
                description = ?
            WHERE id = ?
        ";

        $data[] = $id;

    } else {

        $sql = "
            INSERT INTO internship_steps
            (
                internship_type_id,
                step_order,
                title,
                description
            )
            VALUES (?, ?, ?, ?)
        ";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
}

/* ========================
   DOCUMENTS
======================== */

elseif ($section === 'documents') {

    $data = [
        $_POST['document_name'],
        nullable($_POST['note']),
        nullable($_POST['display_order'])
    ];

    if ($id > 0) {

        $sql = "
            UPDATE internship_document_requirements
            SET document_name = ?,
                note = ?,
                display_order = ?
            WHERE id = ?
        ";

        $data[] = $id;

    } else {

        $sql = "
            INSERT INTO internship_document_requirements
            (
                document_name,
                note,
                display_order
            )
            VALUES (?, ?, ?)
        ";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
}

/* ========================
   APPLICATION PERIODS
======================== */

elseif ($section === 'applications') {

    $data = [
        $_POST['academic_year'],
        $_POST['semester'],
        nullable($_POST['internship_type_id']),
        nullable($_POST['application_method']),
        nullable($_POST['start_date']),
        nullable($_POST['end_date']),
        nullable($_POST['note'])
    ];

    if ($id > 0) {

        $sql = "
            UPDATE application_periods
            SET academic_year = ?,
                semester = ?,
                internship_type_id = ?,
                application_method = ?,
                start_date = ?,
                end_date = ?,
                note = ?
            WHERE id = ?
        ";

        $data[] = $id;

    } else {

        $sql = "
            INSERT INTO application_periods
            (
                academic_year,
                semester,
                internship_type_id,
                application_method,
                start_date,
                end_date,
                note
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
}

else {
    die('ไม่พบหมวดข้อมูล');
}


// บันทึกกิจกรรมล่าสุดหลังจากบันทึกข้อมูลหลักสำเร็จ
logAdminActivity(
    $pdo,
    'internship',
    $id > 0 ? 'update' : 'create',
    internshipActivityLabel($section),
    ($id > 0 ? 'แก้ไข' : 'เพิ่ม') .
    internshipSectionLabel($section)
);


header(
    'Location: index.php?section=' .
    urlencode($section) .
    '&success=1'
);

exit;