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
$total_penimbangan = 0;
$total_berat = 0;
$total_pendapatan = 0;
$penimbangan = [];

// FILTER
$bulan_filter = $_GET['bulan'] ?? '';
$tahun_filter = $_GET['tahun'] ?? date('Y');

// PROSES EXPORT EXCEL
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    
    try {
        // QUERY UNTUK MENGAMBIL DATA PENIMBANGAN
        $sql = "SELECT 
                    t.*, 
                    p.jenis_sampah,
                    p.alamat_penjemputan,
                    u.nama_lengkap as nama_warga,
                    u.telepon as hp_warga,
                    m.nama_mitra
                FROM penimbangan t
                JOIN penjemputan p ON t.id_penjemputan = p.id
                JOIN users u ON p.id_warga = u.id
                JOIN mitra m ON t.id_mitra = m.id
                WHERE t.id_mitra = ?";
        
        $params = [$mitra_id];
        $filename = "Laporan_Penimbangan_";
        
        if ($export_type === 'bulanan' && $bulan_filter && $tahun_filter) {
            $sql .= " AND MONTH(t.created_at) = ? AND YEAR(t.created_at) = ?";
            $params[] = $bulan_filter;
            $params[] = $tahun_filter;
            $nama_bulan = [
                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
            ];
            $filename .= $nama_bulan[$bulan_filter] . "_" . $tahun_filter;
        } elseif ($export_type === 'tahunan' && $tahun_filter) {
            $sql .= " AND YEAR(t.created_at) = ?";
            $params[] = $tahun_filter;
            $filename .= "Tahun_" . $tahun_filter;
        } else {
            $filename .= "Semua_Data";
        }
        
        $sql .= " ORDER BY t.created_at ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data_export = $stmt->fetchAll();
        
        // HEADER EXCEL
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
        header('Cache-Control: max-age=0');
        
        // BUAT FILE EXCEL
        $output = fopen('php://output', 'w');
        
        // HEADER TABEL
        fwrite($output, "LAPORAN PENIMBANGAN SAMPAH\n\n");
        
        if ($export_type === 'bulanan' && $bulan_filter) {
            fwrite($output, "Periode: " . $nama_bulan[$bulan_filter] . " " . $tahun_filter . "\n");
        } elseif ($export_type === 'tahunan' && $tahun_filter) {
            fwrite($output, "Tahun: " . $tahun_filter . "\n");
        } else {
            fwrite($output, "Periode: Semua Data\n");
        }
        
        fwrite($output, "Mitra: " . ($data_export[0]['nama_mitra'] ?? '-') . "\n\n");
        
        // HEADER KOLOM
        $header = array(
            'No',
            'Tanggal',
            'Nama Warga', 
            'Telepon',
            'Jenis Sampah',
            'Alamat Penjemputan',
            'Berat (kg)',
            'Harga per kg',
            'Total Harga'
        );
        fputcsv($output, $header, "\t");
        
        // DATA
        $no = 1;
        $total_berat_export = 0;
        $total_pendapatan_export = 0;
        
        foreach ($data_export as $row) {
            $data = array(
                $no++,
                date('d/m/Y H:i', strtotime($row['created_at'])),
                $row['nama_warga'],
                $row['hp_warga'],
                $row['jenis_sampah'],
                $row['alamat_penjemputan'],
                number_format($row['berat'], 1),
                'Rp ' . number_format($row['harga_per_kg'], 0, ',', '.'),
                'Rp ' . number_format($row['total_harga'], 0, ',', '.')
            );
            fputcsv($output, $data, "\t");
            
            $total_berat_export += $row['berat'];
            $total_pendapatan_export += $row['total_harga'];
        }
        
        // TOTAL
        fputcsv($output, array('', '', '', '', '', '', '', '', ''), "\t");
        $total_row = array(
            '',
            'TOTAL',
            '',
            '',
            '',
            '',
            number_format($total_berat_export, 1) . ' kg',
            '',
            'Rp ' . number_format($total_pendapatan_export, 0, ',', '.')
        );
        fputcsv($output, $total_row, "\t");
        
        fclose($output);
        exit();
        
    } catch (PDOException $e) {
        error_log("Export Excel Error: " . $e->getMessage());
        $error = "Terjadi kesalahan saat mengekspor data.";
    }
}

