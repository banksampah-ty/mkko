<?php
include 'config.php';

// CEK LOGIN DAN ROLE USER
if (!isLoggedIn() || !isUser()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// AMBIL SEMUA TRANSAKSI USER
$stmt = $pdo->prepare("
    SELECT t.*, j.nama_jenis, j.harga_per_kg 
    FROM transaksi t 
    JOIN jenis_sampah j ON t.jenis_sampah_id = j.id 
    WHERE t.user_id = ? 
    ORDER BY t.tanggal_transaksi DESC
");
$stmt->execute([$user_id]);
$transaksi = $stmt->fetchAll();

// HITUNG TOTAL
$total_transaksi = count($transaksi);
$total_berat = array_sum(array_column($transaksi, 'berat'));
$total_saldo = array_sum(array_column($transaksi, 'total_harga'));

// FUNGSI BANTU JIKA BELUM ADA
if (!function_exists('formatRupiah')) {
    function formatRupiah($angka) {
        return 'Rp ' . number_format($angka, 0, ',', '.');
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Transaksi - Bank Sampah</title>
    <style>
        :root {
            --primary: #2e7d32;
            --primary-light: #4caf50;
            --secondary: #ff9800;
            --light: #f5f5f5;
            --dark: #333;
            --gray: #757575;
            --white: #ffffff;
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
        
        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background-color: var(--white);
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--shadow);
            text-align: center;
        }
        
        .summary-card h3 {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 10px;
        }
        
        .summary-card .number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        /* Table Styles */
        .table-container {
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            padding: 20px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
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
        }
        
        .data-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: var(--gray);
        }
        
        /* Status Badge */
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        /* Footer */
        footer {
            background-color: var(--white);
            padding: 20px 0;
            text-align: center;
            color: var(--gray);
            border-top: 1px solid #e0e0e0;
            margin-top: 30px;
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
            
            .table-container {
                overflow-x: auto;
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
                    <h1>Bank Sampah - Riwayat</h1>
                </div>
                <nav>
                    <ul>
                        <li><a href="dashboard-user.php">Dashboard</a></li>
                        <li><a href="withdraw.php">Tarik Saldo</a></li>
                        <li><a href="history.php" class="active">Riwayat</a></li>
                        <li><a href="?logout=1">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <section class="history">
            <div class="container">
                <div class="page-header">
                    <div class="page-title">
                        <h1>Riwayat Transaksi</h1>
                        <p>Semua riwayat setor sampah Anda</p>
                    </div>
                    <a href="dashboard-user.php" class="back-button">
                        ← Kembali ke Dashboard
                    </a>
                </div>

                <!-- SUMMARY CARDS -->
                <div class="summary-cards">
                    <div class="summary-card">
                        <h3>Total Transaksi</h3>
                        <div class="number"><?php echo $total_transaksi; ?></div>
                    </div>
                    <div class="summary-card">
                        <h3>Total Berat Sampah</h3>
                        <div class="number"><?php echo number_format($total_berat, 2); ?> kg</div>
                    </div>
                    <div class="summary-card">
                        <h3>Total Pendapatan</h3>
                        <div class="number"><?php echo formatRupiah($total_saldo); ?></div>
                    </div>
                </div>

                <!-- RIWAYAT TRANSAKSI -->
                <div class="table-container">
                    <?php if (empty($transaksi)): ?>
                        <div class="no-data">
                            <h3>Belum ada transaksi</h3>
                            <p>Mulai setor sampah pertama Anda di dashboard</p>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Jenis Sampah</th>
                                    <th>Berat</th>
                                    <th>Harga per Kg</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transaksi as $trx): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($trx['tanggal_transaksi'])); ?></td>
                                    <td><?php echo htmlspecialchars($trx['nama_jenis']); ?></td>
                                    <td><?php echo number_format($trx['berat'], 2); ?> kg</td>
                                    <td><?php echo formatRupiah($trx['harga_per_kg']); ?></td>
                                    <td><?php echo formatRupiah($trx['total_harga']); ?></td>
                                    <td>
                                        <span class="status-badge status-success">Selesai</span>
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

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Bank Sampah. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>