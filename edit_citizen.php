<?php
require_once 'config.php';

// ตรวจสอบ ID ที่ส่งมา
$citizenId = isset($_GET['id']) ? sanitizeInput($_GET['id']) : '';
$citizen = null;

if (empty($citizenId)) {
    header('Location: reports.php');
    exit;
}

// ดึงข้อมูลประชาชน
try {
    $citizen = $db->fetchOne(
        "SELECT * FROM citizens WHERE citizen_id = ?",
        [$citizenId]
    );
    
    if (!$citizen) {
        throw new Exception('ไม่พบข้อมูลประชาชนที่ต้องการแก้ไข');
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

// จัดการการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        $updateData = [
            'title_th' => sanitizeInput($_POST['title_th'] ?? ''),
            'firstname_th' => sanitizeInput($_POST['firstname_th'] ?? ''),
            'lastname_th' => sanitizeInput($_POST['lastname_th'] ?? ''),
            'title_en' => sanitizeInput($_POST['title_en'] ?? ''),
            'firstname_en' => sanitizeInput($_POST['firstname_en'] ?? ''),
            'lastname_en' => sanitizeInput($_POST['lastname_en'] ?? ''),
            'birth_date' => sanitizeInput($_POST['birth_date'] ?? ''),
            'gender' => sanitizeInput($_POST['gender'] ?? ''),
            'address' => sanitizeInput($_POST['address'] ?? ''),
            'district' => sanitizeInput($_POST['district'] ?? ''),
            'amphoe' => sanitizeInput($_POST['amphoe'] ?? ''),
            'province' => sanitizeInput($_POST['province'] ?? ''),
            'postal_code' => sanitizeInput($_POST['postal_code'] ?? ''),
            'issue_date' => sanitizeInput($_POST['issue_date'] ?? ''),
            'expire_date' => sanitizeInput($_POST['expire_date'] ?? ''),
            'issuer' => sanitizeInput($_POST['issuer'] ?? '')
        ];

        // ตรวจสอบข้อมูลที่จำเป็น
        if (empty($updateData['firstname_th']) || empty($updateData['lastname_th'])) {
            throw new Exception('กรุณาระบุชื่อและนามสกุล');
        }

        // แปลงวันที่
        $updateData['birth_date'] = !empty($updateData['birth_date']) ? date('Y-m-d', strtotime($updateData['birth_date'])) : null;
        $updateData['issue_date'] = !empty($updateData['issue_date']) ? date('Y-m-d', strtotime($updateData['issue_date'])) : null;
        $updateData['expire_date'] = !empty($updateData['expire_date']) ? date('Y-m-d', strtotime($updateData['expire_date'])) : null;

        // อัปเดตข้อมูล
        $sql = "UPDATE citizens SET 
                title_th = ?, firstname_th = ?, lastname_th = ?, 
                title_en = ?, firstname_en = ?, lastname_en = ?,
                birth_date = ?, gender = ?, address = ?, 
                district = ?, amphoe = ?, province = ?, postal_code = ?,
                issue_date = ?, expire_date = ?, issuer = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE citizen_id = ?";

        $params = [
            $updateData['title_th'], $updateData['firstname_th'], $updateData['lastname_th'],
            $updateData['title_en'], $updateData['firstname_en'], $updateData['lastname_en'],
            $updateData['birth_date'], $updateData['gender'], $updateData['address'],
            $updateData['district'], $updateData['amphoe'], $updateData['province'], $updateData['postal_code'],
            $updateData['issue_date'], $updateData['expire_date'], $updateData['issuer'],
            $citizenId
        ];

        $result = $db->execute($sql, $params);

        if ($result) {
            $success = 'อัปเดตข้อมูลเรียบร้อยแล้ว';
            // รีโหลดข้อมูลใหม่
            $citizen = $db->fetchOne("SELECT * FROM citizens WHERE citizen_id = ?", [$citizenId]);
        } else {
            throw new Exception('ไม่สามารถอัปเดตข้อมูลได้');
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขข้อมูลประชาชน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Sarabun', sans-serif;
            min-height: 100vh;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .navbar {
            background: rgba(255,255,255,0.95) !important;
            backdrop-filter: blur(10px);
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #dee2e6;
        }
        .form-control:focus, .form-select:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .btn-custom {
            border-radius: 10px;
            padding: 10px 30px;
        }
        .required::after {
            content: " *";
            color: red;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-id-card me-2"></i>ระบบบัตรประชาชน
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-home me-1"></i>หน้าหลัก
                </a>
                <a class="nav-link" href="reports.php">
                    <i class="fas fa-chart-bar me-1"></i>รายงาน
                </a>
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-1"></i>แดชบอร์ด
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="text-white mb-0">
                            <i class="fas fa-edit me-3"></i>แก้ไขข้อมูลประชาชน
                        </h1>
                        <p class="text-white-50 mb-0">เลขบัตรประชาชน: <?php echo htmlspecialchars($citizenId); ?></p>
                    </div>
                    <a href="reports.php" class="btn btn-light">
                        <i class="fas fa-arrow-left me-2"></i>กลับ
                    </a>
                </div>
            </div>
        </div>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($citizen): ?>
        <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="action" value="update">
            
            <div class="row">
                <!-- ข้อมูลส่วนตัว -->
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-user me-2"></i>ข้อมูลส่วนตัว
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- ชื่อภาษาไทย -->
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">คำนำหน้า (ไทย)</label>
                                    <select name="title_th" class="form-select">
                                        <option value="">เลือก</option>
                                        <option value="นาย" <?php echo $citizen['title_th'] === 'นาย' ? 'selected' : ''; ?>>นาย</option>
                                        <option value="นาง" <?php echo $citizen['title_th'] === 'นาง' ? 'selected' : ''; ?>>นาง</option>
                                        <option value="นางสาว" <?php echo $citizen['title_th'] === 'นางสาว' ? 'selected' : ''; ?>>นางสาว</option>
                                        <option value="เด็กชาย" <?php echo $citizen['title_th'] === 'เด็กชาย' ? 'selected' : ''; ?>>เด็กชาย</option>
                                        <option value="เด็กหญิง" <?php echo $citizen['title_th'] === 'เด็กหญิง' ? 'selected' : ''; ?>>เด็กหญิง</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label required">ชื่อ (ไทย)</label>
                                    <input type="text" name="firstname_th" class="form-control" 
                                           value="<?php echo htmlspecialchars($citizen['firstname_th']); ?>" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label required">นามสกุล (ไทย)</label>
                                    <input type="text" name="lastname_th" class="form-control" 
                                           value="<?php echo htmlspecialchars($citizen['lastname_th']); ?>" required>
                                </div>
                            </div>

                            <!-- ชื่อภาษาอังกฤษ -->
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">คำนำหน้า (อังกฤษ)</label>
                                    <select name="title_en" class="form-select">
                                        <option value="">เลือก</option>
                                        <option value="Mr." <?php echo $citizen['title_en'] === 'Mr.' ? 'selected' : ''; ?>>Mr.</option>
                                        <option value="Mrs." <?php echo $citizen['title_en'] === 'Mrs.' ? 'selected' : ''; ?>>Mrs.</option>
                                        <option value="Miss" <?php echo $citizen['title_en'] === 'Miss' ? 'selected' : ''; ?>>Miss</option>
                                        <option value="Master" <?php echo $citizen['title_en'] === 'Master' ? 'selected' : ''; ?>>Master</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ชื่อ (อังกฤษ)</label>
                                    <input type="text" name="firstname_en" class="form-control" 
                                           value="<?php echo htmlspecialchars($citizen['firstname_en']); ?>">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">นามสกุล (อังกฤษ)</label>
                                    <input type="text" name="lastname_en" class="form-control" 
                                           value="<?php echo htmlspecialchars($citizen['lastname_en']); ?>">
                                </div>
                            </div>

                            <!-- วันเกิดและเพศ -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">วันเกิด</label>
                                    <input type="date" name="birth_date" class="form-control" 
                                           value="<?php echo $citizen['birth_date']; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">เพศ</label>
                                    <select name="gender" class="form-select">
                                        <option value="">เลือก</option>
                                        <option value="M" <?php echo $citizen['gender'] === 'M' ? 'selected' : ''; ?>>ชาย</option>
                                        <option value="F" <?php echo $citizen['gender'] === 'F' ? 'selected' : ''; ?>>หญิง</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ข้อมูลที่อยู่ -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-map-marker-alt me-2"></i>ข้อมูลที่อยู่
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">ที่อยู่</label>
                                <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($citizen['address']); ?></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">ตำบล/แขวง</label>
                                        <input type="text" name="district" class="form-control" 
                                               value="<?php echo htmlspecialchars($citizen['district']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">อำเภอ/เขต</label>
                                        <input type="text" name="amphoe" class="form-control" 
                                               value="<?php echo htmlspecialchars($citizen['amphoe']); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label class="form-label">จังหวัด</label>
                                        <input type="text" name="province" class="form-control" 
                                               value="<?php echo htmlspecialchars($citizen['province']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">รหัสไปรษณีย์</label>
                                        <input type="text" name="postal_code" class="form-control" 
                                               value="<?php echo htmlspecialchars($citizen['postal_code']); ?>" 
                                               pattern="[0-9]{5}" maxlength="5">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ข้อมูลบัตร -->
                    <div class="card mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">
                                <i class="fas fa-id-card me-2"></i>ข้อมูลบัตร
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">วันออกบัตร</label>
                                        <input type="date" name="issue_date" class="form-control" 
                                               value="<?php echo $citizen['issue_date']; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">วันหมดอายุ</label>
                                        <input type="date" name="expire_date" class="form-control" 
                                               value="<?php echo $citizen['expire_date']; ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">หน่วยงานที่ออกบัตร</label>
                                <input type="text" name="issuer" class="form-control" 
                                       value="<?php echo htmlspecialchars($citizen['issuer']); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ข้อมูลเพิ่มเติม -->
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-camera me-2"></i>รูปถ่าย
                            </h5>
                        </div>
                        <div class="card-body text-center">
                            <?php if (!empty($citizen['photo'])): ?>
                                <img src="<?php echo $citizen['photo']; ?>" class="img-fluid rounded mb-3" 
                                     style="max-height: 200px;" alt="รูปถ่าย">
                            <?php else: ?>
                                <div class="border rounded p-4 text-muted mb-3">
                                    <i class="fas fa-user fa-3x mb-2"></i><br>
                                    ไม่มีรูปถ่าย
                                </div>
                            <?php endif; ?>
                            <p class="text-muted small">
                                <i class="fas fa-info-circle me-1"></i>
                                ไม่สามารถแก้ไขรูปถ่ายได้ ต้องอ่านบัตรใหม่
                            </p>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>ข้อมูลระบบ
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <strong>เลขบัตรประชาชน:</strong><br>
                                <span class="font-monospace"><?php echo htmlspecialchars($citizen['citizen_id']); ?></span>
                            </div>
                            <div class="mb-2">
                                <strong>ลงทะเบียนเมื่อ:</strong><br>
                                <?php echo formatDate($citizen['created_at'], 'd/m/Y H:i:s'); ?>
                            </div>
                            <div class="mb-2">
                                <strong>แก้ไขล่าสุด:</strong><br>
                                <?php echo formatDate($citizen['updated_at'], 'd/m/Y H:i:s'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ปุ่มบันทึก -->
            <div class="text-center mb-4">
                <button type="submit" class="btn btn-primary btn-custom btn-lg me-3">
                    <i class="fas fa-save me-2"></i>บันทึกการแก้ไข
                </button>
                <a href="reports.php" class="btn btn-secondary btn-custom btn-lg">
                    <i class="fas fa-times me-2"></i>ยกเลิก
                </a>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Bootstrap form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();

        // ตรวจสอบรูปแบบรหัสไปรษณีย์
        document.querySelector('input[name="postal_code"]').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // แสดงคำเตือนก่อนออกจากหน้า (ถ้ามีการเปลี่ยนแปลงข้อมูล)
        let formChanged = false;
        document.querySelectorAll('input, select, textarea').forEach(function(element) {
            element.addEventListener('change', function() {
                formChanged = true;
            });
        });

        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // ยกเลิกคำเตือนเมื่อส่งฟอร์ม
        document.querySelector('form').addEventListener('submit', function() {
            formChanged = false;
        });
    </script>
</body>
</html>