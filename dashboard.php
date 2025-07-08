<?php
require_once 'config.php';

// ดึงสถิติข้อมูล
try {
    // สถิติทั่วไป
    $totalCitizens = $db->fetchOne("SELECT COUNT(*) as count FROM citizens")['count'];
    $todayRegistrations = $db->fetchOne("SELECT COUNT(*) as count FROM citizens WHERE DATE(created_at) = CURDATE()")['count'];
    $weekRegistrations = $db->fetchOne("SELECT COUNT(*) as count FROM citizens WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)")['count'];
    $monthRegistrations = $db->fetchOne("SELECT COUNT(*) as count FROM citizens WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())")['count'];
    
    // สถิติบัตร
    $expiringSoon = $db->fetchOne("SELECT COUNT(*) as count FROM citizens WHERE expire_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)")['count'];
    $expired = $db->fetchOne("SELECT COUNT(*) as count FROM citizens WHERE expire_date < CURDATE()")['count'];
    
    // สถิติเพศ
    $maleCount = $db->fetchOne("SELECT COUNT(*) as count FROM citizens WHERE gender = 'M'")['count'];
    $femaleCount = $db->fetchOne("SELECT COUNT(*) as count FROM citizens WHERE gender = 'F'")['count'];
    
} catch (Exception $e) {
    $totalCitizens = $todayRegistrations = $weekRegistrations = $monthRegistrations = 0;
    $expiringSoon = $expired = $maleCount = $femaleCount = 0;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แดชบอร์ด - ระบบบัตรประชาชน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
        }
        .navbar {
            background: rgba(255,255,255,0.95) !important;
            backdrop-filter: blur(10px);
        }
        .stat-card {
            text-align: center;
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .chart-container {
            position: relative;
            height: 400px;
            margin: 20px 0;
        }
        .widget {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .progress-custom {
            height: 8px;
            border-radius: 4px;
        }
        .text-primary { color: #007bff !important; }
        .text-success { color: #28a745 !important; }
        .text-info { color: #17a2b8 !important; }
        .text-warning { color: #ffc107 !important; }
        .text-danger { color: #dc3545 !important; }
        .bg-primary-gradient { background: linear-gradient(45deg, #007bff, #0056b3); }
        .bg-success-gradient { background: linear-gradient(45deg, #28a745, #20c997); }
        .bg-info-gradient { background: linear-gradient(45deg, #17a2b8, #138496); }
        .bg-warning-gradient { background: linear-gradient(45deg, #ffc107, #e0a800); }
        .bg-danger-gradient { background: linear-gradient(45deg, #dc3545, #c82333); }
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
                <a class="nav-link active" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-1"></i>แดชบอร์ด
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="text-white mb-0">
                    <i class="fas fa-tachometer-alt me-3"></i>แดชบอร์ด
                </h1>
                <p class="text-white-50 mb-0">ภาพรวมข้อมูลและสถิติระบบ</p>
            </div>
        </div>

        <!-- สถิติหลัก -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card stat-card bg-primary-gradient text-white">
                    <div class="stat-number"><?php echo number_format($totalCitizens); ?></div>
                    <div class="stat-label">ประชาชนทั้งหมด</div>
                    <i class="fas fa-users fa-2x opacity-50 mt-2"></i>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card stat-card bg-success-gradient text-white">
                    <div class="stat-number"><?php echo number_format($todayRegistrations); ?></div>
                    <div class="stat-label">ลงทะเบียนวันนี้</div>
                    <i class="fas fa-user-plus fa-2x opacity-50 mt-2"></i>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card stat-card bg-info-gradient text-white">
                    <div class="stat-number"><?php echo number_format($weekRegistrations); ?></div>
                    <div class="stat-label">ลงทะเบียนสัปดาห์นี้</div>
                    <i class="fas fa-calendar-week fa-2x opacity-50 mt-2"></i>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card stat-card bg-warning-gradient text-white">
                    <div class="stat-number"><?php echo number_format($monthRegistrations); ?></div>
                    <div class="stat-label">ลงทะเบียนเดือนนี้</div>
                    <i class="fas fa-calendar-alt fa-2x opacity-50 mt-2"></i>
                </div>
            </div>
        </div>

        <!-- แถวที่ 2 -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>สถิติการลงทะเบียนรายวัน (7 วันล่าสุด)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="dailyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie me-2"></i>สัดส่วนเพศ
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="genderChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- แถวที่ 3 -->
        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>สถานะบัตร
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <div class="text-center p-3">
                                    <div class="h2 text-warning mb-2"><?php echo number_format($expiringSoon); ?></div>
                                    <div class="text-muted">ใกล้หมดอายุ</div>
                                    <div class="progress progress-custom mt-2">
                                        <div class="progress-bar bg-warning" style="width: <?php echo $totalCitizens > 0 ? ($expiringSoon / $totalCitizens * 100) : 0; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center p-3">
                                    <div class="h2 text-danger mb-2"><?php echo number_format($expired); ?></div>
                                    <div class="text-muted">หมดอายุแล้ว</div>
                                    <div class="progress progress-custom mt-2">
                                        <div class="progress-bar bg-danger" style="width: <?php echo $totalCitizens > 0 ? ($expired / $totalCitizens * 100) : 0; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>จังหวัดที่ลงทะเบียนมากที่สุด
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="provinceStats">
                            <!-- จะถูกโหลดด้วย JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- สถิติล่าสุด -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-clock me-2"></i>กิจกรรมล่าสุด
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="recentActivity">
                            <!-- จะถูกโหลดด้วย JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // โหลดข้อมูลเมื่อหน้าเว็บโหลดเสร็จ
        document.addEventListener('DOMContentLoaded', function() {
            loadDashboardData();
            setupAutoRefresh();
        });

        // โหลดข้อมูลแดชบอร์ด
        function loadDashboardData() {
            fetch('get_stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        updateCharts(data.data);
                        updateProvinceStats(data.data.top_provinces);
                        updateRecentActivity(data.data);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // อัปเดตกราฟ
        function updateCharts(stats) {
            // กราฟการลงทะเบียนรายวัน
            const dailyCtx = document.getElementById('dailyChart').getContext('2d');
            new Chart(dailyCtx, {
                type: 'line',
                data: {
                    labels: stats.daily_registrations.map(item => item.date_formatted),
                    datasets: [{
                        label: 'จำนวนการลงทะเบียน',
                        data: stats.daily_registrations.map(item => item.count),
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });

            // กราหเพศ
            const genderCtx = document.getElementById('genderChart').getContext('2d');
            new Chart(genderCtx, {
                type: 'doughnut',
                data: {
                    labels: ['ชาย', 'หญิง'],
                    datasets: [{
                        data: [stats.gender.male, stats.gender.female],
                        backgroundColor: ['#007bff', '#e83e8c'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // อัปเดตสถิติจังหวัด
        function updateProvinceStats(provinces) {
            const container = document.getElementById('provinceStats');
            if (!provinces || provinces.length === 0) {
                container.innerHTML = '<p class="text-muted text-center">ไม่มีข้อมูล</p>';
                return;
            }

            const maxCount = Math.max(...provinces.map(p => p.count));
            container.innerHTML = provinces.slice(0, 5).map(province => `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold">${province.province}</span>
                    <span class="badge bg-primary">${province.count}</span>
                </div>
                <div class="progress progress-custom mb-3">
                    <div class="progress-bar bg-primary" style="width: ${(province.count / maxCount) * 100}%"></div>
                </div>
            `).join('');
        }

        // อัปเดตกิจกรรมล่าสุด
        function updateRecentActivity(stats) {
            const container = document.getElementById('recentActivity');
            const activities = [
                { icon: 'fas fa-users', text: `มีประชาชนลงทะเบียนทั้งหมด ${stats.total_citizens} คน`, time: 'ล่าสุด' },
                { icon: 'fas fa-user-plus', text: `ลงทะเบียนวันนี้ ${stats.today_registrations} คน`, time: 'วันนี้' },
                { icon: 'fas fa-chart-line', text: `อัตราความสำเร็จการอ่านบัตร ${stats.read_logs?.success_rate || 0}%`, time: 'ทั้งหมด' },
                { icon: 'fas fa-exclamation-triangle', text: `บัตรใกล้หมดอายุ ${stats.expiring_soon} ใบ`, time: '90 วันข้างหน้า' }
            ];

            container.innerHTML = activities.map(activity => `
                <div class="d-flex align-items-center mb-3">
                    <div class="me-3">
                        <i class="${activity.icon} fa-lg text-primary"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold">${activity.text}</div>
                        <small class="text-muted">${activity.time}</small>
                    </div>
                </div>
            `).join('');
        }

        // ตั้งค่าการรีเฟรชอัตโนมัติ
        function setupAutoRefresh() {
            // รีเฟรชข้อมูลทุก 5 นาที
            setInterval(loadDashboardData, 5 * 60 * 1000);
        }
    </script>
</body>
</html>