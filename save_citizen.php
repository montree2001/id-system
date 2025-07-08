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

    if (!$data) {
        throw new Exception('ข้อมูล JSON ไม่ถูกต้อง');
    }

    // ตรวจสอบข้อมูลที่จำเป็น
    $requiredFields = ['citizenId', 'firstnameTh', 'lastnameTh'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            throw new Exception("ข้อมูล {$field} จำเป็นต้องระบุ");
        }
    }

    // ตรวจสอบรูปแบบเลขบัตรประชาชน
    $citizenId = sanitizeInput($data['citizenId']);
    if (!validateCitizenId($citizenId)) {
        throw new Exception('เลขบัตรประชาชนไม่ถูกต้อง');
    }

    // เริ่ม transaction
    $db->beginTransaction();

    try {
        // ตรวจสอบว่ามีข้อมูลอยู่แล้วหรือไม่
        $existingCitizen = $db->fetchOne(
            "SELECT id, citizen_id, created_at FROM citizens WHERE citizen_id = ?",
            [$citizenId]
        );

        // ดึงการตั้งค่าระบบ
        $settings = [];
        $settingsResult = $db->fetchAll("SELECT setting_key, setting_value FROM system_settings");
        foreach ($settingsResult as $setting) {
            $settings[$setting['setting_key']] = $setting['setting_value'];
        }

        $allowDuplicate = ($settings['allow_duplicate_registration'] ?? 'false') === 'true';
        $autoUpdate = ($settings['auto_update_existing'] ?? 'true') === 'true';

        if ($existingCitizen) {
            if (!$allowDuplicate && !$autoUpdate) {
                // บันทึก log การพยายามลงทะเบียนซ้ำ
                $db->execute(
                    "INSERT INTO card_read_logs (citizen_id, read_status, error_message, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)",
                    [
                        $citizenId,
                        'duplicate',
                        'พยายามลงทะเบียนซ้ำ',
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]
                );

                throw new Exception('เลขบัตรประชาชนนี้ได้ลงทะเบียนไว้แล้วเมื่อ ' . formatDate($existingCitizen['created_at'], 'd/m/Y H:i:s'));
            }

            if ($autoUpdate) {
                // อัปเดตข้อมูลที่มีอยู่
                $updateResult = updateCitizenData($db, $data, $existingCitizen['id']);
                
                // บันทึก log
                $db->execute(
                    "INSERT INTO card_read_logs (citizen_id, read_status, ip_address, user_agent) VALUES (?, ?, ?, ?)",
                    [
                        $citizenId,
                        'success',
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]
                );

                $db->commit();
                jsonResponse(['id' => $existingCitizen['id'], 'action' => 'updated'], 'success', 'อัปเดตข้อมูลเรียบร้อยแล้ว');
            }
        } else {
            // เพิ่มข้อมูลใหม่
            $newId = insertCitizenData($db, $data);
            
            // บันทึก log
            $db->execute(
                "INSERT INTO card_read_logs (citizen_id, read_status, ip_address, user_agent) VALUES (?, ?, ?, ?)",
                [
                    $citizenId,
                    'success',
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]
            );

            $db->commit();
            jsonResponse(['id' => $newId, 'action' => 'inserted'], 'success', 'บันทึกข้อมูลเรียบร้อยแล้ว');
        }

    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }

} catch (Exception $e) {
    // บันทึก log ข้อผิดพลาด
    if (isset($citizenId)) {
        try {
            $db->execute(
                "INSERT INTO card_read_logs (citizen_id, read_status, error_message, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)",
                [
                    $citizenId,
                    'error',
                    $e->getMessage(),
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]
            );
        } catch (Exception $logError) {
            // ไม่ต้องทำอะไรถ้าบันทึก log ไม่ได้
        }
    }

    jsonResponse(null, 'error', $e->getMessage());
}

/**
 * เพิ่มข้อมูลประชาชนใหม่
 */
function insertCitizenData($db, $data) {
    $sql = "INSERT INTO citizens (
        citizen_id, title_th, firstname_th, lastname_th, title_en, firstname_en, lastname_en,
        birth_date, gender, address, district, amphoe, province, postal_code,
        issue_date, expire_date, issuer, photo, laser_code
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $params = [
        sanitizeInput($data['citizenId']),
        sanitizeInput($data['titleTh'] ?? ''),
        sanitizeInput($data['firstnameTh']),
        sanitizeInput($data['lastnameTh']),
        sanitizeInput($data['titleEn'] ?? ''),
        sanitizeInput($data['firstnameEn'] ?? ''),
        sanitizeInput($data['lastnameEn'] ?? ''),
        formatDateForDb($data['birthDate'] ?? ''),
        sanitizeInput($data['gender'] ?? ''),
        sanitizeInput($data['address'] ?? ''),
        sanitizeInput($data['district'] ?? ''),
        sanitizeInput($data['amphoe'] ?? ''),
        sanitizeInput($data['province'] ?? ''),
        sanitizeInput($data['postalCode'] ?? ''),
        formatDateForDb($data['issueDate'] ?? ''),
        formatDateForDb($data['expireDate'] ?? ''),
        sanitizeInput($data['issuer'] ?? ''),
        $data['photo'] ?? null,
        sanitizeInput($data['laserCode'] ?? '')
    ];

    $db->execute($sql, $params);
    return $db->lastInsertId();
}

/**
 * อัปเดตข้อมูลประชาชนที่มีอยู่
 */
function updateCitizenData($db, $data, $citizenId) {
    $sql = "UPDATE citizens SET 
        title_th = ?, firstname_th = ?, lastname_th = ?, title_en = ?, firstname_en = ?, lastname_en = ?,
        birth_date = ?, gender = ?, address = ?, district = ?, amphoe = ?, province = ?, postal_code = ?,
        issue_date = ?, expire_date = ?, issuer = ?, photo = ?, laser_code = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?";

    $params = [
        sanitizeInput($data['titleTh'] ?? ''),
        sanitizeInput($data['firstnameTh']),
        sanitizeInput($data['lastnameTh']),
        sanitizeInput($data['titleEn'] ?? ''),
        sanitizeInput($data['firstnameEn'] ?? ''),
        sanitizeInput($data['lastnameEn'] ?? ''),
        formatDateForDb($data['birthDate'] ?? ''),
        sanitizeInput($data['gender'] ?? ''),
        sanitizeInput($data['address'] ?? ''),
        sanitizeInput($data['district'] ?? ''),
        sanitizeInput($data['amphoe'] ?? ''),
        sanitizeInput($data['province'] ?? ''),
        sanitizeInput($data['postalCode'] ?? ''),
        formatDateForDb($data['issueDate'] ?? ''),
        formatDateForDb($data['expireDate'] ?? ''),
        sanitizeInput($data['issuer'] ?? ''),
        $data['photo'] ?? null,
        sanitizeInput($data['laserCode'] ?? ''),
        $citizenId
    ];

    return $db->execute($sql, $params);
}

/**
 * จัดรูปแบบวันที่สำหรับฐานข้อมูล
 */
function formatDateForDb($dateStr) {
    if (empty($dateStr)) {
        return null;
    }
    
    // ลองแปลงวันที่หลายรูปแบบ
    $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'];
    
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $dateStr);
        if ($date !== false) {
            return $date->format('Y-m-d');
        }
    }
    
    // ถ้าแปลงไม่ได้ให้ลองใช้ strtotime
    $timestamp = strtotime($dateStr);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }
    
    return null;
}
?>