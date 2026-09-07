<?php

require_once "../../api/db.php";
require_once "../admin_activity.php";

$section = $_GET['section'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die('ID ไม่ถูกต้อง');
}

$tables = [
    'types'        => 'internship_types',
    'periods'      => 'internship_periods',
    'steps'        => 'internship_steps',
    'documents'    => 'internship_document_requirements',
    'applications' => 'application_periods'
];

if (!isset($tables[$section])) {
    die('ไม่พบหมวดข้อมูล');
}

$table = $tables[$section];

$labelColumns = [
    'types'        => 'type_name_th',
    'periods'      => 'academic_year',
    'steps'        => 'title',
    'documents'    => 'document_name',
    'applications' => 'academic_year'
];

$sectionLabels = [
    'types'        => 'ประเภทการฝึกงาน',
    'periods'      => 'ช่วงปฏิบัติงาน',
    'steps'        => 'ขั้นตอนการฝึกงาน',
    'documents'    => 'เอกสารฝึกงาน',
    'applications' => 'ช่วงรับสมัคร'
];

try {

    // อ่านข้อมูลก่อนลบ เพื่อใช้แสดงใน Recent Activity
    $labelColumn = $labelColumns[$section];

    $stmt = $pdo->prepare("
        SELECT *
        FROM {$table}
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);
    $oldRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$oldRow) {
        die('ไม่พบข้อมูลที่ต้องการลบ');
    }

    if ($section === 'periods' || $section === 'applications') {
        $itemLabel =
            $sectionLabels[$section] .
            ' ปีการศึกษา ' .
            (string)($oldRow['academic_year'] ?? '') .
            ' ภาคเรียน ' .
            (string)($oldRow['semester'] ?? '');
    } else {
        $itemLabel = trim((string)($oldRow[$labelColumn] ?? ''));

        if ($itemLabel === '') {
            $itemLabel = $sectionLabels[$section] . ' ID ' . $id;
        }
    }

    $stmt = $pdo->prepare("
        DELETE FROM {$table}
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        die('ไม่พบข้อมูลที่ต้องการลบ');
    }

    logAdminActivity(
        $pdo,
        'internship',
        'delete',
        $itemLabel,
        'ลบ' . $sectionLabels[$section]
    );

    header(
        'Location: index.php?section=' .
        urlencode($section) .
        '&deleted=1'
    );

    exit;

} catch (PDOException $e) {

    die(
        'ไม่สามารถลบข้อมูลได้ อาจมีข้อมูลอื่นอ้างอิงรายการนี้อยู่'
    );
}