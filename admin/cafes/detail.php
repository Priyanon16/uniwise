<?php
require_once "../../api/db.php";
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('รหัสข้อมูลไม่ถูกต้อง');
}
$stmt = $pdo->prepare("SELECT * FROM msu_cafes WHERE id=:id LIMIT 1");
$stmt->execute([':id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    http_response_code(404);
    exit('ไม่พบข้อมูลร้านคาเฟ่');
}
function e(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($row['cafe_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/cafes.css">
</head>

<body>
    <div class="page-shell page-shell-form">
        <div class="page-header">
            <div><span class="section-kicker">CAFE DETAIL</span>
                <h1><?= e($row['cafe_name']) ?></h1>
                <p>รายละเอียดข้อมูลร้านคาเฟ่ในระบบ</p>
            </div>
            <div class="d-flex gap-2"><a href="index.php" class="btn btn-outline-secondary">กลับรายการ</a><a href="save.php?id=<?= $id ?>" class="btn btn-mbs-primary">แก้ไข</a></div>
        </div><?php if (isset($_GET['saved'])): ?><div class="alert alert-success">บันทึกข้อมูลเรียบร้อยแล้ว</div><?php endif; ?><div class="detail-card">
            <div class="detail-top">
                <div class="detail-icon"><i class="bi bi-cup-hot-fill"></i></div>
                <div><span class="id-badge">#<?= $id ?></span>
                    <h2><?= e($row['cafe_name']) ?></h2><span class="zone-badge"><?= e($row['zone']) ?></span>
                </div>
            </div>
            <div class="detail-grid">
                <div class="detail-item"><span>เวลาเปิด-ปิด</span><strong><?= nl2br(e($row['opening_hours'])) ?></strong></div>
                <div class="detail-item"><span>เบอร์โทร</span><strong><?= e($row['phone_number'] ?: '-') ?></strong></div>
                <div class="detail-item"><span>ช่องทางออนไลน์</span><strong><?= e($row['online_channels'] ?: '-') ?></strong></div>
                <div class="detail-item"><span>ตำแหน่งแผนที่</span><strong><?= e($row['maps_location'] ?: '-') ?></strong></div>
            </div>
            <div class="detail-section">
                <h3>รายละเอียดร้าน</h3>
                <p><?= nl2br(e($row['description'] ?: '-')) ?></p>
            </div>
            <div class="detail-section">
                <h3>อาหารและเครื่องดื่ม</h3>
                <p><?= nl2br(e($row['food_and_drinks'] ?: '-')) ?></p>
            </div>
        </div>
    </div>
</body>

</html>