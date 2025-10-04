<?php
// project-root/staff/cek_absensi_guru.php

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

// Panggil metode statis yang mengembalikan objek PDO secara langsung.
// Metode ini biasanya bernama connect() atau getInstance().
// Silakan sesuaikan dengan metode yang ada di file Database.php Anda.
$pdo = Database::getInstance()->getConnection();

$jadwal_model = new JadwalPelajaran($pdo);
$absensi_guru_model = new AbsensiMapelGuru($pdo);
$settings_model = new SchoolSettings($pdo);
$tahun_ajaran_model = new TahunAjaran($pdo);

$hari_ini = date('Y-m-d');
$nama_hari_ini = getDayName(date('N'));

// Ambil data tahun ajaran dan semester aktif dari tabel yang benar
$tahun_ajaran_aktif_data = $tahun_ajaran_model->getActiveTahunAjaran();
$tahun_ajaran_aktif = $tahun_ajaran_aktif_data ? $tahun_ajaran_aktif_data['tahun_ajaran'] : null;

$school_settings = $settings_model->getSettings();
$semester_aktif = $school_settings ? $school_settings['semester_aktif'] : null;

// Cek apakah data yang dibutuhkan ada
if (!$tahun_ajaran_aktif || !$semester_aktif) {
    // Tampilkan pesan error jika data tidak ditemukan
    $error_message = "Data Tahun Ajaran atau Semester aktif tidak ditemukan. Silakan periksa pengaturan sekolah.";
    $jadwal_hari_ini = [];
} else {
    // Ambil semua jadwal pelajaran untuk hari ini
    $jadwal_hari_ini = $jadwal_model->getScheduleByDay($nama_hari_ini, $tahun_ajaran_aktif, $semester_aktif);
}

// Gabungkan data jadwal dengan data absensi guru
$data_absensi = [];
foreach ($jadwal_hari_ini as $jadwal) {
    // Cek status absensi guru untuk jadwal ini
    $status_absensi_guru = $absensi_guru_model->getAbsensiByJadwalAndDate($jadwal['id'], $hari_ini);

    $status_teks = 'Belum Melakukan Absensi';
    $status_warna = 'text-red-500'; // Warna merah untuk 'Belum'

    if ($status_absensi_guru) {
        if (!empty($status_absensi_guru['waktu_selesai_ajar'])) {
            $status_teks = 'Selesai Mengajar';
            $status_warna = 'text-green-500'; // Warna hijau untuk 'Selesai'
        } else {
            $status_teks = 'Absensi Dalam Proses';
            $status_warna = 'text-yellow-500'; // Warna kuning untuk 'Dalam Proses'
        }
    }

    $data_absensi[] = [
        'nama_mapel' => $jadwal['nama_mapel'],
        'nama_kelas' => $jadwal['nama_kelas'],
        'nama_guru' => $jadwal['nama_guru'],
        'jam_mulai' => substr($jadwal['jam_mulai'], 0, 5),
        'jam_selesai' => substr($jadwal['jam_selesai'], 0, 5),
        'status' => $status_teks,
        'warna' => $status_warna,
    ];
}

?>

<?php require_once __DIR__ . '/../components/header_staff.php'; ?>

<div class="min-h-screen bg-gray-100 p-8">
    <div class="max-w-7xl mx-auto bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold mb-4">Daftar Hadir Mengajar Guru Hari Ini</h2>
        <p class="mb-4">Tanggal: **<?php echo date('d-m-Y'); ?>**</p>

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
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>