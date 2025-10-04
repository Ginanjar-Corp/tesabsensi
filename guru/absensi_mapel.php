<?php
// project-root/guru/absensi_mapel.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php'; // Pastikan ini di-include untuk konstanta semester
require_once __DIR__ . '/../includes/helpers.php'; // Pastikan ini di-include untuk getActiveTahunAjaran()
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/JadwalPelajaran.php';
require_once __DIR__ . '/../classes/Guru.php';

// Pastikan pengguna sudah login dan memiliki peran Guru
require_login();
require_role('Guru');

$error_message = flash('error');
$success_message = flash('success');

$jadwal_model = null;
$guru_model = null;
$pdo = null;
$guru_schedule = [];
$guru_data = null;

// Variabel untuk tahun ajaran dan semester aktif
$tahun_ajaran_aktif = null; // Akan diambil dari database melalui helper
$semester_aktif = defined('APP_SEMESTER_AKTIF') ? APP_SEMESTER_AKTIF : null; // Diambil dari konstanta

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $jadwal_model = new JadwalPelajaran($pdo);
    $guru_model = new Guru($pdo);

    $user_id = $_SESSION['user_id'];
    $related_id = $_SESSION['related_id']; // Ini adalah guru_id dari tabel 'guru'

    if (!$related_id) {
        set_flash('error', 'Data guru tidak ditemukan untuk akun Anda. Silakan hubungi administrator.');
        redirect(ROOT_URL . 'logout.php');
    }

    $guru_data = $guru_model->findById($related_id);
    if (!$guru_data) {
        set_flash('error', 'Data guru tidak ditemukan. Silakan hubungi administrator.');
        redirect(ROOT_URL . 'logout.php');
    }

    // --- VALIDASI PENTING: Cek apakah guru sudah absen masuk hari ini ---
    // Fungsi isGuruCheckedInToday() harus ada di helpers.php
    if (!function_exists('isGuruCheckedInToday')) {
        error_log("FATAL ERROR: Fungsi isGuruCheckedInToday() tidak terdefinisi di absensi_mapel.php.");
        set_flash('error', 'Terjadi kesalahan sistem: Fungsi penting (isGuruCheckedInToday) tidak ditemukan. Mohon hubungi administrator.');
        redirect(ROOT_URL . 'guru/dashboard.php');
        exit();
    }

    $is_guru_checked_in = isGuruCheckedInToday($pdo, $related_id);
    if (!$is_guru_checked_in) {
        set_flash('error', 'Anda harus absen masuk terlebih dahulu sebelum dapat melihat dan mencatat absensi mata pelajaran.');
        redirect(ROOT_URL . 'guru/dashboard.php');
        exit();
    }
    // --- Akhir Validasi ---

    // Ambil tahun ajaran aktif dari database menggunakan fungsi helper
    // Menggunakan 'tahun_ajaran' sebagai kunci array, sesuai skema tabel
    $active_tahun_ajaran_data = getActiveTahunAjaran();
    if ($active_tahun_ajaran_data && isset($active_tahun_ajaran_data['tahun_ajaran'])) {
        $tahun_ajaran_aktif = $active_tahun_ajaran_data['tahun_ajaran'];
    } else {
        error_log("DEBUG absensi_mapel: Tahun Ajaran aktif tidak ditemukan dari database.");
    }


    error_log("DEBUG absensi_mapel: Tahun Ajaran Aktif (dari DB): " . ($tahun_ajaran_aktif ?? 'N/A'));
    error_log("DEBUG absensi_mapel: Semester Aktif (dari Konstanta): " . ($semester_aktif ?? 'N/A'));

    if (!$tahun_ajaran_aktif || !$semester_aktif) {
        set_flash('info', 'Tahun Ajaran aktif belum disetel di database atau Semester aktif belum disetel di konfigurasi aplikasi. Mohon hubungi administrator.');
        error_log("DEBUG absensi_mapel: Tahun Ajaran atau Semester aktif tidak ditemukan.");
        // Anda bisa memilih untuk menampilkan halaman kosong atau redirect
    }

    $current_day = date('N'); // 1 (Senin) sampai 7 (Minggu)
    $hari_indonesia = '';
    switch ($current_day) {
        case 1: $hari_indonesia = 'Senin'; break;
        case 2: $hari_indonesia = 'Selasa'; break;
        case 3: $hari_indonesia = 'Rabu'; break;
        case 4: $hari_indonesia = 'Kamis'; break;
        case 5: $hari_indonesia = 'Jumat'; break;
        case 6: $hari_indonesia = 'Sabtu'; break;
        case 7: $hari_indonesia = 'Minggu'; break;
    }

    // Pastikan $tahun_ajaran_aktif dan $semester_aktif tidak null sebelum memanggil
    if ($tahun_ajaran_aktif && $semester_aktif) {
        $guru_schedule = $jadwal_model->getScheduleForTeacherToday($related_id, $hari_indonesia, $tahun_ajaran_aktif, $semester_aktif);
        error_log("DEBUG absensi_mapel: Memuat jadwal untuk Guru ID: {$related_id}, Hari: {$hari_indonesia}, TA: {$tahun_ajaran_aktif}, Semester: {$semester_aktif}");
    } else {
        $guru_schedule = []; // Tidak ada jadwal jika tahun ajaran/semester aktif tidak ditemukan
        error_log("DEBUG absensi_mapel: Tidak memuat jadwal karena Tahun Ajaran atau Semester aktif tidak valid.");
    }

} catch (PDOException $e) {
    error_log("Guru Absensi Mapel Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Guru Absensi Mapel General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
}

// Sertakan header guru
require_once __DIR__ . '/../components/header_guru.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Jadwal Mengajar Hari Ini</h1>
    <p class="text-gray-700 mb-4">Selamat datang, <span class="font-semibold"><?php echo htmlspecialchars($guru_data['nama_lengkap'] ?? 'Guru'); ?></span>!</p>
    <p class="text-gray-700 mb-6">Hari ini: <span class="font-semibold"><?php echo htmlspecialchars($hari_indonesia); ?>, <?php echo htmlspecialchars(format_date_indonesian(date('Y-m-d'))); ?></span></p>
    <p class="text-gray-700 mb-6">Tahun Ajaran: <span class="font-semibold"><?php echo htmlspecialchars($tahun_ajaran_aktif ?? 'N/A'); ?></span> | Semester: <span class="font-semibold"><?php echo htmlspecialchars($semester_aktif ?? 'N/A'); ?></span></p>

    <?php if ($error_message): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
        </div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold mb-4">Jadwal Anda Hari Ini</h2>
        <?php if (empty($guru_schedule)): ?>
            <p class="text-gray-600">Tidak ada jadwal mengajar untuk Anda hari ini pada tahun ajaran dan semester aktif.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                    <thead>
                        <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">No.</th>
                            <th class="py-3 px-6 text-left">Waktu</th>
                            <th class="py-3 px-6 text-left">Kelas</th>
                            <th class="py-3 px-6 text-left">Mata Pelajaran</th>
                            <th class="py-3 px-6 text-left">Ruangan</th>
                            <th class="py-3 px-6 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm">
                        <?php $no = 1; ?>
                        <?php foreach ($guru_schedule as $jadwal): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                <td class="py-3 px-6"><?php echo substr(htmlspecialchars($jadwal['jam_mulai']), 0, 5) . ' - ' . substr(htmlspecialchars($jadwal['jam_selesai']), 0, 5); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($jadwal['nama_kelas']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($jadwal['nama_mapel']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($jadwal['ruangan'] ?? '-'); ?></td>
                                <td class="py-3 px-6 text-center whitespace-nowrap">
                                    <?php if ($is_guru_checked_in): ?>
                                        <a href="<?php echo ROOT_URL; ?>guru/catat_absensi_mapel.php?jadwal_id=<?php echo htmlspecialchars($jadwal['id']); ?>" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-3 rounded text-xs">
                                            Catat Absensi
                                        </a>
                                    <?php else: ?>
                                        <button disabled class="bg-gray-300 text-gray-600 font-bold py-1 px-3 rounded text-xs cursor-not-allowed opacity-70" title="Anda harus absen masuk terlebih dahulu">
                                            Catat Absensi
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Sertakan footer
require_once __DIR__ . '/../components/footer.php';
?>
