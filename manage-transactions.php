<?php
include 'config.php';

// Cek login dan role admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

// Ambil semua transaksi
$stmt = $pdo->query("
    SELECT t.*, u.nama_lengkap, u.username, j.nama_jenis, j.harga_per_kg 
    FROM transaksi t 
    JOIN users u ON t.user_id = u.id 
    JOIN jenis_sampah j ON t.jenis_sampah_id = j.id 
    ORDER BY t.tanggal_transaksi DESC
");
$transaksi = $stmt->fetchAll();

// Hitung statistik
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_transaksi,
        COALESCE(SUM(berat), 0) as total_berat,
        COALESCE(SUM(total_harga), 0) as total_nilai
    FROM transaksi
");
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Transaksi - Bank Sampah</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <span class="logo-icon">♻️</span>
                    <h1>Bank Sampah - Transaksi</h1>
                </div>
                <nav>
                    <ul>
                       <li><a href="dashboard-admin.php">Dashboard</a></li>
                        <li><a href="manage-users.php">Kelola Warga</a></li>
                        <li><a href="manage-mitra.php">Kelola Mitra</a></li>
                        <li><a href="manage-transactions.php" class="active">Transaksi</a></li>
                        <li><a href="manage-jenis-sampah.php">Jenis Sampah</a></li>
                        <li><a href="manage-withdrawals.php">Penarikan</a></li>
                        <li><a href="?logout=1">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <section class="dashboard">
            <div class="container">
                <div class="dashboard-header">
                    <div class="dashboard-title">
                        <h1>Data Transaksi Sampah</h1>
                        <p>Riwayat semua transaksi setor sampah nasabah</p>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">📊</div>
                        <div class="stat-info">
                            <h3>Total Transaksi</h3>
                            <p class="stat-number"><?php echo $stats['total_transaksi']; ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">📦</div>
                        <div class="stat-info">
                            <h3>Total Berat</h3>
                            <p class="stat-number"><?php echo number_format($stats['total_berat'], 2); ?> kg</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">💰</div>
                        <div class="stat-info">
                            <h3>Total Nilai</h3>
                            <p class="stat-number"><?php echo formatRupiah($stats['total_nilai']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="dashboard-content">
                    <div class="content-section">
                        <div class="section-header">
                            <h2>Daftar Transaksi</h2>
                            <div>
                                <button class="btn btn-primary" onclick="exportTransaksi()">Export Data</button>
                            </div>
                        </div>
                        
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID Transaksi</th>
                                        <th>Tanggal</th>
                                        <th>Nasabah</th>
                                        <th>Jenis Sampah</th>
                                        <th>Berat (kg)</th>
                                        <th>Harga/kg</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($transaksi)): ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; padding: 2rem; color: var(--gray);">
                                                Belum ada transaksi
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($transaksi as $trx): ?>
                                        <tr>
                                            <td>
                                                <strong>TRX<?php echo str_pad($trx['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($trx['tanggal_transaksi'])); ?></td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($trx['nama_lengkap']); ?></strong><br>
                                                    <small style="color: var(--gray);">@<?php echo htmlspecialchars($trx['username']); ?></small>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($trx['nama_jenis']); ?></td>
                                            <td><?php echo number_format($trx['berat'], 2); ?></td>
                                            <td><?php echo formatRupiah($trx['harga_per_kg']); ?></td>
                                            <td style="font-weight: bold; color: var(--primary);">
                                                <?php echo formatRupiah($trx['total_harga']); ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
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
        function exportTransaksi() {
            alert('Fitur export data transaksi akan segera tersedia!');
            // Di sini bisa diimplementasikan export ke Excel/PDF
        }
    </script>
</body>
</html>