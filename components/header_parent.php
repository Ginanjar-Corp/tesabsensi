<?php
// project-root/components/header_parent.php

// Pastikan session sudah dimulai di halaman yang memanggil header ini
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Untuk kebutuhan demo, kita akan mengambil dari URL saat ini
$current_page_basename = basename($_SERVER['PHP_SELF']);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo (isset($page_title) ? $page_title . ' - ' : '') . APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
            /* Mencegah overflow horizontal yang disebabkan oleh sidebar yang bergeser keluar */
            overflow-x: hidden;
        }
        /* Default sidebar styling for desktop */
        .sidebar {
            width: 250px;
            background-color: #374151; /* bg-gray-700 */
            color: white;
            padding: 1rem;
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000; /* Pastikan sidebar di atas konten lain */
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease-in-out; /* Transisi untuk slide in/out */
        }
        .sidebar-menu-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: 0.375rem; /* rounded-md */
            transition: background-color 0.2s;
        }
        .sidebar-menu-item:hover {
            background-color: #4b5563; /* bg-gray-600 */
        }
        .sidebar-menu-item.active {
            background-color: #1d4ed8; /* bg-blue-700 */
        }
        
        /* Main content wrapper for desktop */
        .main-content-wrapper {
            margin-left: 250px; /* Offset konten utama sebesar lebar sidebar */
            flex-grow: 1;
            /* Pastikan konten tidak terpotong saat sidebar tertutup di mobile, 
               tetapi tetap bisa di-scroll secara vertikal */
            min-height: 100vh; 
            display: flex;
            flex-direction: column;
        }

        /* Overlay for mobile sidebar */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 40; /* Di bawah sidebar, di atas konten utama */
            display: none; /* Hidden by default */
        }
        .overlay.active {
            display: block;
        }

        /* Responsive styles for mobile */
        @media (max-width: 767px) { /* Applies to screens smaller than 768px (md breakpoint) */
            .sidebar {
                transform: translateX(-100%); /* Hide sidebar by default on mobile */
                /* position: fixed; already defined above */
                /* height: 100vh; already defined above */
                /* width: 250px; already defined above */
                /* box-shadow: already defined above */
            }
            .sidebar.translate-x-0 {
                transform: translateX(0); /* Show sidebar when active */
            }
            .main-content-wrapper {
                margin-left: 0; /* No left margin on mobile */
            }
            /* Hamburger button is hidden by default using md:hidden, 
               so no need to explicitly display it here for mobile, it's there by default */
        }
    </style>
