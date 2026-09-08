<?php

require_once "../../api/db.php";
require_once "../admin_activity.php";


// =====================================================
// Helper
// =====================================================

function cleanString($value): string
{
    return trim((string)($value ?? ''));
}


/**
 * ตรวจสอบโครงสร้าง parent-child ของ component
 *
 * ป้องกัน:
 * - parent ไม่มีอยู่จริง
 * - parent เป็นตัวเอง
 * - วงจร เช่น A → B → A
 */
function validateComponentHierarchy(array $rows): void
{
    $keys = [];
    $parentMap = [];

    foreach ($rows as $row) {

        $key = cleanString($row['client_key'] ?? '');

        if ($key === '') {
            throw new Exception(
                "ไม่พบรหัสภายในของหัวข้อโครงสร้างหลักสูตร"
            );
        }

        if (isset($keys[$key])) {
            throw new Exception(
                "พบหัวข้อโครงสร้างหลักสูตรซ้ำ กรุณาลองเพิ่มรายการใหม่อีกครั้ง"
            );
        }

        $keys[$key] = true;

        $parentMap[$key] =
            cleanString($row['parent_key'] ?? '');
    }


    // ตรวจ parent
    foreach ($parentMap as $key => $parentKey) {

        if ($parentKey === '') {
            continue;
        }

        if ($parentKey === $key) {
            throw new Exception(
                "หัวข้อโครงสร้างไม่สามารถเป็นหัวข้อแม่ของตัวเองได้"
            );
        }

        if (!isset($keys[$parentKey])) {
            throw new Exception(
                "พบหัวข้อแม่ที่ไม่มีอยู่ในแผนเดียวกัน"
            );
        }
    }


    // ตรวจ cycle
    foreach (array_keys($keys) as $startKey) {

        $visited = [];
        $current = $startKey;

        while (
            isset($parentMap[$current]) &&
            $parentMap[$current] !== ''
        ) {

            if (isset($visited[$current])) {
                throw new Exception(
                    "โครงสร้างหัวข้อมีการอ้างอิงวนกัน กรุณาตรวจสอบหัวข้อแม่"
                );
            }

            $visited[$current] = true;

            $current = $parentMap[$current];

            if (!isset($keys[$current])) {
                break;
            }
        }
    }
}


// =====================================================
// ค่าเริ่มต้น
// =====================================================

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

$isEdit = false;
$error = '';

$program = [
    'program_code' => '',
    'degree_level' => 'bachelor',
    'curriculum_name' => '',
    'major_name' => '',
    'major_name_en' => '',
    'degree_name_th' => '',
    'degree_abbr_th' => '',
    'degree_name_en' => '',
    'degree_abbr_en' => '',
    'aliases' => '',
    'curriculum_year' => '',
    'program_language' => '',
    'admission_info' => '',
    'cooperation_info' => '',
    'integration_info' => '',
    'active' => 1
];

$plans = [];
$requirements = [];
$careers = [];
$objectives = [];


// =====================================================
// โหลดข้อมูลกรณีแก้ไข
// =====================================================

