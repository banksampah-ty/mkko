<?php
include 'config.php';

// CEK LOGIN DAN ROLE MITRA
if (!isLoggedIn() || !isMitra()) {
    header("Location: login.php");
    exit();
}

$mitra_id = $_SESSION['mitra_id'];
$success = '';
$error = '';

// INISIALISASI VARIABEL DENGAN NILAI DEFAULT
$total_penjemputan = 0;
$total_pending = 0;
$total_dijadwalkan = 0;
$total_diproses = 0;
$total_selesai = 0;
$total_ditolak = 0;
$rating_avg = 0;
$total_ulasan = 0;
$jadwal_hari_ini = [];
$penjemputan_terbaru = [];
$penjemputan_pending = [];

try {
    // AMBIL DATA STATISTIK - PERBAIKI QUERY INI
    $sql_stats = "
        SELECT 
            COUNT(*) as total_penjemputan,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'dijadwalkan' THEN 1 ELSE 0 END) as dijadwalkan,
            SUM(CASE WHEN status = 'diproses' THEN 1 ELSE 0 END) as diproses,
            SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as selesai,
            SUM(CASE WHEN status = 'ditolak' THEN 1 ELSE 0 END) as ditolak
        FROM penjemputan 
        WHERE id_mitra = ?
    ";
    $stmt = $pdo->prepare($sql_stats);
    $stmt->execute([$mitra_id]);
    $stats = $stmt->fetch();
    
    if ($stats) {
        $total_penjemputan = $stats['total_penjemputan'] ?? 0;
        $total_pending = $stats['pending'] ?? 0;
        $total_dijadwalkan = $stats['dijadwalkan'] ?? 0;
        $total_diproses = $stats['diproses'] ?? 0;
        $total_selesai = $stats['selesai'] ?? 0;
        $total_ditolak = $stats['ditolak'] ?? 0;
    }

    // AMBIL DATA RATING
$sql_rating = "
    SELECT 
        AVG(rating) as rating_avg,
        COUNT(*) as total_ulasan
    FROM rating_mitra 
    WHERE id_mitra = ?
";
$stmt = $pdo->prepare($sql_rating);
$stmt->execute([$mitra_id]);
$rating_data = $stmt->fetch();

