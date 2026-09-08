<?php

require_once "../../api/db.php";
require_once "../admin_activity.php";

function cleanString($value): string
{
    return trim((string)($value ?? ''));
}

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

$isEdit = false;
$error = '';

$cafe = [
    'cafe_name' => '',
    'opening_hours' => '',
    'description' => '',
    'food_and_drinks' => '',
    'phone_number' => '',
    'online_channels' => '',
    'zone' => '',
    'maps_location' => ''
];

if ($id) {

    $stmt = $pdo->prepare("
        SELECT *
        FROM msu_cafes
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $id
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        exit("ไม่พบข้อมูลคาเฟ่");
    }

    $cafe = array_merge(
        $cafe,
        $row
    );

    $isEdit = true;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedId = filter_input(
        INPUT_POST,
        'id',
        FILTER_VALIDATE_INT
    );

    $cafeName       = cleanString($_POST['cafe_name'] ?? '');
    $openingHours   = cleanString($_POST['opening_hours'] ?? '');
    $description    = cleanString($_POST['description'] ?? '');
    $foodAndDrinks  = cleanString($_POST['food_and_drinks'] ?? '');
    $phoneNumber    = cleanString($_POST['phone_number'] ?? '');
    $onlineChannels = cleanString($_POST['online_channels'] ?? '');
    $zone           = cleanString($_POST['zone'] ?? '');
    $mapsLocation   = cleanString($_POST['maps_location'] ?? '');

    $cafe = [
        'cafe_name' => $cafeName,
        'opening_hours' => $openingHours,
        'description' => $description,
        'food_and_drinks' => $foodAndDrinks,
        'phone_number' => $phoneNumber,
        'online_channels' => $onlineChannels,
        'zone' => $zone,
        'maps_location' => $mapsLocation
    ];


    if (
        $cafeName === '' ||
        $openingHours === '' ||
        $zone === ''
    ) {

        $error =
            "กรุณากรอกข้อมูลที่จำเป็นให้ครบ";
    } else {

        try {

            $pdo->beginTransaction();

            if ($postedId) {

                $stmt = $pdo->prepare("
                    UPDATE msu_cafes
                    SET
                        cafe_name = :cafe_name,
                        opening_hours = :opening_hours,
                        description = :description,
                        food_and_drinks = :food_and_drinks,
                        phone_number = :phone_number,
                        online_channels = :online_channels,
                        zone = :zone,
                        maps_location = :maps_location
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':cafe_name' => $cafeName,
                    ':opening_hours' => $openingHours,
                    ':description' => $description ?: null,
                    ':food_and_drinks' => $foodAndDrinks ?: null,
                    ':phone_number' => $phoneNumber ?: null,
                    ':online_channels' => $onlineChannels ?: null,
                    ':zone' => $zone,
                    ':maps_location' => $mapsLocation ?: null,
                    ':id' => $postedId
                ]);

                $cafeId = (int)$postedId;
            } else {

                $stmt = $pdo->prepare("
                    INSERT INTO msu_cafes
                    (
                        cafe_name,
                        opening_hours,
                        description,
                        food_and_drinks,
                        phone_number,
                        online_channels,
                        zone,
                        maps_location
                    )
                    VALUES
                    (
                        :cafe_name,
                        :opening_hours,
                        :description,
                        :food_and_drinks,
                        :phone_number,
                        :online_channels,
                        :zone,
                        :maps_location
                    )
                ");

                $stmt->execute([
                    ':cafe_name' => $cafeName,
                    ':opening_hours' => $openingHours,
                    ':description' => $description ?: null,
                    ':food_and_drinks' => $foodAndDrinks ?: null,
                    ':phone_number' => $phoneNumber ?: null,
                    ':online_channels' => $onlineChannels ?: null,
                    ':zone' => $zone,
                    ':maps_location' => $mapsLocation ?: null
                ]);

                $cafeId =
                    (int)$pdo->lastInsertId();
            }


            logAdminActivity(
                $pdo,
                'cafes',
                $postedId ? 'update' : 'create',
                $cafeName,
                ($postedId ? 'แก้ไข' : 'เพิ่ม') .
                    'ข้อมูลคาเฟ่ ' .
                    $cafeName
            );


            $pdo->commit();


            header(
                "Location: index.php?success=" .
                    ($postedId ? 'edit' : 'create')
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

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
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
        <?= $isEdit
            ? 'แก้ไขข้อมูลคาเฟ่'
            : 'เพิ่มข้อมูลคาเฟ่'
        ?>
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
                            CAFE EDITOR
                        </span>

                        <h1>
                            <?= $isEdit
                                ? 'แก้ไขข้อมูลคาเฟ่'
                                : 'เพิ่มข้อมูลคาเฟ่'
                            ?>
                        </h1>

                        <p>
                            กรอกข้อมูลร้านคาเฟ่ให้ครบถ้วนก่อนบันทึก
                        </p>

                    </div>

                    <div class="hero-decoration">
                        MBS
                    </div>

                </section>


                <div class="page-section">

                    <div class="editor-toolbar">

                        <a
                            href="index.php"
                            class="btn btn-outline-secondary">

                            <i class="bi bi-arrow-left"></i>
                            กลับหน้าคาเฟ่

                        </a>

                        <span>
                            <?= $isEdit
                                ? 'กำลังแก้ไขข้อมูลเดิม'
                                : 'กำลังสร้างข้อมูลคาเฟ่ใหม่'
                            ?>
                        </span>

                    </div>


                    <?php if ($error): ?>

                        <div class="alert alert-danger">
                            <?= e($error) ?>
                        </div>

                    <?php endif; ?>


                    <form method="post">

                        <?php if ($isEdit): ?>

                            <input
                                type="hidden"
                                name="id"
                                value="<?= (int)$id ?>">

                        <?php endif; ?>


                        <div class="card border-0 shadow-sm mb-4">

                            <div class="card-body p-4">

                                <h2 class="h5 fw-bold mb-4">
                                    1. ข้อมูลทั่วไป
                                </h2>

                                <div class="row g-3">

                                    <div class="col-md-8">

                                        <label class="form-label fw-semibold">
                                            ชื่อร้าน
                                            <span class="required-star">*</span>
                                        </label>

                                        <input
                                            type="text"
                                            name="cafe_name"
                                            class="form-control"
                                            value="<?= e($cafe['cafe_name']) ?>"
                                            placeholder="เช่น The Tree Café"
                                            required>

                                    </div>


                                    <div class="col-md-4">

                                        <label class="form-label fw-semibold">
                                            โซน
                                            <span class="required-star">*</span>
                                        </label>

                                        <input
                                            type="text"
                                            name="zone"
                                            class="form-control"
                                            value="<?= e($cafe['zone']) ?>"
                                            placeholder="เช่น ฝั่งขามเรียง"
                                            required>

                                    </div>


                                    <div class="col-12">

                                        <label class="form-label fw-semibold">
                                            เวลาเปิด-ปิด
                                            <span class="required-star">*</span>
                                        </label>

                                        <textarea
                                            name="opening_hours"
                                            class="form-control"
                                            rows="3"
                                            placeholder="เช่น 08:00 - 22:00 น."
                                            required><?= e($cafe['opening_hours']) ?></textarea>

                                    </div>


                                    <div class="col-12">

                                        <label class="form-label">
                                            รายละเอียดร้าน
                                        </label>

                                        <textarea
                                            name="description"
                                            class="form-control"
                                            rows="4"
                                            placeholder="รายละเอียด จุดเด่น บรรยากาศของร้าน"><?= e($cafe['description']) ?></textarea>

                                    </div>


                                    <div class="col-12">

                                        <label class="form-label">
                                            อาหารและเครื่องดื่ม
                                        </label>

                                        <textarea
                                            name="food_and_drinks"
                                            class="form-control"
                                            rows="4"
                                            placeholder="เมนูอาหาร เครื่องดื่ม ของหวาน หรือเบเกอรี่"><?= e($cafe['food_and_drinks']) ?></textarea>

                                    </div>

                                </div>

                            </div>

                        </div>


                        <div class="card border-0 shadow-sm mb-4">

                            <div class="card-body p-4">

                                <h2 class="h5 fw-bold mb-4">
                                    2. ช่องทางติดต่อและตำแหน่ง
                                </h2>

                                <div class="row g-3">

                                    <div class="col-md-6">

                                        <label class="form-label">
                                            เบอร์โทร
                                        </label>

                                        <input
                                            type="text"
                                            name="phone_number"
                                            class="form-control"
                                            value="<?= e($cafe['phone_number']) ?>"
                                            placeholder="เช่น 081 234 5678">

                                    </div>


                                    <div class="col-md-6">

                                        <label class="form-label">
                                            ช่องทางออนไลน์
                                        </label>

                                        <input
                                            type="text"
                                            name="online_channels"
                                            class="form-control"
                                            value="<?= e($cafe['online_channels']) ?>"
                                            placeholder="เช่น LINEMAN, GRAB">

                                    </div>


                                    <div class="col-12">

                                        <label class="form-label">
                                            Google Maps
                                        </label>

                                        <input
                                            type="url"
                                            name="maps_location"
                                            class="form-control"
                                            value="<?= e($cafe['maps_location']) ?>"
                                            placeholder="https://maps.app.goo.gl/...">

                                        <div class="form-text">
                                            วางลิงก์ตำแหน่งร้านจาก Google Maps
                                        </div>

                                    </div>

                                </div>

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
                                บันทึกข้อมูล
                            </button>

                        </div>

                    </form>

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