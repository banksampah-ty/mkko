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

// HANDLE FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jenis_feedback = $_POST['jenis_feedback'] ?? '';
    $prioritas = $_POST['prioritas'] ?? 'medium';
    $judul = $_POST['judul'] ?? '';
    $pesan = $_POST['pesan'] ?? '';
    
    // VALIDASI
    if (empty($jenis_feedback) || empty($judul) || empty($pesan)) {
        $error = "Semua field wajib diisi!";
    } else {
        try {
            // SIMPAN FEEDBACK KE DATABASE
            $sql = "INSERT INTO feedback_mitra (mitra_id, jenis_feedback, prioritas, judul, pesan, status) VALUES (?, ?, ?, ?, ?, 'pending')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$mitra_id, $jenis_feedback, $prioritas, $judul, $pesan]);
            
            $success = "Feedback berhasil dikirim! Terima kasih atas masukan Anda.";
            
            // Reset form
            $_POST = [];
            
        } catch (PDOException $e) {
            error_log("Feedback Mitra Error: " . $e->getMessage());
            $error = "Terjadi kesalahan saat mengirim feedback: " . $e->getMessage();
        }
    }
}

// AMBIL RIWAYAT FEEDBACK
try {
    $sql = "
        SELECT * FROM feedback_mitra 
        WHERE mitra_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$mitra_id]);
    $riwayat_feedback = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Riwayat Feedback Error: " . $e->getMessage());
    $riwayat_feedback = [];
}

