<?php
require_once "../../api/db.php";
$search = trim($_GET['search'] ?? '');
$zone = trim($_GET['zone'] ?? '');
$sort = trim($_GET['sort'] ?? 'id_asc');
$sql = "SELECT * FROM msu_cafes WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= " AND (CAST(id AS CHAR) LIKE :search OR cafe_name LIKE :search OR opening_hours LIKE :search OR description LIKE :search OR food_and_drinks LIKE :search OR phone_number LIKE :search OR online_channels LIKE :search OR zone LIKE :search OR maps_location LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}
if ($zone !== '') {
    $sql .= " AND zone=:zone";
    $params[':zone'] = $zone;
}
$orderBy = match ($sort) {
    'id_desc' => 'id DESC',
    'name_asc' => 'cafe_name ASC',
    'name_desc' => 'cafe_name DESC',
    'latest' => 'created_at DESC, id DESC',
    default => 'id ASC'
};
$sql .= " ORDER BY $orderBy";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$zones = $pdo->query("SELECT DISTINCT zone FROM msu_cafes WHERE zone<>'' ORDER BY zone ASC")->fetchAll(PDO::FETCH_COLUMN);
$total = (int)$pdo->query("SELECT COUNT(*) FROM msu_cafes")->fetchColumn();
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
    <title>จัดการข้อมูลคาเฟ่</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/cafes.css">
</head>

<body>
    <div class="page-shell">
        <div class="page-header">
            <div><span class="section-kicker">MSU CAFE DATABASE</span>
                <h1>จัดการข้อมูลคาเฟ่</h1>
                <p>ค้นหา ตรวจสอบ เพิ่ม แก้ไข และลบข้อมูลร้านคาเฟ่รอบมหาวิทยาลัยมหาสารคาม</p>
            </div><a href="save.php" class="btn btn-mbs-primary"><i class="bi bi-plus-lg"></i> เพิ่มร้านคาเฟ่</a>
        </div>
        <div class="stats-card">
            <div class="stats-icon"><i class="bi bi-cup-hot-fill"></i></div>
            <div><span>ร้านคาเฟ่ทั้งหมด</span><strong><?= number_format($total) ?></strong><small>รายการในระบบ</small></div>
        </div>
        <div class="filter-card">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-12 col-lg-5"><label class="form-label">ค้นหา</label><input type="text" name="search" class="form-control" value="<?= e($search) ?>" placeholder="ชื่อร้าน โซน เมนู เบอร์โทร..."></div>
                <div class="col-12 col-md-5 col-lg-3"><label class="form-label">โซน</label><select name="zone" class="form-select">
                        <option value="">ทุกโซน</option><?php foreach ($zones as $z): ?><option value="<?= e($z) ?>" <?= $zone === $z ? 'selected' : '' ?>><?= e($z) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="col-12 col-md-4 col-lg-2"><label class="form-label">เรียงข้อมูล</label><select name="sort" class="form-select">
                        <option value="id_asc" <?= $sort === 'id_asc' ? 'selected' : '' ?>>ID แรก → ล่าสุด</option>
                        <option value="id_desc" <?= $sort === 'id_desc' ? 'selected' : '' ?>>ID ล่าสุด → แรก</option>
                        <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>เพิ่มล่าสุด</option>
                        <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>ชื่อ A → Z</option>
                        <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>ชื่อ Z → A</option>
                    </select></div>
                <div class="col-12 col-md-3 col-lg-2 d-grid"><button class="btn btn-mbs-primary"><i class="bi bi-search"></i> ค้นหา</button></div>
            </form>
        </div>
        <div class="table-card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ชื่อร้าน</th>
                            <th>โซน</th>
                            <th>เวลาเปิด</th>
                            <th>ออนไลน์</th>
                            <th class="text-end">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody><?php if (!$rows): ?><tr>
                                <td colspan="6" class="empty-state">ไม่พบข้อมูล</td>
                            </tr><?php else: foreach ($rows as $r): ?><tr>
                                    <td><span class="id-badge">#<?= (int)$r['id'] ?></span></td>
                                    <td>
                                        <div class="cafe-name"><?= e($r['cafe_name']) ?></div>
                                        <div class="cafe-sub"><?= e($r['phone_number'] ?: 'ไม่มีเบอร์โทร') ?></div>
                                    </td>
                                    <td><span class="zone-badge"><?= e($r['zone']) ?></span></td>
                                    <td class="text-soft"><?= e($r['opening_hours']) ?></td>
                                    <td class="text-soft"><?= e($r['online_channels'] ?: '-') ?></td>
                                    <td class="text-end"><a class="btn btn-sm btn-light" href="detail.php?id=<?= (int)$r['id'] ?>"><i class="bi bi-eye"></i></a> <a class="btn btn-sm btn-light" href="save.php?id=<?= (int)$r['id'] ?>"><i class="bi bi-pencil-square"></i></a>
                                        <form method="post" action="delete.php" class="d-inline" onsubmit="return confirm('ยืนยันการลบร้านนี้หรือไม่?')"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash3"></i></button></form>
                                    </td>
                                </tr><?php endforeach;
                                endif; ?></tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>