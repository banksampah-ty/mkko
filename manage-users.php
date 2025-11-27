<?php
include 'config.php';

// CEK LOGIN DAN ROLE ADMIN
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

$success = '';
$error = '';

// HANDLE TOGGLE STATUS USER
if (isset($_GET['toggle_status'])) {
    $user_id = (int)$_GET['toggle_status'];
    
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        $new_status = $user['status'] == 'active' ? 'inactive' : 'active';
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $user_id]);
        
        $success = "Status user berhasil diubah menjadi " . $new_status;
    } else {
        $error = "User tidak ditemukan!";
    }
}

// HANDLE RESET SALDO
if (isset($_POST['reset_saldo'])) {
    $user_id = (int)$_POST['user_id'];
    
    $stmt = $pdo->prepare("UPDATE users SET saldo = 0 WHERE id = ?");
    if ($stmt->execute([$user_id])) {
        $success = "Saldo user berhasil direset ke 0";
    } else {
        $error = "Gagal mereset saldo user";
    }
}

// HANDLE TAMBAH USER BARU
if (isset($_POST['tambah_user'])) {
    $username = sanitize($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $nama_lengkap = sanitize($_POST['nama_lengkap']);
    $nik = sanitize($_POST['nik']);
    $email = sanitize($_POST['email']);
    $telepon = sanitize($_POST['telepon']);
    $alamat = sanitize($_POST['alamat']);
    
    // Validasi NIK
    if (strlen($nik) != 16 || !is_numeric($nik)) {
        $error = "NIK harus 16 digit angka!";
    } else {
        // Cek username sudah ada
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->fetch()) {
            $error = "Username sudah digunakan!";
        } else {
            // Cek NIK sudah ada
            $stmt = $pdo->prepare("SELECT id FROM users WHERE nik = ?");
            $stmt->execute([$nik]);
            
            if ($stmt->fetch()) {
                $error = "NIK sudah terdaftar!";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, password, nama_lengkap, nik, email, telepon, alamat, role, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'user', 'active')
                ");
                
                if ($stmt->execute([$username, $password, $nama_lengkap, $nik, $email, $telepon, $alamat])) {
                    $success = "User baru berhasil ditambahkan";
                } else {
                    $error = "Gagal menambahkan user baru";
                }
            }
        }
    }
}

// HANDLE EDIT USER
if (isset($_POST['edit_user'])) {
    $user_id = (int)$_POST['user_id'];
    $nama_lengkap = sanitize($_POST['nama_lengkap']);
    $nik = sanitize($_POST['nik']);
    $email = sanitize($_POST['email']);
    $telepon = sanitize($_POST['telepon']);
    $alamat = sanitize($_POST['alamat']);
    
    // Validasi NIK
    if (strlen($nik) != 16 || !is_numeric($nik)) {
        $error = "NIK harus 16 digit angka!";
    } else {
        // Cek jika NIK sudah digunakan oleh user lain
        $stmt = $pdo->prepare("SELECT id FROM users WHERE nik = ? AND id != ?");
        $stmt->execute([$nik, $user_id]);
        
        if ($stmt->fetch()) {
            $error = "NIK sudah digunakan oleh user lain!";
        } else {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET nama_lengkap = ?, nik = ?, email = ?, telepon = ?, alamat = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            
            if ($stmt->execute([$nama_lengkap, $nik, $email, $telepon, $alamat, $user_id])) {
                $success = "Data user berhasil diupdate";
            } else {
                $error = "Gagal mengupdate data user";
            }
        }
    }
}

// HANDLE HAPUS USER
if (isset($_GET['delete_user'])) {
    $user_id = (int)$_GET['delete_user'];
    
    // Cek apakah user memiliki transaksi atau penarikan
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_transaksi FROM transaksi WHERE user_id = ?
        UNION ALL
        SELECT COUNT(*) as total_penarikan FROM penarikan WHERE user_id = ?
    ");
    $stmt->execute([$user_id, $user_id]);
    $count1 = $stmt->fetchColumn();
    $count2 = $stmt->fetchColumn();
    
    if ($count1 == 0 && $count2 == 0) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$user_id])) {
            $success = "User berhasil dihapus";
        } else {
            $error = "Gagal menghapus user";
        }
    } else {
        $error = "Tidak dapat menghapus user yang memiliki transaksi atau penarikan!";
    }
}

// AMBIL DATA USER UNTUK EDIT
$edit_user = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit_user = $stmt->fetch();
}

// FILTER DAN PENCARIAN
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where_conditions = ["u.role = 'user'"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.nama_lengkap LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.nik LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($status_filter)) {
    $where_conditions[] = "u.status = ?";
    $params[] = $status_filter;
}

$where_sql = implode(' AND ', $where_conditions);

