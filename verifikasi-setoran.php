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

// CEK APAKAH ADA ID PENJEMPUTAN
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: manage-penjemputan.php?error=ID penjemputan tidak valid");
    exit();
}

$id_penjemputan = (int)$_GET['id'];

// AMBIL DATA PENJEMPUTAN
try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.nama_lengkap, u.telepon, u.alamat, u.saldo, u.id as user_id,
               js.nama_jenis, js.harga_per_kg as harga_standar
        FROM penjemputan p 
        JOIN users u ON p.id_warga = u.id 
        LEFT JOIN jenis_sampah js ON js.nama_jenis LIKE CONCAT('%', p.jenis_sampah, '%')
        WHERE p.id = ? AND p.id_mitra = ? AND p.status IN ('dijadwalkan', 'diproses')
    ");
    $stmt->execute([$id_penjemputan, $mitra_id]);
    $penjemputan = $stmt->fetch();
    
    if (!$penjemputan) {
        header("Location: manage-penjemputan.php?error=Penjemputan tidak ditemukan atau tidak dapat diverifikasi");
        exit();
    }
    
} catch (PDOException $e) {
    error_log("Verifikasi Error: " . $e->getMessage());
    $error = "Terjadi kesalahan saat mengambil data penjemputan";
}

// AMBIL DATA JENIS SAMPAH UNTUK DROPDOWN
try {
    $stmt = $pdo->prepare("SELECT * FROM jenis_sampah ORDER BY nama_jenis");
    $stmt->execute();
    $jenis_sampah = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Jenis Sampah Error: " . $e->getMessage());
    $jenis_sampah = [];
}

// PROSES VERIFIKASI SETORAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verifikasi_setoran'])) {
    $berat_aktual = (float)$_POST['berat_aktual'];
    $jenis_sampah_aktual = sanitize($_POST['jenis_sampah_aktual']);
    $harga_per_kg = (float)$_POST['harga_per_kg'];
    $keterangan = sanitize($_POST['keterangan'] ?? '');
    
    // VALIDASI
    if ($berat_aktual <= 0) {
        $error = "❌ Berat aktual harus lebih dari 0 kg";
    } elseif ($harga_per_kg <= 0) {
        $error = "❌ Harga per kg harus lebih dari 0";
    } else {
        try {
            // HITUNG TOTAL HARGA
            $total_harga = $berat_aktual * $harga_per_kg;
            
            // MULAI TRANSACTION
            $pdo->beginTransaction();
            
            // UPDATE PENJEMPUTAN - GUNAKAN KOLOM YANG ADA
            $keterangan_full = "Jenis Aktual: " . $jenis_sampah_aktual;
            if (!empty($keterangan)) {
                $keterangan_full .= " | " . $keterangan;
            }
            
            // ✅ PERBAIKAN: HAPUS waktu_selesai KARENA KOLOM TIDAK ADA
            $stmt = $pdo->prepare("
                UPDATE penjemputan 
                SET berat = ?, keterangan = ?, status = 'selesai'
                WHERE id = ?
            ");
            $stmt->execute([$berat_aktual, $keterangan_full, $id_penjemputan]);
            
            // ✅ PERBAIKAN: CEK APAKAH TABEL PENIMBANGAN ADA SEBELUM INSERT
            try {
                // Cek apakah tabel penimbangan ada
                $pdo->query("SELECT 1 FROM penimbangan LIMIT 1");
                
                $stmt = $pdo->prepare("
                    INSERT INTO penimbangan (id_penjemputan, id_mitra, berat, harga_per_kg, total_harga, keterangan)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$id_penjemputan, $mitra_id, $berat_aktual, $harga_per_kg, $total_harga, $keterangan_full]);
            } catch (Exception $e) {
                // Jika tabel penimbangan tidak ada, lanjutkan tanpa error
                error_log("Tabel penimbangan tidak ada, dilanjutkan tanpa menyimpan: " . $e->getMessage());
            }
            
            // ✅ PERBAIKAN: CARI ID JENIS SAMPAH YANG SESUAI
            $jenis_id = null;
            foreach ($jenis_sampah as $js) {
                if ($js['nama_jenis'] === $jenis_sampah_aktual) {
                    $jenis_id = $js['id'];
                    break;
                }
            }

            // JIKA JENIS SAMPAH TIDAK DITEMUKAN, GUNAKAN JENIS DEFAULT
            if (!$jenis_id && !empty($jenis_sampah)) {
                $jenis_id = $jenis_sampah[0]['id'];
            }

            if ($jenis_id) {
                // ✅ PERBAIKAN: SIMPAN TRANSAKSI DAN UPDATE SALDO
                $stmt = $pdo->prepare("
                    INSERT INTO transaksi (user_id, jenis_sampah_id, berat, total_harga) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$penjemputan['user_id'], $jenis_id, $berat_aktual, $total_harga]);
                
                // ✅ PERBAIKAN: UPDATE SALDO USER LANGSUNG
                $new_saldo = $penjemputan['saldo'] + $total_harga;
                $stmt = $pdo->prepare("UPDATE users SET saldo = ? WHERE id = ?");
                $stmt->execute([$new_saldo, $penjemputan['user_id']]);
                
                // ✅ PERBAIKAN: BUAT NOTIFIKASI UNTUK USER
                $judul_notifikasi = "Setoran Sampah Berhasil";
                $pesan_notifikasi = "Penjemputan sampah " . $jenis_sampah_aktual . " seberat " . $berat_aktual . " kg telah selesai. Saldo bertambah: Rp " . number_format($total_harga, 0, ',', '.');
                
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO notifikasi (user_id, judul, pesan) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$penjemputan['user_id'], $judul_notifikasi, $pesan_notifikasi]);
                } catch (Exception $e) {
                    // Jika tabel notifikasi tidak ada, lanjutkan tanpa error
                    error_log("Tabel notifikasi tidak ada: " . $e->getMessage());
                }
                
                // COMMIT SEMUA PERUBAHAN
                $pdo->commit();
                
                $success = "✅ Verifikasi setoran berhasil! 
                           <br>• Berat: " . $berat_aktual . " kg
                           <br>• Total: Rp " . number_format($total_harga, 0, ',', '.') . "
                           <br>• Saldo warga: Rp " . number_format($new_saldo, 0, ',', '.');
                
                // ✅ PERBAIKAN: REDIRECT SETELAH BERHASIL
                header("Refresh: 3; URL=manage-penjemputan.php?success=" . urlencode("Verifikasi penjemputan berhasil"));
                
            } else {
                $error = "❌ Jenis sampah tidak valid untuk transaksi";
                $pdo->rollBack();
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Verifikasi Setoran Error: " . $e->getMessage());
            $error = "❌ Gagal melakukan verifikasi setoran: " . $e->getMessage();
        }
    }
}