if ($rating_data) {
    $rating_avg = round($rating_data['rating_avg'] ?? 0, 1);
    $total_ulasan = $rating_data['total_ulasan'] ?? 0;
} else {
    $rating_avg = 0;
    $total_ulasan = 0;
}

    // AMBIL JADWAL PENJEMPUTAN HARI INI - PERBAIKI QUERY INI
    $hari_ini = date('Y-m-d');
    
    // Coba dengan tabel users jika tabel warga tidak ada
    try {
        $sql_jadwal = "
    SELECT p.*, u.nama_lengkap as nama_warga, u.telepon as hp_warga
    FROM penjemputan p 
    JOIN users u ON p.id_warga = u.id 
    WHERE p.id_mitra = ? 
    AND DATE(p.waktu_penjemputan) = ?
    AND p.status IN ('dijadwalkan', 'diproses')
    ORDER BY p.waktu_penjemputan ASC
    LIMIT 5
        ";
        $stmt = $pdo->prepare($sql_jadwal);
        $stmt->execute([$mitra_id, $hari_ini]);
        $jadwal_hari_ini = $stmt->fetchAll();
    } catch (Exception $e) {
        // Fallback jika join dengan users gagal
        $sql_jadwal = "
            SELECT p.*, p.alamat_penjemputan
            FROM penjemputan p 
            WHERE p.id_mitra = ? 
            AND DATE(p.waktu_penjemputan) = ?
            AND p.status IN ('dijadwalkan', 'diproses')
            ORDER BY p.waktu_penjemputan ASC
            LIMIT 5
        ";
        $stmt = $pdo->prepare($sql_jadwal);
        $stmt->execute([$mitra_id, $hari_ini]);
        $jadwal_hari_ini = $stmt->fetchAll();
        
        // Tambahkan nama default untuk data yang diambil
        foreach ($jadwal_hari_ini as &$jadwal) {
            $jadwal['nama_warga'] = 'Warga';
            $jadwal['hp_warga'] = '-';
        }
    }

    // AMBIL PENJEMPUTAN TERBARU - PERBAIKI QUERY INI
    try {
        $sql_terbaru = "
            SELECT p.*, u.nama as nama_warga, u.telepon as hp_warga
            FROM penjemputan p 
            JOIN users u ON p.id_warga = u.id 
            WHERE p.id_mitra = ? 
            ORDER BY p.waktu_pemintaan DESC 
            LIMIT 5
        ";
        $stmt = $pdo->prepare($sql_terbaru);
        $stmt->execute([$mitra_id]);
        $penjemputan_terbaru = $stmt->fetchAll();
    } catch (Exception $e) {
        // Fallback jika join dengan users gagal
        $sql_terbaru = "
            SELECT p.*, p.alamat_penjemputan
            FROM penjemputan p 
            WHERE p.id_mitra = ? 
            ORDER BY p.waktu_pemintaan DESC 
            LIMIT 5
        ";
        $stmt = $pdo->prepare($sql_terbaru);
        $stmt->execute([$mitra_id]);
        $penjemputan_terbaru = $stmt->fetchAll();
        
        // Tambahkan nama default untuk data yang diambil
        foreach ($penjemputan_terbaru as &$penjemputan) {
            $penjemputan['nama_warga'] = 'Warga';
            $penjemputan['hp_warga'] = '-';
        }
    }

    // AMBIL PENJEMPUTAN PENDING UNTUK QUICK ACTIONS
    try {
        $sql_pending = "
            SELECT COUNT(*) as total 
            FROM penjemputan 
            WHERE id_mitra = ? AND status = 'pending'
        ";
        $stmt = $pdo->prepare($sql_pending);
        $stmt->execute([$mitra_id]);
        $pending_data = $stmt->fetch();
        $total_pending = $pending_data['total'] ?? 0;
    } catch (Exception $e) {
        // Jika query khusus pending gagal, gunakan data dari stats
        $total_pending = $stats['pending'] ?? 0;
    }

} catch (PDOException $e) {
    error_log("Dashboard Mitra Error: " . $e->getMessage());
    $error = "Terjadi kesalahan saat mengambil data: " . $e->getMessage();
}

