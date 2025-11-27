<?php
include 'config.php';

// CEK LOGIN DAN ROLE ADMIN
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

$success = '';
$error = '';

// HANDLE APPROVE PENARIKAN
if (isset($_POST['approve'])) {
    $penarikan_id = (int)$_POST['penarikan_id'];
    
    // Mulai transaction untuk keamanan data
    $pdo->beginTransaction();
    
    try {
        // Dapatkan data penarikan
        $stmt = $pdo->prepare("SELECT * FROM penarikan WHERE id = ? AND status = 'pending' FOR UPDATE");
        $stmt->execute([$penarikan_id]);
        $penarikan = $stmt->fetch();
        
        if (!$penarikan) {
            throw new Exception("Data penarikan tidak ditemukan atau sudah diproses!");
        }
        
        // CEK SALDO USER (TAPI JANGAN KURANGI - biarkan trigger yang handle)
        $stmt = $pdo->prepare("SELECT saldo FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$penarikan['user_id']]);
        $user = $stmt->fetch();
        
        if ($user['saldo'] < $penarikan['jumlah']) {
            throw new Exception("Saldo user tidak mencukupi untuk penarikan!");
        }
        
        // Update status penarikan menjadi approved - TRIGGER akan otomatis mengurangi saldo
        $stmt = $pdo->prepare("
            UPDATE penarikan 
            SET status = 'approved', 
                admin_id = ?, 
                tanggal_verifikasi = NOW(),
                keterangan = COALESCE(?, keterangan)
            WHERE id = ?
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $_POST['keterangan'] ?? null,
            $penarikan_id
        ]);
        
        // Commit transaction
        $pdo->commit();
        $success = "Penarikan berhasil disetujui! Saldo user akan dikurangi otomatis oleh sistem.";
        
    } catch (Exception $e) {
        // Rollback transaction jika ada error
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// HANDLE REJECT PENARIKAN
if (isset($_POST['reject'])) {
    $penarikan_id = (int)$_POST['penarikan_id'];
    $alasan_penolakan = sanitize($_POST['alasan_penolakan'] ?? '');
    
    $stmt = $pdo->prepare("
        UPDATE penarikan 
        SET status = 'rejected', 
            admin_id = ?, 
            tanggal_verifikasi = NOW(),
            keterangan = ?
        WHERE id = ?
    ");
    
    if ($stmt->execute([$_SESSION['user_id'], $alasan_penolakan, $penarikan_id])) {
        $success = "Penarikan berhasil ditolak!";
    } else {
        $error = "Gagal menolak penarikan!";
    }
}

// HANDLE BATAL APPROVAL (Hanya untuk testing/darurat)
if (isset($_POST['batal_approve']) && $_SESSION['username'] === 'admin') {
    $penarikan_id = (int)$_POST['penarikan_id'];
    
    $pdo->beginTransaction();
    try {
        // Dapatkan data penarikan yang sudah approved
        $stmt = $pdo->prepare("SELECT * FROM penarikan WHERE id = ? AND status = 'approved'");
        $stmt->execute([$penarikan_id]);
        $penarikan = $stmt->fetch();
        
        if ($penarikan) {
            // Kembalikan saldo user (karena trigger sudah mengurangi)
            $stmt = $pdo->prepare("UPDATE users SET saldo = saldo + ? WHERE id = ?");
            $stmt->execute([$penarikan['jumlah'], $penarikan['user_id']]);
            
            // Reset status penarikan ke pending
            $stmt = $pdo->prepare("
                UPDATE penarikan 
                SET status = 'pending', 
                    admin_id = NULL, 
                    tanggal_verifikasi = NULL 
                WHERE id = ?
            ");
            $stmt->execute([$penarikan_id]);
        }
        
        $pdo->commit();
        $success = "Approval penarikan berhasil dibatalkan dan saldo dikembalikan!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Gagal membatalkan approval: " . $e->getMessage();
    }
}

// HANDLE EKSPOR EXCEL
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="laporan_penarikan_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Data untuk ekspor
    $export_start_date = $_GET['export_start_date'] ?? date('Y-m-01');
    $export_end_date = $_GET['export_end_date'] ?? date('Y-m-t');
    $export_status = $_GET['export_status'] ?? '';
    
    // Build query untuk ekspor
    $export_where = ["DATE(p.tanggal_penarikan) BETWEEN ? AND ?"];
    $export_params = [$export_start_date, $export_end_date];
    
    if (!empty($export_status)) {
        $export_where[] = "p.status = ?";
        $export_params[] = $export_status;
    }
    
    $export_where_sql = implode(' AND ', $export_where);
    
    // ✅ PERBAIKI BAGIAN INI SAJA - Ganti query yang bermasalah
    $stmt = $pdo->prepare("
        SELECT 
            p.*, 
            u.nama_lengkap, 
            u.username,
            admin.nama_lengkap as admin_verifikasi
        FROM penarikan p 
        JOIN users u ON p.user_id = u.id 
        LEFT JOIN users admin ON p.admin_id = admin.id 
        WHERE $export_where_sql
        ORDER BY p.tanggal_penarikan DESC
    ");
    $stmt->execute($export_params);
    $export_data = $stmt->fetchAll();
    
    // Hitung total untuk laporan
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            COALESCE(SUM(CASE WHEN status = 'approved' THEN jumlah END), 0) as total_approved,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN jumlah END), 0) as total_pending,
            COALESCE(SUM(CASE WHEN status = 'rejected' THEN jumlah END), 0) as total_rejected
        FROM penarikan 
        WHERE $export_where_sql
    ");
    $stmt->execute($export_params);
    $export_totals = $stmt->fetch();
    
    
    // Hitung total untuk laporan
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            COALESCE(SUM(CASE WHEN status = 'approved' THEN jumlah END), 0) as total_approved,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN jumlah END), 0) as total_pending,
            COALESCE(SUM(CASE WHEN status = 'rejected' THEN jumlah END), 0) as total_rejected
        FROM penarikan 
        WHERE $export_where_sql
    ");
    $stmt->execute($export_params);
    $export_totals = $stmt->fetch();
    
    echo "<table border='1'>";
    echo "<tr><td colspan='8' style='background: #4CAF50; color: white; text-align: center; font-size: 16px; font-weight: bold;'>LAPORAN PENARIKAN SALDO</td></tr>";
    echo "<tr><td colspan='8'>Periode: " . date('d/m/Y', strtotime($export_start_date)) . " - " . date('d/m/Y', strtotime($export_end_date)) . "</td></tr>";
    echo "<tr><td colspan='8'>Tanggal Ekspor: " . date('d/m/Y H:i') . "</td></tr>";
    echo "<tr><td colspan='8'></td></tr>";
    
    // Header tabel
    echo "<tr style='background: #f2f2f2;'>";
    echo "<th>No</th>";
    echo "<th>Tanggal</th>";
    echo "<th>Nasabah</th>";
    echo "<th>Jumlah Penarikan</th>";
    echo "<th>Status</th>";
    echo "<th>Keterangan</th>";
    echo "<th>Admin Verifikasi</th>";
    echo "<th>Tanggal Verifikasi</th>";
    echo "</tr>";
    
    // Data
    $no = 1;
    foreach ($export_data as $row) {
        $status_text = [
            'pending' => 'MENUNGGU',
            'approved' => 'DISETUJUI', 
            'rejected' => 'DITOLAK'
        ];
        
        echo "<tr>";
        echo "<td>" . $no++ . "</td>";
        echo "<td>" . date('d/m/Y H:i', strtotime($row['tanggal_penarikan'])) . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_lengkap']) . " (@".htmlspecialchars($row['username']).")</td>";
        echo "<td>" . number_format($row['jumlah'], 0, ',', '.') . "</td>";
        echo "<td>" . $status_text[$row['status']] . "</td>";
        echo "<td>" . ($row['keterangan'] ?: '-') . "</td>";
        echo "<td>" . ($row['admin_verifikasi'] ?: '-') . "</td>";
        echo "<td>" . ($row['tanggal_verifikasi'] ? date('d/m/Y H:i', strtotime($row['tanggal_verifikasi'])) : '-') . "</td>";
        echo "</tr>";
    }
    
    // Total
    echo "<tr style='background: #e8f5e8; font-weight: bold;'>";
    echo "<td colspan='3'>TOTAL</td>";
    echo "<td colspan='5'>";
    echo "Total Data: " . $export_totals['total'] . " | ";
    echo "Disetujui: " . number_format($export_totals['total_approved'], 0, ',', '.') . " | ";
    echo "Pending: " . number_format($export_totals['total_pending'], 0, ',', '.') . " | ";
    echo "Ditolak: " . number_format($export_totals['total_rejected'], 0, ',', '.');
    echo "</td>";
    echo "</tr>";
    
    echo "</table>";
    exit;
}

