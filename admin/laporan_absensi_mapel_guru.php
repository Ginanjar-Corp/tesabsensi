<?php
// project-root/admin/laporan_absensi_mapel_guru.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/AbsensiMapelGuru.php';
require_once __DIR__ . '/../classes/Kelas.php';
require_once __DIR__ . '/../classes/MataPelajaran.php';
require_once __DIR__ . '/../classes/Guru.php';

// Pastikan pengguna sudah login dan memiliki peran Admin atau Tata Usaha
require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$absensi_mapel_guru_model = null;
$kelas_model = null;
$mapel_model = null;
$guru_model = null;
$pdo = null;

$report_data = [];
$all_kelas = [];
$all_mapel = [];
$all_guru = [];

$start_date = date('Y-m-01'); // Default ke awal bulan ini
$end_date = date('Y-m-d');   // Default ke hari ini
$selected_kelas_id = null;
$selected_mapel_id = null;
$selected_guru_id = null;

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $absensi_mapel_guru_model = new AbsensiMapelGuru($pdo);
    $kelas_model = new Kelas($pdo);
    $mapel_model = new MataPelajaran($pdo);
    $guru_model = new Guru($pdo);

    $all_kelas = $kelas_model->getAll();
    $all_mapel = $mapel_model->getAll();
    $all_guru = $guru_model->getAll();

    // Ambil filter dari parameter GET jika ada
    if (isset($_GET['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date'])) {
        $start_date = $_GET['start_date'];
    }
    if (isset($_GET['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date'])) {
        $end_date = $_GET['end_date'];
    }
    if (isset($_GET['kelas_id']) && (int)$_GET['kelas_id'] > 0) {
        $selected_kelas_id = (int)$_GET['kelas_id'];
    }
    if (isset($_GET['mapel_id']) && (int)$_GET['mapel_id'] > 0) {
        $selected_mapel_id = (int)$_GET['mapel_id'];
    }
    if (isset($_GET['guru_id']) && (int)$_GET['guru_id'] > 0) {
        $selected_guru_id = (int)$_GET['guru_id'];
    }

    $report_data = $absensi_mapel_guru_model->getGuruSubjectTeachingReport(
        $start_date,
        $end_date,
        $selected_guru_id,
        $selected_mapel_id,
        $selected_kelas_id
    );

} catch (PDOException $e) {
    error_log("Laporan Absensi Mapel Guru Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Laporan Absensi Mapel Guru General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
}

// Sertakan header
require_once __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Laporan Absensi Mata Pelajaran Guru</h1>

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
        <form id="filterForm" action="" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 items-end">
            <div>
                <label for="start_date" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Mulai:</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div>
                <label for="end_date" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Selesai:</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div>
                <label for="guru_id" class="block text-gray-700 text-sm font-bold mb-2">Guru:</label>
                <select id="guru_id" name="guru_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Guru</option>
                    <?php foreach ($all_guru as $guru): ?>
                        <option value="<?php echo htmlspecialchars($guru['id']); ?>" <?php echo ($selected_guru_id == $guru['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($guru['nama_lengkap']); ?>
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
            <div class="md:col-span-1 lg:col-span-1 flex justify-start items-end space-x-2">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full md:w-auto">
                    Tampilkan Laporan
                </button>
                <button type="button" id="exportExcelBtn" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full md:w-auto">
                    Export ke Excel
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold mb-4">Hasil Laporan Konfirmasi Pengajaran Guru</h2>
        <p class="text-gray-600 mb-4">Periode: <?php echo htmlspecialchars(format_date_indonesian($start_date)); ?> - <?php echo htmlspecialchars(format_date_indonesian($end_date)); ?></p>

        <?php if (empty($report_data)): ?>
            <p class="text-gray-600">Tidak ada data konfirmasi pengajaran guru untuk filter yang dipilih.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                    <thead>
                        <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">No.</th>
                            <th class="py-3 px-6 text-left">Tanggal</th>
                            <th class="py-3 px-6 text-left">Guru</th>
                            <th class="py-3 px-6 text-left">Mata Pelajaran</th>
                            <th class="py-3 px-6 text-left">Kelas</th>
                            <th class="py-3 px-6 text-left">Hari Jadwal</th>
                            <th class="py-3 px-6 text-left">Waktu Jadwal</th>
                            <th class="py-3 px-6 text-center">Waktu Mulai Ajar</th>
                            <th class="py-3 px-6 text-center">Waktu Selesai Ajar</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm">
                        <?php $no = 1; ?>
                        <?php foreach ($report_data as $data): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars(format_date_indonesian($data['tanggal_ajar'])); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($data['nama_guru']); ?> (NIP: <?php echo htmlspecialchars($data['nip'] ?? '-'); ?>)</td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($data['nama_mapel']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($data['nama_kelas']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($data['hari']); ?></td>
                                <td class="py-3 px-6"><?php echo substr(htmlspecialchars($data['jadwal_mulai']), 0, 5) . ' - ' . substr(htmlspecialchars($data['jadwal_selesai']), 0, 5); ?></td>
                                <td class="py-3 px-6 text-center"><?php echo htmlspecialchars($data['waktu_mulai_ajar'] ? substr($data['waktu_mulai_ajar'], 0, 5) : '-'); ?></td>
                                <td class="py-3 px-6 text-center"><?php echo htmlspecialchars($data['waktu_selesai_ajar'] ? substr($data['waktu_selesai_ajar'], 0, 5) : '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('exportExcelBtn').addEventListener('click', function() {
    const form = document.getElementById('filterForm');
    const formData = new FormData(form);
    
    // Construct query string from form data
    const params = new URLSearchParams();
    for (const pair of formData.entries()) {
        if (pair[1]) { // Only include non-empty values
            params.append(pair[0], pair[1]);
        }
    }

    // Redirect to a hypothetical export endpoint with current filters
    window.location.href = '<?php echo ROOT_URL; ?>api/export_absensi_mapel_guru.php?' + params.toString();
});
</script>

<?php
// Sertakan footer
require_once __DIR__ . '/../components/footer.php';
?>
