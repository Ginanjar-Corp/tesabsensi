<?php
// project-root/admin/detail_absensi_siswa.php

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/JadwalPelajaran.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/AbsensiMapelSiswa.php';

// Pastikan hanya user dengan role Admin atau TataUsaha yang bisa mengakses
require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$jadwal_id = isset($_GET['jadwal_id']) ? $_GET['jadwal_id'] : null;
$tanggal_ajar = isset($_GET['tanggal']) ? $_GET['tanggal'] : null;

// Validasi input dari URL
if (!$jadwal_id || !$tanggal_ajar) {
    $error_message = "Parameter jadwal_id atau tanggal tidak ditemukan.";
    $jadwal_data = null;
    $siswa_absensi = [];
} else {
    try {
        // Inisialisasi koneksi dan model
        $pdo = Database::getInstance()->getConnection();
        $jadwal_model = new JadwalPelajaran($pdo);
        $siswa_model = new Siswa($pdo);
        $absensi_model = new AbsensiMapelSiswa($pdo);

        // Ambil detail jadwal pelajaran
        $jadwal_data = $jadwal_model->findByIdJadwal($jadwal_id);

        if (!$jadwal_data) {
            $error_message = "Jadwal pelajaran tidak ditemukan.";
            $siswa_absensi = [];
        } else {
            // Logika untuk mengambil daftar siswa berdasarkan tipe jadwal
            if ($jadwal_data['tipe_jadwal'] === 'Siswa') {
                $list_siswa_kelas = $jadwal_model->getStudentsByJadwalId($jadwal_id);
            } else {
                $list_siswa_kelas = $siswa_model->getByKelasId($jadwal_data['kelas_id']);
            }
            
            // Ambil data absensi siswa untuk jadwal dan tanggal ini
            $absensi_siswa = $absensi_model->getAttendanceForSchedule($jadwal_id, $tanggal_ajar);

            $siswa_absensi = [];
            foreach ($list_siswa_kelas as $siswa) {
                // Cari status absensi siswa di array yang sudah diambil
                $status = 'Belum Absen';
                $catatan = '-';
                $found = false;
                foreach ($absensi_siswa as $absen) {
                    if ($absen['siswa_id'] == $siswa['id']) {
                        $status = getStatusKehadiranText($absen['status_kehadiran']);
                        $catatan = !empty($absen['catatan']) ? $absen['catatan'] : '-';
                        $found = true;
                        break;
                    }
                }
                
                $siswa_absensi[] = [
                    'nama_siswa' => $siswa['nama_lengkap'],
                    'status_kehadiran' => $status,
                    'catatan' => $catatan
                ];
            }
        }

    } catch (Exception $e) {
        $error_message = "Terjadi kesalahan: " . $e->getMessage();
        $jadwal_data = null;
        $siswa_absensi = [];
    }
}

function getStatusKehadiranText($status_code) {
    switch ($status_code) {
        case 'H': return 'Hadir';
        case 'I': return 'Izin';
        case 'S': return 'Sakit';
        case 'A': return 'Alpa';
        default: return 'Tidak Diketahui';
    }
}

?>

<?php require_once __DIR__ . '/../components/header_admin.php'; ?>

<div class="min-h-screen bg-gray-100 p-8">
    <div class="max-w-7xl mx-auto bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold mb-4">Detail Absensi Siswa</h2>
        
        <a href="cek_absensi_guru.php" class="inline-block bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded mb-4">
            &larr; Kembali
        </a>

        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $error_message; ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($jadwal_data)): ?>
            <div class="bg-gray-50 p-4 rounded-lg mb-6">
                <p><strong>Mata Pelajaran:</strong> <?php echo htmlspecialchars($jadwal_data['nama_mapel']); ?></p>
                <p><strong>Kelas:</strong> <?php echo htmlspecialchars($jadwal_data['nama_kelas'] ?? 'Jadwal Siswa'); ?></p>
                <p><strong>Guru Pengampu:</strong> <?php echo htmlspecialchars($jadwal_data['nama_guru']); ?></p>
                <p><strong>Tanggal:</strong> <?php echo htmlspecialchars(format_tanggal_indo($tanggal_ajar)); ?></p>
            </div>

            <?php if (empty($siswa_absensi)): ?>
                <p class="text-center text-gray-500">Tidak ada data absensi yang tersedia untuk jadwal ini.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full leading-normal">
                        <thead>
                            <tr>
                                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    No
                                </th>
                                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Nama Siswa
                                </th>
                                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Status Kehadiran
                                </th>
                                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Catatan
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; ?>
                            <?php foreach ($siswa_absensi as $siswa): ?>
                                <tr>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        <p><?php echo $i++; ?></p>
                                    </td>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        <?php echo htmlspecialchars($siswa['nama_siswa']); ?>
                                    </td>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        <span class="font-bold">
                                            <?php echo htmlspecialchars($siswa['status_kehadiran']); ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        <?php echo htmlspecialchars($siswa['catatan']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>