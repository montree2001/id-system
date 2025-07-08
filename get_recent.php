<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // ดึงข้อมูลการลงทะเบียนล่าสุด 10 รายการ
    $sql = "SELECT 
                citizen_id,
                CONCAT(title_th, firstname_th, ' ', lastname_th) as fullname_th,
                CONCAT(title_en, firstname_en, ' ', lastname_en) as fullname_en,
                birth_date,
                gender,
                CONCAT(district, ', ', amphoe, ', ', province) as location,
                DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as created_at,
                CASE 
                    WHEN expire_date < CURDATE() THEN 'หมดอายุ'
                    WHEN expire_date < DATE_ADD(CURDATE(), INTERVAL 90 DAY) THEN 'ใกล้หมดอายุ'
                    ELSE 'ปกติ'
                END as card_status
            FROM citizens 
            ORDER BY created_at DESC 
            LIMIT 10";

    $recentRegistrations = $db->fetchAll($sql);

    // ปรับปรุงข้อมูลเพื่อแสดงผล
    foreach ($recentRegistrations as &$registration) {
        // ซ่อนเลขบัตรบางส่วน
        $citizenId = $registration['citizen_id'];
        $registration['citizen_id'] = substr($citizenId, 0, 1) . '-' . 
                                     substr($citizenId, 1, 4) . '-' . 
                                     substr($citizenId, 5, 5) . '-XX-X';
        
        // จัดรูปแบบเพศ
        $registration['gender_text'] = $registration['gender'] === 'M' ? 'ชาย' : 'หญิง';
        
        // สีสำหรับสถานะบัตร
        $registration['status_color'] = match($registration['card_status']) {
            'หมดอายุ' => 'danger',
            'ใกล้หมดอายุ' => 'warning',
            default => 'success'
        };
    }

    jsonResponse($recentRegistrations, 'success');

} catch (Exception $e) {
    jsonResponse([], 'error', 'เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage());
}
?>