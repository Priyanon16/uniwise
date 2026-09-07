<?php
require_once "../../api/db.php";

$pageTitle = "จัดการข้อมูลติดต่อคณะ";

$search = trim($_GET['search'] ?? '');
$type = trim($_GET['type'] ?? '');
$status = trim($_GET['status'] ?? '');
$sort = trim($_GET['sort'] ?? 'sort_asc');

$allowedTypes = ['phone', 'email', 'website', 'facebook', 'office_hours'];

$sql = "
    SELECT
        id,
        contact_type,
        contact_label,
        contact_value,
        description,
        sort_order,
        active,
        created_at,
        updated_at
    FROM faculty_contacts
    WHERE 1 = 1
";

$params = [];

if ($search !== '') {
    $sql .= "
        AND (
            contact_label LIKE :search
            OR contact_value LIKE :search
            OR description LIKE :search
            OR contact_type LIKE :search
        )
    ";
    $params[':search'] = '%' . $search . '%';
}

if (in_array($type, $allowedTypes, true)) {
    $sql .= " AND contact_type = :type";
    $params[':type'] = $type;
}

if ($status === '1' || $status === '0') {
    $sql .= " AND active = :active";
    $params[':active'] = (int)$status;
}

switch ($sort) {
    case 'id_asc':
        $sql .= " ORDER BY id ASC";
        break;
    case 'id_desc':
    case 'latest':
        $sql .= " ORDER BY id DESC";
        break;
    case 'label_asc':
        $sql .= " ORDER BY contact_label ASC, id ASC";
        break;
    case 'sort_desc':
        $sql .= " ORDER BY sort_order DESC, id DESC";
        break;
    case 'sort_asc':
    default:
        $sql .= " ORDER BY sort_order ASC, id ASC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function typeLabel(string $type): string
{
    return match ($type) {
        'phone' => 'โทรศัพท์',
        'email' => 'อีเมล',
        'website' => 'เว็บไซต์',
        'facebook' => 'Facebook',
        'office_hours' => 'เวลาทำการ',
        default => $type
    };
}

function typeIcon(string $type): string
{
    return match ($type) {
        'phone' => 'bi-telephone-fill',
        'email' => 'bi-envelope-fill',
        'website' => 'bi-globe2',
        'facebook' => 'bi-facebook',
        'office_hours' => 'bi-clock-fill',
        default => 'bi-info-circle-fill'
    };
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
    <link rel="stylesheet" href="assets/contacts.css?v=<?= filemtime(__DIR__ . '/assets/contacts.css') ?>">
</head>
<body>
<div class="admin-layout">
<?php
$activeMenu = 'contacts';
$basePath = '../';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-shell">
<header class="topbar">
    <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="เปิดเมนู"><i class="bi bi-list"></i></button>
    <div class="topbar-title">
        <span class="topbar-kicker">MBS • MAHASARAKHAM UNIVERSITY</span>
        <strong>ข้อมูลติดต่อคณะ</strong>
    </div>
    <a href="javascript:history.back()" class="header-back-btn"><i class="bi bi-arrow-left"></i><span>ย้อนกลับ</span></a>
</header>

<main class="content-area">
<section class="page-hero">
    <div>
        <span class="hero-badge"><span></span> CONTACT MANAGEMENT</span>
        <h1>จัดการข้อมูลติดต่อคณะ</h1>
        <p>จัดการโทรศัพท์ อีเมล เว็บไซต์ Facebook และเวลาทำการสำหรับ MBS UniWise AI</p>
    </div>
    <div class="hero-decoration">MBS</div>
</section>

<div class="page-section">
    <div class="page-actions">
        <div>
            <span class="section-kicker">FACULTY CONTACT DATABASE</span>
            <h2>รายการข้อมูลติดต่อ</h2>
            <p>ค้นหา ตรวจสอบ เพิ่ม แก้ไข และกำหนดลำดับการแสดงผล</p>
        </div>
        <a href="save.php" class="btn btn-mbs-primary"><i class="bi bi-plus-lg"></i> เพิ่มข้อมูลติดต่อ</a>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php
            echo match ($_GET['success']) {
                'create' => 'เพิ่มข้อมูลติดต่อเรียบร้อยแล้ว',
                'edit' => 'แก้ไขข้อมูลติดต่อเรียบร้อยแล้ว',
                'delete' => 'ลบข้อมูลติดต่อเรียบร้อยแล้ว',
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
                <div class="col-lg-4">
                    <label class="form-label">ค้นหา</label>
                    <input type="text" name="search" class="form-control" value="<?= e($search) ?>" placeholder="ค้นหาชื่อ ค่า หรือรายละเอียด">
                </div>

                <div class="col-md-4 col-lg-2">
                    <label class="form-label">ประเภท</label>
                    <select name="type" class="form-select">
                        <option value="">ทุกประเภท</option>
                        <?php foreach ($allowedTypes as $typeOption): ?>
                            <option value="<?= e($typeOption) ?>" <?= $type === $typeOption ? 'selected' : '' ?>><?= e(typeLabel($typeOption)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4 col-lg-2">
                    <label class="form-label">สถานะ</label>
                    <select name="status" class="form-select">
                        <option value="">ทุกสถานะ</option>
                        <option value="1" <?= $status === '1' ? 'selected' : '' ?>>เปิดใช้งาน</option>
                        <option value="0" <?= $status === '0' ? 'selected' : '' ?>>ปิดใช้งาน</option>
                    </select>
                </div>

                <div class="col-md-4 col-lg-2">
                    <label class="form-label">เรียงตาม</label>
                    <select name="sort" class="form-select" onchange="this.form.submit()">
                        <option value="sort_asc" <?= $sort === 'sort_asc' ? 'selected' : '' ?>>ลำดับน้อย → มาก</option>
                        <option value="sort_desc" <?= $sort === 'sort_desc' ? 'selected' : '' ?>>ลำดับมาก → น้อย</option>
                        <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>เพิ่มล่าสุด</option>
                        <option value="id_asc" <?= $sort === 'id_asc' ? 'selected' : '' ?>>ID น้อย → มาก</option>
                        <option value="label_asc" <?= $sort === 'label_asc' ? 'selected' : '' ?>>ชื่อ ก → ฮ</option>
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
        <strong><?= count($contacts) ?></strong>
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
                        <th>ประเภท</th>
                        <th>ชื่อแสดงผล</th>
                        <th>ข้อมูล</th>
                        <th>สถานะ</th>
                        <th class="text-center" style="min-width:260px;">จัดการ</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$contacts): ?>
                        <tr><td colspan="7" class="text-center text-secondary py-5">ยังไม่มีข้อมูลติดต่อ</td></tr>
                    <?php endif; ?>

                    <?php foreach ($contacts as $row): ?>
                        <tr>
                            <td class="ps-3"><?= (int)$row['id'] ?></td>
                            <td><?= (int)$row['sort_order'] ?></td>
                            <td>
                                <span class="contact-type-badge">
                                    <i class="bi <?= e(typeIcon($row['contact_type'])) ?>"></i>
                                    <?= e(typeLabel($row['contact_type'])) ?>
                                </span>
                            </td>
                            <td class="fw-semibold"><?= e($row['contact_label']) ?></td>
                            <td style="min-width:320px;">
                                <div class="contact-value"><?= e($row['contact_value']) ?></div>
                                <?php if (!empty($row['description'])): ?>
                                    <div class="contact-description mt-1"><?= e($row['description']) ?></div>
                                <?php endif; ?>
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
                                    <a href="detail.php?id=<?= (int)$row['id'] ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-eye"></i> ดูข้อมูล</a>
                                    <a href="save.php?id=<?= (int)$row['id'] ?>" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i> แก้ไข</a>
                                    <form method="post" action="delete.php" class="m-0" onsubmit="return confirm('ยืนยันการลบข้อมูลติดต่อนี้หรือไม่?');">
                                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash3"></i> ลบ</button>
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
    <div><strong>MBS UniWise Admin</strong><span>คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</span></div>
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
    function openSidebar() { sidebar?.classList.add('show'); sidebarOverlay?.classList.add('show'); }
    function closeSidebar() { sidebar?.classList.remove('show'); sidebarOverlay?.classList.remove('show'); }
    if (localStorage.getItem('mbsSidebarCollapsed') === '1' && window.innerWidth >= 992) document.body.classList.add('sidebar-collapsed');
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
        } else document.body.classList.remove('sidebar-collapsed');
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
