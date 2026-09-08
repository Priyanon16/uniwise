<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
require_once "db.php";
function sendJson(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
$id = trim($_GET['id'] ?? '');
$search = trim($_GET['search'] ?? '');
$zone = trim($_GET['zone'] ?? '');
$onlineChannel = trim($_GET['online_channel'] ?? '');
$mode = strtolower(trim($_GET['mode'] ?? 'list'));
if (!in_array($mode, ['list', 'detail', 'zones'], true)) sendJson(['success' => false, 'message' => 'mode ต้องเป็น list, detail หรือ zones'], 400);
try {
    if ($mode === 'zones') {
        $stmt = $pdo->query("SELECT zone, COUNT(*) AS cafe_count FROM msu_cafes GROUP BY zone ORDER BY zone ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['cafe_count'] = (int)$r['cafe_count'];
        }
        unset($r);
        sendJson(['success' => true, 'mode' => 'zones', 'count' => count($rows), 'data' => $rows]);
    }
    if ($mode === 'detail') {
        if ($id === '' || !ctype_digit($id)) sendJson(['success' => false, 'message' => 'กรุณาระบุ id ของร้าน'], 400);
        $stmt = $pdo->prepare("SELECT * FROM msu_cafes WHERE id=:id LIMIT 1");
        $stmt->execute([':id' => (int)$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) sendJson(['success' => false, 'message' => 'ไม่พบข้อมูลร้านคาเฟ่'], 404);
        $row['id'] = (int)$row['id'];
        sendJson(['success' => true, 'mode' => 'detail', 'data' => $row]);
    }
    $sql = "SELECT * FROM msu_cafes WHERE 1=1";
    $params = [];
    if ($search !== '') {
        $sql .= " AND (cafe_name LIKE :search OR opening_hours LIKE :search OR description LIKE :search OR food_and_drinks LIKE :search OR phone_number LIKE :search OR online_channels LIKE :search OR zone LIKE :search OR maps_location LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }
    if ($zone !== '') {
        $sql .= " AND zone=:zone";
        $params[':zone'] = $zone;
    }
    if ($onlineChannel !== '') {
        $sql .= " AND online_channels LIKE :online_channel";
        $params[':online_channel'] = '%' . $onlineChannel . '%';
    }
    $sql .= " ORDER BY zone ASC, cafe_name ASC, id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
    }
    unset($r);
    sendJson(['success' => true, 'mode' => 'list', 'filters' => ['search' => $search !== '' ? $search : null, 'zone' => $zone !== '' ? $zone : null, 'online_channel' => $onlineChannel !== '' ? $onlineChannel : null], 'count' => count($rows), 'data' => $rows]);
} catch (PDOException $e) {
    sendJson(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูลคาเฟ่'], 500);
}
