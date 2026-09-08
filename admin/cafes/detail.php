<?php

require_once "../../api/db.php";

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id) {
    exit("ID คาเฟ่ไม่ถูกต้อง");
}

$stmt = $pdo->prepare("
    SELECT *
    FROM msu_cafes
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    ':id' => $id
]);

$cafe = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cafe) {
    exit("ไม่พบข้อมูลคาเฟ่");
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
?>
<!doctype html>
<html lang="th">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">

    <title>
        รายละเอียดคาเฟ่
    </title>

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

                    <strong>
                        ข้อมูลคาเฟ่
                    </strong>

                </div>

                <a
                    href="javascript:history.back()"
                    class="header-back-btn">

                    <i class="bi bi-arrow-left"></i>

                    <span>
                        ย้อนกลับ
                    </span>

                </a>

            </header>


            <main class="content-area">

                <section class="page-hero">

                    <div>

                        <span class="hero-badge">
                            <span></span>
                            CAFE DETAIL
                        </span>

                        <h1>
                            <?= htmlspecialchars(
                                $cafe['cafe_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </h1>

                        <p>
                            <?= htmlspecialchars(
                                $cafe['zone'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </p>

                    </div>


                    <div class="hero-actions-inline">

                        <a
                            href="index.php"
                            class="btn btn-light-soft">

                            <i class="bi bi-arrow-left"></i>
                            กลับรายการคาเฟ่

                        </a>

                        <a
                            href="save.php?id=<?= (int)$cafe['id'] ?>"
                            class="btn btn-mbs-yellow">

                            <i class="bi bi-pencil-square"></i>
                            แก้ไขข้อมูล

                        </a>

                    </div>


                    <div class="hero-decoration">
                        MBS
                    </div>

                </section>


                <div class="page-section">

                    <div class="card border-0 shadow-sm mb-4">

                        <div class="card-body p-4">

                            <h2 class="h5 fw-bold mb-4">
                                ข้อมูลทั่วไป
                            </h2>

                            <div class="row g-4">

                                <div class="col-md-6">

                                    <div class="detail-label">
                                        ชื่อร้าน
                                    </div>

                                    <div>
                                        <?= showValue(
                                            $cafe['cafe_name']
                                        ) ?>
                                    </div>

                                </div>


                                <div class="col-md-6">

                                    <div class="detail-label">
                                        โซน
                                    </div>

                                    <div>
                                        <?= showValue(
                                            $cafe['zone']
                                        ) ?>
                                    </div>

                                </div>


                                <div class="col-md-6">

                                    <div class="detail-label">
                                        เวลาเปิด-ปิด
                                    </div>

                                    <div>
                                        <?= showValue(
                                            $cafe['opening_hours']
                                        ) ?>
                                    </div>

                                </div>


                                <div class="col-md-6">

                                    <div class="detail-label">
                                        เบอร์โทร
                                    </div>

                                    <div>
                                        <?= showValue(
                                            $cafe['phone_number']
                                        ) ?>
                                    </div>

                                </div>


                                <div class="col-md-6">

                                    <div class="detail-label">
                                        ช่องทางออนไลน์
                                    </div>

                                    <div>
                                        <?= showValue(
                                            $cafe['online_channels']
                                        ) ?>
                                    </div>

                                </div>


                                <div class="col-md-6">

                                    <div class="detail-label">
                                        Google Maps
                                    </div>

                                    <div>

                                        <?php if (!empty($cafe['maps_location'])): ?>

                                            <a
                                                href="<?= htmlspecialchars(
                                                            $cafe['maps_location'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-geo-alt-fill"></i>
                                                เปิดตำแหน่งใน Google Maps
                                            </a>

                                        <?php else: ?>

                                            <span class="text-secondary">
                                                -
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </div>


                                <div class="col-12">

                                    <div class="detail-label">
                                        รายละเอียดร้าน
                                    </div>

                                    <div>
                                        <?= showValue(
                                            $cafe['description']
                                        ) ?>
                                    </div>

                                </div>


                                <div class="col-12">

                                    <div class="detail-label">
                                        อาหารและเครื่องดื่ม
                                    </div>

                                    <div>
                                        <?= showValue(
                                            $cafe['food_and_drinks']
                                        ) ?>
                                    </div>

                                </div>

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

            if (
                localStorage.getItem('mbsSidebarCollapsed') === '1' &&
                window.innerWidth >= 992
            ) {
                document.body.classList.add('sidebar-collapsed');
            }

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

            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', openSidebar);
            }

            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', closeSidebar);
            }

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
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
    </script>

</body>

</html>