// FILTER DATA
$status_filter = $_GET['status'] ?? '';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$search = $_GET['search'] ?? '';

// BUILD QUERY DENGAN FILTER
$where_conditions = ["1=1"];
$params = [];

if (!empty($status_filter)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

if (!empty($start_date)) {
    $where_conditions[] = "DATE(p.tanggal_penarikan) >= ?";
    $params[] = $start_date;
}

if (!empty($end_date)) {
    $where_conditions[] = "DATE(p.tanggal_penarikan) <= ?";
    $params[] = $end_date;
}

if (!empty($search)) {
    $where_conditions[] = "(u.nama_lengkap LIKE ? OR u.username LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_sql = implode(' AND ', $where_conditions);

// AMBIL DATA PENARIKAN DENGAN FILTER
$stmt = $pdo->prepare("
    SELECT 
        p.*, 
        u.nama_lengkap, 
        u.username, 
        u.saldo as saldo_sekarang,
        admin.nama_lengkap as admin_verifikasi
    FROM penarikan p 
    JOIN users u ON p.user_id = u.id 
    LEFT JOIN users admin ON p.admin_id = admin.id 
    WHERE $where_sql
    ORDER BY 
        CASE 
            WHEN p.status = 'pending' THEN 1
            WHEN p.status = 'approved' THEN 2
            ELSE 3 
        END,
        p.tanggal_penarikan DESC
");

$stmt->execute($params);
$penarikan = $stmt->fetchAll();

// STATISTIK PENARIKAN
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_penarikan,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
        COALESCE(SUM(CASE WHEN status = 'approved' THEN jumlah END), 0) as total_disetujui,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN jumlah END), 0) as total_pending,
        COALESCE(SUM(CASE WHEN status = 'rejected' THEN jumlah END), 0) as total_ditolak
    FROM penarikan
");
$stats = $stmt->fetch();

// STATISTIK BULAN INI
$current_month = date('Y-m');
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_bulan_ini,
        COALESCE(SUM(jumlah), 0) as total_jumlah_bulan_ini
    FROM penarikan 
    WHERE DATE(tanggal_penarikan) BETWEEN ? AND LAST_DAY(?)
    AND status = 'approved'
");
$stmt->execute([$current_month . '-01', $current_month]);
$stats_bulan_ini = $stmt->fetch();

// LAPORAN KEUANGAN - DATA UNTUK CHART
$stmt = $pdo->prepare("
    SELECT 
        DATE(tanggal_penarikan) as tanggal,
        COUNT(*) as jumlah_transaksi,
        COALESCE(SUM(jumlah), 0) as total_nominal
    FROM penarikan 
    WHERE status = 'approved'
    AND DATE(tanggal_penarikan) BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE()
    GROUP BY DATE(tanggal_penarikan)
    ORDER BY tanggal DESC
    LIMIT 30
");
$stmt->execute();
$chart_data = $stmt->fetchAll();

// DATA UNTUK LAPORAN BULANAN
$stmt = $pdo->prepare("
    SELECT 
        YEAR(tanggal_penarikan) as tahun,
        MONTH(tanggal_penarikan) as bulan,
        COUNT(*) as jumlah_transaksi,
        COALESCE(SUM(jumlah), 0) as total_nominal
    FROM penarikan 
    WHERE status = 'approved'
    GROUP BY YEAR(tanggal_penarikan), MONTH(tanggal_penarikan)
    ORDER BY tahun DESC, bulan DESC
    LIMIT 12
");
$stmt->execute();
$bulanan_data = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Penarikan - Bank Sampah</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            max-width: 1400px;
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
            gap: 15px;
        }
        
        nav a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            padding: 8px 15px;
            border-radius: var(--border-radius);
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        nav a:hover, nav a.active {
            background-color: var(--primary);
            color: var(--white);
        }
        
        /* Main Content */
        main {
            padding: 30px 0;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .dashboard-title h1 {
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .dashboard-title p {
            color: var(--gray);
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
        }
        
        .stat-card.warning { border-left-color: var(--warning); }
        .stat-card.success { border-left-color: var(--success); }
        .stat-card.danger { border-left-color: var(--danger); }
        .stat-card.info { border-left-color: var(--info); }
        
        .stat-card h3 {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 10px;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        .stat-card small {
            color: var(--gray);
            font-size: 0.8rem;
        }
        
        /* Content Section */
        .content-section {
            background: var(--white);
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 25px;
        }
        
        .content-section h2 {
            color: var(--primary);
            margin-bottom: 20px;
            font-size: 1.3rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        /* Filter Section */
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }
        
        .form-row {
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
        
        .btn-danger {
            background: var(--danger);
            color: var(--white);
        }
        
        .btn-warning {
            background: var(--warning);
            color: var(--dark);
        }
        
        .btn-info {
            background: var(--info);
            color: var(--white);
        }
        
        .btn-export {
            background: #28a745;
            color: white;
            padding: 8px 15px;
            font-size: 0.8rem;
        }
        
        /* Table Styles */
        .table-container {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .data-table th, .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .data-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--gray);
            position: sticky;
            top: 0;
        }
        
        .data-table tr:hover {
            background-color: #f8f9fa;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
            color: white;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .action-form {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .btn-action {
            padding: 6px 12px;
            font-size: 0.8rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        /* Chart Container */
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-top: 20px;
            height: 300px;
        }
        
        /* Laporan Section */
        .laporan-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .laporan-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary);
        }
        
        /* Alert Styles */
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            font-weight: 500;
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
            
            nav ul {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .laporan-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
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
                    <h1>Bank Sampah - Kelola Penarikan</h1>
                </div>
                <nav>
                    <ul>
                        <li><a href="dashboard-admin.php">Dashboard</a></li>
                        <li><a href="manage-users.php">Kelola Warga</a></li>
                        <li><a href="manage-mitra.php">Kelola Mitra</a></li>
                        <li><a href="manage-transactions.php">Transaksi</a></li>
                        <li><a href="manage-jenis-sampah.php">Jenis Sampah</a></li>
                        <li><a href="manage-withdrawals.php" class="active">Penarikan</a></li>
                        <li><a href="?logout=1">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <section class="dashboard">
            <div class="container">
                <!-- NOTIFICATION -->
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        ✅ <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        ❌ <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- HEADER -->
                <div class="dashboard-header">
                    <div class="dashboard-title">
                        <h1>Kelola Permintaan Penarikan</h1>
                        <p>Verifikasi dan kelola permintaan penarikan saldo dari nasabah</p>
                    </div>
                    <div>
                        <a href="?export=excel&export_start_date=<?php echo $start_date; ?>&export_end_date=<?php echo $end_date; ?>&export_status=<?php echo $status_filter; ?>" 
                           class="btn btn-export">
                            📊 Ekspor Excel
                        </a>
                    </div>
                </div>

                <!-- STATISTIK -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Total Penarikan</h3>
                        <div class="stat-number"><?php echo $stats['total_penarikan']; ?></div>
                    </div>
                    <div class="stat-card warning">
                        <h3>Menunggu</h3>
                        <div class="stat-number"><?php echo $stats['pending']; ?></div>
                        <small><?php echo formatRupiah($stats['total_pending']); ?></small>
                    </div>
                    <div class="stat-card success">
                        <h3>Disetujui</h3>
                        <div class="stat-number"><?php echo $stats['approved']; ?></div>
                        <small><?php echo formatRupiah($stats['total_disetujui']); ?></small>
                    </div>
                    <div class="stat-card info">
                        <h3>Bulan Ini</h3>
                        <div class="stat-number"><?php echo $stats_bulan_ini['total_bulan_ini']; ?></div>
                        <small><?php echo formatRupiah($stats_bulan_ini['total_jumlah_bulan_ini']); ?></small>
                    </div>
                </div>

                <!-- FILTER SECTION -->
                <div class="content-section">
                    <h2>Filter Data</h2>
                    <div class="filter-section">
                        <form method="GET" class="filter-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Status:</label>
                                    <select name="status" class="form-control">
                                        <option value="">Semua Status</option>
                                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Menunggu</option>
                                        <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Disetujui</option>
                                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Ditolak</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Dari Tanggal:</label>
                                    <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>Sampai Tanggal:</label>
                                    <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>Pencarian User:</label>
                                    <input type="text" name="search" placeholder="Nama atau username..." 
                                           value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                                </div>
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">🔍 Filter</button>
                                    <a href="manage-withdrawals.php" class="btn btn-warning">🔄 Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- LAPORAN KEUANGAN -->
                <div class="content-section">
                    <h2>
                        Laporan Keuangan Penarikan
                        <a href="?export=excel&export_start_date=<?php echo date('Y-m-01'); ?>&export_end_date=<?php echo date('Y-m-t'); ?>" 
                           class="btn btn-export">
                            📥 Ekspor Laporan Bulan Ini
                        </a>
                    </h2>
                    
                    <div class="laporan-grid">
                        <div class="laporan-card">
                            <h3>📈 Statistik 30 Hari Terakhir</h3>
                            <div class="chart-container">
                                <canvas id="lineChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="laporan-card">
                            <h3>📊 Ringkasan Bulanan</h3>
                            <div style="max-height: 300px; overflow-y: auto;">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Bulan</th>
                                            <th>Jumlah Transaksi</th>
                                            <th>Total Nominal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bulanan_data as $data): ?>
                                        <tr>
                                            <td><?php echo date('F Y', mktime(0, 0, 0, $data['bulan'], 1, $data['tahun'])); ?></td>
                                            <td><?php echo $data['jumlah_transaksi']; ?></td>
                                            <td style="font-weight: bold; color: var(--danger);">
                                                ➖ <?php echo formatRupiah($data['total_nominal']); ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DAFTAR PENARIKAN -->
                <div class="content-section">
                    <h2>Daftar Permintaan Penarikan (<?php echo count($penarikan); ?> data)</h2>
                    
                    <?php if (empty($penarikan)): ?>
                        <div class="empty-state">
                            <p>❌ Tidak ada data penarikan yang sesuai dengan filter</p>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Nasabah</th>
                                        <th>Jumlah Penarikan</th>
                                        <th>Saldo Saat Ini</th>
                                        <th>Status</th>
                                        <th>Keterangan</th>
                                        <th>Admin</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($penarikan as $p): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo date('d/m/Y', strtotime($p['tanggal_penarikan'])); ?></strong>
                                                <br><small><?php echo date('H:i', strtotime($p['tanggal_penarikan'])); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($p['nama_lengkap']); ?></strong>
                                                <br><small>@<?php echo htmlspecialchars($p['username']); ?></small>
                                            </div>
                                        </td>
                                        <td style="font-weight: bold; color: var(--danger);">
                                            ➖ <?php echo formatRupiah($p['jumlah']); ?>
                                        </td>
                                        <td style="font-weight: bold; color: var(--primary);">
                                            <?php echo formatRupiah($p['saldo_sekarang']); ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_config = [
                                                'pending' => ['color' => '#ffc107', 'text' => 'Menunggu', 'icon' => '⏳'],
                                                'approved' => ['color' => '#28a745', 'text' => 'Disetujui', 'icon' => '✅'],
                                                'rejected' => ['color' => '#dc3545', 'text' => 'Ditolak', 'icon' => '❌']
                                            ];
                                            $status = $status_config[$p['status']];
                                            ?>
                                            <span class="status-badge" style="background: <?php echo $status['color']; ?>;">
                                                <?php echo $status['icon']; ?> <?php echo $status['text']; ?>
                                            </span>
                                            <?php if ($p['tanggal_verifikasi']): ?>
                                                <br><small><?php echo date('d/m H:i', strtotime($p['tanggal_verifikasi'])); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $p['keterangan'] ? nl2br(htmlspecialchars($p['keterangan'])) : '-'; ?>
                                        </td>
                                        <td>
                                            <?php echo $p['admin_verifikasi'] ?: '-'; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($p['status'] === 'pending'): ?>
                                                    <!-- APPROVE FORM -->
                                                    <form method="POST" class="action-form">
                                                        <input type="hidden" name="penarikan_id" value="<?php echo $p['id']; ?>">
                                                        <input type="text" name="keterangan" placeholder="Keterangan (opsional)" 
                                                               style="font-size: 11px; padding: 4px; margin-bottom: 5px; width: 100%;">
                                                        <button type="submit" name="approve" class="btn-action btn-success"
                                                                onclick="return confirm('Setujui penarikan <?php echo formatRupiah($p['jumlah']); ?> dari <?php echo htmlspecialchars($p['nama_lengkap']); ?>?')">
                                                            ✅ Approve
                                                        </button>
                                                    </form>
                                                    
                                                    <!-- REJECT FORM -->
                                                    <form method="POST" class="action-form">
                                                        <input type="hidden" name="penarikan_id" value="<?php echo $p['id']; ?>">
                                                        <input type="text" name="alasan_penolakan" placeholder="Alasan penolakan" 
                                                               style="font-size: 11px; padding: 4px; margin-bottom: 5px; width: 100%;" required>
                                                        <button type="submit" name="reject" class="btn-action btn-danger"
                                                                onclick="return confirm('Tolak penarikan <?php echo formatRupiah($p['jumlah']); ?> dari <?php echo htmlspecialchars($p['nama_lengkap']); ?>?')">
                                                            ❌ Tolak
                                                        </button>
                                                    </form>
                                                    
                                                <?php elseif ($p['status'] === 'approved' && $_SESSION['username'] === 'admin'): ?>
                                                    <!-- BATAL APPROVAL (Hanya untuk super admin) -->
                                                    <form method="POST" class="action-form">
                                                        <input type="hidden" name="penarikan_id" value="<?php echo $p['id']; ?>">
                                                        <button type="submit" name="batal_approve" class="btn-action btn-warning"
                                                                onclick="return confirm('⚠️ BATAL APPROVAL? Saldo akan dikembalikan ke user! Tindakan ini hanya untuk emergency.')">
                                                            🔄 Batal
                                                        </button>
                                                    </form>
                                                    
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 Bank Sampah. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // GRAFIK LINE CHART UNTUK 30 HARI TERAKHIR
        const lineCtx = document.getElementById('lineChart').getContext('2d');
        const lineChart = new Chart(lineCtx, {
            type: 'line',
            data: {
                labels: [<?php echo implode(',', array_map(function($item) { 
                    return "'" . date('d/m', strtotime($item['tanggal'])) . "'"; 
                }, array_reverse($chart_data))); ?>],
                datasets: [{
                    label: 'Total Penarikan (Rp)',
                    data: [<?php echo implode(',', array_map(function($item) { 
                        return $item['total_nominal']; 
                    }, array_reverse($chart_data))); ?>],
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Trend Penarikan 30 Hari Terakhir'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });

        // AUTO HIDE ALERTS
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>