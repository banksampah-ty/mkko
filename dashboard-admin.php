<?php
include 'config.php';

// CEK LOGIN DAN ROLE ADMIN
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

// HANDLE LOGOUT
if (isset($_GET['logout'])) {
    session_destroy();
    redirect('login.php');
}

// HANDLE VERIFIKASI MITRA
if (isset($_GET['verify_mitra'])) {
    $mitra_id = intval($_GET['verify_mitra']);
    try {
        $stmt = $pdo->prepare("UPDATE mitra SET status_verifikasi = 'verified' WHERE id = ?");
        $stmt->execute([$mitra_id]);
        setFlashMessage('success', 'Mitra berhasil diverifikasi!');
    } catch (PDOException $e) {
        setFlashMessage('error', 'Gagal memverifikasi mitra: ' . $e->getMessage());
    }
    redirect('dashboard-admin.php');
}

// HANDLE REJECT MITRA
if (isset($_GET['reject_mitra'])) {
    $mitra_id = intval($_GET['reject_mitra']);
    try {
        $stmt = $pdo->prepare("UPDATE mitra SET status_verifikasi = 'rejected' WHERE id = ?");
        $stmt->execute([$mitra_id]);
        setFlashMessage('success', 'Mitra berhasil ditolak!');
    } catch (PDOException $e) {
        setFlashMessage('error', 'Gagal menolak mitra: ' . $e->getMessage());
    }
    redirect('dashboard-admin.php');
}

// AMBIL DATA STATISTIK UTAMA
$stmt = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'user') as total_nasabah,
        (SELECT COUNT(*) FROM mitra) as total_mitra,
        (SELECT COUNT(*) FROM mitra WHERE status_verifikasi = 'pending') as mitra_pending,
        (SELECT COUNT(*) FROM transaksi) as total_transaksi,
        (SELECT COALESCE(SUM(berat), 0) FROM transaksi) as total_sampah,
        (SELECT COALESCE(SUM(total_harga), 0) FROM transaksi) as total_uang,
        (SELECT COUNT(*) FROM penarikan WHERE status = 'pending') as penarikan_pending,
        (SELECT COUNT(*) FROM penarikan WHERE status = 'approved') as penarikan_approved,
        (SELECT COALESCE(SUM(jumlah), 0) FROM penarikan WHERE status = 'approved') as total_penarikan,
        (SELECT COUNT(*) FROM users WHERE status = 'active' AND role = 'user') as user_aktif
");
$stats = $stmt->fetch();

// STATISTIK BULAN INI
$current_month = date('Y-m');
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(berat), 0) as sampah_bulan_ini,
        COALESCE(SUM(total_harga), 0) as uang_bulan_ini,
        COUNT(*) as transaksi_bulan_ini
    FROM transaksi 
    WHERE DATE_FORMAT(tanggal_transaksi, '%Y-%m') = ?
");
$stmt->execute([$current_month]);
$stats_bulan_ini = $stmt->fetch();

