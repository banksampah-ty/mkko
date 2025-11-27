<?php
include 'config.php';

// Redirect jika sudah login
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('dashboard-admin.php');
    } else {
        redirect('dashboard-user.php');
    }
}

// Proses pendaftaran
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $nama_lengkap = sanitize($_POST['nama_lengkap']);
    $nik = sanitize($_POST['nik']);
    $email = sanitize($_POST['email']);
    $telepon = sanitize($_POST['telepon']);
    $alamat = sanitize($_POST['alamat']);

    // Validasi
    if (empty($username) || empty($password) || empty($confirm_password) || empty($nama_lengkap) || empty($nik) || empty($email)) {
        $error = "Semua field wajib diisi!";
    } elseif ($password !== $confirm_password) {
        $error = "Password dan konfirmasi password tidak cocok!";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter!";
    } elseif (strlen($nik) != 16 || !is_numeric($nik)) {
        $error = "NIK harus 16 digit angka!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid!";
    } else {
        // Cek apakah username sudah ada
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->fetch()) {
            $error = "Username sudah digunakan!";
        } else {
            // Cek apakah NIK sudah terdaftar
            $stmt = $pdo->prepare("SELECT id FROM users WHERE nik = ?");
            $stmt->execute([$nik]);
            
            if ($stmt->fetch()) {
                $error = "NIK sudah terdaftar!";
            } else {
                // Cek apakah email sudah terdaftar
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                
                if ($stmt->fetch()) {
                    $error = "Email sudah terdaftar!";
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $verification_code = generateVerificationCode();
                    $verification_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Insert user baru
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, password, nama_lengkap, nik, email, telepon, alamat, role, verification_code, verification_expires) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'user', ?, ?)
                    ");
                    
                    if ($stmt->execute([$username, $hashed_password, $nama_lengkap, $nik, $email, $telepon, $alamat, $verification_code, $verification_expires])) {
                        // Kirim email verifikasi
                        $email_sent = sendVerificationEmail($email, $nama_lengkap, $verification_code);
                        
                        if ($email_sent || EMAIL_DEVELOPMENT_MODE) {
                            $_SESSION['pending_verification'] = $username;
                            $_SESSION['pending_email'] = $email;
                            $_SESSION['pending_nama'] = $nama_lengkap;
                            $_SESSION['original_verification_code'] = $verification_code;
                            
                            // Jika development mode, redirect ke halaman khusus
                            if (EMAIL_DEVELOPMENT_MODE) {
                                redirect('verifikasi-dev.php');
                            } else {
                                redirect('verifikasi.php');
                            }
                        } else {
                            // Jika gagal kirim email dan bukan development mode
                            $success = "Pendaftaran berhasil! Namun gagal mengirim email verifikasi. Silakan hubungi admin untuk aktivasi akun.";
                        }
                    } else {
                        $error = "Terjadi kesalahan saat mendaftar. Silakan coba lagi.";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - Bank Sampah Desa Mejobo</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <span class="logo-icon">♻️</span>
                    <h1>Bank Sampah Desa Mejobo</h1>
                </div>
                <nav>
                    <ul>
                        <li><a href="index.html">Beranda</a></li>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php" class="active">Daftar</a></li>
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
                            <h2>Daftar Akun Baru</h2>
                            <p>Isi form berikut untuk membuat akun nasabah</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-error">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <?php echo $success; ?>
                                <?php if (strpos($success, 'verifikasi') === false): ?>
                                    <br><a href="login.php" style="color: #155724; text-decoration: underline;">Klik di sini untuk login</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="login-form" <?php echo $success ? 'style="display:none;"' : ''; ?>>
                            <div class="form-group">
                                <label for="nama_lengkap">Nama Lengkap *</label>
                                <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control" 
                                       placeholder="Masukkan nama lengkap" required
                                       value="<?php echo htmlspecialchars($_POST['nama_lengkap'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="nik">NIK (Nomor Induk Kependudukan) *</label>
                                <input type="text" id="nik" name="nik" class="form-control nik-input" 
                                       placeholder="Masukkan 16 digit NIK" required
                                       maxlength="16" pattern="[0-9]{16}"
                                       value="<?php echo htmlspecialchars($_POST['nik'] ?? ''); ?>">
                                <small style="color: #666;">NIK harus 16 digit angka</small>
                            </div>

                            <div class="form-group">
                                <label for="username">Username *</label>
                                <input type="text" id="username" name="username" class="form-control" 
                                       placeholder="Masukkan username" required
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                                <small style="color: #666;">Username harus unik dan tidak boleh ada spasi</small>
                            </div>

                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" class="form-control" 
                                       placeholder="Masukkan email" required
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                <small style="color: #666;">Kode verifikasi akan dikirim ke email ini</small>
                            </div>

                            <div class="form-group">
                                <label for="telepon">Nomor Telepon</label>
                                <input type="tel" id="telepon" name="telepon" class="form-control" 
                                       placeholder="Masukkan nomor telepon"
                                       value="<?php echo htmlspecialchars($_POST['telepon'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="alamat">Alamat</label>
                                <textarea id="alamat" name="alamat" class="form-control" 
                                          placeholder="Masukkan alamat lengkap" rows="3"><?php echo htmlspecialchars($_POST['alamat'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="password">Password *</label>
                                <input type="password" id="password" name="password" class="form-control" 
                                       placeholder="Masukkan password" required>
                                <small style="color: #666;">Minimal 6 karakter</small>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Konfirmasi Password *</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                       placeholder="Masukkan ulang password" required>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block" id="submit-btn">
                                Daftar Sekarang
                            </button>
                        </form>

                        <div class="login-footer">
                            <p>Sudah punya akun? <a href="login.php">Login di sini</a></p>
                            
                            <?php if (EMAIL_DEVELOPMENT_MODE): ?>
                            <div style="background: #e7f3ff; padding: 10px; border-radius: 5px; margin-top: 10px;">
                                <p style="margin: 0; color: #0066cc; font-size: 0.9em;">
                                    <strong>Mode Development:</strong> Kode verifikasi akan ditampilkan di halaman berikutnya.
                                </p>
                            </div>
                            <?php else: ?>
                            <div style="background: #f8f9fa; padding: 10px; border-radius: 5px; margin-top: 10px;">
                                <p style="margin: 0; color: #666; font-size: 0.9em;">
                                    <strong>Note:</strong> Setelah pendaftaran, Anda akan menerima kode verifikasi via email.
                                </p>
                            </div>
                            <?php endif; ?>
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
        // Validasi real-time
        document.addEventListener('DOMContentLoaded', function() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            const nik = document.getElementById('nik');
            const email = document.getElementById('email');
            const submitBtn = document.getElementById('submit-btn');
            
            // Validasi password
            function validatePassword() {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.style.borderColor = 'var(--danger)';
                    return false;
                } else {
                    confirmPassword.style.borderColor = 'var(--success)';
                    return true;
                }
            }
            
            // Validasi NIK
            function validateNIK() {
                const nikValue = nik.value;
                if (nikValue.length === 16 && /^\d+$/.test(nikValue)) {
                    nik.style.borderColor = 'var(--success)';
                    return true;
                } else {
                    nik.style.borderColor = 'var(--danger)';
                    return false;
                }
            }
            
            // Validasi Email
            function validateEmail() {
                const emailValue = email.value;
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (emailPattern.test(emailValue)) {
                    email.style.borderColor = 'var(--success)';
                    return true;
                } else {
                    email.style.borderColor = 'var(--danger)';
                    return false;
                }
            }
            
            // Update tombol submit berdasarkan validasi
            function updateSubmitButton() {
                const isFormValid = validatePassword() && validateNIK() && validateEmail();
                submitBtn.disabled = !isFormValid;
            }
            
            password.addEventListener('input', function() {
                validatePassword();
                updateSubmitButton();
            });
            
            confirmPassword.addEventListener('input', function() {
                validatePassword();
                updateSubmitButton();
            });
            
            nik.addEventListener('input', function() {
                validateNIK();
                updateSubmitButton();
                
                // Restrict to numbers only
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value.length > 16) {
                    this.value = this.value.slice(0, 16);
                }
            });
            
            email.addEventListener('input', function() {
                validateEmail();
                updateSubmitButton();
            });
            
            // Restrict NIK input to numbers only
            nik.addEventListener('keypress', function(e) {
                const char = String.fromCharCode(e.which);
                if (!/^\d+$/.test(char)) {
                    e.preventDefault();
                }
            });
            
            // Form submission validation
            const form = document.querySelector('.login-form');
            form.addEventListener('submit', function(e) {
                const nikValue = nik.value;
                const emailValue = email.value;
                
                if (nikValue.length !== 16 || !/^\d+$/.test(nikValue)) {
                    e.preventDefault();
                    alert('NIK harus 16 digit angka!');
                    nik.focus();
                    return false;
                }
                
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue)) {
                    e.preventDefault();
                    alert('Format email tidak valid!');
                    email.focus();
                    return false;
                }
                
                // Show loading state
                submitBtn.innerHTML = '⏳ Mendaftarkan...';
                submitBtn.disabled = true;
                
                return true;
            });
            
            // Initialize button state
            updateSubmitButton();
        });
    </script>
</body>
</html>