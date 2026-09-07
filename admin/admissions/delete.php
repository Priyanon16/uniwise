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
        urlencode("ID กำหนดการไม่ถูกต้อง")
    );

    exit;
}


try {

    $pdo->beginTransaction();


    // -----------------------------------------------------
    // Load data before delete
    // -----------------------------------------------------

    $stmt = $pdo->prepare("
        SELECT
            academic_year,
            round_number,
            round_name,
            quota_type,
            activity,
            date_display
        FROM admission_schedules
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $id
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$row) {

        throw new Exception(
            "ไม่พบข้อมูลกำหนดการที่ต้องการลบ"
        );
    }


    $itemLabel =
        'รอบ ' .
        (int)$row['round_number'] .
        ' • ' .
        trim((string)$row['quota_type']) .
        ' • ' .
        trim((string)$row['activity']);


    // -----------------------------------------------------
    // Delete
    // -----------------------------------------------------

    $stmt = $pdo->prepare("
        DELETE FROM admission_schedules
        WHERE id = :id
    ");

    $stmt->execute([
        ':id' => $id
    ]);


    if ($stmt->rowCount() === 0) {

        throw new Exception(
            "ไม่พบข้อมูลกำหนดการที่ต้องการลบ"
        );
    }


    // -----------------------------------------------------
    // Activity log
    // -----------------------------------------------------

    logAdminActivity(
        $pdo,
        'admission',
        'delete',
        $itemLabel,
        'ลบกำหนดการรับสมัคร ปีการศึกษา ' .
        (int)$row['academic_year'] .
        ' (' .
        trim((string)$row['date_display']) .
        ')'
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
