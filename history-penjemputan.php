<?php
include 'config.php';

// CEK LOGIN DAN ROLE USER
if (!isLoggedIn() || !isUser()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// INISIALISASI VARIABEL STATISTIK DENGAN NILAI DEFAULT
$total_penjemputan = 0;
$total_selesai = 0;
$total_belum_dinilai = 0;
$penjemputan = [];

try {
    // AMBIL SEMUA PENJEMPUTAN USER DENGAN DATA RATING
    $sql = "SELECT p.*, m.nama_mitra, m.no_hp as hp_mitra, 
                   r.rating, r.ulasan, r.created_at as waktu_penilaian
            FROM penjemputan p 
            LEFT JOIN mitra m ON p.id_mitra = m.id 
            LEFT JOIN rating_mitra r ON p.id = r.id_penjemputan AND r.id_user = ?
            WHERE p.id_warga = ? 
            ORDER BY p.waktu_pemintaan DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $user_id]);
    $penjemputan = $stmt->fetchAll();

    // HITUNG STATISTIK
    $total_penjemputan = count($penjemputan);
    
    foreach ($penjemputan as $p) {
        if ($p['status'] === 'selesai') {
            $total_selesai++;
            if (!$p['rating']) {
                $total_belum_dinilai++;
            }
        }
    }

} catch (PDOException $e) {
    error_log("History Penjemputan Error: " . $e->getMessage());
    $error = "Terjadi kesalahan saat mengambil data penjemputan.";
}

