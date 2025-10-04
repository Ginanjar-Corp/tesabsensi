<?php
// project-root/components/header_staff.php

// Inisialisasi path logo sekolah
$school_logo_path = '';

try {
    // Mendapatkan koneksi database dan model pengaturan sekolah
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $school_settings_model = new SchoolSettings($pdo);
    $settings = $school_settings_model->getSettings();

    // Jika pengaturan dan path logo sekolah ada, gunakan path tersebut
    if ($settings && !empty($settings['logo_sekolah_path'])) {
        $school_logo_path = htmlspecialchars($settings['logo_sekolah_path']);
    }
} catch (PDOException $e) {
    // Tangani error koneksi database
    error_log("Database connection error in header: " . $e->getMessage());
} catch (Exception $e) {
    // Tangani error umum saat mengambil pengaturan sekolah
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

// Pastikan APP_NAME sudah didefinisikan, misalnya di config/app_config.php
// Jika belum, tambahkan di file config/app_config.php: define('APP_NAME', 'Nama Aplikasi Anda');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Staff - <?php echo APP_NAME; ?></title>
    <!-- Tailwind CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Font Awesome untuk ikon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        /* Mengatur font Inter secara global */
        body {
            font-family: 'Inter', sans-serif;
        }
        /* Styling tambahan untuk menu sidebar */
        .sidebar-menu-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem; /* Padding lebih besar untuk area klik yang lebih baik */
            border-radius: 0.5rem;
            color: #4b5563; /* text-gray-700 */
            transition: background-color 0.3s ease, color 0.3s ease;
            text-decoration: none; /* Hapus underline default */
            cursor: pointer; /* Menunjukkan bahwa item dapat diklik */
        }
        .sidebar-menu-item:hover {
            background-color: #3b82f6; /* bg-blue-500 */
            color: white;
        }
        .sidebar-menu-item.active {
            background-color: #2563eb; /* bg-blue-600 */
            color: white;
            font-weight: 600; /* Semi-bold untuk item aktif */
        }
        .sidebar-menu-item i {
            margin-right: 0.75rem; /* Spasi antara ikon dan teks */
        }

        /* Styling untuk dropdown */
        .dropdown-container {
            position: relative;
            margin-bottom: 0.5rem; /* mb-2 */
        }
        .dropdown-button {
            width: 100%;
            justify-content: space-between; /* Untuk memposisikan ikon panah di kanan */
        }
        .dropdown-content {
            padding-left: 1.5rem; /* Indentasi untuk submenu */
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out, padding-top 0.3s ease-out, padding-bottom 0.3s ease-out;
        }
        .dropdown-content.show {
            max-height: 500px; /* Cukup besar untuk menampung semua item submenu */
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }
        .dropdown-content a {
            padding: 0.5rem 1rem; /* Padding untuk item submenu */
            display: flex;
            align-items: center;
            color: #4b5563; /* text-gray-700 */
            border-radius: 0.5rem;
            text-decoration: none;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .dropdown-content a:hover {
            background-color: #e2e8f0; /* bg-gray-200 */
        }
        .dropdown-content a.active {
            background-color: #bfdbfe; /* bg-blue-200 */
            color: #1e40af; /* text-blue-800 */
            font-weight: 500;
        }
        .dropdown-content a i {
            margin-right: 0.75rem;
        }
        .arrow-icon {
            transition: transform 0.3s ease;
        }
        .dropdown-button.active .arrow-icon {
            transform: rotate(90deg);
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

        /* Responsif sidebar untuk mobile */
        #admin-sidebar {
            transition: transform 0.3s ease-in-out;
        }
        /* Hidden by default on small screens, flex on medium and up */
        @media (max-width: 767px) { /* md breakpoint in Tailwind is 768px */
            #admin-sidebar.translate-x-full {
                transform: translateX(-100%);
            }
            #admin-sidebar.translate-x-0 {
                transform: translateX(0);
            }
        }
    </style>