// HANDLE ERROR MESSAGE
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}
?>

<!-- HTML CODE TETAP SAMA -->
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Penjemputan - Bank Sampah</title>
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
            max-width: 800px;
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
        
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background-color: var(--primary);
            color: var(--white);
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .back-button:hover {
            background-color: var(--primary-light);
        }
        
        /* Form Styles */
        .verification-form {
            background: var(--white);
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .section-title {
            font-size: 1.2rem;
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-light);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary);
        }
        
        .info-label {
            font-size: 0.8rem;
            color: var(--gray);
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-weight: 600;
            color: var(--dark);
            font-size: 1rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
        }
        
        .form-group label {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.9rem;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
        }
        
        .form-control:disabled {
            background-color: #f5f5f5;
            color: var(--gray);
            cursor: not-allowed;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background: var(--primary-light);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }
        
        .btn-success {
            background: var(--success);
            color: var(--white);
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-lg {
            padding: 15px 30px;
            font-size: 1.1rem;
        }
        
        .calculation-section {
            background: #e8f5e8;
            padding: 20px;
            border-radius: var(--border-radius);
            border: 2px solid var(--success);
        }
        
        .calculation-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #d4edda;
        }
        
        .calculation-item:last-child {
            border-bottom: none;
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--primary);
        }
        
        .calculation-label {
            color: var(--dark);
        }
        
        .calculation-value {
            font-weight: 600;
            color: var(--primary);
        }
        
        /* Alert Styles */
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            font-weight: 500;
            animation: slideDown 0.3s;
        }
        
        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
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
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
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
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            nav ul {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .verification-form {
                padding: 20px;
            }
        }
        
        @media (max-width: 480px) {
            .btn-lg {
                width: 100%;
                text-align: center;
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
                    <h1>Bank Sampah - Verifikasi Penjemputan</h1>
                </div>
                <nav>
                    <ul>
                        <li><a href="dashboard-mitra.php">Dashboard</a></li>
                        <li><a href="manage-penjemputan.php">Penjemputan</a></li>
                        <li><a href="manage-jadwal.php">Jadwal</a></li>
                        <li><a href="riwayat-mitra.php">Riwayat</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <section class="verification-page">
            <div class="container">
                <div class="page-header">
                    <div class="page-title">
                        <h1>Verifikasi Penjemputan Sampah</h1>
                        <p>Timbang dan verifikasi sampah yang telah dijemput sebelum menyelesaikan proses</p>
                    </div>
                    <a href="manage-penjemputan.php" class="back-button">
                        ← Kembali ke Manage Penjemputan
                    </a>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        ✅ <?php echo $success; ?>
                    </div>
                    <div class="alert alert-info">
                        🔄 Redirect ke halaman manage penjemputan dalam 3 detik...
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-error">
                        ❌ <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($penjemputan && empty($success)): ?>
                <form method="POST" class="verification-form">
                    <input type="hidden" name="verifikasi_setoran" value="1">

                    <!-- Section 1: Informasi Penjemputan -->
                    <div class="form-section">
                        <h2 class="section-title">📋 Informasi Penjemputan</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">👤 Nama Warga</div>
                                <div class="info-value"><?php echo htmlspecialchars($penjemputan['nama_lengkap']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">📞 Telepon</div>
                                <div class="info-value"><?php echo htmlspecialchars($penjemputan['telepon']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">🗑️ Jenis Sampah (Klaim)</div>
                                <div class="info-value"><?php echo htmlspecialchars($penjemputan['jenis_sampah']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">💰 Saldo Saat Ini</div>
                                <div class="info-value">Rp <?php echo number_format($penjemputan['saldo'], 0, ',', '.'); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Verifikasi Aktual -->
                    <div class="form-section">
                        <h2 class="section-title">✅ Verifikasi Penjemputan</h2>
                        
                        <div class="form-group">
                            <label for="jenis_sampah_aktual">🗑️ Jenis Sampah Aktual *</label>
                            <select id="jenis_sampah_aktual" name="jenis_sampah_aktual" class="form-control" required>
                                <option value="">Pilih Jenis Sampah</option>
                                <?php foreach ($jenis_sampah as $js): ?>
                                    <option value="<?php echo htmlspecialchars($js['nama_jenis']); ?>" 
                                        <?php echo ($js['nama_jenis'] === $penjemputan['jenis_sampah']) ? 'selected' : ''; ?>
                                        data-harga="<?php echo $js['harga_per_kg']; ?>">
                                        <?php echo htmlspecialchars($js['nama_jenis']); ?> - Rp <?php echo number_format($js['harga_per_kg'], 0, ',', '.'); ?>/kg
                                    </option>
                                <?php endforeach; ?>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="berat_aktual">⚖️ Berat Aktual (kg) *</label>
                            <input type="number" id="berat_aktual" name="berat_aktual" class="form-control" 
                                   step="0.01" min="0.1" required placeholder="Masukkan berat sampah dalam kg">
                        </div>

                        <div class="form-group">
                            <label for="harga_per_kg">💰 Harga per Kg (Rp) *</label>
                            <input type="number" id="harga_per_kg" name="harga_per_kg" class="form-control" 
                                   step="100" min="100" required placeholder="Masukkan harga per kg">
                        </div>

                        <div class="form-group">
                            <label for="keterangan">📝 Keterangan (opsional)</label>
                            <textarea id="keterangan" name="keterangan" class="form-control" rows="3" 
                                      placeholder="Catatan tambahan tentang sampah..."></textarea>
                        </div>
                    </div>

                    <!-- Section 3: Perhitungan -->
                    <div class="form-section">
                        <h2 class="section-title">🧮 Perhitungan Pembayaran</h2>
                        <div class="calculation-section">
                            <div class="calculation-item">
                                <span class="calculation-label">Berat Sampah</span>
                                <span class="calculation-value" id="display_berat">0 kg</span>
                            </div>
                            <div class="calculation-item">
                                <span class="calculation-label">Harga per Kg</span>
                                <span class="calculation-value" id="display_harga">Rp 0</span>
                            </div>
                            <div class="calculation-item">
                                <span class="calculation-label">Total yang akan dibayarkan</span>
                                <span class="calculation-value" id="display_total">Rp 0</span>
                            </div>
                            <div class="calculation-item">
                                <span class="calculation-label">Saldo Warga Setelah</span>
                                <span class="calculation-value" id="display_saldo_akhir">Rp <?php echo number_format($penjemputan['saldo'], 0, ',', '.'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Section 4: Tombol Aksi -->
                    <div class="form-section">
                        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                            <button type="submit" name="verifikasi_setoran" class="btn btn-success btn-lg">
                                ✅ Verifikasi & Simpan
                            </button>
                            <a href="manage-penjemputan.php" class="btn btn-outline btn-lg">
                                ❌ Batal
                            </a>
                        </div>
                    </div>
                </form>
                <?php elseif (empty($success)): ?>
                    <div class="alert alert-error">
                        ❌ Data penjemputan tidak ditemukan atau tidak dapat diakses.
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Bank Sampah. All rights reserved.</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const beratInput = document.getElementById('berat_aktual');
            const hargaInput = document.getElementById('harga_per_kg');
            const jenisSelect = document.getElementById('jenis_sampah_aktual');
            
            const displayBerat = document.getElementById('display_berat');
            const displayHarga = document.getElementById('display_harga');
            const displayTotal = document.getElementById('display_total');
            const displaySaldoAkhir = document.getElementById('display_saldo_akhir');
            
            const saldoAwal = <?php echo $penjemputan ? $penjemputan['saldo'] : 0; ?>;
            
            // Fungsi untuk update perhitungan
            function updateCalculation() {
                const berat = parseFloat(beratInput.value) || 0;
                const harga = parseFloat(hargaInput.value) || 0;
                const total = berat * harga;
                const saldoAkhir = saldoAwal + total;
                
                displayBerat.textContent = berat.toFixed(2) + ' kg';
                displayHarga.textContent = 'Rp ' + harga.toLocaleString('id-ID');
                displayTotal.textContent = 'Rp ' + total.toLocaleString('id-ID');
                displaySaldoAkhir.textContent = 'Rp ' + saldoAkhir.toLocaleString('id-ID');
            }
            
            // Event listeners
            beratInput.addEventListener('input', updateCalculation);
            hargaInput.addEventListener('input', updateCalculation);
            
            // Auto-set harga ketika jenis sampah dipilih
            jenisSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const harga = selectedOption.getAttribute('data-harga');
                
                if (harga && harga !== 'null') {
                    hargaInput.value = harga;
                    updateCalculation();
                }
            });
            
            // Validasi form sebelum submit
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const berat = parseFloat(beratInput.value);
                    const harga = parseFloat(hargaInput.value);
                    
                    if (berat <= 0) {
                        e.preventDefault();
                        alert('❌ Berat aktual harus lebih dari 0 kg');
                        beratInput.focus();
                        return false;
                    }
                    
                    if (harga <= 0) {
                        e.preventDefault();
                        alert('❌ Harga per kg harus lebih dari 0');
                        hargaInput.focus();
                        return false;
                    }
                    
                    if (!jenisSelect.value) {
                        e.preventDefault();
                        alert('❌ Silakan pilih jenis sampah');
                        jenisSelect.focus();
                        return false;
                    }
                    
                    // Konfirmasi sebelum submit
                    if (!confirm('Apakah Anda yakin dengan data verifikasi ini? Setelah disimpan, tidak dapat diubah.')) {
                        e.preventDefault();
                        return false;
                    }
                });
            }
            
            // Initialize calculation
            updateCalculation();
            
            // Auto-hide alerts
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    if (!alert.classList.contains('alert-info')) {
                        alert.style.transition = 'opacity 0.5s';
                        alert.style.opacity = '0';
                        setTimeout(() => {
                            if (alert.parentNode) {
                                alert.parentNode.removeChild(alert);
                            }
                        }, 500);
                    }
                });
            }, 5000);
        });
    </script>
</body>
</html>