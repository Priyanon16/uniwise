<?php

require_once "../../api/db.php";
require_once "../admin_activity.php";

function cleanString($value): string
{
    return trim((string)($value ?? ''));
}

function degreeName(string $degree): string
{
    return match ($degree) {
        'bachelor' => 'ปริญญาตรี',
        'master' => 'ปริญญาโท',
        'doctoral' => 'ปริญญาเอก',
        default => '-'
    };
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$programId = filter_input(INPUT_GET, 'program_id', FILTER_VALIDATE_INT);
$isEdit = false;
$error = '';

$course = [
    'id' => null,
    'program_id' => $programId ?: null,
    'course_code' => '',
    'course_name_th' => '',
    'course_name_en' => '',
    'credits' => '',
    'course_group' => '',
    'course_type' => '',
    'plan_applicability' => '',
    'credit_counted' => 1,
    'assessment_type' => '',
    'prerequisite' => '',
    'description_th' => '',
    'description_en' => '',
    'raw_content' => '',
    'sort_order' => 0
];

if ($id) {
    $stmt = $pdo->prepare("\n        SELECT *\n        FROM course_descriptions\n        WHERE id = :id\n        LIMIT 1\n    ");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        exit('ไม่พบคำอธิบายรายวิชา');
    }

    $course = array_merge($course, $row);
    $programId = (int)$course['program_id'];
    $isEdit = true;
}

if (!$programId) {
    exit('กรุณาระบุหลักสูตร');
}