try {
    // QUERY UNTUK MENGAMBIL DATA PENIMBANGAN (TAMPILAN NORMAL)
    $sql = "SELECT 
                t.*, 
                p.jenis_sampah,
                p.alamat_penjemputan,
                u.nama_lengkap as nama_warga,
                u.telepon as hp_warga,
                m.nama_mitra
            FROM penimbangan t
            JOIN penjemputan p ON t.id_penjemputan = p.id
            JOIN users u ON p.id_warga = u.id
            JOIN mitra m ON t.id_mitra = m.id
            WHERE t.id_mitra = ?";
    
    $params = [$mitra_id];
    
    if ($bulan_filter && $tahun_filter) {
        $sql .= " AND MONTH(t.created_at) = ? AND YEAR(t.created_at) = ?";
        $params[] = $bulan_filter;
        $params[] = $tahun_filter;
    } elseif ($tahun_filter) {
        $sql .= " AND YEAR(t.created_at) = ?";
        $params[] = $tahun_filter;
    }
    
    $sql .= " ORDER BY t.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $penimbangan = $stmt->fetchAll();

    // HITUNG TOTAL STATISTIK
    $total_penimbangan = count($penimbangan);
    
    foreach ($penimbangan as $t) {
        $total_berat += $t['berat'];
        $total_pendapatan += $t['total_harga'];
    }

} catch (PDOException $e) {
    error_log("History Penimbangan Error: " . $e->getMessage());
    $error = "Terjadi kesalahan saat mengambil data penimbangan.";
}

// HANDLE SUCCESS MESSAGE
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// AMBIL TAHUN UNTUK FILTER
$sql_tahun = "SELECT DISTINCT YEAR(created_at) as tahun FROM penimbangan WHERE id_mitra = ? ORDER BY tahun DESC";
$stmt = $pdo->prepare($sql_tahun);
$stmt->execute([$mitra_id]);
$tahun_list = $stmt->fetchAll(PDO::FETCH_COLUMN);