</head>
<body class="bg-gray-100 antialiased">

    <!-- Overlay untuk menutup sidebar saat di mobile -->
    <div id="sidebar-overlay" class="overlay" onclick="toggleSidebar()"></div>

    <!-- Kontainer utama dengan flexbox untuk layout sidebar dan konten -->
    <div class="flex h-screen bg-gray-100">

        <!-- Sidebar Admin Panel -->
        <div id="admin-sidebar" class="fixed inset-y-0 left-0 w-64 bg-white shadow-lg p-6 flex flex-col justify-between z-50 transform -translate-x-full md:relative md:translate-x-0 md:rounded-r-lg">
            <!-- Bagian atas sidebar (judul dan menu) -->
            <div class="flex-1 overflow-y-auto pr-2">
                <div class="text-2xl font-bold text-gray-800 mb-8">Admin Panel</div>
                <nav>
                    <ul>
                        <li class="mb-2">
                            <a href="<?php echo ROOT_URL; ?>staff/dashboard.php" class="sidebar-menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                        </li>

                        <!-- Dropdown Data Master -->
                        <li class="dropdown-container">
                            <a class="sidebar-menu-item dropdown-button" onclick="toggleDropdown(this, 'dataMasterDropdown')">
                                <i class="fas fa-database"></i> Data Master
                                <i class="fas fa-chevron-right ml-auto arrow-icon"></i>
                            </a>
                            <ul id="dataMasterDropdown" class="dropdown-content">
                                <li><a href="<?php echo ROOT_URL; ?>staff/manage_users.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'manage_users.php') ? 'active' : ''; ?>">
                                    <i class="fas fa-users-cog"></i> Pengguna</a></li>
                                <li><a href="<?php echo ROOT_URL; ?>staff/manage_tahun_ajaran.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'manage_tahun_ajaran.php') ? 'active' : ''; ?>">
                                    <i class="fas fa-calendar-check"></i> Tahun Ajaran</a></li>
                                <li><a href="<?php echo ROOT_URL; ?>staff/manage_kelas.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'manage_kelas.php') ? 'active' : ''; ?>">
                                    <i class="fas fa-school"></i> Kelas</a></li>
                                <li><a href="<?php echo ROOT_URL; ?>staff/manage_siswa.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'manage_siswa.php') ? 'active' : ''; ?>">
                                    <i class="fas fa-user-graduate"></i> Siswa</a></li>
                                <!--<li><a href="<?php echo ROOT_URL; ?>staff/manage_guru.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'manage_guru.php') ? 'active' : ''; ?>">
                                    <i class="fas fa-chalkboard-teacher"></i> Guru</a></li>-->
                                <li><a href="<?php echo ROOT_URL; ?>staff/manage_mapel.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'manage_mapel.php') ? 'active' : ''; ?>">
                                    <i class="fas fa-book"></i> Mata Pelajaran</a></li>
                                <li><a href="<?php echo ROOT_URL; ?>staff/manage_jadwal.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'manage_jadwal.php') ? 'active' : ''; ?>">
                                    <i class="fas fa-calendar-alt"></i> Jadwal</a></li>
                                <li><a href="<?php echo ROOT_URL; ?>staff/kalender_pendidikan.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'kalender_pendidikan.php') ? 'active' : ''; ?>">
                                    <i class="fas fa-calendar-alt"></i> Kaldik</a></li>
                            </ul>
                        </li>

                        <!-- Dropdown Absensi -->
                        <li class="dropdown-container">
                            <a class="sidebar-menu-item dropdown-button" onclick="toggleDropdown(this, 'absensiDropdown')">
                                <i class="fas fa-clipboard-check"></i> Absensi
                                <i class="fas fa-chevron-right ml-auto arrow-icon"></i>
                            </a>
                            <ul id="absensiDropdown" class="dropdown-content">
                                <li><a href="<?php echo ROOT_URL; ?>staff/absensi_gerbang.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'absensi_gerbang.php') ? 'active' : ''; ?>">
                                    <i class="fas fa-fingerprint"></i> Absensi Masuk</a></li>
                                <li><a href="<?php echo ROOT_URL; ?>staff/absensi_gerbang_pulang.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'absensi_gerbang_pulang.php') ? 'active' : ''; ?>">
                                    <i class="fas fa-fingerprint"></i> Absensi Pulang</a></li>
                                <li><a href="<?php echo ROOT_URL; ?>staff/manage_absensi_siswa.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'manage_absensi_siswa.php') ? 'active' : ''; ?>">
                                    <i class="fas fa-user-check"></i> Manual Siswa</a></li>
                                <li><a href="<?php echo ROOT_URL; ?>staff/manage_absensi_guru.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'manage_absensi_guru.php') ? 'active' : ''; ?>">
                                    <i class="fas fa-chalkboard-teacher"></i> Manual Guru</a></li>
                                <!--<li><a href="<?php echo ROOT_URL; ?>admin/absensi_mapel_admin.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'absensi_mapel_admin.php') ? 'active' : ''; ?>">
                                    <i class="fas fa-user-check"></i> Absensi Mapel</a></li>-->
                                <li><a href="<?php echo ROOT_URL; ?>staff/manage_absensi_mapel.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'manage_absensi_mapel.php') ? 'active' : ''; ?>">
                                    <i class="fas fa-user-check"></i> Manual Mapel</a></li>
                            </ul>
                        </li>
                        
                        <!-- Dropdown Rekap Absensi -->
                        <li class="dropdown-container">
                            <a class="sidebar-menu-item dropdown-button" onclick="toggleDropdown(this, 'rekapDropdown')">
                                <i class="fas fa-calendar-check"></i> Kirim Rekap Absensi
                                <i class="fas fa-chevron-right ml-auto arrow-icon"></i>
                            </a>
                            <ul id="rekapDropdown" class="dropdown-content">
                                <li><a href="<?php echo ROOT_URL; ?>staff/rekap_absensi.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'rekap_absensi.php') ? 'active' : ''; ?>">
                                    <i class="fas fa-paper-plane"></i> Kirim Rekap Absensi</a></li>
                                <li><a href="<?php echo ROOT_URL; ?>staff/rekap_absensi_mapel.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'rekap_absensi_mapel.php') ? 'active' : ''; ?>">
                                    <i class="fas fa-chalkboard-teacher"></i> Kirim Rekap Absensi Mapel</a></li>
                            </ul>
                        </li>
    

                        <!-- Menu Lainnya (Non-dropdown) -->
                        <li class="mb-2">
                            <a href="<?php echo ROOT_URL; ?>staff/naik_kelas.php" class="sidebar-menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'naik_kelas.php') ? 'active' : ''; ?>">
                                <i class="fas fa-arrow-up"></i> Naik Kelas & Lulus
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo ROOT_URL; ?>staff/manage_pengumuman.php" class="sidebar-menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_pengumuman.php') ? 'active' : ''; ?>">
                                <i class="fas fa-comment"></i> Pengumuman
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo ROOT_URL; ?>staff/manage_informasi_umum.php" class="sidebar-menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_informasi_umum.php') ? 'active' : ''; ?>">
                                <i class="fas fa-comment"></i> Informasi
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo ROOT_URL; ?>staff/manage_whatsapp_queue.php" class="sidebar-menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_whatsapp_queue.php') ? 'active' : ''; ?>">
                                <i class="fas fa-comments"></i> Pesan WhatsApp
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo ROOT_URL; ?>staff/settings/school_settings.php" class="sidebar-menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'school_settings.php') ? 'active' : ''; ?>">
                                <i class="fas fa-cog"></i> Setting Sekolah
                            </a>
                        </li>
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

        <!-- Area Konten Utama - Dimulai di sini, akan ditutup di footer_admin.php -->
        <div class="flex-1 p-8 overflow-y-auto">
            <!-- Hamburger Menu Button (hanya terlihat di layar kecil) -->
            <button id="admin-hamburger-button" class="md:hidden fixed top-4 left-4 z-40 p-2 bg-blue-600 text-white rounded-lg shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-bars text-xl"></i>
            </button>

