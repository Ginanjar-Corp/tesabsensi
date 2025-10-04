<?php
// project-root/public_parent/pelanggaran.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_parent.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/PelanggaranSiswa.php';
require_once __DIR__ . '/../classes/JenisPelanggaran.php';
require_once __DIR__ . '/../classes/TahunAjaran.php';

// Pastikan orang tua sudah login
require_parent_login();

$siswa_id = get_current_parent_siswa_id();
$nama_lengkap_siswa = get_current_parent_nama_lengkap();

$pelanggaran_model = null;
$jenis_pelanggaran_model = null;
$tahun_ajaran_model = null;
$pdo = null;

$pelanggaran_data = [];
$all_jenis_pelanggaran = [];
$all_tahun_ajaran = [];

// Filter variables
$filter_jenis_pelanggaran_id = isset($_GET['filter_jenis_pelanggaran_id']) ? (int)$_GET['filter_jenis_pelanggaran_id'] : null;
$filter_tahun_ajaran_id = isset($_GET['filter_tahun_ajaran_id']) ? (int)$_GET['filter_tahun_ajaran_id'] : null;

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $pelanggaran_model = new PelanggaranSiswa($pdo);
    $jenis_pelanggaran_model = new JenisPelanggaran($pdo);
    $tahun_ajaran_model = new TahunAjaran($pdo);

    $all_jenis_pelanggaran = $jenis_pelanggaran_model->getAll();
    $all_tahun_ajaran = $tahun_ajaran_model->getAllTahunAjaran();

    // Siapkan filter untuk getAllParent
    $filters = [];
    if ($filter_jenis_pelanggaran_id) {
        $filters['jenis_pelanggaran_id'] = $filter_jenis_pelanggaran_id;
    }
    if ($filter_tahun_ajaran_id) {
        $filters['tahun_ajaran_id'] = $filter_tahun_ajaran_id;
    }

    $pelanggaran_data = $pelanggaran_model->getAllParent($siswa_id, $filters);

} catch (Exception $e) {
    error_log("Parent Pelanggaran Page Error: " . $e->getMessage());
    // Handle error gracefully
    $pelanggaran_data = [];
}

// Definisikan judul halaman sebelum menyertakan header
$page_title = "Pelanggaran Anak";

require_once __DIR__ . '/../components/header_parent.php';
?>
    <!-- Main Content -->
    <div class="flex-1 p-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Pelanggaran Anak</h1>
            <div class="flex items-center space-x-4">
                <span class="text-gray-700">Selamat datang, <span class="font-semibold"><?php echo htmlspecialchars($nama_lengkap_siswa); ?></span>!</span>
                <img src="https://placehold.co/40x40/cbd5e1/475569?text=PT" alt="User Avatar" class="rounded-full border-2 border-indigo-400">
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-xl font-semibold mb-4">Filter Data Pelanggaran</h2>
            <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                <div>
                    <label for="filter_jenis_pelanggaran_id" class="block text-gray-700 text-sm font-bold mb-2">Jenis Pelanggaran:</label>
                    <select id="filter_jenis_pelanggaran_id" name="filter_jenis_pelanggaran_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="">Semua Jenis Pelanggaran</option>
                        <?php foreach ($all_jenis_pelanggaran as $jp): ?>
                            <option value="<?php echo htmlspecialchars($jp['id']); ?>" <?php echo ($filter_jenis_pelanggaran_id == $jp['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($jp['nama_pelanggaran']); ?>
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
            <h2 class="text-xl font-semibold mb-4">Daftar Catatan Pelanggaran</h2>
            <?php if (empty($pelanggaran_data)): ?>
                <p class="text-gray-600">Tidak ada catatan pelanggaran yang ditemukan untuk anak Anda.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                                <th class="py-3 px-6 text-left">No.</th>
                                <th class="py-3 px-6 text-left">Tahun Ajaran</th>
                                <th class="py-3 px-6 text-left">Tanggal</th>
                                <th class="py-3 px-6 text-left">Waktu</th>
                                <th class="py-3 px-6 text-left">Jenis Pelanggaran</th>
                                <th class="py-3 px-6 text-center">Poin Deduksi</th>
                                <th class="py-3 px-6 text-left">Petugas</th>
                                <th class="py-3 px-6 text-left">Deskripsi Kejadian</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 text-sm">
                            <?php $no = 1; ?>
                            <?php foreach ($pelanggaran_data as $pelanggaran): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                    <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                    <td class="py-3 px-6"><?php echo htmlspecialchars($pelanggaran['nama_tahun_ajaran'] ?? '-'); ?></td>
                                    <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars(format_date_indonesian($pelanggaran['tanggal_pelanggaran'])); ?></td>
                                    <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars($pelanggaran['waktu_pelanggaran']); ?></td>
                                    <td class="py-3 px-6"><?php echo htmlspecialchars($pelanggaran['nama_pelanggaran']); ?></td>
                                    <td class="py-3 px-6 text-center"><?php echo htmlspecialchars($pelanggaran['poin_deduksi_aktual']); ?></td>
                                    <td class="py-3 px-6"><?php echo htmlspecialchars($pelanggaran['nama_petugas']); ?></td>
                                    <td class="py-3 px-6"><?php echo htmlspecialchars(substr($pelanggaran['deskripsi_kejadian'] ?? '-', 0, 50)); ?><?php echo (strlen($pelanggaran['deskripsi_kejadian'] ?? '') > 50) ? '...' : ''; ?></td>
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
