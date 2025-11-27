<?php
include 'config.php';

// CEK JIKA SUDAH LOGIN SEBAGAI USER
if (isLoggedIn() && (isUser() || isAdmin())) {
    if (isAdmin()) {
        header("Location: dashboard-admin.php");
        exit();
    } else {
        header("Location: dashboard-user.php");
        exit();
    }
}

// PROSES LOGIN USER
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    // VALIDASI INPUT
    if (empty($username) || empty($password)) {
        $error = "Username dan password harus diisi!";
    } else {
        // CARI USER DI DATABASE (HANYA ROLE USER)
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'user'");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // CEK STATUS AKUN
                if ($user['status'] !== 'active') {
                    $error = "Akun tidak aktif. Silakan hubungi admin.";
                } else if ($user['is_verified'] !== 'yes') {
                    $error = "Akun belum diverifikasi. Silakan cek email Anda.";
                } else {
                    // SET SESSION USER
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['saldo'] = $user['saldo'];
                    $_SESSION['is_verified'] = $user['is_verified'];
                    $_SESSION['login_time'] = time();
                    
                    header("Location: dashboard-user.php");
                    exit();
                }
            } else {
                $error = "Username atau password salah!";
            }
        } catch(PDOException $e) {
            error_log("User Login error: " . $e->getMessage());
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
    <title>Login Warga - Bank Sampah</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .login-options {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .login-option-btn {
            flex: 1;
            padding: 10px;
            text-align: center;
            border: 2px solid #3498db;
            border-radius: 5px;
            text-decoration: none;
            color: #3498db;
            font-weight: bold;
            transition: all 0.3s;
        }
        .login-option-btn.active {
            background: #3498db;
            color: white;
        }
        .login-option-btn:hover {
            background: #3498db;
            color: white;
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
                        <li><a href="login.php" class="active">Login Warga</a></li>
                        <li><a href="login-admin.php">Login Admin</a></li>
                        <li><a href="login-mitra.php">Login Mitra</a></li>
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
                            <h2>Login Warga</h2>
                            <p>Masuk sebagai nasabah Bank Sampah</p>
                        </div>

                        <!-- Login Options -->
                        <div class="login-options">
                            <a href="login.php" class="login-option-btn active">Warga</a>
                            <a href="login-admin.php" class="login-option-btn">Admin</a>
                            <a href="login-mitra.php" class="login-option-btn">Mitra</a>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-error">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="login-form">
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" id="username" name="username" class="form-control" 
                                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                       placeholder="Masukkan username" required>
                            </div>

                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" id="password" name="password" class="form-control" 
                                       placeholder="Masukkan password" required>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block">Masuk sebagai Warga</button>
                        </form>

                        <div class="login-footer">
                            <p>Belum punya akun? <a href="register.php">Daftar di sini</a></p>
                            
                            <div class="demo-credentials">
                                <p><strong>Demo Warga:</strong></p>
                                <p><strong>Username:</strong> user</p>
                                <p><strong>Password:</strong> password123</p>
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
</body>
</html>