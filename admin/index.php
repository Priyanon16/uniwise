<?php
require_once "../api/db.php";

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function safeCount(PDO $pdo, string $table): int
{
    $allowed = [
        'academic_programs',
        'internship_types',
        'internship_periods',
        'internship_steps',
        'internship_document_requirements',
        'application_periods',
        'academic_calendar_events',
        'academic_guides',
        'faculty_contacts',
        'admission_schedules'
    ];

    if (!in_array($table, $allowed, true)) {
        return 0;
    }

    try {
        return (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

$programCount = safeCount($pdo, 'academic_programs');
$internshipTypeCount = safeCount($pdo, 'internship_types');
$periodCount = safeCount($pdo, 'internship_periods');
$stepCount = safeCount($pdo, 'internship_steps');
$documentCount = safeCount($pdo, 'internship_document_requirements');
$applicationCount = safeCount($pdo, 'application_periods');
$calendarCount = safeCount($pdo, 'academic_calendar_events');
$guideCount = safeCount($pdo, 'academic_guides');
$contactCount = safeCount($pdo, 'faculty_contacts');
$admissionCount = safeCount($pdo, 'admission_schedules');

$internshipDataCount =
    $internshipTypeCount +
    $periodCount +
    $stepCount +
    $documentCount +
    $applicationCount;

$recentActivities = [];
$activityLogReady = true;

try {
    $stmt = $pdo->query("
        SELECT
            id,
            module,
            action_type,
            item_label,
            detail,
            created_at
        FROM admin_activity_logs
        ORDER BY created_at DESC, id DESC
        LIMIT 8
    ");

    $recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $activityLogReady = false;
}

function actionLabel(string $action): string
{
    return match ($action) {
        'create' => 'เพิ่มข้อมูล',
        'update' => 'แก้ไขข้อมูล',
        'delete' => 'ลบข้อมูล',
        default => 'ดำเนินการ'
    };
}

function actionIcon(string $action): string
{
    return match ($action) {
        'create' => 'bi-plus-lg',
        'update' => 'bi-pencil',
        'delete' => 'bi-trash3',
        default => 'bi-clock-history'
    };
}

function actionClass(string $action): string
{
    return match ($action) {
        'create' => 'activity-create',
        'update' => 'activity-update',
        'delete' => 'activity-delete',
        default => 'activity-default'
    };
}

function moduleLabel(string $module): string
{
    return match ($module) {

        'programs' =>
        'หลักสูตรและสาขาวิชา',

        'internship' =>
        'ฝึกงานและสหกิจศึกษา',

        'calendar' =>
        'ปฏิทินการศึกษา',

        'academic_guide' =>
        'คู่มือการเรียน',

        'contact' =>
        'ข้อมูลติดต่อคณะ',

        'admission' =>
        'รับสมัครนักศึกษา',

        default =>
        $module
    };
}
?>
<!doctype html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>MBS UniWise Admin Dashboard</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- CSS กลางของระบบ Admin -->
    <link
        rel="stylesheet"
        href="assets/admin.css?v=<?= filemtime(__DIR__ . '/assets/admin.css') ?>">

    <!-- CSS เฉพาะ Dashboard -->
    <link
        rel="stylesheet"
        href="assets/dashboard.css?v=<?= filemtime(__DIR__ . '/assets/dashboard.css') ?>">
</head>

<body>

    <div class="admin-layout">

        <?php
        $activeMenu = 'dashboard';
        $basePath = '';

        require __DIR__ . '/includes/sidebar.php';
        ?>


        <!-- =========================
         Main
    ========================== -->
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
                    <strong>ระบบจัดการข้อมูล MBS UniWise</strong>
                </div>

                <div class="topbar-status">
                    <span class="status-dot"></span>
                    <span>ระบบพร้อมใช้งาน</span>
                </div>

            </header>


            <main class="content-area">

                <!-- Hero -->
                <section class="dashboard-hero">

                    <div class="hero-content">
                        <span class="hero-badge">
                            <span></span>
                            ADMIN DASHBOARD
                        </span>

                        <h1>
                            ยินดีต้อนรับสู่<br>
                            MBS UniWise Admin
                        </h1>

                        <p>
                            ระบบจัดการฐานข้อมูลของคณะการบัญชีและการจัดการ
                            มหาวิทยาลัยมหาสารคาม
                        </p>
                    </div>

                    <div class="hero-decoration">
                        MBS
                    </div>

                </section>


                <!-- Stats -->
                <section class="stats-grid">

                    <a href="programs/index.php" class="stat-card">
                        <div class="stat-icon blue">
                            <i class="bi bi-mortarboard-fill"></i>
                        </div>

                        <div class="stat-content">
                            <span>หลักสูตรทั้งหมด</span>
                            <strong><?= $programCount ?></strong>
                            <small>รายการในฐานข้อมูล</small>
                        </div>

                        <i class="bi bi-arrow-up-right stat-arrow"></i>
                    </a>


                    <a href="internship_admin/index.php" class="stat-card">
                        <div class="stat-icon yellow">
                            <i class="bi bi-briefcase-fill"></i>
                        </div>

                        <div class="stat-content">
                            <span>ข้อมูลฝึกงาน</span>
                            <strong><?= $internshipDataCount ?></strong>
                            <small>ข้อมูลทุกหมวดรวมกัน</small>
                        </div>

                        <i class="bi bi-arrow-up-right stat-arrow"></i>
                    </a>
                    <a href="calendar/index.php" class="stat-card">

                        <div class="stat-icon calendar">
                            <i class="bi bi-calendar-event-fill"></i>
                        </div>

                        <div class="stat-content">

                            <span>ปฏิทินการศึกษา</span>

                            <strong>
                                <?= $calendarCount ?>
                            </strong>

                            <small>
                                กิจกรรมในปฏิทิน
                            </small>

                        </div>

                        <i class="bi bi-arrow-up-right stat-arrow"></i>

                    </a>

                    <a href="admissions/index.php" class="stat-card">

                        <div class="stat-icon cyan">
                            <i class="bi bi-person-vcard-fill"></i>
                        </div>

                        <div class="stat-content">

                            <span>กำหนดการรับสมัคร</span>

                            <strong>
                                <?= $admissionCount ?>
                            </strong>

                            <small>
                                รายการในฐานข้อมูล
                            </small>

                        </div>

                        <i class="bi bi-arrow-up-right stat-arrow"></i>

                    </a>


                    <div class="stat-card">
                        <div class="stat-icon cyan">
                            <i class="bi bi-list-check"></i>
                        </div>

                        <div class="stat-content">
                            <span>ขั้นตอนฝึกงาน</span>
                            <strong><?= $stepCount ?></strong>
                            <small>ขั้นตอนในระบบ</small>
                        </div>
                    </div>


                    <div class="stat-card">
                        <div class="stat-icon navy">
                            <i class="bi bi-file-earmark-text-fill"></i>
                        </div>

                        <div class="stat-content">
                            <span>เอกสารฝึกงาน</span>
                            <strong><?= $documentCount ?></strong>
                            <small>เอกสารที่กำหนดไว้</small>
                        </div>
                    </div>

                </section>


                <!-- Main dashboard content -->
                <div class="dashboard-grid">

                    <!-- Quick menu -->
                    <section class="dashboard-card">

                        <div class="card-heading">
                            <div>
                                <span class="section-kicker">QUICK ACCESS</span>
                                <h2>จัดการข้อมูล</h2>
                                <p>เลือกส่วนที่ต้องการแก้ไขได้จากเมนูด้านล่าง</p>
                            </div>
                        </div>

                        <div class="quick-grid">

                            <a href="internship_admin/index.php" class="quick-card">
                                <div class="quick-icon internship">
                                    <i class="bi bi-briefcase-fill"></i>
                                </div>

                                <div>
                                    <h3>ฝึกงานและสหกิจศึกษา</h3>
                                    <p>
                                        ประเภทการฝึกงาน ขั้นตอน เอกสาร
                                        ช่วงปฏิบัติงาน และช่วงรับสมัคร
                                    </p>
                                </div>

                                <span class="quick-go">
                                    <i class="bi bi-arrow-right"></i>
                                </span>
                            </a>


                            <a href="programs/index.php" class="quick-card">
                                <div class="quick-icon program">
                                    <i class="bi bi-mortarboard-fill"></i>
                                </div>

                                <div>
                                    <h3>หลักสูตรและสาขาวิชา</h3>
                                    <p>
                                        ข้อมูลหลักสูตร ระดับการศึกษา
                                        สาขาวิชา Alias และรายละเอียดหลักสูตร
                                    </p>
                                </div>

                                <span class="quick-go">
                                    <i class="bi bi-arrow-right"></i>
                                </span>
                            </a>

                            <a href="admissions/index.php" class="quick-card">

                                <div class="quick-icon calendar">
                                    <i class="bi bi-person-vcard-fill"></i>
                                </div>

                                <div>

                                    <h3>รับสมัครนักศึกษา</h3>

                                    <p>
                                        จัดการรอบรับสมัคร โครงการ
                                        กำหนดการ สัมภาษณ์ ประกาศผล
                                        ยืนยันสิทธิ์ และรายงานตัว
                                    </p>

                                </div>

                                <span class="quick-go">
                                    <i class="bi bi-arrow-right"></i>
                                </span>

                            </a>

                            <a href="calendar/index.php" class="quick-card">

                                <div class="quick-icon calendar">
                                    <i class="bi bi-calendar-event-fill"></i>
                                </div>

                                <div>

                                    <h3>
                                        ปฏิทินการศึกษา
                                    </h3>

                                    <p>
                                        จัดการวันลงทะเบียน เปิดภาคการศึกษา
                                        สอบกลางภาค สอบปลายภาค ถอนรายวิชา
                                        แจ้งจบ และกำหนดการทางการศึกษา
                                    </p>

                                </div>

                                <span class="quick-go">
                                    <i class="bi bi-arrow-right"></i>
                                </span>

                            </a>

                            <a href="academic_guides/index.php" class="quick-card">

                                <div class="quick-icon program">
                                    <i class="bi bi-journal-text"></i>
                                </div>

                                <div>
                                    <h3>คู่มือการเรียน</h3>
                                    <p>
                                        จัดการหัวข้อคู่มือ ขั้นตอน เอกสาร
                                        และกำหนดการที่เกี่ยวข้องกับงานวิชาการ
                                    </p>
                                </div>

                                <span class="quick-go">
                                    <i class="bi bi-arrow-right"></i>
                                </span>

                            </a>


                            <a href="contacts/index.php" class="quick-card">

                                <div class="quick-icon calendar">
                                    <i class="bi bi-telephone-fill"></i>
                                </div>

                                <div>
                                    <h3>ข้อมูลติดต่อคณะ</h3>
                                    <p>
                                        จัดการเบอร์โทร อีเมล เว็บไซต์
                                        Facebook และเวลาทำการของคณะ
                                    </p>
                                </div>

                                <span class="quick-go">
                                    <i class="bi bi-arrow-right"></i>
                                </span>

                            </a>

                        </div>

                    </section>


                    <!-- Activity -->
                    <section class="dashboard-card activity-card">

                        <div class="card-heading activity-heading">
                            <div>
                                <span class="section-kicker">RECENT ACTIVITY</span>
                                <h2>การแก้ไขล่าสุด</h2>
                                <p>รายการเพิ่ม แก้ไข และลบข้อมูลล่าสุดในระบบ</p>
                            </div>

                            <span class="live-badge">
                                <span></span>
                                ล่าสุด
                            </span>
                        </div>


                        <?php if (!$activityLogReady): ?>

                            <div class="activity-empty">
                                <div class="activity-empty-icon">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                                <h3>ยังไม่ได้เปิดระบบบันทึกกิจกรรม</h3>
                                <p>
                                    ให้รันไฟล์ SQL ที่แนบมาด้วยก่อน
                                    แล้วระบบจะแสดงประวัติการแก้ไขที่นี่
                                </p>
                            </div>

                        <?php elseif (!$recentActivities): ?>

                            <div class="activity-empty">
                                <div class="activity-empty-icon">
                                    <i class="bi bi-check2-circle"></i>
                                </div>
                                <h3>ยังไม่มีกิจกรรมล่าสุด</h3>
                                <p>
                                    เมื่อมีการเพิ่ม แก้ไข หรือลบข้อมูล
                                    รายการจะปรากฏบริเวณนี้
                                </p>
                            </div>

                        <?php else: ?>

                            <div class="activity-list">

                                <?php foreach ($recentActivities as $activity): ?>

                                    <div class="activity-item">

                                        <div class="activity-icon <?= e(actionClass($activity['action_type'])) ?>">
                                            <i class="bi <?= e(actionIcon($activity['action_type'])) ?>"></i>
                                        </div>

                                        <div class="activity-content">

                                            <div class="activity-main">
                                                <strong>
                                                    <?= e(actionLabel($activity['action_type'])) ?>
                                                </strong>

                                                <span class="activity-module">
                                                    <?= e(moduleLabel($activity['module'])) ?>
                                                </span>
                                            </div>

                                            <div class="activity-name">
                                                <?= e($activity['item_label'] ?: '-') ?>
                                            </div>

                                            <?php if (!empty($activity['detail'])): ?>
                                                <p><?= e($activity['detail']) ?></p>
                                            <?php endif; ?>

                                            <time>
                                                <i class="bi bi-clock"></i>
                                                <?= e(date('d/m/Y H:i', strtotime($activity['created_at']))) ?> น.
                                            </time>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php endif; ?>

                    </section>

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
        document.addEventListener('DOMContentLoaded', function() {

            const sidebar =
                document.getElementById('sidebar');

            const mobileMenuBtn =
                document.getElementById('mobileMenuBtn');

            const sidebarOverlay =
                document.getElementById('sidebarOverlay');

            const sidebarToggle =
                document.getElementById('sidebarToggle');


            // =====================================================
            // Mobile Sidebar
            // =====================================================

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


            // =====================================================
            // Desktop Sidebar Collapse
            // =====================================================

            const savedSidebarState =
                localStorage.getItem(
                    'mbsSidebarCollapsed'
                );


            // โหลดสถานะเดิมเฉพาะ Desktop
            if (
                savedSidebarState === '1' &&
                window.innerWidth > 991
            ) {

                document.body.classList.add(
                    'sidebar-collapsed'
                );
            }


            if (sidebarToggle) {

                sidebarToggle.addEventListener(
                    'click',
                    function() {

                        // บน Desktop = ย่อ / ขยาย
                        if (window.innerWidth > 991) {

                            document.body.classList.toggle(
                                'sidebar-collapsed'
                            );


                            const isCollapsed =
                                document.body.classList.contains(
                                    'sidebar-collapsed'
                                );


                            localStorage.setItem(
                                'mbsSidebarCollapsed',
                                isCollapsed ? '1' : '0'
                            );

                            return;
                        }


                        // บน Mobile = เปิด / ปิด Drawer
                        if (
                            sidebar &&
                            sidebar.classList.contains('show')
                        ) {

                            closeSidebar();

                        } else {

                            openSidebar();
                        }
                    }
                );
            }


            // =====================================================
            // Resize
            // =====================================================

            window.addEventListener(
                'resize',
                function() {

                    // ถ้ากลับมา Desktop
                    if (window.innerWidth > 991) {

                        closeSidebar();

                        const saved =
                            localStorage.getItem(
                                'mbsSidebarCollapsed'
                            );


                        if (saved === '1') {

                            document.body.classList.add(
                                'sidebar-collapsed'
                            );

                        } else {

                            document.body.classList.remove(
                                'sidebar-collapsed'
                            );
                        }

                    } else {

                        // Mobile ไม่ใช้ collapsed mode
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