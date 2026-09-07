<?php

header(
    "Content-Type: application/json; charset=UTF-8"
);

header(
    "Access-Control-Allow-Origin: *"
);

require_once "db.php";

function thaiDate($date)
{
    if (!$date) {
        return null;
    }

    $months = [
        1 => 'มกราคม',
        2 => 'กุมภาพันธ์',
        3 => 'มีนาคม',
        4 => 'เมษายน',
        5 => 'พฤษภาคม',
        6 => 'มิถุนายน',
        7 => 'กรกฎาคม',
        8 => 'สิงหาคม',
        9 => 'กันยายน',
        10 => 'ตุลาคม',
        11 => 'พฤศจิกายน',
        12 => 'ธันวาคม'
    ];

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return null;
    }

    $day = (int)date('j', $timestamp);
    $month = (int)date('n', $timestamp);
    $year = (int)date('Y', $timestamp) + 543;

    return $day . ' ' . $months[$month] . ' ' . $year;
}


function thaiDateRange($startDate, $endDate = null)
{
    if (!$startDate) {
        return null;
    }

    if (!$endDate || $startDate === $endDate) {
        return thaiDate($startDate);
    }

    $startTs = strtotime($startDate);
    $endTs = strtotime($endDate);

    if ($startTs === false || $endTs === false) {
        return null;
    }

    $months = [
        1 => 'มกราคม',
        2 => 'กุมภาพันธ์',
        3 => 'มีนาคม',
        4 => 'เมษายน',
        5 => 'พฤษภาคม',
        6 => 'มิถุนายน',
        7 => 'กรกฎาคม',
        8 => 'สิงหาคม',
        9 => 'กันยายน',
        10 => 'ตุลาคม',
        11 => 'พฤศจิกายน',
        12 => 'ธันวาคม'
    ];

    $startDay = (int)date('j', $startTs);
    $startMonth = (int)date('n', $startTs);
    $startYear = (int)date('Y', $startTs) + 543;

    $endDay = (int)date('j', $endTs);
    $endMonth = (int)date('n', $endTs);
    $endYear = (int)date('Y', $endTs) + 543;

    // วันอยู่เดือนและปีเดียวกัน
    if (
        $startMonth === $endMonth
        && $startYear === $endYear
    ) {
        return $startDay
            . ' - '
            . $endDay
            . ' '
            . $months[$startMonth]
            . ' '
            . $startYear;
    }

    // ปีเดียวกัน แต่คนละเดือน
    if ($startYear === $endYear) {
        return $startDay
            . ' '
            . $months[$startMonth]
            . ' - '
            . $endDay
            . ' '
            . $months[$endMonth]
            . ' '
            . $startYear;
    }

    // ข้ามปี
    return $startDay
        . ' '
        . $months[$startMonth]
        . ' '
        . $startYear
        . ' - '
        . $endDay
        . ' '
        . $months[$endMonth]
        . ' '
        . $endYear;
}


