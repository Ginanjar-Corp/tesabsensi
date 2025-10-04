<?php
// project-root/staff/dashboard.php

// Memastikan file konfigurasi dan helper yang diperlukan sudah di-include
require_once __DIR__ . '/../config/database.php'; // Atau constants.php jika koneksi ada di sana
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php'; // Pastikan file ini ada dan berisi fungsi-fungsi hitung
require_once __DIR__ . '/../includes/auth.php';

// Memastikan pengguna sudah login dan memiliki peran yang sesuai
require_login();
require_role(['Admin', 'TataUsaha']); // Hanya Admin dan Tata Usaha yang bisa akses dashboard ini

// Mengambil data user dari sesi
$user_data = $_SESSION['user_data'] ?? [];
$username = $user_data['username'] ?? 'Pengguna';
$role_name = $user_data['role_name'] ?? 'Tidak Dikenal';

// --- Mengambil data statistik menggunakan fungsi-fungsi helper ---
// Pastikan fungsi-fungsi ini didefinisikan di includes/helpers.php atau file lain yang di-include
$total_siswa_aktif = getTotalSiswaAktif();
$total_guru_aktif = getTotalGuruAktif();
$total_kelas = getTotalKelas();
$total_mapel = getTotalMataPelajaran();
$siswa_absen_hari_ini = getSiswaAbsenHariIni();
$siswa_belum_absen_hari_ini = getSiswaBelumAbsenHariIni();
$guru_absen_hari_ini = getGuruAbsenHariIni();
$guru_belum_absen_hari_ini = getGuruBelumAbsenHariIni();
// --- Akhir pengambilan data statistik ---

// Mengambil data guru dengan jadwal hari ini dan status kehadiran
$guruJadwalHariIni = getGuruJadwalDanKehadiranHariIni();

// --- Membaca konten dari file update.txt ---
$update_log_content = "File update.txt tidak ditemukan atau tidak dapat dibaca.";
$update_file_path = __DIR__ . '/../logs/update.txt'; // Sesuaikan path jika file berada di lokasi lain

if (file_exists($update_file_path) && is_readable($update_file_path)) {
    $update_log_content = file_get_contents($update_file_path);
} else {
    error_log("Error: update.txt not found or not readable at " . $update_file_path);
}
// --- Akhir membaca konten dari file update.txt ---


