<?php
require_once 'config.php';

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

// UPDATE SESSION SALDO
$_SESSION['saldo'] = $user['saldo'];

// AMBIL STATISTIK USER
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(berat), 0) as total_sampah,
        COUNT(*) as total_transaksi
    FROM transaksi 
    WHERE user_id = ?
");
$stmt->execute([$user_id]);
$stats = $stmt->fetch();

// AMBIL TRANSAKSI USER (5 TERBARU)
$stmt = $pdo->prepare("
    SELECT t.*, j.nama_jenis, j.harga_per_kg 
    FROM transaksi t 
    JOIN jenis_sampah j ON t.jenis_sampah_id = j.id 
    WHERE t.user_id = ? 
    ORDER BY t.tanggal_transaksi DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$transaksi = $stmt->fetchAll();

// AMBIL JENIS SAMPAH
$stmt = $pdo->query("SELECT * FROM jenis_sampah ORDER BY nama_jenis");
$jenis_sampah = $stmt->fetchAll();

// AMBIL MITRA TERVERIFIKASI
$stmt = $pdo->query("SELECT id, nama_mitra FROM mitra WHERE status_verifikasi = 'verified'");
$mitra_list = $stmt->fetchAll();

// AMBIL SEMUA PENJEMPUTAN USER (SETOR & JEMPUT)
$stmt = $pdo->prepare("
    SELECT p.*, m.nama_mitra 
    FROM penjemputan p 
    LEFT JOIN mitra m ON p.id_mitra = m.id 
    WHERE p.id_warga = ? 
    ORDER BY p.waktu_pemintaan DESC 
    LIMIT 10
");
$stmt->execute([$user_id]);
$penjemputan = $stmt->fetchAll();

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
$jumlah_notifikasi = $stmt->fetch()['jumlah_belum_dibaca'];

// PROSES KIRIM SAMPAH (GABUNGAN SETOR & PENJEMPUTAN)
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // KIRIM SAMPAH (SETOR ATAU JEMPUT)
    if (isset($_POST['kirim_sampah'])) {
        $jenis_sampah = sanitize($_POST['jenis_sampah']);
        $berat = (float)$_POST['berat'];
        $tipe_layanan = sanitize($_POST['tipe_layanan']); // 'setor' atau 'jemput'
        $alamat_penjemputan = sanitize($_POST['alamat_penjemputan'] ?? '');
        $id_mitra = !empty($_POST['id_mitra']) ? (int)$_POST['id_mitra'] : null;
        $keterangan = sanitize($_POST['keterangan'] ?? '');
        
        // VALIDASI INPUT
        if (empty($jenis_sampah)) {
            $error = "Jenis sampah harus diisi";
        } elseif ($berat <= 0) {
            $error = "Berat sampah harus lebih dari 0";
        } elseif ($tipe_layanan === 'jemput' && empty($alamat_penjemputan)) {
            $error = "Alamat penjemputan harus diisi untuk layanan jemput";
        } else {
            try {
                // TENTUKAN STATUS BERDASARKAN TIPE LAYANAN
                $status = ($tipe_layanan === 'setor') ? 'diproses' : 'pending';
                
                // BUAT KETERANGAN
                $keterangan_full = "Layanan: " . ($tipe_layanan === 'setor' ? 'Setor Langsung' : 'Penjemputan');
                if (!empty($keterangan)) {
                    $keterangan_full .= " | " . $keterangan;
                }
                if ($tipe_layanan === 'setor') {
                    $keterangan_full .= " | Berat: " . number_format($berat, 2) . " kg";
                }
                
                // SIMPAN KE PENJEMPUTAN
                $stmt = $pdo->prepare("
                    INSERT INTO penjemputan (id_warga, id_mitra, alamat_penjemputan, jenis_sampah, keterangan, status) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([$user_id, $id_mitra, $alamat_penjemputan, $jenis_sampah, $keterangan_full, $status]);
                
                // BUAT NOTIFIKASI
                $judul_notifikasi = ($tipe_layanan === 'setor') ? 'Setor Sampah Dijadwalkan' : 'Penjemputan Dijadwalkan';
                $pesan_notifikasi = ($tipe_layanan === 'setor') 
                    ? "Permintaan setor sampah $jenis_sampah seberat " . number_format($berat, 2) . " kg telah diajukan. Tunggu verifikasi mitra."
                    : "Permintaan penjemputan sampah ($jenis_sampah) berhasil diajukan. Mitra akan segera menghubungi Anda.";
                
                $stmt = $pdo->prepare("
                    INSERT INTO notifikasi (user_id, judul, pesan) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$user_id, $judul_notifikasi, $pesan_notifikasi]);
                
                $success = ($tipe_layanan === 'setor')
                    ? "Permintaan setor sampah berhasil diajukan! Tunggu verifikasi dari mitra untuk mendapatkan saldo."
                    : "Permintaan penjemputan berhasil diajukan! Mitra akan segera menghubungi Anda.";
                
                // RELOAD DATA TERBARU
                header("Location: dashboard-user.php?success=" . urlencode($success));
                exit();
                
            } catch (Exception $e) {
                $error = "Terjadi kesalahan: " . $e->getMessage();
            }
        }
    }
    
    // UPDATE PROFIL
    if (isset($_POST['update_profil'])) {
        $nama_lengkap = sanitize($_POST['nama_lengkap']);
        $email = sanitize($_POST['email']);
        $telepon = sanitize($_POST['telepon']);
        $alamat = sanitize($_POST['alamat']);
        
        // VALIDASI EMAIL
        if ($email !== $user['email']) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $error = "Email sudah digunakan oleh user lain";
            }
        }
        
        if (!$error) {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET nama_lengkap = ?, email = ?, telepon = ?, alamat = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            
            if ($stmt->execute([$nama_lengkap, $email, $telepon, $alamat, $user_id])) {
                $_SESSION['nama_lengkap'] = $nama_lengkap;
                $user['nama_lengkap'] = $nama_lengkap;
                $user['email'] = $email;
                $user['telepon'] = $telepon;
                $user['alamat'] = $alamat;
                
                $success = "Profil berhasil diperbarui!";
            } else {
                $error = "Gagal memperbarui profil";
            }
        }
    }
    
    // TANDAI NOTIFIKASI SUDAH DIBACA
    if (isset($_POST['baca_notifikasi'])) {
        $notif_id = (int)$_POST['notif_id'];
        $stmt = $pdo->prepare("UPDATE notifikasi SET dibaca = 'yes' WHERE id = ? AND user_id = ?");
        $stmt->execute([$notif_id, $user_id]);
        
        if ($jumlah_notifikasi > 0) {
            $jumlah_notifikasi--;
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
    <title>Dashboard Nasabah - Bank Sampah</title>
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
            display: flex;
            align-items: center;
            gap: 5px;
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
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            background: var(--white);
            padding: 15px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .user-details .username {
            font-weight: 600;
            color: var(--dark);
        }
        
        .user-details .role {
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--white);
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 15px;
            border-left: 4px solid var(--primary);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            color: var(--primary);
        }
        
        .stat-info h3 {
            margin: 0 0 8px 0;
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        /* Dashboard Content */
        .dashboard-content {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        /* Tab Navigation */
        .tab-container {
            display: flex;
            background: var(--white);
            border-bottom: 1px solid #e0e0e0;
        }
        
        .tab {
            padding: 15px 25px;
            cursor: pointer;
            border: none;
            background: transparent;
            font-weight: 500;
            color: var(--gray);
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }
        
        .tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            background: #f8f9fa;
        }
        
        .tab:hover {
            color: var(--primary);
            background: #f8f9fa;
        }
        
        .tab-content {
            display: none;
            padding: 30px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Content Grid */
        .content-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .content-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary);
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
        
        /* Table Styles */
        .table-container {
            background: var(--white);
            border-radius: var(--border-radius);
            overflow: hidden;
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
        
        /* Status Badges */
        .status-badge {
            padding: 5px 10px;
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
        
        /* Penjemputan Items */
        .penjemputan-item {
            background: var(--white);
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 10px;
            border-left: 4px solid var(--primary);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: box-shadow 0.3s;
        }
        
        .penjemputan-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        
        .penjemputan-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .penjemputan-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: var(--gray);
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
            max-height: 80vh;
            overflow-y: auto;
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
        
        .profile-form {
            padding: 20px;
        }
        
        /* Notification Styles */
        .notification-badge {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7rem;
            margin-left: 5px;
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
        
        .no-notifications {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }
        
        /* Info Box Styles */
        .info-box {
            background: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            border-left: 4px solid var(--primary);
        }
        
        .info-box.warning {
            border-left-color: var(--warning);
            background: #fff3cd;
        }
        
        .info-box.success {
            border-left-color: var(--success);
            background: #d4edda;
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
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .content-row {
                grid-template-columns: 1fr;
            }
            
            .tab-container {
                flex-direction: column;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
            
            nav ul {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .penjemputan-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .penjemputan-meta {
                flex-direction: column;
                gap: 5px;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .tab {
                padding: 12px 15px;
                font-size: 0.9rem;
            }
            
            .tab-content {
                padding: 20px;
            }
            
            .content-section {
                padding: 20px;
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
                    <h1>Bank Sampah</h1>
                </div>
                <nav>
                    <ul>
                        <li><a href="dashboard-user.php" class="active"> Dashboard</a></li>
                        <li><a href="withdraw.php"> Tarik Saldo</a></li>
                        <li><a href="history.php"> Riwayat</a></li>
                        <li><a href="history-penjemputan.php"> Penjemputan</a></li>
                        <li>
                            <a href="#" onclick="openModal('notificationModal')">
                                 Notifikasi
                                <?php if ($jumlah_notifikasi > 0): ?>
                                    <span class="notification-badge"><?php echo $jumlah_notifikasi; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li><a href="#" onclick="openModal('profileModal')"> Profil</a></li>
                        <li><a href="logout.php"> Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <section class="dashboard">
            <div class="container">
                <!-- ALERT MESSAGES -->
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

                <!-- DASHBOARD HEADER -->
                <div class="dashboard-header">
                    <div class="dashboard-title">
                        <h1>Dashboard Nasabah</h1>
                        <p>Selamat datang, <?php echo htmlspecialchars($user['nama_lengkap']); ?>!</p>
                    </div>
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user['nama_lengkap'], 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <div class="username"><?php echo htmlspecialchars($user['nama_lengkap']); ?></div>
                            <div class="role">Nasabah</div>
                        </div>
                    </div>
                </div>

                <!-- STATS GRID -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">💰</div>
                        <div class="stat-info">
                            <h3>Saldo Saat Ini</h3>
                            <div class="stat-number"><?php echo formatRupiah($user['saldo']); ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">📦</div>
                        <div class="stat-info">
                            <h3>Total Sampah</h3>
                            <div class="stat-number"><?php echo number_format($stats['total_sampah'], 2); ?> kg</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">📊</div>
                        <div class="stat-info">
                            <h3>Total Transaksi</h3>
                            <div class="stat-number"><?php echo $stats['total_transaksi']; ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">🔄</div>
                        <div class="stat-info">
                            <h3>Permintaan Aktif</h3>
                            <div class="stat-number"><?php echo count($penjemputan); ?></div>
                            <small><?php echo count(array_filter($penjemputan, fn($p) => in_array($p['status'], ['pending', 'diproses']))); ?> aktif</small>
                        </div>
                    </div>
                </div>

                <!-- DASHBOARD CONTENT -->
                <div class="dashboard-content">
                    <!-- TAB NAVIGATION -->
                    <div class="tab-container">
                        <button class="tab active" onclick="switchTab('kirim-tab')">📤 Kirim Sampah</button>
                        <button class="tab" onclick="switchTab('riwayat-tab')">📋 Riwayat & Status</button>
                    </div>

                    <!-- TAB 1: KIRIM SAMPAH -->
                    <div id="kirim-tab" class="tab-content active">
                        <div class="content-row">
                            <!-- FORM KIRIM SAMPAH -->
                            <div class="content-section">
                                <h2>Kirim Sampah</h2>
                                <form method="POST" class="deposit-form" id="kirimSampahForm">
                                    <!-- PILIH TIPE LAYANAN -->
                                    <div class="form-group">
                                        <label for="tipe_layanan">Pilih Layanan *</label>
                                        <select id="tipe_layanan" name="tipe_layanan" class="form-control" required onchange="toggleLayanan()">
                                            <option value="">Pilih Jenis Layanan</option>
                                            <option value="setor">🏦 Setor Langsung ke Bank Sampah</option>
                                            <option value="jemput">🚚 Penjemputan di Rumah</option>
                                        </select>
                                    </div>

                                    <!-- JENIS SAMPAH -->
                                    <div class="form-group">
                                        <label for="jenis_sampah">Jenis Sampah *</label>
                                        <select id="jenis_sampah" name="jenis_sampah" class="form-control" required>
                                            <option value="">Pilih Jenis Sampah</option>
                                            <?php foreach ($jenis_sampah as $jenis): ?>
                                            <option value="<?php echo htmlspecialchars($jenis['nama_jenis']); ?>" 
                                                    data-harga="<?php echo $jenis['harga_per_kg']; ?>">
                                                <?php echo htmlspecialchars($jenis['nama_jenis']); ?> - <?php echo formatRupiah($jenis['harga_per_kg']); ?>/kg
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <!-- BERAT SAMPAH -->
                                    <div class="form-group">
                                        <label for="berat">Berat Sampah (kg) *</label>
                                        <input type="number" id="berat" name="berat" class="form-control" 
                                               min="0.1" step="0.1" placeholder="0.0" required>
                                    </div>

                                    <!-- ALAMAT PENJEMPUTAN (HANYA UNTUK JEMPUT) -->
                                    <div class="form-group" id="alamatGroup" style="display: none;">
                                        <label for="alamat_penjemputan">Alamat Penjemputan *</label>
                                        <textarea id="alamat_penjemputan" name="alamat_penjemputan" 
                                                  class="form-control" rows="3" 
                                                  placeholder="Alamat lengkap untuk penjemputan"><?php echo htmlspecialchars($user['alamat'] ?? ''); ?></textarea>
                                        <small style="color: var(--gray);">*Wajib diisi untuk layanan penjemputan</small>
                                    </div>

                                    <!-- PILIH MITRA (OPSIONAL) -->
                                    <div class="form-group" id="mitraGroup" style="display: none;">
                                        <label for="id_mitra">Pilih Mitra (Opsional)</label>
                                        <select id="id_mitra" name="id_mitra" class="form-control">
                                            <option value="">-- Pilih Mitra --</option>
                                            <?php foreach ($mitra_list as $mitra): ?>
                                            <option value="<?php echo $mitra['id']; ?>">
                                                <?php echo htmlspecialchars($mitra['nama_mitra']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small style="color: var(--gray);">Biarkan kosong untuk ditugaskan otomatis</small>
                                    </div>

                                    <!-- KETERANGAN -->
                                    <div class="form-group">
                                        <label for="keterangan">Keterangan Tambahan</label>
                                        <textarea id="keterangan" name="keterangan" class="form-control" rows="2"
                                                  placeholder="Catatan khusus (opsional)"></textarea>
                                    </div>

                                    <!-- ESTIMASI -->
                                    <div class="form-group">
                                        <label>Estimasi Pendapatan:</label>
                                        <div id="total-estimasi" style="font-size: 1.2rem; font-weight: bold; color: var(--primary);">
                                            Rp 0
                                        </div>
                                        <small style="color: var(--gray);">*Estimasi ini akan diverifikasi oleh mitra</small>
                                    </div>

                                    <button type="submit" name="kirim_sampah" class="btn btn-primary btn-block">
                                        📤 Ajukan Permintaan
                                    </button>
                                </form>
                            </div>

                            <!-- INFO LAYANAN -->
                            <div class="content-section">
                                <h2>Informasi Layanan</h2>
                                <div class="info-box success">
                                    <h4 style="color: var(--primary); margin-bottom: 10px;">🏦 Setor Langsung</h4>
                                    <p style="line-height: 1.6; margin-bottom: 10px;">
                                        <strong>Bawa sampah langsung ke bank sampah</strong>
                                    </p>
                                    <ul style="line-height: 1.6; color: var(--gray); padding-left: 20px;">
                                        <li>Status: Langsung diproses</li>
                                        <li>Verifikasi: Cepat oleh mitra</li>
                                        <li>Saldo: Langsung bertambah setelah verifikasi</li>
                                        <li>Waktu: Sesuai jam operasional bank sampah</li>
                                    </ul>
                                </div>

                                <div class="info-box warning">
                                    <h4 style="color: var(--secondary); margin-bottom: 10px;">🚚 Penjemputan di Rumah</h4>
                                    <p style="line-height: 1.6; margin-bottom: 10px;">
                                        <strong>Mitra menjemput sampah ke rumah Anda</strong>
                                    </p>
                                    <ul style="line-height: 1.6; color: var(--gray); padding-left: 20px;">
                                        <li>Status: Menunggu konfirmasi mitra</li>
                                        <li>Verifikasi: Saat penjemputan</li>
                                        <li>Saldo: Bertambah setelah penjemputan selesai</li>
                                        <li>Waktu: Dijadwalkan dengan mitra</li>
                                    </ul>
                                </div>

                                <div class="info-box">
                                    <h4 style="color: var(--primary); margin-bottom: 10px;">💡 Tips:</h4>
                                    <ul style="line-height: 1.6; padding-left: 20px;">
                                        <li>Pisahkan sampah berdasarkan jenisnya</li>
                                        <li>Pastikan sampah bersih dan kering</li>
                                        <li>Untuk penjemputan, pastikan alamat jelas</li>
                                        <li>Simpan bukti transaksi untuk referensi</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: RIWAYAT & STATUS -->
                    <div id="riwayat-tab" class="tab-content">
                        <div class="content-row">
                            <!-- STATUS PERMINTAAN TERKINI -->
                            <div class="content-section">
                                <h2>Status Permintaan Terkini</h2>
                                <div style="max-height: 400px; overflow-y: auto;">
                                    <?php if (empty($penjemputan)): ?>
                                        <div style="text-align: center; padding: 20px; color: var(--gray);">
                                            📭 Belum ada permintaan pengiriman sampah
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($penjemputan as $p): 
                                            $is_setor_langsung = (strpos($p['keterangan'] ?? '', 'Setor Langsung') !== false);
                                        ?>
                                        <div class="penjemputan-item">
                                            <div class="penjemputan-header">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($p['jenis_sampah']); ?></strong>
                                                    <div style="font-size: 0.8rem; color: var(--gray); margin-top: 2px;">
                                                        <?php echo $is_setor_langsung ? '🏦 Setor Langsung' : '🚚 Penjemputan'; ?>
                                                    </div>
                                                </div>
                                                <span class="status-badge status-<?php echo $p['status']; ?>">
                                                    <?php 
                                                    $status_text = [
                                                        'pending' => '⏳ Menunggu',
                                                        'dijadwalkan' => '📅 Dijadwalkan', 
                                                        'diproses' => '🔄 Diproses',
                                                        'selesai' => '✅ Selesai',
                                                        'ditolak' => '❌ Ditolak'
                                                    ];
                                                    echo $status_text[$p['status']] ?? $p['status'];
                                                    ?>
                                                </span>
                                            </div>
                                            <div class="penjemputan-meta">
                                                <span>
                                                    <?php if ($p['nama_mitra']): ?>
                                                        Mitra: <?php echo htmlspecialchars($p['nama_mitra']); ?>
                                                    <?php elseif ($is_setor_langsung): ?>
                                                        Menunggu verifikasi mitra
                                                    <?php else: ?>
                                                        Menunggu penugasan mitra
                                                    <?php endif; ?>
                                                </span>
                                                <span><?php echo date('d/m/Y H:i', strtotime($p['waktu_pemintaan'])); ?></span>
                                            </div>
                                            <?php if ($p['keterangan']): ?>
                                                <div style="margin-top: 8px; font-size: 0.9rem; color: var(--gray);">
                                                    <?php echo htmlspecialchars($p['keterangan']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($penjemputan)): ?>
                                    <div style="text-align: center; margin-top: 15px;">
                                        <a href="history-penjemputan.php" class="btn btn-outline">
                                            📋 Lihat Semua Riwayat
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- RIWAYAT TRANSAKSI -->
                            <div class="content-section">
                                <h2>Riwayat Transaksi</h2>
                                <div class="table-container">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>Jenis Sampah</th>
                                                <th>Berat</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($transaksi)): ?>
                                                <tr>
                                                    <td colspan="4" style="text-align: center; color: var(--gray);">
                                                        📭 Belum ada transaksi
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($transaksi as $trx): ?>
                                                <tr>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($trx['tanggal_transaksi'])); ?></td>
                                                    <td><?php echo htmlspecialchars($trx['nama_jenis']); ?></td>
                                                    <td><?php echo number_format($trx['berat'], 2); ?> kg</td>
                                                    <td><?php echo formatRupiah($trx['total_harga']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                    <?php if (!empty($transaksi)): ?>
                                        <div style="text-align: center; margin-top: 15px;">
                                            <a href="history.php" class="btn btn-outline">
                                                📊 Lihat Semua Transaksi
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- MODAL NOTIFIKASI -->
    <div id="notificationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">🔔 Notifikasi</h2>
                <button type="button" class="close" onclick="closeModal('notificationModal')">&times;</button>
            </div>
            <div class="modal-body">
                <?php if (empty($notifikasi)): ?>
                    <div class="no-notifications">
                        <div style="font-size: 3rem; margin-bottom: 15px;">📭</div>
                        <h3>Tidak ada notifikasi</h3>
                        <p>Belum ada notifikasi untuk Anda saat ini.</p>
                    </div>
                <?php else: ?>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($notifikasi as $notif): ?>
                            <div class="notification-item <?php echo $notif['dibaca'] === 'no' ? 'unread' : ''; ?>" 
                                 onclick="markAsRead(<?php echo $notif['id']; ?>, this)">
                                <div class="notification-title">
                                    <?php echo htmlspecialchars($notif['judul']); ?>
                                    <?php if ($notif['dibaca'] === 'no'): ?>
                                        <span style="color: var(--primary); font-size: 0.7rem;">● Baru</span>
                                    <?php endif; ?>
                                </div>
                                <div class="notification-message">
                                    <?php echo htmlspecialchars($notif['pesan']); ?>
                                </div>
                                <div class="notification-time">
                                    <?php echo date('d/m/Y H:i', strtotime($notif['created_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="notifications.php" class="btn btn-outline">Lihat Semua Notifikasi</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- MODAL PROFIL -->
    <div id="profileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">👤 Profil Saya</h2>
                <button type="button" class="close" onclick="closeModal('profileModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" class="profile-form">
                    <div class="form-group">
                        <label for="nama_lengkap">Nama Lengkap</label>
                        <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control" 
                               value="<?php echo htmlspecialchars($user['nama_lengkap']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="telepon">Telepon</label>
                        <input type="tel" id="telepon" name="telepon" class="form-control" 
                               value="<?php echo htmlspecialchars($user['telepon']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="alamat">Alamat</label>
                        <textarea id="alamat" name="alamat" class="form-control" rows="3"><?php echo htmlspecialchars($user['alamat']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Saldo Saat Ini</label>
                        <div style="font-size: 1.2rem; font-weight: bold; color: var(--primary);">
                            <?php echo formatRupiah($user['saldo']); ?>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" name="update_profil" class="btn btn-primary" style="flex: 1;">
                            💾 Update Profil
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeModal('profileModal')">
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
        // MODAL FUNCTIONS
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // MARK NOTIFICATION AS READ
        function markAsRead(notifId, element) {
            const formData = new FormData();
            formData.append('baca_notifikasi', '1');
            formData.append('notif_id', notifId);
            
            fetch('dashboard-user.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                element.classList.remove('unread');
                const badge = document.querySelector('.notification-badge');
                if (badge) {
                    const currentCount = parseInt(badge.textContent);
                    if (currentCount > 1) {
                        badge.textContent = currentCount - 1;
                    } else {
                        badge.remove();
                    }
                }
                
                const newIndicator = element.querySelector('span');
                if (newIndicator) {
                    newIndicator.remove();
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        // TOGGLE FORM BERDASARKAN JENIS LAYANAN
        function toggleLayanan() {
            const tipeLayanan = document.getElementById('tipe_layanan').value;
            const alamatGroup = document.getElementById('alamatGroup');
            const mitraGroup = document.getElementById('mitraGroup');
            
            if (tipeLayanan === 'jemput') {
                alamatGroup.style.display = 'block';
                mitraGroup.style.display = 'block';
                document.getElementById('alamat_penjemputan').required = true;
            } else {
                alamatGroup.style.display = 'none';
                mitraGroup.style.display = 'none';
                document.getElementById('alamat_penjemputan').required = false;
            }
        }

        // KALKULASI TOTAL ESTIMASI
        function calculateTotal() {
            const jenisSampah = document.getElementById('jenis_sampah');
            const beratInput = document.getElementById('berat');
            const totalEstimasi = document.getElementById('total-estimasi');
            
            if (!jenisSampah || !beratInput || !totalEstimasi) return;
            
            const selectedOption = jenisSampah.options[jenisSampah.selectedIndex];
            const hargaPerKg = selectedOption.getAttribute('data-harga');
            const berat = parseFloat(beratInput.value) || 0;
            
            if (hargaPerKg && berat > 0) {
                const total = hargaPerKg * berat;
                totalEstimasi.textContent = 'Rp ' + total.toLocaleString('id-ID');
            } else {
                totalEstimasi.textContent = 'Rp 0';
            }
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

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    if (modal.style.display === 'block') {
                        closeModal(modal.id);
                    }
                });
            }
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            const jenisSampah = document.getElementById('jenis_sampah');
            const beratInput = document.getElementById('berat');
            
            if (jenisSampah && beratInput) {
                jenisSampah.addEventListener('change', calculateTotal);
                beratInput.addEventListener('input', calculateTotal);
            }
            
            // Auto refresh setiap 30 detik
            setInterval(() => {
                location.reload();
            }, 30000);
        });
        
        // TAB FUNCTIONS
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>