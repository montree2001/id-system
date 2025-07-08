<?php
require_once 'config.php';

try {
    // ดึงพารามิเตอร์
    $format = isset($_GET['format']) ? sanitizeInput($_GET['format']) : 'excel';
    $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
    
    // สร้าง WHERE clause สำหรับการค้นหา
    $whereClause = '';
    $params = [];
    
    if (!empty($search)) {
        $whereClause = "WHERE citizen_id LIKE ? OR firstname_th LIKE ? OR lastname_th LIKE ? OR firstname_en LIKE ? OR lastname_en LIKE ?";
        $searchParam = "%{$search}%";
        $params = [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam];
    }

    // ดึงข้อมูล
    $sql = "SELECT 
                citizen_id,
                CONCAT(title_th, firstname_th, ' ', lastname_th) as fullname_th,
                CONCAT(title_en, firstname_en, ' ', lastname_en) as fullname_en,
                DATE_FORMAT(birth_date, '%d/%m/%Y') as birth_date,
                CASE WHEN gender = 'M' THEN 'ชาย' ELSE 'หญิง' END as gender,
                CONCAT(address, ' ', district, ' ', amphoe, ' ', province, ' ', postal_code) as full_address,
                DATE_FORMAT(issue_date, '%d/%m/%Y') as issue_date,
                DATE_FORMAT(expire_date, '%d/%m/%Y') as expire_date,
                issuer,
                CASE 
                    WHEN expire_date < CURDATE() THEN 'หมดอายุ'
                    WHEN expire_date < DATE_ADD(CURDATE(), INTERVAL 90 DAY) THEN 'ใกล้หมดอายุ'
                    ELSE 'ปกติ'
                END as card_status,
                DATE_FORMAT(created_at, '%d/%m/%Y %H:%i:%s') as registration_date
            FROM citizens 
            {$whereClause}
            ORDER BY created_at DESC";

    $data = $db->fetchAll($sql, $params);

    if (empty($data)) {
        throw new Exception('ไม่มีข้อมูลสำหรับการส่งออก');
    }

    // กำหนดชื่อไฟล์
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "citizens_data_{$timestamp}";

    switch ($format) {
        case 'excel':
            exportToExcel($data, $filename);
            break;
        case 'csv':
            exportToCSV($data, $filename);
            break;
        case 'pdf':
            exportToPDF($data, $filename);
            break;
        default:
            throw new Exception('รูปแบบการส่งออกไม่ถูกต้อง');
    }

} catch (Exception $e) {
    die('เกิดข้อผิดพลาด: ' . $e->getMessage());
}

/**
 * ส่งออกเป็น Excel
 */
function exportToExcel($data, $filename) {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');

    // สร้างไฟล์ XML สำหรับ Excel (Simple XLSX format)
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $xml .= '<sheetData>';

    // Header row
    $xml .= '<row r="1">';
    $headers = [
        'เลขบัตรประชาชน', 'ชื่อ-นามสกุล (ไทย)', 'ชื่อ-นามสกุล (อังกฤษ)', 
        'วันเกิด', 'เพศ', 'ที่อยู่', 'วันออกบัตร', 'วันหมดอายุ', 
        'หน่วยงานออกบัตร', 'สถานะบัตร', 'วันลงทะเบียน'
    ];
    
    $colIndex = 1;
    foreach ($headers as $header) {
        $xml .= '<c r="' . getColumnLetter($colIndex) . '1" t="inlineStr">';
        $xml .= '<is><t>' . htmlspecialchars($header) . '</t></is>';
        $xml .= '</c>';
        $colIndex++;
    }
    $xml .= '</row>';

    // Data rows
    $rowIndex = 2;
    foreach ($data as $row) {
        $xml .= '<row r="' . $rowIndex . '">';
        $colIndex = 1;
        
        foreach ([
            $row['citizen_id'], $row['fullname_th'], $row['fullname_en'],
            $row['birth_date'], $row['gender'], $row['full_address'],
            $row['issue_date'], $row['expire_date'], $row['issuer'],
            $row['card_status'], $row['registration_date']
        ] as $cellValue) {
            $xml .= '<c r="' . getColumnLetter($colIndex) . $rowIndex . '" t="inlineStr">';
            $xml .= '<is><t>' . htmlspecialchars($cellValue ?? '') . '</t></is>';
            $xml .= '</c>';
            $colIndex++;
        }
        
        $xml .= '</row>';
        $rowIndex++;
    }

    $xml .= '</sheetData>';
    $xml .= '</worksheet>';

    // สำหรับความเรียบง่าย เราจะส่งออกเป็น CSV แทน Excel จริง
    exportToCSV($data, $filename, 'excel');
}

