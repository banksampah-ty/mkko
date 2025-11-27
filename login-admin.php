<?php
include 'config.php';

// CEK JIKA SUDAH LOGIN SEBAGAI ADMIN
if (isLoggedIn() && isAdmin()) {
    header("Location: dashboard-admin.php");
    exit();
}

// PROSES LOGIN ADMIN
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    // VALIDASI INPUT
    if (empty($username) || empty($password)) {
        $error = "Username dan password harus diisi!";
    } else {
        // CARI USER DI DATABASE (HANYA ROLE ADMIN)
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin'");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // CEK STATUS AKUN
                if ($user['status'] !== 'active') {
                    $error = "Akun admin tidak aktif.";
                } else {
                    // SET SESSION ADMIN
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['saldo'] = $user['saldo'];
                    $_SESSION['is_verified'] = $user['is_verified'];
                    $_SESSION['login_time'] = time();
                    
                    header("Location: dashboard-admin.php");
                    exit();
                }
            } else {
                $error = "Username admin atau password salah!";
            }
        } catch(PDOException $e) {
            error_log("Admin Login error: " . $e->getMessage());
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
    <title>Login Admin - Bank Sampah</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .login-options {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .login-option-btn {
            flex: 1;
            padding: 10px;
            text-align: center;
            border: 2px solid #e74c3c;
            border-radius: 5px;
            text-decoration: none;
            color: #e74c3c;
            font-weight: bold;
            transition: all 0.3s;
        }
        .login-option-btn.active {
            background: #e74c3c;
            color: white;
        }
        .login-option-btn:hover {
            background: #e74c3c;
            color: white;
        }
        .btn-admin {
            background: #e74c3c;
            border-color: #e74c3c;
        }
        .btn-admin:hover {
            background: #c0392b;
            border-color: #c0392b;
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
                        <li><a href="login-admin.php" class="active">Login Admin</a></li>
                        <li><a href="login-mitra.php">Login Mitra</a></li>
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
                            <h2>Login Admin</h2>
                            <p>Akses terbatas untuk administrator sistem</p>
                        </div>

                        <!-- Login Options -->
                        <div class="login-options">
                            <a href="login.php" class="login-option-btn">Warga</a>
                            <a href="login-admin.php" class="login-option-btn active">Admin</a>
                            <a href="login-mitra.php" class="login-option-btn">Mitra</a>
                        </div>

                        <div class="admin-warning">
                            ⚠️ <strong>Akses Terbatas</strong><br>
                            Hanya untuk administrator yang berwenang
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-error">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="login-form">
                            <div class="form-group">
                                <label for="username">Username Admin</label>
                                <input type="text" id="username" name="username" class="form-control" 
                                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                       placeholder="Masukkan username admin" required>
                            </div>

                            <div class="form-group">
                                <label for="password">Password Admin</label>
                                <input type="password" id="password" name="password" class="form-control" 
                                       placeholder="Masukkan password admin" required>
                            </div>

                            <button type="submit" class="btn btn-admin btn-block">Masuk sebagai Admin</button>
                        </form>

                        <div class="login-footer">
                            <div class="demo-credentials">
                                <p><strong>Demo Admin:</strong></p>
                                <p><strong>Username:</strong> admin</p>
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