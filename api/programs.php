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
// Helper: ชื่อระดับภาษาไทย
// =========================================================

function getDegreeLabel(string $degreeLevel): string
{
    $labels = [
        'bachelor' => 'ปริญญาตรี',
        'master' => 'ปริญญาโท',
        'doctoral' => 'ปริญญาเอก'
    ];

    return $labels[$degreeLevel] ?? $degreeLevel;
}


// =========================================================
// Helper: จัดรูปแบบตัวเลข
// 21.00 → 21
// 1.50  → 1.5
// 2.25  → 2.25
// =========================================================

function formatNumber($value)
{
    if ($value === null || $value === '') {
        return null;
    }

    $number = (float)$value;

    if (floor($number) == $number) {
        return (int)$number;
    }

    return $number;
}


// =========================================================
// Helper: Flat Components → Tree
// =========================================================

function buildComponentTree(array $components): array
{
    if (empty($components)) {
        return [];
    }

    $map = [];

    foreach ($components as $component) {

        $id = (int)$component['id'];

        $component['id'] = $id;

        $component['parent_component_id'] =
            !empty($component['parent_component_id'])
            ? (int)$component['parent_component_id']
            : null;

        $component['children'] = [];

        $map[$id] = $component;
    }


    foreach ($map as $id => &$component) {

        $parentId =
            $component['parent_component_id'];

        if (
            $parentId !== null &&
            isset($map[$parentId]) &&
            $parentId !== $id
        ) {
            $map[$parentId]['children'][] =
                &$component;
        }
    }

    unset($component);


    $tree = [];

    foreach ($map as $id => &$component) {

        $parentId =
            $component['parent_component_id'];

        if (
            $parentId === null ||
            !isset($map[$parentId]) ||
            $parentId === $id
        ) {
            $tree[] = &$component;
        }
    }

    unset($component);


    $sortTree = function (&$nodes) use (&$sortTree) {

        usort(
            $nodes,
            function ($a, $b) {

                $sortA =
                    (int)($a['sort_order'] ?? 0);

                $sortB =
                    (int)($b['sort_order'] ?? 0);

                if ($sortA === $sortB) {
                    return
                        (int)$a['id']
                        <=>
                        (int)$b['id'];
                }

                return $sortA <=> $sortB;
            }
        );

        foreach ($nodes as &$node) {

            if (!empty($node['children'])) {
                $sortTree(
                    $node['children']
                );
            }
        }

        unset($node);
    };

    $sortTree($tree);

    return $tree;
}


// =========================================================
// MAIN
// =========================================================

