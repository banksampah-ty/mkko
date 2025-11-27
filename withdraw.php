<?php
include 'config.php';

// CEK LOGIN DAN ROLE USER
if (!isLoggedIn() || !isUser()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// AMBIL DATA USER LENGKAP
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// AMBIL NOTIFIKASI USER
$stmt = $pdo->prepare("
    SELECT * FROM notifikasi 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$notifikasi = $stmt->fetchAll();

// HITUNG JUMLAH NOTIFIKASI BELUM DIBACA
$stmt = $pdo->prepare("
    SELECT COUNT(*) as jumlah_belum_dibaca 
    FROM notifikasi 
    WHERE user_id = ? AND dibaca = 'no'
");
$stmt->execute([$user_id]);
$notif_data = $stmt->fetch();
$jumlah_notifikasi = $notif_data['jumlah_belum_dibaca'] ?? 0;

// AMBIL RIWAYAT PENARIKAN TERBARU
$stmt = $pdo->prepare("
    SELECT * FROM penarikan 
    WHERE user_id = ? 
    ORDER BY tanggal_penarikan DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$riwayat_penarikan = $stmt->fetchAll();

// AMBIL METODE PEMBAYARAN YANG TERSEDIA
$stmt = $pdo->prepare("
    SELECT * FROM metode_pembayaran 
    WHERE status = 'active'
    ORDER BY nama_metode ASC
");
$stmt->execute();
$metode_pembayaran = $stmt->fetchAll();

// PROSES PENARIKAN SALDO
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tarik_saldo'])) {
        $jumlah = (float)$_POST['jumlah'];
        $keterangan = sanitize($_POST['keterangan'] ?? '');
        $metode_pembayaran_id = (int)$_POST['metode_pembayaran'];
        $nomor_tujuan = sanitize($_POST['nomor_tujuan'] ?? '');
        
        // VALIDASI
        if ($jumlah <= 0) {
            $error = "Jumlah penarikan harus lebih dari 0";
        } elseif ($jumlah > $user['saldo']) {
            $error = "Saldo tidak mencukupi. Saldo tersedia: " . formatRupiah($user['saldo']);
        } elseif (empty($metode_pembayaran_id)) {
            $error = "Pilih metode pembayaran terlebih dahulu";
        } elseif (empty($nomor_tujuan)) {
            $error = "Masukkan nomor tujuan pembayaran";
        } else {
            try {
                // CEK METODE PEMBAYARAN VALID
                $stmt = $pdo->prepare("SELECT * FROM metode_pembayaran WHERE id = ? AND status = 'active'");
                $stmt->execute([$metode_pembayaran_id]);
                $metode = $stmt->fetch();
                
                if (!$metode) {
                    $error = "Metode pembayaran tidak valid";
                } else {
                    // SIMPAN PENARIKAN DENGAN STATUS PENDING
                    $stmt = $pdo->prepare("
                        INSERT INTO penarikan (user_id, jumlah, keterangan, metode_pembayaran_id, nomor_tujuan, status) 
                        VALUES (?, ?, ?, ?, ?, 'pending')
                    ");
                    
                    $stmt->execute([$user_id, $jumlah, $keterangan, $metode_pembayaran_id, $nomor_tujuan]);
                    
                    // BUAT NOTIFIKASI UNTUK USER
                    $stmt = $pdo->prepare("
                        INSERT INTO notifikasi (user_id, judul, pesan) 
                        VALUES (?, 'Penarikan Diajukan', ?)
                    ");
                    $pesan_notifikasi = "Pengajuan penarikan saldo sebesar " . formatRupiah($jumlah) . " ke " . $metode['nama_metode'] . " (" . $nomor_tujuan . ") telah diajukan. Menunggu persetujuan admin.";
                    $stmt->execute([$user_id, $pesan_notifikasi]);
                    
                    // BUAT NOTIFIKASI UNTUK ADMIN
                    $stmt = $pdo->prepare("
                        INSERT INTO notifikasi (user_id, judul, pesan, tipe) 
                        SELECT id, 'Penarikan Baru', ?, 'user'
                        FROM users 
                        WHERE role = 'admin'
                    ");
                    $pesan_admin = "Ada pengajuan penarikan baru dari " . $user['nama_lengkap'] . " sebesar " . formatRupiah($jumlah) . " ke " . $metode['nama_metode'] . " (" . $nomor_tujuan . ")";
                    $stmt->execute([$pesan_admin]);
                    
                    $success = "Pengajuan penarikan berhasil! Menunggu persetujuan admin.";
                    
                    // RELOAD DATA TERBARU
                    header("Location: withdraw.php?success=" . urlencode($success));
                    exit();
                }
                
            } catch (Exception $e) {
                $error = "Terjadi kesalahan: " . $e->getMessage();
            }
        }
    }
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
    <title>Tarik Saldo - Bank Sampah</title>
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
            max-width: 1000px;
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
            padding: 8px 12px;
            border-radius: var(--border-radius);
            transition: all 0.3s;
            font-size: 0.9rem;
            white-space: nowrap;
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
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .content-section {
            background: var(--white);
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .content-section h2 {
            color: var(--primary);
            margin-bottom: 20px;
            font-size: 1.3rem;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
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
        
        /* Payment Method Styles */
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .payment-method {
            border: 2px solid #ddd;
            border-radius: var(--border-radius);
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: var(--white);
        }
        
        .payment-method:hover {
            border-color: var(--primary);
        }
        
        .payment-method.selected {
            border-color: var(--primary);
            background-color: #e8f5e8;
        }
        
        .payment-icon {
            font-size: 2rem;
            margin-bottom: 8px;
        }
        
        .payment-name {
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        /* Button Styles */
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
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
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background: var(--primary);
            color: var(--white);
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
        
        /* Saldo Info */
        .saldo-info {
            text-align: center;
            padding: 30px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: var(--white);
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }
        
        .saldo-label {
            font-size: 1rem;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        
        .saldo-amount {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .saldo-note {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        /* Riwayat Styles */
        .riwayat-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .riwayat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary);
        }
        
        .riwayat-info {
            flex: 1;
        }
        
        .riwayat-amount {
            font-weight: 600;
            color: var(--primary);
        }
        
        .riwayat-meta {
            font-size: 0.8rem;
            color: var(--gray);
            margin-top: 5px;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        
        /* Notification Styles */
        .notification-badge {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7rem;
            margin-left: 5px;
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
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            nav ul {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .saldo-amount {
                font-size: 2rem;
            }
            
            .payment-methods {
                grid-template-columns: repeat(2, 1fr);
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
                    <h1>Bank Sampah - Tarik Saldo</h1>
                </div>
                <nav>
                    <ul>
                        <li><a href="dashboard-user.php">Dashboard</a></li>
                        <li><a href="withdraw.php" class="active">Tarik Saldo</a></li>
                        <li><a href="history.php">Riwayat</a></li>
                        <li><a href="history-penjemputan.php">Penjemputan</a></li>
                        <li>
                            <a href="#" onclick="openModal('notificationModal')">
                                Notifikasi
                                <?php if ($jumlah_notifikasi > 0): ?>
                                    <span class="notification-badge"><?php echo $jumlah_notifikasi; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li><a href="#" onclick="openModal('profileModal')">Profil</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-title">
                    <h1>Tarik Saldo</h1>
                    <p>Ajukan penarikan saldo dari tabungan sampah Anda</p>
                </div>
                <a href="dashboard-user.php" class="back-button">
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

            <div class="content-grid">
                <!-- Form Penarikan -->
                <div class="content-section">
                    <h2>Form Penarikan Saldo</h2>
                    
                    <div class="saldo-info">
                        <div class="saldo-label">Saldo Tersedia</div>
                        <div class="saldo-amount"><?php echo formatRupiah($user['saldo']); ?></div>
                        <div class="saldo-note">Saldo dapat ditarik kapan saja</div>
                    </div>

                    <form method="POST">
                        <div class="form-group">
                            <label for="jumlah">Jumlah Penarikan</label>
                            <input type="number" id="jumlah" name="jumlah" class="form-control" 
                                   min="1000" step="1000" 
                                   placeholder="Contoh: 50000" required>
                            <small style="color: var(--gray);">Minimal penarikan: Rp 1.000</small>
                        </div>

                        <div class="form-group">
                            <label>Pilih Metode Penarikan</label>
                            <div class="payment-methods">
                                <?php foreach ($metode_pembayaran as $metode): ?>
                                <div class="payment-method" onclick="selectPaymentMethod(<?php echo $metode['id']; ?>, '<?php echo $metode['nama_metode']; ?>')">
                                    <div class="payment-icon">
                                        <?php 
                                        $icons = [
                                            'gopay' => '💰',
                                            'ovo' => '📱',
                                            'dana' => '💳',
                                            'bank' => '🏦',
                                            'cash' => '💵'
                                        ];
                                        echo $icons[strtolower($metode['nama_metode'])] ?? '💳';
                                        ?>
                                    </div>
                                    <div class="payment-name"><?php echo htmlspecialchars($metode['nama_metode']); ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" id="metode_pembayaran" name="metode_pembayaran" required>
                        </div>

                        <div class="form-group">
                            <label for="nomor_tujuan">Nomor Tujuan</label>
                            <input type="text" id="nomor_tujuan" name="nomor_tujuan" class="form-control" 
                                   placeholder="Contoh: 081234567890" required>
                            <small style="color: var(--gray);">Masukkan nomor GoPay/OVO/Dana atau rekening bank</small>
                        </div>

                        <div class="form-group">
                            <label for="keterangan">Keterangan (Opsional)</label>
                            <textarea id="keterangan" name="keterangan" class="form-control" rows="3"
                                      placeholder="Alasan penarikan atau informasi tambahan..."></textarea>
                        </div>

                        <div class="alert" style="background: #fff3cd; color: #856404; border-color: #ffeaa7;">
                            <strong>📝 Informasi:</strong> Penarikan saldo membutuhkan persetujuan admin terlebih dahulu. Proses verifikasi biasanya memakan waktu 1-2 hari kerja.
                        </div>

                        <button type="submit" name="tarik_saldo" class="btn btn-primary btn-block">
                            💰 Ajukan Penarikan
                        </button>
                    </form>
                </div>

                <!-- Riwayat Penarikan -->
                <div class="content-section">
                    <h2>Riwayat Penarikan Terbaru</h2>
                    
                    <div class="riwayat-list">
                        <?php if (empty($riwayat_penarikan)): ?>
                            <div style="text-align: center; padding: 40px; color: var(--gray);">
                                <h3>📭 Belum ada penarikan</h3>
                                <p>Anda belum melakukan penarikan saldo.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($riwayat_penarikan as $penarikan): ?>
                            <div class="riwayat-item">
                                <div class="riwayat-info">
                                    <div class="riwayat-amount">
                                        <?php echo formatRupiah($penarikan['jumlah']); ?>
                                    </div>
                                    <div class="riwayat-meta">
                                        📅 <?php echo date('d/m/Y H:i', strtotime($penarikan['tanggal_penarikan'])); ?>
                                        • <?php echo htmlspecialchars($penarikan['metode_pembayaran'] ?? 'Transfer Bank'); ?>
                                        <?php if ($penarikan['keterangan']): ?>
                                            • 💬 <?php echo htmlspecialchars($penarikan['keterangan']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="status-badge status-<?php echo $penarikan['status']; ?>">
                                    <?php 
                                    $status_text = [
                                        'pending' => '⏳ Menunggu',
                                        'approved' => '✅ Disetujui', 
                                        'rejected' => '❌ Ditolak'
                                    ];
                                    echo $status_text[$penarikan['status']] ?? $penarikan['status'];
                                    ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($riwayat_penarikan)): ?>
                        <div style="text-align: center; margin-top: 20px;">
                            <a href="riwayat-penarikan.php" class="btn btn-outline">
                                📋 Lihat Semua Riwayat
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Informasi Tambahan -->
                    <div style="margin-top: 25px; padding: 20px; background: #e8f5e8; border-radius: var(--border-radius);">
                        <h4 style="color: var(--primary); margin-bottom: 10px;">💡 Informasi Penting:</h4>
                        <ul style="line-height: 1.6; color: var(--dark);">
                            <li>Penarikan akan diproses dalam 1-2 hari kerja</li>
                            <li>Pastikan nomor tujuan pembayaran sudah benar</li>
                            <li>Minimal penarikan adalah Rp 1.000</li>
                            <li>Saldo tidak bisa ditarik jika masih dalam proses verifikasi</li>
                            <li>Biaya administrasi mungkin berlaku tergantung metode</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- MODAL NOTIFIKASI -->
    <div id="notificationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">🔔 Notifikasi</h2>
                <span class="close" onclick="closeModal('notificationModal')">&times;</span>
            </div>
            <div style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($notifikasi)): ?>
                    <div style="text-align: center; padding: 20px; color: var(--gray);">
                        📭 Tidak ada notifikasi
                    </div>
                <?php else: ?>
                    <?php foreach ($notifikasi as $notif): ?>
                    <div class="notification-item <?php echo $notif['dibaca'] === 'no' ? 'unread' : ''; ?>" 
                         onclick="markAsRead(<?php echo $notif['id']; ?>)">
                        <div class="notification-title"><?php echo htmlspecialchars($notif['judul']); ?></div>
                        <div class="notification-message"><?php echo htmlspecialchars($notif['pesan']); ?></div>
                        <div class="notification-time">
                            📅 <?php echo date('d/m/Y H:i', strtotime($notif['created_at'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Bank Sampah. All rights reserved.</p>
        </div>
    </footer>

    <style>
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
        
        .notification-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .notification-item:hover {
            background-color: #f8f9fa;
        }
        
        .notification-item.unread {
            background-color: #e8f5e8;
            border-left: 3px solid var(--primary);
        }
        
        .notification-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .notification-message {
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .notification-time {
            font-size: 0.8rem;
            color: var(--gray);
        }
    </style>

    <script>
        // MODAL FUNCTIONS
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // TUTUP MODAL JIKA KLIK DI LUAR
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let modal of modals) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            }
        }
        
        // MARK NOTIFICATION AS READ
        function markAsRead(notifId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'notif_id';
            input.value = notifId;
            
            const submit = document.createElement('input');
            submit.type = 'hidden';
            submit.name = 'baca_notifikasi';
            submit.value = '1';
            
            form.appendChild(input);
            form.appendChild(submit);
            document.body.appendChild(form);
            form.submit();
        }
        
        // SELECT PAYMENT METHOD
        let selectedMethod = null;
        
        function selectPaymentMethod(methodId, methodName) {
            // Remove selected class from all methods
            document.querySelectorAll('.payment-method').forEach(method => {
                method.classList.remove('selected');
            });
            
            // Add selected class to clicked method
            event.currentTarget.classList.add('selected');
            
            // Set hidden input value
            document.getElementById('metode_pembayaran').value = methodId;
            
            // Update placeholder based on method
            const nomorTujuan = document.getElementById('nomor_tujuan');
            if (methodName.toLowerCase().includes('gopay') || methodName.toLowerCase().includes('ovo') || methodName.toLowerCase().includes('dana')) {
                nomorTujuan.placeholder = 'Contoh: 081234567890';
            } else if (methodName.toLowerCase().includes('bank')) {
                nomorTujuan.placeholder = 'Contoh: 1234567890 (Nomor Rekening)';
            } else {
                nomorTujuan.placeholder = 'Masukkan nomor tujuan';
            }
            
            selectedMethod = methodId;
        }
        
        // AUTO HIDE ALERT
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);

        // VALIDASI FORM
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const jumlahInput = document.getElementById('jumlah');
            const metodeInput = document.getElementById('metode_pembayaran');
            const nomorTujuanInput = document.getElementById('nomor_tujuan');
            const saldo = <?php echo $user['saldo']; ?>;
            
            if (form && jumlahInput) {
                form.addEventListener('submit', function(e) {
                    const jumlah = parseFloat(jumlahInput.value);
                    
                    if (jumlah < 1000) {
                        e.preventDefault();
                        alert('❌ Minimal penarikan adalah Rp 1.000');
                        jumlahInput.focus();
                        return false;
                    }
                    
                    if (jumlah > saldo) {
                        e.preventDefault();
                        alert('❌ Saldo tidak mencukupi. Saldo tersedia: <?php echo formatRupiah($user['saldo']); ?>');
                        jumlahInput.focus();
                        return false;
                    }
                    
                    if (!metodeInput.value) {
                        e.preventDefault();
                        alert('❌ Pilih metode pembayaran terlebih dahulu');
                        return false;
                    }
                    
                    if (!nomorTujuanInput.value.trim()) {
                        e.preventDefault();
                        alert('❌ Masukkan nomor tujuan pembayaran');
                        nomorTujuanInput.focus();
                        return false;
                    }
                });
            }
        });
    </script>
</body>
</html>