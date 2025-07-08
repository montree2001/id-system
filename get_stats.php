<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $stats = [];

    // จำนวนประชาชนทั้งหมด
    $stats['total_citizens'] = $db->fetchOne("SELECT COUNT(*) as count FROM citizens")['count'];

    // ลงทะเบียนวันนี้
    $stats['today_registrations'] = $db->fetchOne(
        "SELECT COUNT(*) as count FROM citizens WHERE DATE(created_at) = CURDATE()"
    )['count'];

    // ลงทะเบียนสัปดาห์นี้
    $stats['week_registrations'] = $db->fetchOne(
        "SELECT COUNT(*) as count FROM citizens WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)"
    )['count'];

    // ลงทะเบียนเดือนนี้
    $stats['month_registrations'] = $db->fetchOne(
        "SELECT COUNT(*) as count FROM citizens WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())"
    )['count'];

    // บัตรใกล้หมดอายุ (90 วัน)
    $stats['expiring_soon'] = $db->fetchOne(
        "SELECT COUNT(*) as count FROM citizens WHERE expire_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)"
    )['count'];

    // บัตรหมดอายุแล้ว
    $stats['expired'] = $db->fetchOne(
        "SELECT COUNT(*) as count FROM citizens WHERE expire_date < CURDATE()"
    )['count'];

    // สถิติเพศ
    $genderStats = $db->fetchAll(
        "SELECT gender, COUNT(*) as count FROM citizens WHERE gender IN ('M', 'F') GROUP BY gender"
    );
    
    $stats['gender'] = [
        'male' => 0,
        'female' => 0
    ];
    
    foreach ($genderStats as $stat) {
        if ($stat['gender'] === 'M') {
            $stats['gender']['male'] = $stat['count'];
        } elseif ($stat['gender'] === 'F') {
            $stats['gender']['female'] = $stat['count'];
        }
    }

    // สถิติตามจังหวัด (Top 10)
    $stats['top_provinces'] = $db->fetchAll(
        "SELECT province, COUNT(*) as count 
         FROM citizens 
         WHERE province IS NOT NULL AND province != '' 
         GROUP BY province 
         ORDER BY count DESC 
         LIMIT 10"
    );

    // สถิติการลงทะเบียนรายวัน (7 วันล่าสุด)
    $stats['daily_registrations'] = $db->fetchAll(
        "SELECT 
            DATE(created_at) as registration_date,
            DATE_FORMAT(created_at, '%d/%m') as date_formatted,
            COUNT(*) as count
         FROM citizens 
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
         GROUP BY DATE(created_at)
         ORDER BY registration_date ASC"
    );

    // สถิติการลงทะเบียนรายเดือน (12 เดือนล่าสุด)
    $stats['monthly_registrations'] = $db->fetchAll(
        "SELECT 
            YEAR(created_at) as year,
            MONTH(created_at) as month,
            DATE_FORMAT(created_at, '%m/%Y') as month_formatted,
            COUNT(*) as count
         FROM citizens 
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
         GROUP BY YEAR(created_at), MONTH(created_at)
         ORDER BY year ASC, month ASC"
    );

    // สถิติช่วงอายุ
    $stats['age_groups'] = $db->fetchAll(
        "SELECT 
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) < 18 THEN 'ต่ำกว่า 18 ปี'
                WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 18 AND 30 THEN '18-30 ปี'
                WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 31 AND 45 THEN '31-45 ปี'
                WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 46 AND 60 THEN '46-60 ปี'
                WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) > 60 THEN 'มากกว่า 60 ปี'
                ELSE 'ไม่ระบุ'
            END as age_group,
            COUNT(*) as count
         FROM citizens 
         WHERE birth_date IS NOT NULL
         GROUP BY age_group
         ORDER BY 
            CASE age_group
                WHEN 'ต่ำกว่า 18 ปี' THEN 1
                WHEN '18-30 ปี' THEN 2
                WHEN '31-45 ปี' THEN 3
                WHEN '46-60 ปี' THEN 4
                WHEN 'มากกว่า 60 ปี' THEN 5
                ELSE 6
            END"
    );

    // สถิติ log การอ่านบัตร
    $stats['read_logs'] = [
        'total_reads' => $db->fetchOne("SELECT COUNT(*) as count FROM card_read_logs")['count'],
        'success_reads' => $db->fetchOne("SELECT COUNT(*) as count FROM card_read_logs WHERE read_status = 'success'")['count'],
        'error_reads' => $db->fetchOne("SELECT COUNT(*) as count FROM card_read_logs WHERE read_status = 'error'")['count'],
        'duplicate_attempts' => $db->fetchOne("SELECT COUNT(*) as count FROM card_read_logs WHERE read_status = 'duplicate'")['count'],
    ];

    // คำนวณอัตราความสำเร็จ
    if ($stats['read_logs']['total_reads'] > 0) {
        $stats['read_logs']['success_rate'] = round(
            ($stats['read_logs']['success_reads'] / $stats['read_logs']['total_reads']) * 100, 2
        );
    } else {
        $stats['read_logs']['success_rate'] = 0;
    }

    // ข้อมูลการใช้งานล่าสุด
    $stats['recent_activity'] = $db->fetchOne(
        "SELECT MAX(read_at) as last_read FROM card_read_logs"
    )['last_read'];

    // ข้อมูลระบบ
    $stats['system_info'] = [
        'database_size' => getDatabaseSize($db),
        'oldest_record' => $db->fetchOne("SELECT MIN(created_at) as oldest FROM citizens")['oldest'],
        'newest_record' => $db->fetchOne("SELECT MAX(created_at) as newest FROM citizens")['newest'],
    ];

    jsonResponse($stats, 'success');

} catch (Exception $e) {
    jsonResponse([], 'error', 'เกิดข้อผิดพลาดในการดึงสถิติ: ' . $e->getMessage());
}

/**
 * คำนวณขนาดฐานข้อมูล
 */
function getDatabaseSize($db) {
    try {
        $result = $db->fetchOne(
            "SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
             FROM information_schema.tables 
             WHERE table_schema = ?", 
            [DB_NAME]
        );
        
        return $result['size_mb'] . ' MB';
    } catch (Exception $e) {
        return 'ไม่สามารถคำนวณได้';
    }
}
?>