try {

    // =====================================
    // Query String
    // =====================================

    $id =
        isset($_GET['id'])
        ? (int)$_GET['id']
        : null;


    $academicYear =
        trim(
            $_GET['academic_year']
            ?? ''
        );


    $degreeLevel =
        trim(
            $_GET['degree_level']
            ?? ''
        );


    $semester =
        trim(
            $_GET['semester']
            ?? ''
        );


    $category =
        trim(
            $_GET['category']
            ?? ''
        );


    $phase =
        trim(
            $_GET['phase']
            ?? ''
        );


    $yearLevel =
        trim(
            $_GET['year_level']
            ?? ''
        );


    $studentCode =
        trim(
            $_GET['student_code']
            ?? ''
        );


    $search =
        trim(
            $_GET['search']
            ?? ''
        );


    $date =
        trim(
            $_GET['date']
            ?? ''
        );


    $active =
        $_GET['active']
        ?? '1';


    // =====================================
    // Normalize year_level
    //
    // รองรับ:
    // 4
    // ปี 4
    // ปี4
    // ชั้นปี 4
    // ชั้นปีที่ 4
    // =====================================

    if ($yearLevel !== '') {

        if (
            preg_match(
                '/([1-9][0-9]*)/',
                $yearLevel,
                $matches
            )
        ) {
            $yearLevel = $matches[1];
        }
    }


    // =====================================
    // SQL
    // =====================================

    $sql = "
        SELECT *
        FROM academic_calendar_events
        WHERE 1 = 1
    ";


    $params = [];


    // =====================================
    // ID
    // =====================================

    if ($id) {

        $sql .= "
            AND id = :id
        ";

        $params[':id'] =
            $id;
    }


    // =====================================
    // Academic Year
    // =====================================

    if ($academicYear !== '') {

        $sql .= "
            AND academic_year = :academic_year
        ";

        $params[':academic_year'] =
            (int)$academicYear;
    }


    // =====================================
    // Degree Level
    // =====================================

    if ($degreeLevel !== '') {

        $sql .= "
            AND degree_level = :degree_level
        ";

        $params[':degree_level'] =
            $degreeLevel;
    }


    // =====================================
    // Semester
    // =====================================

    if ($semester !== '') {

        $sql .= "
            AND semester = :semester
        ";

        $params[':semester'] =
            $semester;
    }


    // =====================================
    // Category
    // =====================================

    if ($category !== '') {

        $sql .= "
            AND category_code = :category
        ";

        $params[':category'] =
            $category;
    }


    // =====================================
    // Phase
    // =====================================

    if ($phase !== '') {

        $sql .= "
            AND phase_no = :phase
        ";

        $params[':phase'] =
            (int)$phase;
    }


    // =====================================
    // Year Level
    //
    // รองรับ:
    // - ปีที่ระบุตรง ๆ
    // - all
    // - audience_text ที่มีเลขปี
    // - "ปีที่ 3 ขึ้นไป"
    //
    // เช่น:
    // ผู้ใช้ถามปี 4
    // ต้องเจอ:
    // - ปี 4
    // - ปี 3 ขึ้นไป
    // - ปี 2 ขึ้นไป
    // - ทุกชั้นปี
    // =====================================

    if ($yearLevel !== '') {

        $yearLevelInt =
            (int)$yearLevel;


        $yearConditions = [

            "year_level = :year_level",

            "year_level = 'all'",

            "audience_text LIKE :year_search"
        ];


        $params[':year_level'] =
            $yearLevel;


        $params[':year_search'] =
            '%' .
            $yearLevel .
            '%';


        // ---------------------------------
        // รองรับ "ปี X ขึ้นไป"
        //
        // ถ้าผู้ใช้ถามปี 4
        // จะตรวจ:
        // ปี 1 ขึ้นไป
        // ปี 2 ขึ้นไป
        // ปี 3 ขึ้นไป
        // ปี 4 ขึ้นไป
        // ---------------------------------

        if ($yearLevelInt > 0) {

            for (
                $minYear = 1;
                $minYear <= $yearLevelInt;
                $minYear++
            ) {

                $placeholder1 =
                    ':year_up_' .
                    $minYear .
                    '_1';

                $placeholder2 =
                    ':year_up_' .
                    $minYear .
                    '_2';

                $placeholder3 =
                    ':year_up_' .
                    $minYear .
                    '_3';

                $placeholder4 =
                    ':year_up_' .
                    $minYear .
                    '_4';


                $yearConditions[] =
                    "audience_text LIKE {$placeholder1}";

                $yearConditions[] =
                    "audience_text LIKE {$placeholder2}";

                $yearConditions[] =
                    "audience_text LIKE {$placeholder3}";

                $yearConditions[] =
                    "audience_text LIKE {$placeholder4}";


                $params[$placeholder1] =
                    '%ชั้นปีที่ ' .
                    $minYear .
                    ' ขึ้นไป%';

                $params[$placeholder2] =
                    '%ชั้นปี ' .
                    $minYear .
                    ' ขึ้นไป%';

                $params[$placeholder3] =
                    '%ปีที่ ' .
                    $minYear .
                    ' ขึ้นไป%';

                $params[$placeholder4] =
                    '%ปี ' .
                    $minYear .
                    ' ขึ้นไป%';
            }
        }


        $sql .= "
            AND (
                " .
                implode(
                    "\n OR ",
                    $yearConditions
                ) .
                "
            )
        ";
    }


    // =====================================
    // Student Code
    // =====================================

    if ($studentCode !== '') {

        $sql .= "
            AND (
                student_code LIKE :student_code
                OR audience_text LIKE :student_search
            )
        ";


        $params[':student_code'] =
            '%' .
            $studentCode .
            '%';


        $params[':student_search'] =
            '%' .
            $studentCode .
            '%';
    }


    // =====================================
    // Search
    // =====================================

    if ($search !== '') {

        $sql .= "
            AND (
                event_name LIKE :search

                OR phase_name LIKE :search

                OR category_code LIKE :search

                OR audience_text LIKE :search

                OR keywords LIKE :search

                OR note LIKE :search

                OR location_or_channel LIKE :search
            )
        ";


        $params[':search'] =
            '%' .
            $search .
            '%';
    }


    // =====================================
    // Date
    //
    // ค้นหาว่าวันที่นี้
    // อยู่ในกิจกรรมใดบ้าง
    // =====================================

    if ($date !== '') {

        $sql .= "
            AND (
                :target_date
                BETWEEN
                    start_date
                    AND
                    COALESCE(
                        end_date,
                        start_date
                    )
            )
        ";


        $params[':target_date'] =
            $date;
    }


    // =====================================
    // Active
    // =====================================

    if (
        $active === '1'
        ||
        $active === '0'
    ) {

        $sql .= "
            AND active = :active
        ";


        $params[':active'] =
            (int)$active;
    }


    // =====================================
    // Order
    // =====================================

    $sql .= "
        ORDER BY

            academic_year DESC,

            CASE semester

                WHEN 'first'
                    THEN 1

                WHEN 'second'
                    THEN 2

                WHEN 'summer'
                    THEN 3

                ELSE 4

            END,

            COALESCE(
                start_date,
                '9999-12-31'
            ),

            phase_no,

            sort_order,

            id
    ";


    // =====================================
    // Execute
    // =====================================

    $stmt =
        $pdo->prepare(
            $sql
        );


    $stmt->execute(
        $params
    );


    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    // =====================================
    // เพิ่มข้อมูลภาษาไทย
    // =====================================

foreach ($rows as &$row) {

    // =====================================
    // ชื่อภาคภาษาไทย
    // =====================================

    $row['semester_name_th'] =
        match ($row['semester']) {

            'first' =>
                'ภาคต้น',

            'second' =>
                'ภาคปลาย',

            'summer' =>
                'ภาคฤดูร้อน',

            default =>
                null
        };


    // =====================================
    // สถานะภาษาไทย
    // =====================================

    $row['status_name_th'] =
        $row['event_status'] === 'no_activity'
        ? 'ไม่มีกิจกรรม'
        : 'มีกิจกรรม';


    // =====================================
    // วันที่ภาษาไทย
    // =====================================

    $row['start_date_th'] =
        thaiDate(
            $row['start_date'] ?? null
        );


    $row['end_date_th'] =
        thaiDate(
            $row['end_date'] ?? null
        );


    $row['date_range_th'] =
        thaiDateRange(
            $row['start_date'] ?? null,
            $row['end_date'] ?? null
        );
}


    unset($row);


    // =====================================
    // Response
    // =====================================

    echo json_encode(
        [
            'success' =>
                true,

            'count' =>
                count($rows),

            'filters' => [

                'id' =>
                    $id,

                'academic_year' =>
                    $academicYear
                    ?: null,

                'degree_level' =>
                    $degreeLevel
                    ?: null,

                'semester' =>
                    $semester
                    ?: null,

                'category' =>
                    $category
                    ?: null,

                'phase' =>
                    $phase
                    ?: null,

                'year_level' =>
                    $yearLevel
                    ?: null,

                'student_code' =>
                    $studentCode
                    ?: null,

                'search' =>
                    $search
                    ?: null,

                'date' =>
                    $date
                    ?: null
            ],

            'data' =>
                $rows
        ],

        JSON_UNESCAPED_UNICODE
        |
        JSON_PRETTY_PRINT
    );


} catch (PDOException $e) {


    http_response_code(
        500
    );


    echo json_encode(
        [
            'success' =>
                false,

            'message' =>
                'เกิดข้อผิดพลาดในการดึงข้อมูล',

            'error' =>
                $e->getMessage()
        ],

        JSON_UNESCAPED_UNICODE
        |
        JSON_PRETTY_PRINT
    );
}