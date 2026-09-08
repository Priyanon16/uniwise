<?php

require_once "../../api/db.php";

$pageTitle = "จัดการข้อมูลคาเฟ่";

$search = trim($_GET['search'] ?? '');
$zone   = trim($_GET['zone'] ?? '');
$sort   = trim($_GET['sort'] ?? 'id_asc');

$sql = "
    SELECT
        id,
        cafe_name,
        opening_hours,
        description,
        food_and_drinks,
        phone_number,
        online_channels,
        zone,
        maps_location,
        created_at
    FROM msu_cafes
    WHERE 1 = 1
";

$params = [];

if ($search !== '') {
    $sql .= "
        AND (
            CAST(id AS CHAR) LIKE :search
            OR cafe_name LIKE :search
            OR opening_hours LIKE :search
            OR description LIKE :search
            OR food_and_drinks LIKE :search
            OR phone_number LIKE :search
            OR online_channels LIKE :search
            OR zone LIKE :search
            OR maps_location LIKE :search
        )
    ";

    $params[':search'] = '%' . $search . '%';
}

if ($zone !== '') {
    $sql .= " AND zone = :zone";
    $params[':zone'] = $zone;
}

switch ($sort) {
    case 'id_desc':
        $sql .= " ORDER BY id DESC";
        break;

    case 'latest':
        $sql .= " ORDER BY created_at DESC, id DESC";
        break;

    case 'name_asc':
        $sql .= " ORDER BY cafe_name ASC, id ASC";
        break;

    case 'name_desc':
        $sql .= " ORDER BY cafe_name DESC, id ASC";
        break;

    case 'id_asc':
    default:
        $sql .= " ORDER BY id ASC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cafes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$zoneStmt = $pdo->query("
    SELECT DISTINCT zone
    FROM msu_cafes
    WHERE zone IS NOT NULL
      AND TRIM(zone) <> ''
    ORDER BY zone ASC
");

$zones = $zoneStmt->fetchAll(PDO::FETCH_COLUMN);

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

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet">

    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="../assets/admin.css?v=<?= filemtime(__DIR__ . '/../assets/admin.css') ?>">

    <link
        rel="stylesheet"
        href="assets/cafes.css?v=<?= filemtime(__DIR__ . '/assets/cafes.css') ?>">
</head>

<body>

    <div class="admin-layout">

        <?php
        $activeMenu = 'cafes';
        $basePath   = '../';

        include __DIR__ . '/../includes/sidebar.php';
        ?>

        <div class="main-shell">

            <header class="topbar">

                <button
                    type="button"
                    class="mobile-menu-btn"
                    id="mobileMenuBtn"
                    aria-label="เปิดเมนู">
                    <i class="bi bi-list"></i>
                </button>

                <div class="topbar-title">
                    <span class="topbar-kicker">
                        MBS • MAHASARAKHAM UNIVERSITY
                    </span>

                    <strong>ข้อมูลคาเฟ่</strong>
                </div>

                <a
                    href="javascript:history.back()"
                    class="header-back-btn">
                    <i class="bi bi-arrow-left"></i>
                    <span>ย้อนกลับ</span>
                </a>

            </header>


            <main class="content-area">

                <section class="page-hero">

                    <div>
                        <span class="hero-badge">
                            <span></span>
                            CAFE MANAGEMENT
                        </span>

                        <h1>จัดการข้อมูลคาเฟ่</h1>

                        <p>
                            จัดการข้อมูลร้านคาเฟ่รอบมหาวิทยาลัยมหาสารคาม
                        </p>
                    </div>

                    <div class="hero-decoration">
                        MBS
                    </div>

                </section>


                <div class="page-section">

                    <div class="page-actions">

                        <div>
                            <span class="section-kicker">
                                MSU CAFE DATABASE
                            </span>

                            <h2>รายการคาเฟ่</h2>

                            <p>
                                ค้นหา ตรวจสอบ เพิ่ม และแก้ไขข้อมูลคาเฟ่ในระบบ
                            </p>
                        </div>

                        <a
                            href="save.php"
                            class="btn btn-mbs-primary">
                            <i class="bi bi-plus-lg"></i>
                            เพิ่มคาเฟ่
                        </a>

                    </div>


                    <?php if (isset($_GET['success'])): ?>

                        <div class="alert alert-success alert-dismissible fade show">

                            <?php
                            switch ($_GET['success']) {
                                case 'create':
                                    echo "เพิ่มข้อมูลคาเฟ่เรียบร้อยแล้ว";
                                    break;

                                case 'edit':
                                    echo "แก้ไขข้อมูลคาเฟ่เรียบร้อยแล้ว";
                                    break;

                                case 'delete':
                                    echo "ลบข้อมูลคาเฟ่เรียบร้อยแล้ว";
                                    break;

                                default:
                                    echo "ดำเนินการเรียบร้อยแล้ว";
                            }
                            ?>

                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="alert">
                            </button>

                        </div>

                    <?php endif; ?>


                    <?php if (isset($_GET['error'])): ?>

                        <div class="alert alert-danger alert-dismissible fade show">

                            <?= e($_GET['error']) ?>

                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="alert">
                            </button>

                        </div>

                    <?php endif; ?>


                    <div class="card border-0 shadow-sm mb-4">

                        <div class="card-body">

                            <form
                                method="get"
                                class="row g-3">

                                <div class="col-lg-5">

                                    <label class="form-label">
                                        ค้นหา
                                    </label>

                                    <input
                                        type="text"
                                        name="search"
                                        class="form-control"
                                        value="<?= e($search) ?>"
                                        placeholder="ค้นหาชื่อร้าน โซน เมนู เบอร์โทร หรือช่องทางออนไลน์">

                                </div>


                                <div class="col-md-4 col-lg-3">

                                    <label class="form-label">
                                        โซน
                                    </label>

                                    <select
                                        name="zone"
                                        class="form-select">

                                        <option value="">
                                            ทุกโซน
                                        </option>

                                        <?php foreach ($zones as $item): ?>

                                            <option
                                                value="<?= e($item) ?>"
                                                <?= $zone === $item ? 'selected' : '' ?>>
                                                <?= e($item) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                                <div class="col-md-4 col-lg-2">

                                    <label class="form-label">
                                        เรียงตาม
                                    </label>

                                    <select
                                        name="sort"
                                        class="form-select"
                                        onchange="this.form.submit()">

                                        <option
                                            value="id_asc"
                                            <?= $sort === 'id_asc' ? 'selected' : '' ?>>
                                            ID น้อย → มาก
                                        </option>

                                        <option
                                            value="latest"
                                            <?= $sort === 'latest' ? 'selected' : '' ?>>
                                            เพิ่มล่าสุด
                                        </option>

                                        <option
                                            value="name_asc"
                                            <?= $sort === 'name_asc' ? 'selected' : '' ?>>
                                            ชื่อร้าน ก → ฮ
                                        </option>

                                        <option
                                            value="name_desc"
                                            <?= $sort === 'name_desc' ? 'selected' : '' ?>>
                                            ชื่อร้าน ฮ → ก
                                        </option>

                                    </select>

                                </div>


                                <div class="col-md-4 col-lg-2 d-flex align-items-end gap-2">

                                    <button
                                        type="submit"
                                        class="btn btn-mbs-primary flex-fill">
                                        ค้นหา
                                    </button>

                                    <a
                                        href="index.php"
                                        class="btn btn-outline-secondary">
                                        ล้าง
                                    </a>

                                </div>

                            </form>

                        </div>

                    </div>


                    <div class="mb-3">

                        <span class="text-secondary">
                            พบ
                        </span>

                        <strong>
                            <?= count($cafes) ?>
                        </strong>

                        <span class="text-secondary">
                            รายการ
                        </span>

                    </div>


                    <div class="card border-0 shadow-sm">

                        <div class="card-body p-0">

                            <div class="table-responsive">

                                <table class="table table-hover align-middle mb-0">

                                    <thead class="table-light">

                                        <tr>
                                            <th class="ps-3">ID</th>
                                            <th>ชื่อร้าน</th>
                                            <th>โซน</th>
                                            <th>เวลาเปิด-ปิด</th>
                                            <th>เบอร์โทร</th>
                                            <th>ช่องทางออนไลน์</th>
                                            <th class="text-center" style="min-width: 230px;">
                                                จัดการ
                                            </th>
                                        </tr>

                                    </thead>


                                    <tbody>

                                        <?php if (!$cafes): ?>

                                            <tr>
                                                <td
                                                    colspan="7"
                                                    class="text-center text-secondary py-5">
                                                    ยังไม่มีข้อมูลคาเฟ่
                                                </td>
                                            </tr>

                                        <?php endif; ?>


                                        <?php foreach ($cafes as $row): ?>

                                            <tr>

                                                <td class="ps-3">
                                                    <?= (int)$row['id'] ?>
                                                </td>

                                                <td style="min-width: 220px;">

                                                    <div class="fw-semibold">
                                                        <?= e($row['cafe_name']) ?>
                                                    </div>

                                                    <?php if (!empty($row['maps_location'])): ?>

                                                        <div class="program-alias mt-1">

                                                            <a
                                                                href="<?= e($row['maps_location']) ?>"
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                class="text-decoration-none">
                                                                <i class="bi bi-geo-alt-fill"></i>
                                                                Google Maps
                                                            </a>

                                                        </div>

                                                    <?php endif; ?>

                                                </td>

                                                <td>
                                                    <span class="badge rounded-pill text-bg-light border">
                                                        <?= e($row['zone']) ?>
                                                    </span>
                                                </td>

                                                <td style="min-width: 220px;">
                                                    <?= e($row['opening_hours']) ?>
                                                </td>

                                                <td>
                                                    <?= !empty($row['phone_number'])
                                                        ? e($row['phone_number'])
                                                        : '-' ?>
                                                </td>

                                                <td>
                                                    <?= !empty($row['online_channels'])
                                                        ? e($row['online_channels'])
                                                        : '-' ?>
                                                </td>

                                                <td>

                                                    <div class="d-flex justify-content-center gap-2 flex-wrap">

                                                        <a
                                                            href="detail.php?id=<?= (int)$row['id'] ?>"
                                                            class="btn btn-outline-primary btn-sm">

                                                            <i class="bi bi-eye"></i>
                                                            ดูข้อมูล

                                                        </a>

                                                        <a
                                                            href="save.php?id=<?= (int)$row['id'] ?>"
                                                            class="btn btn-warning btn-sm">

                                                            <i class="bi bi-pencil"></i>
                                                            แก้ไข

                                                        </a>

                                                        <form
                                                            method="post"
                                                            action="delete.php"
                                                            class="m-0"
                                                            onsubmit="return confirm('ยืนยันการลบข้อมูลคาเฟ่นี้หรือไม่?');">

                                                            <input
                                                                type="hidden"
                                                                name="id"
                                                                value="<?= (int)$row['id'] ?>">

                                                            <button
                                                                type="submit"
                                                                class="btn btn-danger btn-sm">

                                                                <i class="bi bi-trash3"></i>
                                                                ลบ

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

                    <span>
                        คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม
                    </span>
                </div>

                <span>
                    Mahasarakham Business School
                </span>

            </footer>

        </div>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const sidebar =
                document.getElementById('sidebar');

            const sidebarOverlay =
                document.getElementById('sidebarOverlay');

            const mobileMenuBtn =
                document.getElementById('mobileMenuBtn');

            const sidebarToggle =
                document.getElementById('sidebarToggle');


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


            if (
                localStorage.getItem('mbsSidebarCollapsed') === '1' &&
                window.innerWidth >= 992
            ) {

                document.body.classList.add(
                    'sidebar-collapsed'
                );
            }


            if (sidebarToggle) {

                sidebarToggle.addEventListener(
                    'click',
                    function() {

                        if (window.innerWidth < 992) {
                            return;
                        }

                        document.body.classList.toggle(
                            'sidebar-collapsed'
                        );

                        const collapsed =
                            document.body.classList.contains(
                                'sidebar-collapsed'
                            );

                        localStorage.setItem(
                            'mbsSidebarCollapsed',
                            collapsed ? '1' : '0'
                        );
                    }
                );
            }


            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener(
                    'click',
                    openSidebar
                );
            }


            if (sidebarOverlay) {
                sidebarOverlay.addEventListener(
                    'click',
                    closeSidebar
                );
            }


            window.addEventListener(
                'resize',
                function() {

                    if (window.innerWidth >= 992) {

                        closeSidebar();

                        if (
                            localStorage.getItem('mbsSidebarCollapsed') === '1'
                        ) {

                            document.body.classList.add(
                                'sidebar-collapsed'
                            );

                        } else {

                            document.body.classList.remove(
                                'sidebar-collapsed'
                            );
                        }

                    } else {

                        document.body.classList.remove(
                            'sidebar-collapsed'
                        );
                    }
                }
            );

        });
    </script>

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
    </script>

</body>

</html>