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
        JSON_PRETTY_PRINT
    );

    exit;
}


// =========================================================
// รับ Query String
// =========================================================

$academicYear = trim($_GET['academic_year'] ?? '');

$roundNumber = trim($_GET['round_number'] ?? '');

$roundName = trim($_GET['round_name'] ?? '');

$quotaType = trim($_GET['quota_type'] ?? '');

$activityType = trim($_GET['activity_type'] ?? '');

$activity = trim($_GET['activity'] ?? '');

$search = trim($_GET['search'] ?? '');

$mode = strtolower(
    trim($_GET['mode'] ?? 'events')
);


// =========================================================
// ตรวจ mode
// =========================================================

$allowedModes = [
    'events',
    'rounds',
    'projects'
];

if (!in_array($mode, $allowedModes, true)) {

    sendJson(
        [
            "success" => false,
            "message" => "mode ต้องเป็น events, rounds หรือ projects"
        ],
        400
    );
}


// =========================================================
// ตรวจรอบ
// =========================================================

if (
    $roundNumber !== '' &&
    !in_array($roundNumber, ['1', '2', '3', '4'], true)
) {

    sendJson(
        [
            "success" => false,
            "message" => "round_number ต้องเป็น 1, 2, 3 หรือ 4"
        ],
        400
    );
}


// =========================================================
// activity_type ที่รองรับ
// =========================================================

$allowedActivityTypes = [
    'tcas_registration',
    'school_selection',
    'application',
    'payment_check',
    'payment_deadline',
    'score_check',
    'interview_eligible',
    'interview',
    'interview_result',
    'ability_test_eligible',
    'ability_test',
    'screening_confirm',
    'selection_result',
    'tcas_confirm',
    'waiver',
    'admission_eligible',
    'report',
    'other'
];

if (
    $activityType !== '' &&
    !in_array(
        $activityType,
        $allowedActivityTypes,
        true
    )
) {

    sendJson(
        [
            "success" => false,
            "message" => "activity_type ไม่ถูกต้อง",
            "allowed_activity_types" => $allowedActivityTypes
        ],
        400
    );
}