// JIKA TIDAK ADA DATA, GUNAKAN TAHUN SEKARANG
if (empty($tahun_list)) {
    $tahun_list = [date('Y')];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Penimbangan - Bank Sampah</title>
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
        
        /* Export Section */
        .export-section {
            background: var(--white);
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 25px;
        }
        
        .export-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-excel {
            background: #217346;
            color: white;
        }
        
        .btn-excel:hover {
            background: #1a5c38;
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
        
        .stat-card .subtext {
            font-size: 0.8rem;
            color: var(--gray);
            margin-top: 5px;
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
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
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
            
            .filter-grid, .export-grid {
                grid-template-columns: 1fr;
            }
            
            .export-buttons {
                flex-direction: column;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            .data-table {
                min-width: 800px;
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
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <span class="logo-icon">♻️</span>
                    <h1>Bank Sampah - Riwayat Penimbangan</h1>
                </div>
                <nav>
                    <ul>
                        <li><a href="dashboard-mitra.php">Dashboard</a></li>
                        <li><a href="manage-penjemputan.php">Penjemputan</a></li>
                        <li><a href="manage-jadwal.php">Jadwal</a></li>
                        <li><a href="history-penimbangan.php" class="active">Riwayat</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <section class="history-penimbangan">
            <div class="container">
                <div class="page-header">
                    <div class="page-title">
                        <h1>⚖️ Riwayat Penimbangan Sampah</h1>
                        <p>Lihat semua riwayat penimbangan sampah yang telah Anda lakukan</p>
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

                <!-- Export Section -->
                <div class="export-section">
                    <h3 style="margin-bottom: 15px; color: var(--primary);">📊 Export Laporan</h3>
                    <div class="export-grid">
                        <div class="form-group">
                            <label>Export Berdasarkan Periode:</label>
                            <div class="export-buttons">
                                <?php if ($bulan_filter && $tahun_filter): ?>
                                    <a href="?export=bulanan&bulan=<?php echo $bulan_filter; ?>&tahun=<?php echo $tahun_filter; ?>" class="btn btn-excel">
                                        📄 Export Bulan Ini
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($tahun_filter): ?>
                                    <a href="?export=tahunan&tahun=<?php echo $tahun_filter; ?>" class="btn btn-excel">
                                        📅 Export Tahun Ini
                                    </a>
                                <?php endif; ?>
                                
                                <a href="?export=semua" class="btn btn-excel">
                                    📋 Export Semua Data
                                </a>
                            </div>
                        </div>
                        <div class="form-group">
                            <small style="color: var(--gray);">
                                💡 Pilih filter terlebih dahulu untuk export data spesifik. 
                                File akan didownload dalam format Excel.
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>📊 Total Penimbangan</h3>
                        <div class="number"><?php echo $total_penimbangan; ?></div>
                        <div class="subtext">Kali penimbangan</div>
                    </div>
                    <div class="stat-card">
                        <h3>⚖️ Total Berat</h3>
                        <div class="number"><?php echo number_format($total_berat, 1); ?></div>
                        <div class="subtext">Kilogram</div>
                    </div>
                    <div class="stat-card">
                        <h3>💰 Total Pendapatan</h3>
                        <div class="number">Rp <?php echo number_format($total_pendapatan, 0, ',', '.'); ?></div>
                        <div class="subtext">Total penghasilan</div>
                    </div>
                    <div class="stat-card">
                        <h3>📈 Rata-rata</h3>
                        <div class="number">Rp <?php echo $total_penimbangan > 0 ? number_format($total_pendapatan / $total_penimbangan, 0, ',', '.') : 0; ?></div>
                        <div class="subtext">Per penimbangan</div>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" class="filter-form">
                        <div class="filter-grid">
                            <div class="form-group">
                                <label for="bulan">📅 Filter Bulan</label>
                                <select id="bulan" name="bulan" class="form-control">
                                    <option value="">Semua Bulan</option>
                                    <option value="1" <?php echo $bulan_filter == '1' ? 'selected' : ''; ?>>Januari</option>
                                    <option value="2" <?php echo $bulan_filter == '2' ? 'selected' : ''; ?>>Februari</option>
                                    <option value="3" <?php echo $bulan_filter == '3' ? 'selected' : ''; ?>>Maret</option>
                                    <option value="4" <?php echo $bulan_filter == '4' ? 'selected' : ''; ?>>April</option>
                                    <option value="5" <?php echo $bulan_filter == '5' ? 'selected' : ''; ?>>Mei</option>
                                    <option value="6" <?php echo $bulan_filter == '6' ? 'selected' : ''; ?>>Juni</option>
                                    <option value="7" <?php echo $bulan_filter == '7' ? 'selected' : ''; ?>>Juli</option>
                                    <option value="8" <?php echo $bulan_filter == '8' ? 'selected' : ''; ?>>Agustus</option>
                                    <option value="9" <?php echo $bulan_filter == '9' ? 'selected' : ''; ?>>September</option>
                                    <option value="10" <?php echo $bulan_filter == '10' ? 'selected' : ''; ?>>Oktober</option>
                                    <option value="11" <?php echo $bulan_filter == '11' ? 'selected' : ''; ?>>November</option>
                                    <option value="12" <?php echo $bulan_filter == '12' ? 'selected' : ''; ?>>Desember</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="tahun">📅 Filter Tahun</label>
                                <select id="tahun" name="tahun" class="form-control">
                                    <?php foreach ($tahun_list as $tahun): ?>
                                        <option value="<?php echo $tahun; ?>" <?php echo $tahun_filter == $tahun ? 'selected' : ''; ?>>
                                            <?php echo $tahun; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div style="display: flex; gap: 10px; flex-direction: column;">
                                    <button type="submit" class="btn btn-primary">
                                        🔍 Terapkan Filter
                                    </button>
                                    <a href="history-penimbangan.php" class="btn btn-outline">
                                        🔄 Reset Filter
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Penimbangan Table -->
                <div class="table-container">
                    <?php if (empty($penimbangan)): ?>
                        <div class="empty-state">
                            <h3>📭 Belum ada riwayat penimbangan</h3>
                            <p>Tidak ada data penimbangan yang ditemukan untuk periode yang dipilih.</p>
                            <?php if ($bulan_filter || $tahun_filter != date('Y')): ?>
                                <a href="history-penimbangan.php" class="btn btn-primary" style="margin-top: 15px;">
                                    Tampilkan Semua Data
                                </a>
                            <?php else: ?>
                                <p style="margin-top: 15px;">Mulai lakukan penimbangan untuk melihat riwayat di sini.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>📅 Tanggal</th>
                                    <th>👤 Warga</th>
                                    <th>🗑️ Jenis Sampah</th>
                                    <th class="text-right">⚖️ Berat (kg)</th>
                                    <th class="text-right">💰 Harga/kg</th>
                                    <th class="text-right">💵 Total</th>
                                    <th>📍 Lokasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($penimbangan as $t): ?>
                                <tr>
                                    <td>
                                        <?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($t['nama_warga']); ?></strong>
                                        <?php if ($t['hp_warga']): ?>
                                            <br><small style="color: var(--gray);">📞 <?php echo htmlspecialchars($t['hp_warga']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($t['jenis_sampah']); ?></td>
                                    <td class="text-right"><?php echo number_format($t['berat'], 1); ?></td>
                                    <td class="text-right">Rp <?php echo number_format($t['harga_per_kg'], 0, ',', '.'); ?></td>
                                    <td class="text-right" style="font-weight: 600; color: var(--success);">
                                        Rp <?php echo number_format($t['total_harga'], 0, ',', '.'); ?>
                                    </td>
                                    <td>
                                        <div style="max-width: 200px;">
                                            <?php echo htmlspecialchars($t['alamat_penjemputan']); ?>
                                            <?php if (!empty($t['keterangan'])): ?>
                                                <br><small style="color: var(--gray);">📝 <?php echo htmlspecialchars($t['keterangan']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background-color: #f8f9fa; font-weight: 600;">
                                    <td colspan="3" class="text-right">Total:</td>
                                    <td class="text-right"><?php echo number_format($total_berat, 1); ?> kg</td>
                                    <td class="text-right">-</td>
                                    <td class="text-right" style="color: var(--success);">
                                        Rp <?php echo number_format($total_pendapatan, 0, ',', '.'); ?>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </section>
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
    </script>
</body>
</html>