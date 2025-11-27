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

// INISIALISASI VARIABEL STATISTIK DENGAN NILAI DEFAULT
$total_penjemputan = 0;
$total_pending = 0;
$total_dijadwalkan = 0;
$total_diproses = 0;
$total_selesai = 0;
$total_ditolak = 0;
$penjemputan = [];

// AMBIL DATA JENIS SAMPAH UNTUK PERHITUNGAN HARGA
try {
    $stmt = $pdo->prepare("SELECT * FROM jenis_sampah ORDER BY nama_jenis");
    $stmt->execute();
    $jenis_sampah = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Jenis Sampah Error: " . $e->getMessage());
    $jenis_sampah = [];
}

// PROSES UPDATE STATUS PENJEMPUTAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id_penjemputan = (int)$_POST['id_penjemputan'];
    $status = sanitize($_POST['status']);
    $catatan = sanitize($_POST['catatan'] ?? '');
    
    try {
        // CEK APAKAH PENJEMPUTAN MILIK MITRA INI
        $stmt = $pdo->prepare("SELECT id FROM penjemputan WHERE id = ? AND id_mitra = ?");
        $stmt->execute([$id_penjemputan, $mitra_id]);
        $penjemputan = $stmt->fetch();
        
        if (!$penjemputan) {
            $error = "Penjemputan tidak ditemukan!";
        } else {
            $stmt = $pdo->prepare("
                UPDATE penjemputan 
                SET status = ?, keterangan = ? 
                WHERE id = ?
            ");
            $stmt->execute([$status, $catatan, $id_penjemputan]);
            
            // JIKA STATUS DIJADWALKAN, UPDATE WAKTU PENJEMPUTAN JIKA ADA
            if ($status === 'dijadwalkan' && !empty($_POST['waktu_penjemputan'])) {
                $waktu_penjemputan = $_POST['waktu_penjemputan'];
                $stmt = $pdo->prepare("
                    UPDATE penjemputan 
                    SET waktu_penjemputan = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$waktu_penjemputan, $id_penjemputan]);
            }
            
            $success = "Status penjemputan berhasil diperbarui!";
            
            // Redirect untuk refresh data
            header("Location: manage-penjemputan.php?success=" . urlencode($success));
            exit();
        }
        
    } catch (Exception $e) {
        error_log("Update Status Error: " . $e->getMessage());
        $error = "Gagal memperbarui status: " . $e->getMessage();
    }
}

