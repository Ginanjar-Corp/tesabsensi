<?php
// project-root/public_parent/izin.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_parent.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/IzinKeluarPulang.php';
require_once __DIR__ . '/../classes/TahunAjaran.php';

// Pastikan orang tua sudah login
require_parent_login();

$siswa_id = get_current_parent_siswa_id();
$nama_lengkap_siswa = get_current_parent_nama_lengkap();

$izin_model = null;
$tahun_ajaran_model = null;
$pdo = null;

$izin_data = [];
$all_tahun_ajaran = [];
$status_izin_options = ['Menunggu', 'Disetujui', 'Ditolak', 'Dibatalkan'];

// Filter variables
$filter_status_izin = isset($_GET['filter_status_izin']) ? $_GET['filter_status_izin'] : '';
$filter_tahun_ajaran_id = isset($_GET['filter_tahun_ajaran_id']) ? (int)$_GET['filter_tahun_ajaran_id'] : null;

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $izin_model = new IzinKeluarPulang($pdo);
    $tahun_ajaran_model = new TahunAjaran($pdo);

    $all_tahun_ajaran = $tahun_ajaran_model->getAllTahunAjaran();

    // Siapkan filter untuk getAllParent
    $filters = [];
    if ($filter_status_izin) {
        $filters['status_izin'] = $filter_status_izin;
    }
    if ($filter_tahun_ajaran_id) {
        $filters['tahun_ajaran_id'] = $filter_tahun_ajaran_id;
    }

    // DEBUGGING: Tampilkan siswa_id dan filters yang digunakan
    error_log("DEBUG: Siswa ID for Izin: " . ($siswa_id ?? 'NULL'));
    error_log("DEBUG: Filters for Izin: " . print_r($filters, true));

    $izin_data = $izin_model->getAllParent($siswa_id, $filters);


} catch (Exception $e) {
    error_log("Parent Izin Page Error: " . $e->getMessage());
    // Handle error gracefully
    $izin_data = [];
}

// Definisikan judul halaman sebelum menyertakan header
$page_title = "Izin Keluar/Pulang Anak";

require_once __DIR__ . '/../components/header_parent.php';
?>
    <!-- Main Content -->
    <div class="flex-1 p-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Izin Keluar/Pulang Anak</h1>
            <div class="flex items-center space-x-4">
                <span class="text-gray-700">Selamat datang, <span class="font-semibold"><?php echo htmlspecialchars($nama_lengkap_siswa); ?></span>!</span>
                <img src="https://placehold.co/40x40/cbd5e1/475569?text=PT" alt="User Avatar" class="rounded-full border-2 border-indigo-400">
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-xl font-semibold mb-4">Filter Data Izin</h2>
            <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                <div>
                    <label for="filter_status_izin" class="block text-gray-700 text-sm font-bold mb-2">Status Izin:</label>
                    <select id="filter_status_izin" name="filter_status_izin" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="">Semua Status</option>
                        <?php foreach ($status_izin_options as $status): ?>
                            <option value="<?php echo htmlspecialchars($status); ?>" <?php echo ($filter_status_izin == $status) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filter_tahun_ajaran_id" class="block text-gray-700 text-sm font-bold mb-2">Tahun Ajaran:</label>
                    <select id="filter_tahun_ajaran_id" name="filter_tahun_ajaran_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="">Semua Tahun Ajaran</option>
                        <?php foreach ($all_tahun_ajaran as $ta): ?>
                            <option value="<?php echo htmlspecialchars($ta['id']); ?>" <?php echo ($filter_tahun_ajaran_id == $ta['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ta['tahun_ajaran']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex justify-start items-end md:col-span-1">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full md:w-auto">
                        Tampilkan Filter
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold mb-4">Daftar Izin Keluar/Pulang</h2>
            <?php if (empty($izin_data)): ?>
                <p class="text-gray-600">Tidak ada catatan izin keluar/pulang yang ditemukan untuk anak Anda.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                                <th class="py-3 px-6 text-left">No.</th>
                                <th class="py-3 px-6 text-left">Tahun Ajaran</th>
                                <th class="py-3 px-6 text-left">Tanggal Izin</th>
                                <th class="py-3 px-6 text-left">Waktu Keluar</th>
                                <th class="py-3 px-6 text-left">Waktu Kembali</th>
                                <th class="py-3 px-6 text-left">Keperluan</th>
                                <th class="py-3 px-6 text-left">Diberi Izin Oleh</th>
                                <th class="py-3 px-6 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 text-sm">
                            <?php $no = 1; ?>
                            <?php foreach ($izin_data as $izin): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                    <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                    <td class="py-3 px-6"><?php echo htmlspecialchars($izin['nama_tahun_ajaran'] ?? '-'); ?></td>
                                    <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars(format_date_indonesian($izin['tanggal_izin'])); ?></td>
                                    <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars($izin['waktu_keluar']); ?></td>
                                    <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars($izin['waktu_kembali']); ?></td>
                                    <td class="py-3 px-6"><?php echo htmlspecialchars($izin['keperluan']); ?></td>
                                    <td class="py-3 px-6"><?php echo htmlspecialchars($izin['nama_pemberi_izin'] ?? 'N/A'); ?></td>
                                    <td class="py-3 px-6 text-center">
                                        <span class="px-2 py-1 font-semibold leading-tight rounded-full
                                            <?php
                                                // Logika untuk warna status
                                                if ($izin['status_izin'] == 'Disetujui') echo 'bg-green-100 text-green-800';
                                                elseif ($izin['status_izin'] == 'Menunggu') echo 'bg-yellow-100 text-yellow-800';
                                                elseif ($izin['status_izin'] == 'Ditolak') echo 'bg-red-100 text-red-800';
                                                else echo 'bg-gray-100 text-gray-800'; // Dibatalkan atau default
                                            ?>">
                                            <?php echo htmlspecialchars($izin['status_izin']); ?>
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
<?php
require_once __DIR__ . '/../components/footer_parent.php';
?>