// PROSES SIMPAN PENILAIAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_penilaian'])) {
    $id_penjemputan = (int)$_POST['id_penjemputan'];
    $rating = (int)$_POST['rating'];
    $ulasan = sanitize($_POST['ulasan'] ?? '');
    
    // VALIDASI
    if ($rating < 1 || $rating > 5) {
        $error = "Penilaian harus antara 1-5 bintang!";
    } else {
        try {
            // CEK APAKAH PENJEMPUTAN MILIK USER DAN SUDAH SELESAI
            $stmt = $pdo->prepare("
                SELECT id, id_mitra 
                FROM penjemputan 
                WHERE id = ? AND id_warga = ? AND status = 'selesai'
            ");
            $stmt->execute([$id_penjemputan, $user_id]);
            $penjemputan_data = $stmt->fetch();
            
            if (!$penjemputan_data) {
                $error = "Penjemputan tidak valid atau belum selesai!";
            } else {
                // CEK APAKAH SUDAH ADA PENILAIAN
                $stmt = $pdo->prepare("
                    SELECT id FROM rating_mitra 
                    WHERE id_penjemputan = ? AND id_user = ?
                ");
                $stmt->execute([$id_penjemputan, $user_id]);
                $existing_rating = $stmt->fetch();
                
                if ($existing_rating) {
                    // UPDATE PENILAIAN YANG SUDAH ADA
                    $stmt = $pdo->prepare("
                        UPDATE rating_mitra 
                        SET rating = ?, ulasan = ?, updated_at = NOW() 
                        WHERE id_penjemputan = ? AND id_user = ?
                    ");
                    $stmt->execute([$rating, $ulasan, $id_penjemputan, $user_id]);
                    $success = "Penilaian berhasil diperbarui!";
                } else {
                    // SIMPAN PENILAIAN BARU
                    $stmt = $pdo->prepare("
                        INSERT INTO rating_mitra (id_penjemputan, id_user, id_mitra, rating, ulasan) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $id_penjemputan, 
                        $user_id, 
                        $penjemputan_data['id_mitra'], 
                        $rating, 
                        $ulasan
                    ]);
                    $success = "Terima kasih atas penilaian Anda!";
                }
                
                // Redirect untuk refresh data
                header("Location: history-penjemputan.php?success=" . urlencode($success));
                exit();
            }
            
        } catch (Exception $e) {
            $error = "Gagal menyimpan penilaian: " . $e->getMessage();
        }
    }
}

// HANDLE SUCCESS MESSAGE
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
?>

<!-- HTML DAN CSS TETAP SAMA SEPERTI SEBELUMNYA -->

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Penjemputan - Bank Sampah</title>
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
            display: inline-block;
            text-align: center;
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
        
        .btn-warning {
            background: var(--warning);
            color: var(--dark);
        }
        
        .btn-warning:hover {
            background: #e0a800;
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
        
        /* Rating Stars */
        .rating-stars {
            display: flex;
            gap: 2px;
            margin: 10px 0;
        }
        
        .star {
            color: #ddd;
            font-size: 1.2rem;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .star.active {
            color: #ffc107;
        }
        
        .star:hover {
            color: #ffc107;
        }
        
        .penilaian-text {
            background: #f8f9fa;
            padding: 15px;
            border-radius: var(--border-radius);
            margin-top: 10px;
            border-left: 3px solid var(--primary);
        }
        
        .penilaian-meta {
            font-size: 0.8rem;
            color: var(--gray);
            margin-top: 5px;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
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
        }
        
        .modal-content {
            background-color: var(--white);
            margin: 5% auto;
            padding: 0;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 500px;
            box-shadow: var(--shadow);
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
        }
        
        .close {
            font-size: 24px;
            cursor: pointer;
            color: var(--white);
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
            
            .action-buttons {
                flex-direction: column;
            }
            
            nav ul {
                flex-wrap: wrap;
                justify-content: center;
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
                    <h1>Bank Sampah - Riwayat Penjemputan</h1>
                </div>
                <nav>
                    <ul>
                        <li><a href="dashboard-user.php">Dashboard</a></li>
                        <li><a href="withdraw.php">Tarik Saldo</a></li>
                        <li><a href="history.php">Riwayat Transaksi</a></li>
                        <li><a href="history-penjemputan.php" class="active">Penjemputan</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <section class="history-penjemputan">
            <div class="container">
                <div class="page-header">
                    <div class="page-title">
                        <h1>Riwayat Penjemputan Sampah</h1>
                        <p>Lihat semua riwayat permintaan penjemputan sampah Anda</p>
                    </div>
                    <a href="dashboard-user.php" class="back-button">
                        ← Kembali ke Dashboard
                    </a>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Total Penjemputan</h3>
                        <div class="number"><?php echo $total_penjemputan; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Selesai</h3>
                        <div class="number"><?php echo $total_selesai; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Belum Dinilai</h3>
                        <div class="number"><?php echo $total_belum_dinilai; ?></div>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" class="filter-form">
                        <div class="filter-grid">
                            <div class="form-group">
                                <label for="status">Filter Status</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="">Semua Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="dijadwalkan">Dijadwalkan</option>
                                    <option value="diproses">Diproses</option>
                                    <option value="selesai">Selesai</option>
                                    <option value="ditolak">Ditolak</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="has_rating">Filter Penilaian</label>
                                <select id="has_rating" name="has_rating" class="form-control">
                                    <option value="">Semua</option>
                                    <option value="yes">Sudah Dinilai</option>
                                    <option value="no">Belum Dinilai</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Penjemputan List -->
                <div class="penjemputan-list">
                    <?php if (empty($penjemputan)): ?>
                        <div class="empty-state">
                            <h3>Belum ada riwayat penjemputan</h3>
                            <p>Anda belum pernah mengajukan penjemputan sampah.</p>
                            <a href="dashboard-user.php" class="btn btn-primary" style="margin-top: 15px;">
                                Ajukan Penjemputan Pertama
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($penjemputan as $p): ?>
                        <div class="penjemputan-card">
                            <div class="penjemputan-header">
                                <div class="penjemputan-info">
                                    <h3><?php echo htmlspecialchars($p['jenis_sampah']); ?></h3>
                                    <span class="status-badge status-<?php echo $p['status']; ?>">
                                        <?php 
                                        $status_text = [
                                            'pending' => 'Menunggu Konfirmasi',
                                            'dijadwalkan' => 'Dijadwalkan', 
                                            'diproses' => 'Sedang Diproses',
                                            'selesai' => 'Selesai',
                                            'ditolak' => 'Ditolak'
                                        ];
                                        echo $status_text[$p['status']] ?? $p['status'];
                                        ?>
                                    </span>
                                </div>
                                
                                <?php if ($p['status'] === 'selesai' && $p['nama_mitra']): ?>
                                <div class="action-buttons">
                                    <?php if ($p['rating']): ?>
                                        <button type="button" class="btn btn-outline btn-sm" 
                                                onclick="openPenilaianModal(<?php echo $p['id']; ?>, <?php echo $p['rating']; ?>, '<?php echo htmlspecialchars($p['ulasan']); ?>')">
                                            ✏️ Edit Penilaian
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-warning btn-sm" 
                                                onclick="openPenilaianModal(<?php echo $p['id']; ?>)">
                                            ⭐ Beri Penilaian
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="penjemputan-meta">
                                <div class="meta-item">
                                    <span class="meta-label">Tanggal Pengajuan</span>
                                    <span class="meta-value">
                                        <?php echo date('d/m/Y H:i', strtotime($p['waktu_pemintaan'])); ?>
                                    </span>
                                </div>
                                
                                <?php if ($p['waktu_penjemputan']): ?>
                                <div class="meta-item">
                                    <span class="meta-label">Jadwal Penjemputan</span>
                                    <span class="meta-value">
                                        <?php echo date('d/m/Y H:i', strtotime($p['waktu_penjemputan'])); ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($p['nama_mitra']): ?>
                                <div class="meta-item">
                                    <span class="meta-label">Mitra</span>
                                    <span class="meta-value">
                                        <?php echo htmlspecialchars($p['nama_mitra']); ?>
                                        <?php if ($p['hp_mitra']): ?>
                                            <br><small><?php echo htmlspecialchars($p['hp_mitra']); ?></small>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="meta-item">
                                    <span class="meta-label">Alamat Penjemputan</span>
                                    <span class="meta-value">
                                        <?php echo htmlspecialchars($p['alamat_penjemputan']); ?>
                                        <?php if ($p['keterangan']): ?>
                                            <br><small><?php echo htmlspecialchars($p['keterangan']); ?></small>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Tampilkan Penilaian jika sudah ada -->
                            <?php if ($p['rating']): ?>
                            <div class="penilaian-section">
                                <div class="rating-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="star <?php echo $i <= $p['rating'] ? 'active' : ''; ?>">★</span>
                                    <?php endfor; ?>
                                    <span style="margin-left: 10px; font-weight: 600;">
                                        <?php echo $p['rating']; ?>/5
                                    </span>
                                </div>
                                
                                <?php if ($p['ulasan']): ?>
                                <div class="penilaian-text">
                                    <?php echo htmlspecialchars($p['ulasan']); ?>
                                    <div class="penilaian-meta">
                                        Dinilai pada: <?php echo date('d/m/Y H:i', strtotime($p['waktu_penilaian'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <!-- Modal untuk memberikan penilaian -->
    <div id="penilaianModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Beri Penilaian untuk Mitra</h2>
                <span class="close" onclick="closeModal('penilaianModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="penilaianForm" method="POST">
                    <input type="hidden" name="id_penjemputan" id="penilaian_penjemputan_id">
                    <input type="hidden" name="submit_penilaian" value="1">
                    
                    <div class="form-group">
                        <label>Penilaian:</label>
                        <div class="rating-stars" id="ratingStars">
                            <span class="star" data-rating="1">★</span>
                            <span class="star" data-rating="2">★</span>
                            <span class="star" data-rating="3">★</span>
                            <span class="star" data-rating="4">★</span>
                            <span class="star" data-rating="5">★</span>
                        </div>
                        <input type="hidden" name="rating" id="selectedRating" required>
                        <small style="color: var(--gray);">Klik bintang untuk memberikan penilaian (1-5)</small>
                    </div>

                    <div class="form-group">
                        <label for="ulasan">Komentar (opsional):</label>
                        <textarea id="ulasan" name="ulasan" class="form-control" rows="4" 
                                  placeholder="Bagaimana pengalaman Anda dengan layanan mitra?"></textarea>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            ✅ Simpan Penilaian
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeModal('penilaianModal')">
                            Batal
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
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Open penilaian modal
        function openPenilaianModal(penjemputanId, existingRating = 0, existingUlasan = '') {
            document.getElementById('penilaian_penjemputan_id').value = penjemputanId;
            
            // Reset stars
            const stars = document.querySelectorAll('#ratingStars .star');
            stars.forEach(star => {
                star.classList.remove('active');
            });
            
            // Set existing rating if any
            if (existingRating > 0) {
                setRating(existingRating);
                document.getElementById('selectedRating').value = existingRating;
            } else {
                document.getElementById('selectedRating').value = '';
            }
            
            // Set existing ulasan if any
            if (existingUlasan) {
                document.getElementById('ulasan').value = existingUlasan;
            } else {
                document.getElementById('ulasan').value = '';
            }
            
            openModal('penilaianModal');
        }
        
        // Rating system
        function setRating(rating) {
            const stars = document.querySelectorAll('#ratingStars .star');
            stars.forEach((star, index) => {
                if (index < rating) {
                    star.classList.add('active');
                } else {
                    star.classList.remove('active');
                }
            });
            document.getElementById('selectedRating').value = rating;
        }
        
        // Initialize rating stars
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('#ratingStars .star');
            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    setRating(rating);
                });
                
                star.addEventListener('mouseover', function() {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    const stars = document.querySelectorAll('#ratingStars .star');
                    stars.forEach((s, index) => {
                        if (index < rating) {
                            s.style.color = '#ffc107';
                        } else {
                            s.style.color = '#ddd';
                        }
                    });
                });
                
                star.addEventListener('mouseout', function() {
                    const currentRating = parseInt(document.getElementById('selectedRating').value) || 0;
                    const stars = document.querySelectorAll('#ratingStars .star');
                    stars.forEach((s, index) => {
                        if (index < currentRating) {
                            s.style.color = '#ffc107';
                        } else {
                            s.style.color = '#ddd';
                        }
                    });
                });
            });
            
            // Form validation
            const penilaianForm = document.getElementById('penilaianForm');
            if (penilaianForm) {
                penilaianForm.addEventListener('submit', function(e) {
                    const rating = document.getElementById('selectedRating').value;
                    if (!rating) {
                        e.preventDefault();
                        alert('Silakan berikan penilaian dengan mengklik bintang!');
                        return false;
                    }
                });
            }
            
            // Close modal when clicking outside
            window.onclick = function(event) {
                const modals = document.getElementsByClassName('modal');
                for (let modal of modals) {
                    if (event.target === modal) {
                        modal.style.display = 'none';
                    }
                }
            }
            
            // Auto-hide alerts
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                });
            }, 5000);
        });
    </script>
</body>
</html>