<?php

require_once "../../api/db.php";

$programId = filter_input(INPUT_GET, 'program_id', FILTER_VALIDATE_INT);

if (!$programId) {
    exit('program_id ไม่ถูกต้อง');
}

$stmt = $pdo->prepare("\n    SELECT\n        id,\n        program_code,\n        degree_level,\n        curriculum_name,\n        major_name,\n        major_name_en,\n        curriculum_year\n    FROM academic_programs\n    WHERE id = :id\n    LIMIT 1\n");
$stmt->execute([':id' => $programId]);
$program = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$program) {
    exit('ไม่พบข้อมูลหลักสูตร');
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

$search = trim($_GET['search'] ?? '');
$courseGroup = trim($_GET['course_group'] ?? '');
$courseType = trim($_GET['course_type'] ?? '');
$creditCounted = trim($_GET['credit_counted'] ?? '');
$sort = trim($_GET['sort'] ?? 'sort_asc');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$where = ['program_id = :program_id'];
$params = [':program_id' => $programId];

if ($search !== '') {
    $where[] = "(\n        course_code LIKE :search\n        OR course_name_th LIKE :search\n        OR course_name_en LIKE :search\n        OR course_group LIKE :search\n        OR prerequisite LIKE :search\n        OR description_th LIKE :search\n        OR description_en LIKE :search\n    )";
    $params[':search'] = '%' . $search . '%';
}

if ($courseGroup !== '') {
    $where[] = 'course_group = :course_group';
    $params[':course_group'] = $courseGroup;
}

if ($courseType !== '') {
    $where[] = 'course_type = :course_type';
    $params[':course_type'] = $courseType;
}

if ($creditCounted === '1' || $creditCounted === '0') {
    $where[] = 'credit_counted = :credit_counted';
    $params[':credit_counted'] = (int)$creditCounted;
}

$orderBy = match ($sort) {
    'code_asc' => 'course_code ASC, id ASC',
    'code_desc' => 'course_code DESC, id DESC',
    'name_asc' => 'course_name_th ASC, id ASC',
    'name_desc' => 'course_name_th DESC, id DESC',
    'latest' => 'id DESC',
    default => 'sort_order ASC, course_code ASC, id ASC'
};

$whereSql = implode("\n AND ", $where);

$countStmt = $pdo->prepare("\n    SELECT COUNT(*)\n    FROM course_descriptions\n    WHERE {$whereSql}\n");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$sql = "\n    SELECT\n        id,\n        program_id,\n        course_code,\n        course_name_th,\n        course_name_en,\n        credits,\n        course_group,\n        course_type,\n        plan_applicability,\n        credit_counted,\n        assessment_type,\n        prerequisite,\n        sort_order\n    FROM course_descriptions\n    WHERE {$whereSql}\n    ORDER BY {$orderBy}\n    LIMIT :limit OFFSET :offset\n";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("\n    SELECT DISTINCT course_group\n    FROM course_descriptions\n    WHERE program_id = :program_id\n      AND course_group IS NOT NULL\n      AND TRIM(course_group) <> ''\n    ORDER BY course_group ASC\n");
$stmt->execute([':program_id' => $programId]);
$groups = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare("\n    SELECT DISTINCT course_type\n    FROM course_descriptions\n    WHERE program_id = :program_id\n      AND course_type IS NOT NULL\n      AND TRIM(course_type) <> ''\n    ORDER BY course_type ASC\n");
$stmt->execute([':program_id' => $programId]);
$types = $stmt->fetchAll(PDO::FETCH_COLUMN);

function typeLabel(?string $type): string
{
    $type = trim((string)$type);
    return match ($type) {
        'required' => 'วิชาบังคับ',
        'elective' => 'วิชาเลือก',
        'thesis' => 'วิทยานิพนธ์',
        'independent_study' => 'การค้นคว้าอิสระ',
        'foundation' => 'วิชาพื้นฐาน',
        'seminar' => 'สัมมนา',
        '' => '-',
        default => $type
    };
}

function buildPageUrl(int $pageNumber): string
{
    $query = $_GET;
    $query['page'] = $pageNumber;
    return '?' . http_build_query($query);
}

?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการคำอธิบายรายวิชา</title>

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

    <a href="detail.php?id=<?= (int)$programId ?>" class="header-back-btn">
        <i class="bi bi-arrow-left"></i>
        <span>กลับหลักสูตร</span>
    </a>
</header>

<main class="content-area">
<section class="page-hero course-hero">
    <div>
        <span class="hero-badge"><span></span> COURSE DESCRIPTION MANAGEMENT</span>
        <h1>จัดการคำอธิบายรายวิชา</h1>
        <p>
            <?= htmlspecialchars($program['major_name'], ENT_QUOTES, 'UTF-8') ?>
            • <?= degreeName($program['degree_level']) ?>
        </p>
    </div>

    <div class="hero-actions-inline">
        <a href="detail.php?id=<?= (int)$programId ?>" class="btn btn-light-soft">
            <i class="bi bi-arrow-left"></i>
            กลับรายละเอียดหลักสูตร
        </a>

        <a href="course_description_save.php?program_id=<?= (int)$programId ?>" class="btn btn-mbs-yellow">
            <i class="bi bi-plus-lg"></i>
            เพิ่มรายวิชา
        </a>
    </div>

    <div class="hero-decoration">COURSE</div>
</section>

<div class="page-section">

    <div class="page-actions">
        <div>
            <span class="section-kicker">COURSE DATABASE</span>
            <h2>รายการคำอธิบายรายวิชา</h2>
            <p>ค้นหา ตรวจสอบ เพิ่ม แก้ไข และลบคำอธิบายรายวิชาของหลักสูตรนี้</p>
        </div>

        <a href="course_description_save.php?program_id=<?= (int)$programId ?>" class="btn btn-mbs-primary">
            <i class="bi bi-plus-lg"></i>
            เพิ่มคำอธิบายรายวิชา
        </a>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php
            echo match ($_GET['success']) {
                'create' => 'เพิ่มคำอธิบายรายวิชาเรียบร้อยแล้ว',
                'edit' => 'แก้ไขคำอธิบายรายวิชาเรียบร้อยแล้ว',
                'delete' => 'ลบคำอธิบายรายวิชาเรียบร้อยแล้ว',
                default => 'ดำเนินการเรียบร้อยแล้ว'
            };
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <input type="hidden" name="program_id" value="<?= (int)$programId ?>">

                <div class="col-lg-4">
                    <label class="form-label">ค้นหา</label>
                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="รหัสวิชา ชื่อวิชา หมวดวิชา หรือคำอธิบาย"
                    >
                </div>

                <div class="col-md-4 col-lg-2">
                    <label class="form-label">กลุ่มวิชา</label>
                    <select name="course_group" class="form-select">
                        <option value="">ทุกกลุ่ม</option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8') ?>" <?= $courseGroup === $group ? 'selected' : '' ?>>
                                <?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4 col-lg-2">
                    <label class="form-label">ประเภทวิชา</label>
                    <select name="course_type" class="form-select">
                        <option value="">ทุกประเภท</option>
                        <?php foreach ($types as $type): ?>
                            <option value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" <?= $courseType === $type ? 'selected' : '' ?>>
                                <?= htmlspecialchars(typeLabel($type), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4 col-lg-2">
                    <label class="form-label">การนับหน่วยกิต</label>
                    <select name="credit_counted" class="form-select">
                        <option value="">ทั้งหมด</option>
                        <option value="1" <?= $creditCounted === '1' ? 'selected' : '' ?>>นับหน่วยกิต</option>
                        <option value="0" <?= $creditCounted === '0' ? 'selected' : '' ?>>ไม่นับหน่วยกิต</option>
                    </select>
                </div>

                <div class="col-md-4 col-lg-2">
                    <label class="form-label">เรียงตาม</label>
                    <select name="sort" class="form-select" onchange="this.form.submit()">
                        <option value="sort_asc" <?= $sort === 'sort_asc' ? 'selected' : '' ?>>ลำดับ → รหัสวิชา</option>
                        <option value="code_asc" <?= $sort === 'code_asc' ? 'selected' : '' ?>>รหัสวิชา น้อย → มาก</option>
                        <option value="code_desc" <?= $sort === 'code_desc' ? 'selected' : '' ?>>รหัสวิชา มาก → น้อย</option>
                        <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>ชื่อวิชา ก → ฮ</option>
                        <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>ชื่อวิชา ฮ → ก</option>
                        <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>เพิ่มล่าสุด</option>
                    </select>
                </div>

                <div class="col-12 d-flex gap-2 justify-content-end">
                    <button type="submit" class="btn btn-mbs-primary">
                        <i class="bi bi-search"></i>
                        ค้นหา
                    </button>
                    <a href="course_descriptions.php?program_id=<?= (int)$programId ?>" class="btn btn-outline-secondary">
                        ล้างตัวกรอง
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="course-list-summary mb-3">
        <div>
            <span class="text-secondary">พบทั้งหมด</span>
            <strong><?= number_format($totalRows) ?></strong>
            <span class="text-secondary">รายวิชา</span>
        </div>

        <div class="small text-secondary">
            แสดง <?= $totalRows > 0 ? number_format($offset + 1) : 0 ?>–<?= number_format(min($offset + $perPage, $totalRows)) ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th class="ps-3">ลำดับ</th>
                        <th>รหัสวิชา</th>
                        <th>ชื่อวิชา</th>
                        <th>หน่วยกิต</th>
                        <th>กลุ่ม / ประเภท</th>
                        <th>แผนที่ใช้</th>
                        <th>การประเมิน</th>
                        <th class="text-center" style="min-width: 260px;">จัดการ</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$courses): ?>
                        <tr>
                            <td colspan="8" class="text-center text-secondary py-5">
                                ยังไม่มีข้อมูลคำอธิบายรายวิชาที่ตรงกับเงื่อนไข
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($courses as $index => $row): ?>
                        <tr>
                            <td class="ps-3 text-secondary">
                                <?= number_format($offset + $index + 1) ?>
                            </td>
                            <td>
                                <span class="program-code">
                                    <?= htmlspecialchars($row['course_code'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td style="min-width: 300px;">
                                <div class="fw-semibold">
                                    <?= htmlspecialchars($row['course_name_th'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <?php if (!empty($row['course_name_en'])): ?>
                                    <div class="program-alias mt-1">
                                        <?= htmlspecialchars($row['course_name_en'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($row['prerequisite'])): ?>
                                    <div class="small text-secondary mt-1">
                                        <i class="bi bi-link-45deg"></i>
                                        วิชาบังคับก่อน: <?= htmlspecialchars($row['prerequisite'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['credits'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="min-width: 170px;">
                                <div><?= htmlspecialchars($row['course_group'] ?: '-', ENT_QUOTES, 'UTF-8') ?></div>
                                <?php if (!empty($row['course_type'])): ?>
                                    <span class="badge text-bg-light border mt-1">
                                        <?= htmlspecialchars(typeLabel($row['course_type']), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ((int)$row['credit_counted'] === 0): ?>
                                    <span class="badge text-bg-warning mt-1">ไม่นับหน่วยกิต</span>
                                <?php endif; ?>
                            </td>
                            <td style="min-width: 180px;">
                                <?= !empty($row['plan_applicability'])
                                    ? htmlspecialchars($row['plan_applicability'], ENT_QUOTES, 'UTF-8')
                                    : '<span class="text-secondary">-</span>' ?>
                            </td>
                            <td>
                                <?= !empty($row['assessment_type'])
                                    ? htmlspecialchars($row['assessment_type'], ENT_QUOTES, 'UTF-8')
                                    : '-' ?>
                            </td>
                            <td>
                                <div class="d-flex justify-content-center gap-2 flex-wrap">
                                    <a href="course_description_detail.php?id=<?= (int)$row['id'] ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-eye"></i> ดูข้อมูล
                                    </a>

                                    <a href="course_description_save.php?id=<?= (int)$row['id'] ?>" class="btn btn-warning btn-sm">
                                        <i class="bi bi-pencil"></i> แก้ไข
                                    </a>

                                    <form method="post" action="course_description_delete.php" class="m-0" onsubmit="return confirm('ยืนยันการลบคำอธิบายรายวิชานี้หรือไม่?');">
                                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                        <input type="hidden" name="program_id" value="<?= (int)$programId ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="bi bi-trash3"></i> ลบ
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="mt-4" aria-label="Course pagination">
            <ul class="pagination justify-content-center flex-wrap">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $page > 1 ? htmlspecialchars(buildPageUrl($page - 1), ENT_QUOTES, 'UTF-8') : '#' ?>">ก่อนหน้า</a>
                </li>

                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for ($p = $startPage; $p <= $endPage; $p++):
                ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= htmlspecialchars(buildPageUrl($p), ENT_QUOTES, 'UTF-8') ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>

                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $page < $totalPages ? htmlspecialchars(buildPageUrl($page + 1), ENT_QUOTES, 'UTF-8') : '#' ?>">ถัดไป</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>

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
            localStorage.setItem(
                'mbsSidebarCollapsed',
                document.body.classList.contains('sidebar-collapsed') ? '1' : '0'
            );
        });
    }

    if (mobileMenuBtn) mobileMenuBtn.addEventListener('click', openSidebar);
    if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

    window.addEventListener('resize', function () {
        if (window.innerWidth >= 992) {
            closeSidebar();
            document.body.classList.toggle(
                'sidebar-collapsed',
                localStorage.getItem('mbsSidebarCollapsed') === '1'
            );
        } else {
            document.body.classList.remove('sidebar-collapsed');
        }
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
