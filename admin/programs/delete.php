<?php

require_once "../../api/db.php";
require_once "../admin_activity.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);
    exit("Method Not Allowed");
}


$id = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id) {

    header(
        "Location: index.php?error=" .
        urlencode("ID หลักสูตรไม่ถูกต้อง")
    );

    exit;
}


try {

    $pdo->beginTransaction();


    // ---------------------------------
    // อ่านชื่อหลักสูตรก่อนลบ เพื่อนำไปบันทึก Log
    // ---------------------------------

    $stmt = $pdo->prepare("
        SELECT
            program_code,
            curriculum_name,
            major_name
        FROM academic_programs
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $id
    ]);

    $program = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$program) {
        throw new Exception("ไม่พบหลักสูตรที่ต้องการลบ");
    }

    $itemLabel = trim((string)($program['major_name'] ?? ''));

    if ($itemLabel === '') {
        $itemLabel = trim((string)($program['curriculum_name'] ?? ''));
    }

    if ($itemLabel === '') {
        $itemLabel = 'หลักสูตร ID ' . $id;
    }


    // ---------------------------------
    // ลบข้อมูลลูก
    // ---------------------------------

    $tables = [
        'program_admission_requirements',
        'program_careers',
        'program_objectives',
        'program_plans'
    ];


    foreach ($tables as $table) {

        $stmt = $pdo->prepare("
            DELETE FROM {$table}
            WHERE program_id = :program_id
        ");

        $stmt->execute([
            ':program_id' => $id
        ]);
    }


    // ---------------------------------
    // ลบหลักสูตร
    // ---------------------------------

    $stmt = $pdo->prepare("
        DELETE FROM academic_programs
        WHERE id = :id
    ");

    $stmt->execute([
        ':id' => $id
    ]);


    if ($stmt->rowCount() === 0) {

        throw new Exception(
            "ไม่พบหลักสูตรที่ต้องการลบ"
        );
    }


    // ---------------------------------
    // บันทึกกิจกรรมล่าสุด
    // ---------------------------------

    logAdminActivity(
        $pdo,
        'programs',
        'delete',
        $itemLabel,
        'ลบข้อมูลหลักสูตร ' .
        trim((string)($program['curriculum_name'] ?? '')) .
        (!empty($program['program_code'])
            ? ' (' . $program['program_code'] . ')'
            : '')
    );


    $pdo->commit();


    header(
        "Location: index.php?success=delete"
    );

    exit;


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }


    header(
        "Location: index.php?error=" .
        urlencode(
            "ไม่สามารถลบข้อมูลได้: " .
            $e->getMessage()
        )
    );

    exit;
}