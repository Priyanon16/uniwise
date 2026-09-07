<?php

/*
|--------------------------------------------------------------------------
| MBS UniWise Admin - Shared Sidebar
|--------------------------------------------------------------------------
|
| $activeMenu
|   dashboard        = แดชบอร์ด
|   internship       = ฝึกงานและสหกิจศึกษา
|   programs         = หลักสูตรและสาขาวิชา
|   admissions       = รับสมัครนักศึกษา
|   calendar         = ปฏิทินการศึกษา
|   academic_guides  = คู่มือการเรียน
|   contacts         = ข้อมูลติดต่อคณะ
|
| $basePath
|   admin/index.php                  = ''
|   admin/programs/index.php         = '../'
|   admin/internship_admin/index.php = '../'
|   admin/admissions/index.php       = '../'
|   admin/calendar/index.php         = '../'
|
*/

$activeMenu = $activeMenu ?? '';
$basePath   = $basePath ?? '';

?>

<aside class="sidebar" id="sidebar">

    <!-- ========================================
         LOGO / BRAND
    ========================================= -->

    <div class="sidebar-brand">

        <div class="brand-logo">
            MBS
        </div>

        <div class="brand-copy">

            <strong>
                MBS UniWise
            </strong>

            <span>
                Admin Management
            </span>

        </div>

    </div>


    <!-- ========================================
         SIDEBAR TOGGLE
    ========================================= -->

    <button
        type="button"
        class="sidebar-toggle"
        id="sidebarToggle"
        aria-label="ย่อหรือขยายเมนู"
        title="ย่อ / ขยายเมนู"
    >

        <i
            class="bi bi-chevron-left"
            id="sidebarToggleIcon"
        ></i>

    </button>


    <div class="sidebar-divider"></div>


    <!-- ========================================
         MAIN MENU
    ========================================= -->

    <div class="sidebar-section-label">
        เมนูหลัก
    </div>


    <nav class="sidebar-nav">

        <!-- Dashboard -->

        <a
            href="<?= $basePath ?>index.php"
            class="
                sidebar-link
                <?= $activeMenu === 'dashboard'
                    ? 'active'
                    : ''
                ?>
            "
            title="แดชบอร์ด"
        >

            <span class="sidebar-icon">

                <i class="bi bi-grid-1x2-fill"></i>

            </span>

            <span class="sidebar-text">
                แดชบอร์ด
            </span>

        </a>


        <!-- Internship -->

        <a
            href="<?= $basePath ?>internship_admin/index.php"
            class="
                sidebar-link
                <?= $activeMenu === 'internship'
                    ? 'active'
                    : ''
                ?>
            "
            title="ฝึกงานและสหกิจศึกษา"
        >

            <span class="sidebar-icon">

                <i class="bi bi-briefcase-fill"></i>

            </span>

            <span class="sidebar-text">
                ฝึกงานและสหกิจศึกษา
            </span>

        </a>


        <!-- Programs -->

        <a
            href="<?= $basePath ?>programs/index.php"
            class="
                sidebar-link
                <?= $activeMenu === 'programs'
                    ? 'active'
                    : ''
                ?>
            "
            title="หลักสูตรและสาขาวิชา"
        >

            <span class="sidebar-icon">

                <i class="bi bi-mortarboard-fill"></i>

            </span>

            <span class="sidebar-text">
                หลักสูตรและสาขาวิชา
            </span>

        </a>


        <!-- Admissions -->

        <a
            href="<?= $basePath ?>admissions/index.php"
            class="
                sidebar-link
                <?= $activeMenu === 'admissions'
                    ? 'active'
                    : ''
                ?>
            "
            title="รับสมัครนักศึกษา"
        >

            <span class="sidebar-icon">

                <i class="bi bi-person-vcard-fill"></i>

            </span>

            <span class="sidebar-text">
                รับสมัครนักศึกษา
            </span>

        </a>


        <!-- Academic Calendar -->

        <a
            href="<?= $basePath ?>calendar/index.php"
            class="
                sidebar-link
                <?= $activeMenu === 'calendar'
                    ? 'active'
                    : ''
                ?>
            "
            title="ปฏิทินการศึกษา"
        >

            <span class="sidebar-icon">

                <i class="bi bi-calendar-event-fill"></i>

            </span>

            <span class="sidebar-text">
                ปฏิทินการศึกษา
            </span>

        </a>


        <!-- Academic Guide -->

        <a
            href="<?= $basePath ?>academic_guides/index.php"
            class="
                sidebar-link
                <?= $activeMenu === 'academic_guides'
                    ? 'active'
                    : ''
                ?>
            "
            title="คู่มือการเรียน"
        >

            <span class="sidebar-icon">

                <i class="bi bi-journal-text"></i>

            </span>

            <span class="sidebar-text">
                คู่มือการเรียน
            </span>

        </a>


        <!-- Faculty Contact -->

        <a
            href="<?= $basePath ?>contacts/index.php"
            class="
                sidebar-link
                <?= $activeMenu === 'contacts'
                    ? 'active'
                    : ''
                ?>
            "
            title="ข้อมูลติดต่อคณะ"
        >

            <span class="sidebar-icon">

                <i class="bi bi-telephone-fill"></i>

            </span>

            <span class="sidebar-text">
                ข้อมูลติดต่อคณะ
            </span>

        </a>

    </nav>


    <!-- ========================================
         SYSTEM MENU
    ========================================= -->

    <div
        class="
            sidebar-section-label
            sidebar-system-label
        "
    >
        ระบบ
    </div>


    <nav class="sidebar-nav">

        <a
            href="<?= $basePath ?>../"
            class="sidebar-link"
            title="กลับหน้าเว็บไซต์"
        >

            <span class="sidebar-icon">

                <i class="bi bi-box-arrow-up-right"></i>

            </span>

            <span class="sidebar-text">
                กลับหน้าเว็บไซต์
            </span>

        </a>

    </nav>


    <!-- ========================================
         FACULTY ADMIN
    ========================================= -->

    <div class="sidebar-footer">

        <div class="faculty-badge">

            <span class="faculty-dot"></span>

            <div class="faculty-info">

                <strong>
                    Faculty Admin
                </strong>

                <span>
                    Mahasarakham Business School
                </span>

            </div>

        </div>

    </div>

</aside>


<!-- ========================================
     MOBILE OVERLAY
========================================= -->

<div
    class="sidebar-overlay"
    id="sidebarOverlay"
></div>