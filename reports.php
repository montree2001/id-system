<?php
require_once 'config.php';

// ดึงข้อมูลสำหรับรายงาน
try {
    // ข้อมูลทั่วไป
    $totalCitizens = $db->fetchOne("SELECT COUNT(*) as count FROM citizens")['count'];
    $todayRegistrations = $db->fetchOne("SELECT COUNT(*) as count FROM citizens WHERE DATE(created_at) = CURDATE()")['count'];
    
    // ดึงข้อมูลประชาชนทั้งหมด (รองรับการแบ่งหน้า)
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = 20;
    $offset = ($page - 1) * $perPage;
    
    // ค้นหา
    $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
    $whereClause = '';
    $params = [];
    
    if (!empty($search)) {
        $whereClause = "WHERE citizen_id LIKE ? OR firstname_th LIKE ? OR lastname_th LIKE ? OR firstname_en LIKE ? OR lastname_en LIKE ?";
        $searchParam = "%{$search}%";
        $params = [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam];
    }
    
    // นับจำนวนทั้งหมด
    $totalCount = $db->fetchOne("SELECT COUNT(*) as count FROM citizens {$whereClause}", $params)['count'];
    $totalPages = ceil($totalCount / $perPage);
    
    // ดึงข้อมูล
    $citizens = $db->fetchAll(
        "SELECT * FROM citizen_summary {$whereClause} LIMIT {$offset}, {$perPage}",
        $params
    );

} catch (Exception $e) {
    $citizens = [];
    $totalCount = 0;
    $totalPages = 0;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานข้อมูลประชาชน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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
        .table th {
            background-color: #f8f9fa;
            border-top: none;
        }
        .badge-status {
            font-size: 0.8rem;
        }
        .btn-export {
            background: linear-gradient(45deg, #17a2b8, #20c997);
            border: none;
            color: white;
        }
        .btn-export:hover {
            background: linear-gradient(45deg, #138496, #1e7e34);
            color: white;
        }
        .photo-thumbnail {
            width: 40px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        .search-box {
            max-width: 400px;
        }
        .stats-mini {
            background: rgba(255,255,255,0.9);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
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
                <a class="nav-link active" href="reports.php">
                    <i class="fas fa-chart-bar me-1"></i>รายงาน
                </a>
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-1"></i>แดชบอร์ด
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- สถิติย่อ -->
        <div class="row">
            <div class="col-md-6">
                <div class="stats-mini text-center">
                    <h4 class="text-primary mb-0"><?php echo number_format($totalCount); ?></h4>
                    <small class="text-muted">ประชาชนทั้งหมด</small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stats-mini text-center">
                    <h4 class="text-success mb-0"><?php echo number_format($todayRegistrations); ?></h4>
                    <small class="text-muted">ลงทะเบียนวันนี้</small>
                </div>
            </div>
        </div>

        <!-- ตารางข้อมูล -->
        <div class="card">
            <div class="card-header bg-white">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0">
                            <i class="fas fa-users me-2"></i>รายชื่อประชาชน
                        </h5>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex gap-2 justify-content-end">
                            <!-- ค้นหา -->
                            <form method="GET" class="d-flex search-box">
                                <input type="text" name="search" class="form-control form-control-sm" 
                                       placeholder="ค้นหาชื่อ หรือเลขบัตร..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="btn btn-outline-primary btn-sm ms-1">
                                    <i class="fas fa-search"></i>
                                </button>
                                <?php if (!empty($search)): ?>
                                <a href="reports.php" class="btn btn-outline-secondary btn-sm ms-1">
                                    <i class="fas fa-times"></i>
                                </a>
                                <?php endif; ?>
                            </form>
                            
                            <!-- ปุ่มส่งออก -->
                            <div class="dropdown">
                                <button class="btn btn-export btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="fas fa-download me-1"></i>ส่งออก
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="export.php?format=excel<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>">
                                        <i class="fas fa-file-excel me-2"></i>Excel
                                    </a></li>
                                    <li><a class="dropdown-item" href="export.php?format=csv<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>">
                                        <i class="fas fa-file-csv me-2"></i>CSV
                                    </a></li>
                                    <li><a class="dropdown-item" href="export.php?format=pdf<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>">
                                        <i class="fas fa-file-pdf me-2"></i>PDF
                                    </a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($citizens)): ?>
                    <div class="text-center p-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">
                            <?php echo !empty($search) ? 'ไม่พบข้อมูลที่ค้นหา' : 'ยังไม่มีข้อมูลประชาชน'; ?>
                        </h5>
                        <?php if (!empty($search)): ?>
                            <a href="reports.php" class="btn btn-primary">ดูข้อมูลทั้งหมด</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th width="5%">#</th>
                                    <th width="15%">เลขบัตรประชาชน</th>
                                    <th width="20%">ชื่อ-นามสกุล (ไทย)</th>
                                    <th width="20%">ชื่อ-นามสกุล (อังกฤษ)</th>
                                    <th width="10%">เพศ</th>
                                    <th width="15%">ที่อยู่</th>
                                    <th width="10%">สถานะบัตร</th>
                                    <th width="5%">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($citizens as $index => $citizen): ?>
                                <tr>
                                    <td><?php echo $offset + $index + 1; ?></td>
                                    <td>
                                        <span class="font-monospace">
                                            <?php 
                                            $citizenId = $citizen['citizen_id'];
                                            echo substr($citizenId, 0, 1) . '-' . 
                                                 substr($citizenId, 1, 4) . '-' . 
                                                 substr($citizenId, 5, 5) . '-XX-X';
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($citizen['fullname_th']); ?></td>
                                    <td><?php echo htmlspecialchars($citizen['fullname_en']); ?></td>
                                    <td>
                                        <?php if ($citizen['gender'] === 'M'): ?>
                                            <span class="badge bg-primary">ชาย</span>
                                        <?php elseif ($citizen['gender'] === 'F'): ?>
                                            <span class="badge bg-pink">หญิง</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($citizen['full_address']); ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = match($citizen['card_status']) {
                                            'หมดอายุ' => 'bg-danger',
                                            'ใกล้หมดอายุ' => 'bg-warning',
                                            default => 'bg-success'
                                        };
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?> badge-status">
                                            <?php echo $citizen['card_status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                    data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item" href="#" 
                                                       onclick="viewCitizen('<?php echo $citizen['citizen_id']; ?>')">
                                                        <i class="fas fa-eye me-2"></i>ดูรายละเอียด
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="#" 
                                                       onclick="editCitizen('<?php echo $citizen['citizen_id']; ?>')">
                                                        <i class="fas fa-edit me-2"></i>แก้ไข
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#" 
                                                       onclick="deleteCitizen('<?php echo $citizen['citizen_id']; ?>')">
                                                        <i class="fas fa-trash me-2"></i>ลบ
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center p-3">
                        <div class="text-muted">
                            แสดง <?php echo number_format($offset + 1); ?> - <?php echo number_format(min($offset + $perPage, $totalCount)); ?> 
                            จาก <?php echo number_format($totalCount); ?> รายการ
                        </div>
                        
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal สำหรับดูรายละเอียด -->
    <div class="modal fade" id="citizenModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">รายละเอียดประชาชน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="citizenDetails">
                    <!-- จะถูกโหลดด้วย JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewCitizen(citizenId) {
            fetch(`get_citizen.php?id=${citizenId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const citizen = data.data;
                        document.getElementById('citizenDetails').innerHTML = `
                            <div class="row">
                                <div class="col-md-8">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th width="30%">เลขบัตรประชาชน:</th>
                                            <td>${citizen.citizen_id}</td>
                                        </tr>
                                        <tr>
                                            <th>ชื่อ-นามสกุล (ไทย):</th>
                                            <td>${citizen.fullname_th}</td>
                                        </tr>
                                        <tr>
                                            <th>ชื่อ-นามสกุล (อังกฤษ):</th>
                                            <td>${citizen.fullname_en}</td>
                                        </tr>
                                        <tr>
                                            <th>วันเกิด:</th>
                                            <td>${citizen.birth_date}</td>
                                        </tr>
                                        <tr>
                                            <th>เพศ:</th>
                                            <td>${citizen.gender === 'M' ? 'ชาย' : 'หญิง'}</td>
                                        </tr>
                                        <tr>
                                            <th>ที่อยู่:</th>
                                            <td>${citizen.full_address}</td>
                                        </tr>
                                        <tr>
                                            <th>วันออกบัตร:</th>
                                            <td>${citizen.issue_date || '-'}</td>
                                        </tr>
                                        <tr>
                                            <th>วันหมดอายุ:</th>
                                            <td>${citizen.expire_date || '-'}</td>
                                        </tr>
                                        <tr>
                                            <th>สถานะบัตร:</th>
                                            <td><span class="badge bg-${citizen.card_status === 'ปกติ' ? 'success' : 'warning'}">${citizen.card_status}</span></td>
                                        </tr>
                                        <tr>
                                            <th>ลงทะเบียนเมื่อ:</th>
                                            <td>${citizen.created_at}</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-4 text-center">
                                    ${citizen.photo ? 
                                        `<img src="${citizen.photo}" class="img-fluid rounded" style="max-width: 150px;">` :
                                        '<div class="border rounded p-4 text-muted"><i class="fas fa-user fa-3x mb-2"></i><br>ไม่มีรูปถ่าย</div>'
                                    }
                                </div>
                            </div>
                        `;
                        
                        const modal = new bootstrap.Modal(document.getElementById('citizenModal'));
                        modal.show();
                    } else {
                        alert('ไม่สามารถโหลดข้อมูลได้');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('เกิดข้อผิดพลาดในการโหลดข้อมูล');
                });
        }

        function editCitizen(citizenId) {
            // เปลี่ยนเส้นทางไปหน้าแก้ไข
            window.location.href = `edit_citizen.php?id=${citizenId}`;
        }

        function deleteCitizen(citizenId) {
            if (confirm('คุณแน่ใจว่าต้องการลบข้อมูลนี้?')) {
                fetch('delete_citizen.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ citizen_id: citizenId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert('ลบข้อมูลเรียบร้อยแล้ว');
                        location.reload();
                    } else {
                        alert('เกิดข้อผิดพลาด: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('เกิดข้อผิดพลาดในการลบข้อมูล');
                });
            }
        }
    </script>
</body>
</html>