<?php
require_once "../../api/db.php";

$pageTitle = "จัดการคู่มือการเรียน";

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$sort = trim($_GET['sort'] ?? 'sort_asc');

$sql = "
    SELECT
        g.id,
        g.topic_code,
        g.title,
        g.summary,
        g.keywords,
        g.active,
        g.sort_order,
        g.created_at,
        g.updated_at,
        (SELECT COUNT(*) FROM academic_guide_steps s WHERE s.guide_id = g.id) AS step_count,
        (SELECT COUNT(*) FROM academic_guide_documents d WHERE d.guide_id = g.id) AS document_count,
        (SELECT COUNT(*) FROM academic_guide_periods p WHERE p.guide_id = g.id) AS period_count
    FROM academic_guides g
    WHERE 1 = 1
";

$params = [];

if ($search !== '') {
    $sql .= "
        AND (
            g.topic_code LIKE :search
            OR g.title LIKE :search
            OR g.summary LIKE :search
            OR g.keywords LIKE :search
        )
    ";
    $params[':search'] = '%' . $search . '%';
}

if ($status === '1' || $status === '0') {
    $sql .= " AND g.active = :active";
    $params[':active'] = (int)$status;
}

switch ($sort) {
    case 'id_asc':
        $sql .= " ORDER BY g.id ASC";
        break;
    case 'id_desc':
    case 'latest':
        $sql .= " ORDER BY g.id DESC";
        break;
    case 'title_asc':
        $sql .= " ORDER BY g.title ASC, g.id ASC";
        break;
    case 'title_desc':
        $sql .= " ORDER BY g.title DESC, g.id ASC";
        break;
    case 'sort_desc':
        $sql .= " ORDER BY g.sort_order DESC, g.id DESC";
        break;
    case 'sort_asc':
    default:
        $sql .= " ORDER BY g.sort_order ASC, g.id ASC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$guides = $stmt->fetchAll(PDO::FETCH_ASSOC);

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>

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
    <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="เปิดเมนู">
        <i class="bi bi-list"></i>
    </button>

    <div class="topbar-title">
        <span class="topbar-kicker">MBS • MAHASARAKHAM UNIVERSITY</span>
        <strong>คู่มือการเรียน</strong>
    </div>

    <a href="javascript:history.back()" class="header-back-btn">
        <i class="bi bi-arrow-left"></i>
        <span>ย้อนกลับ</span>
    </a>
</header>

<main class="content-area">
<section class="page-hero">
    <div>
        <span class="hero-badge"><span></span> ACADEMIC GUIDE MANAGEMENT</span>
        <h1>จัดการคู่มือการเรียน</h1>
        <p>จัดการหัวข้อ ขั้นตอน เอกสาร และกำหนดการที่ใช้ตอบคำถามผ่าน MBS UniWise AI</p>
    </div>
    <div class="hero-decoration">MBS</div>
</section>

<div class="page-section">
    <div class="page-actions">
        <div>
            <span class="section-kicker">ACADEMIC GUIDE DATABASE</span>
            <h2>รายการคู่มือการเรียน</h2>
            <p>ค้นหา ตรวจสอบ เพิ่ม แก้ไข และจัดลำดับข้อมูลคู่มือ</p>
        </div>

        <a href="save.php" class="btn btn-mbs-primary">
            <i class="bi bi-plus-lg"></i>
            เพิ่มหัวข้อคู่มือ
        </a>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php
            echo match ($_GET['success']) {
                'create' => 'เพิ่มข้อมูลคู่มือเรียบร้อยแล้ว',
                'edit' => 'แก้ไขข้อมูลคู่มือเรียบร้อยแล้ว',
                'delete' => 'ลบข้อมูลคู่มือเรียบร้อยแล้ว',
                default => 'ดำเนินการเรียบร้อยแล้ว'
            };
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= e($_GET['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-lg-5">
                    <label class="form-label">ค้นหา</label>
                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        value="<?= e($search) ?>"
                        placeholder="ค้นหา Topic code, ชื่อหัวข้อ, Summary หรือ Keywords"
                    >
                </div>

                <div class="col-md-4 col-lg-2">
                    <label class="form-label">สถานะ</label>
                    <select name="status" class="form-select">
                        <option value="">ทุกสถานะ</option>
                        <option value="1" <?= $status === '1' ? 'selected' : '' ?>>เปิดใช้งาน</option>
                        <option value="0" <?= $status === '0' ? 'selected' : '' ?>>ปิดใช้งาน</option>
                    </select>
                </div>

                <div class="col-md-4 col-lg-3">
                    <label class="form-label">เรียงตาม</label>
                    <select name="sort" class="form-select" onchange="this.form.submit()">
                        <option value="sort_asc" <?= $sort === 'sort_asc' ? 'selected' : '' ?>>ลำดับน้อย → มาก</option>
                        <option value="sort_desc" <?= $sort === 'sort_desc' ? 'selected' : '' ?>>ลำดับมาก → น้อย</option>
                        <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>เพิ่มล่าสุด</option>
                        <option value="id_asc" <?= $sort === 'id_asc' ? 'selected' : '' ?>>ID น้อย → มาก</option>
                        <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : '' ?>>ชื่อ ก → ฮ</option>
                        <option value="title_desc" <?= $sort === 'title_desc' ? 'selected' : '' ?>>ชื่อ ฮ → ก</option>
                    </select>
                </div>

                <div class="col-md-4 col-lg-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-mbs-primary flex-fill">ค้นหา</button>
                    <a href="index.php" class="btn btn-outline-secondary">ล้าง</a>
                </div>
            </form>
        </div>
    </div>

    <div class="mb-3">
        <span class="text-secondary">พบ</span>
        <strong><?= count($guides) ?></strong>
        <span class="text-secondary">รายการ</span>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th class="ps-3">ID</th>
                        <th>ลำดับ</th>
                        <th>Topic code</th>
                        <th>หัวข้อคู่มือ</th>
                        <th>ข้อมูลย่อย</th>
                        <th>สถานะ</th>
                        <th class="text-center" style="min-width:260px;">จัดการ</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$guides): ?>
                        <tr>
                            <td colspan="7" class="text-center text-secondary py-5">ยังไม่มีข้อมูลคู่มือการเรียน</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($guides as $row): ?>
                        <tr>
                            <td class="ps-3"><?= (int)$row['id'] ?></td>
                            <td><?= (int)$row['sort_order'] ?></td>
                            <td><span class="topic-code"><?= e($row['topic_code']) ?></span></td>
                            <td style="min-width:320px;">
                                <div class="fw-semibold"><?= e($row['title']) ?></div>
                                <?php if (!empty($row['summary'])): ?>
                                    <div class="guide-summary mt-1"><?= e($row['summary']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="min-width:220px;">
                                <div class="guide-counts">
                                    <span><i class="bi bi-list-ol"></i> ขั้นตอน <?= (int)$row['step_count'] ?></span>
                                    <span><i class="bi bi-file-earmark-text"></i> เอกสาร <?= (int)$row['document_count'] ?></span>
                                    <span><i class="bi bi-calendar3"></i> กำหนดการ <?= (int)$row['period_count'] ?></span>
                                </div>
                            </td>
                            <td>
                                <?php if ((int)$row['active'] === 1): ?>
                                    <span class="badge rounded-pill text-bg-success">ใช้งาน</span>
                                <?php else: ?>
                                    <span class="badge rounded-pill text-bg-secondary">ปิดใช้งาน</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex justify-content-center gap-2 flex-wrap">
                                    <a href="detail.php?id=<?= (int)$row['id'] ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-eye"></i> ดูข้อมูล
                                    </a>
                                    <a href="save.php?id=<?= (int)$row['id'] ?>" class="btn btn-warning btn-sm">
                                        <i class="bi bi-pencil"></i> แก้ไข
                                    </a>
                                    <form method="post" action="delete.php" class="m-0" onsubmit="return confirm('ยืนยันการลบหัวข้อคู่มือนี้และข้อมูลย่อยทั้งหมดหรือไม่?');">
                                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
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
        sidebar?.classList.add('show');
        sidebarOverlay?.classList.add('show');
    }
    function closeSidebar() {
        sidebar?.classList.remove('show');
        sidebarOverlay?.classList.remove('show');
    }

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
