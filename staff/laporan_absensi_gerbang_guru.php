<?php
// project-root/staff/laporan_absensi_gerbang_guru.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/AbsensiGerbang.php';

// Pastikan pengguna sudah login dan memiliki peran Admin atau Tata Usaha
require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$absensi_gerbang_model = null;
$pdo = null;
$report_data = [];
$selected_date = date('Y-m-d'); // Default ke tanggal hari ini
$selected_month = date('m'); // Default ke bulan hari ini
$selected_year = date('Y');  // Default ke tahun hari ini
$all_tahun_ajaran = []; // Untuk dropdown tahun ajaran

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $absensi_gerbang_model = new AbsensiGerbang($pdo);

    // Ambil semua tahun ajaran dari database
    $all_tahun_ajaran = getAllTahunAjaran();

    // Ambil tahun ajaran aktif sebagai default filter jika tidak ada dari GET
    $active_tahun_ajaran_data = getActiveTahunAjaran();
    $default_tahun_ajaran = $active_tahun_ajaran_data['tahun_ajaran'] ?? null;

    // Get filter parameters from GET request
    $filter_tahun_ajaran = $_GET['tahun_ajaran'] ?? $default_tahun_ajaran;
    $filter_semester = $_GET['semester'] ?? APP_SEMESTER_AKTIF;

    // Ambil tanggal dari parameter GET jika ada (untuk laporan harian)
    if (isset($_GET['date'])) {
        $input_date = $_GET['date'];
        // Validasi format tanggal
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $input_date)) {
            $selected_date = $input_date;
        } else {
            set_flash('error', 'Format tanggal tidak valid. Menggunakan tanggal hari ini.');
        }
    }

    // Ambil bulan dan tahun dari parameter GET jika ada (untuk laporan bulanan)
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

    // Fetch daily report data for guru
    $report_data = $absensi_gerbang_model->getDailyGuruGateAttendanceReport(
        $selected_date,
        $filter_tahun_ajaran,
        $filter_semester
    );

} catch (PDOException $e) {
    error_log("Laporan Absensi Gerbang Guru Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Laporan Absensi Gerbang Guru General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
}

// Sertakan header
require_once __DIR__ . '/../components/header_staff.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Laporan Absensi Gerbang Guru</h1>

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
        <h2 class="text-xl font-semibold mb-4">Laporan Harian - Pilih Tanggal, Tahun Ajaran & Semester</h2>
        <form id="dailyFilterForm" action="" method="GET" class="flex flex-col md:flex-row items-end md:space-x-4 space-y-4 md:space-y-0">
            <div>
                <label for="date" class="block text-gray-700 text-sm font-bold mb-2">Tanggal:</label>
                <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($selected_date); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div>
                <label for="tahun_ajaran" class="block text-gray-700 text-sm font-bold mb-2">Tahun Ajaran:</label>
                <select id="tahun_ajaran" name="tahun_ajaran" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua</option>
                    <?php foreach ($all_tahun_ajaran as $ta): ?>
                        <option value="<?php echo htmlspecialchars($ta['tahun_ajaran']); ?>"
                                <?php echo ($filter_tahun_ajaran == $ta['tahun_ajaran']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ta['tahun_ajaran']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="semester_filter" class="block text-gray-700 text-sm font-bold mb-2">Semester:</label>
                <select id="semester_filter" name="semester" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua</option>
                    <option value="Ganjil" <?php echo ($filter_semester == 'Ganjil') ? 'selected' : ''; ?>>Ganjil</option>
                    <option value="Genap" <?php echo ($filter_semester == 'Genap') ? 'selected' : ''; ?>>Genap</option>
                </select>
            </div>
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                Tampilkan Laporan Harian
            </button>
            <button type="button" id="exportDailyExcelBtn" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                Export Harian ke Excel
            </button>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-semibold mb-4">Laporan Bulanan - Pilih Bulan, Tahun, Tahun Ajaran & Semester</h2>
        <form id="monthlyFilterForm" action="" method="GET" class="flex flex-col md:flex-row items-end md:space-x-4 space-y-4 md:space-y-0">
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
                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 6; $y++): // Adjust year range as needed ?>
                        <option value="<?php echo $y; ?>" <?php echo ($selected_year == $y) ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label for="tahun_ajaran_monthly" class="block text-gray-700 text-sm font-bold mb-2">Tahun Ajaran:</label>
                <select id="tahun_ajaran_monthly" name="tahun_ajaran" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua</option>
                    <?php foreach ($all_tahun_ajaran as $ta): ?>
                        <option value="<?php echo htmlspecialchars($ta['tahun_ajaran']); ?>"
                                <?php echo ($filter_tahun_ajaran == $ta['tahun_ajaran']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ta['tahun_ajaran']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="semester_monthly" class="block text-gray-700 text-sm font-bold mb-2">Semester:</label>
                <select id="semester_monthly" name="semester" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua</option>
                    <option value="Ganjil" <?php echo ($filter_semester == 'Ganjil') ? 'selected' : ''; ?>>Ganjil</option>
                    <option value="Genap" <?php echo ($filter_semester == 'Genap') ? 'selected' : ''; ?>>Genap</option>
                </select>
            </div>
            <button type="button" id="exportMonthlyExcelBtn" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                Export Bulanan ke Excel
            </button>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold mb-4">Absensi Gerbang Guru Tanggal: <?php echo htmlspecialchars(format_date_indonesian($selected_date)); ?></h2>
        
        <?php if (empty($report_data)): ?>
            <p class="text-gray-600">Tidak ada data absensi gerbang guru untuk tanggal ini.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                    <thead>
                        <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">No.</th>
                            <th class="py-3 px-6 text-left">NIP</th>
                            <th class="py-3 px-6 text-left">Nama Guru</th>
                            <th class="py-3 px-6 text-center">Jam Masuk</th>
                            <th class="py-3 px-6 text-center">Jam Pulang</th>
                            <th class="py-3 px-6 text-center">Status Masuk</th>
                            <th class="py-3 px-6 text-center">Status Pulang</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm">
                        <?php $no = 1; ?>
                        <?php foreach ($report_data as $data): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($data['nip'] ?? '-'); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($data['nama_lengkap']); ?></td>
                                <td class="py-3 px-6 text-center"><?php echo htmlspecialchars($data['jam_masuk'] ? substr($data['jam_masuk'], 0, 5) : '-'); ?></td>
                                <td class="py-3 px-6 text-center"><?php echo htmlspecialchars($data['jam_pulang'] ? substr($data['jam_pulang'], 0, 5) : '-'); ?></td>
                                <td class="py-3 px-6 text-center">
                                    <span class="px-2 py-1 font-semibold leading-tight rounded-full <?php 
                                        if ($data['status_masuk'] == 'Hadir') echo 'bg-green-100 text-green-800';
                                        else if ($data['status_masuk'] == 'Terlambat') echo 'bg-yellow-100 text-yellow-800';
                                        else echo 'bg-red-100 text-red-800';
                                    ?>">
                                        <?php echo htmlspecialchars($data['status_masuk']); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-6 text-center">
                                    <span class="px-2 py-1 font-semibold leading-tight rounded-full <?php 
                                        if ($data['status_pulang'] == 'Pulang') echo 'bg-blue-100 text-blue-800';
                                        else echo 'bg-gray-100 text-gray-800'; // Belum Pulang
                                    ?>">
                                        <?php echo htmlspecialchars($data['status_pulang']); ?>
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

<script>
document.getElementById('exportDailyExcelBtn').addEventListener('click', function() {
    const form = document.getElementById('dailyFilterForm');
    const formData = new FormData(form);
    
    const params = new URLSearchParams();
    for (const pair of formData.entries()) {
        if (pair[1]) {
            params.append(pair[0], pair[1]);
        }
    }

    // Arahkan ke endpoint ekspor harian guru
    window.location.href = '<?php echo ROOT_URL; ?>api/export_absensi_gerbang_guru.php?' + params.toString();
});

document.getElementById('exportMonthlyExcelBtn').addEventListener('click', function() {
    const form = document.getElementById('monthlyFilterForm');
    const formData = new FormData(form);
    
    const params = new URLSearchParams();
    for (const pair of formData.entries()) {
        if (pair[1]) {
            params.append(pair[0], pair[1]);
        }
    }

    // Arahkan ke endpoint ekspor bulanan guru
    window.location.href = '<?php echo ROOT_URL; ?>api/export_absensi_gerbang_guru_bulanan.php?' + params.toString();
});
</script>

<?php
// Sertakan footer
require_once __DIR__ . '/../components/footer.php';
?>
