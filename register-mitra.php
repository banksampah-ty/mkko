<?php
include 'config.php';

// Redirect jika sudah login sebagai mitra
if (isset($_SESSION['mitra_id'])) {
    header("Location: dashboard-mitra.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize input data
    $nama_mitra = sanitize($_POST['nama_mitra']);
    $alamat = sanitize($_POST['alamat']);
    $email = sanitize($_POST['email']);
    $no_hp = sanitize($_POST['no_hp']);
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validasi input
    if (empty($nama_mitra) || empty($alamat) || empty($email) || empty($no_hp) || empty($username) || empty($password)) {
        $error = "Semua field harus diisi!";
    } elseif (!validateEmail($email)) {
        $error = "Format email tidak valid!";
    } elseif (!validatePhone($no_hp)) {
        $error = "Format nomor HP tidak valid!";
    } elseif ($password !== $confirm_password) {
        $error = "Konfirmasi password tidak sesuai!";
    } elseif (!validatePassword($password)) {
        $error = "Password minimal 8 karakter dan mengandung huruf serta angka!";
    } else {
        // Cek apakah username atau email sudah terdaftar
        try {
            $stmt = $pdo->prepare("SELECT id FROM mitra WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            $existing_mitra = $stmt->fetch();
            
            if ($existing_mitra) {
                $error = "Username atau email sudah terdaftar!";
            } else {
                $hashed_password = hashPassword($password);
                $token = bin2hex(random_bytes(50));
                
                // Handle file upload
                $dokumen_izin = uploadFile('dokumen_izin');
                $ktp_pemilik = uploadFile('ktp_pemilik');
                
                if (!$dokumen_izin || !$ktp_pemilik) {
                    $error = "Upload dokumen gagal! Pastikan file sesuai format.";
                } else {
                    $sql = "INSERT INTO mitra (nama_mitra, alamat, email, no_hp, dokumen_izin, ktp_pemilik, username, password, token_verifikasi) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    
                    if ($stmt->execute([$nama_mitra, $alamat, $email, $no_hp, $dokumen_izin, $ktp_pemilik, $username, $hashed_password, $token])) {
                        // Kirim email verifikasi
                        if (sendVerificationEmail($email, $nama_mitra, $token)) {
                            $success = "Pendaftaran berhasil! Silakan cek email untuk verifikasi akun.";
                        } else {
                            $success = "Pendaftaran berhasil! Namun gagal mengirim email verifikasi. Silakan hubungi admin.";
                        }
                        
                        // Clear form
                        $_POST = array();
                    } else {
                        $error = "Terjadi kesalahan saat menyimpan data. Silakan coba lagi.";
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Registration Error: " . $e->getMessage());
            $error = "Terjadi kesalahan sistem. Silakan coba lagi nanti.";
        }
    }
}

function uploadFile($fieldName) {
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    $file_name = $_FILES[$fieldName]['name'];
    $file_tmp = $_FILES[$fieldName]['tmp_name'];
    $file_size = $_FILES[$fieldName]['size'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Validasi file
    if (!in_array($file_ext, $allowed_extensions)) {
        return null;
    }
    
    if ($file_size > $max_size) {
        return null;
    }
    
    // Buat folder uploads jika belum ada
    if (!is_dir('uploads')) {
        mkdir('uploads', 0755, true);
    }
    
    $new_filename = uniqid() . '_' . time() . '.' . $file_ext;
    $upload_path = 'uploads/' . $new_filename;
    
    if (move_uploaded_file($file_tmp, $upload_path)) {
        return $new_filename;
    }
    
    return null;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Mitra - Bank Sampah</title>
    <link rel="stylesheet" href="style.css">
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
                            <h2>Daftar Mitra Pengumpul Sampah</h2>
                            <p>Isi form berikut untuk bergabung sebagai mitra</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-error"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" class="register-form">
                            <div class="form-group">
                                <input type="text" name="nama_mitra" class="form-control" 
                                       placeholder="Nama Mitra / Perusahaan" 
                                       value="<?php echo isset($_POST['nama_mitra']) ? $_POST['nama_mitra'] : ''; ?>" 
                                       required>
                            </div>

                            <div class="form-group">
                                <textarea name="alamat" class="form-control" 
                                          placeholder="Alamat Lengkap" 
                                          rows="3" required><?php echo isset($_POST['alamat']) ? $_POST['alamat'] : ''; ?></textarea>
                            </div>

                            <div class="form-group">
                                <input type="email" name="email" class="form-control" 
                                       placeholder="Email" 
                                       value="<?php echo isset($_POST['email']) ? $_POST['email'] : ''; ?>" 
                                       required>
                            </div>

                            <div class="form-group">
                                <input type="text" name="no_hp" class="form-control" 
                                       placeholder="Nomor HP" 
                                       value="<?php echo isset($_POST['no_hp']) ? $_POST['no_hp'] : ''; ?>" 
                                       required>
                                <small>Contoh: 081234567890</small>
                            </div>

                            <div class="form-group">
                                <input type="text" name="username" class="form-control" 
                                       placeholder="Username" 
                                       value="<?php echo isset($_POST['username']) ? $_POST['username'] : ''; ?>" 
                                       required>
                                <small>Username untuk login</small>
                            </div>

                            <div class="form-group">
                                <input type="password" name="password" class="form-control" 
                                       placeholder="Password" required>
                                <small>Minimal 8 karakter, mengandung huruf dan angka</small>
                            </div>

                            <div class="form-group">
                                <input type="password" name="confirm_password" class="form-control" 
                                       placeholder="Konfirmasi Password" required>
                            </div>

                            <div class="form-group">
                                <label>Dokumen Izin Usaha:</label>
                                <input type="file" name="dokumen_izin" class="form-control" 
                                       accept=".pdf,.jpg,.jpeg,.png" required>
                                <small>Format: PDF, JPG, PNG (Maks. 5MB)</small>
                            </div>

                            <div class="form-group">
                                <label>KTP Pemilik:</label>
                                <input type="file" name="ktp_pemilik" class="form-control" 
                                       accept=".jpg,.jpeg,.png" required>
                                <small>Format: JPG, PNG (Maks. 5MB)</small>
                            </div>

                            <div class="terms">
                                <h4>Syarat & Ketentuan:</h4>
                                <ul>
                                    <li>Data yang diisi harus benar dan valid</li>
                                    <li>Dokumen yang diupload harus jelas dan terbaca</li>
                                    <li>Akun akan diverifikasi oleh admin dalam 1x24 jam</li>
                                    <li>Setelah verifikasi, mitra dapat login ke sistem</li>
                                </ul>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block">Daftar sebagai Mitra</button>
                        </form>

                        <div class="login-footer">
                            <p>Sudah punya akun mitra? <a href="login-mitra.php">Login di sini</a></p>
                            <p>Ingin daftar sebagai user? <a href="register.php">Daftar User</a></p>
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