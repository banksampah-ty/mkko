<?php
include 'config.php';

// Redirect jika sudah login sebagai mitra
if (isLoggedIn() && isMitra()) {
    header("Location: dashboard-mitra.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    // Debug log
    error_log("Login attempt - Username: $username");
    
    if (empty($username) || empty($password)) {
        $error = "Username dan password harus diisi!";
    } else {
        try {
            // Cek apakah user ada
            $stmt = $pdo->prepare("SELECT * FROM mitra WHERE username = ?");
            $stmt->execute([$username]);
            $mitra = $stmt->fetch();
            
            if ($mitra) {
                error_log("Mitra found: " . $mitra['username'] . ", Status: " . $mitra['status_verifikasi']);
                
                // Cek status verifikasi
                if ($mitra['status_verifikasi'] !== 'verified') {
                    $error = "Akun mitra belum diverifikasi. Silakan tunggu verifikasi admin.";
                } 
                // Cek password
                elseif (password_verify($password, $mitra['password'])) {
                    // Login berhasil - bersihkan session lama
                    session_regenerate_id(true);
                    
                    // Set session untuk mitra
                    $_SESSION['mitra_id'] = $mitra['id'];
                    $_SESSION['mitra_nama'] = $mitra['nama_mitra'];
                    $_SESSION['username'] = $mitra['username'];
                    $_SESSION['role'] = 'mitra';
                    $_SESSION['is_verified'] = 'yes';
                    $_SESSION['login_time'] = time();
                    
                    // Pastikan tidak ada session user/admin
                    unset($_SESSION['user_id']);
                    unset($_SESSION['nama_lengkap']);
                    unset($_SESSION['saldo']);
                    
                    error_log("Login SUCCESS for mitra: " . $username);
                    header("Location: dashboard-mitra.php");
                    exit();
                } else {
                    $error = "Password salah!";
                    error_log("Password FAILED for mitra: " . $username);
                }
            } else {
                $error = "Username tidak ditemukan!";
                error_log("Username NOT FOUND: " . $username);
            }
            
        } catch (PDOException $e) {
            error_log("MITRA LOGIN ERROR: " . $e->getMessage());
            $error = "Terjadi kesalahan sistem. Silakan coba lagi.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Mitra - Bank Sampah</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-weight: bold;
        }
        .alert-error {
            background-color: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        .demo-credentials {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
            border-left: 4px solid #28a745;
        }
        .demo-credentials p {
            margin: 5px 0;
            font-size: 0.9em;
        }
        .login-options {
            display: flex;
            gap: 10px;
            margin: 15px 0;
        }
        .login-option-btn {
            flex: 1;
            padding: 10px;
            text-align: center;
            border: 2px solid #27ae60;
            border-radius: 5px;
            text-decoration: none;
            color: #27ae60;
            font-weight: bold;
            transition: all 0.3s;
        }
        .login-option-btn.active {
            background: #27ae60;
            color: white;
        }
        .login-option-btn:hover {
            background: #27ae60;
            color: white;
        }
        .btn-mitra {
            background: #27ae60;
            border-color: #27ae60;
        }
        .btn-mitra:hover {
            background: #219653;
            border-color: #219653;
        }
        .mitra-info {
            background: #e8f5e8;
            border: 1px solid #27ae60;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
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
                        <li><a href="login.php">Login Warga</a></li>
                        <li><a href="login-admin.php">Login Admin</a></li>
                        <li><a href="login-mitra.php" class="active">Login Mitra</a></li>
                        <li><a href="register-mitra.php">Daftar Mitra</a></li>
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
                            <h2>Login Mitra</h2>
                            <p>Masuk ke akun mitra Bank Sampah</p>
                        </div>

                        <!-- Login Options -->
                        <div class="login-options">
                            <a href="login.php" class="login-option-btn">Warga</a>
                            <a href="login-admin.php" class="login-option-btn">Admin</a>
                            <a href="login-mitra.php" class="login-option-btn active">Mitra</a>
                        </div>

                        <div class="mitra-info">
                            🚚 <strong>Mitra Pengumpul Sampah</strong><br>
                            Login untuk mengelola penjemputan dan penimbangan sampah
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-error"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST" class="login-form">
                            <div class="form-group">
                                <label for="username">Username Mitra</label>
                                <input type="text" id="username" name="username" class="form-control" 
                                       placeholder="Masukkan username mitra" 
                                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" id="password" name="password" class="form-control" 
                                       placeholder="Masukkan password" required>
                            </div>

                            <button type="submit" class="btn btn-mitra btn-block">Masuk sebagai Mitra</button>
                        </form>

                        <div class="login-footer">
                            <p>Belum punya akun mitra? <a href="register-mitra.php">Daftar sebagai Mitra</a></p>
                            
                            <div class="demo-credentials">
                                <p><strong>Akun Demo Mitra:</strong></p>
                                <p><strong>Username:</strong> mitra_demo</p>
                                <p><strong>Password:</strong> password123</p>
                                <p><small><em>Pastikan akun sudah diverifikasi oleh admin</em></small></p>
                            </div>

                            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                                <p style="text-align: center; margin: 0;">
                                    <small>
                                        Login sebagai: 
                                        <a href="login.php">User</a> | 
                                        <a href="login-admin.php">Admin</a>
                                    </small>
                                </p>
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
        // Client-side validation
        document.querySelector('.login-form').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!username || !password) {
                e.preventDefault();
                alert('Username dan password harus diisi!');
                return false;
            }
        });

        // Clear error on input focus
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('focus', function() {
                const errorAlert = document.querySelector('.alert-error');
                if (errorAlert) {
                    errorAlert.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>