// AMBIL SEMUA USER DENGAN STATISTIK
$stmt = $pdo->prepare("
    SELECT 
        u.*,
        COUNT(t.id) as total_transaksi,
        COALESCE(SUM(t.total_harga), 0) as total_setoran,
        COUNT(p.id) as total_penarikan,
        COALESCE(SUM(CASE WHEN p.status = 'approved' THEN p.jumlah ELSE 0 END), 0) as total_ditarik
    FROM users u 
    LEFT JOIN transaksi t ON u.id = t.user_id 
    LEFT JOIN penarikan p ON u.id = p.user_id 
    WHERE $where_sql
    GROUP BY u.id 
    ORDER BY u.created_at DESC
");

$stmt->execute($params);
$users = $stmt->fetchAll();

// HITUNG STATISTIK
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_users,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users,
        COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_users,
        COALESCE(SUM(saldo), 0) as total_saldo
    FROM users 
    WHERE role = 'user'
");
$stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User - Bank Sampah</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <span class="logo-icon">♻️</span>
                    <h1>Bank Sampah - Kelola User</h1>
                </div>
                <nav>
                    <ul>
                        <li><a href="dashboard-admin.php">Dashboard</a></li>
                        <li><a href="manage-users.php"class="active">Kelola Warga</a></li>
                        <li><a href="manage-mitra.php">Kelola Mitra</a></li>
                        <li><a href="manage-transactions.php">Transaksi</a></li>
                        <li><a href="manage-jenis-sampah.php">Jenis Sampah</a></li>
                        <li><a href="manage-withdrawals.php">Penarikan</a></li>
                        <li><a href="?logout=1">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <section class="dashboard">
            <div class="container">
                <!-- NOTIFICATION -->
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- HEADER -->
                <div class="dashboard-header">
                    <div class="dashboard-title">
                        <h1>Kelola Nasabah</h1>
                        <p>Manajemen data nasabah bank sampah</p>
                    </div>
                    <div class="header-actions">
                        <button onclick="toggleForm('form-tambah-user')" class="btn-primary">
                            + Tambah User Baru
                        </button>
                    </div>
                </div>

                <!-- STATISTIK -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-info">
                            <h3>Total Nasabah</h3>
                            <p class="stat-number"><?php echo $stats['total_users']; ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">✅</div>
                        <div class="stat-info">
                            <h3>Aktif</h3>
                            <p class="stat-number"><?php echo $stats['active_users']; ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">❌</div>
                        <div class="stat-info">
                            <h3>Non-Aktif</h3>
                            <p class="stat-number"><?php echo $stats['inactive_users']; ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">💰</div>
                        <div class="stat-info">
                            <h3>Total Saldo</h3>
                            <p class="stat-number"><?php echo formatRupiah($stats['total_saldo']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- FORM TAMBAH/EDIT USER -->
                <div id="form-tambah-user" class="content-section" style="<?php echo !$edit_user ? 'display: none;' : ''; ?>">
                    <h2><?php echo $edit_user ? 'Edit User' : 'Tambah User Baru'; ?></h2>
                    <form method="POST" class="form">
                        <?php if ($edit_user): ?>
                            <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Nama Lengkap *</label>
                                <input type="text" name="nama_lengkap" 
                                       value="<?php echo $edit_user['nama_lengkap'] ?? ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>NIK *</label>
                                <input type="text" name="nik" 
                                       value="<?php echo $edit_user['nik'] ?? ''; ?>" 
                                       pattern="[0-9]{16}" maxlength="16" required
                                       placeholder="16 digit NIK" class="nik-input">
                                <small style="color: #666;">16 digit angka</small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <?php if (!$edit_user): ?>
                            <div class="form-group">
                                <label>Username *</label>
                                <input type="text" name="username" required
                                       value="<?php echo $edit_user['username'] ?? ''; ?>">
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!$edit_user): ?>
                            <div class="form-group">
                                <label>Password *</label>
                                <input type="password" name="password" required>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" 
                                       value="<?php echo $edit_user['email'] ?? ''; ?>">
                            </div>
                            <div class="form-group">
                                <label>Telepon</label>
                                <input type="text" name="telepon" 
                                       value="<?php echo $edit_user['telepon'] ?? ''; ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Alamat</label>
                            <textarea name="alamat" rows="3"><?php echo $edit_user['alamat'] ?? ''; ?></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="<?php echo $edit_user ? 'edit_user' : 'tambah_user'; ?>" 
                                    class="btn-primary">
                                <?php echo $edit_user ? 'Update User' : 'Tambah User'; ?>
                            </button>
                            <?php if ($edit_user): ?>
                                <a href="manage-users.php" class="btn-secondary">Batal</a>
                            <?php else: ?>
                                <button type="button" onclick="toggleForm('form-tambah-user')" class="btn-secondary">
                                    Batal
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- FILTER DAN PENCARIAN -->
                <div class="content-section">
                    <div class="filter-section">
                        <form method="GET" class="filter-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Pencarian:</label>
                                    <input type="text" name="search" placeholder="Cari nama, username, email, atau NIK..." 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Status:</label>
                                    <select name="status">
                                        <option value="">Semua Status</option>
                                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Aktif</option>
                                        <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Non-Aktif</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <button type="submit" class="btn-primary">Filter</button>
                                    <a href="manage-users.php" class="btn-secondary">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- DAFTAR USER -->
                <div class="content-section">
                    <h2>Daftar Nasabah (<?php echo count($users); ?> user)</h2>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>NIK</th>
                                    <th>Kontak</th>
                                    <th>Saldo</th>
                                    <th>Statistik</th>
                                    <th>Status</th>
                                    <th>Tanggal Daftar</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">Tidak ada data user</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="user-info">
                                                <div class="user-avatar sm">
                                                    <?php echo strtoupper(substr($user['nama_lengkap'], 0, 1)); ?>
                                                </div>
                                                <div class="user-details">
                                                    <strong><?php echo htmlspecialchars($user['nama_lengkap']); ?></strong>
                                                    <small>@<?php echo htmlspecialchars($user['username']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <code><?php echo htmlspecialchars($user['nik']); ?></code>
                                        </td>
                                        <td>
                                            <div class="contact-info">
                                                <?php if ($user['email']): ?>
                                                    <div><?php echo htmlspecialchars($user['email']); ?></div>
                                                <?php endif; ?>
                                                <?php if ($user['telepon']): ?>
                                                    <small><?php echo htmlspecialchars($user['telepon']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td style="font-weight: bold; color: var(--primary);">
                                            <?php echo formatRupiah($user['saldo']); ?>
                                        </td>
                                        <td>
                                            <div class="user-stats">
                                                <small>📊 Transaksi: <?php echo $user['total_transaksi']; ?></small>
                                                <small>💰 Setoran: <?php echo formatRupiah($user['total_setoran']); ?></small>
                                                <small>💸 Penarikan: <?php echo $user['total_penarikan']; ?></small>
                                                <small>➖ Ditarik: <?php echo formatRupiah($user['total_ditarik']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $user['status']; ?>">
                                                <?php echo $user['status'] == 'active' ? 'Aktif' : 'Non-Aktif'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="?edit=<?php echo $user['id']; ?>" 
                                                   class="btn-action btn-edit" title="Edit User">
                                                    ✏️ Edit
                                                </a>
                                                
                                                <a href="?toggle_status=<?php echo $user['id']; ?>" 
                                                   class="btn-action <?php echo $user['status'] == 'active' ? 'btn-warning' : 'btn-success'; ?>"
                                                   title="<?php echo $user['status'] == 'active' ? 'Non-aktifkan' : 'Aktifkan'; ?>">
                                                    <?php echo $user['status'] == 'active' ? '❌ Non-aktif' : '✅ Aktifkan'; ?>
                                                </a>
                                                
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" name="reset_saldo" 
                                                            class="btn-action btn-secondary"
                                                            onclick="return confirm('Reset saldo <?php echo htmlspecialchars($user['nama_lengkap']); ?>? Saldo akan menjadi 0.')"
                                                            title="Reset Saldo ke 0">
                                                        🔄 Reset
                                                    </button>
                                                </form>
                                                
                                                <a href="?delete_user=<?php echo $user['id']; ?>" 
                                                   class="btn-action btn-danger"
                                                   onclick="return confirm('Hapus user <?php echo htmlspecialchars($user['nama_lengkap']); ?>? Tindakan ini tidak dapat dibatalkan!')"
                                                   title="Hapus User">
                                                    🗑️ Hapus
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
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
        function toggleForm(formId) {
            const form = document.getElementById(formId);
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
        
        // Validasi NIK
        document.addEventListener('DOMContentLoaded', function() {
            const nikInputs = document.querySelectorAll('.nik-input');
            nikInputs.forEach(nikInput => {
                nikInput.addEventListener('input', function(e) {
                    // Hanya allow angka
                    this.value = this.value.replace(/[^0-9]/g, '');
                    
                    // Validasi length
                    if (this.value.length === 16) {
                        this.style.borderColor = 'var(--success)';
                    } else {
                        this.style.borderColor = 'var(--danger)';
                    }
                });
                
                // Validasi saat form submit
                const form = nikInput.closest('form');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        if (nikInput.value.length !== 16) {
                            e.preventDefault();
                            alert('NIK harus 16 digit angka!');
                            nikInput.focus();
                        }
                    });
                }
            });
        });
        
        // Auto hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>