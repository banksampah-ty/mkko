<?php
include 'config.php';

// Cek login dan role admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

// Handle tambah jenis sampah
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_jenis'])) {
    $nama_jenis = sanitize($_POST['nama_jenis']);
    $harga_per_kg = (float)$_POST['harga_per_kg'];
    $deskripsi = sanitize($_POST['deskripsi']);
    
    if (empty($nama_jenis) || $harga_per_kg <= 0) {
        $error = "Nama jenis dan harga harus diisi dengan benar!";
    } else {
        $stmt = $pdo->prepare("INSERT INTO jenis_sampah (nama_jenis, harga_per_kg, deskripsi) VALUES (?, ?, ?)");
        if ($stmt->execute([$nama_jenis, $harga_per_kg, $deskripsi])) {
            $success = "Jenis sampah berhasil ditambahkan!";
            // Refresh halaman
            echo "<script>window.location.href = 'manage-jenis-sampah.php';</script>";
            exit;
        } else {
            $error = "Gagal menambahkan jenis sampah!";
        }
    }
}

// Handle edit jenis sampah
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_jenis'])) {
    $id = (int)$_POST['id'];
    $nama_jenis = sanitize($_POST['nama_jenis']);
    $harga_per_kg = (float)$_POST['harga_per_kg'];
    $deskripsi = sanitize($_POST['deskripsi']);
    
    if (empty($nama_jenis) || $harga_per_kg <= 0) {
        $error = "Nama jenis dan harga harus diisi dengan benar!";
    } else {
        $stmt = $pdo->prepare("UPDATE jenis_sampah SET nama_jenis = ?, harga_per_kg = ?, deskripsi = ? WHERE id = ?");
        if ($stmt->execute([$nama_jenis, $harga_per_kg, $deskripsi, $id])) {
            $success = "Jenis sampah berhasil diperbarui!";
            // Refresh halaman
            echo "<script>window.location.href = 'manage-jenis-sampah.php';</script>";
            exit;
        } else {
            $error = "Gagal memperbarui jenis sampah!";
        }
    }
}

// Handle hapus jenis sampah
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $stmt = $pdo->prepare("DELETE FROM jenis_sampah WHERE id = ?");
    $stmt->execute([$id]);
    $success = "Jenis sampah berhasil dihapus!";
    echo "<script>window.location.href = 'manage-jenis-sampah.php';</script>";
    exit;
}

// Ambil data untuk edit (jika ada)
$edit_data = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM jenis_sampah WHERE id = ?");
    $stmt->execute([$id]);
    $edit_data = $stmt->fetch();
}

