<?php
include 'config.php';

// CEK LOGIN DAN ROLE ADMIN
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

// HANDLE ACTIONS
if (isset($_GET['verify_mitra'])) {
    $mitra_id = intval($_GET['verify_mitra']);
    try {
        $stmt = $pdo->prepare("UPDATE mitra SET status_verifikasi = 'verified' WHERE id = ?");
        $stmt->execute([$mitra_id]);
        setFlashMessage('success', 'Mitra berhasil diverifikasi!');
    } catch (PDOException $e) {
        setFlashMessage('error', 'Gagal memverifikasi mitra: ' . $e->getMessage());
    }
    redirect('manage-mitra.php');
}

if (isset($_GET['reject_mitra'])) {
    $mitra_id = intval($_GET['reject_mitra']);
    try {
        $stmt = $pdo->prepare("UPDATE mitra SET status_verifikasi = 'rejected' WHERE id = ?");
        $stmt->execute([$mitra_id]);
        setFlashMessage('success', 'Mitra berhasil ditolak!');
    } catch (PDOException $e) {
        setFlashMessage('error', 'Gagal menolak mitra: ' . $e->getMessage());
    }
    redirect('manage-mitra.php');
}

if (isset($_GET['delete_mitra'])) {
    $mitra_id = intval($_GET['delete_mitra']);
    try {
        $stmt = $pdo->prepare("DELETE FROM mitra WHERE id = ?");
        $stmt->execute([$mitra_id]);
        setFlashMessage('success', 'Mitra berhasil dihapus!');
    } catch (PDOException $e) {
        setFlashMessage('error', 'Gagal menghapus mitra: ' . $e->getMessage());
    }
    redirect('manage-mitra.php');
}

// FILTER DAN PENCARIAN
$filter_status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// BUILD QUERY
$query = "SELECT * FROM mitra WHERE 1=1";
$params = [];

if ($filter_status !== 'all') {
    $query .= " AND status_verifikasi = ?";
    $params[] = $filter_status;
}

if (!empty($search)) {
    $query .= " AND (nama_mitra LIKE ? OR username LIKE ? OR email LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY created_at DESC";

// PAGINATION
$items_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// HITUNG TOTAL DATA
$count_query = "SELECT COUNT(*) as total FROM mitra WHERE 1=1";
$count_params = $params;

if ($filter_status !== 'all') {
    $count_query .= " AND status_verifikasi = ?";
}

if (!empty($search)) {
    $count_query .= " AND (nama_mitra LIKE ? OR username LIKE ? OR email LIKE ?)";
}

$stmt = $pdo->prepare($count_query);
$stmt->execute($count_params);
$total_items = $stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);

// AMBIL DATA DENGAN PAGINATION
$query .= " LIMIT ? OFFSET ?";
$params[] = $items_per_page;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$mitra_list = $stmt->fetchAll();

