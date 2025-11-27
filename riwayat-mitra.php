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
$total_pendapatan_warga = 0;
$total_penghasilan_mitra = 0;
$penimbangan = [];

// FILTER
$bulan_filter = $_GET['bulan'] ?? '';
$tahun_filter = $_GET['tahun'] ?? date('Y');

// PROSES EXPORT EXCEL - DISIMPAN UNTUK FITUR EKSPOR
if (isset($_GET['export'])) {
    // ... kode export tetap sama ...
}

try {
    // QUERY SEDERHANA UNTUK MENGAMBIL DATA PENIMBANGAN - DIPERBAIKI
    $sql = "SELECT 
                t.*, 
                p.jenis_sampah,
                p.alamat_penjemputan,
                u.nama_lengkap as nama_warga,
                u.telepon as hp_warga,
                m.nama_mitra
            FROM penimbangan t
            LEFT JOIN penjemputan p ON t.id_penjemputan = p.id
            LEFT JOIN users u ON p.id_warga = u.id
            LEFT JOIN mitra m ON t.id_mitra = m.id
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

    // DEBUG: Tampilkan error jika ada
    if ($stmt->errorCode() != '00000') {
        $error_info = $stmt->errorInfo();
        error_log("Database Error: " . $error_info[2]);
    }

    // HITUNG TOTAL STATISTIK
    $total_penimbangan = count($penimbangan);
    
    foreach ($penimbangan as $t) {
        $total_berat += floatval($t['berat']);
        $total_pendapatan_warga += floatval($t['total_harga']);
        
        // HITUNG PENGHASILAN MITRA (SIMPLE VERSION)
        // Asumsi margin 20% jika tidak ada data harga_jual
        $harga_jual_per_kg = floatval($t['harga_per_kg']) * 1.2;
        $total_penjualan = floatval($t['berat']) * $harga_jual_per_kg;
        $pendapatan_mitra = $total_penjualan - floatval($t['total_harga']);
        $total_penghasilan_mitra += $pendapatan_mitra;
    }

} catch (PDOException $e) {
    error_log("History Penimbangan Error: " . $e->getMessage());
    $error = "Terjadi kesalahan saat mengambil data penimbangan: " . $e->getMessage();
}