try {

    // =====================================================
    // 1. รับ Query String
    // =====================================================

    $id =
        isset($_GET['id'])
        ? (int)$_GET['id']
        : null;

    $programCode =
        trim($_GET['program_code'] ?? '');

    $degreeLevel =
        trim($_GET['degree_level'] ?? '');

    $majorName =
        trim($_GET['major_name'] ?? '');

    $search =
        trim($_GET['search'] ?? '');

    $planCode =
        trim($_GET['plan_code'] ?? '');

    $topic =
        strtolower(
            trim($_GET['topic'] ?? 'general')
        );

    $active =
        isset($_GET['active'])
        ? trim((string)$_GET['active'])
        : '1';


    // =====================================================
    // 2. Validation
    // =====================================================

    $allowedDegrees = [
        'bachelor',
        'master',
        'doctoral'
    ];

    if (
        $degreeLevel !== '' &&
        !in_array(
            $degreeLevel,
            $allowedDegrees,
            true
        )
    ) {

        sendJson(
            [
                'success' => false,
                'message' =>
                    'degree_level ต้องเป็น bachelor, master หรือ doctoral'
            ],
            400
        );
    }


    // -----------------------------------------------------
    // Topic ที่ API รองรับ
    // -----------------------------------------------------

    $allowedTopics = [
        'general',

        // รายการหลักสูตร
        'program_list',

        // รายการสาขา
        'major_list',

        'credits',
        'curriculum',
        'curriculum_structure',
        'study_plan',
        'plans',

        'admission',
        'admission_requirements',

        'career',
        'careers',

        'objective',
        'objectives'
    ];


    if (
        !in_array(
            $topic,
            $allowedTopics,
            true
        )
    ) {

        // ไม่ error
        // ถ้า topic แปลก ให้ fallback เป็น general
        $topic = 'general';
    }


    if (
        $active !== '' &&
        $active !== '0' &&
        $active !== '1'
    ) {

        sendJson(
            [
                'success' => false,
                'message' =>
                    'active ต้องเป็น 0 หรือ 1'
            ],
            400
        );
    }


    // =====================================================
    // 3. Query หลักสูตร
    // =====================================================

    $sql = "
        SELECT *
        FROM academic_programs
        WHERE 1 = 1
    ";

    $params = [];


    if ($id) {

        $sql .= "
            AND id = :id
        ";

        $params[':id'] = $id;
    }


    if ($programCode !== '') {

        $sql .= "
            AND program_code = :program_code
        ";

        $params[':program_code'] =
            $programCode;
    }


    if ($degreeLevel !== '') {

        $sql .= "
            AND degree_level = :degree_level
        ";

        $params[':degree_level'] =
            $degreeLevel;
    }


    if ($majorName !== '') {

        $sql .= "
            AND (
                major_name LIKE :major_name
                OR major_name_en LIKE :major_name
                OR curriculum_name LIKE :major_name
                OR aliases LIKE :major_name
            )
        ";

        $params[':major_name'] =
            '%' . $majorName . '%';
    }


    if ($search !== '') {

        $sql .= "
            AND (
                program_code LIKE :search
                OR curriculum_name LIKE :search
                OR major_name LIKE :search
                OR major_name_en LIKE :search
                OR degree_name_th LIKE :search
                OR degree_name_en LIKE :search
                OR aliases LIKE :search
            )
        ";

        $params[':search'] =
            '%' . $search . '%';
    }


    if (
        $active === '1' ||
        $active === '0'
    ) {

        $sql .= "
            AND active = :active
        ";

        $params[':active'] =
            (int)$active;
    }


    $sql .= "
        ORDER BY
            FIELD(
                degree_level,
                'bachelor',
                'master',
                'doctoral'
            ),
            curriculum_name ASC,
            major_name ASC,
            id ASC
    ";


    $stmt =
        $pdo->prepare($sql);

    $stmt->execute($params);

    $programs =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    // =====================================================
    // 3.1 PROGRAM LIST
    //
    // ใช้สำหรับ:
    // "มีหลักสูตรอะไรบ้าง"
    //
    // รวม curriculum_name ที่ซ้ำกัน
    // =====================================================

    if ($topic === 'program_list') {

        $programListMap = [];

        foreach ($programs as $program) {

            $curriculumName =
                trim(
                    (string)(
                        $program['curriculum_name']
                        ?? ''
                    )
                );

            $programDegree =
                trim(
                    (string)(
                        $program['degree_level']
                        ?? ''
                    )
                );

            if ($curriculumName === '') {
                continue;
            }

            $key =
                $programDegree
                . '|'
                . $curriculumName;

            if (
                !isset(
                    $programListMap[$key]
                )
            ) {

                $programListMap[$key] = [

                    'curriculum_name' =>
                        $curriculumName,

                    'degree_level' =>
                        $programDegree,

                    'degree_level_label' =>
                        getDegreeLabel(
                            $programDegree
                        )
                ];
            }
        }


        $programList =
            array_values(
                $programListMap
            );


        usort(
            $programList,
            function ($a, $b) {

                $degreeOrder = [
                    'bachelor' => 1,
                    'master' => 2,
                    'doctoral' => 3
                ];

                $degreeA =
                    $degreeOrder[
                        $a['degree_level']
                    ] ?? 99;

                $degreeB =
                    $degreeOrder[
                        $b['degree_level']
                    ] ?? 99;

                if ($degreeA !== $degreeB) {
                    return $degreeA <=> $degreeB;
                }

                return strcmp(
                    $a['curriculum_name'],
                    $b['curriculum_name']
                );
            }
        );


        sendJson(
            [
                'success' => true,

                'count' =>
                    count($programList),

                'record_count' =>
                    count($programs),

                'topic' =>
                    'program_list',

                'filters' => [

                    'id' =>
                        $id,

                    'program_code' =>
                        $programCode !== ''
                            ? $programCode
                            : null,

                    'degree_level' =>
                        $degreeLevel !== ''
                            ? $degreeLevel
                            : null,

                    'major_name' =>
                        $majorName !== ''
                            ? $majorName
                            : null,

                    'search' =>
                        $search !== ''
                            ? $search
                            : null,

                    'active' =>
                        $active
                ],

                'data' =>
                    $programList
            ]
        );
    }


    // =====================================================
    // 3.2 MAJOR LIST
    //
    // ใช้สำหรับ:
    // "มีสาขาอะไรบ้าง"
    //
    // รวม major_name ที่ซ้ำกัน
    // =====================================================

    if ($topic === 'major_list') {

        $majorListMap = [];

        foreach ($programs as $program) {

            $majorNameValue =
                trim(
                    (string)(
                        $program['major_name']
                        ?? ''
                    )
                );

            $majorNameEn =
                trim(
                    (string)(
                        $program['major_name_en']
                        ?? ''
                    )
                );

            $programDegree =
                trim(
                    (string)(
                        $program['degree_level']
                        ?? ''
                    )
                );

            $curriculumName =
                trim(
                    (string)(
                        $program['curriculum_name']
                        ?? ''
                    )
                );


            if ($majorNameValue === '') {
                continue;
            }


            $key =
                $programDegree
                . '|'
                . $majorNameValue;


            if (
                !isset(
                    $majorListMap[$key]
                )
            ) {

                $majorListMap[$key] = [

                    'major_name' =>
                        $majorNameValue,

                    'major_name_en' =>
                        $majorNameEn !== ''
                            ? $majorNameEn
                            : null,

                    'curriculum_name' =>
                        $curriculumName !== ''
                            ? $curriculumName
                            : null,

                    'degree_level' =>
                        $programDegree,

                    'degree_level_label' =>
                        getDegreeLabel(
                            $programDegree
                        )
                ];
            }
        }


        $majorList =
            array_values(
                $majorListMap
            );


        usort(
            $majorList,
            function ($a, $b) {

                $degreeOrder = [
                    'bachelor' => 1,
                    'master' => 2,
                    'doctoral' => 3
                ];

                $degreeA =
                    $degreeOrder[
                        $a['degree_level']
                    ] ?? 99;

                $degreeB =
                    $degreeOrder[
                        $b['degree_level']
                    ] ?? 99;

                if ($degreeA !== $degreeB) {
                    return $degreeA <=> $degreeB;
                }

                return strcmp(
                    $a['major_name'],
                    $b['major_name']
                );
            }
        );


        sendJson(
            [
                'success' => true,

                'count' =>
                    count($majorList),

                'record_count' =>
                    count($programs),

                'topic' =>
                    'major_list',

                'filters' => [

                    'id' =>
                        $id,

                    'program_code' =>
                        $programCode !== ''
                            ? $programCode
                            : null,

                    'degree_level' =>
                        $degreeLevel !== ''
                            ? $degreeLevel
                            : null,

                    'major_name' =>
                        $majorName !== ''
                            ? $majorName
                            : null,

                    'search' =>
                        $search !== ''
                            ? $search
                            : null,

                    'active' =>
                        $active
                ],

                'data' =>
                    $majorList
            ]
        );
    }


    // =====================================================
    // 4. ดึงข้อมูลตาม Topic
    //
    // ส่วนเดิม
    // =====================================================

    foreach ($programs as &$program) {

        $programId =
            (int)$program['id'];


        // -------------------------------------------------
        // แปลง id และเพิ่ม label
        // -------------------------------------------------

        $program['id'] =
            $programId;

        $program['degree_level_label'] =
            getDegreeLabel(
                $program['degree_level']
            );


        // =================================================
        // ลดข้อมูลทั่วไปตาม topic
        // =================================================

        if ($topic !== 'general') {

            $program = [
                'id' =>
                    $programId,

                'program_code' =>
                    $program['program_code'],

                'degree_level' =>
                    $program['degree_level'],

                'degree_level_label' =>
                    getDegreeLabel(
                        $program['degree_level']
                    ),

                'curriculum_name' =>
                    $program['curriculum_name'],

                'major_name' =>
                    $program['major_name'],

                'major_name_en' =>
                    $program['major_name_en']
                    ?? null,

                'curriculum_year' =>
                    $program['curriculum_year']
                    ?? null
            ];
        }


        // =================================================
        // 4.1 Credits / Curriculum / Study Plan
        // =================================================

        $needsPlans = in_array(
            $topic,
            [
                'credits',
                'curriculum',
                'curriculum_structure',
                'study_plan',
                'plans'
            ],
            true
        );


        if ($needsPlans) {

            $planSql = "
                SELECT
                    id,
                    program_id,
                    plan_code,
                    plan_name,
                    total_credits,
                    description,
                    sort_order

                FROM program_plans

                WHERE program_id = :program_id
            ";

            $planParams = [
                ':program_id' =>
                    $programId
            ];


            if ($planCode !== '') {

                $planSql .= "
                    AND plan_code = :plan_code
                ";

                $planParams[':plan_code'] =
                    $planCode;
            }


            $planSql .= "
                ORDER BY
                    sort_order ASC,
                    id ASC
            ";


            $stmtPlan =
                $pdo->prepare(
                    $planSql
                );

            $stmtPlan->execute(
                $planParams
            );

            $plans =
                $stmtPlan->fetchAll(
                    PDO::FETCH_ASSOC
                );


            foreach ($plans as &$plan) {

                $planId =
                    (int)$plan['id'];

                $plan['id'] =
                    $planId;

                $plan['total_credits'] =
                    formatNumber(
                        $plan['total_credits']
                    );


                $stmtComponent =
                    $pdo->prepare("
                        SELECT
                            id,
                            plan_id,
                            parent_component_id,
                            component_name,
                            description,
                            credits,
                            hours,
                            note,
                            sort_order

                        FROM
                            program_plan_components

                        WHERE
                            plan_id = :plan_id

                        ORDER BY
                            sort_order ASC,
                            id ASC
                    ");


                $stmtComponent->execute([
                    ':plan_id' =>
                        $planId
                ]);


                $flatComponents =
                    $stmtComponent->fetchAll(
                        PDO::FETCH_ASSOC
                    );


                foreach (
                    $flatComponents
                    as &$component
                ) {

                    $component['credits'] =
                        formatNumber(
                            $component['credits']
                        );

                    $component['hours'] =
                        formatNumber(
                            $component['hours']
                        );
                }

                unset($component);


                $plan['components'] =
                    buildComponentTree(
                        $flatComponents
                    );


                $plan['component_count'] =
                    count(
                        $flatComponents
                    );
            }

            unset($plan);


            $program['plans'] =
                $plans;
        }


        // =================================================
        // 4.2 Admission
        // =================================================

        if (
            in_array(
                $topic,
                [
                    'admission',
                    'admission_requirements'
                ],
                true
            )
        ) {

            $stmtRequirement =
                $pdo->prepare("
                    SELECT
                        r.id,
                        r.plan_id,
                        p.plan_code,
                        p.plan_name,
                        r.requirement_text,
                        r.sort_order

                    FROM
                        program_admission_requirements r

                    LEFT JOIN
                        program_plans p
                    ON
                        r.plan_id = p.id

                    WHERE
                        r.program_id = :program_id

                    ORDER BY
                        r.sort_order ASC,
                        r.id ASC
                ");


            $stmtRequirement->execute([
                ':program_id' =>
                    $programId
            ]);


            $program[
                'admission_requirements'
            ] =
                $stmtRequirement->fetchAll(
                    PDO::FETCH_ASSOC
                );
        }


        // =================================================
        // 4.3 Careers
        // =================================================

        if (
            in_array(
                $topic,
                [
                    'career',
                    'careers'
                ],
                true
            )
        ) {

            $stmtCareer =
                $pdo->prepare("
                    SELECT
                        id,
                        career_name,
                        sort_order

                    FROM
                        program_careers

                    WHERE
                        program_id = :program_id

                    ORDER BY
                        sort_order ASC,
                        id ASC
                ");


            $stmtCareer->execute([
                ':program_id' =>
                    $programId
            ]);


            $program['careers'] =
                $stmtCareer->fetchAll(
                    PDO::FETCH_ASSOC
                );
        }


        // =================================================
        // 4.4 Objectives
        // =================================================

        if (
            in_array(
                $topic,
                [
                    'objective',
                    'objectives'
                ],
                true
            )
        ) {

            $stmtObjective =
                $pdo->prepare("
                    SELECT
                        id,
                        objective_text,
                        sort_order

                    FROM
                        program_objectives

                    WHERE
                        program_id = :program_id

                    ORDER BY
                        sort_order ASC,
                        id ASC
                ");


            $stmtObjective->execute([
                ':program_id' =>
                    $programId
            ]);


            $program['objectives'] =
                $stmtObjective->fetchAll(
                    PDO::FETCH_ASSOC
                );
        }


        // =================================================
        // 4.5 General
        // =================================================

        if ($topic === 'general') {

            $program['degree_level_label'] =
                getDegreeLabel(
                    $program['degree_level']
                );
        }
    }

    unset($program);


    // =====================================================
    // 5. Response
    // =====================================================

    sendJson(
        [
            'success' =>
                true,

            'count' =>
                count($programs),

            'topic' =>
                $topic,

            'filters' => [

                'id' =>
                    $id,

                'program_code' =>
                    $programCode !== ''
                        ? $programCode
                        : null,

                'degree_level' =>
                    $degreeLevel !== ''
                        ? $degreeLevel
                        : null,

                'major_name' =>
                    $majorName !== ''
                        ? $majorName
                        : null,

                'search' =>
                    $search !== ''
                        ? $search
                        : null,

                'plan_code' =>
                    $planCode !== ''
                        ? $planCode
                        : null,

                'active' =>
                    $active
            ],

            'data' =>
                $programs
        ]
    );

} catch (PDOException $e) {

    sendJson(
        [
            'success' =>
                false,

            'message' =>
                'เกิดข้อผิดพลาดในการดึงข้อมูลจากฐานข้อมูล',

            'error' =>
                $e->getMessage()
        ],
        500
    );

} catch (Throwable $e) {

    sendJson(
        [
            'success' =>
                false,

            'message' =>
                'เกิดข้อผิดพลาดในการประมวลผลข้อมูล',

            'error' =>
                $e->getMessage()
        ],
        500
    );
}