<?php
// project-root/public_parent/dashboard.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_parent.php'; // Sertakan file autentikasi orang tua
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/Kelas.php';
require_once __DIR__ . '/../classes/HafalanSiswa.php';
require_once __DIR__ . '/../classes/SoroganSiswa.php';
require_once __DIR__ . '/../classes/NgajiSiswa.php';
require_once __DIR__ . '/../classes/PelanggaranSiswa.php';
require_once __DIR__ . '/../classes/IzinKeluarPulang.php';
require_once __DIR__ . '/../classes/InformasiUmum.php'; // Tambahkan untuk informasi umum

// Pastikan orang tua sudah login
require_parent_login();

$siswa_id = get_current_parent_siswa_id();
$username = get_current_parent_username();
$nama_lengkap = get_current_parent_nama_lengkap();
$kelas_id = get_current_parent_kelas_id(); // Ini adalah ID kelas, bukan nama

$success_message = parent_flash('success'); // Ambil pesan sukses setelah login

$page_title = "Dashboard Orang Tua";

$pdo = null;
$siswa_model = null;
$kelas_model = null;
$hafalan_model = null;
$sorogan_model = null;
$ngaji_model = null;
$pelanggaran_model = null;
$izin_model = null;
$informasi_umum_model = null;

$nama_kelas_siswa = 'N/A';
$latest_hafalan = [];
$latest_sorogan = [];
$latest_ngaji = [];
$latest_pelanggaran = [];
$latest_izin = [];
$latest_informasi_umum = [];


try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $siswa_model = new Siswa($pdo);
    $kelas_model = new Kelas($pdo);
    $hafalan_model = new HafalanSiswa($pdo);
    $sorogan_model = new SoroganSiswa($pdo);
    $ngaji_model = new NgajiSiswa($pdo);
    $pelanggaran_model = new PelanggaranSiswa($pdo);
    $izin_model = new IzinKeluarPulang($pdo);
    $informasi_umum_model = new InformasiUmum($pdo);

    // Ambil nama kelas siswa
    if ($kelas_id) {
        $kelas_data = $kelas_model->getById($kelas_id);
        if ($kelas_data) {
            $nama_kelas_siswa = $kelas_data['nama_kelas'];
        }
    }

    // Ambil data hafalan terbaru (limit 1)
    $latest_hafalan_data = $hafalan_model->getAll(['siswa_id' => $siswa_id]);
    if (!empty($latest_hafalan_data)) {
        $latest_hafalan = $latest_hafalan_data[0];
    }

    // Ambil data sorogan terbaru (limit 1)
    $latest_sorogan_data = $sorogan_model->getAll(['siswa_id' => $siswa_id]);
    if (!empty($latest_sorogan_data)) {
        $latest_sorogan = $latest_sorogan_data[0];
    }

    // Ambil data ngaji terbaru (limit 1)
    $latest_ngaji_data = $ngaji_model->getAll(['siswa_id' => $siswa_id]);
    if (!empty($latest_ngaji_data)) {
        $latest_ngaji = $latest_ngaji_data[0];
    }

    // Ambil data pelanggaran terbaru (limit 1)
    $latest_pelanggaran_data = $pelanggaran_model->getAll(['siswa_id' => $siswa_id]);
    if (!empty($latest_pelanggaran_data)) {
        $latest_pelanggaran = $latest_pelanggaran_data[0];
    }

    // Ambil data izin terbaru (limit 1)
    $latest_izin_data = $izin_model->getAllParent($siswa_id); // Asumsi ada method getAllParent di IzinKeluarPulang
    if (!empty($latest_izin_data)) {
        $latest_izin = $latest_izin_data[0];
    }

    // Ambil informasi umum terbaru (limit 1, status aktif)
    $latest_informasi_umum_data = $informasi_umum_model->getAll(['status_publikasi' => 'Aktif']);
    if (!empty($latest_informasi_umum_data)) {
        // Urutkan berdasarkan tanggal publikasi terbaru
        usort($latest_informasi_umum_data, function($a, $b) {
            return strtotime($b['tanggal_publikasi']) - strtotime($a['tanggal_publikasi']);
        });
        $latest_informasi_umum = $latest_informasi_umum_data[0];
    }


} catch (Exception $e) {
    error_log("Dashboard Parent Error: " . $e->getMessage());
    // Handle error gracefully, misalnya tampilkan pesan ke user
}