// HANDLE SUCCESS MESSAGE
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// AMBIL TAHUN UNTUK FILTER - DIPERBAIKI
try {
    $sql_tahun = "SELECT DISTINCT YEAR(created_at) as tahun FROM penimbangan WHERE id_mitra = ? ORDER BY tahun DESC";
    $stmt = $pdo->prepare($sql_tahun);
    $stmt->execute([$mitra_id]);
    $tahun_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Error getting tahun list: " . $e->getMessage());
    $tahun_list = [date('Y')];
}

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
            background: var(--white);
            color: var(--primary);
            padding: 10px 20px;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            box-shadow: var(--shadow);
            transition: all 0.3s;
            border: 2px solid var(--primary);
        }
        
        .back-button:hover {
            background: var(--primary);
            color: var(--white);
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
        
        .subtext {
            font-size: 0.8rem;
            color: var(--gray);
            margin-top: 5px;
        }
        
        /* Export Section */
        .export-section {
            background: var(--white);
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        
        .export-grid {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 20px;
            align-items: end;
        }
        
        @media (max-width: 768px) {
            .export-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        /* Filter Section */
        .filter-section {
            background: var(--white);
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        
        .filter-form {
            width: 100%;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-group label {
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-control {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.2);
        }
        
        /* Button Styles */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: var(--primary);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-1px);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }
        
        .btn-outline:hover {
            background: var(--primary);
            color: var(--white);
        }
        
        .btn-excel {
            background: #217346;
            color: var(--white);
        }
        
        .btn-excel:hover {
            background: #1a5c38;
            transform: translateY(-1px);
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
        
        .data-table th,
        .data-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--dark);
            position: sticky;
            top: 0;
        }
        
        .data-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .data-table tfoot {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-profit {
            color: var(--success);
            font-weight: 600;
        }
        
        .text-cost {
            color: var(--danger);
            font-weight: 600;
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
            
            nav ul {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .export-buttons {
                flex-direction: column;
            }
            
            .data-table {
                display: block;
                overflow-x: auto;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-grid {
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
                        <li><a href="riwayat-mitra.php" class="active">Riwayat</a></li>
                        <li><a href="pengumuman-mitra.php">Pengumuman</a></li>
                        <li><a href="feedback-mitra.php">Feedback</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="page-header">
                <div class="page-title">
                    <h1>⚖️ Riwayat Penimbangan Sampah</h1>
                    <p>Lihat semua riwayat penimbangan dan penghasilan Anda</p>
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
            <section class="stats-grid">
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
                    <h3>💰 Total Bayar ke Warga</h3>
                    <div class="number">Rp <?php echo number_format($total_pendapatan_warga, 0, ',', '.'); ?></div>
                    <div class="subtext">Total dibayarkan</div>
                </div>
                <div class="stat-card">
                    <h3>💵 Total Penghasilan</h3>
                    <div class="number" style="color: var(--success);">
                        Rp <?php echo number_format($total_penghasilan_mitra, 0, ',', '.'); ?>
                    </div>
                    <div class="subtext">Keuntungan bersih</div>
                </div>
            </section>

            <!-- Filter Section -->
            <section class="filter-section">
                <form method="GET" class="filter-form">
                    <div class="filter-grid">
                        <div class="form-group">
                            <label for="bulan">📅 Filter Bulan</label>
                            <select id="bulan" name="bulan" class="form-control">
                                <option value="">Semua Bulan</option>
                                <?php
                                $bulan_list = [
                                    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                                    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                                    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                                ];
                                foreach ($bulan_list as $key => $value): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $bulan_filter == $key ? 'selected' : ''; ?>>
                                        <?php echo $value; ?>
                                    </option>
                                <?php endforeach; ?>
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
                                <a href="riwayat-mitra.php" class="btn btn-outline">
                                    🔄 Reset Filter
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </section>

            <!-- Penimbangan Table -->
            <section class="table-container">
                <?php if (empty($penimbangan)): ?>
                    <div class="empty-state">
                        <h3>📭 Belum ada riwayat penimbangan</h3>
                        <p>Tidak ada data penimbangan yang ditemukan untuk periode yang dipilih.</p>
                        <?php if ($bulan_filter || $tahun_filter != date('Y')): ?>
                            <a href="riwayat-mitra.php" class="btn btn-primary" style="margin-top: 15px;">
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
                                <th class="text-right">💰 Harga Beli/kg</th>
                                <th class="text-right">💵 Total Bayar</th>
                                <th class="text-right">💎 Penghasilan</th>
                                <th>📍 Lokasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($penimbangan as $t): ?>
                            <?php
                                // HITUNG PENGHASILAN MITRA
                                $harga_jual_per_kg = floatval($t['harga_per_kg']) * 1.2;
                                $total_penjualan = floatval($t['berat']) * $harga_jual_per_kg;
                                $pendapatan_mitra = $total_penjualan - floatval($t['total_harga']);
                                $margin_per_kg = $harga_jual_per_kg - floatval($t['harga_per_kg']);
                            ?>
                            <tr>
                                <td>
                                    <?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($t['nama_warga'] ?? 'Tidak Diketahui'); ?></strong>
                                    <?php if (!empty($t['hp_warga'])): ?>
                                        <br><small style="color: var(--gray);">📞 <?php echo htmlspecialchars($t['hp_warga']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($t['jenis_sampah'] ?? 'Tidak Diketahui'); ?></td>
                                <td class="text-right"><?php echo number_format(floatval($t['berat']), 1); ?></td>
                                <td class="text-right">Rp <?php echo number_format(floatval($t['harga_per_kg']), 0, ',', '.'); ?></td>
                                <td class="text-right text-cost">
                                    Rp <?php echo number_format(floatval($t['total_harga']), 0, ',', '.'); ?>
                                </td>
                                <td class="text-right text-profit">
                                    Rp <?php echo number_format($pendapatan_mitra, 0, ',', '.'); ?>
                                    <br><small style="color: var(--success);">(+Rp <?php echo number_format($margin_per_kg, 0, ',', '.'); ?>/kg)</small>
                                </td>
                                <td>
                                    <div style="max-width: 200px;">
                                        <?php echo htmlspecialchars($t['alamat_penjemputan'] ?? 'Tidak Diketahui'); ?>
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
                                <td class="text-right text-cost">
                                    Rp <?php echo number_format($total_pendapatan_warga, 0, ',', '.'); ?>
                                </td>
                                <td class="text-right text-profit">
                                    Rp <?php echo number_format($total_penghasilan_mitra, 0, ',', '.'); ?>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                <?php endif; ?>
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
    </script>
</body>
</html>