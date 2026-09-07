<?php
require_once "../../api/db.php";
require_once "../admin_activity.php";

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function cleanString($value): string
{
    return trim((string)($value ?? ''));
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$isEdit = false;
$error = '';

$guide = [
    'topic_code' => '',
    'title' => '',
    'summary' => '',
    'keywords' => '',
    'active' => 1,
    'sort_order' => 0
];

$steps = [];
$documents = [];
$periods = [];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM academic_guides WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        exit('ไม่พบข้อมูลคู่มือการเรียน');
    }

    $guide = array_merge($guide, $row);
    $isEdit = true;

    $stmt = $pdo->prepare("SELECT * FROM academic_guide_steps WHERE guide_id = :guide_id ORDER BY step_order ASC, id ASC");
    $stmt->execute([':guide_id' => $id]);
    $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM academic_guide_documents WHERE guide_id = :guide_id ORDER BY sort_order ASC, id ASC");
    $stmt->execute([':guide_id' => $id]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM academic_guide_periods WHERE guide_id = :guide_id ORDER BY sort_order ASC, start_date ASC, id ASC");
    $stmt->execute([':guide_id' => $id]);
    $periods = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    $topicCode = cleanString($_POST['topic_code'] ?? '');
    $title = cleanString($_POST['title'] ?? '');
    $summary = cleanString($_POST['summary'] ?? '');
    $keywords = cleanString($_POST['keywords'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $active = isset($_POST['active']) ? 1 : 0;

    $steps = is_array($_POST['steps'] ?? null) ? $_POST['steps'] : [];
    $documents = is_array($_POST['documents'] ?? null) ? $_POST['documents'] : [];
    $periods = is_array($_POST['periods'] ?? null) ? $_POST['periods'] : [];

    $guide = [
        'topic_code' => $topicCode,
        'title' => $title,
        'summary' => $summary,
        'keywords' => $keywords,
        'sort_order' => $sortOrder,
        'active' => $active
    ];

    if ($topicCode === '' || $title === '') {
        $error = 'กรุณากรอก Topic code และชื่อหัวข้อคู่มือให้ครบ';
    } elseif (!preg_match('/^[a-z0-9_\-]+$/', $topicCode)) {
        $error = 'Topic code ใช้ได้เฉพาะ a-z, 0-9, _ และ - เท่านั้น';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM academic_guides WHERE topic_code = :topic_code AND id <> :id LIMIT 1");
            $stmt->execute([
                ':topic_code' => $topicCode,
                ':id' => $postedId ?: 0
            ]);

            if ($stmt->fetchColumn()) {
                throw new Exception('Topic code นี้มีอยู่ในระบบแล้ว');
            }

            $pdo->beginTransaction();

            if ($postedId) {
                $stmt = $pdo->prepare("
                    UPDATE academic_guides
                    SET
                        topic_code = :topic_code,
                        title = :title,
                        summary = :summary,
                        keywords = :keywords,
                        active = :active,
                        sort_order = :sort_order
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':topic_code' => $topicCode,
                    ':title' => $title,
                    ':summary' => $summary ?: null,
                    ':keywords' => $keywords ?: null,
                    ':active' => $active,
                    ':sort_order' => $sortOrder,
                    ':id' => $postedId
                ]);
                $guideId = (int)$postedId;
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO academic_guides
                    (topic_code, title, summary, keywords, active, sort_order)
                    VALUES (:topic_code, :title, :summary, :keywords, :active, :sort_order)
                ");
                $stmt->execute([
                    ':topic_code' => $topicCode,
                    ':title' => $title,
                    ':summary' => $summary ?: null,
                    ':keywords' => $keywords ?: null,
                    ':active' => $active,
                    ':sort_order' => $sortOrder
                ]);
                $guideId = (int)$pdo->lastInsertId();
            }

            $stmt = $pdo->prepare("DELETE FROM academic_guide_steps WHERE guide_id = :guide_id");
            $stmt->execute([':guide_id' => $guideId]);

            $stmt = $pdo->prepare("DELETE FROM academic_guide_documents WHERE guide_id = :guide_id");
            $stmt->execute([':guide_id' => $guideId]);

            $stmt = $pdo->prepare("DELETE FROM academic_guide_periods WHERE guide_id = :guide_id");
            $stmt->execute([':guide_id' => $guideId]);

            $stepOrder = 1;
            foreach ($steps as $step) {
                $stepTitle = cleanString($step['step_title'] ?? '');
                $description = cleanString($step['description'] ?? '');
                $url = cleanString($step['url'] ?? '');

                if ($stepTitle === '' && $description === '' && $url === '') {
                    continue;
                }

                if ($description === '') {
                    throw new Exception('กรุณากรอกรายละเอียดของขั้นตอนให้ครบ');
                }

                $stmt = $pdo->prepare("
                    INSERT INTO academic_guide_steps
                    (guide_id, step_order, step_title, description, url)
                    VALUES (:guide_id, :step_order, :step_title, :description, :url)
                ");
                $stmt->execute([
                    ':guide_id' => $guideId,
                    ':step_order' => $stepOrder++,
                    ':step_title' => $stepTitle ?: null,
                    ':description' => $description,
                    ':url' => $url ?: null
                ]);
            }

            $documentOrder = 1;
            foreach ($documents as $document) {
                $documentName = cleanString($document['document_name'] ?? '');
                $documentUrl = cleanString($document['document_url'] ?? '');
                $note = cleanString($document['note'] ?? '');

                if ($documentName === '' && $documentUrl === '' && $note === '') {
                    continue;
                }

                if ($documentName === '') {
                    throw new Exception('กรุณากรอกชื่อเอกสารให้ครบ');
                }

                $stmt = $pdo->prepare("
                    INSERT INTO academic_guide_documents
                    (guide_id, document_name, document_url, note, sort_order)
                    VALUES (:guide_id, :document_name, :document_url, :note, :sort_order)
                ");
                $stmt->execute([
                    ':guide_id' => $guideId,
                    ':document_name' => $documentName,
                    ':document_url' => $documentUrl ?: null,
                    ':note' => $note ?: null,
                    ':sort_order' => $documentOrder++
                ]);
            }

            $periodOrder = 1;
            foreach ($periods as $period) {
                $academicYear = cleanString($period['academic_year'] ?? '');
                $semester = cleanString($period['semester'] ?? '');
                $yearLevel = cleanString($period['year_level'] ?? '');
                $studentCode = cleanString($period['student_code'] ?? '');
                $periodName = cleanString($period['period_name'] ?? '');
                $startDate = cleanString($period['start_date'] ?? '');
                $endDate = cleanString($period['end_date'] ?? '');
                $dateText = cleanString($period['date_text'] ?? '');
                $note = cleanString($period['note'] ?? '');

                $hasData = $academicYear !== '' || $semester !== '' || $yearLevel !== '' ||
                    $studentCode !== '' || $periodName !== '' || $startDate !== '' || $endDate !== '' ||
                    $dateText !== '' || $note !== '';

                if (!$hasData) {
                    continue;
                }

                if ($periodName === '') {
                    throw new Exception('กรุณากรอกชื่อกำหนดการให้ครบ');
                }

                $stmt = $pdo->prepare("
                    INSERT INTO academic_guide_periods
                    (
                        guide_id, academic_year, semester, year_level,
                        student_code, period_name, start_date, end_date,
                        date_text, note, sort_order
                    )
                    VALUES
                    (
                        :guide_id, :academic_year, :semester, :year_level,
                        :student_code, :period_name, :start_date, :end_date,
                        :date_text, :note, :sort_order
                    )
                ");
                $stmt->execute([
                    ':guide_id' => $guideId,
                    ':academic_year' => $academicYear !== '' ? (int)$academicYear : null,
                    ':semester' => $semester ?: null,
                    ':year_level' => $yearLevel ?: null,
                    ':student_code' => $studentCode ?: null,
                    ':period_name' => $periodName,
                    ':start_date' => $startDate ?: null,
                    ':end_date' => $endDate ?: null,
                    ':date_text' => $dateText ?: null,
                    ':note' => $note ?: null,
                    ':sort_order' => $periodOrder++
                ]);
            }

            logAdminActivity(
                $pdo,
                'academic_guide',
                $postedId ? 'update' : 'create',
                $title,
                ($postedId ? 'แก้ไข' : 'เพิ่ม') . 'ข้อมูลคู่มือการเรียน (' . $topicCode . ')'
            );

            $pdo->commit();

            header('Location: index.php?success=' . ($postedId ? 'edit' : 'create'));
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'ไม่สามารถบันทึกข้อมูลได้: ' . $e->getMessage();
        }
    }
}

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$stepsJson = json_encode(array_values($steps), $jsonFlags) ?: '[]';
$documentsJson = json_encode(array_values($documents), $jsonFlags) ?: '[]';
$periodsJson = json_encode(array_values($periods), $jsonFlags) ?: '[]';
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $isEdit ? 'แก้ไขคู่มือการเรียน' : 'เพิ่มคู่มือการเรียน' ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/admin.css?v=<?= filemtime(__DIR__ . '/../assets/admin.css') ?>">
    <link rel="stylesheet" href="assets/guides.css?v=<?= filemtime(__DIR__ . '/assets/guides.css') ?>">
</head>
<body>
<div class="admin-layout">
<?php
$activeMenu = 'academic_guides';
$basePath = '../';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-shell">
<header class="topbar">
    <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="เปิดเมนู"><i class="bi bi-list"></i></button>
    <div class="topbar-title">
        <span class="topbar-kicker">MBS • MAHASARAKHAM UNIVERSITY</span>
        <strong>คู่มือการเรียน</strong>
    </div>
    <a href="javascript:history.back()" class="header-back-btn"><i class="bi bi-arrow-left"></i><span>ย้อนกลับ</span></a>
</header>

<main class="content-area">
<section class="page-hero">
    <div>
        <span class="hero-badge"><span></span> ACADEMIC GUIDE EDITOR</span>
        <h1><?= $isEdit ? 'แก้ไขข้อมูลคู่มือการเรียน' : 'เพิ่มข้อมูลคู่มือการเรียน' ?></h1>
        <p>จัดการข้อมูลหลัก ขั้นตอน เอกสาร และกำหนดการของแต่ละหัวข้อ</p>
    </div>
    <div class="hero-decoration">MBS</div>
</section>

<div class="page-section">
    <div class="editor-toolbar">
        <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> กลับรายการคู่มือ</a>
        <span><?= $isEdit ? 'กำลังแก้ไขข้อมูลเดิม' : 'กำลังสร้างหัวข้อคู่มือใหม่' ?></span>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" id="guideForm">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int)$guide['id'] ?>">
        <?php endif; ?>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h2 class="h5 fw-bold mb-4">1. ข้อมูลหัวข้อคู่มือ</h2>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Topic code <span class="required-star">*</span></label>
                        <input type="text" name="topic_code" class="form-control" value="<?= e($guide['topic_code']) ?>" placeholder="เช่น major_transfer" required>
                        <div class="form-text">ใช้เป็นค่าที่ API และ Dify เรียก เช่น resignation, exam_conflict</div>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">ลำดับ</label>
                        <input type="number" min="0" name="sort_order" class="form-control" value="<?= (int)$guide['sort_order'] ?>">
                    </div>

                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input type="checkbox" name="active" class="form-check-input" id="active" <?= (int)$guide['active'] === 1 ? 'checked' : '' ?>>
                            <label for="active" class="form-check-label">เปิดใช้งานหัวข้อนี้</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">ชื่อหัวข้อคู่มือ <span class="required-star">*</span></label>
                        <input type="text" name="title" class="form-control" value="<?= e($guide['title']) ?>" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label">สรุป</label>
                        <textarea name="summary" class="form-control" rows="3"><?= e($guide['summary']) ?></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Keywords / คำค้น</label>
                        <textarea name="keywords" class="form-control" rows="2" placeholder="เช่น ย้ายสาขา, ย้ายคณะ, เปลี่ยนสาขา"><?= e($guide['keywords']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="section-header">
                    <div>
                        <h2 class="h5 fw-bold mb-1">2. ขั้นตอน</h2>
                        <p class="text-secondary small mb-0">เรียงตามลำดับที่แสดงใน API</p>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="addStep()">+ เพิ่มขั้นตอน</button>
                </div>
                <div id="stepsContainer"></div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="section-header">
                    <div>
                        <h2 class="h5 fw-bold mb-1">3. เอกสารที่เกี่ยวข้อง</h2>
                        <p class="text-secondary small mb-0">ชื่อเอกสาร ลิงก์ และหมายเหตุ</p>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="addDocument()">+ เพิ่มเอกสาร</button>
                </div>
                <div id="documentsContainer"></div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="section-header">
                    <div>
                        <h2 class="h5 fw-bold mb-1">4. กำหนดการ / ช่วงเวลา</h2>
                        <p class="text-secondary small mb-0">รองรับปีการศึกษา ภาค ชั้นปี รหัสนิสิต และข้อความวันที่แบบไม่ต่อเนื่อง</p>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="addPeriod()">+ เพิ่มกำหนดการ</button>
                </div>
                <div id="periodsContainer"></div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-5">
            <a href="index.php" class="btn btn-outline-secondary px-4">ยกเลิก</a>
            <button type="submit" class="btn btn-primary px-5">บันทึกทั้งหมด</button>
        </div>
    </form>
</div>
</main>

<footer class="admin-footer">
    <div><strong>MBS UniWise Admin</strong><span>คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</span></div>
    <span>Mahasarakham Business School</span>
</footer>
</div>
</div>

<script>
const oldSteps = <?= $stepsJson ?>;
const oldDocuments = <?= $documentsJson ?>;
const oldPeriods = <?= $periodsJson ?>;
let stepCounter = 0;
let documentCounter = 0;
let periodCounter = 0;

function esc(value = '') {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function addStep(data = {}) {
    const i = stepCounter++;
    const div = document.createElement('div');
    div.className = 'dynamic-item';
    div.innerHTML = `
        <div class="dynamic-item-header">
            <strong>ขั้นตอนที่ ${i + 1}</strong>
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.dynamic-item').remove(); renumberSteps();">ลบรายการ</button>
        </div>
        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label">ชื่อขั้นตอน</label>
                <input type="text" name="steps[${i}][step_title]" class="form-control" value="${esc(data.step_title ?? '')}">
            </div>
            <div class="col-md-7">
                <label class="form-label">URL</label>
                <input type="url" name="steps[${i}][url]" class="form-control" value="${esc(data.url ?? '')}" placeholder="https://...">
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">รายละเอียด <span class="required-star">*</span></label>
                <textarea name="steps[${i}][description]" class="form-control" rows="3">${esc(data.description ?? '')}</textarea>
            </div>
        </div>
    `;
    document.getElementById('stepsContainer').appendChild(div);
    renumberSteps();
}

function renumberSteps() {
    document.querySelectorAll('#stepsContainer .dynamic-item').forEach((item, index) => {
        const strong = item.querySelector('.dynamic-item-header strong');
        if (strong) strong.textContent = `ขั้นตอนที่ ${index + 1}`;
    });
}

function addDocument(data = {}) {
    const i = documentCounter++;
    const div = document.createElement('div');
    div.className = 'dynamic-item';
    div.innerHTML = `
        <div class="dynamic-item-header">
            <strong>เอกสาร</strong>
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.dynamic-item').remove()">ลบรายการ</button>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">ชื่อเอกสาร</label>
                <input type="text" name="documents[${i}][document_name]" class="form-control" value="${esc(data.document_name ?? '')}">
            </div>
            <div class="col-md-6">
                <label class="form-label">URL เอกสาร</label>
                <input type="url" name="documents[${i}][document_url]" class="form-control" value="${esc(data.document_url ?? '')}" placeholder="https://...">
            </div>
            <div class="col-12">
                <label class="form-label">หมายเหตุ</label>
                <textarea name="documents[${i}][note]" class="form-control" rows="2">${esc(data.note ?? '')}</textarea>
            </div>
        </div>
    `;
    document.getElementById('documentsContainer').appendChild(div);
}

function addPeriod(data = {}) {
    const i = periodCounter++;
    const div = document.createElement('div');
    div.className = 'dynamic-item';
    div.innerHTML = `
        <div class="dynamic-item-header">
            <strong>กำหนดการ</strong>
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.dynamic-item').remove()">ลบรายการ</button>
        </div>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">ปีการศึกษา</label>
                <input type="number" name="periods[${i}][academic_year]" class="form-control" value="${esc(data.academic_year ?? '')}" placeholder="2569">
            </div>
            <div class="col-md-3">
                <label class="form-label">ภาคการศึกษา</label>
                <input type="text" name="periods[${i}][semester]" class="form-control" value="${esc(data.semester ?? '')}" placeholder="ภาคต้น">
            </div>
            <div class="col-md-3">
                <label class="form-label">ชั้นปี</label>
                <input type="text" name="periods[${i}][year_level]" class="form-control" value="${esc(data.year_level ?? '')}" placeholder="ปี 2">
            </div>
            <div class="col-md-3">
                <label class="form-label">รหัสนิสิต</label>
                <input type="text" name="periods[${i}][student_code]" class="form-control" value="${esc(data.student_code ?? '')}" placeholder="68">
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">ชื่อกำหนดการ</label>
                <input type="text" name="periods[${i}][period_name]" class="form-control" value="${esc(data.period_name ?? '')}">
            </div>
            <div class="col-md-6">
                <label class="form-label">วันที่เริ่ม</label>
                <input type="date" name="periods[${i}][start_date]" class="form-control" value="${esc(data.start_date ?? '')}">
            </div>
            <div class="col-md-6">
                <label class="form-label">วันที่สิ้นสุด</label>
                <input type="date" name="periods[${i}][end_date]" class="form-control" value="${esc(data.end_date ?? '')}">
            </div>
            <div class="col-12">
                <label class="form-label">ข้อความวันที่</label>
                <input type="text" name="periods[${i}][date_text]" class="form-control" value="${esc(data.date_text ?? '')}" placeholder="เช่น 15, 17 และ 18 มิถุนายน 2569">
                <div class="form-text">ใช้เมื่อวันที่จริงไม่ต่อเนื่อง ไม่ควรบังคับเป็นช่วงวันที่เดียว</div>
            </div>
            <div class="col-12">
                <label class="form-label">หมายเหตุ</label>
                <textarea name="periods[${i}][note]" class="form-control" rows="2">${esc(data.note ?? '')}</textarea>
            </div>
        </div>
    `;
    document.getElementById('periodsContainer').appendChild(div);
}

(oldSteps.length ? oldSteps : [{}]).forEach(addStep);
(oldDocuments.length ? oldDocuments : [{}]).forEach(addDocument);
(oldPeriods.length ? oldPeriods : [{}]).forEach(addPeriod);
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebarToggle = document.getElementById('sidebarToggle');

    function openSidebar() { sidebar?.classList.add('show'); sidebarOverlay?.classList.add('show'); }
    function closeSidebar() { sidebar?.classList.remove('show'); sidebarOverlay?.classList.remove('show'); }

    if (localStorage.getItem('mbsSidebarCollapsed') === '1' && window.innerWidth >= 992) {
        document.body.classList.add('sidebar-collapsed');
    }

    sidebarToggle?.addEventListener('click', function () {
        if (window.innerWidth < 992) return;
        document.body.classList.toggle('sidebar-collapsed');
        localStorage.setItem('mbsSidebarCollapsed', document.body.classList.contains('sidebar-collapsed') ? '1' : '0');
    });

    mobileMenuBtn?.addEventListener('click', openSidebar);
    sidebarOverlay?.addEventListener('click', closeSidebar);

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
