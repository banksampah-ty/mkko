<?php
include 'config.php';

// Cek jika tidak ada pending verification
if (!isset($_SESSION['pending_verification'])) {
    redirect('register.php');
}

$username = $_SESSION['pending_verification'];
$error = '';
$success = '';

// Ambil kode verifikasi dari berbagai sumber
$verification_code_from_session = $_SESSION['dev_verification_code'] ?? '';
$verification_code_from_original = $_SESSION['original_verification_code'] ?? '';

// Coba ambil dari database juga
try {
    $stmt = $pdo->prepare("SELECT verification_code, verification_expires FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user_data = $stmt->fetch();
    $verification_code_from_db = $user_data['verification_code'] ?? '';
} catch (PDOException $e) {
    error_log("Error fetching verification code from DB: " . $e->getMessage());
    $verification_code_from_db = '';
}

// Prioritaskan kode verifikasi
$verification_code = $verification_code_from_session ?: $verification_code_from_original ?: $verification_code_from_db;

if (!$verification_code) {
    $error = "Kode verifikasi tidak ditemukan. Silakan daftar ulang.";
}

// Proses verifikasi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_code = sanitize($_POST['verification_code']);
    
    error_log("Verification attempt - Input: $input_code, Expected: $verification_code, Username: $username");
    
    // Coba verifikasi dengan metode utama
    $verified = verifyUserAccount($username, $input_code);
    
    // Jika gagal, coba metode alternatif
    if (!$verified) {
        error_log("Primary verification failed, trying alternative...");
        $verified = verifyUserAccountAlternative($username, $input_code);
    }
    
    if ($verified) {
        // Hapus semua session pending
        unset($_SESSION['pending_verification']);
        unset($_SESSION['pending_email']);
        unset($_SESSION['pending_nama']);
        unset($_SESSION['original_verification_code']);
        clearDevelopmentVerificationCode();
        
        // Ambil data user untuk login
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Set session login
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['saldo'] = $user['saldo'];
            $_SESSION['is_verified'] = $user['is_verified'];
            
            $success = "Verifikasi berhasil! Anda akan diarahkan ke dashboard.";
            
            // Redirect setelah 3 detik
            header("refresh:3;url=dashboard-user.php");
        }
    } else {
        $error = "Kode verifikasi tidak valid!";
        
        // Debug information
        error_log("All verification attempts failed for user: $username");
        error_log("Available codes - Session: $verification_code_from_session, Original: $verification_code_from_original, DB: $verification_code_from_db");
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Akun - Bank Sampah</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
            font-family: monospace;
            font-size: 0.9em;
        }
        .code-options {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
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
                        <li><a href="index.html">Beranda</a></li>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Daftar</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <section class="login-section">
            <div class="container">
                <div class="login-container">
                    <div class="login-box">
                        <div class="login-header">
                            <h2>Verifikasi Akun</h2>
                            <p>Development Mode - Kode verifikasi ditampilkan di bawah</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-error">
                                <?php echo $error; ?>
                                <?php if (EMAIL_DEVELOPMENT_MODE): ?>
                                <br><small>Periksa file error_log untuk detail debugging.</small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <?php echo $success; ?>
                            </div>
                        <?php endif; ?>

                        <div class="alert alert-warning">
                            <strong>Development Mode Aktif</strong>
                            <p>Kode verifikasi untuk <strong><?php echo htmlspecialchars($_SESSION['pending_email'] ?? 'N/A'); ?></strong>:</p>
                            <h1 style="color: #007bff; font-size: 3em; text-align: center; margin: 20px 0; letter-spacing: 10px; font-family: 'Courier New', monospace;">
                                <?php echo $verification_code; ?>
                            </h1>
                            <p>Kode ini berlaku hingga: <strong><?php echo date('H:i:s', strtotime('+1 hour')); ?></strong></p>
                        </div>

                        <?php if (EMAIL_DEVELOPMENT_MODE): ?>
                        <div class="code-options">
                            <p><strong>Kode verifikasi dari:</strong></p>
                            <ul>
                                <li>Session: <?php echo $verification_code_from_session ?: 'Tidak tersedia'; ?></li>
                                <li>Original: <?php echo $verification_code_from_original ?: 'Tidak tersedia'; ?></li>
                                <li>Database: <?php echo $verification_code_from_db ?: 'Tidak tersedia'; ?></li>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <form method="POST" class="login-form">
                            <div class="form-group">
                                <label for="verification_code">Kode Verifikasi</label>
                                <input type="text" id="verification_code" name="verification_code" class="form-control" 
                                       value="<?php echo $verification_code; ?>" 
                                       placeholder="Masukkan 6 digit kode" required maxlength="6"
                                       pattern="[0-9]{6}">
                                <small style="color: #666;">Kode sudah diisi otomatis. Anda bisa mengubahnya jika perlu.</small>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block">Verifikasi Akun</button>
                        </form>

                        <div class="login-footer">
                            <p>Kembali ke <a href="register.php">halaman pendaftaran</a></p>
                            <?php if (EMAIL_DEVELOPMENT_MODE): ?>
                            <p><small><a href="debug-verification.php">Debug Info</a></small></p>
                            <?php endif; ?>
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
        document.addEventListener('DOMContentLoaded', function() {
            const codeInput = document.getElementById('verification_code');
            
            // Auto-select code when clicked
            codeInput.addEventListener('click', function() {
                this.select();
            });
            
            // Validate code input
            codeInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value.length === 6) {
                    this.style.borderColor = 'var(--success)';
                } else {
                    this.style.borderColor = 'var(--danger)';
                }
            });
        });
    </script>
</body>
</html>