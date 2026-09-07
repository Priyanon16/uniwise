<?php
require_once "../../api/db.php";
require_once "../admin_activity.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: index.php?error=' . urlencode('ID คู่มือไม่ถูกต้อง'));
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT topic_code, title FROM academic_guides WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $guide = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$guide) {
        throw new Exception('ไม่พบคู่มือที่ต้องการลบ');
    }

    foreach (['academic_guide_steps', 'academic_guide_documents', 'academic_guide_periods'] as $table) {
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE guide_id = :guide_id");
        $stmt->execute([':guide_id' => $id]);
    }

    $stmt = $pdo->prepare("DELETE FROM academic_guides WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('ไม่พบคู่มือที่ต้องการลบ');
    }

    logAdminActivity(
        $pdo,
        'academic_guide',
        'delete',
        (string)$guide['title'],
        'ลบข้อมูลคู่มือการเรียน (' . (string)$guide['topic_code'] . ')'
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