// BUAT TABEL JIKA BELUM ADA
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS feedback_mitra (
            id INT PRIMARY KEY AUTO_INCREMENT,
            mitra_id INT NOT NULL,
            jenis_feedback VARCHAR(50) NOT NULL,
            prioritas ENUM('low', 'medium', 'high') DEFAULT 'medium',
            judul VARCHAR(255) NOT NULL,
            pesan TEXT NOT NULL,
            status ENUM('pending', 'dibaca', 'diproses', 'selesai') DEFAULT 'pending',
            tanggapan TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (mitra_id) REFERENCES mitra(id) ON DELETE CASCADE
        )
    ");
} catch (PDOException $e) {
    error_log("Create table feedback_mitra error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - Bank Sampah</title>
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
        
        /* Form Styles */
        .form-section {
            background: var(--white);
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 40px;
        }
        
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
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: var(--primary);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background: var(--primary-light);
        }
        
        .btn-block {
            width: 100%;
        }
        
        /* Priority Badges */
        .priority-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .priority-low { background: #d4edda; color: #155724; }
        .priority-medium { background: #fff3cd; color: #856404; }
        .priority-high { background: #f8d7da; color: #721c24; }
        
        /* Status Badges */
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-dibaca { background: #d1ecf1; color: #0c5460; }
        .status-diproses { background: #d1edff; color: #004085; }
        .status-selesai { background: #d4edda; color: #155724; }
        
        /* Riwayat Section */
        .riwayat-section {
            background: var(--white);
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .section-title {
            font-size: 1.3rem;
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        
        .feedback-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .feedback-card {
            padding: 20px;
            border: 1px solid #eee;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary);
            transition: all 0.3s;
        }

        .feedback-card:hover {
            box-shadow: var(--shadow);
        }
        
        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .feedback-title {
            font-size: 1.1rem;
            color: var(--dark);
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .feedback-meta {
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .feedback-content {
            color: var(--dark);
            line-height: 1.5;
            margin-bottom: 15px;
        }
        
        .feedback-footer {
            padding-top: 15px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .feedback-tanggapan {
            background: #f8f9fa;
            padding: 15px;
            border-radius: var(--border-radius);
            margin-top: 10px;
            border-left: 3px solid var(--info);
        }
        
        .tanggapan-label {
            font-weight: 600;
            color: var(--info);
            margin-bottom: 5px;
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
            
            .feedback-header {
                flex-direction: column;
            }
            
            .feedback-footer {
                flex-direction: column;
                align-items: flex-start;
            }
            
            nav ul {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .form-section, .riwayat-section {
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
                    <h1>Bank Sampah - Feedback</h1>
                </div>
                <nav>
                    <ul>
                        <li><a href="dashboard-mitra.php">Dashboard</a></li>
                        <li><a href="manage-penjemputan.php">Penjemputan</a></li>
                        <li><a href="manage-jadwal.php">Jadwal</a></li>
                        <li><a href="riwayat-mitra.php">Riwayat</a></li>
                        <li><a href="pengumuman-mitra.php">Pengumuman</a></li>
                        <li><a href="feedback-mitra.php" class="active">Feedback</a></li>
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
                    <h1>💬 Kirim Feedback</h1>
                    <p>Berikan masukan dan saran untuk pengembangan sistem Bank Sampah</p>
                </div>
            </div>

            <!-- Form Feedback -->
            <section class="form-section">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="jenis_feedback">Jenis Feedback *</label>
                        <select name="jenis_feedback" id="jenis_feedback" class="form-control" required>
                            <option value="">Pilih Jenis Feedback</option>
                            <option value="saran" <?php echo ($_POST['jenis_feedback'] ?? '') === 'saran' ? 'selected' : ''; ?>>Saran Perbaikan</option>
                            <option value="bug" <?php echo ($_POST['jenis_feedback'] ?? '') === 'bug' ? 'selected' : ''; ?>>Laporan Bug/Error</option>
                            <option value="fitur" <?php echo ($_POST['jenis_feedback'] ?? '') === 'fitur' ? 'selected' : ''; ?>>Permintaan Fitur Baru</option>
                            <option value="keluhan" <?php echo ($_POST['jenis_feedback'] ?? '') === 'keluhan' ? 'selected' : ''; ?>>Keluhan</option>
                            <option value="lainnya" <?php echo ($_POST['jenis_feedback'] ?? '') === 'lainnya' ? 'selected' : ''; ?>>Lainnya</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="prioritas">Prioritas</label>
                        <select name="prioritas" id="prioritas" class="form-control">
                            <option value="low" <?php echo ($_POST['prioritas'] ?? 'medium') === 'low' ? 'selected' : ''; ?>>Rendah</option>
                            <option value="medium" <?php echo ($_POST['prioritas'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>Sedang</option>
                            <option value="high" <?php echo ($_POST['prioritas'] ?? 'medium') === 'high' ? 'selected' : ''; ?>>Tinggi</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="judul">Judul Feedback *</label>
                        <input type="text" name="judul" id="judul" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['judul'] ?? ''); ?>" 
                               placeholder="Masukkan judul feedback" required>
                    </div>

                    <div class="form-group">
                        <label for="pesan">Isi Feedback *</label>
                        <textarea name="pesan" id="pesan" class="form-control" 
                                  placeholder="Jelaskan secara detail feedback Anda..." required><?php echo htmlspecialchars($_POST['pesan'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        📤 Kirim Feedback
                    </button>
                </form>
            </section>

            <!-- Riwayat Feedback -->
            <section class="riwayat-section">
                <h2 class="section-title">📋 Riwayat Feedback</h2>
                
                <?php if (empty($riwayat_feedback)): ?>
                    <div class="empty-state">
                        <h3>📭 Belum ada feedback</h3>
                        <p>Anda belum mengirim feedback apapun.</p>
                    </div>
                <?php else: ?>
                    <div class="feedback-list">
                        <?php foreach ($riwayat_feedback as $feedback): ?>
                        <div class="feedback-card">
                            <div class="feedback-header">
                                <div>
                                    <div class="feedback-title">
                                        <?php echo htmlspecialchars($feedback['judul']); ?>
                                    </div>
                                    <div class="feedback-meta">
                                        📅 <?php echo date('d F Y H:i', strtotime($feedback['created_at'])); ?>
                                         • 🏷️ <?php echo ucfirst($feedback['jenis_feedback']); ?>
                                    </div>
                                </div>
                                <div>
                                    <span class="priority-badge priority-<?php echo $feedback['prioritas']; ?>">
                                        <?php 
                                            $priority_text = [
                                                'low' => 'Rendah',
                                                'medium' => 'Sedang', 
                                                'high' => 'Tinggi'
                                            ];
                                            echo $priority_text[$feedback['prioritas']];
                                        ?>
                                    </span>
                                    <span class="status-badge status-<?php echo $feedback['status']; ?>">
                                        <?php 
                                            $status_text = [
                                                'pending' => '⏳ Menunggu',
                                                'dibaca' => '👀 Dibaca',
                                                'diproses' => '🔄 Diproses',
                                                'selesai' => '✅ Selesai'
                                            ];
                                            echo $status_text[$feedback['status']];
                                        ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="feedback-content">
                                <?php echo nl2br(htmlspecialchars($feedback['pesan'])); ?>
                            </div>
                            
                            <?php if ($feedback['tanggapan']): ?>
                            <div class="feedback-tanggapan">
                                <div class="tanggapan-label">💬 Tanggapan Admin:</div>
                                <?php echo nl2br(htmlspecialchars($feedback['tanggapan'])); ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="feedback-footer">
                                <small>ID: #<?php echo str_pad($feedback['id'], 4, '0', STR_PAD_LEFT); ?></small>
                                <small>Terakhir update: <?php echo date('d/m/Y H:i', strtotime($feedback['updated_at'])); ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
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