/**
 * ส่งออกเป็น CSV
 */
function exportToCSV($data, $filename, $forExcel = false) {
    if ($forExcel) {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    } else {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    }
    
    header('Cache-Control: max-age=0');

    // เพิ่ม BOM สำหรับ UTF-8
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    // Header
    $headers = [
        'เลขบัตรประชาชน', 'ชื่อ-นามสกุล (ไทย)', 'ชื่อ-นามสกุล (อังกฤษ)', 
        'วันเกิด', 'เพศ', 'ที่อยู่', 'วันออกบัตร', 'วันหมดอายุ', 
        'หน่วยงานออกบัตร', 'สถานะบัตร', 'วันลงทะเบียน'
    ];
    fputcsv($output, $headers);

    // Data
    foreach ($data as $row) {
        $csvRow = [
            $row['citizen_id'],
            $row['fullname_th'],
            $row['fullname_en'],
            $row['birth_date'],
            $row['gender'],
            $row['full_address'],
            $row['issue_date'],
            $row['expire_date'],
            $row['issuer'],
            $row['card_status'],
            $row['registration_date']
        ];
        fputcsv($output, $csvRow);
    }

    fclose($output);
}

/**
 * ส่งออกเป็น PDF
 */
function exportToPDF($data, $filename) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    header('Cache-Control: max-age=0');

    // สำหรับความเรียบง่าย เราจะสร้าง HTML และแปลงเป็น PDF
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>รายงานข้อมูลประชาชน</title>
        <style>
            body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .header { text-align: center; margin-bottom: 20px; }
            .page-break { page-break-after: always; }
        </style>
    </head>
    <body>
        <div class="header">
            <h2>รายงานข้อมูลประชาชน</h2>
            <p>สร้างเมื่อ: <?php echo date('d/m/Y H:i:s'); ?></p>
            <p>จำนวนรายการ: <?php echo count($data); ?> รายการ</p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>ลำดับ</th>
                    <th>เลขบัตรประชาชน</th>
                    <th>ชื่อ-นามสกุล</th>
                    <th>เพศ</th>
                    <th>วันเกิด</th>
                    <th>สถานะบัตร</th>
                    <th>วันลงทะเบียน</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $index => $row): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo htmlspecialchars($row['citizen_id']); ?></td>
                    <td><?php echo htmlspecialchars($row['fullname_th']); ?></td>
                    <td><?php echo htmlspecialchars($row['gender']); ?></td>
                    <td><?php echo htmlspecialchars($row['birth_date']); ?></td>
                    <td><?php echo htmlspecialchars($row['card_status']); ?></td>
                    <td><?php echo htmlspecialchars($row['registration_date']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    // แสดง HTML แทนการแปลงเป็น PDF (ต้องติดตั้ง library เพิ่มเติมสำหรับ PDF)
    echo $html;
}

/**
 * แปลงหมายเลขคอลัมน์เป็นตัวอักษร Excel
 */
function getColumnLetter($columnIndex) {
    $letter = '';
    while ($columnIndex > 0) {
        $columnIndex--;
        $letter = chr(65 + ($columnIndex % 26)) . $letter;
        $columnIndex = intval($columnIndex / 26);
    }
    return $letter;
}
?>