<script>
    // Fungsi untuk toggle sidebar di mobile
    function toggleSidebar() {
        const sidebar = document.getElementById('admin-sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        sidebar.classList.toggle('-translate-x-full');
        sidebar.classList.toggle('translate-x-0');
        overlay.classList.toggle('active');
    }

    // Event listener untuk tombol hamburger
    document.getElementById('admin-hamburger-button').addEventListener('click', toggleSidebar);

    // Fungsi untuk toggle dropdown menu
    function toggleDropdown(buttonElement, dropdownId) {
        const dropdownContent = document.getElementById(dropdownId);
        dropdownContent.classList.toggle('show');
        buttonElement.classList.toggle('active'); // Toggle active class for arrow rotation
    }

    document.addEventListener('DOMContentLoaded', function() {
        const currentPath = window.location.pathname.split('/').pop();
        const dropdownButtons = document.querySelectorAll('.dropdown-button');

        // Buka dropdown jika ada item aktif di dalamnya
        dropdownButtons.forEach(button => {
            const dropdownContent = button.nextElementSibling; // Mengasumsikan dropdown-content adalah sibling berikutnya
            if (dropdownContent && dropdownContent.classList.contains('dropdown-content')) {
                const activeItemInDropdown = dropdownContent.querySelector(`.dropdown-content a.active`);
                if (activeItemInDropdown) {
                    dropdownContent.classList.add('show');
                    button.classList.add('active'); // Set dropdown button to active
                }
            }
        });
    });
</script>
