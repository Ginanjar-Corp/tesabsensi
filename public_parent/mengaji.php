<?php
// project-root/public_parent/mengaji.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_parent.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/NgajiSiswa.php';
require_once __DIR__ . '/../classes/Guru.php';
require_once __DIR__ . '/../classes/TahunAjaran.php';

// Pastikan orang tua sudah login
require_parent_login();

$siswa_id = get_current_parent_siswa_id();
$nama_lengkap_siswa = get_current_parent_nama_lengkap();

$ngaji_model = null;
$guru_model = null;
$tahun_ajaran_model = null;
$pdo = null;

$ngaji_data = [];
$all_guru = [];
$all_tahun_ajaran = [];
$jenis_materi_options = ['Iqro', 'Juz Amma', 'Al-Qur\'an']; // Contoh jenis materi

// Filter variables
$filter_guru_id = isset($_GET['filter_guru_id']) ? (int)$_GET['filter_guru_id'] : null;
$filter_tahun_ajaran_id = isset($_GET['filter_tahun_ajaran_id']) ? (int)$_GET['filter_tahun_ajaran_id'] : null;
$filter_jenis_materi = isset($_GET['filter_jenis_materi']) ? $_GET['filter_jenis_materi'] : '';

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $ngaji_model = new NgajiSiswa($pdo);
    $guru_model = new Guru($pdo);
    $tahun_ajaran_model = new TahunAjaran($pdo);

    $all_guru = $guru_model->getAll();
    $all_tahun_ajaran = $tahun_ajaran_model->getAllTahunAjaran();

    // Siapkan filter untuk getAllParent
    $filters = [];
    if ($filter_guru_id) {
        $filters['guru_id'] = $filter_guru_id;
    }
    if ($filter_tahun_ajaran_id) {
        $filters['tahun_ajaran_id'] = $filter_tahun_ajaran_id;
    }
    if ($filter_jenis_materi) {
        $filters['jenis_materi'] = $filter_jenis_materi;
    }

    $ngaji_data = $ngaji_model->getAllParent($siswa_id, $filters);

} catch (Exception $e) {
    error_log("Parent Mengaji Page Error: " . $e->getMessage());
    // Handle error gracefully
    $ngaji_data = [];
}

// Definisikan judul halaman sebelum menyertakan header
$page_title = "Mengaji Anak";

require_once __DIR__ . '/../components/header_parent.php';
?>
    <!-- Main Content -->
    <div class="flex-1 p-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Mengaji Anak</h1>
            <div class="flex items-center space-x-4">
                <span class="text-gray-700">Selamat datang, <span class="font-semibold"><?php echo htmlspecialchars($nama_lengkap_siswa); ?></span>!</span>
                <img src="https://placehold.co/40x40/cbd5e1/475569?text=PT" alt="User Avatar" class="rounded-full border-2 border-indigo-400">
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-xl font-semibold mb-4">Filter Data Mengaji</h2>
            <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label for="filter_jenis_materi" class="block text-gray-700 text-sm font-bold mb-2">Jenis Materi:</label>
                    <select id="filter_jenis_materi" name="filter_jenis_materi" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="">Semua Jenis Materi</option>
                        <?php foreach ($jenis_materi_options as $jenis): ?>
                            <option value="<?php echo htmlspecialchars($jenis); ?>" <?php echo ($filter_jenis_materi == $jenis) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($jenis); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filter_guru_id" class="block text-gray-700 text-sm font-bold mb-2">Guru Pengajar:</label>
                    <select id="filter_guru_id" name="filter_guru_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="">Semua Guru</option>
                        <?php foreach ($all_guru as $guru): ?>
                            <option value="<?php echo htmlspecialchars($guru['id']); ?>" <?php echo ($filter_guru_id == $guru['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($guru['nama_lengkap']); ?>
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
            <h2 class="text-xl font-semibold mb-4">Daftar Catatan Mengaji</h2>
            <?php if (empty($ngaji_data)): ?>
                <p class="text-gray-600">Tidak ada catatan mengaji yang ditemukan untuk anak Anda.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                                <th class="py-3 px-6 text-left">No.</th>
                                <th class="py-3 px-6 text-left">Tahun Ajaran</th>
                                <th class="py-3 px-6 text-left">Tanggal</th>
                                <th class="py-3 px-6 text-left">Jenis Materi</th>
                                <th class="py-3 px-6 text-left">Halaman</th>
                                <th class="py-3 px-6 text-left">Guru</th>
                                <th class="py-3 px-6 text-left">Catatan Guru</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 text-sm">
                            <?php $no = 1; ?>
                            <?php foreach ($ngaji_data as $ngaji): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                    <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                    <td class="py-3 px-6"><?php echo htmlspecialchars($ngaji['nama_tahun_ajaran'] ?? '-'); ?></td>
                                    <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars(format_date_indonesian($ngaji['tanggal_setor'])); ?></td>
                                    <td class="py-3 px-6"><?php echo htmlspecialchars($ngaji['jenis_materi']); ?></td>
                                    <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($ngaji['halaman_dari'] . ' - ' . $ngaji['halaman_sampai']); ?></td>
                                    <td class="py-3 px-6"><?php echo htmlspecialchars($ngaji['nama_guru']); ?></td>
                                    <td class="py-3 px-6"><?php echo htmlspecialchars(substr($ngaji['catatan_guru'] ?? '-', 0, 50)); ?><?php echo (strlen($ngaji['catatan_guru'] ?? '') > 50) ? '...' : ''; ?></td>
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
