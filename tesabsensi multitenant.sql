-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Waktu pembuatan: 30 Sep 2025 pada 18.32
-- Versi server: 10.11.14-MariaDB-cll-lve
-- Versi PHP: 8.3.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u7653190_tesabsensi`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `absensi_gerbang`
--

CREATE TABLE `absensi_gerbang` (
  `id` int(11) NOT NULL,
  `related_id` int(11) NOT NULL COMMENT 'ID siswa atau guru',
  `jenis_pengguna` enum('siswa','guru') NOT NULL,
  `tanggal` date NOT NULL,
  `jam` time NOT NULL,
  `tipe` enum('masuk','pulang') NOT NULL,
  `recorded_by_user_id` int(11) DEFAULT NULL COMMENT 'ID pengguna (admin/petugas) yang mencatat',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `absensi_harian_guru`
--

CREATE TABLE `absensi_harian_guru` (
  `id` int(11) NOT NULL,
  `guru_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `tahun_ajaran` varchar(50) DEFAULT NULL,
  `semester` varchar(20) DEFAULT NULL,
  `waktu_masuk` time DEFAULT NULL,
  `waktu_pulang` time DEFAULT NULL,
  `status_masuk` enum('Hadir','Terlambat','Sakit','Izin','Alpha','Tidak Absen') DEFAULT 'Tidak Absen',
  `status_pulang` enum('Pulang','Belum Pulang') NOT NULL DEFAULT 'Belum Pulang',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `absensi_harian_siswa`
--