// PROSES TOLAK PENJEMPUTAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tolak_penjemputan'])) {
    $id_penjemputan = (int)$_POST['id_penjemputan'];
    $alasan_penolakan = sanitize($_POST['alasan_penolakan']);
    
    try {
        // CEK APAKAH PENJEMPUTAN MILIK MITRA INI DAN MASIH PENDING
        $stmt = $pdo->prepare("
            SELECT id FROM penjemputan 
            WHERE id = ? AND id_mitra = ? AND status = 'pending'
        ");
        $stmt->execute([$id_penjemputan, $mitra_id]);
        $penjemputan = $stmt->fetch();
        
        if (!$penjemputan) {
            $error = "Penjemputan tidak valid atau sudah diproses!";
        } else {
            $stmt = $pdo->prepare("
                UPDATE penjemputan 
                SET status = 'ditolak', keterangan = ? 
                WHERE id = ?
            ");
            $stmt->execute([$alasan_penolakan, $id_penjemputan]);
            
            $success = "Penjemputan berhasil ditolak!";
            
            // Redirect untuk refresh data
            header("Location: manage-penjemputan.php?success=" . urlencode($success));
            exit();
        }
        
    } catch (Exception $e) {
        error_log("Tolak Penjemputan Error: " . $e->getMessage());
        $error = "Gagal menolak penjemputan: " . $e->getMessage();
    }
}

// AMBIL DATA PENJEMPUTAN
try {
    // FILTER BERDASARKAN STATUS JIKA ADA
    $status_filter = $_GET['status'] ?? '';
    $search_filter = $_GET['search'] ?? '';
    
    $sql = "SELECT p.*, u.nama_lengkap as nama_warga, u.telepon as hp_warga, u.saldo as saldo_warga
            FROM penjemputan p 
            JOIN users u ON p.id_warga = u.id 
            WHERE p.id_mitra = ?";
    
    $params = [$mitra_id];
    
    if ($status_filter && $status_filter !== 'all') {
        $sql .= " AND p.status = ?";
        $params[] = $status_filter;
    }
    
    if ($search_filter) {
        $sql .= " AND (u.nama_lengkap LIKE ? OR p.jenis_sampah LIKE ? OR p.alamat_penjemputan LIKE ?)";
        $search_term = "%$search_filter%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $sql .= " ORDER BY 
              CASE 
                WHEN p.status = 'pending' THEN 1
                WHEN p.status = 'dijadwalkan' THEN 2
                WHEN p.status = 'diproses' THEN 3
                WHEN p.status = 'selesai' THEN 4
                WHEN p.status = 'ditolak' THEN 5
              END,
              p.waktu_pemintaan DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $penjemputan = $stmt->fetchAll();
    
    // HITUNG STATISTIK
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
    
} catch (PDOException $e) {
    error_log("Manage Penjemputan Error: " . $e->getMessage());
    $error = "Terjadi kesalahan saat mengambil data penjemputan: " . $e->getMessage();
}

// FUNGSI UNTUK MENDAPATKAN HARGA JENIS SAMPAH
function getHargaPerKg($jenis_sampah, $jenis_sampah_list) {
    foreach ($jenis_sampah_list as $jenis) {
        if (strpos($jenis_sampah, $jenis['nama_jenis']) !== false) {
            return $jenis['harga_per_kg'];
        }
    }
    return 0; // Default harga jika tidak ditemukan
}

// FUNGSI UNTUK MENGHITUNG PERKIRAAN HARGA
function hitungPerkiraanHarga($penjemputan, $jenis_sampah_list) {
    if (!$penjemputan['berat'] || $penjemputan['berat'] <= 0) {
        return 0;
    }
    
    $harga_per_kg = getHargaPerKg($penjemputan['jenis_sampah'], $jenis_sampah_list);
    return $penjemputan['berat'] * $harga_per_kg;
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
    <title>Manage Penjemputan - Bank Sampah</title>
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
            --purple: #6f42c1;
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
        
        /* Button Styles */
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
        
        .btn-success {
            background: var(--success);
            color: var(--white);
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-danger {
            background: var(--danger);
            color: var(--white);
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-info {
            background: var(--info);
            color: var(--white);
        }
        
        .btn-info:hover {
            background: #138496;
        }
        
        .btn-warning {
            background: var(--warning);
            color: var(--dark);
        }
        
        .btn-warning:hover {
            background: #e0a800;
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
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }
        
        .btn-preview {
            background: var(--info);
            color: var(--white);
        }
        
        .btn-preview:hover {
            background: #138496;
        }
        
        .btn-verify {
            background: var(--purple);
            color: var(--white);
        }
        
        .btn-verify:hover {
            background: #5a2d91;
        }
        
        /* Penjemputan List */
        .penjemputan-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .penjemputan-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 25px;
            border-left: 4px solid var(--primary);
            transition: box-shadow 0.3s;
        }
        
        .penjemputan-card:hover {
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        
        .penjemputan-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .penjemputan-info h3 {
            color: var(--primary);
            margin-bottom: 8px;
            font-size: 1.2rem;
        }
        
        .penjemputan-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        
        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
            min-width: 200px;
        }
        
        .meta-label {
            font-size: 0.8rem;
            color: var(--gray);
            font-weight: 500;
        }
        
        .meta-value {
            font-weight: 600;
            color: var(--dark);
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
            gap: 10px;
            flex-wrap: wrap;
        }
        
        /* Info Harga Styles */
        .info-harga {
            background: #e8f5e8;
            padding: 15px;
            border-radius: var(--border-radius);
            margin-top: 15px;
            border-left: 4px solid var(--success);
        }
        
        .harga-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #d4edda;
        }
        
        .harga-item:last-child {
            border-bottom: none;
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .harga-label {
            color: var(--dark);
        }
        
        .harga-value {
            font-weight: 600;
            color: var(--primary);
        }
        
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
            
            .penjemputan-header {
                flex-direction: column;
            }
            
            .penjemputan-meta {
                flex-direction: column;
                gap: 10px;
            }
            
            .meta-item {
                min-width: auto;
            }
            
            .action-buttons {
                flex-direction: column;
                width: 100%;
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
                    <h1>Bank Sampah - Manage Penjemputan</h1>
                </div>
                <nav>
                    <ul>
                        <li><a href="dashboard-mitra.php">Dashboard</a></li>
                        <li><a href="manage-penjemputan.php" class="active">Penjemputan</a></li>
                        <li><a href="manage-jadwal.php">Jadwal</a></li>
                        <li><a href="riwayat-mitra.php">Riwayat</a></li>
                        <li><a href="pengumuman-mitra.php">Pengumuman</a></li>
                        <li><a href="feedback-mitra.php">Feedback</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <section class="manage-penjemputan">
            <div class="container">
                <div class="page-header">
                    <div class="page-title">
                        <h1>Manage Penjemputan Sampah</h1>
                        <p>Kelola semua permintaan penjemputan sampah dari warga</p>
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
                        <h3>Total Penjemputan</h3>
                        <div class="number"><?php echo $total_penjemputan; ?></div>
                    </div>
                    <div class="stat-card pending">
                        <h3>Menunggu Konfirmasi</h3>
                        <div class="number"><?php echo $total_pending; ?></div>
                    </div>
                    <div class="stat-card dijadwalkan">
                        <h3>Dijadwalkan</h3>
                        <div class="number"><?php echo $total_dijadwalkan; ?></div>
                    </div>
                    <div class="stat-card diproses">
                        <h3>Sedang Diproses</h3>
                        <div class="number"><?php echo $total_diproses; ?></div>
                    </div>
                    <div class="stat-card selesai">
                        <h3>Selesai</h3>
                        <div class="number"><?php echo $total_selesai; ?></div>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" class="filter-form">
                        <div class="filter-grid">
                            <div class="form-group">
                                <label for="status">Filter Status</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="all" <?php echo empty($status_filter) || $status_filter === 'all' ? 'selected' : ''; ?>>Semua Status</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="dijadwalkan" <?php echo $status_filter === 'dijadwalkan' ? 'selected' : ''; ?>>Dijadwalkan</option>
                                    <option value="diproses" <?php echo $status_filter === 'diproses' ? 'selected' : ''; ?>>Diproses</option>
                                    <option value="selesai" <?php echo $status_filter === 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                                    <option value="ditolak" <?php echo $status_filter === 'ditolak' ? 'selected' : ''; ?>>Ditolak</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="search">Cari (Nama/Jenis/Alamat)</label>
                                <input type="text" id="search" name="search" class="form-control" 
                                       value="<?php echo htmlspecialchars($search_filter); ?>" 
                                       placeholder="Cari penjemputan...">
                            </div>
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div style="display: flex; gap: 10px; flex-direction: column;">
                                    <button type="submit" class="btn btn-primary">
                                        🔍 Terapkan Filter
                                    </button>
                                    <a href="manage-penjemputan.php" class="btn btn-outline">
                                        🔄 Reset Filter
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Penjemputan List -->
                <div class="penjemputan-list">
                    <?php if (empty($penjemputan)): ?>
                        <div class="empty-state">
                            <h3>📭 Belum ada penjemputan</h3>
                            <p>Tidak ada permintaan penjemputan yang sesuai dengan filter Anda.</p>
                            <a href="manage-penjemputan.php" class="btn btn-primary">
                                Tampilkan Semua
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($penjemputan as $p): 
                            $perkiraan_harga = hitungPerkiraanHarga($p, $jenis_sampah);
                            $harga_per_kg = getHargaPerKg($p['jenis_sampah'], $jenis_sampah);
                        ?>
                        <div class="penjemputan-card">
                            <div class="penjemputan-header">
                                <div class="penjemputan-info">
                                    <h3>🗑️ <?php echo htmlspecialchars($p['jenis_sampah']); ?></h3>
                                    <span class="status-badge status-<?php echo $p['status']; ?>">
                                        <?php 
                                        $status_text = [
                                            'pending' => '⏳ Menunggu Konfirmasi',
                                            'dijadwalkan' => '📅 Dijadwalkan', 
                                            'diproses' => '🚚 Sedang Diproses',
                                            'selesai' => '✅ Selesai',
                                            'ditolak' => '❌ Ditolak'
                                        ];
                                        echo $status_text[$p['status']] ?? $p['status'];
                                        ?>
                                    </span>
                                </div>
                                
                                <div class="action-buttons">
                                    <?php if ($p['status'] === 'pending'): ?>
                                        <button type="button" class="btn btn-success btn-sm" 
                                                onclick="openTerimaModal(<?php echo $p['id']; ?>)">
                                            ✅ Terima
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm" 
                                                onclick="openTolakModal(<?php echo $p['id']; ?>)">
                                            ❌ Tolak
                                        </button>
                                    <?php elseif (in_array($p['status'], ['dijadwalkan', 'diproses'])): ?>
                                        <!-- TOMBOL PREVIEW INFO HARGA -->
                                        <button type="button" class="btn btn-preview btn-sm" 
                                                onclick="openPreviewModal(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['jenis_sampah']); ?>', <?php echo $p['berat'] ?? 0; ?>, <?php echo $harga_per_kg; ?>, <?php echo $perkiraan_harga; ?>, <?php echo $p['saldo_warga']; ?>)">
                                            📊 Preview Harga
                                        </button>
                                        <!-- TOMBOL VERIFIKASI -->
                                        <a href="verifikasi-setoran.php?id=<?php echo $p['id']; ?>" class="btn btn-verify btn-sm">
                                            ⚖️ Verifikasi
                                        </a>
                                        <!-- TOMBOL UPDATE -->
                                        <button type="button" class="btn btn-outline btn-sm" 
                                                onclick="openUpdateModal(<?php echo $p['id']; ?>, '<?php echo $p['status']; ?>', '<?php echo $p['waktu_penjemputan']; ?>', '<?php echo htmlspecialchars($p['keterangan'] ?? ''); ?>')">
                                            ✏️ Update
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="penjemputan-meta">
                                <div class="meta-item">
                                    <span class="meta-label">👤 Warga</span>
                                    <span class="meta-value">
                                        <?php echo htmlspecialchars($p['nama_warga']); ?>
                                        <br><small>📞 <?php echo htmlspecialchars($p['hp_warga']); ?></small>
                                    </span>
                                </div>
                                
                                <div class="meta-item">
                                    <span class="meta-label">📅 Tanggal Pengajuan</span>
                                    <span class="meta-value">
                                        <?php echo date('d/m/Y H:i', strtotime($p['waktu_pemintaan'])); ?>
                                    </span>
                                </div>
                                
                                <?php if ($p['waktu_penjemputan']): ?>
                                <div class="meta-item">
                                    <span class="meta-label">⏰ Jadwal Penjemputan</span>
                                    <span class="meta-value">
                                        <?php echo date('d/m/Y H:i', strtotime($p['waktu_penjemputan'])); ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="meta-item">
                                    <span class="meta-label">📍 Alamat Penjemputan</span>
                                    <span class="meta-value">
                                        <?php echo htmlspecialchars($p['alamat_penjemputan']); ?>
                                        <?php if ($p['keterangan']): ?>
                                            <br><small>📝 <?php echo htmlspecialchars($p['keterangan']); ?></small>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>

                            <!-- INFO BERAT DAN HARGA -->
                            <?php if ($p['berat'] && $p['berat'] > 0): ?>
                            <div class="info-harga">
                                <h4 style="color: var(--primary); margin-bottom: 10px;">💰 Informasi Berat & Harga</h4>
                                <div class="harga-item">
                                    <span class="harga-label">Berat Sampah:</span>
                                    <span class="harga-value"><?php echo number_format($p['berat'], 2); ?> kg</span>
                                </div>
                                <div class="harga-item">
                                    <span class="harga-label">Harga per Kg:</span>
                                    <span class="harga-value">Rp <?php echo number_format($harga_per_kg, 0, ',', '.'); ?></span>
                                </div>
                                <div class="harga-item">
                                    <span class="harga-label">Perkiraan Total:</span>
                                    <span class="harga-value">Rp <?php echo number_format($perkiraan_harga, 0, ',', '.'); ?></span>
                                </div>
                                <div class="harga-item">
                                    <span class="harga-label">Saldo Warga Saat Ini:</span>
                                    <span class="harga-value">Rp <?php echo number_format($p['saldo_warga'], 0, ',', '.'); ?></span>
                                </div>
                                <div class="harga-item">
                                    <span class="harga-label">Saldo Setelah Transaksi:</span>
                                    <span class="harga-value">Rp <?php echo number_format($p['saldo_warga'] + $perkiraan_harga, 0, ',', '.'); ?></span>
                                </div>
                            </div>
                            <?php else: ?>
                            <div style="background: #fff3cd; padding: 10px; border-radius: var(--border-radius); margin-top: 15px; border-left: 4px solid var(--warning);">
                                <span style="color: #856404;">⚠️ Belum ada informasi berat sampah. Silakan verifikasi untuk menimbang.</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <!-- Modal untuk menerima penjemputan -->
    <div id="terimaModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">✅ Terima Penjemputan</h2>
                <button type="button" class="close" onclick="closeModal('terimaModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="terimaForm" method="POST">
                    <input type="hidden" name="id_penjemputan" id="terima_penjemputan_id">
                    <input type="hidden" name="update_status" value="1">
                    <input type="hidden" name="status" value="dijadwalkan">
                    
                    <div class="form-group">
                        <label for="waktu_penjemputan">⏰ Jadwal Penjemputan *</label>
                        <input type="datetime-local" id="waktu_penjemputan" name="waktu_penjemputan" 
                               class="form-control" required min="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="catatan">📝 Catatan untuk Warga (opsional):</label>
                        <textarea id="catatan" name="catatan" class="form-control" rows="3" 
                                  placeholder="Berikan catatan atau informasi tambahan..."></textarea>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn btn-success" style="flex: 1;">
                            ✅ Terima & Jadwalkan
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeModal('terimaModal')">
                            ❌ Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal untuk menolak penjemputan -->
    <div id="tolakModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">❌ Tolak Penjemputan</h2>
                <button type="button" class="close" onclick="closeModal('tolakModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="tolakForm" method="POST">
                    <input type="hidden" name="id_penjemputan" id="tolak_penjemputan_id">
                    <input type="hidden" name="tolak_penjemputan" value="1">
                    
                    <div class="form-group">
                        <label for="alasan_penolakan">📋 Alasan Penolakan *</label>
                        <textarea id="alasan_penolakan" name="alasan_penolakan" class="form-control" 
                                  rows="4" required placeholder="Berikan alasan penolakan..."></textarea>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn btn-danger" style="flex: 1;">
                            ❌ Tolak Penjemputan
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeModal('tolakModal')">
                            ↩️ Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal untuk update status -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="updateModalTitle">✏️ Update Status Penjemputan</h2>
                <button type="button" class="close" onclick="closeModal('updateModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="updateForm" method="POST">
                    <input type="hidden" name="id_penjemputan" id="update_penjemputan_id">
                    <input type="hidden" name="update_status" value="1">
                    <input type="hidden" name="status" id="update_status">
                    
                    <div class="form-group" id="waktuPenjemputanGroup">
                        <label for="update_waktu_penjemputan">⏰ Jadwal Penjemputan</label>
                        <input type="datetime-local" id="update_waktu_penjemputan" name="waktu_penjemputan" 
                               class="form-control" min="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="update_catatan">📝 Catatan (opsional):</label>
                        <textarea id="update_catatan" name="catatan" class="form-control" rows="3" 
                                  placeholder="Berikan catatan atau informasi tambahan..."></textarea>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            ✅ Update Status
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeModal('updateModal')">
                            ❌ Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal untuk preview harga -->
    <div id="previewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">📊 Preview Informasi Harga</h2>
                <button type="button" class="close" onclick="closeModal('previewModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="info-harga">
                    <h4 id="previewJenisSampah" style="color: var(--primary); margin-bottom: 15px;"></h4>
                    <div class="harga-item">
                        <span class="harga-label">Berat Sampah:</span>
                        <span class="harga-value" id="previewBerat">0 kg</span>
                    </div>
                    <div class="harga-item">
                        <span class="harga-label">Harga per Kg:</span>
                        <span class="harga-value" id="previewHargaPerKg">Rp 0</span>
                    </div>
                    <div class="harga-item">
                        <span class="harga-label">Perkiraan Total:</span>
                        <span class="harga-value" id="previewTotal">Rp 0</span>
                    </div>
                    <div class="harga-item">
                        <span class="harga-label">Saldo Warga Saat Ini:</span>
                        <span class="harga-value" id="previewSaldoAwal">Rp 0</span>
                    </div>
                    <div class="harga-item">
                        <span class="harga-label">Saldo Setelah Transaksi:</span>
                        <span class="harga-value" id="previewSaldoAkhir">Rp 0</span>
                    </div>
                </div>
                
                <div style="margin-top: 20px; padding: 15px; background: #d1ecf1; border-radius: var(--border-radius);">
                    <p style="color: #0c5460; margin: 0; font-size: 0.9rem;">
                        💡 <strong>Informasi:</strong> Ini adalah perkiraan harga berdasarkan data yang ada. 
                        Harga final akan ditentukan saat verifikasi dengan berat aktual.
                    </p>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <a href="#" id="previewVerifikasiLink" class="btn btn-success" style="flex: 1;">
                        ⚖️ Lanjutkan ke Verifikasi
                    </a>
                    <button type="button" class="btn btn-outline" onclick="closeModal('previewModal')">
                        ❌ Tutup
                    </button>
                </div>
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
        
        // Open terima modal
        function openTerimaModal(penjemputanId) {
            document.getElementById('terima_penjemputan_id').value = penjemputanId;
            
            const now = new Date();
            const localDateTime = now.toISOString().slice(0, 16);
            document.getElementById('waktu_penjemputan').min = localDateTime;
            document.getElementById('waktu_penjemputan').value = localDateTime;
            
            document.getElementById('catatan').value = '';
            
            openModal('terimaModal');
        }
        
        // Open tolak modal
        function openTolakModal(penjemputanId) {
            document.getElementById('tolak_penjemputan_id').value = penjemputanId;
            document.getElementById('alasan_penolakan').value = '';
            openModal('tolakModal');
        }
        
        // Open update modal
        function openUpdateModal(penjemputanId, status, waktuPenjemputan = '', catatan = '') {
            document.getElementById('update_penjemputan_id').value = penjemputanId;
            document.getElementById('update_status').value = status;
            
            const waktuGroup = document.getElementById('waktuPenjemputanGroup');
            const modalTitle = document.getElementById('updateModalTitle');
            
            if (status === 'dijadwalkan') {
                waktuGroup.style.display = 'block';
                if (waktuPenjemputan) {
                    const dt = new Date(waktuPenjemputan);
                    const localDateTime = dt.toISOString().slice(0, 16);
                    document.getElementById('update_waktu_penjemputan').value = localDateTime;
                } else {
                    const now = new Date();
                    const localDateTime = now.toISOString().slice(0, 16);
                    document.getElementById('update_waktu_penjemputan').value = localDateTime;
                }
                modalTitle.textContent = '✏️ Update Jadwal Penjemputan';
            } else {
                waktuGroup.style.display = 'none';
                if (status === 'diproses') {
                    modalTitle.textContent = '🚚 Mulai Proses Penjemputan';
                } else if (status === 'selesai') {
                    modalTitle.textContent = '✅ Selesaikan Penjemputan';
                } else {
                    modalTitle.textContent = '✏️ Update Status Penjemputan';
                }
            }
            
            document.getElementById('update_catatan').value = catatan || '';
            
            openModal('updateModal');
        }
        
        // Open preview modal
        function openPreviewModal(penjemputanId, jenisSampah, berat, hargaPerKg, totalHarga, saldoAwal) {
            document.getElementById('previewJenisSampah').textContent = 'Jenis Sampah: ' + jenisSampah;
            document.getElementById('previewBerat').textContent = (berat || 0).toFixed(2) + ' kg';
            document.getElementById('previewHargaPerKg').textContent = 'Rp ' + (hargaPerKg || 0).toLocaleString('id-ID');
            document.getElementById('previewTotal').textContent = 'Rp ' + (totalHarga || 0).toLocaleString('id-ID');
            document.getElementById('previewSaldoAwal').textContent = 'Rp ' + (saldoAwal || 0).toLocaleString('id-ID');
            document.getElementById('previewSaldoAkhir').textContent = 'Rp ' + ((saldoAwal || 0) + (totalHarga || 0)).toLocaleString('id-ID');
            
            // Set link verifikasi
            document.getElementById('previewVerifikasiLink').href = 'verifikasi-setoran.php?id=' + penjemputanId;
            
            openModal('previewModal');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    closeModal(modal.id);
                }
            });
        }

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '⏳ Memproses...';
                    }
                });
            });
        });

        // Auto-close alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>