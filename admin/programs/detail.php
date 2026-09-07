<?php

require_once "../../api/db.php";


// =========================================================
// รับ ID
// =========================================================

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id) {
    exit("ID หลักสูตรไม่ถูกต้อง");
}


// =========================================================
// Helper
// =========================================================

function degreeName(string $degree): string
{
    return match ($degree) {
        'bachelor' => 'ปริญญาตรี',
        'master' => 'ปริญญาโท',
        'doctoral' => 'ปริญญาเอก',
        default => '-'
    };
}


function showValue($value): string
{
    if (
        $value === null ||
        trim((string)$value) === ''
    ) {
        return '-';
    }

    return nl2br(
        htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        )
    );
}


function showNumber($value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    $number = (float)$value;

    if (floor($number) == $number) {
        return (string)(int)$number;
    }

    return rtrim(
        rtrim(
            number_format(
                $number,
                2,
                '.',
                ''
            ),
            '0'
        ),
        '.'
    );
}


// =========================================================
// แปลง Component แบบ Flat → Tree
// =========================================================

function buildComponentTree(array $components): array
{
    if (empty($components)) {
        return [];
    }

    $map = [];

    foreach ($components as $component) {

        $componentId =
            (int)$component['id'];

        $component['id'] =
            $componentId;

        $component['parent_component_id'] =
            !empty(
                $component['parent_component_id']
            )
                ? (int)$component['parent_component_id']
                : null;

        $component['children'] = [];

        $map[$componentId] =
            $component;
    }


    // -----------------------------------------------------
    // เชื่อมลูกเข้ากับแม่
    // -----------------------------------------------------

    foreach ($map as $componentId => &$component) {

        $parentId =
            $component['parent_component_id'];

        if (
            $parentId !== null &&
            isset($map[$parentId]) &&
            $parentId !== $componentId
        ) {

            $map[$parentId]['children'][] =
                &$component;
        }
    }

    unset($component);


    // -----------------------------------------------------
    // เอาเฉพาะ root
    // -----------------------------------------------------

    $tree = [];

    foreach ($map as $componentId => &$component) {

        $parentId =
            $component['parent_component_id'];

        if (
            $parentId === null ||
            !isset($map[$parentId]) ||
            $parentId === $componentId
        ) {

            $tree[] =
                &$component;
        }
    }

    unset($component);


    // -----------------------------------------------------
    // เรียง sort_order ทุกระดับ
    // -----------------------------------------------------

    $sortTree =
        function (&$nodes) use (&$sortTree) {

            usort(
                $nodes,
                function ($a, $b) {

                    $sortA =
                        (int)(
                            $a['sort_order']
                            ?? 0
                        );

                    $sortB =
                        (int)(
                            $b['sort_order']
                            ?? 0
                        );

                    if ($sortA === $sortB) {

                        return
                            (int)$a['id']
                            <=>
                            (int)$b['id'];
                    }

                    return
                        $sortA
                        <=>
                        $sortB;
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
// Render Component แบบ Recursive
// =========================================================

function renderComponentRows(
    array $components,
    int $level = 0
): void {

    foreach ($components as $component) {

        $paddingLeft =
            12 + ($level * 28);

        $hasChildren =
            !empty(
                $component['children']
            );

        ?>

        <tr>

            <td
                style="
                    padding-left:
                    <?= $paddingLeft ?>px;
                "
            >

                <?php if ($level > 0): ?>

                    <span
                        class="text-secondary me-1"
                    >
                        ↳
                    </span>

                <?php endif; ?>


                <span
                    class="<?= $hasChildren
                        ? 'fw-semibold'
                        : ''
                    ?>"
                >

                    <?= htmlspecialchars(
                        $component[
                            'component_name'
                        ] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </span>


                <?php if (
                    !empty(
                        $component['description']
                    )
                ): ?>

                    <div
                        class="
                            small
                            text-secondary
                            mt-1
                        "
                    >

                        <?= showValue(
                            $component[
                                'description'
                            ]
                        ) ?>

                    </div>

                <?php endif; ?>

            </td>


            <td>

                <?= showNumber(
                    $component[
                        'credits'
                    ] ?? null
                ) ?>

            </td>


            <td>

                <?= showNumber(
                    $component[
                        'hours'
                    ] ?? null
                ) ?>

            </td>


            <td>

                <?= showValue(
                    $component[
                        'note'
                    ] ?? null
                ) ?>

            </td>

        </tr>

        <?php

        if ($hasChildren) {

            renderComponentRows(
                $component['children'],
                $level + 1
            );
        }
    }
}


// =========================================================
// Program
// =========================================================

$stmt = $pdo->prepare("
    SELECT *
    FROM academic_programs
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    ':id' => $id
]);

$program =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );

if (!$program) {
    exit("ไม่พบข้อมูลหลักสูตร");
}


// =========================================================
// Plans
// =========================================================

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

$plans =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


// =========================================================
// Components ของแต่ละ Plan
// =========================================================

foreach ($plans as &$plan) {

    $stmt = $pdo->prepare("
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
        FROM program_plan_components
        WHERE plan_id = :plan_id
        ORDER BY
            sort_order ASC,
            id ASC
    ");

    $stmt->execute([
        ':plan_id' =>
            $plan['id']
    ]);

    $flatComponents =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    $plan['components'] =
        buildComponentTree(
            $flatComponents
        );
}

unset($plan);


// =========================================================
// Requirements
// =========================================================

$stmt = $pdo->prepare("
    SELECT
        r.*,
        p.plan_code,
        p.plan_name
    FROM program_admission_requirements r
    LEFT JOIN program_plans p
        ON r.plan_id = p.id
    WHERE r.program_id = :program_id
    ORDER BY
        r.sort_order ASC,
        r.id ASC
");

$stmt->execute([
    ':program_id' => $id
]);

$requirements =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


// =========================================================
// Careers
// =========================================================

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

$careers =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


// =========================================================
// Objectives
// =========================================================

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

$objectives =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


// =========================================================
// Course Descriptions
// =========================================================

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM course_descriptions
    WHERE program_id = :program_id
");

$stmt->execute([
    ':program_id' => $id
]);

$courseCount =
    (int)$stmt->fetchColumn();


$stmt = $pdo->prepare("
    SELECT
        id,
        course_code,
        course_name_th,
        course_name_en,
        credits,
        course_group,
        course_type,
        credit_counted,
        assessment_type
    FROM course_descriptions
    WHERE program_id = :program_id
    ORDER BY
        sort_order ASC,
        course_code ASC,
        id ASC
    LIMIT 10
");

$stmt->execute([
    ':program_id' => $id
]);

$courseDescriptions =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

?>
<!doctype html>

<html lang="th">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        รายละเอียดหลักสูตร
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- CSS กลางของระบบ Admin -->
    <link
        rel="stylesheet"
        href="../assets/admin.css?v=<?= filemtime(__DIR__ . '/../assets/admin.css') ?>"
    >

    <!-- CSS เฉพาะหน้าหลักสูตร -->
    <link
        rel="stylesheet"
        href="assets/programs.css?v=<?= filemtime(__DIR__ . '/assets/programs.css') ?>"
    >

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
        <span class="hero-badge"><span></span> PROGRAM DETAIL</span>
        <h1><?= htmlspecialchars($program['major_name'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= degreeName($program['degree_level']) ?> • <?= htmlspecialchars($program['program_code'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <div class="hero-actions-inline">
        <a href="index.php" class="btn btn-light-soft">
            <i class="bi bi-arrow-left"></i>
            กลับรายการหลักสูตร
        </a>

        <a href="save.php?id=<?= (int)$program['id'] ?>" class="btn btn-mbs-yellow">
            <i class="bi bi-pencil-square"></i>
            แก้ไขข้อมูล
        </a>
    </div>

    <div class="hero-decoration">MBS</div>
</section>
<div class="page-section">


    <!-- ================================================= -->
    <!-- ข้อมูลทั่วไป -->
    <!-- ================================================= -->

    <div
        class="
            card
            border-0
            shadow-sm
            mb-4
        "
    >

        <div class="card-body p-4">

            <h2 class="h5 fw-bold mb-4">
                ข้อมูลทั่วไป
            </h2>


            <div class="row g-4">


                <div class="col-md-6">

                    <div class="detail-label">
                        ชื่อหลักสูตร
                    </div>

                    <div>
                        <?= showValue(
                            $program[
                                'curriculum_name'
                            ]
                        ) ?>
                    </div>

                </div>


                <div class="col-md-6">

                    <div class="detail-label">
                        สาขาวิชาภาษาอังกฤษ
                    </div>

                    <div>
                        <?= showValue(
                            $program[
                                'major_name_en'
                            ]
                        ) ?>
                    </div>

                </div>


                <div class="col-md-6">

                    <div class="detail-label">
                        ชื่อปริญญาภาษาไทย
                    </div>

                    <div>
                        <?= showValue(
                            $program[
                                'degree_name_th'
                            ]
                        ) ?>
                    </div>

                </div>


                <div class="col-md-6">

                    <div class="detail-label">
                        อักษรย่อภาษาไทย
                    </div>

                    <div>
                        <?= showValue(
                            $program[
                                'degree_abbr_th'
                            ]
                        ) ?>
                    </div>

                </div>


                <div class="col-md-6">

                    <div class="detail-label">
                        ชื่อปริญญาภาษาอังกฤษ
                    </div>

                    <div>
                        <?= showValue(
                            $program[
                                'degree_name_en'
                            ]
                        ) ?>
                    </div>

                </div>


                <div class="col-md-6">

                    <div class="detail-label">
                        อักษรย่อภาษาอังกฤษ
                    </div>

                    <div>
                        <?= showValue(
                            $program[
                                'degree_abbr_en'
                            ]
                        ) ?>
                    </div>

                </div>


                <div class="col-md-6">

                    <div class="detail-label">
                        ปีหลักสูตร
                    </div>

                    <div>
                        <?= showValue(
                            $program[
                                'curriculum_year'
                            ]
                        ) ?>
                    </div>

                </div>


                <div class="col-md-6">

                    <div class="detail-label">
                        Alias
                    </div>

                    <div>
                        <?= showValue(
                            $program[
                                'aliases'
                            ]
                        ) ?>
                    </div>

                </div>


                <div class="col-12">

                    <div class="detail-label">
                        ภาษาที่ใช้ในการเรียนการสอน
                    </div>

                    <div>
                        <?= showValue(
                            $program[
                                'program_language'
                            ]
                        ) ?>
                    </div>

                </div>


                <div class="col-12">

                    <div class="detail-label">
                        ข้อมูลการรับเข้า
                    </div>

                    <div>
                        <?= showValue(
                            $program[
                                'admission_info'
                            ]
                        ) ?>
                    </div>

                </div>


                <div class="col-12">

                    <div class="detail-label">
                        ความร่วมมือ
                    </div>

                    <div>
                        <?= showValue(
                            $program[
                                'cooperation_info'
                            ]
                        ) ?>
                    </div>

                </div>


                <div class="col-12">

                    <div class="detail-label">
                        การบูรณาการ
                    </div>

                    <div>
                        <?= showValue(
                            $program[
                                'integration_info'
                            ]
                        ) ?>
                    </div>

                </div>


            </div>

        </div>

    </div>


    <!-- ================================================= -->
    <!-- Plans -->
    <!-- ================================================= -->

    <div
        class="
            card
            border-0
            shadow-sm
            mb-4
        "
    >

        <div class="card-body p-4">


            <h2 class="h5 fw-bold mb-4">
                แผน / โครงสร้างหน่วยกิต
            </h2>


            <?php if (!$plans): ?>


                <p class="text-secondary mb-0">
                    ไม่มีข้อมูล
                </p>


            <?php else: ?>


                <?php foreach ($plans as $plan): ?>


                    <div class="plan-detail-block">


                        <div
                            class="
                                d-flex
                                flex-column
                                flex-md-row
                                justify-content-between
                                gap-2
                                mb-3
                            "
                        >


                            <div>


                                <h3
                                    class="
                                        h6
                                        fw-bold
                                        mb-1
                                    "
                                >

                                    <?= showValue(
                                        $plan[
                                            'plan_name'
                                        ]
                                    ) ?>

                                </h3>


                                <div
                                    class="
                                        small
                                        text-secondary
                                    "
                                >

                                    รหัสแผน:

                                    <?= showValue(
                                        $plan[
                                            'plan_code'
                                        ]
                                    ) ?>

                                </div>


                            </div>


                            <div
                                class="
                                    text-md-end
                                "
                            >


                                <div
                                    class="
                                        small
                                        text-secondary
                                    "
                                >
                                    หน่วยกิตรวม
                                </div>


                                <div
                                    class="
                                        fs-5
                                        fw-bold
                                    "
                                >

                                    <?= showNumber(
                                        $plan[
                                            'total_credits'
                                        ]
                                    ) ?>

                                </div>


                            </div>


                        </div>


                        <?php if (
                            !empty(
                                $plan[
                                    'description'
                                ]
                            )
                        ): ?>


                            <div class="mb-3">

                                <?= showValue(
                                    $plan[
                                        'description'
                                    ]
                                ) ?>

                            </div>


                        <?php endif; ?>


                        <?php if (
                            empty(
                                $plan[
                                    'components'
                                ]
                            )
                        ): ?>


                            <div
                                class="
                                    text-secondary
                                    small
                                "
                            >
                                ไม่มีรายละเอียดโครงสร้างหน่วยกิต
                            </div>


                        <?php else: ?>


                            <div class="table-responsive">


                                <table
                                    class="
                                        table
                                        table-sm
                                        align-middle
                                        mb-0
                                    "
                                >


                                    <thead
                                        class="table-light"
                                    >

                                        <tr>

                                            <th>
                                                หมวด / โครงสร้าง
                                            </th>

                                            <th
                                                style="
                                                    width:
                                                    120px;
                                                "
                                            >
                                                หน่วยกิต
                                            </th>

                                            <th
                                                style="
                                                    width:
                                                    120px;
                                                "
                                            >
                                                ชั่วโมง
                                            </th>

                                            <th>
                                                หมายเหตุ
                                            </th>

                                        </tr>

                                    </thead>


                                    <tbody>

                                        <?php
                                            renderComponentRows(
                                                $plan[
                                                    'components'
                                                ]
                                            );
                                        ?>

                                    </tbody>


                                </table>


                            </div>


                        <?php endif; ?>


                    </div>


                <?php endforeach; ?>


            <?php endif; ?>


        </div>

    </div>


    <!-- ================================================= -->
    <!-- Requirements -->
    <!-- ================================================= -->

    <div
        class="
            card
            border-0
            shadow-sm
            mb-4
        "
    >

        <div class="card-body p-4">


            <h2 class="h5 fw-bold mb-4">
                คุณสมบัติผู้สมัคร
            </h2>


            <?php if (!$requirements): ?>


                <p class="text-secondary mb-0">
                    ไม่มีข้อมูล
                </p>


            <?php else: ?>


                <ol class="mb-0">


                    <?php foreach (
                        $requirements as $item
                    ): ?>


                        <li class="mb-3">


                            <?php if (
                                !empty(
                                    $item[
                                        'plan_id'
                                    ]
                                )
                            ): ?>


                                <span
                                    class="
                                        badge
                                        text-bg-light
                                        border
                                        mb-1
                                    "
                                >

                                    แผน

                                    <?= htmlspecialchars(
                                        trim(
                                            (
                                                $item[
                                                    'plan_code'
                                                ]
                                                ?? ''
                                            )
                                            .
                                            ' '
                                            .
                                            (
                                                $item[
                                                    'plan_name'
                                                ]
                                                ?? ''
                                            )
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </span>


                                <br>


                            <?php endif; ?>


                            <?= nl2br(
                                htmlspecialchars(
                                    $item[
                                        'requirement_text'
                                    ],
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                            ) ?>


                        </li>


                    <?php endforeach; ?>


                </ol>


            <?php endif; ?>


        </div>

    </div>


    <!-- ================================================= -->
    <!-- Careers -->
    <!-- ================================================= -->

    <div
        class="
            card
            border-0
            shadow-sm
            mb-4
        "
    >

        <div class="card-body p-4">


            <h2 class="h5 fw-bold mb-4">
                อาชีพหลังสำเร็จการศึกษา
            </h2>


            <?php if (!$careers): ?>


                <p class="text-secondary mb-0">
                    ไม่มีข้อมูล
                </p>


            <?php else: ?>


                <ol class="mb-0">


                    <?php foreach (
                        $careers as $career
                    ): ?>


                        <li class="mb-2">

                            <?= htmlspecialchars(
                                $career[
                                    'career_name'
                                ],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </li>


                    <?php endforeach; ?>


                </ol>


            <?php endif; ?>


        </div>

    </div>


    <!-- ================================================= -->
    <!-- Course Descriptions -->
    <!-- ================================================= -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body p-4">

            <div class="section-header mb-4">
                <div>
                    <h2 class="h5 fw-bold mb-1">
                        คำอธิบายรายวิชา
                    </h2>
                    <p class="text-secondary small mb-0">
                        มีข้อมูล <?= number_format($courseCount) ?> รายวิชาในหลักสูตรนี้
                    </p>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <a
                        href="course_descriptions.php?program_id=<?= (int)$program['id'] ?>"
                        class="btn btn-outline-primary btn-sm"
                    >
                        <i class="bi bi-journal-text"></i>
                        จัดการรายวิชาทั้งหมด
                    </a>

                    <a
                        href="course_description_save.php?program_id=<?= (int)$program['id'] ?>"
                        class="btn btn-mbs-primary btn-sm"
                    >
                        <i class="bi bi-plus-lg"></i>
                        เพิ่มรายวิชา
                    </a>
                </div>
            </div>

            <?php if (!$courseDescriptions): ?>

                <div class="course-empty-state">
                    <i class="bi bi-journal-x"></i>
                    <strong>ยังไม่มีคำอธิบายรายวิชา</strong>
                    <span>สามารถเพิ่มข้อมูลรายวิชาของหลักสูตรนี้ได้จากปุ่มด้านบน</span>
                </div>

            <?php else: ?>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>รหัสวิชา</th>
                                <th>ชื่อวิชา</th>
                                <th>หน่วยกิต</th>
                                <th>กลุ่ม / ประเภท</th>
                                <th>การประเมิน</th>
                                <th class="text-end">ดูข้อมูล</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($courseDescriptions as $course): ?>
                            <tr>
                                <td>
                                    <span class="program-code">
                                        <?= htmlspecialchars($course['course_code'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td style="min-width: 280px;">
                                    <div class="fw-semibold">
                                        <?= htmlspecialchars($course['course_name_th'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <?php if (!empty($course['course_name_en'])): ?>
                                        <div class="program-alias mt-1">
                                            <?= htmlspecialchars($course['course_name_en'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($course['credits'] ?: '-', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <div>
                                        <?= htmlspecialchars($course['course_group'] ?: '-', ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <?php if (!empty($course['course_type'])): ?>
                                        <span class="badge text-bg-light border mt-1">
                                            <?= htmlspecialchars($course['course_type'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ((int)$course['credit_counted'] === 0): ?>
                                        <span class="badge text-bg-warning mt-1">ไม่นับหน่วยกิต</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= !empty($course['assessment_type'])
                                        ? htmlspecialchars($course['assessment_type'], ENT_QUOTES, 'UTF-8')
                                        : '-' ?>
                                </td>
                                <td class="text-end">
                                    <a
                                        href="course_description_detail.php?id=<?= (int)$course['id'] ?>"
                                        class="btn btn-outline-primary btn-sm"
                                    >
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($courseCount > 10): ?>
                    <div class="text-center mt-3">
                        <a
                            href="course_descriptions.php?program_id=<?= (int)$program['id'] ?>"
                            class="btn btn-outline-primary btn-sm"
                        >
                            ดูทั้งหมด <?= number_format($courseCount) ?> รายวิชา
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        </div>

    </div>


    <!-- ================================================= -->
    <!-- Objectives -->
    <!-- ================================================= -->

    <div
        class="
            card
            border-0
            shadow-sm
            mb-5
        "
    >

        <div class="card-body p-4">


            <h2 class="h5 fw-bold mb-4">
                วัตถุประสงค์หลักสูตร
            </h2>


            <?php if (!$objectives): ?>


                <p class="text-secondary mb-0">
                    ไม่มีข้อมูล
                </p>


            <?php else: ?>


                <ol class="mb-0">


                    <?php foreach (
                        $objectives as $objective
                    ): ?>


                        <li class="mb-3">

                            <?= nl2br(
                                htmlspecialchars(
                                    $objective[
                                        'objective_text'
                                    ],
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                            ) ?>

                        </li>


                    <?php endforeach; ?>


                </ol>


            <?php endif; ?>


        </div>

    </div>


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
document.addEventListener('DOMContentLoaded', function () {

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
        sidebarToggle.addEventListener('click', function () {

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
    window.addEventListener('resize', function () {

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
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


</body>

</html>