$stmt = $pdo->prepare("\n    SELECT\n        id,\n        program_code,\n        degree_level,\n        curriculum_name,\n        major_name,\n        major_name_en,\n        curriculum_year\n    FROM academic_programs\n    WHERE id = :id\n    LIMIT 1\n");
$stmt->execute([':id' => $programId]);
$program = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$program) {
    exit('ไม่พบข้อมูลหลักสูตร');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $postedProgramId = filter_input(INPUT_POST, 'program_id', FILTER_VALIDATE_INT);

    if (!$postedProgramId || $postedProgramId !== $programId) {
        $error = 'ข้อมูลหลักสูตรไม่ถูกต้อง';
    } else {
        $courseCode = cleanString($_POST['course_code'] ?? '');
        $courseNameTh = cleanString($_POST['course_name_th'] ?? '');
        $courseNameEn = cleanString($_POST['course_name_en'] ?? '');
        $credits = cleanString($_POST['credits'] ?? '');
        $courseGroup = cleanString($_POST['course_group'] ?? '');
        $courseType = cleanString($_POST['course_type'] ?? '');
        $planApplicability = cleanString($_POST['plan_applicability'] ?? '');
        $creditCounted = isset($_POST['credit_counted']) ? 1 : 0;
        $assessmentType = cleanString($_POST['assessment_type'] ?? '');
        $prerequisite = cleanString($_POST['prerequisite'] ?? '');
        $descriptionTh = cleanString($_POST['description_th'] ?? '');
        $descriptionEn = cleanString($_POST['description_en'] ?? '');
        $rawContent = cleanString($_POST['raw_content'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($courseCode === '' || $courseNameTh === '') {
            $error = 'กรุณากรอกรหัสวิชาและชื่อวิชาภาษาไทย';
        } else {
            try {
                $pdo->beginTransaction();

                $params = [
                    ':program_id' => $programId,
                    ':curriculum_name' => $program['curriculum_name'] ?: null,
                    ':major_name' => $program['major_name'] ?: null,
                    ':degree_level' => $program['degree_level'],
                    ':course_code' => $courseCode,
                    ':course_name_th' => $courseNameTh,
                    ':course_name_en' => $courseNameEn ?: null,
                    ':credits' => $credits ?: null,
                    ':course_group' => $courseGroup ?: null,
                    ':course_type' => $courseType ?: null,
                    ':plan_applicability' => $planApplicability ?: null,
                    ':credit_counted' => $creditCounted,
                    ':assessment_type' => $assessmentType ?: null,
                    ':prerequisite' => $prerequisite ?: null,
                    ':description_th' => $descriptionTh ?: null,
                    ':description_en' => $descriptionEn ?: null,
                    ':raw_content' => $rawContent ?: null,
                    ':sort_order' => $sortOrder
                ];

                if ($postedId) {
                    $stmt = $pdo->prepare("\n                        UPDATE course_descriptions\n                        SET\n                            program_id = :program_id,\n                            curriculum_name = :curriculum_name,\n                            major_name = :major_name,\n                            degree_level = :degree_level,\n                            course_code = :course_code,\n                            course_name_th = :course_name_th,\n                            course_name_en = :course_name_en,\n                            credits = :credits,\n                            course_group = :course_group,\n                            course_type = :course_type,\n                            plan_applicability = :plan_applicability,\n                            credit_counted = :credit_counted,\n                            assessment_type = :assessment_type,\n                            prerequisite = :prerequisite,\n                            description_th = :description_th,\n                            description_en = :description_en,\n                            raw_content = :raw_content,\n                            sort_order = :sort_order\n                        WHERE id = :id\n                    ");
                    $params[':id'] = $postedId;
                    $stmt->execute($params);
                    $courseId = (int)$postedId;
                    $action = 'update';
                } else {
                    $stmt = $pdo->prepare("\n                        INSERT INTO course_descriptions\n                        (\n                            program_id,\n                            curriculum_name,\n                            major_name,\n                            degree_level,\n                            course_code,\n                            course_name_th,\n                            course_name_en,\n                            credits,\n                            course_group,\n                            course_type,\n                            plan_applicability,\n                            credit_counted,\n                            assessment_type,\n                            prerequisite,\n                            description_th,\n                            description_en,\n                            raw_content,\n                            sort_order\n                        )\n                        VALUES\n                        (\n                            :program_id,\n                            :curriculum_name,\n                            :major_name,\n                            :degree_level,\n                            :course_code,\n                            :course_name_th,\n                            :course_name_en,\n                            :credits,\n                            :course_group,\n                            :course_type,\n                            :plan_applicability,\n                            :credit_counted,\n                            :assessment_type,\n                            :prerequisite,\n                            :description_th,\n                            :description_en,\n                            :raw_content,\n                            :sort_order\n                        )\n                    ");
                    $stmt->execute($params);
                    $courseId = (int)$pdo->lastInsertId();
                    $action = 'create';
                }

                logAdminActivity(
                    $pdo,
                    'programs',
                    $action,
                    $courseCode . ' ' . $courseNameTh,
                    ($action === 'create' ? 'เพิ่ม' : 'แก้ไข') .
                    'คำอธิบายรายวิชาใน ' . $program['major_name']
                );

                $pdo->commit();

                header(
                    'Location: course_descriptions.php?program_id=' .
                    $programId .
                    '&success=' . ($action === 'create' ? 'create' : 'edit')
                );
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'ไม่สามารถบันทึกข้อมูลได้: ' . $e->getMessage();
            }
        }

        $course = array_merge($course, [
            'id' => $postedId,
            'program_id' => $programId,
            'course_code' => $courseCode ?? '',
            'course_name_th' => $courseNameTh ?? '',
            'course_name_en' => $courseNameEn ?? '',
            'credits' => $credits ?? '',
            'course_group' => $courseGroup ?? '',
            'course_type' => $courseType ?? '',
            'plan_applicability' => $planApplicability ?? '',
            'credit_counted' => $creditCounted ?? 1,
            'assessment_type' => $assessmentType ?? '',
            'prerequisite' => $prerequisite ?? '',
            'description_th' => $descriptionTh ?? '',
            'description_en' => $descriptionEn ?? '',
            'raw_content' => $rawContent ?? '',
            'sort_order' => $sortOrder ?? 0
        ]);
    }
}

$stmt = $pdo->prepare("\n    SELECT DISTINCT course_group\n    FROM course_descriptions\n    WHERE course_group IS NOT NULL\n      AND TRIM(course_group) <> ''\n    ORDER BY course_group ASC\n");
$stmt->execute();
$allGroups = $stmt->fetchAll(PDO::FETCH_COLUMN);

?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $isEdit ? 'แก้ไขคำอธิบายรายวิชา' : 'เพิ่มคำอธิบายรายวิชา' ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/admin.css?v=<?= filemtime(__DIR__ . '/../assets/admin.css') ?>">
    <link rel="stylesheet" href="assets/programs.css?v=<?= filemtime(__DIR__ . '/assets/programs.css') ?>">
</head>
<body>
<div class="admin-layout">
<?php
$activeMenu = 'programs';
$basePath = '../';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-shell">
<header class="topbar">
    <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="เปิดเมนู">
        <i class="bi bi-list"></i>
    </button>

    <div class="topbar-title">
        <span class="topbar-kicker">MBS • MAHASARAKHAM UNIVERSITY</span>
        <strong>คำอธิบายรายวิชา</strong>
    </div>

    <a href="course_descriptions.php?program_id=<?= (int)$programId ?>" class="header-back-btn">
        <i class="bi bi-arrow-left"></i>
        <span>ย้อนกลับ</span>
    </a>
</header>

<main class="content-area">
<section class="page-hero course-hero">
    <div>
        <span class="hero-badge"><span></span> COURSE DESCRIPTION EDITOR</span>
        <h1><?= $isEdit ? 'แก้ไขคำอธิบายรายวิชา' : 'เพิ่มคำอธิบายรายวิชา' ?></h1>
        <p>
            <?= htmlspecialchars($program['major_name'], ENT_QUOTES, 'UTF-8') ?>
            • <?= degreeName($program['degree_level']) ?>
        </p>
    </div>
    <div class="hero-decoration">COURSE</div>
</section>

<div class="page-section">
    <div class="editor-toolbar">
        <a href="course_descriptions.php?program_id=<?= (int)$programId ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
            กลับรายการรายวิชา
        </a>
        <span><?= $isEdit ? 'กำลังแก้ไขข้อมูลรายวิชาเดิม' : 'กำลังเพิ่มรายวิชาใหม่' ?></span>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="program_id" value="<?= (int)$programId ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int)$course['id'] ?>">
        <?php endif; ?>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="section-header">
                    <div>
                        <h2 class="h5 fw-bold mb-1">1. ข้อมูลรายวิชา</h2>
                        <p class="text-secondary small mb-0">ข้อมูลหลักสูตรและระดับการศึกษาจะเชื่อมจาก Program อัตโนมัติ</p>
                    </div>
                </div>

                <div class="course-program-context mb-4">
                    <div>
                        <span>หลักสูตร</span>
                        <strong><?= htmlspecialchars($program['curriculum_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                    <div>
                        <span>สาขาวิชา</span>
                        <strong><?= htmlspecialchars($program['major_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                    <div>
                        <span>ระดับ</span>
                        <strong><?= degreeName($program['degree_level']) ?></strong>
                    </div>
                </div>

                <div class="required-note"><span>*</span> จำเป็นต้องกรอก</div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">รหัสวิชา <span class="required-star">*</span></label>
                        <input type="text" name="course_code" class="form-control" value="<?= htmlspecialchars($course['course_code'], ENT_QUOTES, 'UTF-8') ?>" placeholder="เช่น 0904 501" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">หน่วยกิต</label>
                        <input type="text" name="credits" class="form-control" value="<?= htmlspecialchars($course['credits'], ENT_QUOTES, 'UTF-8') ?>" placeholder="เช่น 3(3-0-6) หรือ 36 หน่วยกิต">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">ลำดับการแสดงผล</label>
                        <input type="number" name="sort_order" class="form-control" value="<?= (int)$course['sort_order'] ?>" min="0">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">ชื่อวิชาภาษาไทย <span class="required-star">*</span></label>
                        <input type="text" name="course_name_th" class="form-control" value="<?= htmlspecialchars($course['course_name_th'], ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label">ชื่อวิชาภาษาอังกฤษ</label>
                        <input type="text" name="course_name_en" class="form-control" value="<?= htmlspecialchars($course['course_name_en'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">กลุ่มวิชา</label>
                        <input list="courseGroupOptions" type="text" name="course_group" class="form-control" value="<?= htmlspecialchars($course['course_group'], ENT_QUOTES, 'UTF-8') ?>" placeholder="เช่น กลุ่มวิชาบังคับ / กลุ่มวิชาเลือก">
                        <datalist id="courseGroupOptions">
                            <?php foreach ($allGroups as $group): ?>
                                <option value="<?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8') ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">ประเภทวิชา</label>
                        <input list="courseTypeOptions" type="text" name="course_type" class="form-control" value="<?= htmlspecialchars($course['course_type'], ENT_QUOTES, 'UTF-8') ?>" placeholder="required / elective / thesis / seminar">
                        <datalist id="courseTypeOptions">
                            <option value="required">วิชาบังคับ</option>
                            <option value="elective">วิชาเลือก</option>
                            <option value="thesis">วิทยานิพนธ์</option>
                            <option value="independent_study">การค้นคว้าอิสระ</option>
                            <option value="foundation">วิชาพื้นฐาน</option>
                            <option value="seminar">สัมมนา</option>
                        </datalist>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">ใช้กับแผน</label>
                        <input type="text" name="plan_applicability" class="form-control" value="<?= htmlspecialchars($course['plan_applicability'], ENT_QUOTES, 'UTF-8') ?>" placeholder="เช่น แผน 1 แบบ 1.1, แผน 2 แบบ 2.1">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">การประเมินผล</label>
                        <input type="text" name="assessment_type" class="form-control" value="<?= htmlspecialchars($course['assessment_type'], ENT_QUOTES, 'UTF-8') ?>" placeholder="เช่น S/U หรือ Grade">
                    </div>

                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input type="checkbox" name="credit_counted" class="form-check-input" id="creditCounted" <?= (int)$course['credit_counted'] === 1 ? 'checked' : '' ?>>
                            <label for="creditCounted" class="form-check-label">นับหน่วยกิต</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">รายวิชาบังคับก่อน</label>
                        <input type="text" name="prerequisite" class="form-control" value="<?= htmlspecialchars($course['prerequisite'], ENT_QUOTES, 'UTF-8') ?>" placeholder="เช่น 0900 203 สถิติประยุกต์ในเชิงธุรกิจ">
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="section-header">
                    <div>
                        <h2 class="h5 fw-bold mb-1">2. คำอธิบายรายวิชา</h2>
                        <p class="text-secondary small mb-0">เก็บทั้งภาษาไทยและภาษาอังกฤษเพื่อให้ API ใช้งานได้ตรงข้อมูลต้นฉบับ</p>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">คำอธิบายรายวิชาภาษาไทย</label>
                        <textarea name="description_th" class="form-control" rows="8"><?= htmlspecialchars($course['description_th'], ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label">คำอธิบายรายวิชาภาษาอังกฤษ</label>
                        <textarea name="description_en" class="form-control" rows="8"><?= htmlspecialchars($course['description_en'], ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="section-header">
                    <div>
                        <h2 class="h5 fw-bold mb-1">3. Raw Content</h2>
                        <p class="text-secondary small mb-0">ไม่จำเป็นต้องกรอก หากไม่ได้ใช้ข้อความต้นฉบับแบบรวม</p>
                    </div>
                </div>

                <textarea name="raw_content" class="form-control" rows="8" placeholder="ข้อความต้นฉบับของรายวิชา (ถ้ามี)"><?= htmlspecialchars($course['raw_content'], ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-5">
            <a href="course_descriptions.php?program_id=<?= (int)$programId ?>" class="btn btn-outline-secondary px-4">ยกเลิก</a>
            <button type="submit" class="btn btn-primary px-5">
                <i class="bi bi-floppy"></i>
                บันทึกข้อมูล
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
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebarToggle = document.getElementById('sidebarToggle');

    function openSidebar() {
        if (sidebar) sidebar.classList.add('show');
        if (sidebarOverlay) sidebarOverlay.classList.add('show');
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('show');
        if (sidebarOverlay) sidebarOverlay.classList.remove('show');
    }

    if (localStorage.getItem('mbsSidebarCollapsed') === '1' && window.innerWidth >= 992) {
        document.body.classList.add('sidebar-collapsed');
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            if (window.innerWidth < 992) return;
            document.body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('mbsSidebarCollapsed', document.body.classList.contains('sidebar-collapsed') ? '1' : '0');
        });
    }

    if (mobileMenuBtn) mobileMenuBtn.addEventListener('click', openSidebar);
    if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

    window.addEventListener('resize', function () {
        if (window.innerWidth >= 992) {
            closeSidebar();
            document.body.classList.toggle('sidebar-collapsed', localStorage.getItem('mbsSidebarCollapsed') === '1');
        } else {
            document.body.classList.remove('sidebar-collapsed');
        }
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