require_once __DIR__ . '/../components/header_parent.php';
?>

        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Dashboard Orang Tua</h1>
            <div class="flex items-center space-x-4">
                <span class="text-gray-700">Selamat datang, <span class="font-semibold"><?php echo htmlspecialchars($nama_lengkap); ?></span>!</span>
                <img src="https://placehold.co/40x40/cbd5e1/475569?text=PT" alt="User Avatar" class="rounded-full border-2 border-indigo-400">
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Informasi Anak</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="bg-blue-50 p-4 rounded-lg flex items-center shadow-sm">
                    <i class="fas fa-id-badge text-blue-500 text-2xl mr-4"></i>
                    <div>
                        <p class="text-sm text-gray-600">Username:</p>
                        <p class="font-bold text-gray-800"><?php echo htmlspecialchars($username); ?></p>
                    </div>
                </div>
                <div class="bg-green-50 p-4 rounded-lg flex items-center shadow-sm">
                    <i class="fas fa-user-graduate text-green-500 text-2xl mr-4"></i>
                    <div>
                        <p class="text-sm text-gray-600">Nama Lengkap Anak:</p>
                        <p class="font-bold text-gray-800"><?php echo htmlspecialchars($nama_lengkap); ?></p>
                    </div>
                </div>
                <div class="bg-yellow-50 p-4 rounded-lg flex items-center shadow-sm">
                    <i class="fas fa-school text-yellow-500 text-2xl mr-4"></i>
                    <div>
                        <p class="text-sm text-gray-600">Kelas Anak:</p>
                        <p class="font-bold text-gray-800"><?php echo htmlspecialchars($nama_kelas_siswa); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
            <!-- Card Informasi Umum Terbaru -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-lg font-semibold text-gray-800">Informasi Umum Terbaru</h3>
                    <a href="<?php echo ROOT_URL; ?>public_parent/informasi_umum.php" class="text-blue-600 hover:underline text-sm">Lihat Semua</a>
                </div>
                <?php if (!empty($latest_informasi_umum)): ?>
                    <div class="mt-4 bg-gray-50 p-3 rounded-md border border-gray-200">
                        <p class="text-sm text-gray-700 font-semibold mb-1"><?php echo htmlspecialchars($latest_informasi_umum['judul']); ?></p>
                        <p class="text-xs text-gray-500 mb-2">Dipublikasikan: <?php echo htmlspecialchars(format_date_indonesian($latest_informasi_umum['tanggal_publikasi'])); ?></p>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars(substr($latest_informasi_umum['konten'], 0, 100)); ?><?php echo (strlen($latest_informasi_umum['konten']) > 100) ? '...' : ''; ?></p>
                    </div>
                <?php else: ?>
                    <div class="mt-4 bg-gray-50 p-3 rounded-md border border-gray-200">
                        <p class="text-sm text-gray-700"><i class="fas fa-info-circle mr-2 text-blue-500"></i> Tidak ada informasi umum terbaru.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Card Hafalan Terbaru -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-lg font-semibold text-gray-800">Hafalan Terbaru</h3>
                    <a href="<?php echo ROOT_URL; ?>public_parent/hafalan.php" class="text-blue-600 hover:underline text-sm">Lihat Semua</a>
                </div>
                <?php if (!empty($latest_hafalan)): ?>
                    <div class="mt-4 bg-gray-50 p-3 rounded-md border border-gray-200">
                        <p class="text-sm text-gray-700 font-semibold mb-1"><?php echo htmlspecialchars($latest_hafalan['nama_kitab']); ?></p>
                        <p class="text-xs text-gray-500 mb-2">Tanggal: <?php echo htmlspecialchars(format_date_indonesian($latest_hafalan['tanggal_setor'])); ?> - Nilai: <?php echo htmlspecialchars($latest_hafalan['nilai']); ?></p>
                        <p class="text-sm text-gray-600">Bagian: <?php echo htmlspecialchars($latest_hafalan['bagian_hafalan']); ?></p>
                        <p class="text-sm text-gray-600">Status: <span class="font-bold <?php
                            if ($latest_hafalan['status_hafalan'] === 'Mencapai Target') echo 'text-green-600';
                            elseif ($latest_hafalan['status_hafalan'] === 'Masih Kurang') echo 'text-yellow-600';
                            else echo 'text-red-600';
                        ?>"><?php echo htmlspecialchars($latest_hafalan['status_hafalan']); ?></span></p>
                    </div>
                <?php else: ?>
                    <div class="mt-4 bg-gray-50 p-3 rounded-md border border-gray-200">
                        <p class="text-sm text-gray-700"><i class="fas fa-clipboard-list mr-2 text-indigo-500"></i> Belum ada data hafalan terbaru.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Card Sorogan Terbaru -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-lg font-semibold text-gray-800">Sorogan Terbaru</h3>
                    <a href="<?php echo ROOT_URL; ?>public_parent/sorogan.php" class="text-blue-600 hover:underline text-sm">Lihat Semua</a>
                </div>
                <?php if (!empty($latest_sorogan)): ?>
                    <div class="mt-4 bg-gray-50 p-3 rounded-md border border-gray-200">
                        <p class="text-sm text-gray-700 font-semibold mb-1"><?php echo htmlspecialchars($latest_sorogan['nama_kitab']); ?></p>
                        <p class="text-xs text-gray-500 mb-2">Tanggal: <?php echo htmlspecialchars(format_date_indonesian($latest_sorogan['tanggal_setor'])); ?> - Nilai: <?php echo htmlspecialchars($latest_sorogan['nilai']); ?></p>
                        <p class="text-sm text-gray-600">Halaman: <?php echo htmlspecialchars($latest_sorogan['halaman_dari'] . ' - ' . $latest_sorogan['halaman_sampai']); ?></p>
                        <p class="text-sm text-gray-600">Status: <span class="font-bold <?php
                            if ($latest_sorogan['status_bacaan'] === 'Lulus') echo 'text-green-600';
                            elseif ($latest_sorogan['status_bacaan'] === 'Mengulang') echo 'text-yellow-600';
                            else echo 'text-red-600';
                        ?>"><?php echo htmlspecialchars($latest_sorogan['status_bacaan']); ?></span></p>
                    </div>
                <?php else: ?>
                    <div class="mt-4 bg-gray-50 p-3 rounded-md border border-gray-200">
                        <p class="text-sm text-gray-700"><i class="fas fa-book-reader mr-2 text-green-500"></i> Belum ada data sorogan terbaru.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Card Ngaji Terbaru -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-lg font-semibold text-gray-800">Ngaji Terbaru</h3>
                    <a href="<?php echo ROOT_URL; ?>public_parent/ngaji.php" class="text-blue-600 hover:underline text-sm">Lihat Semua</a>
                </div>
                <?php if (!empty($latest_ngaji)): ?>
                    <div class="mt-4 bg-gray-50 p-3 rounded-md border border-gray-200">
                        <p class="text-sm text-gray-700 font-semibold mb-1"><?php echo htmlspecialchars($latest_ngaji['jenis_materi']); ?></p>
                        <p class="text-xs text-gray-500 mb-2">Tanggal: <?php echo htmlspecialchars(format_date_indonesian($latest_ngaji['tanggal_setor'])); ?></p>
                        <p class="text-sm text-gray-600">Halaman: <?php echo htmlspecialchars($latest_ngaji['halaman_dari'] . ' - ' . $latest_ngaji['halaman_sampai']); ?></p>
                        <p class="text-sm text-gray-600">Catatan: <?php echo htmlspecialchars(substr($latest_ngaji['catatan_guru'], 0, 50)); ?><?php echo (strlen($latest_ngaji['catatan_guru']) > 50) ? '...' : ''; ?></p>
                    </div>
                <?php else: ?>
                    <div class="mt-4 bg-gray-50 p-3 rounded-md border border-gray-200">
                        <p class="text-sm text-gray-700"><i class="fas fa-quran mr-2 text-teal-500"></i> Belum ada data ngaji terbaru.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Card Pelanggaran Terbaru -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-lg font-semibold text-gray-800">Pelanggaran Terbaru</h3>
                    <a href="<?php echo ROOT_URL; ?>public_parent/pelanggaran.php" class="text-blue-600 hover:underline text-sm">Lihat Semua</a>
                </div>
                <?php if (!empty($latest_pelanggaran)): ?>
                    <div class="mt-4 bg-gray-50 p-3 rounded-md border border-gray-200">
                        <p class="text-sm text-gray-700 font-semibold mb-1"><?php echo htmlspecialchars($latest_pelanggaran['nama_pelanggaran']); ?></p>
                        <p class="text-xs text-gray-500 mb-2">Tanggal: <?php echo htmlspecialchars(format_date_indonesian($latest_pelanggaran['tanggal_pelanggaran'])); ?> - Poin: <?php echo htmlspecialchars($latest_pelanggaran['poin_deduksi_aktual']); ?></p>
                        <p class="text-sm text-gray-600">Deskripsi: <?php echo htmlspecialchars(substr($latest_pelanggaran['deskripsi_kejadian'], 0, 50)); ?><?php echo (strlen($latest_pelanggaran['deskripsi_kejadian']) > 50) ? '...' : ''; ?></p>
                        <p class="text-sm text-gray-600">Status Tindak Lanjut: <span class="font-bold <?php
                            if ($latest_pelanggaran['status_tindak_lanjut'] === 'Selesai') echo 'text-green-600';
                            elseif ($latest_pelanggaran['status_tindak_lanjut'] === 'Peringatan Tertulis' || $latest_pelanggaran['status_tindak_lanjut'] === 'Skorsing') echo 'text-red-600';
                            else echo 'text-yellow-600';
                        ?>"><?php echo htmlspecialchars($latest_pelanggaran['status_tindak_lanjut']); ?></span></p>
                    </div>
                <?php else: ?>
                    <div class="mt-4 bg-gray-50 p-3 rounded-md border border-gray-200">
                        <p class="text-sm text-gray-700"><i class="fas fa-gavel mr-2 text-red-500"></i> Tidak ada pelanggaran terbaru.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Card Izin Terbaru -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-lg font-semibold text-gray-800">Izin Terbaru</h3>
                    <a href="<?php echo ROOT_URL; ?>public_parent/izin.php" class="text-blue-600 hover:underline text-sm">Lihat Semua</a>
                </div>
                <?php if (!empty($latest_izin)): ?>
                    <div class="mt-4 bg-gray-50 p-3 rounded-md border border-gray-200">
                        <p class="text-sm text-gray-700 font-semibold mb-1">Keperluan: <?php echo htmlspecialchars($latest_izin['keperluan']); ?></p>
                        <p class="text-xs text-gray-500 mb-2">Tanggal: <?php echo htmlspecialchars(format_date_indonesian($latest_izin['tanggal_izin'])); ?> - Keluar: <?php echo htmlspecialchars(substr($latest_izin['waktu_keluar'], 0, 5)); ?></p>
                        <p class="text-sm text-gray-600">Status: <span class="font-bold <?php
                            if ($latest_izin['status_izin'] === 'Disetujui') echo 'text-green-600';
                            elseif ($latest_izin['status_izin'] === 'Ditolak') echo 'text-red-600';
                            else echo 'text-yellow-600';
                        ?>"><?php echo htmlspecialchars($latest_izin['status_izin']); ?></span></p>
                        <?php if ($latest_izin['waktu_kembali']): ?>
                            <p class="text-sm text-gray-600">Kembali: <?php echo htmlspecialchars(substr($latest_izin['waktu_kembali'], 0, 5)); ?></p>
                        <?php else: ?>
                            <p class="text-sm text-gray-600">Belum Kembali</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="mt-4 bg-gray-50 p-3 rounded-md border border-gray-200">
                        <p class="text-sm text-gray-700"><i class="fas fa-clock mr-2 text-purple-500"></i> Belum ada izin terbaru.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

<?php
require_once __DIR__ . '/../components/footer_parent.php';
?>
