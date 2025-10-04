<?php
// project-root/guru/laporan_absensi_siswa.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/AbsensiMapel.php'; // Menggunakan AbsensiMapel untuk laporan siswa
require_once __DIR__ . '/../classes/Kelas.php';
require_once __DIR__ . '/../classes/MataPelajaran.php';

// Pastikan pengguna sudah login dan memiliki peran Guru
require_login();
require_role('Guru');

$error_message = flash('error');
$success_message = flash('success');

$absensi_mapel_model = null;
$kelas_model = null;
$mapel_model = null;
$pdo = null;

$guru_id = $_SESSION['related_id']; // ID guru yang sedang login
$report_data = [];
$all_kelas = [];
$all_mapel = [];

// Default filter values
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$selected_kelas_id = (isset($_GET['kelas_id']) && (int)$_GET['kelas_id'] > 0) ? (int)$_GET['kelas_id'] : null;
$selected_mapel_id = (isset($_GET['mapel_id']) && (int)$_GET['mapel_id'] > 0) ? (int)$_GET['mapel_id'] : null;
$selected_status = $_GET['status'] ?? null;

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $absensi_mapel_model = new AbsensiMapel($pdo);
    $kelas_model = new Kelas($pdo);
    $mapel_model = new MataPelajaran($pdo);

    $all_kelas = $kelas_model->getAll();
    $all_mapel = $mapel_model->getAll();

    // Validasi format tanggal
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        set_flash('error', 'Format tanggal tidak valid.');
        // Reset tanggal ke default jika tidak valid
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-d');
    }

    $report_data = $absensi_mapel_model->getStudentAttendanceReportForTeacher(
        $guru_id,
        $start_date,
        $end_date,
        $selected_kelas_id,
        $selected_mapel_id,
        $selected_status
    );

} catch (PDOException $e) {
    error_log("Guru Laporan Absensi Siswa Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Guru Laporan Absensi Siswa General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
}

// Sertakan header
require_once __DIR__ . '/../components/header_guru.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Laporan Absensi Siswa (Mata Pelajaran)</h1>

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

    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-semibold mb-4">Filter Laporan</h2>
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label for="start_date" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Mulai:</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div>
                <label for="end_date" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Selesai:</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div>
                <label for="kelas_id" class="block text-gray-700 text-sm font-bold mb-2">Kelas:</label>
                <select id="kelas_id" name="kelas_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($all_kelas as $kelas): ?>
                        <option value="<?php echo htmlspecialchars($kelas['id']); ?>" <?php echo ($selected_kelas_id == $kelas['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="mapel_id" class="block text-gray-700 text-sm font-bold mb-2">Mata Pelajaran:</label>
                <select id="mapel_id" name="mapel_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Mata Pelajaran</option>
                    <?php foreach ($all_mapel as $mapel): ?>
                        <option value="<?php echo htmlspecialchars($mapel['id']); ?>" <?php echo ($selected_mapel_id == $mapel['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($mapel['nama_mapel']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="status" class="block text-gray-700 text-sm font-bold mb-2">Status Kehadiran:</label>
                <select id="status" name="status" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Status</option>
                    <option value="H" <?php echo ($selected_status == 'H') ? 'selected' : ''; ?>>Hadir</option>
                    <option value="S" <?php echo ($selected_status == 'S') ? 'selected' : ''; ?>>Sakit</option>
                    <option value="I" <?php echo ($selected_status == 'I') ? 'selected' : ''; ?>>Izin</option>
                    <option value="A" <?php echo ($selected_status == 'A') ? 'selected' : ''; ?>>Alpha</option>
                </select>
            </div>
            <div class="flex justify-start items-end md:col-span-1">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full md:w-auto">
                    Tampilkan Laporan
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold mb-4">Detail Absensi Siswa</h2>
        <?php if (empty($report_data)): ?>
            <p class="text-gray-600">Tidak ada data absensi siswa yang ditemukan untuk filter ini.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                    <thead>
                        <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">No.</th>
                            <th class="py-3 px-6 text-left">Tanggal</th>
                            <th class="py-3 px-6 text-left">Waktu</th>
                            <th class="py-3 px-6 text-left">NISN</th>
                            <th class="py-3 px-6 text-left">Nama Siswa</th>
                            <th class="py-3 px-6 text-left">Kelas</th>
                            <th class="py-3 px-6 text-left">Mata Pelajaran</th>
                            <th class="py-3 px-6 text-center">Status</th>
                            <th class="py-3 px-6 text-left">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm">
                        <?php $no = 1; ?>
                        <?php foreach ($report_data as $data): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars(format_date_indonesian($data['tanggal_absen'])); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars(substr($data['waktu_absen'], 0, 5)); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($data['nisn'] ?? '-'); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($data['nama_siswa']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($data['nama_kelas']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($data['nama_mapel']); ?></td>
                                <td class="py-3 px-6 text-center">
                                    <?php
                                    $status_badge_class = '';
                                    $status_text = '';
                                    switch ($data['status_kehadiran']) {
                                        case 'H': $status_text = 'Hadir'; $status_badge_class = 'bg-green-100 text-green-800'; break;
                                        case 'S': $status_text = 'Sakit'; $status_badge_class = 'bg-yellow-100 text-yellow-800'; break;
                                        case 'I': $status_text = 'Izin'; $status_badge_class = 'bg-blue-100 text-blue-800'; break;
                                        case 'A': $status_text = 'Alpha'; $status_badge_class = 'bg-red-100 text-red-800'; break;
                                        default: $status_text = 'Tidak Diketahui'; $status_badge_class = 'bg-gray-100 text-gray-800'; break;
                                    }
                                    ?>
                                    <span class="px-2 py-1 font-semibold leading-tight rounded-full <?php echo $status_badge_class; ?>">
                                        <?php echo htmlspecialchars($status_text); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($data['catatan'] ?? '-'); ?></td>
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
