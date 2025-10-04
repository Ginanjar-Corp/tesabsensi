<?php
// project-root/admin/absensi_mapel_admin.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/JadwalPelajaran.php';

// Pastikan pengguna sudah login dan memiliki peran Admin atau Tata Usaha
require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$jadwal_model = null;
$pdo = null;
$jadwal_hari_ini = [];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $jadwal_model = new JadwalPelajaran($pdo);

    // Dapatkan tahun ajaran dan semester aktif
    $tahun_ajaran_aktif = getActiveTahunAjaranValue(); // Menggunakan fungsi baru yang diperbaiki
    $semester_aktif = defined('APP_SEMESTER_AKTIF') ? APP_SEMESTER_AKTIF : null;

    if (!$tahun_ajaran_aktif) {
        $error_message = "Tidak ada tahun ajaran aktif yang ditemukan.";
    } else if (!$semester_aktif) {
        $error_message = "Konstanta semester aktif tidak didefinisikan.";
    } else {
        $tanggal_sekarang = date('Y-m-d');
        $jadwal_hari_ini = $jadwal_model->getJadwalHariIni($tanggal_sekarang, $tahun_ajaran_aktif, $semester_aktif);
    }

} catch (Exception $e) {
    $error_message = "Terjadi kesalahan: " . $e->getMessage();
}

// Sertakan header
require_once __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-white shadow-md rounded-lg p-6">
        <h2 class="text-3xl font-bold mb-6 text-gray-800">Jadwal Absensi Mata Pelajaran (Admin)</h2>

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

        <?php if (empty($jadwal_hari_ini)): ?>
            <div class="text-center text-gray-500">
                <p>Tidak ada jadwal pelajaran hari ini.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full leading-normal">
                    <thead>
                        <tr>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Waktu
                            </th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Kelas
                            </th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Mata Pelajaran
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
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 px-6 border-b border-gray-200 text-sm">
                                    <?php echo htmlspecialchars($jadwal['jam_mulai'] . ' - ' . $jadwal['jam_selesai']); ?>
                                </td>
                                <td class="py-3 px-6 border-b border-gray-200 text-sm">
                                    <?php echo htmlspecialchars($jadwal['nama_kelas']); ?>
                                </td>
                                <td class="py-3 px-6 border-b border-gray-200 text-sm">
                                    <?php echo htmlspecialchars($jadwal['nama_mapel']); ?>
                                </td>
                                <td class="py-3 px-6 border-b border-gray-200 text-sm">
                                    <?php echo htmlspecialchars($jadwal['nama_guru']); ?>
                                </td>
                                <td class="py-3 px-6 border-b border-gray-200 text-sm whitespace-nowrap">
                                    <a href="<?php echo ROOT_URL; ?>admin/catat_absensi_admin.php?jadwal_id=<?php echo htmlspecialchars($jadwal['id']); ?>" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-3 rounded text-xs">
                                        Catat Absensi
                                    </a>
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