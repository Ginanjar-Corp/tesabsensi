<?php
// project-root/staff/manage_absensi_mapel.php

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/JadwalPelajaran.php';
require_once __DIR__ . '/../classes/Kelas.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/AbsensiMapelSiswa.php';
require_once __DIR__ . '/../classes/TahunAjaran.php';

require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$jadwal_model = null;
$kelas_model = null;
$siswa_model = null;
$absensi_mapel_siswa_model = null;
$tahun_ajaran_model = null;
$pdo = null;

$all_kelas = [];
$jadwal_hari_ini = [];
$selected_kelas = null;
$list_siswa_kelas = [];
$current_attendance = [];
$tanggal_absen = date('Y-m-d');

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $jadwal_model = new JadwalPelajaran($pdo);
    $kelas_model = new Kelas($pdo);
    $siswa_model = new Siswa($pdo);
    $absensi_mapel_siswa_model = new AbsensiMapelSiswa($pdo);
    $tahun_ajaran_model = new TahunAjaran($pdo);

    $tahun_ajaran_aktif = $tahun_ajaran_model->getActiveTahunAjaran();
    if (!$tahun_ajaran_aktif) {
        throw new Exception("Tahun ajaran aktif tidak ditemukan.");
    }
    
    // PERBAIKAN DI BARIS INI
    // Mengubah indeks dari 'nama_tahun_ajaran' menjadi 'tahun_ajaran'
    $jadwal_hari_ini = $jadwal_model->getJadwalHariIni($tanggal_absen, $tahun_ajaran_aktif['tahun_ajaran'], APP_SEMESTER_AKTIF);

} catch (Exception $e) {
    $error_message = "Terjadi kesalahan: " . $e->getMessage();
}

require_once __DIR__ . '/../components/header_staff.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-white shadow-md rounded-lg p-6">
        <h2 class="text-2xl font-bold mb-4">Manajemen Absensi Mata Pelajaran</h2>
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

        <div class="mb-6">
            <h3 class="text-xl font-semibold mb-2">Pilih Jadwal Pelajaran</h3>
            <p class="text-gray-600 mb-4">Pilih mata pelajaran yang jadwalnya aktif hari ini untuk mencatat atau mengubah absensi.</p>
            
            <?php if (empty($jadwal_hari_ini)): ?>
                <p class="text-gray-500">Tidak ada jadwal pelajaran yang aktif hari ini.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full leading-normal">
                        <thead>
                            <tr>
                                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Mata Pelajaran
                                </th>
                                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Kelas
                                </th>
                                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Guru
                                </th>
                                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jadwal_hari_ini as $jadwal): ?>
                                <tr>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?php echo htmlspecialchars($jadwal['nama_mapel']); ?></td>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?php echo htmlspecialchars($jadwal['nama_kelas']); ?></td>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?php echo htmlspecialchars($jadwal['nama_guru']); ?></td>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        <a href="<?php echo ROOT_URL; ?>staff/catat_absensi_mapel.php?jadwal_id=<?php echo htmlspecialchars($jadwal['id']); ?>" class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded text-xs">Catat Absensi</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>