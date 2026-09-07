<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once "db.php";


function sendJson(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_PRETTY_PRINT
    );

    exit;
}


$type = trim($_GET['type'] ?? '');


$allowedTypes = [
    'phone',
    'email',
    'website',
    'facebook',
    'office_hours'
];


try {

    // =========================================================
    // ไม่ส่ง type = แสดงข้อมูลติดต่อทั้งหมด
    // =========================================================

    if ($type === '') {

        $stmt = $pdo->prepare("
            SELECT
                id,
                contact_type,
                contact_label,
                contact_value,
                description,
                sort_order
            FROM faculty_contacts
            WHERE active = 1
            ORDER BY sort_order ASC, id ASC
        ");

        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendJson([
            "success" => true,
            "mode" => "all",
            "count" => count($data),
            "data" => $data
        ]);
    }


    // =========================================================
    // ตรวจ type
    // =========================================================

    if (!in_array($type, $allowedTypes, true)) {

        sendJson([
            "success" => false,
            "count" => 0,
            "data" => [],
            "message" => "ไม่พบประเภทข้อมูลติดต่อที่ระบุ"
        ], 400);
    }


    // =========================================================
    // ค้นหาเฉพาะประเภท
    // =========================================================

    $stmt = $pdo->prepare("
        SELECT
            id,
            contact_type,
            contact_label,
            contact_value,
            description,
            sort_order
        FROM faculty_contacts
        WHERE
            active = 1
            AND contact_type = :type
        ORDER BY sort_order ASC, id ASC
    ");

    $stmt->execute([
        ':type' => $type
    ]);

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);


    sendJson([
        "success" => true,
        "mode" => "specific",
        "type" => $type,
        "count" => count($data),
        "data" => $data
    ]);


} catch (PDOException $e) {

    sendJson([
        "success" => false,
        "message" => "เกิดข้อผิดพลาดในการดึงข้อมูลติดต่อ",
        "error" => $e->getMessage()
    ], 500);
}