</head>
<body class="bg-gray-100 antialiased">

    <!-- Overlay to close sidebar on mobile -->
    <div id="parent-sidebar-overlay" class="overlay" onclick="toggleSidebar()"></div>

    <!-- Main container for flex layout of sidebar and content -->
    <div class="flex min-h-screen bg-gray-100">

        <!-- Parent Panel Sidebar -->
        <div id="parent-sidebar" class="sidebar transform -translate-x-full md:translate-x-0 md:relative md:rounded-r-lg">
            <!-- Sidebar top section (title and menu) -->
            <div class="flex-1 overflow-y-auto pr-2">
                <div class="text-2xl font-bold text-center mb-6 border-b pb-4 border-gray-600">
                    <i class="fas fa-child mr-2"></i> Orang Tua
                </div>
                <nav>
                    <ul>
                        <li class="mb-2">
                            <a href="<?php echo ROOT_URL; ?>public_parent/dashboard.php" class="sidebar-menu-item <?php echo ($current_page_basename == 'dashboard.php') ? 'active' : ''; ?>">
                                <i class="fas fa-home mr-3"></i> Dashboard
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo ROOT_URL; ?>public_parent/hafalan.php" class="sidebar-menu-item <?php echo ($current_page_basename == 'hafalan.php') ? 'active' : ''; ?>">
                                <i class="fas fa-book-reader mr-3"></i> Hafalan Anak
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo ROOT_URL; ?>public_parent/sorogan.php" class="sidebar-menu-item <?php echo ($current_page_basename == 'sorogan.php') ? 'active' : ''; ?>">
                                <i class="fas fa-book-open mr-3"></i> Sorogan Anak
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo ROOT_URL; ?>public_parent/mengaji.php" class="sidebar-menu-item <?php echo ($current_page_basename == 'mengaji.php') ? 'active' : ''; ?>">
                                <i class="fas fa-quran mr-3"></i> Mengaji Anak
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo ROOT_URL; ?>public_parent/pelanggaran.php" class="sidebar-menu-item <?php echo ($current_page_basename == 'pelanggaran.php') ? 'active' : ''; ?>">
                                <i class="fas fa-exclamation-triangle mr-3"></i> Pelanggaran Anak
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo ROOT_URL; ?>public_parent/izin.php" class="sidebar-menu-item <?php echo ($current_page_basename == 'izin.php') ? 'active' : ''; ?>">
                                <i class="fas fa-calendar-alt mr-3"></i> Izin Keluar/Pulang
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo ROOT_URL; ?>public_parent/informasi_umum.php" class="sidebar-menu-item <?php echo ($current_page_basename == 'informasi_umum.php') ? 'active' : ''; ?>">
                                <i class="fas fa-comment-alt mr-3"></i> Informasi
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo ROOT_URL; ?>public_parent/data_diri.php" class="flex items-center p-3 rounded-lg hover:bg-indigo-700 transition duration-200 <?php echo (basename($_SERVER['PHP_SELF']) == 'data_diri.php') ? 'bg-indigo-700 shadow' : ''; ?>">
                                <i class="fas fa-user mr-3"></i> Data Diri
                            </a>
                </li>
                    </ul>
                </nav>
            </div>
            <!-- Sidebar bottom section (logout) -->
            <div class="border-t pt-4 border-gray-600">
                <a href="<?php echo ROOT_URL; ?>public_parent/logout.php" class="flex items-center p-2 rounded-lg text-red-300 hover:bg-gray-600 hover:text-white font-medium transition duration-300">
                    <i class="fas fa-sign-out-alt mr-3"></i> Logout
                </a>
            </div>
        </div>

        <!-- Main Content Area - Starts here, closes in footer_parent.php -->
        <div class="main-content-wrapper p-8 overflow-y-auto">
            <!-- Hamburger Menu Button (only visible on small screens) -->
            <button id="parent-hamburger-button" class="md:hidden fixed top-4 left-4 z-40 p-2 bg-blue-600 text-white rounded-lg shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <script>
                // Fungsi untuk toggle sidebar di mobile
                function toggleSidebar() {
                    const sidebar = document.getElementById('parent-sidebar');
                    const overlay = document.getElementById('parent-sidebar-overlay');
                    sidebar.classList.toggle('translate-x-0');
                    // Tambahkan kelas untuk menggeser konten utama (opsional, tergantung layout)
                    // document.querySelector('.main-content-wrapper').classList.toggle('md:ml-0');
                    overlay.classList.toggle('active');
                }

                // Event listener untuk tombol hamburger
                document.getElementById('parent-hamburger-button').addEventListener('click', toggleSidebar);

                // Event listener untuk overlay (menutup sidebar saat klik di luar)
                document.getElementById('parent-sidebar-overlay').addEventListener('click', toggleSidebar);

                document.addEventListener('DOMContentLoaded', function() {
                    // Logika untuk menandai menu aktif (sudah ada di PHP, ini hanya memastikan)
                    const currentPath = window.location.pathname.split('/').pop();
                    const sidebarLinks = document.querySelectorAll('#parent-sidebar nav a.sidebar-menu-item');
                    sidebarLinks.forEach(link => {
                        // Hapus kelas 'active' dari semua tautan terlebih dahulu
                        link.classList.remove('active');
                        if (link.href.endsWith(currentPath)) {
                            link.classList.add('active');
                        }
                    });

                    // Jika di mobile, sidebar harus dalam keadaan tersembunyi secara default
                    const sidebar = document.getElementById('parent-sidebar');
                    const overlay = document.getElementById('parent-sidebar-overlay');
                    if (window.innerWidth < 768) {
                        sidebar.classList.add('-translate-x-full'); // Pastikan tersembunyi
                        sidebar.classList.remove('translate-x-0'); // Pastikan tidak terlihat
                        overlay.classList.remove('active'); // Pastikan overlay tidak aktif
                    }
                });

                // Perbarui status sidebar saat ukuran jendela diubah
                window.addEventListener('resize', function() {
                    const sidebar = document.getElementById('parent-sidebar');
                    const overlay = document.getElementById('parent-sidebar-overlay');
                    if (window.innerWidth >= 768) {
                        sidebar.classList.remove('-translate-x-full'); // Di desktop, selalu tampil
                        sidebar.classList.add('translate-x-0');
                        overlay.classList.remove('active'); // Overlay tidak perlu di desktop
                    } else {
                        // Di mobile, pastikan tersembunyi jika belum dibuka
                        if (!sidebar.classList.contains('translate-x-0')) {
                            sidebar.classList.add('-translate-x-full');
                            sidebar.classList.remove('translate-x-0');
                        }
                    }
                });
            </script>
