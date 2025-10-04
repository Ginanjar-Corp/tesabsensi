<?php
// project-root/components/header_guru.php

//awal ambil logo

$school_logo_path = '';
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $school_settings_model = new SchoolSettings($pdo);
    $settings = $school_settings_model->getSettings();
    if ($settings && !empty($settings['logo_sekolah_path'])) {
        $school_logo_path = htmlspecialchars($settings['logo_sekolah_path']);
    }
} catch (PDOException $e) {
    // Log error koneksi database, tapi jangan hentikan eksekusi
    error_log("Database connection error in header: " . $e->getMessage());
} catch (Exception $e) {
    // Log error umum, tapi jangan hentikan eksekusi
    error_log("Error fetching school settings in header: " . $e->getMessage());
}

// Tambahkan favicon jika logo sekolah tersedia
if (!empty($school_logo_path)) {
    echo '<link rel="icon" href="' . $school_logo_path . '" type="image/x-icon">';
    echo '<link rel="shortcut icon" href="' . $school_logo_path . '" type="image/x-icon">';
} else {
    // Fallback favicon jika tidak ada logo sekolah (opsional)
    // Anda bisa menunjuk ke ikon default atau tidak menampilkan apa-apa
    // echo '<link rel="icon" href="' . ROOT_URL . 'assets/images/default_favicon.ico" type="image/x-icon">';
}

//akhir ambil logo

// Pastikan APP_NAME sudah didefinisikan, misalnya di config/app_config.php
// Jika belum, tambahkan: define('APP_NAME', 'Nama Aplikasi Anda');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Guru - <?php echo APP_NAME; ?></title>
    <!-- Tailwind CSS CDN (untuk pengembangan) atau link ke build CSS Anda -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Font Awesome untuk ikon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        /* Mengatur font Inter secara global */
        body {
            font-family: 'Inter', sans-serif;
        }
        /* Styling tambahan untuk menu sidebar jika diperlukan */
        .sidebar-menu-item {
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .sidebar-menu-item:hover {
            background-color: #3b82f6; /* bg-blue-500 */
            color: white;
        }
        .sidebar-menu-item.active {
            background-color: #2563eb; /* bg-blue-600 */
            color: white;
        }

        /* Overlay untuk menutup sidebar saat terbuka di mobile */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 40; /* Di bawah sidebar, di atas konten utama */
            display: none; /* Sembunyikan secara default */
        }
        .overlay.active {
            display: block;
        }
    </style>
