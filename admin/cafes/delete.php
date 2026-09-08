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
        urlencode("ID คาเฟ่ไม่ถูกต้อง")
    );

    exit;
}

try {

    $pdo->beginTransaction();


    $stmt = $pdo->prepare("
        SELECT
            cafe_name
        FROM msu_cafes
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $id
    ]);

    $cafe =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cafe) {

        throw new Exception(
            "ไม่พบข้อมูลคาเฟ่ที่ต้องการลบ"
        );
    }


    $stmt = $pdo->prepare("
        DELETE FROM msu_cafes
        WHERE id = :id
    ");

    $stmt->execute([
        ':id' => $id
    ]);


    if ($stmt->rowCount() === 0) {

        throw new Exception(
            "ไม่พบข้อมูลคาเฟ่ที่ต้องการลบ"
        );
    }


    logAdminActivity(
        $pdo,
        'cafes',
        'delete',
        $cafe['cafe_name'],
        'ลบข้อมูลคาเฟ่ ' .
        $cafe['cafe_name']
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
