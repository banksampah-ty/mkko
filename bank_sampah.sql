-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 20 Nov 2025 pada 12.59
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bank_sampah`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `feedback_mitra`
--

CREATE TABLE `feedback_mitra` (
  `id` int(11) NOT NULL,
  `mitra_id` int(11) NOT NULL,
  `jenis_feedback` varchar(50) NOT NULL,
  `prioritas` enum('low','medium','high') DEFAULT 'medium',
  `judul` varchar(255) NOT NULL,
  `pesan` text NOT NULL,
  `status` enum('pending','dibaca','diproses','selesai') DEFAULT 'pending',
  `tanggapan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `jenis_sampah`
--

CREATE TABLE `jenis_sampah` (
  `id` int(11) NOT NULL,
  `nama_jenis` varchar(50) NOT NULL,
  `harga_per_kg` decimal(10,2) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `jenis_sampah`
--

INSERT INTO `jenis_sampah` (`id`, `nama_jenis`, `harga_per_kg`, `deskripsi`, `created_at`) VALUES
(1, 'Plastik', 5000.00, 'Botol plastik, kemasan plastik, gelas plastik', '2025-11-17 12:44:22'),
(2, 'Kertas', 3000.00, 'Kertas koran, kardus, buku, majalah', '2025-11-17 12:44:22'),
(3, 'Kaleng', 4000.00, 'Kaleng aluminium, kaleng besi, kaleng minuman', '2025-11-17 12:44:22'),
(4, 'Botol Kaca', 2000.00, 'Botol kaca berbagai ukuran dan warna', '2025-11-17 12:44:22'),
(5, 'Elektronik', 8000.00, 'Barang elektronik rusak, kabel, baterai', '2025-11-17 12:44:22'),
(6, 'Logam', 6000.00, 'Besi, tembaga, aluminium, kuningan', '2025-11-17 12:44:22'),
(8, 'hvs', 4000.00, 'j', '2025-11-19 15:53:58');

-- --------------------------------------------------------

--
-- Struktur dari tabel `metode_pembayaran`
--

CREATE TABLE `metode_pembayaran` (
  `id` int(11) NOT NULL,
  `nama_metode` varchar(50) NOT NULL,
  `kode_metode` varchar(20) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `biaya_admin` decimal(15,2) DEFAULT 0.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `metode_pembayaran`
--

INSERT INTO `metode_pembayaran` (`id`, `nama_metode`, `kode_metode`, `deskripsi`, `biaya_admin`, `status`, `created_at`, `updated_at`) VALUES
(1, 'GoPay', 'GOPAY', 'Transfer ke GoPay', 1000.00, 'active', '2025-11-17 14:52:10', '2025-11-17 14:52:10'),
(2, 'OVO', 'OVO', 'Transfer ke OVO', 1000.00, 'active', '2025-11-17 14:52:10', '2025-11-17 14:52:10'),
(3, 'DANA', 'DANA', 'Transfer ke DANA', 1000.00, 'active', '2025-11-17 14:52:10', '2025-11-17 14:52:10'),
(4, 'Bank BCA', 'BCA', 'Transfer Bank BCA', 2500.00, 'active', '2025-11-17 14:52:10', '2025-11-17 14:52:10'),
(5, 'Bank BRI', 'BRI', 'Transfer Bank BRI', 2500.00, 'active', '2025-11-17 14:52:10', '2025-11-17 14:52:10'),
(6, 'Bank Mandiri', 'MANDIRI', 'Transfer Bank Mandiri', 2500.00, 'active', '2025-11-17 14:52:10', '2025-11-17 14:52:10'),
(7, 'Cash', 'CASH', 'Penarikan Tunai', 0.00, 'active', '2025-11-17 14:52:10', '2025-11-17 14:52:10');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mitra`
--

CREATE TABLE `mitra` (
  `id` int(11) NOT NULL,
  `nama_mitra` varchar(100) NOT NULL,
  `alamat` text NOT NULL,
  `email` varchar(100) NOT NULL,
  `no_hp` varchar(15) NOT NULL,
  `dokumen_izin` varchar(255) DEFAULT NULL,
  `ktp_pemilik` varchar(255) DEFAULT NULL,
  `no_rekening` varchar(50) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status_verifikasi` enum('pending','verified','rejected') DEFAULT 'pending',
  `token_verifikasi` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `mitra`
--

INSERT INTO `mitra` (`id`, `nama_mitra`, `alamat`, `email`, `no_hp`, `dokumen_izin`, `ktp_pemilik`, `no_rekening`, `username`, `password`, `status_verifikasi`, `token_verifikasi`, `created_at`, `updated_at`) VALUES
(1, 'Mitra Sampah Jaya', 'Jl. Raya Contoh No. 123, Jakarta Selatan', 'mitra@example.com', '081234567892', NULL, NULL, NULL, 'mitra_demo', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'verified', NULL, '2025-11-17 12:44:22', '2025-11-17 12:44:22'),
(2, 'PT Pengumpul Sampah Bersih', 'Jl. Kebersihan No. 45, Jakarta Timur', 'ptbersih@example.com', '081234567893', NULL, NULL, NULL, 'pt_bersih', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'verified', NULL, '2025-11-17 12:44:22', '2025-11-17 12:44:22'),
(3, 'Jaya Abadi', 'Demaan', 'jaya77@gmail.com', '0814352676', '691b192aa46d2_1763383594.png', '691b192aa5023_1763383594.png', NULL, 'jaya12', '$2y$10$5//7UJxdLMOwRnbmP84YfOCxTQzF.CJOZ1.uhex9a/htBDcqOcBnO', 'verified', '4c5ad213c6984396b63d9543cb934e521627231832d45ce66c612bf14a4f864a19225423959241df554a0cece0e3e60528fd', '2025-11-17 12:46:34', '2025-11-17 12:47:05');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mitra_locations`
--

CREATE TABLE `mitra_locations` (
  `id` int(11) NOT NULL,
  `mitra_id` int(11) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `mitra_locations`
--

INSERT INTO `mitra_locations` (`id`, `mitra_id`, `latitude`, `longitude`, `created_at`) VALUES
(1, 3, -6.78476700, 110.86655300, '2025-11-17 13:11:04'),
(2, 3, -6.78476700, 110.86655300, '2025-11-17 13:11:04'),
(3, 3, -6.78476700, 110.86655300, '2025-11-17 13:11:04'),
(4, 3, -6.78476700, 110.86655300, '2025-11-17 13:11:13'),
(5, 3, -6.78476700, 110.86655300, '2025-11-17 13:12:28'),
(6, 3, -6.78476700, 110.86655300, '2025-11-17 13:12:37'),
(7, 3, -6.78476700, 110.86655300, '2025-11-17 13:12:46'),
(8, 3, -6.78476700, 110.86655300, '2025-11-17 13:14:28'),
(9, 3, -6.78476700, 110.86655300, '2025-11-17 13:14:40'),
(10, 3, -6.80530900, 110.84055300, '2025-11-17 13:15:37'),
(11, 3, -6.78476700, 110.86655300, '2025-11-17 13:18:19'),
(12, 3, -6.78476700, 110.86655300, '2025-11-17 13:41:22'),
(13, 3, -6.78476700, 110.86655300, '2025-11-17 13:43:05'),
(14, 3, -6.78476700, 110.86655300, '2025-11-17 13:46:27'),
(15, 3, -6.80464400, 110.84038800, '2025-11-17 13:50:33'),
(16, 3, -6.80464400, 110.84038800, '2025-11-17 13:50:35'),
(17, 3, -6.78476700, 110.86655300, '2025-11-17 14:01:36'),
(18, 3, -6.78476700, 110.86655300, '2025-11-17 15:01:36'),
(19, 3, -6.78721700, 110.84683100, '2025-11-19 15:06:31'),
(20, 3, -6.78722400, 110.84688700, '2025-11-19 15:07:10'),
(21, 3, -6.78722400, 110.84688700, '2025-11-19 16:15:34'),
(22, 3, -6.78722400, 110.84688700, '2025-11-19 16:19:24'),
(23, 3, -6.78722400, 110.84688700, '2025-11-19 16:21:24'),
(24, 3, -6.78722400, 110.84688700, '2025-11-19 16:23:24'),
(25, 3, -6.78722400, 110.84688700, '2025-11-19 16:35:15'),
(26, 3, -6.78722400, 110.84688700, '2025-11-19 16:35:24'),
(27, 3, -6.78722400, 110.84688700, '2025-11-19 16:41:49'),
(28, 3, -6.78722400, 110.84688700, '2025-11-19 16:49:05'),
(29, 3, -6.78722400, 110.84688700, '2025-11-19 16:52:19'),
(30, 3, -6.78722400, 110.84688700, '2025-11-19 16:54:12'),
(31, 3, -6.78722400, 110.84688700, '2025-11-19 16:55:12'),
(32, 3, -6.78722400, 110.84688700, '2025-11-19 17:29:24'),
(33, 3, -6.78722400, 110.84688700, '2025-11-19 17:30:08'),
(34, 3, -6.78722100, 110.84687300, '2025-11-19 17:40:37');

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifikasi`
--

CREATE TABLE `notifikasi` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `mitra_id` int(11) DEFAULT NULL,
  `judul` varchar(255) NOT NULL,
  `pesan` text NOT NULL,
  `tipe` enum('user','mitra','all') DEFAULT 'user',
  `dibaca` enum('yes','no') DEFAULT 'no',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `notifikasi`
--

INSERT INTO `notifikasi` (`id`, `user_id`, `mitra_id`, `judul`, `pesan`, `tipe`, `dibaca`, `created_at`) VALUES
(1, NULL, 1, 'Penjemputan Baru', 'Ada permintaan penjemputan baru dari warga untuk Plastik dan Kertas', 'mitra', 'no', '2025-11-17 12:44:22'),
(2, NULL, 1, 'Penjemputan Baru', 'Ada permintaan penjemputan baru dari warga untuk Kaleng', 'mitra', 'no', '2025-11-17 12:44:22'),
(3, NULL, 2, 'Penjemputan Baru', 'Ada permintaan penjemputan baru dari warga untuk Elektronik', 'mitra', 'no', '2025-11-17 12:44:22'),
(4, NULL, 3, 'Penjemputan Baru', 'Ada permintaan penjemputan baru dari warga untuk Plastik', 'mitra', 'yes', '2025-11-17 12:53:23'),
(5, 4, NULL, 'Penjemputan Dijadwalkan', 'Permintaan penjemputan sampah (Plastik) berhasil diajukan. Mitra akan segera menghubungi Anda.', 'user', 'no', '2025-11-17 12:53:23'),
(6, 4, NULL, 'Setor Sampah Berhasil', 'Anda berhasil menyetor 7.00 kg sampah. Saldo bertambah: Rp 35.000', 'user', 'no', '2025-11-17 13:36:47'),
(7, 4, NULL, 'Setor Sampah Berhasil', 'Setoran sampah 70.00 kg berhasil. Saldo bertambah: Rp 280.000', 'user', 'no', '2025-11-17 14:19:28'),
(8, 1, NULL, 'Penarikan Baru', 'Ada penarikan baru sebesar Rp 500,000 menunggu approval', 'user', 'no', '2025-11-17 14:52:45'),
(9, 4, NULL, 'Penarikan Diajukan', 'Pengajuan penarikan saldo sebesar Rp 500.000 ke Bank BCA (08123445666) telah diajukan. Menunggu persetujuan admin.', 'user', 'no', '2025-11-17 14:52:45'),
(10, 1, NULL, 'Penarikan Baru', 'Ada pengajuan penarikan baru dari Vadin An sebesar Rp 500.000 ke Bank BCA (08123445666)', 'user', 'no', '2025-11-17 14:52:45'),
(11, 4, NULL, 'Penarikan Disetujui', 'Penarikan sebesar Rp 500,000 telah disetujui. Saldo telah dikurangi.', 'user', 'no', '2025-11-17 14:53:13'),
(12, 4, NULL, 'Setor Sampah Berhasil', 'Anda berhasil menyetor 70.00 kg sampah. Saldo bertambah: Rp 280.000', 'user', 'no', '2025-11-19 16:06:13'),
(13, 4, NULL, 'Setor Sampah Berhasil', 'Anda berhasil menyetor 10.00 kg sampah. Saldo bertambah: Rp 40.000', 'user', 'no', '2025-11-19 16:06:28'),
(14, 4, NULL, 'Setor Sampah Berhasil', 'Anda berhasil menyetor 12.00 kg sampah. Saldo bertambah: Rp 60.000', 'user', 'no', '2025-11-19 16:10:42'),
(15, 4, NULL, 'Setor Sampah Berhasil', 'Anda berhasil menyetor 9.00 kg sampah. Saldo bertambah: Rp 45.000', 'user', 'no', '2025-11-19 16:11:24'),
(16, 1, NULL, 'Penarikan Baru', 'Ada penarikan baru sebesar Rp 800,000 menunggu approval', 'user', 'no', '2025-11-19 16:11:44'),
(17, 4, NULL, 'Penarikan Diajukan', 'Pengajuan penarikan saldo sebesar Rp 800.000 ke Bank BRI (0872415214251) telah diajukan. Menunggu persetujuan admin.', 'user', 'no', '2025-11-19 16:11:44'),
(18, 1, NULL, 'Penarikan Baru', 'Ada pengajuan penarikan baru dari Vadin An sebesar Rp 800.000 ke Bank BRI (0872415214251)', 'user', 'no', '2025-11-19 16:11:44'),
(19, 4, NULL, 'Penarikan Disetujui', 'Penarikan sebesar Rp 800,000 telah disetujui. Saldo telah dikurangi.', 'user', 'no', '2025-11-19 16:12:05'),
(20, NULL, 3, 'Penjemputan Baru', 'Ada permintaan penjemputan baru dari warga untuk kaleng', 'mitra', 'no', '2025-11-19 16:52:09'),
(21, 4, NULL, 'Penjemputan Dijadwalkan', 'Permintaan penjemputan sampah (kaleng) berhasil diajukan. Mitra akan segera menghubungi Anda.', 'user', 'no', '2025-11-19 16:52:09');

-- --------------------------------------------------------

--
-- Struktur dari tabel `penarikan`
--

CREATE TABLE `penarikan` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `jumlah` decimal(12,2) NOT NULL,
  `tanggal_penarikan` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `keterangan` text DEFAULT NULL,
  `metode_pembayaran_id` int(11) DEFAULT NULL,
  `nomor_tujuan` varchar(50) DEFAULT NULL,
  `biaya_admin` decimal(15,2) DEFAULT 0.00,
  `admin_id` int(11) DEFAULT NULL,
  `tanggal_verifikasi` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `penarikan`
--

INSERT INTO `penarikan` (`id`, `user_id`, `jumlah`, `tanggal_penarikan`, `status`, `keterangan`, `metode_pembayaran_id`, `nomor_tujuan`, `biaya_admin`, `admin_id`, `tanggal_verifikasi`) VALUES
(1, 4, 500000.00, '2025-11-17 14:52:45', 'rejected', 'jijik', 4, '08123445666', 0.00, 1, '2025-11-17 15:02:46'),
(2, 4, 800000.00, '2025-11-19 16:11:44', 'approved', '', 5, '0872415214251', 0.00, 1, '2025-11-19 16:12:05');

--
-- Trigger `penarikan`
--
DELIMITER $$
CREATE TRIGGER `after_penarikan_approve` AFTER UPDATE ON `penarikan` FOR EACH ROW BEGIN
    IF NEW.status = 'approved' AND OLD.status != 'approved' THEN
        UPDATE users SET saldo = saldo - NEW.jumlah WHERE id = NEW.user_id;
        
        -- Notifikasi ke user bahwa penarikan disetujui
        INSERT INTO notifikasi (user_id, judul, pesan, tipe)
        VALUES (NEW.user_id, 'Penarikan Disetujui', 
                CONCAT('Penarikan sebesar Rp ', FORMAT(NEW.jumlah, 0), ' telah disetujui. Saldo telah dikurangi.'),
                'user');
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_penarikan_insert` AFTER INSERT ON `penarikan` FOR EACH ROW BEGIN
    -- Notifikasi ke semua admin
    INSERT INTO notifikasi (user_id, judul, pesan, tipe)
    SELECT id, 'Penarikan Baru', 
           CONCAT('Ada penarikan baru sebesar Rp ', FORMAT(NEW.jumlah, 0), ' menunggu approval'),
           'user'
    FROM users 
    WHERE role = 'admin';
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `penimbangan`
--

CREATE TABLE `penimbangan` (
  `id` int(11) NOT NULL,
  `id_penjemputan` int(11) DEFAULT NULL,
  `id_mitra` int(11) DEFAULT NULL,
  `berat` decimal(10,2) NOT NULL,
  `harga_per_kg` decimal(10,2) NOT NULL,
  `total_harga` decimal(12,2) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','verified') DEFAULT 'verified'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `penimbangan`
--

INSERT INTO `penimbangan` (`id`, `id_penjemputan`, `id_mitra`, `berat`, `harga_per_kg`, `total_harga`, `keterangan`, `created_at`, `status`) VALUES
(1, 3, 2, 8.50, 8000.00, 68000.00, NULL, '2025-11-17 12:44:22', 'verified');

-- --------------------------------------------------------

--
-- Struktur dari tabel `penjemputan`
--

CREATE TABLE `penjemputan` (
  `id` int(11) NOT NULL,
  `id_warga` int(11) DEFAULT NULL,
  `id_mitra` int(11) DEFAULT NULL,
  `alamat_penjemputan` text NOT NULL,
  `jenis_sampah` varchar(100) NOT NULL,
  `berat` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','dijadwalkan','diproses','selesai','ditolak') DEFAULT 'pending',
  `waktu_pemintaan` timestamp NOT NULL DEFAULT current_timestamp(),
  `waktu_penjemputan` timestamp NULL DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `berat_aktual` decimal(10,2) DEFAULT NULL,
  `harga_per_kg_aktual` decimal(10,2) DEFAULT NULL,
  `total_harga_aktual` decimal(12,2) DEFAULT NULL,
  `tanggal_verifikasi` timestamp NULL DEFAULT NULL,
  `catatan_verifikasi` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `penjemputan`
--

INSERT INTO `penjemputan` (`id`, `id_warga`, `id_mitra`, `alamat_penjemputan`, `jenis_sampah`, `berat`, `status`, `waktu_pemintaan`, `waktu_penjemputan`, `keterangan`, `berat_aktual`, `harga_per_kg_aktual`, `total_harga_aktual`, `tanggal_verifikasi`, `catatan_verifikasi`) VALUES
(1, 2, 1, 'Jl. Merdeka No. 123, Jakarta', 'Plastik dan Kertas', 0.00, 'pending', '2025-11-17 12:44:22', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 2, 1, 'Jl. Merdeka No. 123, Jakarta', 'Kaleng', 0.00, 'dijadwalkan', '2025-11-16 12:44:22', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 2, 2, 'Jl. Merdeka No. 123, Jakarta', 'Elektronik', 0.00, 'selesai', '2025-11-15 12:44:22', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 4, 3, 'demaan', 'Plastik', 0.00, 'selesai', '2025-11-17 12:53:23', '2025-11-17 05:54:00', '', NULL, NULL, NULL, NULL, NULL),
(5, 4, 3, 'demaan', 'kaleng', 0.00, 'diproses', '2025-11-19 16:52:09', '2025-11-21 02:54:00', '', NULL, NULL, NULL, NULL, NULL);

--
-- Trigger `penjemputan`
--
DELIMITER $$
CREATE TRIGGER `after_penjemputan_insert` AFTER INSERT ON `penjemputan` FOR EACH ROW BEGIN
    -- Notifikasi ke mitra terkait
    IF NEW.id_mitra IS NOT NULL THEN
        INSERT INTO notifikasi (mitra_id, judul, pesan, tipe)
        VALUES (NEW.id_mitra, 'Penjemputan Baru',
                CONCAT('Ada permintaan penjemputan baru dari warga untuk ', NEW.jenis_sampah),
                'mitra');
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `rating_mitra`
--

CREATE TABLE `rating_mitra` (
  `id` int(11) NOT NULL,
  `id_mitra` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_penjemputan` int(11) DEFAULT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `ulasan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `rating_mitra`
--

INSERT INTO `rating_mitra` (`id`, `id_mitra`, `id_user`, `id_penjemputan`, `rating`, `ulasan`, `created_at`) VALUES
(1, 1, 2, 3, 5, 'Pelayanan sangat memuaskan, sampah dijemput tepat waktu.', '2025-11-17 12:59:14'),
(2, 1, 2, NULL, 4, 'Mitra responsif dan ramah.', '2025-11-17 12:59:14');

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi`
--

CREATE TABLE `transaksi` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `jenis_sampah_id` int(11) NOT NULL,
  `berat` decimal(8,2) NOT NULL,
  `total_harga` decimal(12,2) NOT NULL,
  `tanggal_transaksi` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `transaksi`
--

INSERT INTO `transaksi` (`id`, `user_id`, `jenis_sampah_id`, `berat`, `total_harga`, `tanggal_transaksi`) VALUES
(1, 2, 1, 5.00, 25000.00, '2025-11-17 12:44:22'),
(2, 2, 2, 3.00, 9000.00, '2025-11-17 12:44:22'),
(3, 2, 3, 2.50, 10000.00, '2025-11-17 12:44:22'),
(4, 4, 1, 7.00, 35000.00, '2025-11-17 13:36:47'),
(5, 4, 3, 70.00, 280000.00, '2025-11-17 14:19:28'),
(6, 4, 8, 70.00, 280000.00, '2025-11-19 16:06:13'),
(7, 4, 3, 10.00, 40000.00, '2025-11-19 16:06:28'),
(8, 4, 1, 12.00, 60000.00, '2025-11-19 16:10:42'),
(9, 4, 1, 9.00, 45000.00, '2025-11-19 16:11:24');

--
-- Trigger `transaksi`
--
DELIMITER $$
CREATE TRIGGER `after_transaksi_insert` AFTER INSERT ON `transaksi` FOR EACH ROW BEGIN
    UPDATE users SET saldo = saldo + NEW.total_harga WHERE id = NEW.user_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `nik` varchar(16) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telepon` varchar(15) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `status` enum('active','inactive') DEFAULT 'active',
  `saldo` decimal(12,2) DEFAULT 0.00,
  `verification_code` varchar(6) DEFAULT NULL,
  `is_verified` enum('yes','no') DEFAULT 'no',
  `verification_expires` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `nik`, `email`, `telepon`, `alamat`, `role`, `status`, `saldo`, `verification_code`, `is_verified`, `verification_expires`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', '1234567890123456', 'admin@banksampah.com', '081234567890', 'Kantor Pusat Bank Sampah', 'admin', 'active', 0.00, NULL, 'yes', NULL, '2025-11-17 12:44:22', '2025-11-17 12:44:22'),
(2, 'user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ahmad Santoso', '1234567890123457', 'ahmad@gmail.com', '081234567891', 'Jl. Merdeka No. 123, Jakarta', 'user', 'active', 44000.00, NULL, 'yes', NULL, '2025-11-17 12:44:22', '2025-11-17 12:50:11'),
(3, 'vadin12', '$2y$10$kyiS/dr7/4c.ue385E.BFOfsn/X9xf9ZmOv.DgCmzJ.00ywUAICW.', 'Vadin A', '3319063426777171', 'vadin55@gmail.com', '0816367818388', 'demaan', 'user', 'active', 0.00, NULL, 'no', NULL, '2025-11-17 12:48:53', '2025-11-17 12:48:53'),
(4, 'vadin13', '$2y$10$tTmLDGNldon27ZwC6MnStuHyrbHJVRM.In7JcGRBsRyAw0UCfQxHC', 'Vadin An', '8632767267167167', 'vadin65@gmail.com', '0835275616464', 'demaan', 'user', 'active', 100000.00, NULL, 'yes', '2025-11-17 14:51:33', '2025-11-17 12:51:33', '2025-11-19 16:12:05');

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `view_mitra_summary`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `view_mitra_summary` (
`id` int(11)
,`nama_mitra` varchar(100)
,`email` varchar(100)
,`no_hp` varchar(15)
,`alamat` text
,`status_verifikasi` enum('pending','verified','rejected')
,`created_at` timestamp
,`total_penjemputan` bigint(21)
,`total_selesai` bigint(21)
,`total_pending` bigint(21)
,`total_pendapatan` decimal(34,2)
);

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `view_user_summary`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `view_user_summary` (
`id` int(11)
,`username` varchar(50)
,`nama_lengkap` varchar(100)
,`nik` varchar(16)
,`email` varchar(100)
,`telepon` varchar(15)
,`alamat` text
,`saldo` decimal(12,2)
,`status` enum('active','inactive')
,`is_verified` enum('yes','no')
,`created_at` timestamp
,`total_transaksi` bigint(21)
,`total_setoran` decimal(34,2)
,`total_penarikan` bigint(21)
,`total_ditarik` decimal(34,2)
);

-- --------------------------------------------------------

--
-- Struktur untuk view `view_mitra_summary`
--
DROP TABLE IF EXISTS `view_mitra_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_mitra_summary`  AS SELECT `m`.`id` AS `id`, `m`.`nama_mitra` AS `nama_mitra`, `m`.`email` AS `email`, `m`.`no_hp` AS `no_hp`, `m`.`alamat` AS `alamat`, `m`.`status_verifikasi` AS `status_verifikasi`, `m`.`created_at` AS `created_at`, count(`p`.`id`) AS `total_penjemputan`, count(case when `p`.`status` = 'selesai' then `p`.`id` end) AS `total_selesai`, count(case when `p`.`status` = 'pending' then `p`.`id` end) AS `total_pending`, coalesce(sum(`tm`.`total_harga`),0) AS `total_pendapatan` FROM ((`mitra` `m` left join `penjemputan` `p` on(`m`.`id` = `p`.`id_mitra`)) left join `penimbangan` `tm` on(`p`.`id` = `tm`.`id_penjemputan`)) GROUP BY `m`.`id` ;

-- --------------------------------------------------------

--
-- Struktur untuk view `view_user_summary`
--
DROP TABLE IF EXISTS `view_user_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_user_summary`  AS SELECT `u`.`id` AS `id`, `u`.`username` AS `username`, `u`.`nama_lengkap` AS `nama_lengkap`, `u`.`nik` AS `nik`, `u`.`email` AS `email`, `u`.`telepon` AS `telepon`, `u`.`alamat` AS `alamat`, `u`.`saldo` AS `saldo`, `u`.`status` AS `status`, `u`.`is_verified` AS `is_verified`, `u`.`created_at` AS `created_at`, count(`t`.`id`) AS `total_transaksi`, coalesce(sum(`t`.`total_harga`),0) AS `total_setoran`, count(`p`.`id`) AS `total_penarikan`, coalesce(sum(case when `p`.`status` = 'approved' then `p`.`jumlah` else 0 end),0) AS `total_ditarik` FROM ((`users` `u` left join `transaksi` `t` on(`u`.`id` = `t`.`user_id`)) left join `penarikan` `p` on(`u`.`id` = `p`.`user_id`)) GROUP BY `u`.`id` ;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `feedback_mitra`
--
ALTER TABLE `feedback_mitra`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mitra_id` (`mitra_id`);

--
-- Indeks untuk tabel `jenis_sampah`
--
ALTER TABLE `jenis_sampah`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `metode_pembayaran`
--
ALTER TABLE `metode_pembayaran`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `mitra`
--
ALTER TABLE `mitra`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_mitra_username` (`username`),
  ADD KEY `idx_mitra_email` (`email`),
  ADD KEY `idx_mitra_status` (`status_verifikasi`);

--
-- Indeks untuk tabel `mitra_locations`
--
ALTER TABLE `mitra_locations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mitra_id` (`mitra_id`);

--
-- Indeks untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifikasi_user` (`user_id`,`dibaca`),
  ADD KEY `idx_notifikasi_mitra` (`mitra_id`,`dibaca`);

--
-- Indeks untuk tabel `penarikan`
--
ALTER TABLE `penarikan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `idx_penarikan_status` (`status`),
  ADD KEY `idx_penarikan_user_date` (`user_id`,`tanggal_penarikan`),
  ADD KEY `metode_pembayaran_id` (`metode_pembayaran_id`);

--
-- Indeks untuk tabel `penimbangan`
--
ALTER TABLE `penimbangan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_mitra` (`id_mitra`),
  ADD KEY `idx_penimbangan_penjemputan` (`id_penjemputan`);

--
-- Indeks untuk tabel `penjemputan`
--
ALTER TABLE `penjemputan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_penjemputan_mitra_status` (`id_mitra`,`status`),
  ADD KEY `idx_penjemputan_warga` (`id_warga`);

--
-- Indeks untuk tabel `rating_mitra`
--
ALTER TABLE `rating_mitra`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_mitra` (`id_mitra`),
  ADD KEY `id_user` (`id_user`),
  ADD KEY `id_penjemputan` (`id_penjemputan`);

--
-- Indeks untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jenis_sampah_id` (`jenis_sampah_id`),
  ADD KEY `idx_transaksi_user_date` (`user_id`,`tanggal_transaksi`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `nik` (`nik`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_username` (`username`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_users_nik` (`nik`),
  ADD KEY `idx_users_verification` (`verification_code`,`verification_expires`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `feedback_mitra`
--
ALTER TABLE `feedback_mitra`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `jenis_sampah`
--
ALTER TABLE `jenis_sampah`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `metode_pembayaran`
--
ALTER TABLE `metode_pembayaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `mitra`
--
ALTER TABLE `mitra`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `mitra_locations`
--
ALTER TABLE `mitra_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT untuk tabel `penarikan`
--
ALTER TABLE `penarikan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `penimbangan`
--
ALTER TABLE `penimbangan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `penjemputan`
--
ALTER TABLE `penjemputan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `rating_mitra`
--
ALTER TABLE `rating_mitra`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `feedback_mitra`
--
ALTER TABLE `feedback_mitra`
  ADD CONSTRAINT `feedback_mitra_ibfk_1` FOREIGN KEY (`mitra_id`) REFERENCES `mitra` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mitra_locations`
--
ALTER TABLE `mitra_locations`
  ADD CONSTRAINT `mitra_locations_ibfk_1` FOREIGN KEY (`mitra_id`) REFERENCES `mitra` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD CONSTRAINT `notifikasi_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifikasi_ibfk_2` FOREIGN KEY (`mitra_id`) REFERENCES `mitra` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `penarikan`
--
ALTER TABLE `penarikan`
  ADD CONSTRAINT `penarikan_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `penarikan_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `penarikan_ibfk_3` FOREIGN KEY (`metode_pembayaran_id`) REFERENCES `metode_pembayaran` (`id`);

--
-- Ketidakleluasaan untuk tabel `penimbangan`
--
ALTER TABLE `penimbangan`
  ADD CONSTRAINT `penimbangan_ibfk_1` FOREIGN KEY (`id_penjemputan`) REFERENCES `penjemputan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `penimbangan_ibfk_2` FOREIGN KEY (`id_mitra`) REFERENCES `mitra` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `penjemputan`
--
ALTER TABLE `penjemputan`
  ADD CONSTRAINT `penjemputan_ibfk_1` FOREIGN KEY (`id_warga`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `penjemputan_ibfk_2` FOREIGN KEY (`id_mitra`) REFERENCES `mitra` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `rating_mitra`
--
ALTER TABLE `rating_mitra`
  ADD CONSTRAINT `rating_mitra_ibfk_1` FOREIGN KEY (`id_mitra`) REFERENCES `mitra` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rating_mitra_ibfk_2` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rating_mitra_ibfk_3` FOREIGN KEY (`id_penjemputan`) REFERENCES `penjemputan` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD CONSTRAINT `transaksi_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transaksi_ibfk_2` FOREIGN KEY (`jenis_sampah_id`) REFERENCES `jenis_sampah` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
