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

// AMBIL DATA PENGUMUMAN
try {
    // Query untuk mengambil pengumuman untuk mitra (tipe 'mitra' atau 'all')
    $sql = "
        SELECT 
            n.*,
            u.nama_lengkap as pengirim
        FROM notifikasi n 
        LEFT JOIN users u ON n.user_id = u.id 
        WHERE (n.tipe = 'mitra' OR n.tipe = 'all')
        AND (n.mitra_id = ? OR n.mitra_id IS NULL)
        ORDER BY n.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$mitra_id]);
    $pengumuman = $stmt->fetchAll();
    
    // Tandai notifikasi sebagai sudah dibaca
    $update_sql = "UPDATE notifikasi SET dibaca = 'yes' WHERE (mitra_id = ? OR mitra_id IS NULL) AND (tipe = 'mitra' OR tipe = 'all')";
    $stmt = $pdo->prepare($update_sql);
    $stmt->execute([$mitra_id]);
    
} catch (PDOException $e) {
    error_log("Pengumuman Mitra Error: " . $e->getMessage());
    $error = "Terjadi kesalahan saat mengambil data: " . $e->getMessage();
    $pengumuman = [];
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
    <title>Pengumuman - Bank Sampah</title>
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
        
        /* Stats Cards */
        .stats-cards {
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
        
        .stat-card.unread { border-left-color: var(--info); }
        .stat-card.total { border-left-color: var(--primary); }
        
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
        
        /* Pengumuman List */
        .pengumuman-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .pengumuman-card {
            background: var(--white);
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary);
            transition: all 0.3s;
        }

        .pengumuman-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        
        .pengumuman-card.unread {
            border-left-color: var(--info);
            background: #f8fdff;
        }
        
        .pengumuman-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .pengumuman-title {
            font-size: 1.2rem;
            color: var(--dark);
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .pengumuman-meta {
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .pengumuman-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-new {
            background: var(--info);
            color: white;
        }
        
        .badge-important {
            background: var(--warning);
            color: var(--dark);
        }
        
        .pengumuman-content {
            color: var(--dark);
            line-height: 1.6;
        }
        
        .pengumuman-footer {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray);
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: var(--gray);
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
            
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .pengumuman-header {
                flex-direction: column;
                gap: 10px;
            }
            
            nav ul {
                flex-wrap: wrap;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .pengumuman-card {
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
                    <h1>Bank Sampah - Pengumuman</h1>
                </div>
                <nav>
                    <ul>
                        <li><a href="dashboard-mitra.php">Dashboard</a></li>
                        <li><a href="manage-penjemputan.php">Penjemputan</a></li>
                        <li><a href="manage-jadwal.php">Jadwal</a></li>
                        <li><a href="riwayat-mitra.php">Riwayat</a></li>
                        <li><a href="pengumuman-mitra.php" class="active">Pengumuman</a></li>
                        <li><a href="feedback-mitra.php">Feedback</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
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

            <!-- Page Header -->
            <div class="page-header">
                <div class="page-title">
                    <h1>📢 Pengumuman & Pemberitahuan</h1>
                    <p>Lihat semua pengumuman terbaru dari sistem Bank Sampah</p>
                </div>
            </div>

            <!-- Stats Cards -->
            <section class="stats-cards">
                <div class="stat-card total">
                    <h3>📊 Total Pengumuman</h3>
                    <div class="number"><?php echo count($pengumuman); ?></div>
                </div>
                <div class="stat-card unread">
                    <h3>🆕 Belum Dibaca</h3>
                    <div class="number">
                        <?php 
                            $unread_count = 0;
                            foreach ($pengumuman as $item) {
                                if ($item['dibaca'] === 'no') $unread_count++;
                            }
                            echo $unread_count;
                        ?>
                    </div>
                </div>
            </section>

            <!-- Pengumuman List -->
            <section class="pengumuman-list">
                <?php if (empty($pengumuman)): ?>
                    <div class="empty-state">
                        <h3>🎉 Tidak ada pengumuman</h3>
                        <p>Belum ada pengumuman atau pemberitahuan untuk Anda.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pengumuman as $item): ?>
                    <div class="pengumuman-card <?php echo $item['dibaca'] === 'no' ? 'unread' : ''; ?>">
                        <div class="pengumuman-header">
                            <div>
                                <div class="pengumuman-title">
                                    <?php echo htmlspecialchars($item['judul']); ?>
                                    <?php if ($item['dibaca'] === 'no'): ?>
                                        <span class="pengumuman-badge badge-new">BARU</span>
                                    <?php endif; ?>
                                </div>
                                <div class="pengumuman-meta">
                                    📅 <?php echo date('d F Y H:i', strtotime($item['created_at'])); ?>
                                    <?php if ($item['pengirim']): ?>
                                         • 👤 Oleh: <?php echo htmlspecialchars($item['pengirim']); ?>
                                    <?php else: ?>
                                         • 👤 Oleh: Sistem
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="pengumuman-content">
                            <?php echo nl2br(htmlspecialchars($item['pesan'])); ?>
                        </div>
                        
                        <div class="pengumuman-footer">
                            📎 Tipe: <strong><?php echo strtoupper($item['tipe']); ?></strong>
                        </div>
                    </div>
                    <?php endforeach; ?>
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