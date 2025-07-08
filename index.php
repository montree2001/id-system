<?php
require_once 'config.php';

// ดึงสถิติข้อมูล
try {
    $totalCitizens = $db->fetchOne("SELECT COUNT(*) as count FROM citizens")['count'];
    $todayRegistrations = $db->fetchOne("SELECT COUNT(*) as count FROM citizens WHERE DATE(created_at) = CURDATE()")['count'];
    $expiringSoon = $db->fetchOne("SELECT COUNT(*) as count FROM citizens WHERE expire_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)")['count'];
} catch (Exception $e) {
    $totalCitizens = $todayRegistrations = $expiringSoon = 0;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบอ่านบัตรประชาชน</title>
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
        .btn-read-card {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            border-radius: 10px;
            padding: 15px 30px;
            font-size: 18px;
            font-weight: bold;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-read-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
            color: white;
        }
        .btn-read-card:disabled {
            background: #6c757d;
            transform: none;
            box-shadow: none;
        }
        .status-indicator {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 10px;
        }
        .status-connected { background-color: #28a745; }
        .status-disconnected { background-color: #dc3545; }
        .status-reading { background-color: #ffc107; animation: pulse 1s infinite; }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .photo-preview {
            max-width: 150px;
            max-height: 200px;
            border-radius: 10px;
            border: 3px solid #dee2e6;
        }
        
        .loading-spinner {
            display: none;
        }
        
        .alert-custom {
            border-radius: 10px;
            border: none;
        }
        
        .stats-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <h1 class="text-center text-white mb-4">
                    <i class="fas fa-id-card me-3"></i>ระบบอ่านบัตรประชาชน
                </h1>
            </div>
        </div>

        <!-- สถิติ -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h3 class="text-primary"><?php echo number_format($totalCitizens); ?></h3>
                        <p class="mb-0">ผู้ลงทะเบียนทั้งหมด</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h3 class="text-success"><?php echo number_format($todayRegistrations); ?></h3>
                        <p class="mb-0">ลงทะเบียนวันนี้</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h3 class="text-warning"><?php echo number_format($expiringSoon); ?></h3>
                        <p class="mb-0">บัตรใกล้หมดอายุ</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Panel อ่านบัตร -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-credit-card me-2"></i>อ่านบัตรประชาชน
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- สถานะการเชื่อมต่อ -->
                        <div class="mb-3">
                            <span class="status-indicator status-disconnected" id="connectionStatus"></span>
                            <span id="connectionText">ไม่ได้เชื่อมต่อเครื่องอ่านบัตร</span>
                        </div>

                        <!-- ปุ่มอ่านบัตร -->
                        <div class="text-center mb-4">
                            <button class="btn btn-read-card" id="btnReadCard" disabled>
                                <i class="fas fa-credit-card me-2"></i>
                                <span class="loading-spinner spinner-border spinner-border-sm me-2"></span>
                                <span id="btnText">เสียบบัตรประชาชนเพื่ออ่านข้อมูล</span>
                            </button>
                        </div>

                        <!-- แสดงข้อมูลบัตร -->
                        <div id="cardDataSection" style="display: none;">
                            <h6 class="mb-3">ข้อมูลจากบัตรประชาชน</h6>
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="row mb-2">
                                        <label class="col-sm-4 col-form-label">เลขบัตร:</label>
                                        <div class="col-sm-8">
                                            <input type="text" class="form-control" id="citizenId" readonly>
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-sm-4 col-form-label">ชื่อ-นามสกุล (ไทย):</label>
                                        <div class="col-sm-8">
                                            <input type="text" class="form-control" id="nameTh" readonly>
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-sm-4 col-form-label">ชื่อ-นามสกุล (อังกฤษ):</label>
                                        <div class="col-sm-8">
                                            <input type="text" class="form-control" id="nameEn" readonly>
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-sm-4 col-form-label">วันเกิด:</label>
                                        <div class="col-sm-8">
                                            <input type="text" class="form-control" id="birthDate" readonly>
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-sm-4 col-form-label">เพศ:</label>
                                        <div class="col-sm-8">
                                            <input type="text" class="form-control" id="gender" readonly>
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-sm-4 col-form-label">ที่อยู่:</label>
                                        <div class="col-sm-8">
                                            <textarea class="form-control" id="address" rows="3" readonly></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 text-center">
                                    <img id="photoPreview" class="photo-preview" src="" alt="รูปถ่าย" style="display: none;">
                                    <div id="noPhoto" class="border rounded p-4 text-muted">
                                        <i class="fas fa-user fa-3x mb-2"></i><br>
                                        ไม่มีรูปถ่าย
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-4">
                                <button class="btn btn-success btn-lg" id="btnSave" onclick="saveData()">
                                    <i class="fas fa-save me-2"></i>บันทึกข้อมูล
                                </button>
                                <button class="btn btn-secondary btn-lg ms-2" onclick="clearData()">
                                    <i class="fas fa-eraser me-2"></i>ล้างข้อมูล
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel ประวัติการลงทะเบียน -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>ประวัติล่าสุด
                        </h5>
                    </div>
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                        <div id="recentRegistrations">
                            <!-- จะถูกโหลดด้วย JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Modal -->
    <div class="modal fade" id="alertModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">แจ้งเตือน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">ตกลง</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="card-reader.js"></script>
    <script>
        let currentCardData = null;

        // เริ่มต้นระบบ
        document.addEventListener('DOMContentLoaded', function() {
            loadRecentRegistrations();
            initCardReader();
        });

        // โหลดประวัติการลงทะเบียนล่าสุด
        function loadRecentRegistrations() {
            fetch('get_recent.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        displayRecentRegistrations(data.data);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // แสดงประวัติการลงทะเบียน
        function displayRecentRegistrations(registrations) {
            const container = document.getElementById('recentRegistrations');
            if (registrations.length === 0) {
                container.innerHTML = '<p class="text-muted text-center">ยังไม่มีข้อมูล</p>';
                return;
            }

            container.innerHTML = registrations.map(reg => `
                <div class="mb-2 p-2 border rounded">
                    <div class="fw-bold">${reg.fullname_th}</div>
                    <small class="text-muted">${reg.citizen_id}</small><br>
                    <small class="text-success">${reg.created_at}</small>
                </div>
            `).join('');
        }

        // บันทึกข้อมูล
        function saveData() {
            if (!currentCardData) {
                showAlert('ข้อผิดพลาด', 'ไม่มีข้อมูลบัตรให้บันทึก');
                return;
            }

            const btn = document.getElementById('btnSave');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>กำลังบันทึก...';

            fetch('save_citizen.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(currentCardData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showAlert('สำเร็จ', 'บันทึกข้อมูลเรียบร้อยแล้ว', 'success');
                    clearData();
                    loadRecentRegistrations();
                    updateStats();
                } else {
                    showAlert('ข้อผิดพลาด', data.message || 'เกิดข้อผิดพลาดในการบันทึกข้อมูล');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('ข้อผิดพลาด', 'เกิดข้อผิดพลาดในการเชื่อมต่อ');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-2"></i>บันทึกข้อมูล';
            });
        }

        // ล้างข้อมูล
        function clearData() {
            currentCardData = null;
            document.getElementById('cardDataSection').style.display = 'none';
            document.getElementById('citizenId').value = '';
            document.getElementById('nameTh').value = '';
            document.getElementById('nameEn').value = '';
            document.getElementById('birthDate').value = '';
            document.getElementById('gender').value = '';
            document.getElementById('address').value = '';
            document.getElementById('photoPreview').style.display = 'none';
            document.getElementById('noPhoto').style.display = 'block';
        }

        // แสดง Alert
        function showAlert(title, message, type = 'danger') {
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalBody').textContent = message;
            const modal = new bootstrap.Modal(document.getElementById('alertModal'));
            modal.show();
        }

        // อัปเดตสถิติ
        function updateStats() {
            fetch('get_stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        location.reload(); // รีโหลดหน้าเพื่ออัปเดตสถิติ
                    }
                })
                .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>