try {

    // =====================================================
    // ถ้าไม่ระบุปี
    // ใช้ปีการศึกษาล่าสุดที่มีในฐานข้อมูล
    // =====================================================

    if ($academicYear === '') {

        $stmtYear = $pdo->query("
            SELECT MAX(academic_year)
            FROM admission_schedules
        ");

        $academicYear = (string)$stmtYear->fetchColumn();
    }


    // =====================================================
    // MODE: ROUNDS
    //
    // ใช้ถาม:
    // ปี 2570 มีรอบรับสมัครอะไรบ้าง
    // =====================================================

    if ($mode === 'rounds') {

        $sql = "
            SELECT
                academic_year,
                round_number,
                round_name,
                COUNT(*) AS event_count,
                COUNT(DISTINCT quota_type) AS quota_count
            FROM admission_schedules
            WHERE academic_year = :academic_year
            GROUP BY
                academic_year,
                round_number,
                round_name
            ORDER BY round_number ASC
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':academic_year' => (int)$academicYear
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


        foreach ($rows as &$row) {

            $row['academic_year'] =
                (int)$row['academic_year'];

            $row['round_number'] =
                (int)$row['round_number'];

            $row['event_count'] =
                (int)$row['event_count'];

            $row['quota_count'] =
                (int)$row['quota_count'];
        }

        unset($row);


        sendJson([
            "success" => true,
            "mode" => "rounds",
            "academic_year" => (int)$academicYear,
            "count" => count($rows),
            "data" => $rows
        ]);
    }


    // =====================================================
    // MODE: PROJECTS
    //
    // ใช้ถาม:
    // รอบ 1 มีโครงการอะไรบ้าง
    // รอบ 2 มีโควตาอะไรบ้าง
    // =====================================================

    if ($mode === 'projects') {

        $sql = "
            SELECT DISTINCT
                academic_year,
                round_number,
                round_name,
                quota_type
            FROM admission_schedules
            WHERE academic_year = :academic_year
        ";

        $params = [
            ':academic_year' => (int)$academicYear
        ];


        if ($roundNumber !== '') {

            $sql .= "
                AND round_number = :round_number
            ";

            $params[':round_number'] =
                (int)$roundNumber;
        }


        /*
         * ไม่เอากลุ่มข้อมูลกลางของระบบ
         * มาแสดงเป็นชื่อโครงการ
         */
        $sql .= "
            AND quota_type <> 'ทุกโครงการ'
            AND quota_type NOT LIKE 'รอบที่ % ทั้งหมด'
        ";


        if ($search !== '') {

            $sql .= "
                AND (
                    round_name LIKE :search
                    OR quota_type LIKE :search
                )
            ";

            $params[':search'] =
                '%' . $search . '%';
        }


        $sql .= "
            ORDER BY
                round_number ASC,
                quota_type ASC
        ";


        $stmt = $pdo->prepare($sql);

        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


        foreach ($rows as &$row) {

            $row['academic_year'] =
                (int)$row['academic_year'];

            $row['round_number'] =
                (int)$row['round_number'];
        }

        unset($row);


        sendJson([
            "success" => true,
            "mode" => "projects",
            "academic_year" => (int)$academicYear,
            "round_number" =>
                $roundNumber !== ''
                ? (int)$roundNumber
                : null,
            "count" => count($rows),
            "data" => $rows
        ]);
    }


    // =====================================================
    // MODE: EVENTS
    //
    // ใช้ค้นกำหนดการจริง
    // =====================================================

    $sql = "
        SELECT
            id,
            academic_year,
            round_number,
            round_name,
            quota_type,
            activity,
            activity_type,
            start_date,
            end_date,
            date_display,
            channel_website,
            notes,
            sort_order
        FROM admission_schedules
        WHERE academic_year = :academic_year
    ";

    $params = [
        ':academic_year' => (int)$academicYear
    ];


    // =====================================================
    // กรองรอบ
    // =====================================================

    if ($roundNumber !== '') {

        $sql .= "
            AND round_number = :round_number
        ";

        $params[':round_number'] =
            (int)$roundNumber;
    }


    // =====================================================
    // กรองชื่อรอบ
    // =====================================================

    if ($roundName !== '') {

        $sql .= "
            AND round_name LIKE :round_name
        ";

        $params[':round_name'] =
            '%' . $roundName . '%';
    }


    // =====================================================
    // กรองโครงการ / โควตา
    // =====================================================

    if ($quotaType !== '') {

        $sql .= "
            AND quota_type LIKE :quota_type
        ";

        $params[':quota_type'] =
            '%' . $quotaType . '%';
    }


    // =====================================================
    // กรองประเภทกิจกรรม
    // =====================================================

    if ($activityType !== '') {

        $sql .= "
            AND activity_type = :activity_type
        ";

        $params[':activity_type'] =
            $activityType;
    }


    // =====================================================
    // กรองชื่อกิจกรรม
    // =====================================================

    if ($activity !== '') {

        $sql .= "
            AND activity LIKE :activity
        ";

        $params[':activity'] =
            '%' . $activity . '%';
    }


    // =====================================================
    // Search ทั่วไป
    // =====================================================

    if ($search !== '') {

        $sql .= "
            AND (
                round_name LIKE :search
                OR quota_type LIKE :search
                OR activity LIKE :search
                OR activity_type LIKE :search
                OR date_display LIKE :search
                OR channel_website LIKE :search
                OR notes LIKE :search
            )
        ";

        $params[':search'] =
            '%' . $search . '%';
    }


    // =====================================================
    // เรียงข้อมูล
    //
    // 1. รอบ
    // 2. โครงการ
    // 3. ขั้นตอน
    // 4. วันที่
    // =====================================================

    $sql .= "
        ORDER BY
            round_number ASC,

            CASE
                WHEN quota_type = 'ทุกโครงการ'
                    THEN 0

                WHEN quota_type LIKE 'รอบที่ % ทั้งหมด'
                    THEN 2

                ELSE 1
            END ASC,

            quota_type ASC,
            sort_order ASC,

            CASE
                WHEN start_date IS NULL
                    THEN 1
                ELSE 0
            END ASC,

            start_date ASC,
            id ASC
    ";


    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


    // =====================================================
    // แปลงชนิดข้อมูล
    // =====================================================

    foreach ($rows as &$row) {

        $row['id'] =
            (int)$row['id'];

        $row['academic_year'] =
            (int)$row['academic_year'];

        $row['round_number'] =
            (int)$row['round_number'];

        $row['sort_order'] =
            (int)$row['sort_order'];
    }

    unset($row);


    // =====================================================
    // RESPONSE
    // =====================================================

    sendJson([
        "success" => true,

        "mode" => "events",

        "filters" => [
            "academic_year" =>
                (int)$academicYear,

            "round_number" =>
                $roundNumber !== ''
                ? (int)$roundNumber
                : null,

            "round_name" =>
                $roundName !== ''
                ? $roundName
                : null,

            "quota_type" =>
                $quotaType !== ''
                ? $quotaType
                : null,

            "activity_type" =>
                $activityType !== ''
                ? $activityType
                : null,

            "activity" =>
                $activity !== ''
                ? $activity
                : null,

            "search" =>
                $search !== ''
                ? $search
                : null
        ],

        "count" => count($rows),

        "data" => $rows
    ]);


} catch (PDOException $e) {

    sendJson(
        [
            "success" => false,
            "message" => "เกิดข้อผิดพลาดในการดึงข้อมูลรับสมัคร"
        ],
        500
    );
}