// Ambil data jenis sampah (setelah semua operasi)
$stmt = $pdo->query("SELECT * FROM jenis_sampah ORDER BY nama_jenis");
$jenis_sampah = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jenis Sampah - Bank Sampah</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <span class="logo-icon">♻️</span>
                    <h1>Bank Sampah - Jenis Sampah</h1>
                </div>
                <nav>
                    <ul>
                        <li><a href="dashboard-admin.php">Dashboard</a></li>
                        <li><a href="manage-users.php">Kelola Warga</a></li>
                        <li><a href="manage-mitra.php">Kelola Mitra</a></li>
                        <li><a href="manage-transactions.php">Transaksi</a></li>
                        <li><a href="manage-jenis-sampah.php" class="active">Jenis Sampah</a></li>
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

                <div class="dashboard-header">
                    <div class="dashboard-title">
                        <h1>Kelola Jenis Sampah</h1>
                        <p>Atur harga dan jenis sampah yang diterima</p>
                    </div>
                </div>

                <div class="content-grid">
                    <!-- Daftar Jenis Sampah -->
                    <div class="content-column">
                        <div class="dashboard-content">
                            <div class="content-section">
                                <div class="section-header">
                                    <h2>Daftar Jenis Sampah</h2>
                                </div>
                                
                                <?php if (empty($jenis_sampah)): ?>
                                    <div class="empty-state">
                                        <div class="icon">📦</div>
                                        <p>Belum ada jenis sampah yang ditambahkan</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-container">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Jenis Sampah</th>
                                                    <th>Harga per kg</th>
                                                    <th>Deskripsi</th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($jenis_sampah as $jenis): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($jenis['nama_jenis']); ?></strong>
                                                    </td>
                                                    <td style="font-weight: bold; color: var(--primary);">
                                                        <?php echo formatRupiah($jenis['harga_per_kg']); ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($jenis['deskripsi'] ?: '-'); ?></td>
                                                    <td>
                                                        <a href="manage-jenis-sampah.php?edit=<?php echo $jenis['id']; ?>" class="btn-action btn-edit">
                                                            Edit
                                                        </a>
                                                        <a href="manage-jenis-sampah.php?hapus=<?php echo $jenis['id']; ?>" class="btn-action btn-delete"
                                                           onclick="return confirm('Hapus jenis sampah <?php echo $jenis['nama_jenis']; ?>?')">
                                                            Hapus
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Form Tambah/Edit Jenis Sampah -->
                    <div class="content-column">
                        <div class="dashboard-content">
                            <div class="content-section">
                                <h2><?php echo $edit_data ? 'Edit Jenis Sampah' : 'Tambah Jenis Sampah Baru'; ?></h2>
                                <form method="POST" class="deposit-form">
                                    <?php if ($edit_data): ?>
                                        <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                                    <?php endif; ?>
                                    
                                    <div class="form-group">
                                        <label for="nama_jenis">Nama Jenis Sampah *</label>
                                        <input type="text" id="nama_jenis" name="nama_jenis" class="form-control" 
                                               placeholder="Contoh: Plastik PET, Kertas HVS" 
                                               value="<?php echo $edit_data ? htmlspecialchars($edit_data['nama_jenis']) : ''; ?>" 
                                               required>
                                    </div>

                                    <div class="form-group">
                                        <label for="harga_per_kg">Harga per kg (Rp) *</label>
                                        <input type="number" id="harga_per_kg" name="harga_per_kg" class="form-control" 
                                               min="100" step="100" placeholder="5000" 
                                               value="<?php echo $edit_data ? $edit_data['harga_per_kg'] : ''; ?>" 
                                               required>
                                    </div>

                                    <div class="form-group">
                                        <label for="deskripsi">Deskripsi</label>
                                        <textarea id="deskripsi" name="deskripsi" class="form-control" 
                                                  placeholder="Deskripsi jenis sampah..." rows="3"><?php echo $edit_data ? htmlspecialchars($edit_data['deskripsi']) : ''; ?></textarea>
                                    </div>

                                    <div class="form-actions">
                                        <button type="submit" name="<?php echo $edit_data ? 'edit_jenis' : 'tambah_jenis'; ?>" class="btn btn-primary btn-block">
                                            <?php echo $edit_data ? 'Update Jenis Sampah' : 'Tambah Jenis Sampah'; ?>
                                        </button>
                                        
                                        <?php if ($edit_data): ?>
                                            <a href="manage-jenis-sampah.php" class="btn btn-secondary btn-block" style="margin-top: 10px; text-align: center; display: block;">
                                                Batal Edit
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>

                            <!-- Informasi -->
                            <div class="content-section">
                                <div class="withdraw-info">
                                    <h4>💡 Tips Pengelolaan Jenis Sampah</h4>
                                    <ul style="margin-top: 0.5rem; padding-left: 1.2rem;">
                                        <li>Pastikan harga sesuai dengan pasar</li>
                                        <li>Gunakan nama yang mudah dipahami nasabah</li>
                                        <li>Update harga secara berkala</li>
                                        <li>Hapus jenis sampah yang sudah tidak diterima</li>
                                    </ul>
                                </div>
                            </div>
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
        // Auto-format harga input
        document.getElementById('harga_per_kg')?.addEventListener('blur', function() {
            if (this.value) {
                this.value = parseInt(this.value);
            }
        });
    </script>
</body>
</html>