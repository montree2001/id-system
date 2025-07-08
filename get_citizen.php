<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // ตรวจสอบ HTTP method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    // รับข้อมูล JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['citizen_id'])) {
        throw new Exception('ไม่ระบุเลขบัตรประชาชนที่ต้องการลบ');
    }

    $citizenId = sanitizeInput($data['citizen_id']);

    if (empty($citizenId)) {
        throw new Exception('เลขบัตรประชาชนไม่ถูกต้อง');
    }

    // ตรวจสอบว่ามีข้อมูลอยู่จริงหรือไม่
    $existingCitizen = $db->fetchOne(
        "SELECT id, citizen_id, CONCAT(title_th, firstname_th, ' ', lastname_th) as fullname FROM citizens WHERE citizen_id = ?",
        [$citizenId]
    );

    if (!$existingCitizen) {
        throw new Exception('ไม่พบข้อมูลประชาชนที่ต้องการลบ');
    }

    // เริ่ม transaction
    $db->beginTransaction();

    try {
        // ลบข้อมูล logs ก่อน (เนื่องจากมี foreign key constraint)
        $db->execute(
            "DELETE FROM card_read_logs WHERE citizen_id = ?",
            [$citizenId]
        );

        // ลบข้อมูลประชาชน
        $result = $db->execute(
            "DELETE FROM citizens WHERE citizen_id = ?",
            [$citizenId]
        );

        if (!$result) {
            throw new Exception('ไม่สามารถลบข้อมูลได้');
        }

        // บันทึก log การลบ (ใช้ตารางแยก)
        try {
            $db->execute(
                "INSERT INTO system_logs (action, description, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, NOW())",
                [
                    'DELETE_CITIZEN',
                    "ลบข้อมูลประชาชน: {$existingCitizen['fullname']} (ID: {$citizenId})",
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]
            );
        } catch (Exception $logError) {
            // ถ้าไม่มีตาราง system_logs ก็ไม่เป็นไร
        }

        $db->commit();

        jsonResponse([
            'deleted_id' => $existingCitizen['id'],
            'citizen_id' => $citizenId,
            'fullname' => $existingCitizen['fullname']
        ], 'success', 'ลบข้อมูลเรียบร้อยแล้ว');

    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('เกิดข้อผิดพลาดในการลบข้อมูล: ' . $e->getMessage());
    }

} catch (Exception $e) {
    jsonResponse(null, 'error', $e->getMessage());
}
?>