// STATISTIK MITRA
$stmt = $pdo->query("
    SELECT 
        status_verifikasi,
        COUNT(*) as total
    FROM mitra 
    GROUP BY status_verifikasi
");
$stats = $stmt->fetchAll();

// HITUNG TOTAL PER STATUS
$total_verified = 0;
$total_pending = 0;
$total_rejected = 0;

foreach ($stats as $stat) {
    switch ($stat['status_verifikasi']) {
        case 'verified':
            $total_verified = $stat['total'];
            break;
        case 'pending':
            $total_pending = $stat['total'];
            break;
        case 'rejected':
            $total_rejected = $stat['total'];
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Mitra - Bank Sampah</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <span class="logo-icon">♻️</span>
                    <h1>Bank Sampah - Kelola Mitra</h1>
                </div>
                <nav>
                    <ul>
                        <li><a href="dashboard-admin.php">Dashboard</a></li>
                        <li><a href="manage-users.php">Kelola Warga</a></li>
                        <li><a href="manage-mitra.php" class="active">Kelola Mitra</a></li>
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
                <!-- HEADER -->
                <div class="dashboard-header">
                    <div class="dashboard-title">
                        <h1>Kelola Mitra Pengumpul Sampah</h1>
                        <p>Manajemen data mitra pengumpul sampah</p>
                    </div>
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['nama_lengkap'], 0, 2)); ?>
                        </div>
                        <div class="user-details">
                            <div class="username"><?php echo $_SESSION['nama_lengkap']; ?></div>
                            <div class="role">Administrator</div>
                        </div>
                    </div>
                </div>

                <!-- FLASH MESSAGES -->
                <?php
                $flashMessage = getFlashMessage();
                if ($flashMessage): ?>
                    <div class="alert alert-<?php echo $flashMessage['type']; ?>">
                        <?php echo $flashMessage['message']; ?>
                    </div>
                <?php endif; ?>

                <!-- STATS MITRA -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">🤝</div>
                        <div class="stat-info">
                            <h3>Total Mitra</h3>
                            <p class="stat-number"><?php echo ($total_verified + $total_pending + $total_rejected); ?></p>
                            <small>Semua mitra</small>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">✅</div>
                        <div class="stat-info">
                            <h3>Terverifikasi</h3>
                            <p class="stat-number"><?php echo $total_verified; ?></p>
                            <small>Aktif</small>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">⏳</div>
                        <div class="stat-info">
                            <h3>Menunggu</h3>
                            <p class="stat-number"><?php echo $total_pending; ?></p>
                            <small>Perlu verifikasi</small>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">❌</div>
                        <div class="stat-info">
                            <h3>Ditolak</h3>
                            <p class="stat-number"><?php echo $total_rejected; ?></p>
                            <small>Tidak disetujui</small>
                        </div>
                    </div>
                </div>

                <!-- FILTER DAN PENCARIAN -->
                <div class="content-section">
                    <div class="section-header">
                        <h2>Daftar Mitra</h2>
                        <div style="display: flex; gap: 1rem; align-items: center;">
                            <form method="GET" style="display: flex; gap: 0.5rem; align-items: center;">
                                <select name="status" class="form-control" style="width: auto;" onchange="this.form.submit()">
                                    <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Semua Status</option>
                                    <option value="verified" <?php echo $filter_status === 'verified' ? 'selected' : ''; ?>>Terverifikasi</option>
                                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Menunggu</option>
                                    <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Ditolak</option>
                                </select>
                                
                                <input type="text" name="search" class="form-control" placeholder="Cari mitra..." 
                                       value="<?php echo htmlspecialchars($search); ?>" style="width: 250px;">
                                
                                <button type="submit" class="btn btn-primary">Cari</button>
                                <a href="manage-mitra.php" class="btn btn-outline">Reset</a>
                            </form>
                        </div>
                    </div>

                    <!-- TABEL MITRA -->
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Nama Mitra</th>
                                    <th>Username</th>
                                    <th>Kontak</th>
                                    <th>Alamat</th>
                                    <th>Status</th>
                                    <th>Tanggal Daftar</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($mitra_list)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 2rem;">
                                            <div class="empty-state">
                                                <p>Tidak ada data mitra</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($mitra_list as $mitra): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($mitra['nama_mitra']); ?></strong>
                                            <?php if ($mitra['dokumen_izin']): ?>
                                                <br><small class="badge">📎 Ada dokumen</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>@<?php echo htmlspecialchars($mitra['username']); ?></td>
                                        <td>
                                            <small><?php echo htmlspecialchars($mitra['email']); ?></small><br>
                                            <small><?php echo htmlspecialchars($mitra['no_hp']); ?></small>
                                        </td>
                                        <td>
                                            <small><?php echo strlen($mitra['alamat']) > 50 ? substr($mitra['alamat'], 0, 50) . '...' : $mitra['alamat']; ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            $status_text = '';
                                            switch ($mitra['status_verifikasi']) {
                                                case 'verified':
                                                    $status_class = 'status-approved';
                                                    $status_text = 'Terverifikasi';
                                                    break;
                                                case 'pending':
                                                    $status_class = 'status-pending';
                                                    $status_text = 'Menunggu';
                                                    break;
                                                case 'rejected':
                                                    $status_class = 'status-rejected';
                                                    $status_text = 'Ditolak';
                                                    break;
                                            }
                                            ?>
                                            <span class="status-badge <?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo date('d/m/Y', strtotime($mitra['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 0.25rem; flex-wrap: wrap;">
                                                <?php if ($mitra['status_verifikasi'] === 'pending'): ?>
                                                    <a href="?verify_mitra=<?php echo $mitra['id']; ?>" 
                                                       class="btn-action btn-success"
                                                       onclick="return confirm('Verifikasi mitra <?php echo $mitra['nama_mitra']; ?>?')"
                                                       title="Verifikasi">
                                                        ✓
                                                    </a>
                                                    <a href="?reject_mitra=<?php echo $mitra['id']; ?>" 
                                                       class="btn-action btn-danger"
                                                       onclick="return confirm('Tolak mitra <?php echo $mitra['nama_mitra']; ?>?')"
                                                       title="Tolak">
                                                        ✗
                                                    </a>
                                                <?php elseif ($mitra['status_verifikasi'] === 'rejected'): ?>
                                                    <a href="?verify_mitra=<?php echo $mitra['id']; ?>" 
                                                       class="btn-action btn-success"
                                                       onclick="return confirm('Verifikasi mitra <?php echo $mitra['nama_mitra']; ?>?')"
                                                       title="Verifikasi">
                                                        ✓
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <!-- Modal Trigger untuk Detail -->
                                                <button type="button" 
                                                        class="btn-action"
                                                        style="background: #17a2b8; color: white;"
                                                        onclick="showMitraDetail(<?php echo htmlspecialchars(json_encode($mitra)); ?>)"
                                                        title="Detail">
                                                    👁️
                                                </button>
                                                
                                                <a href="?delete_mitra=<?php echo $mitra['id']; ?>" 
                                                   class="btn-action btn-danger"
                                                   onclick="return confirm('Hapus permanen mitra <?php echo $mitra['nama_mitra']; ?>?')"
                                                   title="Hapus">
                                                    🗑️
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAGINATION -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination" style="margin-top: 1rem; display: flex; justify-content: center; gap: 0.5rem;">
                        <?php if ($current_page > 1): ?>
                            <a href="?page=<?php echo $current_page - 1; ?>&status=<?php echo $filter_status; ?>&search=<?php echo urlencode($search); ?>" 
                               class="btn btn-outline">‹ Prev</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == $current_page): ?>
                                <span class="btn btn-primary"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&status=<?php echo $filter_status; ?>&search=<?php echo urlencode($search); ?>" 
                                   class="btn btn-outline"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?php echo $current_page + 1; ?>&status=<?php echo $filter_status; ?>&search=<?php echo urlencode($search); ?>" 
                               class="btn btn-outline">Next ›</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div style="text-align: center; margin-top: 1rem; color: #666;">
                        Menampilkan <?php echo count($mitra_list); ?> dari <?php echo $total_items; ?> mitra
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- MODAL DETAIL MITRA -->
    <div id="detailModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5);">
        <div class="modal-content" style="background: white; margin: 5% auto; padding: 2rem; border-radius: 10px; width: 80%; max-width: 600px; max-height: 80vh; overflow-y: auto;">
            <div class="modal-header" style="display: flex; justify-content: between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid #ddd; padding-bottom: 1rem;">
                <h2>Detail Mitra</h2>
                <button onclick="closeModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            <div id="modalBody">
                <!-- Detail akan diisi oleh JavaScript -->
            </div>
            <div class="modal-footer" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #ddd; text-align: right;">
                <button onclick="closeModal()" class="btn btn-outline">Tutup</button>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; 2025 Bank Sampah. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // MODAL FUNCTIONS
        function showMitraDetail(mitra) {
            const modal = document.getElementById('detailModal');
            const modalBody = document.getElementById('modalBody');
            
            const statusText = {
                'verified': 'Terverifikasi',
                'pending': 'Menunggu Verifikasi', 
                'rejected': 'Ditolak'
            };
            
            const statusClass = {
                'verified': 'status-approved',
                'pending': 'status-pending',
                'rejected': 'status-rejected'
            };
            
            modalBody.innerHTML = `
                <div class="detail-section">
                    <h3>Informasi Umum</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>Nama Mitra:</label>
                            <span>${mitra.nama_mitra}</span>
                        </div>
                        <div class="detail-item">
                            <label>Username:</label>
                            <span>@${mitra.username}</span>
                        </div>
                        <div class="detail-item">
                            <label>Status:</label>
                            <span class="status-badge ${statusClass[mitra.status_verifikasi]}">
                                ${statusText[mitra.status_verifikasi]}
                            </span>
                        </div>
                        <div class="detail-item">
                            <label>Tanggal Daftar:</label>
                            <span>${new Date(mitra.created_at).toLocaleDateString('id-ID')}</span>
                        </div>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h3>Kontak</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>Email:</label>
                            <span>${mitra.email}</span>
                        </div>
                        <div class="detail-item">
                            <label>No. HP:</label>
                            <span>${mitra.no_hp}</span>
                        </div>
                        ${mitra.no_rekening ? `
                        <div class="detail-item">
                            <label>No. Rekening:</label>
                            <span>${mitra.no_rekening}</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
                
                <div class="detail-section">
                    <h3>Alamat</h3>
                    <p>${mitra.alamat}</p>
                </div>
                
                ${mitra.dokumen_izin || mitra.ktp_pemilik ? `
                <div class="detail-section">
                    <h3>Dokumen</h3>
                    <div class="detail-grid">
                        ${mitra.dokumen_izin ? `
                        <div class="detail-item">
                            <label>Dokumen Izin:</label>
                            <span>${mitra.dokumen_izin}</span>
                        </div>
                        ` : ''}
                        ${mitra.ktp_pemilik ? `
                        <div class="detail-item">
                            <label>KTP Pemilik:</label>
                            <span>${mitra.ktp_pemilik}</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
                ` : ''}
            `;
            
            modal.style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('detailModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('detailModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>

    <style>
        .detail-section {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .detail-section:last-child {
            border-bottom: none;
        }
        
        .detail-section h3 {
            color: #2e7d32;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-item label {
            font-weight: 600;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .detail-item span {
            color: #333;
        }
        
        .pagination {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin-top: 1rem;
        }
        
        .badge {
            background: #007bff;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.7rem;
        }
        
        @media (max-width: 768px) {
            .detail-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
        }
    </style>
</body>
</html>