<?php
// project-root/admin/cek_absensi_guru.php

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/JadwalPelajaran.php';
require_once __DIR__ . '/../classes/AbsensiMapelGuru.php';
require_once __DIR__ . '/../classes/SchoolSettings.php';
require_once __DIR__ . '/../classes/TahunAjaran.php';

// Pastikan hanya user dengan role Admin atau TataUsaha yang bisa mengakses
require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

// Ambil tanggal dari parameter GET, jika ada. Jika tidak, gunakan tanggal hari ini.
$hari_ini = isset($_GET['tanggal']) && !empty($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
$nama_hari_ini = getDayName(date('N', strtotime($hari_ini)));

// Panggil metode statis yang mengembalikan objek PDO secara langsung.
$pdo = Database::getInstance()->getConnection();

$jadwal_model = new JadwalPelajaran($pdo);
$absensi_guru_model = new AbsensiMapelGuru($pdo);
$settings_model = new SchoolSettings($pdo);
$tahun_ajaran_model = new TahunAjaran($pdo);

// Ambil data tahun ajaran dan semester aktif
$tahun_ajaran_aktif_data = $tahun_ajaran_model->getActiveTahunAjaran();
$tahun_ajaran_aktif = $tahun_ajaran_aktif_data ? $tahun_ajaran_aktif_data['tahun_ajaran'] : null;

$school_settings = $settings_model->getSettings();
$semester_aktif = $school_settings ? $school_settings['semester_aktif'] : null;

// Cek apakah data yang dibutuhkan ada
if (!$tahun_ajaran_aktif || !$semester_aktif) {
    $error_message = "Data Tahun Ajaran atau Semester aktif tidak ditemukan. Silakan periksa pengaturan sekolah.";
    $jadwal_hari_ini = [];
} else {
    $jadwal_hari_ini = $jadwal_model->getScheduleByDay($nama_hari_ini, $tahun_ajaran_aktif, $semester_aktif);
}

// Gabungkan data jadwal dengan data absensi guru
$data_absensi = [];
foreach ($jadwal_hari_ini as $jadwal) {
    $status_absensi_guru = $absensi_guru_model->getAbsensiByJadwalAndDate($jadwal['id'], $hari_ini);

    $status_teks = 'Belum Melakukan Absensi';
    $status_warna = 'text-red-500';
    $show_detail_button = false;

    if ($status_absensi_guru) {
        $show_detail_button = true;
        if (!empty($status_absensi_guru['waktu_selesai_ajar'])) {
            $status_teks = 'Selesai Mengajar';
            $status_warna = 'text-green-500';
        } else {
            $status_teks = 'Absensi Dalam Proses';
            $status_warna = 'text-yellow-500';
        }
    }

    $data_absensi[] = [
        'jadwal_id' => $jadwal['id'],
        'nama_mapel' => $jadwal['nama_mapel'],
        'nama_kelas' => $jadwal['nama_kelas'] ?? 'Jadwal Siswa',
        'nama_guru' => $jadwal['nama_guru'],
        'jam_mulai' => substr($jadwal['jam_mulai'], 0, 5),
        'jam_selesai' => substr($jadwal['jam_selesai'], 0, 5),
        'status' => $status_teks,
        'warna' => $status_warna,
        'show_detail' => $show_detail_button,
    ];
}

?>

<?php require_once __DIR__ . '/../components/header_admin.php'; ?>

<div class="min-h-screen bg-gray-100 p-8">
    <div class="max-w-7xl mx-auto bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold mb-4">Daftar Hadir Mengajar Guru</h2>
        
        <form method="GET" action="" class="mb-4 flex items-center space-x-4">
            <label for="tanggal" class="font-semibold">Pilih Tanggal:</label>
            <input type="date" id="tanggal" name="tanggal" value="<?php echo htmlspecialchars($hari_ini); ?>" class="form-input rounded-md shadow-sm">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Tampilkan</button>
        </form>

        <p class="mb-4">Menampilkan data untuk tanggal: **<?php echo htmlspecialchars(format_tanggal_indo($hari_ini)); ?>**</p>

        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $error_message; ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $success_message; ?></span>
            </div>
        <?php endif; ?>

        <?php if (empty($data_absensi) && empty($error_message)): ?>
            <p class="text-center text-gray-500">Tidak ada jadwal pelajaran untuk hari ini.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full leading-normal">
                    <thead>
                        <tr>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                No
                            </th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Mata Pelajaran
                            </th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Kelas
                            </th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Guru Pengampu
                            </th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Jam Mengajar
                            </th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Status Absensi Guru
                            </th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; ?>
                        <?php foreach ($data_absensi as $data): ?>
                            <tr>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <p><?php echo $i++; ?></p>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <?php echo htmlspecialchars($data['nama_mapel']); ?>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <?php echo htmlspecialchars($data['nama_kelas']); ?>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <?php echo htmlspecialchars($data['nama_guru']); ?>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <?php echo htmlspecialchars($data['jam_mulai']) . ' - ' . htmlspecialchars($data['jam_selesai']); ?>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <span class="font-bold <?php echo $data['warna']; ?>">
                                        <?php echo htmlspecialchars($data['status']); ?>
                                    </span>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <?php if ($data['show_detail']): ?>
                                        <a href="detail_absensi_siswa.php?jadwal_id=<?php echo htmlspecialchars($data['jadwal_id']); ?>&tanggal=<?php echo htmlspecialchars($hari_ini); ?>" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-xs">Detail</a>
                                    <?php else: ?>
                                        <a href="audit_absensi_mapel.php?jadwal_id=<?php echo htmlspecialchars($data['jadwal_id']); ?>&tanggal=<?php echo htmlspecialchars($hari_ini); ?>" class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded text-xs">Catat Absensi</a>
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

<?php require_once __DIR__ . '/../components/footer.php'; ?>