<?php

require_once "../../api/db.php";

$pageTitle = "จัดการหลักสูตรและสาขาวิชา";

$search = trim($_GET['search'] ?? '');
$degree = trim($_GET['degree'] ?? '');
$status = trim($_GET['status'] ?? '');
$sort = trim($_GET['sort'] ?? 'id_asc');

$sql = "
    SELECT
        id,
        program_code,
        degree_level,
        curriculum_name,
        major_name,
        major_name_en,
        aliases,
        curriculum_year,
        active
    FROM academic_programs
    WHERE 1 = 1
";

$params = [];

if ($search !== '') {
    $sql .= "
        AND (
            program_code LIKE :search
            OR curriculum_name LIKE :search
            OR major_name LIKE :search
            OR major_name_en LIKE :search
            OR aliases LIKE :search
            OR curriculum_year LIKE :search
        )
    ";
    $params[':search'] = '%' . $search . '%';
}

$allowedDegrees = ['bachelor', 'master', 'doctoral'];

if (in_array($degree, $allowedDegrees, true)) {
    $sql .= " AND degree_level = :degree_level";
    $params[':degree_level'] = $degree;
}

if ($status === '1' || $status === '0') {
    $sql .= " AND active = :active";
    $params[':active'] = (int)$status;
}

switch ($sort) {

    case 'id_desc':
        // ID มาก → น้อย
        $sql .= " ORDER BY id DESC";
        break;

    case 'latest':
        // รายการที่เพิ่มล่าสุด
        // ตอนนี้ใช้ ID มากสุดเป็นรายการล่าสุด
        $sql .= " ORDER BY id DESC";
        break;

    case 'name_asc':
        // ชื่อสาขา ก → ฮ
        $sql .= " ORDER BY major_name ASC, id ASC";
        break;

    case 'name_desc':
        // ชื่อสาขา ฮ → ก
        $sql .= " ORDER BY major_name DESC, id ASC";
        break;

    case 'id_asc':
    default:
        // ID น้อย → มาก
        $sql .= " ORDER BY id ASC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

function degreeName(string $degree): string
{
    return match ($degree) {
        'bachelor' => 'ปริญญาตรี',
        'master' => 'ปริญญาโท',
        'doctoral' => 'ปริญญาเอก',
        default => '-'
    };
}

function degreeBadge(string $degree): string
{
    return match ($degree) {
        'bachelor' => 'primary',
        'master' => 'success',
        'doctoral' => 'warning',
        default => 'secondary'
    };
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?></title>

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
        <span class="hero-badge"><span></span> PROGRAM MANAGEMENT</span>
        <h1>จัดการหลักสูตรและสาขาวิชา</h1>
        <p>จัดการข้อมูลหลักสูตร ปริญญาตรี ปริญญาโท และปริญญาเอก</p>
    </div>
    <div class="hero-decoration">MBS</div>
</section>
<div class="page-section">


    

    <div class="page-actions">
    <div>
        <span class="section-kicker">PROGRAM DATABASE</span>
        <h2>รายการหลักสูตร</h2>
        <p>ค้นหา ตรวจสอบ เพิ่ม และแก้ไขข้อมูลหลักสูตรในระบบ</p>
    </div>

    <a href="save.php" class="btn btn-mbs-primary">
        <i class="bi bi-plus-lg"></i>
        เพิ่มหลักสูตร
    </a>
</div>

<?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php
            switch ($_GET['success']) {
                case 'create':
                    echo "เพิ่มข้อมูลหลักสูตรเรียบร้อยแล้ว";
                    break;
                case 'edit':
                    echo "แก้ไขข้อมูลหลักสูตรเรียบร้อยแล้ว";
                    break;
                case 'delete':
                    echo "ลบข้อมูลหลักสูตรเรียบร้อยแล้ว";
                    break;
                default:
                    echo "ดำเนินการเรียบร้อยแล้ว";
            }
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($_GET['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-lg-4">
                    <label class="form-label">ค้นหา</label>
                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="ค้นหารหัส ชื่อหลักสูตร ชื่อสาขา Alias หรือชื่อภาษาอังกฤษ"
                    >
                </div>

                <div class="col-md-4 col-lg-2">
                    <label class="form-label">ระดับการศึกษา</label>
                    <select name="degree" class="form-select">
                        <option value="">ทุกระดับ</option>
                        <option value="bachelor" <?= $degree === 'bachelor' ? 'selected' : '' ?>>
                            ปริญญาตรี
                        </option>
                        <option value="master" <?= $degree === 'master' ? 'selected' : '' ?>>
                            ปริญญาโท
                        </option>
                        <option value="doctoral" <?= $degree === 'doctoral' ? 'selected' : '' ?>>
                            ปริญญาเอก
                        </option>
                    </select>
                </div>

                <div class="col-md-4 col-lg-2">
                    <label class="form-label">สถานะ</label>
                    <select name="status" class="form-select">
                        <option value="">ทุกสถานะ</option>
                        <option value="1" <?= $status === '1' ? 'selected' : '' ?>>
                            เปิดใช้งาน
                        </option>
                        <option value="0" <?= $status === '0' ? 'selected' : '' ?>>
                            ปิดใช้งาน
                        </option>
                    </select>
                </div>

                <div class="col-md-4 col-lg-2">
                    <label class="form-label">เรียงตาม</label>
                    <select
                        name="sort"
                        class="form-select"
                        onchange="this.form.submit()"
                    >
                        <option value="id_asc" <?= $sort === 'id_asc' ? 'selected' : '' ?>>
                            ID น้อย → มาก
                        </option>

                        <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>
                            เพิ่มล่าสุด
                        </option>

                        <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>
                            ชื่อสาขา ก → ฮ
                        </option>

                        <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>
                            ชื่อสาขา ฮ → ก
                        </option>
                    </select>
                </div>

                <div class="col-md-4 col-lg-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-mbs-primary flex-fill">
                        ค้นหา
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary">
                        ล้าง
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="mb-3">
        <span class="text-secondary">พบ</span>
        <strong><?= count($programs) ?></strong>
        <span class="text-secondary">รายการ</span>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">ID</th>
                            <th>รหัส</th>
                            <th>ระดับ</th>
                            <th>หลักสูตร</th>
                            <th>สาขาวิชา</th>
                            <th>ชื่อภาษาอังกฤษ</th>
                            <th>ปีหลักสูตร</th>
                            <th>สถานะ</th>
                            <th class="text-center" style="min-width: 260px;">
                                จัดการ
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (!$programs): ?>
                        <tr>
                            <td colspan="9" class="text-center text-secondary py-5">
                                ยังไม่มีข้อมูลหลักสูตร
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($programs as $row): ?>
                        <tr>
                            <td class="ps-3"><?= (int)$row['id'] ?></td>

                            <td>
                                <span class="program-code">
                                    <?= htmlspecialchars($row['program_code']) ?>
                                </span>
                            </td>

                            <td>
                                <span class="badge text-bg-<?= degreeBadge($row['degree_level']) ?>">
                                    <?= degreeName($row['degree_level']) ?>
                                </span>
                            </td>

                            <td style="min-width: 220px;">
                                <?= htmlspecialchars($row['curriculum_name']) ?>
                            </td>

                            <td style="min-width: 250px;">
                                <div class="fw-semibold">
                                    <?= htmlspecialchars($row['major_name']) ?>
                                </div>

                                <?php if (!empty($row['aliases'])): ?>
                                    <div class="program-alias mt-1">
                                        <?= htmlspecialchars($row['aliases']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td style="min-width: 260px;">
                                <?= !empty($row['major_name_en'])
                                    ? htmlspecialchars($row['major_name_en'])
                                    : '<span class="text-secondary">-</span>' ?>
                            </td>

                            <td>
                                <?= !empty($row['curriculum_year'])
                                    ? htmlspecialchars($row['curriculum_year'])
                                    : '-' ?>
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
                                    <a
                                        href="detail.php?id=<?= (int)$row['id'] ?>"
                                        class="btn btn-outline-primary btn-sm"
                                    >
                                        <i class="bi bi-eye"></i> ดูข้อมูล
                                    </a>

                                    <a
                                        href="save.php?id=<?= (int)$row['id'] ?>"
                                        class="btn btn-warning btn-sm"
                                    >
                                        <i class="bi bi-pencil"></i> แก้ไข
                                    </a>

                                    <form
                                        method="post"
                                        action="delete.php"
                                        class="m-0"
                                        onsubmit="return confirm('ยืนยันการลบหลักสูตรนี้และข้อมูลทั้งหมดที่เกี่ยวข้องหรือไม่?');"
                                    >
                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$row['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-danger btn-sm"
                                        >
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
