<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once "db.php";

try {

    // =====================================================
    // รับ Query String
    // =====================================================

    $section = trim($_GET['section'] ?? '');
    $type = trim($_GET['type'] ?? '');

    $academicYear = trim($_GET['academic_year'] ?? '');
    $semester = trim($_GET['semester'] ?? '');
    $documentName = trim($_GET['document_name'] ?? '');

    // =====================================================
    // ตรวจสอบ type
    // =====================================================

    $allowedTypes = [
        'internship',
        'coop'
    ];

    if (
        $type !== ''
        && !in_array($type, $allowedTypes, true)
    ) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" =>
                "type ต้องเป็น internship หรือ coop"
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        exit;
    }


    // =====================================================
    // SECTION ว่าง
    //
    // หมายถึงผู้ใช้ถามภาพรวม เช่น
    // "ฝึกงานและสหกิจศึกษา"
    //
    // ให้ดึงข้อมูลทุกหมวด
    // =====================================================

    if ($section === '') {

        $result = [];


        // -------------------------------------------------
        // 1. TYPES
        // -------------------------------------------------

        $sql = "
            SELECT *
            FROM internship_types
        ";

        $params = [];

        if ($type !== '') {

            $sql .= "
                WHERE type_code = :type
            ";

            $params[':type'] = $type;
        }

        $sql .= "
            ORDER BY id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $result['types'] =
            $stmt->fetchAll(PDO::FETCH_ASSOC);


        // -------------------------------------------------
        // 2. PERIODS
        // -------------------------------------------------

        $sql = "
            SELECT
                p.*,
                t.type_code,
                t.type_name_th,
                t.type_name_en

            FROM internship_periods p

            LEFT JOIN internship_types t
                ON p.internship_type_id = t.id
        ";

        $conditions = [];
        $params = [];

        if ($type !== '') {

            $conditions[] =
                "t.type_code = :type";

            $params[':type'] = $type;
        }

        if ($academicYear !== '') {

            $conditions[] =
                "p.academic_year = :academic_year";

            $params[':academic_year'] =
                $academicYear;
        }

        if ($semester !== '') {

            $conditions[] =
                "p.semester = :semester";

            $params[':semester'] =
                $semester;
        }

        if (!empty($conditions)) {

            $sql .= "
                WHERE " .
                implode(" AND ", $conditions);
        }

        $sql .= "
            ORDER BY p.start_date ASC, p.id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $result['periods'] =
            $stmt->fetchAll(PDO::FETCH_ASSOC);


        // -------------------------------------------------
        // 3. STEPS
        // -------------------------------------------------

        $sql = "
            SELECT
                s.*,
                t.type_code,
                t.type_name_th,
                t.type_name_en

            FROM internship_steps s

            LEFT JOIN internship_types t
                ON s.internship_type_id = t.id
        ";

        $conditions = [];
        $params = [];

        if ($type !== '') {

            // แสดงทั้งขั้นตอนเฉพาะประเภท
            // และขั้นตอนกลางที่ใช้ร่วมกัน
            $conditions[] = "
                (
                    t.type_code = :type
                    OR s.internship_type_id IS NULL
                )
            ";

            $params[':type'] = $type;
        }

        if (!empty($conditions)) {

            $sql .= "
                WHERE " .
                implode(" AND ", $conditions);
        }

        $sql .= "
            ORDER BY
                s.step_order ASC,
                s.id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $result['steps'] =
            $stmt->fetchAll(PDO::FETCH_ASSOC);


        // -------------------------------------------------
        // 4. DOCUMENTS
        // -------------------------------------------------

        $sql = "
            SELECT *
            FROM internship_document_requirements
        ";

        $conditions = [];
        $params = [];

        if ($documentName !== '') {

            $conditions[] =
                "document_name LIKE :document_name";

            $params[':document_name'] =
                '%' . $documentName . '%';
        }

        if (!empty($conditions)) {

            $sql .= "
                WHERE " .
                implode(" AND ", $conditions);
        }

        $sql .= "
            ORDER BY
                display_order ASC,
                id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $result['documents'] =
            $stmt->fetchAll(PDO::FETCH_ASSOC);


        // -------------------------------------------------
        // 5. APPLICATION PERIODS
        // -------------------------------------------------

        $sql = "
            SELECT
                a.*,
                t.type_code,
                t.type_name_th,
                t.type_name_en

            FROM application_periods a

            LEFT JOIN internship_types t
                ON a.internship_type_id = t.id
        ";

        $conditions = [];
        $params = [];

        if ($type !== '') {

            $conditions[] =
                "t.type_code = :type";

            $params[':type'] = $type;
        }

        if ($academicYear !== '') {

            $conditions[] =
                "a.academic_year = :academic_year";

            $params[':academic_year'] =
                $academicYear;
        }

        if ($semester !== '') {

            $conditions[] =
                "a.semester = :semester";

            $params[':semester'] =
                $semester;
        }

        if (!empty($conditions)) {

            $sql .= "
                WHERE " .
                implode(" AND ", $conditions);
        }

        $sql .= "
            ORDER BY
                a.start_date ASC,
                a.id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $result['application_periods'] =
            $stmt->fetchAll(PDO::FETCH_ASSOC);


        // -------------------------------------------------
        // RESPONSE ภาพรวม
        // -------------------------------------------------

        echo json_encode([
            "success" => true,
            "section" => "all",
            "type" => $type !== ''
                ? $type
                : null,

            "count" => [
                "types" =>
                    count($result['types']),

                "periods" =>
                    count($result['periods']),

                "steps" =>
                    count($result['steps']),

                "documents" =>
                    count($result['documents']),

                "application_periods" =>
                    count(
                        $result[
                            'application_periods'
                        ]
                    )
            ],

            "data" => $result

        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        exit;
    }


    // =====================================================
    // ถ้ามี SECTION
    // ทำงานแบบเดิมสำหรับคำถามเฉพาะ
    // =====================================================

    $params = [];

    switch ($section) {


        // =================================================
        // TYPES
        // =================================================

        case 'types':

            $sql = "
                SELECT *
                FROM internship_types
            ";

            if ($type !== '') {

                $sql .= "
                    WHERE type_code = :type
                ";

                $params[':type'] = $type;
            }

            $sql .= "
                ORDER BY id ASC
            ";

            break;


        // =================================================
        // DOCUMENTS
        // =================================================

        case 'documents':

            $sql = "
                SELECT *
                FROM internship_document_requirements
            ";

            if ($documentName !== '') {

                $sql .= "
                    WHERE document_name
                    LIKE :document_name
                ";

                $params[':document_name'] =
                    '%' . $documentName . '%';
            }

            $sql .= "
                ORDER BY
                    display_order ASC,
                    id ASC
            ";

            break;


        // =================================================
        // PERIODS
        // =================================================

        case 'periods':

            $sql = "
                SELECT
                    p.*,
                    t.type_code,
                    t.type_name_th,
                    t.type_name_en

                FROM internship_periods p

                LEFT JOIN internship_types t
                    ON p.internship_type_id = t.id
            ";

            $conditions = [];

            if ($type !== '') {

                $conditions[] =
                    "t.type_code = :type";

                $params[':type'] =
                    $type;
            }

            if ($academicYear !== '') {

                $conditions[] =
                    "p.academic_year = :academic_year";

                $params[':academic_year'] =
                    $academicYear;
            }

            if ($semester !== '') {

                $conditions[] =
                    "p.semester = :semester";

                $params[':semester'] =
                    $semester;
            }

            if (!empty($conditions)) {

                $sql .= "
                    WHERE " .
                    implode(
                        " AND ",
                        $conditions
                    );
            }

            $sql .= "
                ORDER BY
                    p.start_date ASC,
                    p.id ASC
            ";

            break;


        // =================================================
        // STEPS
        // =================================================

        case 'steps':

            $sql = "
                SELECT
                    s.*,
                    t.type_code,
                    t.type_name_th,
                    t.type_name_en

                FROM internship_steps s

                LEFT JOIN internship_types t
                    ON s.internship_type_id = t.id
            ";

            $conditions = [];

            if ($type !== '') {

                $conditions[] = "
                    (
                        t.type_code = :type
                        OR s.internship_type_id IS NULL
                    )
                ";

                $params[':type'] =
                    $type;
            }

            if (!empty($conditions)) {

                $sql .= "
                    WHERE " .
                    implode(
                        " AND ",
                        $conditions
                    );
            }

            $sql .= "
                ORDER BY
                    s.step_order ASC,
                    s.id ASC
            ";

            break;


        // =================================================
        // APPLICATION PERIODS
        // =================================================

        case 'application_periods':

            $sql = "
                SELECT
                    a.*,
                    t.type_code,
                    t.type_name_th,
                    t.type_name_en

                FROM application_periods a

                LEFT JOIN internship_types t
                    ON a.internship_type_id = t.id
            ";

            $conditions = [];

            if ($type !== '') {

                $conditions[] =
                    "t.type_code = :type";

                $params[':type'] =
                    $type;
            }

            if ($academicYear !== '') {

                $conditions[] =
                    "a.academic_year = :academic_year";

                $params[':academic_year'] =
                    $academicYear;
            }

            if ($semester !== '') {

                $conditions[] =
                    "a.semester = :semester";

                $params[':semester'] =
                    $semester;
            }

            if (!empty($conditions)) {

                $sql .= "
                    WHERE " .
                    implode(
                        " AND ",
                        $conditions
                    );
            }

            $sql .= "
                ORDER BY
                    a.start_date ASC,
                    a.id ASC
            ";

            break;


        // =================================================
        // SECTION ไม่ถูกต้อง
        // =================================================

        default:

            http_response_code(400);

            echo json_encode([
                "success" => false,
                "message" =>
                    "section ไม่ถูกต้อง ต้องเป็น types, documents, periods, steps หรือ application_periods"
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            exit;
    }


    // =====================================================
    // Execute สำหรับคำถามเฉพาะ
    // =====================================================

    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $data =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    // =====================================================
    // Response
    // =====================================================

    echo json_encode([
        "success" => true,
        "section" => $section,
        "type" => $type !== ''
            ? $type
            : null,
        "count" => count($data),
        "data" => $data

    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);


} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" =>
            "เกิดข้อผิดพลาดในการดึงข้อมูล",
        "error" =>
            $e->getMessage()

    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}