<?php
include 'config.php';

// Cek apakah user sudah login sebagai mitra
if (!isLoggedIn() || !isMitra()) {
    header("Location: login-mitra.php");
    exit();
}

$mitra_id = $_SESSION['mitra_id'];
$success = '';
$error = '';

// INISIALISASI VARIABEL DENGAN NILAI DEFAULT
$penjemputan = [];
$total_pending = 0;
$total_dijadwalkan = 0;
$total_diproses = 0;
$total_selesai = 0;

// AMBIL DATA PENJEMPUTAN - PERBAIKI QUERY INI
try {
    // Filter berdasarkan status dan tanggal
    $status_filter = $_GET['status'] ?? '';
    $tanggal_filter = $_GET['tanggal'] ?? '';
    
    // PERBAIKAN: GUNAKAN nama_lengkap BUKAN nama
    $sql = "SELECT p.*, u.nama_lengkap as nama_warga, u.telepon, u.alamat as alamat_warga 
            FROM penjemputan p 
            JOIN users u ON p.id_warga = u.id 
            WHERE p.id_mitra = ?";
    
    $params = [$mitra_id];
    
    if ($status_filter) {
        $sql .= " AND p.status = ?";
        $params[] = $status_filter;
    }
    
    if ($tanggal_filter) {
        $sql .= " AND DATE(p.waktu_penjemputan) = ?";
        $params[] = $tanggal_filter;
    }
    
    $sql .= " ORDER BY 
                CASE 
                    WHEN p.status = 'dijadwalkan' AND DATE(p.waktu_penjemputan) = CURDATE() THEN 1
                    WHEN p.status = 'dijadwalkan' THEN 2
                    WHEN p.status = 'pending' THEN 3
                    WHEN p.status = 'diproses' THEN 4
                    ELSE 5
                END,
                p.waktu_penjemputan ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $penjemputan = $stmt->fetchAll();

    // HITUNG STATISTIK DARI DATABASE (SEPERTI DI DASHBOARD)
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
        $total_pending = $stats['pending'] ?? 0;
        $total_dijadwalkan = $stats['dijadwalkan'] ?? 0;
        $total_diproses = $stats['diproses'] ?? 0;
        $total_selesai = $stats['selesai'] ?? 0;
        $total_ditolak = $stats['ditolak'] ?? 0;
    }

} catch (PDOException $e) {
    error_log("Manage Jadwal Error: " . $e->getMessage());
    $error = "Terjadi kesalahan saat mengambil data jadwal: " . $e->getMessage();
}
// Proses update status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $id_penjemputan = (int)$_POST['id_penjemputan'];
        $status = sanitize($_POST['status']);
        $waktu_penjemputan = !empty($_POST['waktu_penjemputan']) ? $_POST['waktu_penjemputan'] : null;
        
        try {
            $pdo->beginTransaction();
            
            if ($status === 'dijadwalkan' && $waktu_penjemputan) {
                // PERBAIKAN: HAPUS updated_at KARENA TIDAK ADA DI TABEL
                $stmt = $pdo->prepare("
                    UPDATE penjemputan 
                    SET status = ?, waktu_penjemputan = ? 
                    WHERE id = ? AND id_mitra = ?
                ");
                $stmt->execute([$status, $waktu_penjemputan, $id_penjemputan, $mitra_id]);
            } else {
                // PERBAIKAN: HAPUS updated_at KARENA TIDAK ADA DI TABEL
                $stmt = $pdo->prepare("
                    UPDATE penjemputan 
                    SET status = ? 
                    WHERE id = ? AND id_mitra = ?
                ");
                $stmt->execute([$status, $id_penjemputan, $mitra_id]);
            }
            
            // Buat notifikasi untuk user
            $stmt = $pdo->prepare("
                SELECT u.id, p.jenis_sampah 
                FROM penjemputan p 
                JOIN users u ON p.id_warga = u.id 
                WHERE p.id = ?
            ");
            $stmt->execute([$id_penjemputan]);
            $data = $stmt->fetch();
            
            if ($data) {
                $status_text = [
                    'pending' => 'Menunggu Konfirmasi',
                    'dijadwalkan' => 'Dijadwalkan',
                    'diproses' => 'Sedang Diproses',
                    'selesai' => 'Selesai',
                    'ditolak' => 'Ditolak'
                ];
                
                $judul = "Status Penjemputan Diperbarui";
                $pesan = "Penjemputan sampah {$data['jenis_sampah']} telah diubah status menjadi: {$status_text[$status]}";
                
                $stmt = $pdo->prepare("
                    INSERT INTO notifikasi (user_id, judul, pesan) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$data['id'], $judul, $pesan]);
            }
            
            $pdo->commit();
            $success = "✅ Status penjemputan berhasil diperbarui!";
            
            // Refresh halaman
            header("Location: manage-jadwal.php?success=" . urlencode($success));
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Update Status Error: " . $e->getMessage());
            $error = "❌ Gagal memperbarui status: " . $e->getMessage();
        }
    }
}

