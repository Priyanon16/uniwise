<?php
require_once "../../api/db.php";
$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$data = ['cafe_name' => '', 'opening_hours' => '', 'description' => '', 'food_and_drinks' => '', 'phone_number' => '', 'online_channels' => '', 'zone' => '', 'maps_location' => ''];
$errors = [];
if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM msu_cafes WHERE id=:id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        exit('ไม่พบข้อมูลร้านคาเฟ่');
    }
    $data = array_merge($data, $row);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $isEdit = $id > 0;
    foreach ($data as $k => $v) {
        $data[$k] = trim($_POST[$k] ?? '');
    }
    if ($data['cafe_name'] === '') $errors['cafe_name'] = 'กรุณากรอกชื่อร้าน';
    if ($data['opening_hours'] === '') $errors['opening_hours'] = 'กรุณากรอกเวลาเปิด-ปิด';
    if ($data['zone'] === '') $errors['zone'] = 'กรุณาระบุโซน';
    if (!$errors) {
        $params = [':cafe_name' => $data['cafe_name'], ':opening_hours' => $data['opening_hours'], ':description' => $data['description'] ?: null, ':food_and_drinks' => $data['food_and_drinks'] ?: null, ':phone_number' => $data['phone_number'] ?: null, ':online_channels' => $data['online_channels'] ?: null, ':zone' => $data['zone'], ':maps_location' => $data['maps_location'] ?: null];
        if ($isEdit) {
            $params[':id'] = $id;
            $stmt = $pdo->prepare("UPDATE msu_cafes SET cafe_name=:cafe_name, opening_hours=:opening_hours, description=:description, food_and_drinks=:food_and_drinks, phone_number=:phone_number, online_channels=:online_channels, zone=:zone, maps_location=:maps_location WHERE id=:id");
            $stmt->execute($params);
        } else {
            $stmt = $pdo->prepare("INSERT INTO msu_cafes(cafe_name,opening_hours,description,food_and_drinks,phone_number,online_channels,zone,maps_location) VALUES(:cafe_name,:opening_hours,:description,:food_and_drinks,:phone_number,:online_channels,:zone,:maps_location)");
            $stmt->execute($params);
            $id = (int)$pdo->lastInsertId();
        }
        header("Location: detail.php?id=$id&saved=1");
        exit;
    }
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
    <title><?= $isEdit ? 'แก้ไข' : 'เพิ่ม' ?>ข้อมูลคาเฟ่</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/cafes.css">
</head>

<body>
    <div class="page-shell page-shell-form">
        <div class="page-header">
            <div><span class="section-kicker">MSU CAFE DATABASE</span>
                <h1><?= $isEdit ? 'แก้ไข' : 'เพิ่ม' ?>ข้อมูลคาเฟ่</h1>
                <p>กรอกข้อมูลร้านคาเฟ่ให้ครบถ้วนก่อนบันทึก</p>
            </div><a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> กลับรายการ</a>
        </div>
        <form method="post" class="form-card"><input type="hidden" name="id" value="<?= $id ?>">
            <div class="row g-4">
                <div class="col-12 col-lg-7"><label class="form-label">ชื่อร้าน <span class="required">*</span></label><input name="cafe_name" class="form-control <?= isset($errors['cafe_name']) ? 'is-invalid' : '' ?>" value="<?= e($data['cafe_name']) ?>">
                    <div class="invalid-feedback"><?= e($errors['cafe_name'] ?? '') ?></div>
                </div>
                <div class="col-12 col-lg-5"><label class="form-label">โซน <span class="required">*</span></label><input name="zone" class="form-control <?= isset($errors['zone']) ? 'is-invalid' : '' ?>" value="<?= e($data['zone']) ?>">
                    <div class="invalid-feedback"><?= e($errors['zone'] ?? '') ?></div>
                </div>
                <div class="col-12"><label class="form-label">เวลาเปิด-ปิด <span class="required">*</span></label><textarea name="opening_hours" rows="3" class="form-control <?= isset($errors['opening_hours']) ? 'is-invalid' : '' ?>"><?= e($data['opening_hours']) ?></textarea>
                    <div class="invalid-feedback"><?= e($errors['opening_hours'] ?? '') ?></div>
                </div>
                <div class="col-12"><label class="form-label">รายละเอียดร้าน</label><textarea name="description" rows="4" class="form-control"><?= e($data['description']) ?></textarea></div>
                <div class="col-12"><label class="form-label">อาหารและเครื่องดื่ม</label><textarea name="food_and_drinks" rows="4" class="form-control"><?= e($data['food_and_drinks']) ?></textarea></div>
                <div class="col-12 col-md-6"><label class="form-label">เบอร์โทร</label><input name="phone_number" class="form-control" value="<?= e($data['phone_number']) ?>"></div>
                <div class="col-12 col-md-6"><label class="form-label">ช่องทางออนไลน์</label><input name="online_channels" class="form-control" value="<?= e($data['online_channels']) ?>"></div>
                <div class="col-12"><label class="form-label">ตำแหน่งบนแผนที่</label><input name="maps_location" class="form-control" value="<?= e($data['maps_location']) ?>"></div>
            </div>
            <div class="form-actions"><a href="index.php" class="btn btn-light">ยกเลิก</a><button class="btn btn-mbs-primary"><i class="bi bi-floppy"></i> บันทึกข้อมูล</button></div>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>