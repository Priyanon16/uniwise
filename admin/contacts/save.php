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

$allowedTypes = ['phone', 'email', 'website', 'facebook', 'office_hours'];

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

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$isEdit = false;
$error = '';

$contact = [
    'contact_type' => 'phone',
    'contact_label' => '',
    'contact_value' => '',
    'description' => '',
    'sort_order' => 0,
    'active' => 1
];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM faculty_contacts WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        exit('ไม่พบข้อมูลติดต่อ');
    }

    $contact = array_merge($contact, $row);
    $isEdit = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $contactType = cleanString($_POST['contact_type'] ?? '');
    $contactLabel = cleanString($_POST['contact_label'] ?? '');
    $contactValue = cleanString($_POST['contact_value'] ?? '');
    $description = cleanString($_POST['description'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $active = isset($_POST['active']) ? 1 : 0;

    $contact = [
        'contact_type' => $contactType,
        'contact_label' => $contactLabel,
        'contact_value' => $contactValue,
        'description' => $description,
        'sort_order' => $sortOrder,
        'active' => $active
    ];

    if (!in_array($contactType, $allowedTypes, true)) {
        $error = 'ประเภทข้อมูลติดต่อไม่ถูกต้อง';
    } elseif ($contactLabel === '' || $contactValue === '') {
        $error = 'กรุณากรอกชื่อแสดงผลและข้อมูลติดต่อให้ครบ';
    } else {
        try {
            $pdo->beginTransaction();

            if ($postedId) {
                $stmt = $pdo->prepare("
                    UPDATE faculty_contacts
                    SET
                        contact_type = :contact_type,
                        contact_label = :contact_label,
                        contact_value = :contact_value,
                        description = :description,
                        sort_order = :sort_order,
                        active = :active
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':contact_type' => $contactType,
                    ':contact_label' => $contactLabel,
                    ':contact_value' => $contactValue,
                    ':description' => $description ?: null,
                    ':sort_order' => $sortOrder,
                    ':active' => $active,
                    ':id' => $postedId
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO faculty_contacts
                    (contact_type, contact_label, contact_value, description, sort_order, active)
                    VALUES (:contact_type, :contact_label, :contact_value, :description, :sort_order, :active)
                ");
                $stmt->execute([
                    ':contact_type' => $contactType,
                    ':contact_label' => $contactLabel,
                    ':contact_value' => $contactValue,
                    ':description' => $description ?: null,
                    ':sort_order' => $sortOrder,
                    ':active' => $active
                ]);
            }

            logAdminActivity(
                $pdo,
                'contact',
                $postedId ? 'update' : 'create',
                $contactLabel,
                ($postedId ? 'แก้ไข' : 'เพิ่ม') . 'ข้อมูลติดต่อประเภท ' . typeLabel($contactType)
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
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $isEdit ? 'แก้ไขข้อมูลติดต่อ' : 'เพิ่มข้อมูลติดต่อ' ?></title>

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
        <span class="hero-badge"><span></span> CONTACT EDITOR</span>
        <h1><?= $isEdit ? 'แก้ไขข้อมูลติดต่อ' : 'เพิ่มข้อมูลติดต่อ' ?></h1>
        <p>ข้อมูลที่บันทึกในส่วนนี้จะถูกใช้โดย Contact API</p>
    </div>
    <div class="hero-decoration">MBS</div>
</section>

<div class="page-section">
    <div class="editor-toolbar">
        <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> กลับรายการข้อมูลติดต่อ</a>
        <span><?= $isEdit ? 'กำลังแก้ไขข้อมูลเดิม' : 'กำลังเพิ่มข้อมูลใหม่' ?></span>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int)$contact['id'] ?>">
        <?php endif; ?>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h2 class="h5 fw-bold mb-4">ข้อมูลติดต่อ</h2>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">ประเภท <span class="required-star">*</span></label>
                        <select name="contact_type" class="form-select" required>
                            <?php foreach ($allowedTypes as $type): ?>
                                <option value="<?= e($type) ?>" <?= $contact['contact_type'] === $type ? 'selected' : '' ?>><?= e(typeLabel($type)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">ลำดับ</label>
                        <input type="number" min="0" name="sort_order" class="form-control" value="<?= (int)$contact['sort_order'] ?>">
                    </div>

                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input type="checkbox" name="active" class="form-check-input" id="active" <?= (int)$contact['active'] === 1 ? 'checked' : '' ?>>
                            <label for="active" class="form-check-label">เปิดใช้งานข้อมูลนี้</label>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">ชื่อแสดงผล <span class="required-star">*</span></label>
                        <input type="text" name="contact_label" class="form-control" value="<?= e($contact['contact_label']) ?>" placeholder="เช่น โทรศัพท์" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">ข้อมูลติดต่อ <span class="required-star">*</span></label>
                        <input type="text" name="contact_value" class="form-control" value="<?= e($contact['contact_value']) ?>" placeholder="เช่น 043-719800 ต่อ 5614, 5628" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label">รายละเอียดเพิ่มเติม</label>
                        <textarea name="description" class="form-control" rows="3"><?= e($contact['description']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-5">
            <a href="index.php" class="btn btn-outline-secondary px-4">ยกเลิก</a>
            <button type="submit" class="btn btn-primary px-5">บันทึกข้อมูล</button>
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
