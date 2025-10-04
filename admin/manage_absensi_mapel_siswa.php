<?php
// project-root/admin/manage_absensi_mapel_siswa.php

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/JadwalPelajaran.php';
require_once __DIR__ . '/../classes/AbsensiMapelSiswa.php';
require_once __DIR__ . '/../classes/TahunAjaran.php';

require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$siswa_model = null;
$jadwal_model = null;
$absensi_mapel_siswa_model = null;
$tahun_ajaran_model = null;
$pdo = null;

$siswa_id = (int)($_GET['siswa_id'] ?? 0);
$siswa_data = null;
$list_jadwal_siswa = [];
$absensi_siswa = [];
$tanggal_absen = date('Y-m-d');

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $siswa_model = new Siswa($pdo);
    $jadwal_model = new JadwalPelajaran($pdo);
    $absensi_mapel_siswa_model = new AbsensiMapelSiswa($pdo);
    $tahun_ajaran_model = new TahunAjaran($pdo);
    
    $siswa_data = $siswa_model->getById($siswa_id);
    if (!$siswa_data) {
        throw new Exception("Data siswa tidak ditemukan.");
    }

    $tahun_ajaran_aktif = $tahun_ajaran_model->getActiveTahunAjaran();
    if (!$tahun_ajaran_aktif) {
        throw new Exception("Tahun ajaran aktif tidak ditemukan.");
    }
    
    // Dapatkan semua jadwal siswa di tahun ajaran dan semester aktif
    $list_jadwal_siswa = $jadwal_model->getJadwalSiswa($siswa_data['kelas_id'], $tahun_ajaran_aktif['tahun_ajaran'], APP_SEMESTER_AKTIF);

    // Dapatkan absensi siswa untuk semua jadwal hari ini
    $absensi_siswa = $absensi_mapel_siswa_model->getBySiswaIdAndTanggal($siswa_id, $tanggal_absen);

} catch (Exception $e) {
    $error_message = "Terjadi kesalahan: " . $e->getMessage();
}

// Proses form saat data dikirim (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$siswa_data) {
            throw new Exception("Data siswa tidak ditemukan.");
        }
        
        $tanggal_absen = date('Y-m-d');
        $waktu_absen = date('H:i:s');
        $operator_id = $_SESSION['user_data']['id'] ?? null;
        if (!$operator_id) {
            throw new Exception("ID operator tidak ditemukan.");
        }

        $jadwal_id = (int)($_POST['jadwal_id'] ?? 0);
        $status_kehadiran = $_POST['status_kehadiran'] ?? 'A';
        $catatan = trim($_POST['catatan'] ?? null);

        // Ambil guru pengampu dari jadwal
        $selected_jadwal = $jadwal_model->getById($jadwal_id);
        if (!$selected_jadwal) {
             throw new Exception("Jadwal tidak ditemukan.");
        }
        $guru_id_jadwal = $selected_jadwal['guru_id'];
        
        if ($absensi_mapel_siswa_model->recordAttendance($siswa_id, $jadwal_id, $guru_id_jadwal, $tanggal_absen, $waktu_absen, $status_kehadiran, $catatan)) {
            flash('success', "Absensi untuk mata pelajaran berhasil diperbarui.");
        } else {
            flash('error', "Gagal memperbarui absensi.");
        }

        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;

    } catch (Exception $e) {
        $error_message = "Terjadi kesalahan saat menyimpan absensi: " . $e->getMessage();
    }
}

require_once __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-white shadow-md rounded-lg p-6">
        <h2 class="text-2xl font-bold mb-4">Manajemen Absensi Mata Pelajaran</h2>
        <?php if ($siswa_data): ?>
            <div class="bg-gray-100 p-4 rounded-lg mb-4">
                <p><strong>Nama Siswa:</strong> <?php echo htmlspecialchars($siswa_data['nama_lengkap']); ?></p>
                <p><strong>Kelas:</strong> <?php echo htmlspecialchars($siswa_data['nama_kelas']); ?></p>
            </div>
            <a href="manage_absensi_mapel.php" class="inline-block bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded mb-4 text-sm">
                &larr; Kembali ke Daftar Kelas
            </a>
        <?php endif; ?>

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
        
        <h3 class="text-xl font-semibold mb-4">Jadwal & Absensi Hari Ini (<?php echo format_tanggal_indo($tanggal_absen); ?>)</h3>
        
        <?php if (empty($list_jadwal_siswa)): ?>
            <div class="text-center text-gray-500">
                <p>Tidak ada jadwal pelajaran untuk siswa ini hari ini.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full leading-normal">
                    <thead>
                        <tr>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Mata Pelajaran
                            </th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Guru
                            </th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Waktu
                            </th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Status Absensi
                            </th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list_jadwal_siswa as $jadwal): ?>
                            <?php 
                                // Cek apakah ada data absensi untuk jadwal ini
                                $status_absensi = $absensi_siswa[$jadwal['id']]['status_kehadiran'] ?? 'H';
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?php echo htmlspecialchars($jadwal['nama_mapel']); ?></td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?php echo htmlspecialchars($jadwal['nama_guru']); ?></td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?php echo htmlspecialchars($jadwal['waktu_mulai'] . ' - ' . $jadwal['waktu_selesai']); ?></td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <form action="" method="post" class="flex items-center">
                                        <input type="hidden" name="jadwal_id" value="<?php echo htmlspecialchars($jadwal['id']); ?>">
                                        <select name="status_kehadiran" class="form-select text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                            <option value="H" <?php echo $status_absensi == 'H' ? 'selected' : ''; ?>>Hadir</option>
                                            <option value="S" <?php echo $status_absensi == 'S' ? 'selected' : ''; ?>>Sakit</option>
                                            <option value="I" <?php echo $status_absensi == 'I' ? 'selected' : ''; ?>>Izin</option>
                                            <option value="A" <?php echo $status_absensi == 'A' ? 'selected' : ''; ?>>Alpa</option>
                                        </select>
                                        <button type="submit" class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-1 px-3 rounded text-xs ml-2">Simpan</button>
                                    </form>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <form action="" method="post" class="mt-2">
                                        <input type="hidden" name="jadwal_id" value="<?php echo htmlspecialchars($jadwal['id']); ?>">
                                        <input type="hidden" name="status_kehadiran" value="H">
                                        <button type="submit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-1 px-3 rounded text-xs">Tandai Hadir</button>
                                    </form>
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