CREATE TABLE `absensi_harian_siswa` (
  `id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `kelas_id` int(11) DEFAULT NULL,
  `tanggal` date NOT NULL,
  `tahun_ajaran` varchar(50) DEFAULT NULL,
  `semester` varchar(20) DEFAULT NULL,
  `waktu_masuk` time DEFAULT NULL,
  `waktu_pulang` time DEFAULT NULL,
  `status_masuk` enum('Hadir','Terlambat','Sakit','Izin','Alpha','Tidak Absen') DEFAULT 'Tidak Absen',
  `status_pulang` enum('Pulang','Belum Pulang') NOT NULL DEFAULT 'Belum Pulang',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `absensi_mapel_guru`
--

CREATE TABLE `absensi_mapel_guru` (
  `id` int(11) NOT NULL,
  `guru_id` int(11) NOT NULL,
  `jadwal_id` int(11) NOT NULL,
  `tanggal_ajar` date NOT NULL,
  `waktu_mulai_ajar` time NOT NULL,
  `waktu_selesai_ajar` time DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `absensi_mapel_siswa`
--

CREATE TABLE `absensi_mapel_siswa` (
  `id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `jadwal_id` int(11) NOT NULL,
  `guru_id` int(11) NOT NULL,
  `tanggal_absen` date NOT NULL,
  `waktu_absen` time NOT NULL,
  `status_kehadiran` enum('H','S','I','A') NOT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `app_settings`
--

CREATE TABLE `app_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `data_type` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `detail_pembayaran_transaksi`
--

CREATE TABLE `detail_pembayaran_transaksi` (
  `id` int(11) NOT NULL,
  `tagihan_siswa_id` int(11) NOT NULL,
  `tanggal_bayar` date NOT NULL,
  `jumlah_bayar` decimal(10,2) NOT NULL,
  `metode_pembayaran` varchar(50) NOT NULL,
  `petugas_id` int(11) NOT NULL,
  `bukti_pembayaran_path` varchar(255) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `status_pembayaran_detail` varchar(50) NOT NULL DEFAULT 'Lunas',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `gaji_settings`
--

CREATE TABLE `gaji_settings` (
  `id` int(11) NOT NULL,
  `nominal_per_pertemuan` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `guru`
--

CREATE TABLE `guru` (
  `id` int(11) NOT NULL,
  `nip` varchar(20) DEFAULT NULL,
  `rfid_tag` varchar(255) DEFAULT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `tempat_lahir` varchar(50) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `jenis_kelamin` enum('L','P') DEFAULT NULL,
  `alamat` varchar(255) DEFAULT NULL,
  `no_telp` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `jabatan` varchar(255) DEFAULT NULL,
  `tunjangan_jabatan` decimal(12,2) DEFAULT NULL,
  `transport` text DEFAULT NULL,
  `tunjangan_transport` decimal(10,2) NOT NULL DEFAULT 0.00,
  `transport_jabatan` decimal(15,2) NOT NULL DEFAULT 0.00,
  `hari_piket` varchar(255) DEFAULT NULL,
  `transport_piket` decimal(15,2) NOT NULL DEFAULT 0.00,
  `gaji_per_pertemuan` decimal(10,2) DEFAULT 0.00,
  `tunjangan_sertifikasi` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `hafalan_siswa`
--

CREATE TABLE `hafalan_siswa` (
  `id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `tahun_ajaran_id` int(11) DEFAULT NULL,
  `kitab_nadhom_id` int(11) NOT NULL,
  `tanggal_setor` date NOT NULL,
  `bagian_hafalan` varchar(255) NOT NULL,
  `nilai` decimal(4,2) NOT NULL,
  `guru_id` int(11) NOT NULL,
  `catatan` text DEFAULT NULL,
  `jenis_setor` enum('Setoran Baru','Mengulang') NOT NULL DEFAULT 'Setoran Baru',
  `status_hafalan` enum('Belum Setor','Masih Kurang','Mencapai Target','Tidak Berlaku') NOT NULL DEFAULT 'Belum Setor',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `informasi_umum`
--

CREATE TABLE `informasi_umum` (
  `id` int(11) NOT NULL,
  `judul` varchar(255) NOT NULL,
  `konten` text NOT NULL,
  `tanggal_publikasi` date NOT NULL,
  `status_publikasi` enum('Aktif','Tidak Aktif') NOT NULL DEFAULT 'Aktif',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `izin_keluar_pulang`
--

CREATE TABLE `izin_keluar_pulang` (
  `id` int(11) NOT NULL,
  `tahun_ajaran_id` int(11) DEFAULT NULL,
  `siswa_id` int(11) NOT NULL,
  `jenis_izin` varchar(20) NOT NULL,
  `tanggal_izin` date NOT NULL,
  `sampai_tanggal` date DEFAULT NULL,
  `waktu_keluar` time DEFAULT NULL,
  `waktu_kembali` time DEFAULT NULL,
  `keperluan` text NOT NULL,
  `diberi_izin_oleh_id` int(11) NOT NULL,
  `catatan_admin` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal_pelajaran`
--

CREATE TABLE `jadwal_pelajaran` (
  `id` int(11) NOT NULL,
  `kelas_id` int(11) DEFAULT NULL,
  `mapel_id` int(11) NOT NULL,
  `guru_id` int(11) NOT NULL,
  `hari` enum('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu') NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `jumlah_jam` int(2) NOT NULL DEFAULT 1,
  `ruangan` varchar(50) DEFAULT NULL,
  `semester` varchar(20) DEFAULT NULL,
  `tahun_ajaran` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tipe_jadwal` enum('Kelas','Siswa') NOT NULL DEFAULT 'Kelas'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal_siswa`
--

CREATE TABLE `jadwal_siswa` (
  `jadwal_id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `jenis_pelanggaran`
--

CREATE TABLE `jenis_pelanggaran` (
  `id` int(11) NOT NULL,
  `nama_pelanggaran` varchar(255) NOT NULL,
  `poin_deduksi` int(11) NOT NULL DEFAULT 0,
  `keterangan` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `jenis_pembayaran`
--

CREATE TABLE `jenis_pembayaran` (
  `id` int(11) NOT NULL,
  `nama_pembayaran` varchar(255) NOT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `tipe_pembayaran` enum('Rutin Bulanan','Bebas') NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `kalender_pendidikan`
--

CREATE TABLE `kalender_pendidikan` (
  `id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `keterangan` varchar(255) NOT NULL,
  `tahun_ajaran` varchar(20) DEFAULT NULL,
  `semester` enum('Ganjil','Genap') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



-- --------------------------------------------------------

--
-- Struktur dari tabel `kas_kategori`
--

CREATE TABLE `kas_kategori` (
  `id` int(11) NOT NULL,
  `nama_kategori` varchar(100) NOT NULL,
  `tipe_kategori` enum('masuk','keluar','campuran') NOT NULL DEFAULT 'campuran',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `kas_transaksi`
--

CREATE TABLE `kas_transaksi` (
  `id` int(11) NOT NULL,
  `tanggal_transaksi` date NOT NULL,
  `deskripsi` varchar(255) NOT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `tipe_transaksi` enum('masuk','keluar') NOT NULL,
  `kategori_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `recorded_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `kas_unit`
--

CREATE TABLE `kas_unit` (
  `id` int(11) NOT NULL,
  `nama_unit` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Struktur dari tabel `kelas`
--

CREATE TABLE `kelas` (
  `id` int(11) NOT NULL,
  `nama_kelas` varchar(50) NOT NULL,
  `tingkat` varchar(10) DEFAULT NULL,
  `jurusan` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

 --------------------------------------------------------

--
-- Struktur dari tabel `kitab_nadhom`
--

CREATE TABLE `kitab_nadhom` (
  `id` int(11) NOT NULL,
  `nama_kitab` varchar(255) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `jenis_kitab` enum('Sorogan','Hafalan') NOT NULL DEFAULT 'Sorogan',
  `target_hafalan` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `lembaga`
--

CREATE TABLE `lembaga` (
  `id` int(11) NOT NULL,
  `nama_lembaga` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mata_pelajaran`
--

CREATE TABLE `mata_pelajaran` (
  `id` int(11) NOT NULL,
  `nama_mapel` varchar(100) NOT NULL,
  `kkm` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `ngaji_siswa`
--

CREATE TABLE `ngaji_siswa` (
  `id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `tahun_ajaran_id` int(11) DEFAULT NULL,
  `guru_id` int(11) NOT NULL,
  `tanggal_setor` date NOT NULL,
  `jenis_materi` varchar(50) NOT NULL,
  `halaman_dari` varchar(100) DEFAULT NULL,
  `halaman_sampai` varchar(100) DEFAULT NULL,
  `catatan_guru` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pelanggaran_siswa`
--

CREATE TABLE `pelanggaran_siswa` (
  `id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `tahun_ajaran_id` int(11) DEFAULT NULL,
  `jenis_pelanggaran_id` int(11) NOT NULL,
  `tanggal_pelanggaran` date NOT NULL,
  `waktu_pelanggaran` time DEFAULT NULL,
  `deskripsi_kejadian` text DEFAULT NULL,
  `poin_deduksi_aktual` int(11) NOT NULL,
  `petugas_id` int(11) NOT NULL,
  `status_tindak_lanjut` varchar(50) NOT NULL DEFAULT 'Belum Ditindak',
  `catatan_tindak_lanjut` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengumuman`
--

CREATE TABLE `pengumuman` (
  `id` int(11) NOT NULL,
  `judul` varchar(255) NOT NULL,
  `isi_pengumuman` text NOT NULL,
  `dikirim_oleh_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tipe_penerima` varchar(50) DEFAULT NULL,
  `penerima_kelas_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `roles`
--

INSERT INTO `roles` (`id`, `role_name`) VALUES
(1, 'Admin'),
(2, 'Guru'),
(3, 'TataUsaha');

-- --------------------------------------------------------

--
-- Struktur dari tabel `school_settings`
--

CREATE TABLE `school_settings` (
  `id` int(11) NOT NULL,
  `nama_sekolah` varchar(255) NOT NULL,
  `alamat_sekolah` varchar(255) DEFAULT NULL,
  `kota` varchar(100) DEFAULT NULL,
  `provinsi` varchar(100) DEFAULT NULL,
  `kode_pos` varchar(10) DEFAULT NULL,
  `telepon_sekolah` varchar(20) DEFAULT NULL,
  `email_sekolah` varchar(100) DEFAULT NULL,
  `website_sekolah` varchar(255) DEFAULT NULL,
  `kepala_sekolah` varchar(100) DEFAULT NULL,
  `nip_kepala_sekolah` varchar(50) DEFAULT NULL,
  `logo_sekolah_path` varchar(255) DEFAULT NULL,
  `tahun_ajaran_aktif` varchar(20) DEFAULT NULL,
  `semester_aktif` enum('Ganjil','Genap') DEFAULT NULL,
  `jam_masuk_sekolah_default` time DEFAULT '07:00:00',
  `toleransi_terlambat_menit` int(11) DEFAULT 15,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `whatsapp_api_url` varchar(255) DEFAULT NULL,
  `whatsapp_api_key` varchar(255) DEFAULT NULL,
  `whatsapp_sender_number` varchar(20) DEFAULT NULL,
  `rfid_machine_api_key` varchar(255) DEFAULT NULL,
  `lokasi_sekolah_lat` varchar(255) DEFAULT NULL,
  `lokasi_sekolah_long` varchar(255) DEFAULT NULL,
  `radius_absensi_meter` int(11) DEFAULT 50,
  `qr_code_path` varchar(255) DEFAULT NULL,
  `qr_token_hash` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `siswa`
--

CREATE TABLE `siswa` (
  `id` int(11) NOT NULL,
  `nisn` varchar(20) DEFAULT NULL,
  `nis` varchar(20) DEFAULT NULL,
  `rfid_tag` varchar(255) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `tempat_lahir` varchar(50) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `jenis_kelamin` enum('L','P') DEFAULT NULL,
  `alamat` varchar(255) DEFAULT NULL,
  `no_telp_ortu` varchar(20) DEFAULT NULL,
  `email_ortu` varchar(100) DEFAULT NULL,
  `kelas_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `status_siswa` enum('Aktif','Pindah','Lulus') NOT NULL DEFAULT 'Aktif',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `sorogan_siswa`
--

CREATE TABLE `sorogan_siswa` (
  `id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `tahun_ajaran_id` int(11) DEFAULT NULL,
  `kitab_nadhom_id` int(11) NOT NULL,
  `guru_id` int(11) NOT NULL,
  `tanggal_setor` date NOT NULL,
  `halaman_dari` varchar(50) NOT NULL,
  `halaman_sampai` varchar(50) NOT NULL,
  `keterangan_bacaan` text DEFAULT NULL,
  `nilai` decimal(5,2) DEFAULT NULL,
  `status_bacaan` enum('Lulus','Mengulang','Belum Selesai') NOT NULL DEFAULT 'Belum Selesai',
  `catatan_guru` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tagihan_siswa`
--

CREATE TABLE `tagihan_siswa` (
  `id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `jenis_pembayaran_id` int(11) NOT NULL,
  `tahun_ajaran_id` int(11) NOT NULL,
  `kelas_id_saat_tagih` int(11) DEFAULT NULL,
  `jumlah_tagihan` decimal(10,2) NOT NULL,
  `sisa_tagihan` decimal(10,2) NOT NULL,
  `periode_tagihan` varchar(50) DEFAULT NULL,
  `tanggal_jatuh_tempo` date DEFAULT NULL,
  `status_tagihan` enum('Belum Lunas','Sebagian Lunas','Lunas','Dibatalkan') DEFAULT 'Belum Lunas',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tahun_ajaran`
--

CREATE TABLE `tahun_ajaran` (
  `id` int(11) NOT NULL,
  `tahun_ajaran` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tunjangan_bulanan_guru`
--

CREATE TABLE `tunjangan_bulanan_guru` (
  `id` int(11) UNSIGNED NOT NULL,
  `guru_id` int(11) NOT NULL,
  `tahun_ajaran_id` int(11) NOT NULL,
  `bulan` int(2) NOT NULL,
  `kegiatan_lainnya` varchar(255) DEFAULT NULL,
  `tunjangan_kegiatan_lainnya` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role_id` int(11) NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `whatsapp_notification_queue`
--

CREATE TABLE `whatsapp_notification_queue` (
  `id` int(11) NOT NULL,
  `recipient_phone_number` varchar(20) NOT NULL,
  `template_name` varchar(50) NOT NULL,
  `pengumuman_id` int(11) DEFAULT NULL COMMENT 'Foreign key to pengumuman table',
  `template_parameters_json` text DEFAULT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `attempt_count` int(11) DEFAULT 0,
  `last_attempt_at` datetime DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;



--
-- Indeks untuk tabel `absensi_gerbang`
--
ALTER TABLE `absensi_gerbang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `related_id` (`related_id`,`jenis_pengguna`,`tanggal`,`tipe`),
  ADD KEY `tanggal` (`tanggal`,`tipe`);

--
-- Indeks untuk tabel `absensi_harian_guru`
--
ALTER TABLE `absensi_harian_guru`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_absensi_guru_tanggal` (`guru_id`,`tanggal`),
  ADD KEY `fk_absensi_harian_guru_guru_id_idx` (`guru_id`);

--
-- Indeks untuk tabel `absensi_harian_siswa`
--
ALTER TABLE `absensi_harian_siswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_absensi_siswa_tanggal` (`siswa_id`,`tanggal`),
  ADD KEY `fk_absensi_harian_siswa_siswa_id_idx` (`siswa_id`);

--
-- Indeks untuk tabel `absensi_mapel_guru`
--
ALTER TABLE `absensi_mapel_guru`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_absensi_mapel_guru_per_sesi` (`guru_id`,`jadwal_id`,`tanggal_ajar`),
  ADD KEY `fk_absensi_mapel_guru_guru_id_idx` (`guru_id`),
  ADD KEY `fk_absensi_mapel_guru_jadwal_id_idx` (`jadwal_id`);

--
-- Indeks untuk tabel `absensi_mapel_siswa`
--
ALTER TABLE `absensi_mapel_siswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_absensi_mapel_siswa_per_sesi` (`siswa_id`,`jadwal_id`,`tanggal_absen`),
  ADD KEY `fk_absensi_mapel_siswa_siswa_id_idx` (`siswa_id`),
  ADD KEY `fk_absensi_mapel_siswa_jadwal_id_idx` (`jadwal_id`),
  ADD KEY `fk_absensi_mapel_siswa_guru_id_idx` (`guru_id`);

--
-- Indeks untuk tabel `app_settings`
--
ALTER TABLE `app_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indeks untuk tabel `detail_pembayaran_transaksi`
--
ALTER TABLE `detail_pembayaran_transaksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tagihan_siswa_id` (`tagihan_siswa_id`),
  ADD KEY `petugas_id` (`petugas_id`);

--
-- Indeks untuk tabel `gaji_settings`
--
ALTER TABLE `gaji_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `guru`
--
ALTER TABLE `guru`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nip` (`nip`),
  ADD UNIQUE KEY `rfid_tag` (`rfid_tag`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `fk_guru_user_id_idx` (`user_id`);

--
-- Indeks untuk tabel `hafalan_siswa`
--
ALTER TABLE `hafalan_siswa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `siswa_id` (`siswa_id`),
  ADD KEY `kitab_nadhom_id` (`kitab_nadhom_id`),
  ADD KEY `guru_id` (`guru_id`);

--
-- Indeks untuk tabel `informasi_umum`
--
ALTER TABLE `informasi_umum`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `izin_keluar_pulang`
--
ALTER TABLE `izin_keluar_pulang`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `jadwal_pelajaran`
--
ALTER TABLE `jadwal_pelajaran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_jadwal_kelas_id_idx` (`kelas_id`),
  ADD KEY `fk_jadwal_mapel_id_idx` (`mapel_id`),
  ADD KEY `fk_jadwal_guru_id_idx` (`guru_id`);

--
-- Indeks untuk tabel `jadwal_siswa`
--
ALTER TABLE `jadwal_siswa`
  ADD PRIMARY KEY (`jadwal_id`,`siswa_id`),
  ADD KEY `siswa_id` (`siswa_id`);

--
-- Indeks untuk tabel `jenis_pelanggaran`
--
ALTER TABLE `jenis_pelanggaran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama_pelanggaran` (`nama_pelanggaran`);

--
-- Indeks untuk tabel `jenis_pembayaran`
--
ALTER TABLE `jenis_pembayaran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_jenis_pembayaran_unit` (`unit_id`);

--
-- Indeks untuk tabel `kalender_pendidikan`
--
ALTER TABLE `kalender_pendidikan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tanggal_tahun_ajaran` (`tanggal`,`tahun_ajaran`);

--
-- Indeks untuk tabel `kas_kategori`
--
ALTER TABLE `kas_kategori`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama_kategori` (`nama_kategori`);

--
-- Indeks untuk tabel `kas_transaksi`
--
ALTER TABLE `kas_transaksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kategori_id` (`kategori_id`),
  ADD KEY `unit_id` (`unit_id`),
  ADD KEY `recorded_by_user_id` (`recorded_by_user_id`);

--
-- Indeks untuk tabel `kas_unit`
--
ALTER TABLE `kas_unit`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama_unit` (`nama_unit`);

--
-- Indeks untuk tabel `kelas`
--
ALTER TABLE `kelas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama_kelas` (`nama_kelas`);

--
-- Indeks untuk tabel `kitab_nadhom`
--
ALTER TABLE `kitab_nadhom`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama_kitab` (`nama_kitab`);

--
-- Indeks untuk tabel `lembaga`
--
ALTER TABLE `lembaga`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama_lembaga` (`nama_lembaga`);

--
-- Indeks untuk tabel `mata_pelajaran`
--
ALTER TABLE `mata_pelajaran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama_mapel` (`nama_mapel`);

--
-- Indeks untuk tabel `ngaji_siswa`
--
ALTER TABLE `ngaji_siswa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `siswa_id` (`siswa_id`),
  ADD KEY `guru_id` (`guru_id`);

--
-- Indeks untuk tabel `pelanggaran_siswa`
--
ALTER TABLE `pelanggaran_siswa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `siswa_id` (`siswa_id`),
  ADD KEY `jenis_pelanggaran_id` (`jenis_pelanggaran_id`),
  ADD KEY `petugas_id` (`petugas_id`);

--
-- Indeks untuk tabel `pengumuman`
--
ALTER TABLE `pengumuman`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dikirim_oleh_user_id` (`dikirim_oleh_user_id`);

--
-- Indeks untuk tabel `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indeks untuk tabel `school_settings`
--
ALTER TABLE `school_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `siswa`
--
ALTER TABLE `siswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nisn` (`nisn`),
  ADD UNIQUE KEY `nis` (`nis`),
  ADD UNIQUE KEY `rfid_tag` (`rfid_tag`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_siswa_kelas_id_idx` (`kelas_id`);

--
-- Indeks untuk tabel `sorogan_siswa`
--
ALTER TABLE `sorogan_siswa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `siswa_id` (`siswa_id`),
  ADD KEY `kitab_nadhom_id` (`kitab_nadhom_id`),
  ADD KEY `guru_id` (`guru_id`);

--
-- Indeks untuk tabel `tagihan_siswa`
--
ALTER TABLE `tagihan_siswa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `siswa_id` (`siswa_id`),
  ADD KEY `jenis_pembayaran_id` (`jenis_pembayaran_id`),
  ADD KEY `tahun_ajaran_id` (`tahun_ajaran_id`),
  ADD KEY `kelas_id_saat_tagih` (`kelas_id_saat_tagih`);

--
-- Indeks untuk tabel `tahun_ajaran`
--
ALTER TABLE `tahun_ajaran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tahun_ajaran` (`tahun_ajaran`);

--
-- Indeks untuk tabel `tunjangan_bulanan_guru`
--
ALTER TABLE `tunjangan_bulanan_guru`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tunjangan` (`guru_id`,`tahun_ajaran_id`,`bulan`),
  ADD KEY `tahun_ajaran_id` (`tahun_ajaran_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_users_role_id_idx` (`role_id`);

--
-- Indeks untuk tabel `whatsapp_notification_queue`
--
ALTER TABLE `whatsapp_notification_queue`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `absensi_gerbang`
--
ALTER TABLE `absensi_gerbang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `absensi_harian_guru`
--
ALTER TABLE `absensi_harian_guru`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=176;

--
-- AUTO_INCREMENT untuk tabel `absensi_harian_siswa`
--
ALTER TABLE `absensi_harian_siswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1122;

--
-- AUTO_INCREMENT untuk tabel `absensi_mapel_guru`
--
ALTER TABLE `absensi_mapel_guru`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=138;

--
-- AUTO_INCREMENT untuk tabel `absensi_mapel_siswa`
--
ALTER TABLE `absensi_mapel_siswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3031;

--
-- AUTO_INCREMENT untuk tabel `app_settings`
--
ALTER TABLE `app_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `detail_pembayaran_transaksi`
--
ALTER TABLE `detail_pembayaran_transaksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT untuk tabel `gaji_settings`
--
ALTER TABLE `gaji_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `guru`
--
ALTER TABLE `guru`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT untuk tabel `hafalan_siswa`
--
ALTER TABLE `hafalan_siswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `informasi_umum`
--
ALTER TABLE `informasi_umum`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `izin_keluar_pulang`
--
ALTER TABLE `izin_keluar_pulang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `jadwal_pelajaran`
--
ALTER TABLE `jadwal_pelajaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=257;

--
-- AUTO_INCREMENT untuk tabel `jenis_pelanggaran`
--
ALTER TABLE `jenis_pelanggaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `jenis_pembayaran`
--
ALTER TABLE `jenis_pembayaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT untuk tabel `kalender_pendidikan`
--
ALTER TABLE `kalender_pendidikan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `kas_kategori`
--
ALTER TABLE `kas_kategori`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT untuk tabel `kas_transaksi`
--
ALTER TABLE `kas_transaksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT untuk tabel `kas_unit`
--
ALTER TABLE `kas_unit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `kelas`
--
ALTER TABLE `kelas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT untuk tabel `kitab_nadhom`
--
ALTER TABLE `kitab_nadhom`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `lembaga`
--
ALTER TABLE `lembaga`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `mata_pelajaran`
--
ALTER TABLE `mata_pelajaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT untuk tabel `ngaji_siswa`
--
ALTER TABLE `ngaji_siswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pelanggaran_siswa`
--
ALTER TABLE `pelanggaran_siswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pengumuman`
--
ALTER TABLE `pengumuman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT untuk tabel `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `school_settings`
--
ALTER TABLE `school_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `siswa`
--
ALTER TABLE `siswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=164;

--
-- AUTO_INCREMENT untuk tabel `sorogan_siswa`
--
ALTER TABLE `sorogan_siswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `tagihan_siswa`
--
ALTER TABLE `tagihan_siswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=246;

--
-- AUTO_INCREMENT untuk tabel `tahun_ajaran`
--
ALTER TABLE `tahun_ajaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `tunjangan_bulanan_guru`
--
ALTER TABLE `tunjangan_bulanan_guru`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT untuk tabel `whatsapp_notification_queue`
--
ALTER TABLE `whatsapp_notification_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=717;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `absensi_harian_guru`
--
ALTER TABLE `absensi_harian_guru`
  ADD CONSTRAINT `fk_absensi_harian_guru_guru_id` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `absensi_harian_siswa`
--
ALTER TABLE `absensi_harian_siswa`
  ADD CONSTRAINT `fk_absensi_harian_siswa_siswa_id` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `absensi_mapel_guru`
--
ALTER TABLE `absensi_mapel_guru`
  ADD CONSTRAINT `fk_absensi_mapel_guru_guru_id` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_absensi_mapel_guru_jadwal_id` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_pelajaran` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `absensi_mapel_siswa`
--
ALTER TABLE `absensi_mapel_siswa`
  ADD CONSTRAINT `fk_absensi_mapel_siswa_guru_id` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_absensi_mapel_siswa_jadwal_id` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_pelajaran` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_absensi_mapel_siswa_siswa_id` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `detail_pembayaran_transaksi`
--
ALTER TABLE `detail_pembayaran_transaksi`
  ADD CONSTRAINT `detail_pembayaran_transaksi_ibfk_1` FOREIGN KEY (`tagihan_siswa_id`) REFERENCES `tagihan_siswa` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `detail_pembayaran_transaksi_ibfk_2` FOREIGN KEY (`petugas_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `guru`
--
ALTER TABLE `guru`
  ADD CONSTRAINT `fk_guru_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `hafalan_siswa`
--
ALTER TABLE `hafalan_siswa`
  ADD CONSTRAINT `hafalan_siswa_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `hafalan_siswa_ibfk_2` FOREIGN KEY (`kitab_nadhom_id`) REFERENCES `kitab_nadhom` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `hafalan_siswa_ibfk_3` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `jadwal_pelajaran`
--
ALTER TABLE `jadwal_pelajaran`
  ADD CONSTRAINT `fk_jadwal_guru_id` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_jadwal_kelas_id` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_jadwal_mapel_id` FOREIGN KEY (`mapel_id`) REFERENCES `mata_pelajaran` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `jadwal_siswa`
--
ALTER TABLE `jadwal_siswa`
  ADD CONSTRAINT `jadwal_siswa_ibfk_1` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_pelajaran` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jadwal_siswa_ibfk_2` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `jenis_pembayaran`
--
ALTER TABLE `jenis_pembayaran`
  ADD CONSTRAINT `fk_jenis_pembayaran_unit` FOREIGN KEY (`unit_id`) REFERENCES `kas_unit` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `kas_transaksi`
--
ALTER TABLE `kas_transaksi`
  ADD CONSTRAINT `kas_transaksi_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `kas_kategori` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `kas_transaksi_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `kas_unit` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `kas_transaksi_ibfk_3` FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `ngaji_siswa`
--
ALTER TABLE `ngaji_siswa`
  ADD CONSTRAINT `fk_ngaji_siswa_guru` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ngaji_siswa_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pelanggaran_siswa`
--
ALTER TABLE `pelanggaran_siswa`
  ADD CONSTRAINT `pelanggaran_siswa_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pelanggaran_siswa_ibfk_2` FOREIGN KEY (`jenis_pelanggaran_id`) REFERENCES `jenis_pelanggaran` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pelanggaran_siswa_ibfk_3` FOREIGN KEY (`petugas_id`) REFERENCES `guru` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pengumuman`
--
ALTER TABLE `pengumuman`
  ADD CONSTRAINT `pengumuman_ibfk_1` FOREIGN KEY (`dikirim_oleh_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `siswa`
--
ALTER TABLE `siswa`
  ADD CONSTRAINT `fk_siswa_kelas_id` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `sorogan_siswa`
--
ALTER TABLE `sorogan_siswa`
  ADD CONSTRAINT `sorogan_siswa_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sorogan_siswa_ibfk_2` FOREIGN KEY (`kitab_nadhom_id`) REFERENCES `kitab_nadhom` (`id`),
  ADD CONSTRAINT `sorogan_siswa_ibfk_3` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`);

--
-- Ketidakleluasaan untuk tabel `tagihan_siswa`
--
ALTER TABLE `tagihan_siswa`
  ADD CONSTRAINT `tagihan_siswa_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tagihan_siswa_ibfk_2` FOREIGN KEY (`jenis_pembayaran_id`) REFERENCES `jenis_pembayaran` (`id`),
  ADD CONSTRAINT `tagihan_siswa_ibfk_3` FOREIGN KEY (`tahun_ajaran_id`) REFERENCES `tahun_ajaran` (`id`),
  ADD CONSTRAINT `tagihan_siswa_ibfk_4` FOREIGN KEY (`kelas_id_saat_tagih`) REFERENCES `kelas` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `tunjangan_bulanan_guru`
--
ALTER TABLE `tunjangan_bulanan_guru`
  ADD CONSTRAINT `tunjangan_bulanan_guru_ibfk_1` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tunjangan_bulanan_guru_ibfk_2` FOREIGN KEY (`tahun_ajaran_id`) REFERENCES `tahun_ajaran` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