// HANDLE SUCCESS MESSAGE
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Mitra - Bank Sampah</title>
    <style>
        :root {
            --primary: #2e7d32;
            --primary-light: #4caf50;
            --secondary: #ff9800;
            --light: #f5f5f5;
            --dark: #333;
            --gray: #757575;
            --white: #ffffff;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --border-radius: 8px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--light);
            color: var(--dark);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header Styles */
        header {
            background-color: var(--white);
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logo-icon {
            font-size: 1.8rem;
        }
        
        .logo h1 {
            font-size: 1.5rem;
            color: var(--primary);
        }
        
        nav ul {
            display: flex;
            list-style: none;
            gap: 20px;
        }
        
        nav a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            padding: 8px 15px;
            border-radius: var(--border-radius);
            transition: all 0.3s;
        }
        
        nav a:hover, nav a.active {
            background-color: var(--primary);
            color: var(--white);
        }
        
        /* Main Content */
        main {
            padding: 30px 0;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .page-title h1 {
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .page-title p {
            color: var(--gray);
        }
        
        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: var(--white);
            padding: 30px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        
        .welcome-content h2 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
        
        .welcome-content p {
            opacity: 0.9;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--white);
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
            border-left: 4px solid var(--primary);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-card.pending { border-left-color: var(--warning); }
        .stat-card.dijadwalkan { border-left-color: var(--info); }
        .stat-card.diproses { border-left-color: var(--secondary); }
        .stat-card.selesai { border-left-color: var(--success); }
        .stat-card.ditolak { border-left-color: var(--danger); }
        .stat-card.rating { border-left-color: #ff6b6b; }
        
        .stat-card h3 {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 10px;
        }
        
        .stat-card .number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        .rating-stars {
            display: flex;
            justify-content: center;
            gap: 2px;
            margin: 10px 0;
        }
        
        .star {
            color: #ffc107;
            font-size: 1.2rem;
        }

        .star.active {
            color: #ffc107;
        }

        .star:not(.active) {
            color: #ddd;
        }
        
        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .dashboard-card {
            background: var(--white);
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .card-title {
            font-size: 1.2rem;
            color: var(--primary);
            font-weight: 600;
        }
        
        .card-link {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .card-link:hover {
            text-decoration: underline;
        }
        
        /* List Styles */
        .item-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: var(--border-radius);
            border-left: 3px solid var(--primary);
            transition: background-color 0.3s;
        }

        .list-item:hover {
            background: #e9ecef;
        }
        
        .item-info h4 {
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .item-meta {
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-dijadwalkan { background: #d1ecf1; color: #0c5460; }
        .status-diproses { background: #d1edff; color: #004085; }
        .status-selesai { background: #d4edda; color: #155724; }
        .status-ditolak { background: #f8d7da; color: #721c24; }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 30px;
        }
        
        .action-card {
            background: var(--white);
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
            text-decoration: none;
            color: var(--dark);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        
        .action-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .action-title {
            font-weight: 600;
            color: var(--primary);
        }
        
        /* Alert Styles */
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            font-weight: 500;
            animation: slideIn 0.3s;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: var(--gray);
        }
        
        /* Footer */
        footer {
            background-color: var(--white);
            padding: 20px 0;
            text-align: center;
            color: var(--gray);
            border-top: 1px solid #e0e0e0;
            margin-top: 50px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
            
            nav ul {
                flex-wrap: wrap;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<header>
    <div class="container">
        <div class="header-content">
            <div class="logo">
                <span class="logo-icon">♻️</span>
                <h1>Bank Sampah - Dashboard Mitra</h1>
            </div>
            <nav>
                <ul>
                    <li><a href="dashboard-mitra.php" class="active">Dashboard</a></li>
                    <li><a href="manage-penjemputan.php">Penjemputan</a></li>
                    <li><a href="manage-jadwal.php">Jadwal</a></li>
                    <li><a href="riwayat-mitra.php">Riwayat</a></li> <!-- TAMBAHAN -->
                    <li><a href="pengumuman-mitra.php">Pengumuman</a></li> <!-- TAMBAHAN -->
                    <li><a href="feedback-mitra.php">Feedback</a></li> <!-- TAMBAHAN -->
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </div>
</header>

    <main>
        <div class="container">
            <!-- Alert Messages -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    ✅ <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    ❌ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Welcome Section -->
           <!-- Welcome Section -->
<section class="welcome-section">
    <div class="welcome-content">
        <h2>Selamat Datang, Mitra Bank Sampah! 👋</h2>
        <p>Kelola penjemputan sampah dengan mudah dan efisien.</p>
        
        <!-- TAMBAHAN: Indikator Lokasi Real-time -->
        <div class="location-indicator" style="margin-top: 15px; padding: 10px; background: rgba(255,255,255,0.2); border-radius: 5px; display: inline-block;">
            <span id="location-status">📍 Lokasi: <strong id="location-text">Mengambil lokasi...</strong></span>
            <button id="update-location-btn" style="margin-left: 10px; padding: 5px 10px; background: var(--white); color: var(--primary); border: none; border-radius: 3px; cursor: pointer; font-size: 0.8rem;">
                🔄 Update Lokasi
            </button>
        </div>
    </div>
</section>

            <!-- Stats Grid -->
            <section class="stats-grid">
                <div class="stat-card">
                    <h3>📊 Total Penjemputan</h3>
                    <div class="number"><?php echo $total_penjemputan; ?></div>
                </div>
                <div class="stat-card pending">
                    <h3>⏳ Menunggu Konfirmasi</h3>
                    <div class="number"><?php echo $total_pending; ?></div>
                </div>
                <div class="stat-card dijadwalkan">
                    <h3>📅 Dijadwalkan</h3>
                    <div class="number"><?php echo $total_dijadwalkan; ?></div>
                </div>
                <div class="stat-card diproses">
                    <h3>🚚 Sedang Diproses</h3>
                    <div class="number"><?php echo $total_diproses; ?></div>
                </div>
                <div class="stat-card selesai">
                    <h3>✅ Selesai</h3>
                    <div class="number"><?php echo $total_selesai; ?></div>
                </div>
                <div class="stat-card rating">
                    <h3>⭐ Rating Rata-rata</h3>
                    <div class="number"><?php echo $rating_avg; ?>/5</div>
                    <div class="rating-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="star <?php echo $i <= round($rating_avg) ? 'active' : ''; ?>">★</span>
                        <?php endfor; ?>
                    </div>
                    <small><?php echo $total_ulasan; ?> ulasan</small>
                </div>
            </section>

            <!-- Dashboard Grid -->
            <section class="dashboard-grid">
                <!-- Jadwal Hari Ini -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">📅 Jadwal Penjemputan Hari Ini</h3>
                        <a href="manage-jadwal.php?status=dijadwalkan" class="card-link">Lihat Semua →</a>
                    </div>
                    
                    <?php if (empty($jadwal_hari_ini)): ?>
                        <div class="empty-state">
                            <h3>📭 Tidak ada jadwal hari ini</h3>
                            <p>Belum ada penjemputan yang dijadwalkan untuk hari ini.</p>
                        </div>
                    <?php else: ?>
                        <div class="item-list">
                            <?php foreach ($jadwal_hari_ini as $jadwal): ?>
                            <div class="list-item">
                                <div class="item-info">
                                    <h4>🗑️ <?php echo htmlspecialchars($jadwal['jenis_sampah'] ?? 'Sampah'); ?></h4>
                                    <div class="item-meta">
                                        👤 <?php echo htmlspecialchars($jadwal['nama_warga'] ?? 'Warga'); ?> • 
                                        ⏰ <?php echo date('H:i', strtotime($jadwal['waktu_penjemputan'])); ?>
                                    </div>
                                </div>
                                <span class="status-badge status-<?php echo $jadwal['status']; ?>">
                                    <?php echo $jadwal['status'] === 'dijadwalkan' ? '📅 Terjadwal' : '🚚 Diproses'; ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Penjemputan Terbaru -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">🆕 Permintaan Terbaru</h3>
                        <a href="manage-penjemputan.php" class="card-link">Lihat Semua →</a>
                    </div>
                    
                    <?php if (empty($penjemputan_terbaru)): ?>
                        <div class="empty-state">
                            <h3>🎉 Belum ada permintaan</h3>
                            <p>Belum ada permintaan penjemputan yang masuk.</p>
                        </div>
                    <?php else: ?>
                        <div class="item-list">
                            <?php foreach ($penjemputan_terbaru as $penjemputan): ?>
                            <div class="list-item">
                                <div class="item-info">
                                    <h4>🗑️ <?php echo htmlspecialchars($penjemputan['jenis_sampah'] ?? 'Sampah'); ?></h4>
                                    <div class="item-meta">
                                        👤 <?php echo htmlspecialchars($penjemputan['nama_warga'] ?? 'Warga'); ?> • 
                                        📅 <?php echo date('d/m H:i', strtotime($penjemputan['waktu_pemintaan'])); ?>
                                    </div>
                                </div>
                                <span class="status-badge status-<?php echo $penjemputan['status']; ?>">
                                    <?php 
                                    $status_text = [
                                        'pending' => '⏳ Pending',
                                        'dijadwalkan' => '📅 Terjadwal', 
                                        'diproses' => '🚚 Diproses',
                                        'selesai' => '✅ Selesai',
                                        'ditolak' => '❌ Ditolak'
                                    ];
                                    echo $status_text[$penjemputan['status']] ?? $penjemputan['status'];
                                    ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Quick Actions -->
           <!-- Quick Actions -->
<section class="quick-actions">
    <a href="manage-penjemputan.php?status=pending" class="action-card">
        <div class="action-icon">⏳</div>
        <div class="action-title">Kelola Pending</div>
        <small><?php echo $total_pending; ?> menunggu</small>
    </a>
    
    <a href="manage-jadwal.php" class="action-card">
        <div class="action-icon">📅</div>
        <div class="action-title">Kelola Jadwal</div>
        <small><?php echo count($jadwal_hari_ini); ?> penjemputan</small>
    </a>
    
    <a href="manage-penjemputan.php?status=diproses" class="action-card">
        <div class="action-icon">🚚</div>
        <div class="action-title">Sedang Diproses</div>
        <small><?php echo $total_diproses; ?> aktif</small>
    </a>
    
    <!-- TAMBAHAN: Quick Actions Baru -->
    <a href="riwayat-mitra.php" class="action-card">
        <div class="action-icon">📋</div>
        <div class="action-title">Riwayat</div>
        <small><?php echo $total_selesai; ?> selesai</small>
    </a>
    
    <a href="pengumuman-mitra.php" class="action-card">
        <div class="action-icon">📢</div>
        <div class="action-title">Pengumuman</div>
        <small>Pemberitahuan</small>
    </a>
    
    <a href="feedback-mitra.php" class="action-card">
        <div class="action-icon">💬</div>
        <div class="action-title">Feedback</div>
        <small>Kirim masukan</small>
    </a>
    
    <a href="profile-mitra.php" class="action-card">
        <div class="action-icon">👤</div>
        <div class="action-title">Profile Saya</div>
        <small>Update profile</small>
    </a>
</section>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Bank Sampah. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.transition = 'opacity 0.5s ease-out';
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.parentNode.removeChild(alert);
                        }
                    }, 500);
                });
            }, 5000);
        });
        // TAMBAHAN: Fungsi Lokasi Real-time
const locationStatus = document.getElementById('location-status');
const locationText = document.getElementById('location-text');
const updateLocationBtn = document.getElementById('update-location-btn');

function updateLocation() {
    locationText.innerHTML = 'Mengambil lokasi...';
    
    if (!navigator.geolocation) {
        locationText.innerHTML = 'Lokasi tidak didukung';
        return;
    }

    navigator.geolocation.getCurrentPosition(
        function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            
            // Simpan lokasi ke server (simulasi)
            saveLocationToServer(lat, lng);
            
            locationText.innerHTML = `Aktif (${lat.toFixed(4)}, ${lng.toFixed(4)})`;
            locationStatus.style.color = '#4CAF50';
        },
        function(error) {
            console.error('Error getting location:', error);
            locationText.innerHTML = 'Lokasi dimatikan';
            locationStatus.style.color = '#f44336';
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 60000
        }
    );
}

function saveLocationToServer(lat, lng) {
    // Simulasi penyimpanan lokasi ke server
    fetch('update-location.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            mitra_id: <?php echo $mitra_id; ?>,
            latitude: lat,
            longitude: lng,
            timestamp: new Date().toISOString()
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Lokasi berhasil disimpan:', data);
    })
    .catch(error => {
        console.error('Error menyimpan lokasi:', error);
    });
}

// Event listener untuk tombol update lokasi
if (updateLocationBtn) {
    updateLocationBtn.addEventListener('click', updateLocation);
}

// Update lokasi otomatis setiap 2 menit
updateLocation();
setInterval(updateLocation, 120000);
    </script>
</body>
</html>