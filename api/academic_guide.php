<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once "db.php";


// =========================================================
// Helper: ส่ง JSON แล้วจบ
// =========================================================

function sendJson(
    array $data,
    int $statusCode = 200
): void {

    http_response_code($statusCode);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


// =========================================================
// รับ Parameter
// =========================================================

$topic = trim($_GET['topic'] ?? '');

$academicYear = trim(
    $_GET['academic_year'] ?? ''
);

$semester = trim(
    $_GET['semester'] ?? ''
);

$yearLevel = trim(
    $_GET['year_level'] ?? ''
);

$studentCode = trim(
    $_GET['student_code'] ?? ''
);


// =========================================================
// topic ที่ระบบรองรับ
// =========================================================

$allowedTopics = [
    'resignation',
    'major_transfer',
    'seat_reservation',
    'late_registration',
    'exam_conflict',
    'study_plan_check',
    'registration_restore'
];


try {

    // =====================================================
    // 1. ไม่ส่ง topic
    //
    // ใช้สำหรับ:
    // "คู่มือการเรียน"
    // "คู่มือการเรียนมีอะไรบ้าง"
    //
    // คืนเฉพาะรายการหัวข้อ
    // =====================================================

    if ($topic === '') {

        $stmt = $pdo->prepare("
            SELECT
                id,
                topic_code,
                title,
                summary,
                sort_order
            FROM academic_guides
            WHERE active = 1
            ORDER BY
                sort_order ASC,
                id ASC
        ");

        $stmt->execute();

        $guides = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


        sendJson([
            "success" => true,
            "mode" => "list",
            "count" => count($guides),
            "data" => $guides
        ]);
    }


    // =====================================================
    // 2. ตรวจสอบ topic
    // =====================================================

    if (
        !in_array(
            $topic,
            $allowedTopics,
            true
        )
    ) {

        sendJson([
            "success" => false,
            "message" =>
                "ไม่พบหัวข้อคู่มือที่ระบุ",
            "topic" => $topic
        ], 400);
    }


    // =====================================================
    // 3. ดึงข้อมูลหัวข้อหลัก
    // =====================================================

    $stmt = $pdo->prepare("
        SELECT
            id,
            topic_code,
            title,
            summary,
            keywords,
            sort_order
        FROM academic_guides
        WHERE
            topic_code = :topic
            AND active = 1
        LIMIT 1
    ");

    $stmt->execute([
        ':topic' => $topic
    ]);

    $guide = $stmt->fetch(
        PDO::FETCH_ASSOC
    );


    // =====================================================
    // ไม่พบหัวข้อ
    // =====================================================

    if (!$guide) {

        sendJson([
            "success" => true,
            "mode" => "detail",
            "topic" => $topic,
            "count" => 0,
            "data" => null,
            "message" =>
                "ไม่พบข้อมูลคู่มือที่ตรงกับหัวข้อ"
        ]);
    }


    $guideId = (int)$guide['id'];


    // =====================================================
    // 4. ดึงขั้นตอน
    // =====================================================

    $stmt = $pdo->prepare("
        SELECT
            id,
            step_order,
            step_title,
            description,
            url
        FROM academic_guide_steps
        WHERE guide_id = :guide_id
        ORDER BY
            step_order ASC,
            id ASC
    ");

    $stmt->execute([
        ':guide_id' => $guideId
    ]);

    $steps = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


    // =====================================================
    // 5. ดึงเอกสาร
    // =====================================================

    $stmt = $pdo->prepare("
        SELECT
            id,
            document_name,
            document_url,
            note,
            sort_order
        FROM academic_guide_documents
        WHERE guide_id = :guide_id
        ORDER BY
            sort_order ASC,
            id ASC
    ");

    $stmt->execute([
        ':guide_id' => $guideId
    ]);

    $documents = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


    // =====================================================
    // 6. ดึงช่วงเวลา / กำหนดการ
    // =====================================================

    $sql = "
        SELECT
            id,
            academic_year,
            semester,
            year_level,
            student_code,
            period_name,
            start_date,
            end_date,
            date_text,
            note,
            sort_order
        FROM academic_guide_periods
        WHERE guide_id = :guide_id
    ";

    $params = [
        ':guide_id' => $guideId
    ];

    $conditions = [];


    // -----------------------------------------------------
    // ปีการศึกษา
    // -----------------------------------------------------

    if ($academicYear !== '') {

        $conditions[] = "
            academic_year = :academic_year
        ";

        $params[':academic_year'] =
            $academicYear;
    }


    // -----------------------------------------------------
    // ภาคการศึกษา
    // -----------------------------------------------------

    if ($semester !== '') {

        $conditions[] = "
            semester = :semester
        ";

        $params[':semester'] =
            $semester;
    }


    // -----------------------------------------------------
    // ชั้นปี
    // -----------------------------------------------------

    if ($yearLevel !== '') {

        $conditions[] = "
            year_level = :year_level
        ";

        $params[':year_level'] =
            $yearLevel;
    }


    // -----------------------------------------------------
    // รหัสนิสิต
    // -----------------------------------------------------

    if ($studentCode !== '') {

        $conditions[] = "
            student_code = :student_code
        ";

        $params[':student_code'] =
            $studentCode;
    }


    if (!empty($conditions)) {

        $sql .= "
            AND " .
            implode(
                " AND ",
                $conditions
            );
    }


    $sql .= "
        ORDER BY
            sort_order ASC,
            start_date ASC,
            id ASC
    ";


    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $periods = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


    // =====================================================
    // 7. รวมข้อมูล
    // =====================================================

    $guide['steps'] = $steps;

    $guide['documents'] =
        $documents;

    $guide['periods'] =
        $periods;


    // =====================================================
    // 8. RESPONSE
    // =====================================================

    sendJson([
        "success" => true,
        "mode" => "detail",
        "topic" => $topic,
        "count" => 1,
        "data" => $guide
    ]);


} catch (PDOException $e) {

    sendJson([
        "success" => false,
        "message" =>
            "เกิดข้อผิดพลาดในการดึงข้อมูลคู่มือการเรียน",
        "error" => $e->getMessage()
    ], 500);
}