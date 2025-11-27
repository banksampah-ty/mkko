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
            
            // UPDATE PENJEMPUTAN
            $keterangan_full = "Jenis Aktual: " . $jenis_sampah_aktual;
            if (!empty($keterangan)) {
                $keterangan_full .= " | " . $keterangan;
            }
            
            $stmt = $pdo->prepare("
                UPDATE penjemputan 
                SET berat = ?, keterangan = ?, status = 'selesai', waktu_selesai = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$berat_aktual, $keterangan_full, $id_penjemputan]);
            
            // SIMPAN KE TABEL PENIMBANGAN
            $stmt = $pdo->prepare("
                INSERT INTO penimbangan (id_penjemputan, id_mitra, berat, harga_per_kg, total_harga, keterangan)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$id_penjemputan, $mitra_id, $berat_aktual, $harga_per_kg, $total_harga, $keterangan_full]);
            
            // CARI ID JENIS SAMPAH
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
                // SIMPAN TRANSAKSI
                $stmt = $pdo->prepare("
                    INSERT INTO transaksi (user_id, jenis_sampah_id, berat, total_harga) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$penjemputan['user_id'], $jenis_id, $berat_aktual, $total_harga]);
                
                // UPDATE SALDO USER
                $new_saldo = $penjemputan['saldo'] + $total_harga;
                $stmt = $pdo->prepare("UPDATE users SET saldo = ? WHERE id = ?");
                $stmt->execute([$new_saldo, $penjemputan['user_id']]);
                
                // BUAT NOTIFIKASI UNTUK USER
                $judul_notifikasi = "Penjemputan Sampah Selesai";
                $pesan_notifikasi = "Penjemputan sampah " . $jenis_sampah_aktual . " seberat " . $berat_aktual . " kg telah selesai. Saldo bertambah: Rp " . number_format($total_harga, 0, ',', '.');
                
                $stmt = $pdo->prepare("
                    INSERT INTO notifikasi (user_id, judul, pesan) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$penjemputan['user_id'], $judul_notifikasi, $pesan_notifikasi]);
                
                $pdo->commit();
                
                $success = "✅ Verifikasi setoran berhasil! 
                           <br>• Berat: " . $berat_aktual . " kg
                           <br>• Total: Rp " . number_format($total_harga, 0, ',', '.') . "
                           <br>• Saldo warga: Rp " . number_format($new_saldo, 0, ',', '.');
                
                // REDIRECT KE HALAMAN MANAGE PENJEMPUTAN SETELAH BERHASIL
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

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Penjemputan - Bank Sampah</title>
    <style>
        /* STYLING SAMA SEPERTI SEBELUMNYA */
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
            max-width: 1200px;
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
            gap: 15px;
        }
        
        nav a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            padding: 8px 12px;
            border-radius: var(--border-radius);
            transition: all 0.3s;
            font-size: 0.9rem;
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
        
        /* Card Styles */
        .penjemputan-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
        }
        
        .penjemputan-card {
            background: var(--white);
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border-left: 4px solid var(--secondary);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .card-title {
            font-size: 1.2rem;
            color: var(--dark);
            font-weight: 600;
        }
        
        .card-meta {
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .card-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 15px;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .detail-label {
            color: var(--gray);
        }
        
        .detail-value {
            font-weight: 500;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 1rem;
        }
        
        /* Button Styles */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: var(--primary);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background: var(--primary-light);
        }
        
        .btn-success {
            background: var(--success);
            color: var(--white);
        }
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: var(--white);
            margin: 5% auto;
            padding: 0;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 500px;
            box-shadow: var(--shadow);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            background: var(--primary);
            color: var(--white);
        }
        
        .modal-title {
            margin: 0;
            color: var(--white);
        }
        
        .close {
            font-size: 24px;
            cursor: pointer;
            color: var(--white);
        }
        
        .modal-body {
            padding: 20px;
        }
        
        /* Alert Styles */
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            font-weight: 500;
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
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray);
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        /* Footer */
        footer {
            background-color: var(--white);
            padding: 20px 0;
            text-align: center;
            color: var(--gray);
            border-top: 1px solid #e0e0e0;
            margin-top: 30px;
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
            
            .penjemputan-cards {
                grid-template-columns: 1fr;
            }
            
            nav ul {
                flex-wrap: wrap;
                justify-content: center;
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
                        <li><a href="verifikasi-setoran.php">Verifikasi Setoran</a></li>
                        <li><a href="verifikasi-penjemputan.php" class="active">Verifikasi Penjemputan</a></li>
                        <li><a href="manage-penjemputan.php">Penjemputan</a></li>
                        <li><a href="riwayat-mitra.php">Riwayat</a></li>
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
                    <h1>⚖️ Verifikasi Penjemputan Sampah</h1>
                    <p>Timbang dan verifikasi sampah yang telah dijemput sebelum menyelesaikan proses</p>
                </div>
            </div>

            <!-- Penjemputan yang Perlu Diverifikasi -->
            <?php if (empty($penjemputan_diproses)): ?>
                <div class="empty-state">
                    <h3>🎉 Tidak ada penjemputan yang perlu diverifikasi</h3>
                    <p>Semua penjemputan sudah selesai atau belum ada yang dalam proses penjemputan.</p>
                </div>
            <?php else: ?>
                <div class="penjemputan-cards">
                    <?php foreach ($penjemputan_diproses as $penjemputan): ?>
                    <div class="penjemputan-card">
                        <div class="card-header">
                            <div class="card-title">
                                🗑️ <?php echo htmlspecialchars($penjemputan['jenis_sampah']); ?>
                            </div>
                            <span style="background: #fff3cd; color: #856404; padding: 5px 10px; border-radius: 12px; font-size: 0.8rem;">
                                🚚 Sedang Diproses
                            </span>
                        </div>
                        
                        <div class="card-meta">
                            👤 <?php echo htmlspecialchars($penjemputan['nama_warga']); ?> • 
                            📞 <?php echo htmlspecialchars($penjemputan['hp_warga'] ?? '-'); ?>
                        </div>
                        
                        <div class="card-details">
                            <div class="detail-item">
                                <span class="detail-label">📍 Alamat:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($penjemputan['alamat_penjemputan']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">📅 Waktu Penjemputan:</span>
                                <span class="detail-value">
                                    <?php echo $penjemputan['waktu_penjemputan'] ? date('d/m/Y H:i', strtotime($penjemputan['waktu_penjemputan'])) : 'Belum dijadwalkan'; ?>
                                </span>
                            </div>
                            <?php if ($penjemputan['keterangan']): ?>
                            <div class="detail-item">
                                <span class="detail-label">💬 Keterangan:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($penjemputan['keterangan']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <button class="btn btn-success btn-block" 
                                onclick="openVerifyModal(
                                    <?php echo $penjemputan['id']; ?>,
                                    '<?php echo htmlspecialchars($penjemputan['jenis_sampah']); ?>',
                                    '<?php echo htmlspecialchars($penjemputan['nama_warga']); ?>'
                                )">
                            ⚖️ Verifikasi & Selesaikan
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal Verifikasi Penjemputan -->
    <div id="verifyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">⚖️ Verifikasi Penjemputan</h2>
                <span class="close" onclick="closeModal('verifyModal')">&times;</span>
            </div>
            <form method="POST" class="modal-body">
                <input type="hidden" name="penjemputan_id" id="verify_penjemputan_id">
                <input type="hidden" name="action" value="verify">
                
                <div class="form-group">
                    <label>Nama Warga</label>
                    <input type="text" id="verify_nama_warga" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label>Jenis Sampah (Klaim)</label>
                    <input type="text" id="verify_jenis_sampah" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label for="jenis_sampah_id">Jenis Sampah Aktual *</label>
                    <select id="jenis_sampah_id" name="jenis_sampah_id" class="form-control" required onchange="updateHarga()">
                        <option value="">Pilih Jenis Sampah</option>
                        <?php foreach ($jenis_sampah as $jenis): ?>
                        <option value="<?php echo $jenis['id']; ?>" data-harga="<?php echo $jenis['harga_per_kg']; ?>">
                            <?php echo htmlspecialchars($jenis['nama_jenis']); ?> - <?php echo formatRupiah($jenis['harga_per_kg']); ?>/kg
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="berat_aktual">Berat Aktual (kg) *</label>
                    <input type="number" id="berat_aktual" name="berat_aktual" class="form-control" 
                           step="0.01" min="0.01" required oninput="calculateTotal()">
                </div>
                
                <div class="form-group">
                    <label for="harga_per_kg">Harga per Kg *</label>
                    <input type="number" id="harga_per_kg" name="harga_per_kg" class="form-control" 
                           step="100" min="0" required oninput="calculateTotal()">
                </div>
                
                <div class="form-group">
                    <label>Total yang akan dibayarkan</label>
                    <input type="text" id="verify_total" class="form-control" readonly style="font-weight: bold; color: var(--primary); font-size: 1.1rem;">
                </div>
                
                <div class="form-group">
                    <label for="catatan">Catatan (Opsional)</label>
                    <textarea id="catatan" name="catatan" class="form-control" rows="3" placeholder="Catatan untuk warga..."></textarea>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="verifikasi_penjemputan" class="btn btn-success" style="flex: 1;">
                        💰 Konfirmasi & Bayar
                    </button>
                    <button type="button" class="btn" onclick="closeModal('verifyModal')" style="background: #6c757d; color: white;">
                        ❌ Batal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Bank Sampah. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // MODAL FUNCTIONS
        function openVerifyModal(penjemputanId, jenisSampah, namaWarga) {
            document.getElementById('verify_penjemputan_id').value = penjemputanId;
            document.getElementById('verify_jenis_sampah').value = jenisSampah;
            document.getElementById('verify_nama_warga').value = namaWarga;
            document.getElementById('verifyModal').style.display = 'block';
            
            // Reset form
            document.getElementById('jenis_sampah_id').value = '';
            document.getElementById('berat_aktual').value = '';
            document.getElementById('harga_per_kg').value = '';
            document.getElementById('verify_total').value = 'Rp 0';
            document.getElementById('catatan').value = '';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // TUTUP MODAL JIKA KLIK DI LUAR
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let modal of modals) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            }
        }
        
        // UPDATE HARGA OTOMATIS
        function updateHarga() {
            const jenisSelect = document.getElementById('jenis_sampah_id');
            const hargaInput = document.getElementById('harga_per_kg');
            const selectedOption = jenisSelect.options[jenisSelect.selectedIndex];
            
            if (selectedOption.value) {
                const harga = selectedOption.getAttribute('data-harga');
                hargaInput.value = harga;
                calculateTotal();
            }
        }
        
        // KALKULASI TOTAL
        function calculateTotal() {
            const berat = parseFloat(document.getElementById('berat_aktual').value) || 0;
            const harga = parseFloat(document.getElementById('harga_per_kg').value) || 0;
            const totalInput = document.getElementById('verify_total');
            
            if (berat > 0 && harga > 0) {
                const total = berat * harga;
                totalInput.value = 'Rp ' + total.toLocaleString('id-ID');
            } else {
                totalInput.value = 'Rp 0';
            }
        }
        
        // AUTO HIDE ALERT
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>