// Handle success message from GET parameter
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Jadwal - Bank Sampah</title>
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
        
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background-color: var(--primary);
            color: var(--white);
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .back-button:hover {
            background-color: var(--primary-light);
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
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        
        .stat-card h3 {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 10px;
        }
        
        .stat-card .number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        /* Filter Section */
        .filter-section {
            background: var(--white);
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 25px;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-group label {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.9rem;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.1);
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background: var(--primary-light);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }
        
        /* Table Styles */
        .table-container {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th, .data-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .data-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .data-table tr:hover {
            background-color: #f8f9fa;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-dijadwalkan { background: #d1ecf1; color: #0c5460; }
        .status-diproses { background: #d1edff; color: #004085; }
        .status-selesai { background: #d4edda; color: #155724; }
        .status-ditolak { background: #f8d7da; color: #721c24; }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }
        
        .btn-success { background: var(--success); color: white; }
        .btn-warning { background: var(--warning); color: var(--dark); }
        .btn-danger { background: var(--danger); color: white; }
        .btn-info { background: var(--info); color: white; }
        
        .btn-success:hover { background: #218838; }
        .btn-warning:hover { background: #e0a800; }
        .btn-danger:hover { background: #c82333; }
        .btn-info:hover { background: #138496; }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background-color: var(--white);
            margin: 5% auto;
            padding: 0;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 500px;
            box-shadow: var(--shadow);
            animation: slideIn 0.3s;
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            background: var(--primary);
            color: var(--white);
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }
        
        .modal-title {
            margin: 0;
            color: var(--white);
            font-size: 1.2rem;
        }
        
        .close {
            font-size: 24px;
            cursor: pointer;
            color: var(--white);
            background: none;
            border: none;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .close:hover {
            color: #e0e0e0;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        /* Alert Styles */
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            font-weight: 500;
            animation: slideDown 0.3s;
        }

        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
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
            padding: 60px 20px;
            color: var(--gray);
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: var(--gray);
            font-size: 1.2rem;
        }

        .empty-state p {
            margin-bottom: 20px;
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
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            .data-table {
                min-width: 800px;
            }
            
            .action-buttons {
                flex-direction: column;
            }

            .action-buttons .btn {
                width: 100%;
                text-align: center;
            }
            
            nav ul {
                flex-wrap: wrap;
                justify-content: center;
            }

            .modal-content {
                margin: 10% auto;
                width: 95%;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .btn-sm {
                font-size: 0.7rem;
                padding: 5px 10px;
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
                    <h1>Bank Sampah - Kelola Jadwal</h1>
                </div>
                <nav>
                    <ul>
                    <li><a href="dashboard-mitra.php">Dashboard</a></li>
                        <li><a href="manage-penjemputan.php" >Penjemputan</a></li>
                        <li><a href="manage-jadwal.php"class="active">Jadwal</a></li>
                        <li><a href="riwayat-mitra.php" >Riwayat</a></li>
                        <li><a href="pengumuman-mitra.php">Pengumuman</a></li>
                        <li><a href="feedback-mitra.php">Feedback</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <section class="jadwal-management">
            <div class="container">
                <div class="page-header">
                    <div class="page-title">
                        <h1>🗓️ Kelola Jadwal Penjemputan</h1>
                        <p>Kelola semua jadwal penjemputan yang ditugaskan kepada Anda</p>
                    </div>
                    <a href="dashboard-mitra.php" class="back-button">
                        ← Kembali ke Dashboard
                    </a>
                </div>

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

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>⏳ Menunggu Konfirmasi</h3>
                        <div class="number"><?php echo $total_pending; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>📅 Dijadwalkan</h3>
                        <div class="number"><?php echo $total_dijadwalkan; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>🚚 Sedang Diproses</h3>
                        <div class="number"><?php echo $total_diproses; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>✅ Selesai</h3>
                        <div class="number"><?php echo $total_selesai; ?></div>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" class="filter-form">
                        <div class="filter-grid">
                            <div class="form-group">
                                <label for="status">🔍 Filter Status</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="">Semua Status</option>
                                    <option value="pending" <?php echo ($status_filter ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="dijadwalkan" <?php echo ($status_filter ?? '') === 'dijadwalkan' ? 'selected' : ''; ?>>Dijadwalkan</option>
                                    <option value="diproses" <?php echo ($status_filter ?? '') === 'diproses' ? 'selected' : ''; ?>>Diproses</option>
                                    <option value="selesai" <?php echo ($status_filter ?? '') === 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                                    <option value="ditolak" <?php echo ($status_filter ?? '') === 'ditolak' ? 'selected' : ''; ?>>Ditolak</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="tanggal">📅 Filter Tanggal</label>
                                <input type="date" id="tanggal" name="tanggal" class="form-control" 
                                       value="<?php echo htmlspecialchars($tanggal_filter ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div style="display: flex; gap: 10px; flex-direction: column;">
                                    <button type="submit" class="btn btn-primary">
                                        🔍 Terapkan Filter
                                    </button>
                                    <a href="manage-jadwal.php" class="btn btn-outline">
                                        🔄 Reset Filter
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Jadwal Table -->
                <div class="table-container">
                    <?php if (empty($penjemputan)): ?>
                        <div class="empty-state">
                            <h3>📭 Belum ada penjemputan</h3>
                            <p>Tidak ada jadwal penjemputan yang ditugaskan kepada Anda saat ini.</p>
                            <a href="manage-penjemputan.php" class="btn btn-primary" style="margin-top: 15px;">
                                Lihat Permintaan Penjemputan
                            </a>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>👤 Warga</th>
                                    <th>🗑️ Jenis Sampah</th>
                                    <th>📍 Alamat</th>
                                    <th>📊 Status</th>
                                    <th>⏰ Waktu Penjemputan</th>
                                    <th>⚡ Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($penjemputan as $p): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($p['nama_warga'] ?? 'N/A'); ?></strong>
                                        <br>
                                        <small style="color: var(--gray);">📞 <?php echo htmlspecialchars($p['telepon'] ?? '-'); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($p['jenis_sampah'] ?? 'Sampah'); ?></td>
                                    <td>
                                        <div style="max-width: 200px;">
                                            <?php echo htmlspecialchars($p['alamat_penjemputan'] ?? 'Alamat tidak tersedia'); ?>
                                            <?php if (!empty($p['keterangan'])): ?>
                                                <br><small style="color: var(--gray);">📝 <?php echo htmlspecialchars($p['keterangan']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $p['status']; ?>">
                                            <?php 
                                            $status_text = [
                                                'pending' => '⏳ Menunggu',
                                                'dijadwalkan' => '📅 Dijadwalkan', 
                                                'diproses' => '🚚 Diproses',
                                                'selesai' => '✅ Selesai',
                                                'ditolak' => '❌ Ditolak'
                                            ];
                                            echo $status_text[$p['status']] ?? $p['status'];
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($p['waktu_penjemputan'])): ?>
                                            <?php echo date('d/m/Y H:i', strtotime($p['waktu_penjemputan'])); ?>
                                        <?php else: ?>
                                            <span style="color: var(--gray);">Belum dijadwalkan</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($p['status'] === 'pending'): ?>
                                                <button type="button" class="btn btn-success btn-sm" 
                                                        onclick="openStatusModal(<?php echo $p['id']; ?>, 'dijadwalkan')">
                                                    ✅ Setujui
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm" 
                                                        onclick="openStatusModal(<?php echo $p['id']; ?>, 'ditolak')">
                                                    ❌ Tolak
                                                </button>
                                            <?php elseif ($p['status'] === 'dijadwalkan'): ?>
                                                <button type="button" class="btn btn-info btn-sm" 
                                                        onclick="openStatusModal(<?php echo $p['id']; ?>, 'diproses')">
                                                    🚚 Mulai Proses
                                                </button>
                                                <button type="button" class="btn btn-warning btn-sm" 
                                                        onclick="openEditModal(<?php echo $p['id']; ?>, '<?php echo $p['waktu_penjemputan'] ?? ''; ?>')">
                                                    ✏️ Edit Jadwal
                                                </button>
                                            <?php elseif ($p['status'] === 'diproses'): ?>
                                                <button type="button" class="btn btn-success btn-sm" 
                                                        onclick="openStatusModal(<?php echo $p['id']; ?>, 'selesai')">
                                                    ✅ Selesai
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($p['status'] === 'dijadwalkan' && empty($p['waktu_penjemputan'])): ?>
                                                <button type="button" class="btn btn-warning btn-sm" 
                                                        onclick="openEditModal(<?php echo $p['id']; ?>, '')">
                                                    ⏰ Atur Jadwal
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <!-- Modal untuk mengubah status -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">✏️ Ubah Status Penjemputan</h2>
                <button type="button" class="close" onclick="closeModal('statusModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="statusForm" method="POST">
                    <input type="hidden" name="id_penjemputan" id="modal_penjemputan_id">
                    <input type="hidden" name="status" id="modal_status">
                    <input type="hidden" name="update_status" value="1">
                    
                    <div class="form-group">
                        <label>📊 Status Baru:</label>
                        <div id="statusDisplay" style="font-weight: bold; padding: 10px; background: #f8f9fa; border-radius: 5px;"></div>
                    </div>
                    
                    <div id="scheduleSection" style="display: none;">
                        <div class="form-group">
                            <label for="waktu_penjemputan">⏰ Jadwalkan Waktu Penjemputan:</label>
                            <input type="datetime-local" id="waktu_penjemputan" name="waktu_penjemputan" class="form-control" required>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            ✅ Konfirmasi Perubahan
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeModal('statusModal')">
                            ❌ Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Bank Sampah. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Modal Functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Open status modal
        function openStatusModal(penjemputanId, status) {
            document.getElementById('modal_penjemputan_id').value = penjemputanId;
            document.getElementById('modal_status').value = status;
            
            const statusText = {
                'pending': '⏳ Menunggu Konfirmasi',
                'dijadwalkan': '📅 Dijadwalkan',
                'diproses': '🚚 Sedang Diproses', 
                'selesai': '✅ Selesai',
                'ditolak': '❌ Ditolak'
            };
            
            document.getElementById('statusDisplay').textContent = statusText[status] || status;
            
            // Show/hide schedule section
            const scheduleSection = document.getElementById('scheduleSection');
            if (status === 'dijadwalkan') {
                scheduleSection.style.display = 'block';
                // Set minimum datetime to current time
                const now = new Date();
                now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
                document.getElementById('waktu_penjemputan').min = now.toISOString().slice(0, 16);
                document.getElementById('waktu_penjemputan').value = now.toISOString().slice(0, 16);
            } else {
                scheduleSection.style.display = 'none';
            }
            
            openModal('statusModal');
        }
        
        // Open edit modal for scheduling
        function openEditModal(penjemputanId, currentTime) {
            document.getElementById('modal_penjemputan_id').value = penjemputanId;
            document.getElementById('modal_status').value = 'dijadwalkan';
            document.getElementById('statusDisplay').textContent = '✏️ Edit Jadwal Penjemputan';
            
            const scheduleSection = document.getElementById('scheduleSection');
            scheduleSection.style.display = 'block';
            
            if (currentTime) {
                // Format datetime for input
                const date = new Date(currentTime);
                date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
                document.getElementById('waktu_penjemputan').value = date.toISOString().slice(0, 16);
            } else {
                const now = new Date();
                now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
                document.getElementById('waktu_penjemputan').value = now.toISOString().slice(0, 16);
            }
            
            // Set minimum datetime to current time
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.getElementById('waktu_penjemputan').min = now.toISOString().slice(0, 16);
            
            openModal('statusModal');
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Close modal when clicking outside
            window.onclick = function(event) {
                const modals = document.getElementsByClassName('modal');
                for (let modal of modals) {
                    if (event.target === modal) {
                        closeModal(modal.id);
                    }
                }
            }
            
            // Close modal with Escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    const modals = document.getElementsByClassName('modal');
                    for (let modal of modals) {
                        if (modal.style.display === 'block') {
                            closeModal(modal.id);
                        }
                    }
                }
            });
            
            // Auto-hide alerts
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
            
            // Form validation for schedule
            const statusForm = document.getElementById('statusForm');
            if (statusForm) {
                statusForm.addEventListener('submit', function(e) {
                    const scheduleSection = document.getElementById('scheduleSection');
                    const waktuInput = document.getElementById('waktu_penjemputan');
                    
                    if (scheduleSection.style.display === 'block' && !waktuInput.value) {
                        e.preventDefault();
                        alert('❌ Silakan tentukan jadwal penjemputan!');
                        waktuInput.focus();
                        return false;
                    }
                    
                    const selectedTime = new Date(waktuInput.value);
                    const now = new Date();
                    
                    if (scheduleSection.style.display === 'block' && selectedTime < now) {
                        e.preventDefault();
                        alert('❌ Waktu penjemputan tidak boleh kurang dari waktu saat ini!');
                        waktuInput.focus();
                        return false;
                    }
                });
            }
        });
    </script>
</body>
</html>