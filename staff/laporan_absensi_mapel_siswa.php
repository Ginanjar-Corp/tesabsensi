<?php
// project-root/staff/laporan_absensi_mapel_siswa.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/AbsensiMapelSiswa.php';
require_once __DIR__ . '/../classes/Kelas.php';
require_once __DIR__ . '/../classes/MataPelajaran.php';
require_once __DIR__ . '/../classes/Guru.php';

// Pastikan pengguna sudah login dan memiliki peran Admin atau Tata Usaha
require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$absensi_mapel_siswa_model = null;
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
$selected_month = date('m'); // Default ke bulan hari ini
$selected_year = date('Y');   // Default ke tahun hari ini

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $absensi_mapel_siswa_model = new AbsensiMapelSiswa($pdo);
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
    if (isset($_GET['month']) && isset($_GET['year'])) {
        $input_month = $_GET['month'];
        $input_year = $_GET['year'];
        if (preg_match('/^\d{2}$/', $input_month) && preg_match('/^\d{4}$/', $input_year)) {
            $selected_month = $input_month;
            $selected_year = $input_year;
        } else {
            set_flash('error', 'Format bulan atau tahun tidak valid. Menggunakan bulan dan tahun hari ini.');
        }
    }


    $report_data = $absensi_mapel_siswa_model->getStudentSubjectAttendanceReport(
        $start_date,
        $end_date,
        $selected_kelas_id,
        $selected_mapel_id,
        $selected_guru_id
    );

} catch (PDOException $e) {
    error_log("Laporan Absensi Mapel Siswa Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Laporan Absensi Mapel Siswa General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
}

// Sertakan header
require_once __DIR__ . '/../components/header_staff.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Laporan Absensi Mata Pelajaran Siswa</h1>

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
        <h2 class="text-xl font-semibold mb-4">Filter Laporan Harian</h2>
        <form id="dailyFilterForm" action="" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 items-end">
            <div>
                <label for="start_date" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Mulai:</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div>
                <label for="end_date" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Selesai:</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
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
            <div class="md:col-span-1 lg:col-span-1 flex justify-start items-end">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full md:w-auto">
                    Tampilkan Laporan
                </button>
                <button type="button" id="exportDailyExcelBtn" class="ml-2 bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full md:w-auto">
                    Export Harian ke Excel
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-semibold mb-4">Filter Laporan Bulanan</h2>
        <form id="monthlyFilterForm" action="" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 items-end">
            <div>
                <label for="month" class="block text-gray-700 text-sm font-bold mb-2">Bulan:</label>
                <select id="month" name="month" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" <?php echo ($selected_month == str_pad($m, 2, '0', STR_PAD_LEFT)) ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0, 0, 0, $m, 10)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label for="year" class="block text-gray-700 text-sm font-bold mb-2">Tahun:</label>
                <select id="year" name="year" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <?php for ($y = date('Y') - 5; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($selected_year == $y) ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label for="kelas_id_monthly" class="block text-gray-700 text-sm font-bold mb-2">Kelas:</label>
                <select id="kelas_id_monthly" name="kelas_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($all_kelas as $kelas): ?>
                        <option value="<?php echo htmlspecialchars($kelas['id']); ?>" <?php echo ($selected_kelas_id == $kelas['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="mapel_id_monthly" class="block text-gray-700 text-sm font-bold mb-2">Mata Pelajaran:</label>
                <select id="mapel_id_monthly" name="mapel_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Mata Pelajaran</option>
                    <?php foreach ($all_mapel as $mapel): ?>
                        <option value="<?php echo htmlspecialchars($mapel['id']); ?>" <?php echo ($selected_mapel_id == $mapel['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($mapel['nama_mapel']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="guru_id_monthly" class="block text-gray-700 text-sm font-bold mb-2">Guru:</label>
                <select id="guru_id_monthly" name="guru_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Guru</option>
                    <?php foreach ($all_guru as $guru): ?>
                        <option value="<?php echo htmlspecialchars($guru['id']); ?>" <?php echo ($selected_guru_id == $guru['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($guru['nama_lengkap']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-1 lg:col-span-1 flex justify-start items-end">
                <button type="button" id="exportMonthlyExcelBtn" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full md:w-auto">
                    Export Bulanan ke Excel
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold mb-4">Hasil Laporan Absensi</h2>
        <p class="text-gray-600 mb-4">Periode: <?php echo htmlspecialchars(format_date_indonesian($start_date)); ?> - <?php echo htmlspecialchars(format_date_indonesian($end_date)); ?></p>

        <?php if (empty($report_data)): ?>
            <p class="text-gray-600">Tidak ada data absensi mata pelajaran siswa untuk filter yang dipilih.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                    <thead>
                        <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">No.</th>
                            <th class="py-3 px-6 text-left">Tanggal</th>
                            <th class="py-3 px-6 text-left">Waktu</th>
                            <th class="py-3 px-6 text-left">Kelas</th>
                            <th class="py-3 px-6 text-left">Mata Pelajaran</th>
                            <th class="py-3 px-6 text-left">Guru</th>
                            <th class="py-3 px-6 text-left">NISN</th>
                            <th class="py-3 px-6 text-left">Nama Siswa</th>
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
                                <td class="py-3 px-6"><?php echo htmlspecialchars($data['nama_kelas']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($data['nama_mapel']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($data['nama_guru']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($data['nisn'] ?? '-'); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($data['nama_siswa']); ?></td>
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

<script>
document.getElementById('exportDailyExcelBtn').addEventListener('click', function() {
    const form = document.getElementById('dailyFilterForm');
    const formData = new FormData(form);
    
    const params = new URLSearchParams();
    // Ambil parameter dari form harian
    const startDate = form.querySelector('#start_date').value;
    const endDate = form.querySelector('#end_date').value;
    const kelasId = form.querySelector('#kelas_id').value;
    const mapelId = form.querySelector('#mapel_id').value;
    const guruId = form.querySelector('#guru_id').value;

    if (startDate) params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);
    if (kelasId) params.append('kelas_id', kelasId);
    if (mapelId) params.append('mapel_id', mapelId);
    if (guruId) params.append('guru_id', guruId);

    // Arahkan ke endpoint ekspor harian mapel siswa
    window.location.href = '<?php echo ROOT_URL; ?>api/export_absensi_mapel_siswa_harian.php?' + params.toString();
});

document.getElementById('exportMonthlyExcelBtn').addEventListener('click', function() {
    const form = document.getElementById('monthlyFilterForm');
    const formData = new FormData(form);
    
    const params = new URLSearchParams();
    // Ambil parameter dari form bulanan
    const month = form.querySelector('#month').value;
    const year = form.querySelector('#year').value;
    const kelasId = form.querySelector('#kelas_id_monthly').value;
    const mapelId = form.querySelector('#mapel_id_monthly').value;
    const guruId = form.querySelector('#guru_id_monthly').value;

    if (month) params.append('month', month);
    if (year) params.append('year', year);
    if (kelasId) params.append('kelas_id', kelasId);
    if (mapelId) params.append('mapel_id', mapelId);
    if (guruId) params.append('guru_id', guruId);

    // Arahkan ke endpoint ekspor bulanan mapel siswa
    window.location.href = '<?php echo ROOT_URL; ?>api/export_absensi_mapel_siswa_bulanan.php?' + params.toString();
});
</script>

<?php
// Sertakan footer
require_once __DIR__ . '/../components/footer.php';
?>