// Menyertakan header (yang sekarang berisi sidebar dan pembuka div konten utama)
require_once __DIR__ . '/../components/header_staff.php';
?>
        <style>
        /* CSS yang sudah ada untuk tabel */
        .table-container {
            background-color: #ffffff;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            /* overflow-x: auto; Ini akan dipindahkan ke kelas Tailwind */
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0; /* gray-200 */
        }
        th {
            background-color: #edf2f7; /* gray-100 */
            font-weight: 600;
            color: #2d3748; /* gray-800 */
            text-transform: uppercase;
            font-size: 0.875rem; /* text-sm */
        }
        tbody tr:last-child td {
            border-bottom: none;
        }
        tbody tr:hover {
            background-color: #f7fafc; /* gray-50 */
        }
        /* Gaya untuk kotak log update */
        .update-log-container {
            background-color: #ffffff;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-top: 1.5rem;
            border: 1px solid #e2e8f0;
        }
        .update-log-content {
            white-space: pre-wrap; /* Mempertahankan spasi dan baris baru */
            font-family: monospace;
            font-size: 0.875rem; /* text-sm */
            color: #4a5568; /* gray-700 */
            max-height: 300px; /* Batasi tinggi untuk scroll */
            overflow-y: auto; /* Tambahkan scroll jika konten terlalu panjang */
            padding: 1rem;
            background-color: #f7fafc; /* gray-50 */
            border-radius: 0.5rem;
            border: 1px dashed #cbd5e0; /* gray-300 */
        }
        </style>
        <!-- Konten Dashboard Spesifik -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
            <h1 class="text-3xl font-semibold text-gray-800 mb-4 sm:mb-0">Dashboard</h1>
            <div class="text-gray-600 text-right">
                <p>Selamat datang, <span class="font-bold"><?php echo htmlspecialchars($username); ?></span>!</p>
                <p>Anda login sebagai: <span class="font-bold"><?php echo htmlspecialchars($role_name); ?></span>.</p>
            </div>
        </div>

        <!-- Bagian Statistik -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
            <!-- Kartu Siswa Aktif -->
            <div class="bg-white rounded-lg shadow-md p-6 flex items-center justify-between border border-gray-200 hover:shadow-lg transition duration-300">
                <div>
                    <p class="text-sm text-gray-500 uppercase font-semibold">Siswa Aktif</p>
                    <p class="text-4xl font-bold text-gray-800 mt-2"><?php echo htmlspecialchars($total_siswa_aktif); ?></p>
                </div>
                <i class="fas fa-user-graduate text-5xl text-blue-400 opacity-75"></i>
            </div>
            <!-- Kartu Guru Aktif -->
            <div class="bg-white rounded-lg shadow-md p-6 flex items-center justify-between border border-gray-200 hover:shadow-lg transition duration-300">
                <div>
                    <p class="text-sm text-gray-500 uppercase font-semibold">Guru Aktif</p>
                    <p class="text-4xl font-bold text-gray-800 mt-2"><?php echo htmlspecialchars($total_guru_aktif); ?></p>
                </div>
                <i class="fas fa-chalkboard-teacher text-5xl text-green-400 opacity-75"></i>
            </div>
            <!-- Kartu Total Kelas -->
            <div class="bg-white rounded-lg shadow-md p-6 flex items-center justify-between border border-gray-200 hover:shadow-lg transition duration-300">
                <div>
                    <p class="text-sm text-gray-500 uppercase font-semibold">Total Kelas</p>
                    <p class="text-4xl font-bold text-gray-800 mt-2"><?php echo htmlspecialchars($total_kelas); ?></p>
                </div>
                <i class="fas fa-school text-5xl text-purple-400 opacity-75"></i>
            </div>
            <!-- Kartu Total Mata Pelajaran -->
            <div class="bg-white rounded-lg shadow-md p-6 flex items-center justify-between border border-gray-200 hover:shadow-lg transition duration-300">
                <div>
                    <p class="text-sm text-gray-500 uppercase font-semibold">Total Mata Pelajaran</p>
                    <p class="text-4xl font-bold text-gray-800 mt-2"><?php echo htmlspecialchars($total_mapel); ?></p>
                </div>
                <i class="fas fa-book text-5xl text-orange-400 opacity-75"></i>
            </div>

            <!-- Kartu Siswa Hadir Hari Ini -->
            <div class="bg-white rounded-lg shadow-md p-6 flex items-center justify-between border border-gray-200 hover:shadow-lg transition duration-300">
                <div>
                    <p class="text-sm text-gray-500 uppercase font-semibold">Siswa Hadir Hari Ini</p>
                    <p class="text-4xl font-bold text-gray-800 mt-2"><?php echo htmlspecialchars($siswa_absen_hari_ini); ?></p>
                </div>
                <i class="fas fa-check-circle text-5xl text-teal-400 opacity-75"></i>
            </div>
            <!-- Kartu Siswa Belum Absen Hari Ini -->
            <div class="bg-white rounded-lg shadow-md p-6 flex items-center justify-between border border-gray-200 hover:shadow-lg transition duration-300">
                <div>
                    <p class="text-sm text-gray-500 uppercase font-semibold">Siswa Tidak Hadir Hari Ini</p>
                    <p class="text-4xl font-bold text-gray-800 mt-2"><?php echo htmlspecialchars($siswa_belum_absen_hari_ini); ?></p>
                </div>
                <i class="fas fa-times-circle text-5xl text-red-400 opacity-75"></i>
            </div>

            <!-- Kartu Guru Hadir Hari Ini -->
            <div class="bg-white rounded-lg shadow-md p-6 flex items-center justify-between border border-gray-200 hover:shadow-lg transition duration-300">
                <div>
                    <p class="text-sm text-gray-500 uppercase font-semibold">Guru Hadir Hari Ini</p>
                    <p class="text-4xl font-bold text-gray-800 mt-2"><?php echo htmlspecialchars($guru_absen_hari_ini); ?></p>
                </div>
                <i class="fas fa-user-check text-5xl text-indigo-400 opacity-75"></i>
            </div>
            <!-- Kartu Guru Belum Absen Hari Ini -->
            <div class="bg-white rounded-lg shadow-md p-6 flex items-center justify-between border border-gray-200 hover:shadow-lg transition duration-300">
                <div>
                    <p class="text-sm text-gray-500 uppercase font-semibold">Guru Belum Absen Hari Ini</p>
                    <p class="text-4xl font-bold text-gray-800 mt-2"><?php echo htmlspecialchars($guru_belum_absen_hari_ini); ?></p>
                </div>
                <i class="fas fa-user-times text-5xl text-yellow-500 opacity-75"></i>
            </div>
        </div>

        <!-- Bagian Jadwal Mengajar Guru Hari Ini (Dibuat full width) -->
        <div class="w-full px-6 mb-8">
    <h3 class="text-2xl font-bold text-gray-800 mb-6">Jadwal Mengajar Guru Hari Ini</h3>
    <div class="table-container overflow-y-auto max-h-96 overflow-x-auto">
        <?php if (!empty($guruJadwalHariIni)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Nama Guru</th>
                        <th>Mata Pelajaran</th>
                        <th>Kelas / Siswa</th>
                        <th>Tipe Jadwal</th>
                        <th>Jam Mengajar</th>
                        <th>Status Kehadiran</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($guruJadwalHariIni as $jadwal): ?>
                        <tr>
                            <td><?= htmlspecialchars($jadwal['nama_lengkap']) ?></td>
                            <td><?= htmlspecialchars($jadwal['nama_mapel']) ?></td>
                            <td><?= htmlspecialchars($jadwal['nama_kelas']) ?></td>
                            <td><?= htmlspecialchars($jadwal['tipe_jadwal']) ?></td>
                            <td><?= htmlspecialchars($jadwal['jam_mulai']) ?> - <?= htmlspecialchars($jadwal['jam_selesai']) ?></td>
                            <td>
                                <span class="px-2 py-1 rounded-full text-xs font-semibold
                                    <?= $jadwal['status_kehadiran'] == 'Hadir' ? 'bg-green-200 text-green-800' : 'bg-red-200 text-red-800' ?>">
                                    <?= htmlspecialchars($jadwal['status_kehadiran']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="p-4 text-gray-600 text-center">Tidak ada guru yang memiliki jadwal mengajar hari ini.</p>
        <?php endif; ?>
    </div>
</div>

        <!-- Bagian Laporan dan Akses Cepat -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Laporan Absensi -->
            <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
                <h2 class="text-xl font-semibold mb-4 text-gray-800">Laporan Absensi</h2>
                <div class="space-y-4">
                    <a href="<?php echo ROOT_URL; ?>staff/laporan_absensi_gerbang_siswa.php" class="flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition duration-300 border border-gray-100">
                        <span class="text-gray-700 font-medium">Absensi Siswa (Gerbang)</span>
                        <i class="fas fa-chart-line text-blue-500"></i>
                    </a>
                    <a href="<?php echo ROOT_URL; ?>staff/laporan_absensi_mapel_siswa.php" class="flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition duration-300 border border-gray-100">
                        <span class="text-gray-700 font-medium">Absensi Siswa (Mapel)</span>
                        <i class="fas fa-chart-bar text-green-500"></i>
                    </a>
                    <a href="<?php echo ROOT_URL; ?>staff/laporan_absensi_gerbang_guru.php" class="flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition duration-300 border border-gray-100">
                        <span class="text-gray-700 font-medium">Absensi Guru (Gerbang)</span>
                        <i class="fas fa-chart-pie text-red-500"></i>
                    </a>
                    <a href="<?php echo ROOT_URL; ?>staff/laporan_absensi_mapel_guru.php" class="flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition duration-300 border border-gray-100">
                        <span class="text-gray-700 font-medium">Absensi Guru (Mapel)</span>
                        <i class="fas fa-chart-area text-yellow-500"></i>
                    </a>
                </div>
            </div>

            <!-- Akses Cepat -->
            <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
                <h2 class="text-xl font-semibold mb-4 text-gray-800">Akses Cepat</h2>
                <div class="space-y-4">
                    <a href="<?php echo ROOT_URL; ?>staff/absensi_gerbang.php" class="flex items-center justify-between p-4 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition duration-300 shadow-md">
                        <span class="font-medium">Mulai Absen Datang</span>
                        <i class="fas fa-fingerprint"></i>
                    </a>
                    <a href="<?php echo ROOT_URL; ?>staff/absensi_gerbang_pulang.php" class="flex items-center justify-between p-4 bg-green-500 hover:bg-green-600 text-white rounded-lg transition duration-300 shadow-md">
                        <span class="font-medium">Mulai Absen Pulang</span>
                        <i class="fas fa-fingerprint"></i>
                    </a>
                    <a href="<?php echo ROOT_URL; ?>staff/settings/school_settings.php" class="flex items-center justify-between p-4 bg-purple-500 hover:bg-purple-600 text-white rounded-lg transition duration-300 shadow-md">
                        <span class="font-medium">Pengaturan Sekolah</span>
                        <i class="fas fa-cog"></i>
                    </a>
                    <a href="<?php echo ROOT_URL; ?>staff/naik_kelas.php" class="flex items-center justify-between p-4 bg-red-500 hover:bg-red-600 text-white rounded-lg transition duration-300 shadow-md">
                        <span class="font-medium">Naik Kelas & Kelulusan</span>
                        <i class="fas fa-arrow-up"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Bagian Log Update -->
        <div class="update-log-container">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Log Pembaruan Aplikasi</h2>
            <div class="update-log-content">
                <?php echo htmlspecialchars($update_log_content); ?>
            </div>
        </div>

<?php
// Menyertakan footer (yang sekarang akan menutup div konten utama dan div utama)
require_once __DIR__ . '/../components/footer.php';
?>