if ($id) {

    // -------------------------------------------------
    // Program
    // -------------------------------------------------

    $stmt = $pdo->prepare("
        SELECT *
        FROM academic_programs
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $id
    ]);

    $row = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$row) {
        exit("ไม่พบข้อมูลหลักสูตร");
    }

    $program = array_merge(
        $program,
        $row
    );

    $isEdit = true;


    // -------------------------------------------------
    // Plans
    // -------------------------------------------------

    $stmt = $pdo->prepare("
        SELECT *
        FROM program_plans
        WHERE program_id = :program_id
        ORDER BY
            sort_order ASC,
            id ASC
    ");

    $stmt->execute([
        ':program_id' => $id
    ]);

    $plans = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


    // -------------------------------------------------
    // Components ของแต่ละ Plan
    // -------------------------------------------------

    foreach ($plans as &$plan) {

        $stmt = $pdo->prepare("
            SELECT *
            FROM program_plan_components
            WHERE plan_id = :plan_id
            ORDER BY
                sort_order ASC,
                id ASC
        ");

        $stmt->execute([
            ':plan_id' => $plan['id']
        ]);

        $components = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


        // ---------------------------------------------
        // สร้าง client_key สำหรับ browser
        // ---------------------------------------------

        $componentIdToKey = [];

        foreach (
            $components as $index => &$component
        ) {

            $componentKey =
                'c_' .
                (int)$plan['id'] .
                '_' .
                $index;

            $component['client_key'] =
                $componentKey;

            $componentIdToKey[(int)$component['id']] = $componentKey;
        }

        unset($component);


        // ---------------------------------------------
        // แปลง parent_component_id → parent_key
        // ---------------------------------------------

        foreach ($components as &$component) {

            $parentId = (int)(
                $component['parent_component_id'] ?? 0
            );

            if (
                $parentId > 0 &&
                isset(
                    $componentIdToKey[$parentId]
                )
            ) {

                $component['parent_key'] =
                    $componentIdToKey[$parentId];
            } else {

                $component['parent_key'] = '';
            }
        }

        unset($component);

        $plan['components'] =
            $components;
    }

    unset($plan);


    // -------------------------------------------------
    // Requirements
    // -------------------------------------------------

    $stmt = $pdo->prepare("
        SELECT *
        FROM program_admission_requirements
        WHERE program_id = :program_id
        ORDER BY
            sort_order ASC,
            id ASC
    ");

    $stmt->execute([
        ':program_id' => $id
    ]);

    $requirements = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


    // -------------------------------------------------
    // Careers
    // -------------------------------------------------

    $stmt = $pdo->prepare("
        SELECT *
        FROM program_careers
        WHERE program_id = :program_id
        ORDER BY
            sort_order ASC,
            id ASC
    ");

    $stmt->execute([
        ':program_id' => $id
    ]);

    $careers = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


    // -------------------------------------------------
    // Objectives
    // -------------------------------------------------

    $stmt = $pdo->prepare("
        SELECT *
        FROM program_objectives
        WHERE program_id = :program_id
        ORDER BY
            sort_order ASC,
            id ASC
    ");

    $stmt->execute([
        ':program_id' => $id
    ]);

    $objectives = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );
}


// =====================================================
// บันทึกข้อมูล
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedId = filter_input(
        INPUT_POST,
        'id',
        FILTER_VALIDATE_INT
    );

    $programCode =
        cleanString(
            $_POST['program_code'] ?? ''
        );

    $degreeLevel =
        cleanString(
            $_POST['degree_level'] ?? ''
        );

    $curriculumName =
        cleanString(
            $_POST['curriculum_name'] ?? ''
        );

    $majorName =
        cleanString(
            $_POST['major_name'] ?? ''
        );

    $majorNameEn =
        cleanString(
            $_POST['major_name_en'] ?? ''
        );

    $degreeNameTh =
        cleanString(
            $_POST['degree_name_th'] ?? ''
        );

    $degreeAbbrTh =
        cleanString(
            $_POST['degree_abbr_th'] ?? ''
        );

    $degreeNameEn =
        cleanString(
            $_POST['degree_name_en'] ?? ''
        );

    $degreeAbbrEn =
        cleanString(
            $_POST['degree_abbr_en'] ?? ''
        );

    $aliases =
        cleanString(
            $_POST['aliases'] ?? ''
        );

    $curriculumYear =
        cleanString(
            $_POST['curriculum_year'] ?? ''
        );

    $studyPeriod =
        cleanString(
            $_POST['study_period'] ?? ''
        );

    $tuitionFeePerSemester =
        cleanString(
            $_POST['tuition_fee_per_semester'] ?? ''
        );

    $totalTuitionFee =
        cleanString(
            $_POST['total_tuition_fee'] ?? ''
        );

    $programLanguage =
        cleanString(
            $_POST['program_language'] ?? ''
        );

    $admissionInfo =
        cleanString(
            $_POST['admission_info'] ?? ''
        );

    $cooperationInfo =
        cleanString(
            $_POST['cooperation_info'] ?? ''
        );

    $integrationInfo =
        cleanString(
            $_POST['integration_info'] ?? ''
        );

    $active =
        isset($_POST['active'])
        ? 1
        : 0;


    $allowedDegrees = [
        'bachelor',
        'master',
        'doctoral'
    ];


    // -------------------------------------------------
    // Validate
    // -------------------------------------------------

    if (
        $programCode === '' ||
        $curriculumName === '' ||
        $majorName === ''
    ) {

        $error =
            "กรุณากรอกข้อมูลที่จำเป็นให้ครบ";
    } elseif (
        !in_array(
            $degreeLevel,
            $allowedDegrees,
            true
        )
    ) {

        $error =
            "ระดับการศึกษาไม่ถูกต้อง";
    } else {

        try {

            $pdo->beginTransaction();


            // =================================================
            // academic_programs
            // =================================================

            if ($postedId) {

                $stmt = $pdo->prepare("
                    UPDATE academic_programs
                    SET
                        program_code = :program_code,
                        degree_level = :degree_level,
                        curriculum_name = :curriculum_name,
                        major_name = :major_name,
                        major_name_en = :major_name_en,
                        degree_name_th = :degree_name_th,
                        degree_abbr_th = :degree_abbr_th,
                        degree_name_en = :degree_name_en,
                        degree_abbr_en = :degree_abbr_en,
                        aliases = :aliases,
                        curriculum_year = :curriculum_year,
                        study_period = :study_period,
                        tuition_fee_per_semester = :tuition_fee_per_semester,
                        total_tuition_fee = :total_tuition_fee,
                        program_language = :program_language,
                        admission_info = :admission_info,
                        cooperation_info = :cooperation_info,
                        integration_info = :integration_info,
                        active = :active
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':program_code' =>
                    $programCode,

                    ':degree_level' =>
                    $degreeLevel,

                    ':curriculum_name' =>
                    $curriculumName,

                    ':major_name' =>
                    $majorName,

                    ':major_name_en' =>
                    $majorNameEn ?: null,

                    ':degree_name_th' =>
                    $degreeNameTh ?: null,

                    ':degree_abbr_th' =>
                    $degreeAbbrTh ?: null,

                    ':degree_name_en' =>
                    $degreeNameEn ?: null,

                    ':degree_abbr_en' =>
                    $degreeAbbrEn ?: null,

                    ':aliases' =>
                    $aliases ?: null,

                    ':curriculum_year' =>
                    $curriculumYear ?: null,

                    ':program_language' =>
                    $programLanguage ?: null,

                    ':admission_info' =>
                    $admissionInfo ?: null,

                    ':cooperation_info' =>
                    $cooperationInfo ?: null,

                    ':integration_info' =>
                    $integrationInfo ?: null,

                    ':active' =>
                    $active,

                    ':id' =>
                    $postedId
                ]);

                $programId =
                    (int)$postedId;
            } else {

                $stmt = $pdo->prepare("
                    INSERT INTO academic_programs
                    (
                        program_code,
                        degree_level,
                        curriculum_name,
                        major_name,
                        major_name_en,
                        degree_name_th,
                        degree_abbr_th,
                        degree_name_en,
                        degree_abbr_en,
                        aliases,
                        curriculum_year,
                        study_period,
                        tuition_fee_per_semester,
                        total_tuition_fee,
                        program_language,
                        admission_info,
                        cooperation_info,
                        integration_info,
                        active
                    )
                    VALUES
                    (
                        :program_code,
                        :degree_level,
                        :curriculum_name,
                        :major_name,
                        :major_name_en,
                        :degree_name_th,
                        :degree_abbr_th,
                        :degree_name_en,
                        :degree_abbr_en,
                        :aliases,
                        :curriculum_year,
                        :study_period,
                        :tuition_fee_per_semester,
                        :total_tuition_fee,
                        :program_language,
                        :admission_info,
                        :cooperation_info,
                        :integration_info,
                        :active
                    )
                ");

                $stmt->execute([
                    ':program_code' =>
                    $programCode,

                    ':degree_level' =>
                    $degreeLevel,

                    ':curriculum_name' =>
                    $curriculumName,

                    ':major_name' =>
                    $majorName,

                    ':major_name_en' =>
                    $majorNameEn ?: null,

                    ':degree_name_th' =>
                    $degreeNameTh ?: null,

                    ':degree_abbr_th' =>
                    $degreeAbbrTh ?: null,

                    ':degree_name_en' =>
                    $degreeNameEn ?: null,

                    ':degree_abbr_en' =>
                    $degreeAbbrEn ?: null,

                    ':aliases' =>
                    $aliases ?: null,

                    ':curriculum_year' =>
                    $curriculumYear ?: null,

                    ':study_period' =>
                    $studyPeriod ?: null,

                    ':tuition_fee_per_semester' =>
                    $tuitionFeePerSemester ?: null,

                    ':total_tuition_fee' =>
                    $totalTuitionFee ?: null,

                    ':program_language' =>
                    $programLanguage ?: null,

                    ':admission_info' =>
                    $admissionInfo ?: null,

                    ':cooperation_info' =>
                    $cooperationInfo ?: null,

                    ':integration_info' =>
                    $integrationInfo ?: null,

                    ':active' =>
                    $active
                ]);

                $programId =
                    (int)$pdo->lastInsertId();
            }


            // =================================================
            // ลบข้อมูลลูกเดิม
            // =================================================

            // Requirements ต้องลบก่อน plans
            // เพราะมี plan_id อ้าง program_plans

            $stmt = $pdo->prepare("
                DELETE
                FROM program_admission_requirements
                WHERE program_id = :program_id
            ");

            $stmt->execute([
                ':program_id' =>
                $programId
            ]);


            $stmt = $pdo->prepare("
                DELETE
                FROM program_careers
                WHERE program_id = :program_id
            ");

            $stmt->execute([
                ':program_id' =>
                $programId
            ]);


            $stmt = $pdo->prepare("
                DELETE
                FROM program_objectives
                WHERE program_id = :program_id
            ");

            $stmt->execute([
                ':program_id' =>
                $programId
            ]);


            // program_plan_components
            // จะถูกลบด้วย ON DELETE CASCADE

            $stmt = $pdo->prepare("
                DELETE
                FROM program_plans
                WHERE program_id = :program_id
            ");

            $stmt->execute([
                ':program_id' =>
                $programId
            ]);


            // =================================================
            // Plans + Components
            // =================================================

            // =================================================
            // รับ Plans + Components
            // ใช้ JSON payload เป็นหลัก เพื่อป้องกันปัญหา
            // nested input / max_input_vars / browser parsing
            // =================================================

            $postedPlans = [];

            $plansPayload =
                cleanString(
                    $_POST['plans_payload'] ?? ''
                );

            if ($plansPayload !== '') {

                $decodedPlans =
                    json_decode(
                        $plansPayload,
                        true
                    );

                if (
                    json_last_error() !== JSON_ERROR_NONE
                ) {
                    throw new Exception(
                        'ข้อมูลแผน/โครงสร้างหน่วยกิตไม่สมบูรณ์: ' .
                            json_last_error_msg()
                    );
                }

                if (!is_array($decodedPlans)) {
                    throw new Exception(
                        'รูปแบบข้อมูลแผน/โครงสร้างหน่วยกิตไม่ถูกต้อง'
                    );
                }

                $postedPlans = $decodedPlans;
            } else {

                // fallback สำหรับกรณี JavaScript ไม่ทำงาน
                $postedPlans =
                    $_POST['plans'] ?? [];
            }

            $planMap = [];


            foreach (
                $postedPlans as
                $planKey => $planData
            ) {

                $planCode =
                    cleanString(
                        $planData['plan_code'] ?? ''
                    );

                $planName =
                    cleanString(
                        $planData['plan_name'] ?? ''
                    );

                $totalCreditsRaw =
                    cleanString(
                        $planData['total_credits'] ?? ''
                    );

                $description =
                    cleanString(
                        $planData['description'] ?? ''
                    );

                $components =
                    $planData['components'] ?? [];


                $hasPlanData =
                    $planCode !== '' ||
                    $planName !== '' ||
                    $totalCreditsRaw !== '' ||
                    $description !== '' ||
                    !empty($components);


                if (!$hasPlanData) {
                    continue;
                }


                // ---------------------------------------------
                // Insert Plan
                // ---------------------------------------------

                $stmt = $pdo->prepare("
                    INSERT INTO program_plans
                    (
                        program_id,
                        plan_code,
                        plan_name,
                        total_credits,
                        description,
                        sort_order
                    )
                    VALUES
                    (
                        :program_id,
                        :plan_code,
                        :plan_name,
                        :total_credits,
                        :description,
                        :sort_order
                    )
                ");

                $stmt->execute([
                    ':program_id' =>
                    $programId,

                    ':plan_code' =>
                    $planCode ?: null,

                    ':plan_name' =>
                    $planName ?: null,

                    ':total_credits' =>
                    $totalCreditsRaw !== ''
                        ? (int)$totalCreditsRaw
                        : null,

                    ':description' =>
                    $description ?: null,

                    ':sort_order' =>
                    count($planMap) + 1
                ]);

                $newPlanId =
                    (int)$pdo->lastInsertId();

                $planMap[(string)$planKey] = $newPlanId;


                // =============================================
                // เตรียม Component Rows
                // =============================================

                $componentRows = [];

                foreach (
                    $components as
                    $componentKey =>
                    $componentData
                ) {

                    $clientKey =
                        cleanString(
                            $componentData['client_key'] ??
                                $componentKey
                        );

                    $parentKey =
                        cleanString(
                            $componentData['parent_key'] ?? ''
                        );

                    $componentName =
                        cleanString(
                            $componentData['component_name'] ?? ''
                        );

                    $componentDescription =
                        cleanString(
                            $componentData['description'] ?? ''
                        );

                    $creditsRaw =
                        cleanString(
                            $componentData['credits'] ?? ''
                        );

                    $hoursRaw =
                        cleanString(
                            $componentData['hours'] ?? ''
                        );

                    $note =
                        cleanString(
                            $componentData['note'] ?? ''
                        );


                    $hasComponentData =
                        $componentName !== '' ||
                        $componentDescription !== '' ||
                        $creditsRaw !== '' ||
                        $hoursRaw !== '' ||
                        $note !== '';


                    if (!$hasComponentData) {
                        continue;
                    }


                    if ($componentName === '') {

                        throw new Exception(
                            "กรุณาระบุชื่อหัวข้อในโครงสร้างหน่วยกิตให้ครบ"
                        );
                    }


                    $componentRows[] = [
                        'client_key' =>
                        $clientKey,

                        'parent_key' =>
                        $parentKey,

                        'component_name' =>
                        $componentName,

                        'description' =>
                        $componentDescription,

                        'credits' =>
                        $creditsRaw,

                        'hours' =>
                        $hoursRaw,

                        'note' =>
                        $note
                    ];
                }


                // ---------------------------------------------
                // ตรวจ hierarchy ก่อน insert
                // ---------------------------------------------

                validateComponentHierarchy(
                    $componentRows
                );


                // =============================================
                // PASS 1
                // Insert component ทุกตัวโดย parent = NULL
                // =============================================

                $componentMap = [];
                $pendingParents = [];

                $componentOrder = 1;


                foreach (
                    $componentRows as
                    $componentData
                ) {

                    $stmt = $pdo->prepare("
                        INSERT INTO program_plan_components
                        (
                            plan_id,
                            parent_component_id,
                            component_name,
                            description,
                            credits,
                            hours,
                            note,
                            sort_order
                        )
                        VALUES
                        (
                            :plan_id,
                            NULL,
                            :component_name,
                            :description,
                            :credits,
                            :hours,
                            :note,
                            :sort_order
                        )
                    ");

                    $stmt->execute([
                        ':plan_id' =>
                        $newPlanId,

                        ':component_name' =>
                        $componentData['component_name'],

                        ':description' =>
                        $componentData['description'] !== ''
                            ? $componentData['description']
                            : null,

                        ':credits' =>
                        $componentData['credits'] !== ''
                            ? (float)$componentData['credits']
                            : null,

                        ':hours' =>
                        $componentData['hours'] !== ''
                            ? (int)$componentData['hours']
                            : null,

                        ':note' =>
                        $componentData['note'] !== ''
                            ? $componentData['note']
                            : null,

                        ':sort_order' =>
                        $componentOrder++
                    ]);


                    $newComponentId =
                        (int)$pdo->lastInsertId();


                    $componentMap[$componentData['client_key']] = $newComponentId;


                    if (
                        $componentData['parent_key'] !== ''
                    ) {

                        $pendingParents[] = [
                            'component_id' =>
                            $newComponentId,

                            'parent_key' =>
                            $componentData['parent_key']
                        ];
                    }
                }


                // =============================================
                // PASS 2
                // Update parent_component_id
                // =============================================

                foreach (
                    $pendingParents as $item
                ) {

                    $parentKey =
                        $item['parent_key'];


                    if (
                        !isset(
                            $componentMap[$parentKey]
                        )
                    ) {
                        continue;
                    }


                    $parentId =
                        (int)$componentMap[$parentKey];


                    $componentId =
                        (int)$item['component_id'];


                    if (
                        $parentId ===
                        $componentId
                    ) {
                        continue;
                    }


                    $stmt = $pdo->prepare("
                        UPDATE
                            program_plan_components
                        SET
                            parent_component_id =
                                :parent_id
                        WHERE
                            id = :component_id
                        AND
                            plan_id = :plan_id
                    ");

                    $stmt->execute([
                        ':parent_id' =>
                        $parentId,

                        ':component_id' =>
                        $componentId,

                        ':plan_id' =>
                        $newPlanId
                    ]);
                }
            }


            // =================================================
            // Requirements
            // =================================================

            $postedRequirements =
                $_POST['requirements'] ?? [];

            $requirementOrder = 1;


            foreach (
                $postedRequirements as
                $requirementData
            ) {

                $text =
                    cleanString(
                        $requirementData['requirement_text'] ?? ''
                    );

                $planKey =
                    cleanString(
                        $requirementData['plan_key'] ?? ''
                    );


                if ($text === '') {
                    continue;
                }


                $planId = null;


                if (
                    $planKey !== '' &&
                    isset(
                        $planMap[$planKey]
                    )
                ) {

                    $planId =
                        $planMap[$planKey];
                }


                $stmt = $pdo->prepare("
                    INSERT INTO
                        program_admission_requirements
                    (
                        program_id,
                        plan_id,
                        requirement_text,
                        sort_order
                    )
                    VALUES
                    (
                        :program_id,
                        :plan_id,
                        :requirement_text,
                        :sort_order
                    )
                ");

                $stmt->execute([
                    ':program_id' =>
                    $programId,

                    ':plan_id' =>
                    $planId,

                    ':requirement_text' =>
                    $text,

                    ':sort_order' =>
                    $requirementOrder++
                ]);
            }


            // =================================================
            // Careers
            // =================================================

            $careerNames =
                $_POST['career_name'] ?? [];

            $careerOrder = 1;


            foreach (
                $careerNames as $career
            ) {

                $career =
                    cleanString($career);


                if ($career === '') {
                    continue;
                }


                $stmt = $pdo->prepare("
                    INSERT INTO program_careers
                    (
                        program_id,
                        career_name,
                        sort_order
                    )
                    VALUES
                    (
                        :program_id,
                        :career_name,
                        :sort_order
                    )
                ");

                $stmt->execute([
                    ':program_id' =>
                    $programId,

                    ':career_name' =>
                    $career,

                    ':sort_order' =>
                    $careerOrder++
                ]);
            }


            // =================================================
            // Objectives
            // =================================================

            $objectiveTexts =
                $_POST['objective_text'] ?? [];

            $objectiveOrder = 1;


            foreach (
                $objectiveTexts as
                $objective
            ) {

                $objective =
                    cleanString(
                        $objective
                    );


                if ($objective === '') {
                    continue;
                }


                $stmt = $pdo->prepare("
                    INSERT INTO program_objectives
                    (
                        program_id,
                        objective_text,
                        sort_order
                    )
                    VALUES
                    (
                        :program_id,
                        :objective_text,
                        :sort_order
                    )
                ");

                $stmt->execute([
                    ':program_id' =>
                    $programId,

                    ':objective_text' =>
                    $objective,

                    ':sort_order' =>
                    $objectiveOrder++
                ]);
            }


            // =================================================
            // บันทึกกิจกรรมล่าสุด
            // =================================================

            logAdminActivity(
                $pdo,
                'programs',
                $postedId ? 'update' : 'create',
                $majorName,
                ($postedId ? 'แก้ไข' : 'เพิ่ม') .
                    'ข้อมูลหลักสูตร ' . $curriculumName .
                    ($programCode !== '' ? ' (' . $programCode . ')' : '')
            );


            // =================================================
            // Commit
            // =================================================

            $pdo->commit();


            header(
                "Location: index.php?success=" .
                    (
                        $postedId
                        ? 'edit'
                        : 'create'
                    )
            );

            exit;
        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error =
                "ไม่สามารถบันทึกข้อมูลได้: " .
                $e->getMessage();
        }
    }
}


// =====================================================
// เตรียม JSON สำหรับ JavaScript
// =====================================================

$planIdToKey = [];


foreach (
    $plans as $index => &$plan
) {

    $key =
        'p' . $index;

    $plan['client_key'] = $key;

    $planIdToKey[(int)$plan['id']] = $key;
}

unset($plan);


foreach (
    $requirements as &$requirement
) {

    $planId =
        (int)(
            $requirement['plan_id'] ?? 0
        );

    $requirement['plan_key'] =
        (
            $planId > 0 &&
            isset(
                $planIdToKey[$planId]
            )
        )
        ? $planIdToKey[$planId]
        : '';
}

unset($requirement);


$jsonFlags =
    JSON_UNESCAPED_UNICODE |
    JSON_HEX_TAG |
    JSON_HEX_AMP |
    JSON_HEX_APOS |
    JSON_HEX_QUOT;


$plansJson =
    json_encode(
        $plans,
        $jsonFlags
    );

$requirementsJson =
    json_encode(
        $requirements,
        $jsonFlags
    );

$careersJson =
    json_encode(
        $careers,
        $jsonFlags
    );

$objectivesJson =
    json_encode(
        $objectives,
        $jsonFlags
    );

?>
<!doctype html>
<html lang="th">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">

    <title>
        <?= $isEdit
            ? 'แก้ไขหลักสูตร'
            : 'เพิ่มหลักสูตร'
        ?>
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- CSS กลางของระบบ Admin -->
    <link
        rel="stylesheet"
        href="../assets/admin.css?v=<?= filemtime(__DIR__ . '/../assets/admin.css') ?>">

    <!-- CSS เฉพาะหน้าหลักสูตร -->
    <link
        rel="stylesheet"
        href="assets/programs.css?v=<?= filemtime(__DIR__ . '/assets/programs.css') ?>">

</head>

<body>

    <div class="admin-layout">
        <?php
        $activeMenu = 'programs';
        $basePath   = '../';

        include __DIR__ . '/../includes/sidebar.php';
        ?>

        <div class="main-shell">
            <header class="topbar">
                <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="เปิดเมนู">
                    <i class="bi bi-list"></i>
                </button>

                <div class="topbar-title">
                    <span class="topbar-kicker">MBS • MAHASARAKHAM UNIVERSITY</span>
                    <strong>หลักสูตรและสาขาวิชา</strong>
                </div>

                <a href="javascript:history.back()" class="header-back-btn">
                    <i class="bi bi-arrow-left"></i>
                    <span>ย้อนกลับ</span>
                </a>
            </header>


            <main class="content-area">
                <section class="page-hero">
                    <div>
                        <span class="hero-badge"><span></span> PROGRAM EDITOR</span>
                        <h1><?= $isEdit ? 'แก้ไขข้อมูลหลักสูตร' : 'เพิ่มข้อมูลหลักสูตร' ?></h1>
                        <p>กรอกข้อมูลหลักสูตรและรายละเอียดที่เกี่ยวข้องให้ครบถ้วน</p>
                    </div>
                    <div class="hero-decoration">MBS</div>
                </section>
                <div class="page-section">


                    <div class="editor-toolbar">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i>
                            กลับหน้าหลักสูตร
                        </a>

                        <?php if ($isEdit): ?>
                            <a
                                href="course_descriptions.php?program_id=<?= (int)$program['id'] ?>"
                                class="btn btn-outline-primary">
                                <i class="bi bi-journal-text"></i>
                                จัดการคำอธิบายรายวิชา
                            </a>
                        <?php endif; ?>

                        <span><?= $isEdit ? 'กำลังแก้ไขข้อมูลเดิม' : 'กำลังสร้างหลักสูตรใหม่' ?></span>
                    </div>

                    <?php if ($error): ?>

                        <div class="alert alert-danger">

                            <?= htmlspecialchars(
                                $error
                            ) ?>

                        </div>

                    <?php endif; ?>


                    <form
                        method="post"
                        id="programForm">

                        <!-- สำรองข้อมูลแผนและโครงสร้างหน่วยกิตเป็น JSON
             เพื่อป้องกัน PHP ตัด/อ่าน nested input ไม่ครบ -->
                        <input
                            type="hidden"
                            name="plans_payload"
                            id="plansPayload"
                            value="">


                        <?php if ($isEdit): ?>

                            <input
                                type="hidden"
                                name="id"
                                value="<?= (int)$program['id'] ?>">

                        <?php endif; ?>


                        <!-- ================================================= -->
                        <!-- 1. ข้อมูลทั่วไป -->
                        <!-- ================================================= -->

                        <div class="card border-0 shadow-sm mb-4">

                            <div class="card-body p-4">

                                <h2 class="h5 fw-bold mb-4">
                                    1. ข้อมูลทั่วไป
                                </h2>


                                <div class="row g-3">


                                    <div class="col-md-4">

                                        <label class="form-label fw-semibold">
                                            รหัสหลักสูตร <span class="required-star">*</span>
                                        </label>

                                        <input
                                            type="text"
                                            name="program_code"
                                            class="form-control"
                                            value="<?= htmlspecialchars(
                                                        $program['program_code']
                                                    ) ?>"
                                            required>

                                    </div>


                                    <div class="col-md-4">

                                        <label class="form-label fw-semibold">
                                            ระดับการศึกษา <span class="required-star">*</span>
                                        </label>

                                        <select
                                            name="degree_level"
                                            class="form-select"
                                            required>

                                            <option
                                                value="bachelor"
                                                <?= $program['degree_level'] === 'bachelor'
                                                    ? 'selected'
                                                    : ''
                                                ?>>
                                                ปริญญาตรี
                                            </option>

                                            <option
                                                value="master"
                                                <?= $program['degree_level'] === 'master'
                                                    ? 'selected'
                                                    : ''
                                                ?>>
                                                ปริญญาโท
                                            </option>

                                            <option
                                                value="doctoral"
                                                <?= $program['degree_level'] === 'doctoral'
                                                    ? 'selected'
                                                    : ''
                                                ?>>
                                                ปริญญาเอก
                                            </option>

                                        </select>

                                    </div>


                                    <div class="col-md-4">

                                        <label class="form-label">
                                            ปีหลักสูตร
                                        </label>

                                        <input
                                            type="text"
                                            name="curriculum_year"
                                            class="form-control"
                                            value="<?= htmlspecialchars(
                                                        $program['curriculum_year']
                                                    ) ?>"
                                            placeholder="เช่น 2566">

                                    </div>


                                    <div class="col-md-4">

                                        <label class="form-label">
                                            ปีหลักสูตร
                                        </label>

                                        <input
                                            type="text"
                                            name="curriculum_year"
                                            class="form-control"
                                            value="<?= htmlspecialchars(
                                                        $program['curriculum_year']
                                                    ) ?>"
                                            placeholder="เช่น 2566">

                                    </div>


                                    <div class="col-12">

                                        <label class="form-label fw-semibold">
                                            ชื่อหลักสูตร <span class="required-star">*</span>
                                        </label>

                                        <div class="col-12">

                                            <label class="form-label fw-semibold">
                                                ชื่อหลักสูตร <span class="required-star">*</span>
                                            </label>

                                            <input
                                                type="text"
                                                name="curriculum_name"
                                                class="form-control"
                                                value="<?= htmlspecialchars(
                                                            $program['curriculum_name']
                                                        ) ?>"
                                                required>

                                        </div>




                                        <div class="col-md-6">

                                            <label class="form-label fw-semibold">
                                                ชื่อสาขาวิชา <span class="required-star">*</span>
                                            </label>

                                            <input
                                                type="text"
                                                name="major_name"
                                                class="form-control"
                                                value="<?= htmlspecialchars(
                                                            $program['major_name']
                                                        ) ?>"
                                                required>

                                        </div>


                                        <div class="col-md-6">

                                            <label class="form-label">
                                                ชื่อสาขาวิชาภาษาอังกฤษ
                                            </label>

                                            <input
                                                type="text"
                                                name="major_name_en"
                                                class="form-control"
                                                value="<?= htmlspecialchars(
                                                            $program['major_name_en']
                                                        ) ?>">

                                        </div>


                                        <div class="col-md-6">

                                            <label class="form-label">
                                                ชื่อปริญญาภาษาไทย
                                            </label>

                                            <input
                                                type="text"
                                                name="degree_name_th"
                                                class="form-control"
                                                value="<?= htmlspecialchars(
                                                            $program['degree_name_th']
                                                        ) ?>">

                                        </div>


                                        <div class="col-md-6">

                                            <label class="form-label">
                                                อักษรย่อปริญญาภาษาไทย
                                            </label>

                                            <input
                                                type="text"
                                                name="degree_abbr_th"
                                                class="form-control"
                                                value="<?= htmlspecialchars(
                                                            $program['degree_abbr_th']
                                                        ) ?>">

                                        </div>


                                        <div class="col-md-6">

                                            <label class="form-label">
                                                ชื่อปริญญาภาษาอังกฤษ
                                            </label>

                                            <input
                                                type="text"
                                                name="degree_name_en"
                                                class="form-control"
                                                value="<?= htmlspecialchars(
                                                            $program['degree_name_en']
                                                        ) ?>">

                                        </div>


                                        <div class="col-md-6">

                                            <label class="form-label">
                                                อักษรย่อปริญญาภาษาอังกฤษ
                                            </label>

                                            <input
                                                type="text"
                                                name="degree_abbr_en"
                                                class="form-control"
                                                value="<?= htmlspecialchars(
                                                            $program['degree_abbr_en']
                                                        ) ?>">

                                        </div>


                                        <div class="col-12">

                                            <label class="form-label">
                                                Alias / คำค้นอื่น
                                            </label>

                                            <textarea
                                                name="aliases"
                                                class="form-control"
                                                rows="2"><?= htmlspecialchars(
                                                                $program['aliases']
                                                            ) ?></textarea>

                                            <div class="form-text">
                                                เช่น BC, คอมธุรกิจ, Business Computer
                                            </div>

                                        </div>


                                        <div class="col-12">

                                            <label class="form-label">
                                                ภาษาที่ใช้ในการเรียนการสอน
                                            </label>

                                            <textarea
                                                name="program_language"
                                                class="form-control"
                                                rows="2"><?= htmlspecialchars(
                                                                $program['program_language']
                                                            ) ?></textarea>

                                        </div>


                                        <div class="col-12">

                                            <label class="form-label">
                                                ข้อมูลการรับเข้า
                                            </label>

                                            <textarea
                                                name="admission_info"
                                                class="form-control"
                                                rows="3"><?= htmlspecialchars(
                                                                $program['admission_info']
                                                            ) ?></textarea>

                                        </div>


                                        <div class="col-12">

                                            <label class="form-label">
                                                ความร่วมมือกับสถาบันอื่น
                                            </label>

                                            <textarea
                                                name="cooperation_info"
                                                class="form-control"
                                                rows="3"><?= htmlspecialchars(
                                                                $program['cooperation_info']
                                                            ) ?></textarea>

                                        </div>


                                        <div class="col-12">

                                            <label class="form-label">
                                                การบูรณาการกับหลักสูตรอื่น
                                            </label>

                                            <textarea
                                                name="integration_info"
                                                class="form-control"
                                                rows="3"><?= htmlspecialchars(
                                                                $program['integration_info']
                                                            ) ?></textarea>

                                        </div>


                                        <div class="col-12">

                                            <div class="form-check form-switch">

                                                <input
                                                    type="checkbox"
                                                    name="active"
                                                    class="form-check-input"
                                                    id="active"
                                                    <?= (int)$program['active'] === 1
                                                        ? 'checked'
                                                        : ''
                                                    ?>>

                                                <label
                                                    for="active"
                                                    class="form-check-label">
                                                    เปิดใช้งานหลักสูตร
                                                </label>

                                            </div>

                                        </div>


                                    </div>

                                </div>

                            </div>


                            <!-- ================================================= -->
                            <!-- 2. แผน / โครงสร้างหน่วยกิต -->
                            <!-- ================================================= -->

                            <div class="card border-0 shadow-sm mb-4">

                                <div class="card-body p-4">


                                    <div class="section-header">

                                        <div>

                                            <h2 class="h5 fw-bold mb-1">
                                                2. แผน / โครงสร้างหน่วยกิต
                                            </h2>

                                            <p class="text-secondary small mb-0">

                                                รองรับโครงสร้างหลายระดับ
                                                โดยเลือกได้ว่าหัวข้อใดอยู่ภายใต้หัวข้อใด

                                            </p>

                                        </div>


                                        <button
                                            type="button"
                                            class="btn btn-outline-primary btn-sm"
                                            onclick="addPlan()">
                                            + เพิ่มแผน
                                        </button>

                                    </div>


                                    <div id="plansContainer"></div>


                                </div>

                            </div>


                            <!-- ================================================= -->
                            <!-- 3. คุณสมบัติผู้สมัคร -->
                            <!-- ================================================= -->

                            <div class="card border-0 shadow-sm mb-4">

                                <div class="card-body p-4">


                                    <div class="section-header">

                                        <h2 class="h5 fw-bold mb-0">
                                            3. คุณสมบัติผู้สมัคร
                                        </h2>


                                        <button
                                            type="button"
                                            class="btn btn-outline-primary btn-sm"
                                            onclick="addRequirement()">
                                            + เพิ่มคุณสมบัติ
                                        </button>

                                    </div>


                                    <div id="requirementsContainer"></div>


                                </div>

                            </div>


                            <!-- ================================================= -->
                            <!-- 4. อาชีพ -->
                            <!-- ================================================= -->

                            <div class="card border-0 shadow-sm mb-4">

                                <div class="card-body p-4">


                                    <div class="section-header">

                                        <h2 class="h5 fw-bold mb-0">
                                            4. อาชีพหลังสำเร็จการศึกษา
                                        </h2>


                                        <button
                                            type="button"
                                            class="btn btn-outline-primary btn-sm"
                                            onclick="addCareer()">
                                            + เพิ่มอาชีพ
                                        </button>

                                    </div>


                                    <div id="careersContainer"></div>


                                </div>

                            </div>


                            <!-- ================================================= -->
                            <!-- 5. วัตถุประสงค์ -->
                            <!-- ================================================= -->

                            <div class="card border-0 shadow-sm mb-4">

                                <div class="card-body p-4">


                                    <div class="section-header">

                                        <h2 class="h5 fw-bold mb-0">
                                            5. วัตถุประสงค์หลักสูตร
                                        </h2>


                                        <button
                                            type="button"
                                            class="btn btn-outline-primary btn-sm"
                                            onclick="addObjective()">
                                            + เพิ่มวัตถุประสงค์
                                        </button>

                                    </div>


                                    <div id="objectivesContainer"></div>


                                </div>

                            </div>


                            <div class="d-flex justify-content-end gap-2 mb-5">

                                <a
                                    href="index.php"
                                    class="btn btn-outline-secondary px-4">
                                    ยกเลิก
                                </a>


                                <button
                                    type="submit"
                                    class="btn btn-primary px-5">
                                    บันทึกทั้งหมด
                                </button>

                            </div>


                    </form>


                </div>
            </main>

            <footer class="admin-footer">
                <div>
                    <strong>MBS UniWise Admin</strong>
                    <span>คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</span>
                </div>
                <span>Mahasarakham Business School</span>
            </footer>
        </div>
    </div>
    <script>
        const oldPlans =
            <?= $plansJson ?: '[]' ?>;

        const oldRequirements =
            <?= $requirementsJson ?: '[]' ?>;

        const oldCareers =
            <?= $careersJson ?: '[]' ?>;

        const oldObjectives =
            <?= $objectivesJson ?: '[]' ?>;


        let planCounter = 0;
        let requirementCounter = 0;
        let componentCounter = 0;


        // =====================================================
        // Escape
        // =====================================================

        function esc(value = '') {

            return String(value)

                .replaceAll(
                    '&',
                    '&amp;'
                )

                .replaceAll(
                    '<',
                    '&lt;'
                )

                .replaceAll(
                    '>',
                    '&gt;'
                )

                .replaceAll(
                    '"',
                    '&quot;'
                )

                .replaceAll(
                    "'",
                    '&#039;'
                );
        }


        // =====================================================
        // Keys
        // =====================================================

        function generatePlanKey() {

            return 'p' +
                (planCounter++);
        }


        function generateComponentKey() {

            return 'c_new_' +
                (componentCounter++);
        }


        // =====================================================
        // Plans
        // =====================================================

        function addPlan(data = {}) {

            const container =
                document.getElementById(
                    'plansContainer'
                );


            const planKey =
                data.client_key ||
                generatePlanKey();


            const match =
                String(planKey)
                .match(/^p(\d+)$/);


            if (match) {

                planCounter =
                    Math.max(
                        planCounter,
                        Number(match[1]) + 1
                    );
            }


            const div =
                document.createElement(
                    'div'
                );


            div.className =
                'dynamic-item plan-item';


            div.dataset.planKey =
                planKey;


            div.innerHTML = `

        <div class="dynamic-item-header">

            <div>

                <strong>
                    แผนการศึกษา
                </strong>

                <div class="small text-secondary">

                    สามารถสร้างโครงสร้างหัวข้อ
                    และหัวข้อย่อยได้อย่างอิสระ

                </div>

            </div>


            <button
                type="button"
                class="btn btn-outline-danger btn-sm"
                onclick="removePlan(this)"
            >
                ลบแผน
            </button>

        </div>


        <div class="row g-3">


            <div class="col-md-3">

                <label class="form-label">
                    รหัสแผน
                </label>

                <input
                    type="text"
                    name="plans[${planKey}][plan_code]"
                    class="form-control plan-code"
                    value="${esc(
                        data.plan_code ?? ''
                    )}"
                    placeholder="เช่น ปกติ, สหกิจ, ก1, ก2, 1.1, 2.1"
                    oninput="refreshRequirementPlans()"
                >

            </div>


            <div class="col-md-5">

                <label class="form-label">
                    ชื่อแผน
                </label>

                <input
                    type="text"
                    name="plans[${planKey}][plan_name]"
                    class="form-control plan-name"
                    value="${esc(
                        data.plan_name ?? ''
                    )}"
                    placeholder="เช่น โปรแกรมปกติ"
                    oninput="refreshRequirementPlans()"
                >

            </div>


            <div class="col-md-4">

                <label class="form-label">
                    หน่วยกิตรวม
                </label>

                <input
                    type="number"
                    min="0"
                    name="plans[${planKey}][total_credits]"
                    class="form-control"
                    value="${esc(
                        data.total_credits ?? ''
                    )}"
                >

            </div>


            <div class="col-12">

                <label class="form-label">
                    รายละเอียดแผน
                </label>

                <textarea
                    name="plans[${planKey}][description]"
                    class="form-control"
                    rows="2"
                >${esc(
                    data.description ?? ''
                )}</textarea>

            </div>


        </div>


        <hr class="my-4">


        <div
            class="
                d-flex
                flex-column
                flex-md-row
                justify-content-between
                align-items-md-center
                gap-2
                mb-3
            "
        >

            <div>

                <h3 class="h6 fw-bold mb-1">
                    โครงสร้างหน่วยกิต
                </h3>

                <div class="small text-secondary">

                    หัวข้อสามารถมีหัวข้อย่อยได้หลายระดับ
                    เช่น หมวดวิชาเฉพาะ → กลุ่มวิชาเอก → วิชาเอกบังคับ

                </div>

            </div>


            <button
                type="button"
                class="btn btn-outline-secondary btn-sm"
                onclick="addComponent('${planKey}')"
            >
                + เพิ่มหัวข้อ
            </button>

        </div>


        <div
            class="components-container"
            data-components-for="${planKey}"
        ></div>

    `;


            container.appendChild(div);


            const components =
                Array.isArray(
                    data.components
                ) ?
                data.components : [];


            if (components.length) {

                components.forEach(
                    component =>
                    addComponent(
                        planKey,
                        component
                    )
                );

            } else {

                addComponent(planKey);
            }


            refreshParentOptions(
                planKey
            );

            refreshRequirementPlans();
        }


        // =====================================================
        // Remove Plan
        // =====================================================

        function removePlan(button) {

            const planItem =
                button.closest(
                    '.plan-item'
                );


            if (!planItem) {
                return;
            }


            planItem.remove();

            refreshRequirementPlans();
        }


        // =====================================================
        // Components
        // =====================================================

        function addComponent(
            planKey,
            data = {}
        ) {

            const container =
                document.querySelector(
                    `[data-components-for="${planKey}"]`
                );


            if (!container) {
                return;
            }


            const componentKey =
                data.client_key ||
                generateComponentKey();


            const div =
                document.createElement(
                    'div'
                );


            div.className =
                'component-item';


            div.dataset.componentKey =
                componentKey;


            div.dataset.parentKey =
                data.parent_key ?? '';


            div.innerHTML = `

        <input
            type="hidden"
            name="plans[${planKey}][components][${componentKey}][client_key]"
            value="${esc(
                componentKey
            )}"
        >


        <div
            class="
                border
                rounded
                p-3
                mb-3
                bg-light
            "
        >


            <div class="row g-3">


                <div class="col-md-5">

                    <label class="form-label small fw-semibold">
                        ชื่อหัวข้อ / หมวด
                    </label>

                    <input
                        type="text"
                        name="plans[${planKey}][components][${componentKey}][component_name]"
                        class="
                            form-control
                            component-name
                        "
                        value="${esc(
                            data.component_name ?? ''
                        )}"
                        placeholder="เช่น หมวดวิชาเฉพาะ"
                        oninput="
                            refreshParentOptions(
                                '${planKey}'
                            )
                        "
                    >

                </div>


                <div class="col-md-4">

                    <label class="form-label small fw-semibold">
                        อยู่ภายใต้
                    </label>

                    <select
                        name="plans[${planKey}][components][${componentKey}][parent_key]"
                        class="
                            form-select
                            parent-component-select
                        "
                        onchange="
                            this.closest('.component-item')
                                .dataset.parentKey =
                                this.value
                        "
                    >

                        <option value="">
                            ไม่มี / เป็นหัวข้อหลัก
                        </option>

                    </select>

                </div>


                <div class="col-md-2">

                    <label class="form-label small">
                        หน่วยกิต
                    </label>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="plans[${planKey}][components][${componentKey}][credits]"
                        class="form-control"
                        value="${esc(
                            data.credits ?? ''
                        )}"
                    >

                </div>


                <div class="col-md-1 d-grid">

                    <label class="form-label small">
                        &nbsp;
                    </label>

                    <button
                        type="button"
                        class="btn btn-outline-danger"
                        onclick="
                            removeComponent(
                                this,
                                '${planKey}'
                            )
                        "
                        title="ลบหัวข้อ"
                    >
                        ×
                    </button>

                </div>


                <div class="col-md-3">

                    <label class="form-label small">
                        ชั่วโมง
                    </label>

                    <input
                        type="number"
                        min="0"
                        name="plans[${planKey}][components][${componentKey}][hours]"
                        class="form-control"
                        value="${esc(
                            data.hours ?? ''
                        )}"
                    >

                </div>


                <div class="col-md-4">

                    <label class="form-label small">
                        หมายเหตุ
                    </label>

                    <input
                        type="text"
                        name="plans[${planKey}][components][${componentKey}][note]"
                        class="form-control"
                        value="${esc(
                            data.note ?? ''
                        )}"
                        placeholder="เช่น ไม่น้อยกว่า 30 หน่วยกิต"
                    >

                </div>


                <div class="col-md-5">

                    <label class="form-label small">
                        รายละเอียด
                    </label>

                    <textarea
                        name="plans[${planKey}][components][${componentKey}][description]"
                        class="form-control"
                        rows="2"
                        placeholder="รายละเอียดเพิ่มเติมของหัวข้อนี้"
                    >${esc(
                        data.description ?? ''
                    )}</textarea>

                </div>


            </div>


        </div>

    `;


            container.appendChild(
                div
            );


            refreshParentOptions(
                planKey
            );
        }


        // =====================================================
        // Remove Component
        // =====================================================

        function removeComponent(
            button,
            planKey
        ) {

            const item =
                button.closest(
                    '.component-item'
                );


            if (!item) {
                return;
            }


            const removedKey =
                item.dataset.componentKey;


            item.remove();


            // ถ้ามีลูกที่อ้าง parent ตัวที่ลบ
            // ให้กลับเป็นหัวข้อหลัก

            const container =
                document.querySelector(
                    `[data-components-for="${planKey}"]`
                );


            if (container) {

                container
                    .querySelectorAll(
                        '.component-item'
                    )
                    .forEach(child => {

                        if (
                            child.dataset.parentKey ===
                            removedKey
                        ) {

                            child.dataset.parentKey = '';
                        }
                    });
            }


            refreshParentOptions(
                planKey
            );
        }


        // =====================================================
        // Refresh Parent Select
        // =====================================================

        function refreshParentOptions(
            planKey
        ) {

            const container =
                document.querySelector(
                    `[data-components-for="${planKey}"]`
                );


            if (!container) {
                return;
            }


            const items = [
                ...container.querySelectorAll(
                    '.component-item'
                )
            ];


            items.forEach(
                currentItem => {

                    const select =
                        currentItem.querySelector(
                            '.parent-component-select'
                        );


                    if (!select) {
                        return;
                    }


                    const currentKey =
                        currentItem.dataset.componentKey;


                    const selected =
                        select.value ||
                        currentItem.dataset.parentKey ||
                        '';


                    let html = `
                <option value="">
                    ไม่มี / เป็นหัวข้อหลัก
                </option>
            `;


                    items.forEach(
                        item => {

                            const key =
                                item.dataset.componentKey;


                            // ห้ามเลือกตัวเอง
                            if (
                                key ===
                                currentKey
                            ) {
                                return;
                            }


                            const name =
                                item.querySelector(
                                    '.component-name'
                                )
                                ?.value
                                .trim() ||
                                'หัวข้อไม่มีชื่อ';


                            html += `
                        <option
                            value="${esc(key)}"
                        >
                            ${esc(name)}
                        </option>
                    `;
                        }
                    );


                    select.innerHTML =
                        html;


                    if (
                        selected !== '' &&
                        select.querySelector(
                            `option[value="${CSS.escape(
                        selected
                    )}"]`
                        )
                    ) {

                        select.value =
                            selected;

                    } else {

                        select.value = '';
                    }


                    currentItem.dataset.parentKey =
                        select.value;
                }
            );
        }


        // =====================================================
        // Requirements
        // =====================================================

        function addRequirement(
            data = {}
        ) {

            const container =
                document.getElementById(
                    'requirementsContainer'
                );


            const reqKey =
                'r' +
                (requirementCounter++);


            const div =
                document.createElement(
                    'div'
                );


            div.className =
                'dynamic-item requirement-item';


            div.dataset.planKey =
                data.plan_key ?? '';


            div.innerHTML = `

        <div class="dynamic-item-header">

            <strong>
                คุณสมบัติ
            </strong>


            <button
                type="button"
                class="btn btn-outline-danger btn-sm"
                onclick="
                    this.closest('.dynamic-item')
                        .remove()
                "
            >
                ลบรายการ
            </button>

        </div>


        <div class="row g-3">


            <div class="col-md-4">

                <label class="form-label">
                    ใช้กับแผน
                </label>

                <select
                    name="requirements[${reqKey}][plan_key]"
                    class="
                        form-select
                        requirement-plan
                    "
                >

                    <option value="">
                        ทุกแผน / ทั้งหลักสูตร
                    </option>

                </select>

            </div>


            <div class="col-md-8">

                <label class="form-label">
                    รายละเอียดคุณสมบัติ
                </label>

                <textarea
                    name="requirements[${reqKey}][requirement_text]"
                    class="form-control"
                    rows="3"
                >${esc(
                    data.requirement_text ?? ''
                )}</textarea>

            </div>


        </div>

    `;


            container.appendChild(
                div
            );


            refreshRequirementPlans();
        }


        // =====================================================
        // Refresh Requirement Plans
        // =====================================================

        function refreshRequirementPlans() {

            const planItems =
                document.querySelectorAll(
                    '.plan-item'
                );


            const requirements =
                document.querySelectorAll(
                    '.requirement-item'
                );


            requirements.forEach(
                req => {

                    const select =
                        req.querySelector(
                            '.requirement-plan'
                        );


                    let selectedKey =
                        select.value ||
                        req.dataset.planKey ||
                        '';


                    let html = `
                <option value="">
                    ทุกแผน / ทั้งหลักสูตร
                </option>
            `;


                    planItems.forEach(
                        (plan, index) => {

                            const key =
                                plan.dataset.planKey;


                            const code =
                                plan.querySelector(
                                    '.plan-code'
                                )
                                ?.value
                                .trim() || '';


                            const name =
                                plan.querySelector(
                                    '.plan-name'
                                )
                                ?.value
                                .trim() || '';


                            let label =
                                `${code} ${name}`.trim();


                            if (!label) {

                                label =
                                    `แผนที่ ${index + 1}`;
                            }


                            html += `
                        <option
                            value="${esc(key)}"
                        >
                            ${esc(label)}
                        </option>
                    `;
                        }
                    );


                    select.innerHTML =
                        html;


                    if (
                        selectedKey !== '' &&
                        select.querySelector(
                            `option[value="${CSS.escape(
                        selectedKey
                    )}"]`
                        )
                    ) {

                        select.value =
                            selectedKey;

                    } else {

                        select.value = '';
                    }


                    req.dataset.planKey =
                        select.value;
                }
            );
        }


        // =====================================================
        // Careers
        // =====================================================

        function addCareer(
            data = {}
        ) {

            const container =
                document.getElementById(
                    'careersContainer'
                );


            const div =
                document.createElement(
                    'div'
                );


            div.className =
                'dynamic-item';


            div.innerHTML = `

        <div class="input-group">

            <input
                type="text"
                name="career_name[]"
                class="form-control"
                value="${esc(
                    data.career_name ?? ''
                )}"
                placeholder="ระบุอาชีพ"
            >


            <button
                type="button"
                class="btn btn-outline-danger"
                onclick="
                    this.closest('.dynamic-item')
                        .remove()
                "
            >
                ลบ
            </button>

        </div>

    `;


            container.appendChild(
                div
            );
        }


        // =====================================================
        // Objectives
        // =====================================================

        function addObjective(
            data = {}
        ) {

            const container =
                document.getElementById(
                    'objectivesContainer'
                );


            const div =
                document.createElement(
                    'div'
                );


            div.className =
                'dynamic-item';


            div.innerHTML = `

        <div class="dynamic-item-header">

            <strong>
                วัตถุประสงค์
            </strong>


            <button
                type="button"
                class="btn btn-outline-danger btn-sm"
                onclick="
                    this.closest('.dynamic-item')
                        .remove()
                "
            >
                ลบรายการ
            </button>

        </div>


        <textarea
            name="objective_text[]"
            class="form-control"
            rows="3"
            placeholder="ระบุวัตถุประสงค์"
        >${esc(
            data.objective_text ?? ''
        )}</textarea>

    `;


            container.appendChild(
                div
            );
        }



        // =====================================================
        // Serialize Plans + Components before submit
        // =====================================================

        function collectPlansPayload() {

            const result = {};

            document
                .querySelectorAll('.plan-item')
                .forEach(planItem => {

                    const planKey =
                        planItem.dataset.planKey;

                    if (!planKey) {
                        return;
                    }

                    const planCode =
                        planItem.querySelector('.plan-code')
                        ?.value ?? '';

                    const planName =
                        planItem.querySelector('.plan-name')
                        ?.value ?? '';

                    const totalCredits =
                        planItem.querySelector(
                            `[name="plans[${planKey}][total_credits]"]`
                        )?.value ?? '';

                    const description =
                        planItem.querySelector(
                            `[name="plans[${planKey}][description]"]`
                        )?.value ?? '';

                    const components = {};

                    planItem
                        .querySelectorAll('.component-item')
                        .forEach(componentItem => {

                            const componentKey =
                                componentItem.dataset.componentKey;

                            if (!componentKey) {
                                return;
                            }

                            const getValue = (field) => {

                                const el =
                                    componentItem.querySelector(
                                        `[name="plans[${planKey}][components][${componentKey}][${field}]"]`
                                    );

                                return el ? el.value : '';
                            };

                            const parentSelect =
                                componentItem.querySelector(
                                    '.parent-component-select'
                                );

                            components[componentKey] = {
                                client_key: componentKey,
                                parent_key: parentSelect ?
                                    parentSelect.value : (
                                        componentItem.dataset.parentKey ||
                                        ''
                                    ),
                                component_name: getValue('component_name'),
                                description: getValue('description'),
                                credits: getValue('credits'),
                                hours: getValue('hours'),
                                note: getValue('note')
                            };
                        });

                    result[planKey] = {
                        plan_code: planCode,
                        plan_name: planName,
                        total_credits: totalCredits,
                        description: description,
                        components: components
                    };
                });

            return result;
        }


        const programForm =
            document.getElementById('programForm');


        if (programForm) {

            programForm.addEventListener(
                'submit',
                function() {

                    const payloadInput =
                        document.getElementById(
                            'plansPayload'
                        );

                    if (!payloadInput) {
                        return;
                    }

                    payloadInput.value =
                        JSON.stringify(
                            collectPlansPayload()
                        );
                }
            );
        }


        // =====================================================
        // Initial Data
        // =====================================================

        if (oldPlans.length) {

            oldPlans.forEach(
                addPlan
            );

        } else {

            addPlan();
        }


        if (oldRequirements.length) {

            oldRequirements.forEach(
                addRequirement
            );

        } else {

            addRequirement();
        }


        if (oldCareers.length) {

            oldCareers.forEach(
                addCareer
            );

        } else {

            addCareer();
        }


        if (oldObjectives.length) {

            oldObjectives.forEach(
                addObjective
            );

        } else {

            addObjective();
        }


        refreshRequirementPlans();
    </script>



    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebarToggle = document.getElementById('sidebarToggle');

            function openSidebar() {
                if (sidebar) {
                    sidebar.classList.add('show');
                }

                if (sidebarOverlay) {
                    sidebarOverlay.classList.add('show');
                }
            }

            function closeSidebar() {
                if (sidebar) {
                    sidebar.classList.remove('show');
                }

                if (sidebarOverlay) {
                    sidebarOverlay.classList.remove('show');
                }
            }

            /* โหลดสถานะ Sidebar เดิมบน Desktop */
            if (
                localStorage.getItem('mbsSidebarCollapsed') === '1' &&
                window.innerWidth >= 992
            ) {
                document.body.classList.add('sidebar-collapsed');
            }

            /* ย่อ / ขยาย Sidebar บน Desktop */
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {

                    if (window.innerWidth < 992) {
                        return;
                    }

                    document.body.classList.toggle('sidebar-collapsed');

                    const collapsed =
                        document.body.classList.contains('sidebar-collapsed');

                    localStorage.setItem(
                        'mbsSidebarCollapsed',
                        collapsed ? '1' : '0'
                    );
                });
            }

            /* เปิด Sidebar บนมือถือ */
            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', openSidebar);
            }

            /* แตะ Overlay เพื่อปิด Sidebar */
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', closeSidebar);
            }

            /* ปรับสถานะเมื่อเปลี่ยนขนาดหน้าจอ */
            window.addEventListener('resize', function() {

                if (window.innerWidth >= 992) {

                    closeSidebar();

                    if (
                        localStorage.getItem('mbsSidebarCollapsed') === '1'
                    ) {
                        document.body.classList.add('sidebar-collapsed');
                    } else {
                        document.body.classList.remove('sidebar-collapsed');
                    }

                } else {

                    document.body.classList.remove('sidebar-collapsed');
                }
            });

        });
    </script>

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


</body>

</html>