</head>
<body class="bg-gray-100 antialiased">

    <!-- Overlay untuk menutup sidebar saat di mobile -->
    <div id="sidebar-overlay" class="overlay" onclick="toggleSidebar()"></div>

    <!-- Kontainer utama dengan flexbox untuk layout sidebar dan konten -->
    <div class="flex h-screen bg-gray-100">

        <!-- Sidebar Guru Panel -->
        <!-- Tambahkan ID dan kelas responsif: hidden by default on small screens, flex on medium and up -->
        <div id="guru-sidebar" class="fixed inset-y-0 left-0 w-64 bg-white shadow-lg p-6 flex flex-col justify-between z-50 transform -translate-x-full md:relative md:translate-x-0 transition-transform duration-300 ease-in-out md:rounded-r-lg">
            <!-- Bagian atas sidebar (judul dan menu) -->
            <div class="flex-1 overflow-y-auto pr-2"> <!-- Ditambahkan flex-1 dan overflow-y-auto di sini -->
                <div class="text-2xl font-bold text-gray-800 mb-8">Guru Panel</div>
                <nav>
                    <ul>
                        <li class="mb-4">
                            <!-- Tambahkan kelas 'active' jika halaman saat ini adalah Dashboard Guru -->
                            <a href="<?php echo ROOT_URL; ?>guru/dashboard.php" class="flex items-center sidebar-menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
                                <i class="fas fa-home mr-3"></i> Dashboard
                            </a>
                        </li>
                        <li class="mb-4">
                            <a href="<?php echo ROOT_URL; ?>guru/absensi_mapel.php" class="flex items-center sidebar-menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'absensi_mapel.php') ? 'active' : ''; ?>">
                                <i class="fas fa-clipboard-check mr-3"></i> Absensi Mata Pelajaran
                            </a>
                        </li>
                        <li class="mb-4">
                            <a href="<?php echo ROOT_URL; ?>guru/jadwal_saya.php" class="flex items-center sidebar-menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'jadwal_saya.php') ? 'active' : ''; ?>">
                                <i class="fas fa-calendar-alt mr-3"></i> Jadwal Mengajar
                            </a>
                        </li>
                        <li class="mb-4">
                            <a href="<?php echo ROOT_URL; ?>guru/laporan_absensi_siswa.php" class="flex items-center sidebar-menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'laporan_absensi_siswa.php') ? 'active' : ''; ?>">
                                <i class="fas fa-chart-bar mr-3"></i> Laporan Absensi Siswa
                            </a>
                        </li>
                        <!-- Menu baru untuk laporan absensi harian guru -->
                        <li class="mb-4">
                            <a href="<?php echo ROOT_URL; ?>guru/laporan_absensi_guru.php" class="flex items-center sidebar-menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'laporan_absensi_guru.php') ? 'active' : ''; ?>">
                                <i class="fas fa-user-clock mr-3"></i> Kehadiran Harian Saya
                            </a>
                        </li>
                        <!--<li class="mb-4">
                            <a href="<?php echo ROOT_URL; ?>guru/rekap_gaji_guru.php" class="flex items-center sidebar-menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'rekap_gaji_guru.php') ? 'active' : ''; ?>">
                                <i class="fa fa-money-bill-wave mr-3"></i> Rekap Bisyaroh
                            </a>
                        </li>-->
                        
                         <!--<li class="mb-4">
                            <h3 class="text-xs uppercase text-gray-500 font-semibold mb-2 ml-3">Pencatatan Siswa</h3>
                        </li>
                        <li class="mb-4">
                            <a href="<?php echo ROOT_URL; ?>guru/pelanggaran_siswa.php" class="flex items-center sidebar-menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'pelanggaran_siswa.php') ? 'active' : ''; ?>">
                                <i class="fas fa-gavel mr-3"></i> Pelanggaran Siswa
                            </a>
                        </li>
                        <li class="mb-4">
                            <a href="<?php echo ROOT_URL; ?>guru/hafalan_siswa.php" class="flex items-center sidebar-menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'hafalan_siswa.php') ? 'active' : ''; ?>">
                                <i class="fas fa-book-open mr-3"></i> Pencatatan Hafalan
                            </a>
                        </li>
                        <li class="mb-4">
                            <a href="<?php echo ROOT_URL; ?>guru/manage_sorogan_siswa.php" class="flex items-center sidebar-menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_sorogan_siswa.php') ? 'active' : ''; ?>">
                                <i class="fas fa-pray mr-3"></i> Pencatatan Sorogan
                            </a>
                        </li>
                        <li class="mb-4">
                            <a href="<?php echo ROOT_URL; ?>guru/izin_keluar_pulang.php" class="flex items-center sidebar-menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'izin_keluar_pulang.php') ? 'active' : ''; ?>">
                                <i class="fas fa-door-open mr-3"></i> Izin Keluar/Pulang
                            </a>
                        </li>-->
                        <!-- Tambahkan menu lain yang relevan untuk guru di sini -->
                    </ul>
                </nav>
            </div>
            <!-- Bagian bawah sidebar (logout) -->
            <div class="border-t pt-4">
                <a href="<?php echo ROOT_URL; ?>logout.php" class="flex items-center p-2 rounded-lg text-red-500 hover:bg-red-500 hover:text-white font-medium transition duration-300">
                    <i class="fas fa-sign-out-alt mr-3"></i> Logout
                </a>
            </div>
        </div>

        <!-- Area Konten Utama - Dimulai di sini, akan ditutup di footer.php -->
        <div class="flex-1 p-8 overflow-y-auto">
            <!-- Hamburger Menu Button (hanya terlihat di layar kecil) -->
            <button id="guru-hamburger-button" class="md:hidden fixed top-4 left-4 z-40 p-2 bg-blue-600 text-white rounded-lg shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-bars text-xl"></i>
            </button>
