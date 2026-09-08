<?php
require_once "../../api/db.php";
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('รหัสข้อมูลไม่ถูกต้อง');
}
$stmt = $pdo->prepare("DELETE FROM msu_cafes WHERE id=:id");
$stmt->execute([':id' => $id]);
header("Location: index.php?deleted=1");
exit;
