<?php
require_once "../../api/db.php";
require_once "../admin_activity.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: index.php?error=' . urlencode('ID ข้อมูลติดต่อไม่ถูกต้อง'));
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT contact_type, contact_label, contact_value FROM faculty_contacts WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$contact) {
        throw new Exception('ไม่พบข้อมูลติดต่อที่ต้องการลบ');
    }

    $stmt = $pdo->prepare("DELETE FROM faculty_contacts WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('ไม่พบข้อมูลติดต่อที่ต้องการลบ');
    }

    logAdminActivity(
        $pdo,
        'contact',
        'delete',
        (string)$contact['contact_label'],
        'ลบข้อมูลติดต่อ ' . (string)$contact['contact_value']
    );

    $pdo->commit();
    header('Location: index.php?success=delete');
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Location: index.php?error=' . urlencode('ไม่สามารถลบข้อมูลได้: ' . $e->getMessage()));
    exit;
}
