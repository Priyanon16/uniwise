<?php

/**
 * บันทึกกิจกรรมของผู้ดูแลระบบ
 *
 * @param PDO    $pdo
 * @param string $module      programs | internship
 * @param string $actionType  create | update | delete
 * @param string $itemLabel   ชื่อรายการที่ถูกแก้ไข
 * @param string|null $detail รายละเอียดเพิ่มเติม
 */
function logAdminActivity(
    PDO $pdo,
    string $module,
    string $actionType,
    string $itemLabel,
    ?string $detail = null
): void {

    try {

        $stmt = $pdo->prepare("
            INSERT INTO admin_activity_logs
            (
                module,
                action_type,
                item_label,
                detail,
                created_at
            )
            VALUES
            (
                :module,
                :action_type,
                :item_label,
                :detail,
                NOW()
            )
        ");

        $stmt->execute([
            ':module' => $module,
            ':action_type' => $actionType,
            ':item_label' => $itemLabel,
            ':detail' => $detail
        ]);

    } catch (Throwable $e) {

        /*
         * ไม่ให้การบันทึก Log ทำให้ระบบหลักล่ม
         * หากยังไม่ได้สร้าง table หรือมีปัญหา
         * ระบบเพิ่ม/แก้ไขข้อมูลหลักยังทำงานต่อได้
         */
    }
}
