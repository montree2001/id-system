<?php
/**
 * ไฟล์การตั้งค่าฐานข้อมูลสำหรับระบบอ่านบัตรประชาชน
 */

// การตั้งค่าฐานข้อมูล
define('DB_HOST', 'localhost');
define('DB_NAME', 'id-crad');
define('DB_USER', 'id-crad'); // เปลี่ยนตามการตั้งค่าของคุณ
define('DB_PASS', 'ResiWtztZecMNRkF'); // เปลี่ยนตามการตั้งค่าของคุณ
define('DB_CHARSET', 'utf8mb4');

// การตั้งค่าโซนเวลา
date_default_timezone_set('Asia/Bangkok');

// การตั้งค่าการแสดงข้อผิดพลาด (ปิดใน production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

/**
 * คลาสสำหรับจัดการฐานข้อมูล
 */
class Database {
    private $pdo;
    private $host = DB_HOST;
    private $dbname = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $charset = DB_CHARSET;

    public function __construct() {
        $this->connect();
    }

    /**
     * เชื่อมต่อฐานข้อมูล
     */
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];
            
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            die('การเชื่อมต่อฐานข้อมูลล้มเหลว: ' . $e->getMessage());
        }
    }

    /**
     * ดึง PDO object
     */
    public function getPdo() {
        return $this->pdo;
    }

    /**
     * เตรียม SQL statement
     */
    public function prepare($sql) {
        return $this->pdo->prepare($sql);
    }

    /**
     * รันคำสั่ง SQL
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            throw new Exception('Database error: ' . $e->getMessage());
        }
    }

    /**
     * ดึงข้อมูลแถวเดียว
     */
    public function fetchOne($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception('Database error: ' . $e->getMessage());
        }
    }

    /**
     * ดึงข้อมูลหลายแถว
     */
    public function fetchAll($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception('Database error: ' . $e->getMessage());
        }
    }

    /**
     * ได้ ID ของแถวที่เพิ่มล่าสุด
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    /**
     * เริ่ม transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    /**
     * commit transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }

    /**
     * rollback transaction
     */
    public function rollback() {
        return $this->pdo->rollback();
    }
}

/**
 * ฟังก์ชันยูทิลิตี้
 */
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function validateCitizenId($citizenId) {
    // ตรวจสอบความยาว 13 หลัก
    if (strlen($citizenId) !== 13 || !ctype_digit($citizenId)) {
        return false;
    }
    
    // ตรวจสอบ checksum
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int)$citizenId[$i] * (13 - $i);
    }
    
    $checksum = (11 - ($sum % 11)) % 10;
    return $checksum == (int)$citizenId[12];
}

function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

function jsonResponse($data, $status = 'success', $message = '') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// สร้าง instance ฐานข้อมูล global
$db = new Database();
?>