// STATISTIK JENIS SAMPAH
$stmt = $pdo->query("
    SELECT j.nama_jenis, 
           COUNT(t.id) as total_transaksi,
           COALESCE(SUM(t.berat), 0) as total_berat,
           COALESCE(SUM(t.total_harga), 0) as total_nilai
    FROM jenis_sampah j 
    LEFT JOIN transaksi t ON j.id = t.jenis_sampah_id 
    GROUP BY j.id, j.nama_jenis
    ORDER BY total_nilai DESC
");
$jenis_sampah_stats = $stmt->fetchAll();

// DATA NASAABAH TERBARU
$stmt = $pdo->query("
    SELECT * FROM users 
    WHERE role = 'user' 
    ORDER BY created_at DESC 
    LIMIT 5
");
$nasabah_terbaru = $stmt->fetchAll();

// MITRA MENUNGGU VERIFIKASI
$stmt = $pdo->query("
    SELECT * FROM mitra 
    WHERE status_verifikasi = 'pending'
    ORDER BY created_at DESC 
    LIMIT 5
");
$mitra_pending = $stmt->fetchAll();

// TRANSAKSI TERBARU
$stmt = $pdo->query("
    SELECT t.*, u.nama_lengkap, u.username, j.nama_jenis, j.harga_per_kg
    FROM transaksi t 
    JOIN users u ON t.user_id = u.id 
    JOIN jenis_sampah j ON t.jenis_sampah_id = j.id 
    ORDER BY t.tanggal_transaksi DESC 
    LIMIT 8
");
$transaksi_terbaru = $stmt->fetchAll();

// PENARIKAN PENDING
$stmt = $pdo->query("
    SELECT p.*, u.nama_lengkap, u.username, u.saldo
    FROM penarikan p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.status = 'pending'
    ORDER BY p.tanggal_penarikan ASC 
    LIMIT 5
");
$penarikan_pending = $stmt->fetchAll();

// GRAFIK TRANSAKSI 7 HARI TERAKHIR
$stmt = $pdo->query("
    SELECT 
        DATE(tanggal_transaksi) as tanggal,
        COUNT(*) as jumlah_transaksi,
        COALESCE(SUM(berat), 0) as total_berat,
        COALESCE(SUM(total_harga), 0) as total_uang
    FROM transaksi 
    WHERE tanggal_transaksi >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(tanggal_transaksi)
    ORDER BY tanggal ASC
");
$grafik_data = $stmt->fetchAll();

// HITUNG RATA-RATA
$rata_transaksi = $stats['total_transaksi'] > 0 ? $stats['total_uang'] / $stats['total_transaksi'] : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Bank Sampah</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <span class="logo-icon">♻️</span>
                    <h1>Bank Sampah - Admin Dashboard</h1>
                </div>
                <nav>
                    <ul>
                        <li><a href="dashboard-admin.php" class="active">Dashboard</a></li>
                        <li><a href="manage-users.php">Kelola Warga</a></li>
                        <li><a href="manage-mitra.php">Kelola Mitra</a></li>
                        <li><a href="manage-transactions.php">Transaksi</a></li>
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
                <!-- HEADER DASHBOARD -->
                <div class="dashboard-header">
                    <div class="dashboard-title">
                        <h1>Dashboard Administrator</h1>
                        <p>Selamat datang, <?php echo $_SESSION['nama_lengkap']; ?>! - <?php echo date('l, d F Y'); ?></p>
                    </div>
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['nama_lengkap'], 0, 2)); ?>
                        </div>
                        <div class="user-details">
                            <div class="username"><?php echo $_SESSION['nama_lengkap']; ?></div>
                            <div class="role">Administrator</div>
                        </div>
                    </div>
                </div>

                <!-- FLASH MESSAGES -->
                <?php
                $flashMessage = getFlashMessage();
                if ($flashMessage): ?>
                    <div class="alert alert-<?php echo $flashMessage['type']; ?>">
                        <?php echo $flashMessage['message']; ?>
                    </div>
                <?php endif; ?>

                <!-- STATS GRID UTAMA -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-info">
                            <h3>Total Nasabah</h3>
                            <p class="stat-number"><?php echo $stats['total_nasabah']; ?></p>
                            <small><?php echo $stats['user_aktif']; ?> aktif</small>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">🤝</div>
                        <div class="stat-info">
                            <h3>Total Mitra</h3>
                            <p class="stat-number"><?php echo $stats['total_mitra']; ?></p>
                            <small><?php echo $stats['mitra_pending']; ?> pending</small>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">💰</div>
                        <div class="stat-info">
                            <h3>Total Transaksi</h3>
                            <p class="stat-number"><?php echo formatRupiah($stats['total_uang']); ?></p>
                            <small><?php echo $stats['total_transaksi']; ?> transaksi</small>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">⏳</div>
                        <div class="stat-info">
                            <h3>Pending Actions</h3>
                            <p class="stat-number"><?php echo ($stats['penarikan_pending'] + $stats['mitra_pending']); ?></p>
                            <small><?php echo $stats['penarikan_pending']; ?> penarikan + <?php echo $stats['mitra_pending']; ?> mitra</small>
                        </div>
                    </div>
                </div>

                <!-- STATS BULAN INI -->
                <div class="stats-grid">
                    <div class="stat-card mini">
                        <div class="stat-icon">📅</div>
                        <div class="stat-info">
                            <h3>Transaksi Bulan Ini</h3>
                            <p class="stat-number"><?php echo $stats_bulan_ini['transaksi_bulan_ini']; ?></p>
                        </div>
                    </div>
                    <div class="stat-card mini">
                        <div class="stat-icon">💵</div>
                        <div class="stat-info">
                            <h3>Pendapatan Bulan Ini</h3>
                            <p class="stat-number"><?php echo formatRupiah($stats_bulan_ini['uang_bulan_ini']); ?></p>
                        </div>
                    </div>
                    <div class="stat-card mini">
                        <div class="stat-icon">🗑️</div>
                        <div class="stat-info">
                            <h3>Sampah Bulan Ini</h3>
                            <p class="stat-number"><?php echo number_format($stats_bulan_ini['sampah_bulan_ini'], 2); ?> kg</p>
                        </div>
                    </div>
                    <div class="stat-card mini">
                        <div class="stat-icon">✅</div>
                        <div class="stat-info">
                            <h3>Penarikan Disetujui</h3>
                            <p class="stat-number"><?php echo $stats['penarikan_approved']; ?></p>
                        </div>
                    </div>
                </div>

                <!-- CONTENT GRID -->
                <div class="content-grid">
                    <!-- COLUMN KIRI -->
                    <div class="content-column">
                        <!-- GRAFIK TRANSAKSI -->
                        <div class="content-section">
                            <div class="section-header">
                                <h2>Grafik Transaksi 7 Hari Terakhir</h2>
                            </div>
                            <div class="chart-container">
                                <canvas id="transaksiChart"></canvas>
                            </div>
                        </div>

                        <!-- JENIS SAMPAH TERPOPULER -->
                        <div class="content-section">
                            <div class="section-header">
                                <h2>Jenis Sampah Terpopuler</h2>
                            </div>
                            <div class="table-container">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Jenis Sampah</th>
                                            <th>Transaksi</th>
                                            <th>Berat</th>
                                            <th>Total Nilai</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($jenis_sampah_stats as $jenis): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($jenis['nama_jenis']); ?></td>
                                            <td><?php echo $jenis['total_transaksi']; ?></td>
                                            <td><?php echo number_format($jenis['total_berat'], 2); ?> kg</td>
                                            <td><?php echo formatRupiah($jenis['total_nilai']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- COLUMN KANAN -->
                    <div class="content-column">
                        <!-- MITRA MENUNGGU VERIFIKASI -->
                        <div class="content-section">
                            <div class="section-header">
                                <h2>Mitra Menunggu Verifikasi</h2>
                                <a href="manage-mitra.php" class="btn-link">Kelola Mitra</a>
                            </div>
                            <div class="pending-list">
                                <?php if (empty($mitra_pending)): ?>
                                    <div class="empty-state">
                                        <p>Tidak ada mitra yang menunggu verifikasi</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($mitra_pending as $mitra): ?>
                                    <div class="pending-item">
                                        <div class="pending-info">
                                            <div class="pending-user">
                                                <strong><?php echo htmlspecialchars($mitra['nama_mitra']); ?></strong>
                                                <small>@<?php echo htmlspecialchars($mitra['username']); ?></small>
                                            </div>
                                            <div class="pending-amount">
                                                <?php echo date('d/m/Y', strtotime($mitra['created_at'])); ?>
                                            </div>
                                        </div>
                                        <div class="pending-meta">
                                            <small><?php echo htmlspecialchars($mitra['email']); ?></small>
                                            <small><?php echo htmlspecialchars($mitra['no_hp']); ?></small>
                                        </div>
                                        <div class="pending-actions">
                                            <a href="?verify_mitra=<?php echo $mitra['id']; ?>" class="btn-sm btn-success" 
                                               onclick="return confirm('Verifikasi mitra <?php echo $mitra['nama_mitra']; ?>?')">
                                                Verifikasi
                                            </a>
                                            <a href="?reject_mitra=<?php echo $mitra['id']; ?>" class="btn-sm btn-danger"
                                               onclick="return confirm('Tolak mitra <?php echo $mitra['nama_mitra']; ?>?')">
                                                Tolak
                                            </a>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- PENARIKAN MENUNGGU -->
                        <div class="content-section">
                            <div class="section-header">
                                <h2>Penarikan Menunggu Verifikasi</h2>
                                <a href="manage-withdrawals.php" class="btn-link">Lihat Semua</a>
                            </div>
                            <div class="pending-list">
                                <?php if (empty($penarikan_pending)): ?>
                                    <div class="empty-state">
                                        <p>Tidak ada penarikan yang menunggu verifikasi</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($penarikan_pending as $p): ?>
                                    <div class="pending-item">
                                        <div class="pending-info">
                                            <div class="pending-user">
                                                <strong><?php echo htmlspecialchars($p['nama_lengkap']); ?></strong>
                                                <small>@<?php echo htmlspecialchars($p['username']); ?></small>
                                            </div>
                                            <div class="pending-amount">
                                                <?php echo formatRupiah($p['jumlah']); ?>
                                            </div>
                                        </div>
                                        <div class="pending-meta">
                                            <small>Saldo: <?php echo formatRupiah($p['saldo']); ?></small>
                                            <small><?php echo date('d/m H:i', strtotime($p['tanggal_penarikan'])); ?></small>
                                        </div>
                                        <div class="pending-actions">
                                            <a href="manage-withdrawals.php?approve=<?php echo $p['id']; ?>" class="btn-sm btn-success">Approve</a>
                                            <a href="manage-withdrawals.php?reject=<?php echo $p['id']; ?>" class="btn-sm btn-danger">Tolak</a>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- NASAABAH TERBARU -->
                        <div class="content-section">
                            <div class="section-header">
                                <h2>Nasabah Terbaru</h2>
                                <a href="manage-users.php" class="btn-link">Lihat Semua</a>
                            </div>
                            <div class="user-list">
                                <?php foreach ($nasabah_terbaru as $user): ?>
                                <div class="user-item">
                                    <div class="user-avatar sm">
                                        <?php echo strtoupper(substr($user['nama_lengkap'], 0, 1)); ?>
                                    </div>
                                    <div class="user-details">
                                        <div class="user-name"><?php echo htmlspecialchars($user['nama_lengkap']); ?></div>
                                        <div class="user-meta">
                                            <span class="user-username">@<?php echo htmlspecialchars($user['username']); ?></span>
                                            <span class="user-saldo"><?php echo formatRupiah($user['saldo']); ?></span>
                                        </div>
                                    </div>
                                    <div class="user-date">
                                        <?php echo date('d/m', strtotime($user['created_at'])); ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 Bank Sampah Desa Mejobo. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // GRAFIK TRANSAKSI
        const ctx = document.getElementById('transaksiChart').getContext('2d');
        const transaksiChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [
                    <?php 
                    $labels = [];
                    $data_berat = [];
                    $data_uang = [];
                    
                    foreach ($grafik_data as $data) {
                        $labels[] = "'" . date('d M', strtotime($data['tanggal'])) . "'";
                        $data_berat[] = $data['total_berat'];
                        $data_uang[] = $data['total_uang'];
                    }
                    echo implode(', ', $labels);
                    ?>
                ],
                datasets: [
                    {
                        label: 'Total Berat (kg)',
                        data: [<?php echo implode(', ', $data_berat); ?>],
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Total Uang (Rp)',
                        data: [<?php echo implode(', ', $data_uang); ?>],
                        backgroundColor: 'rgba(75, 192, 192, 0.5)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1,
                        yAxisID: 'y1',
                        type: 'line'
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Berat (kg)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Uang (Rp)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });

        // AUTO REFRESH SETIAP 30 DETIK UNTUK PENDING ACTIONS
        setTimeout(() => {
            window.location.reload();
        }, 30000);
    </script>
</body>
</html>