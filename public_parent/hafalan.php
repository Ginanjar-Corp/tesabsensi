<?php
// project-root/public_parent/hafalan.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_parent.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/HafalanSiswa.php';
require_once __DIR__ . '/../classes/KitabNadhom.php';
require_once __DIR__ . '/../classes/TahunAjaran.php';

// Pastikan orang tua sudah login
require_parent_login();

$siswa_id = get_current_parent_siswa_id();
$nama_lengkap_siswa = get_current_parent_nama_lengkap();

$hafalan_model = null;
$kitab_nadhom_model = null;
$tahun_ajaran_model = null;
$pdo = null;

$hafalan_data = [];
$all_kitab_nadhom = [];
$all_tahun_ajaran = [];

// Filter variables
$filter_kitab_nadhom_id = isset($_GET['filter_kitab_nadhom_id']) ? (int)$_GET['filter_kitab_nadhom_id'] : null;
$filter_tahun_ajaran_id = isset($_GET['filter_tahun_ajaran_id']) ? (int)$_GET['filter_tahun_ajaran_id'] : null;

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $hafalan_model = new HafalanSiswa($pdo);
    $kitab_nadhom_model = new KitabNadhom($pdo);
    $tahun_ajaran_model = new TahunAjaran($pdo);

    // Ambil hanya kitab jenis 'Hafalan'
    $all_kitab_nadhom = $kitab_nadhom_model->getKitabByJenis('Hafalan');
    $all_tahun_ajaran = $tahun_ajaran_model->getAllTahunAjaran();

    // Siapkan filter untuk getAllParent
    $filters = [];
    if ($filter_kitab_nadhom_id) {
        $filters['kitab_nadhom_id'] = $filter_kitab_nadhom_id;
    }
    if ($filter_tahun_ajaran_id) {
        $filters['tahun_ajaran_id'] = $filter_tahun_ajaran_id;
    }

    $hafalan_data = $hafalan_model->getAllParent($siswa_id, $filters);

} catch (Exception $e) {
    error_log("Parent Hafalan Page Error: " . $e->getMessage());
    // Handle error gracefully, maybe show a user-friendly message
    $hafalan_data = [];
}

// Definisikan judul halaman sebelum menyertakan header
$page_title = "Hafalan Anak";

require_once __DIR__ . '/../components/header_parent.php';
?>
    <!-- Main Content -->
    <div class="flex-1 p-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Hafalan Anak</h1>
            <div class="flex items-center space-x-4">
                <span class="text-gray-700">Selamat datang, <span class="font-semibold"><?php echo htmlspecialchars($nama_lengkap_siswa); ?></span>!</span>
                <img src="https://placehold.co/40x40/cbd5e1/475569?text=PT" alt="User Avatar" class="rounded-full border-2 border-indigo-400">
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-xl font-semibold mb-4">Filter Data Hafalan</h2>
            <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label for="filter_kitab_nadhom_id" class="block text-gray-700 text-sm font-bold mb-2">Kitab/Nadhom:</label>
                    <select id="filter_kitab_nadhom_id" name="filter_kitab_nadhom_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="">Semua Kitab/Nadhom</option>
                        <?php foreach ($all_kitab_nadhom as $kitab): ?>
                            <option value="<?php echo htmlspecialchars($kitab['id']); ?>" <?php echo ($filter_kitab_nadhom_id == $kitab['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($kitab['nama_kitab']); ?>
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
                <div class="flex justify-start items-end">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full md:w-auto">
                        Tampilkan Filter
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold mb-4">Daftar Catatan Hafalan</h2>
            <?php if (empty($hafalan_data)): ?>
                <p class="text-gray-600">Tidak ada catatan hafalan yang ditemukan untuk anak Anda.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                                <th class="py-3 px-6 text-left">No.</th>
                                <th class="py-3 px-6 text-left">Tahun Ajaran</th>
                                <th class="py-3 px-6 text-left">Tanggal</th>
                                <th class="py-3 px-6 text-left">Kitab/Nadhom</th>
                                <th class="py-3 px-6 text-left">Bagian Hafalan</th>
                                <th class="py-3 px-6 text-center">Nilai</th>
                                <th class="py-3 px-6 text-left">Guru</th>
                                <th class="py-3 px-6 text-left">Jenis Setor</th>
                                <th class="py-3 px-6 text-center">Status</th>
                                <th class="py-3 px-6 text-left">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 text-sm">
                            <?php $no = 1; ?>
                            <?php foreach ($hafalan_data as $hafalan): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                    <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                    <td class="py-3 px-6"><?php echo htmlspecialchars($hafalan['nama_tahun_ajaran'] ?? '-'); ?></td>
                                    <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars(format_date_indonesian($hafalan['tanggal_setor'])); ?></td>
                                    <td class="py-3 px-6"><?php echo htmlspecialchars($hafalan['nama_kitab']); ?></td>
                                    <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($hafalan['bagian_hafalan']); ?></td>
                                    <td class="py-3 px-6 text-center"><?php echo htmlspecialchars($hafalan['nilai']); ?></td>
                                    <td class="py-3 px-6"><?php echo htmlspecialchars($hafalan['nama_guru']); ?></td>
                                    <td class="py-3 px-6"><?php echo htmlspecialchars($hafalan['jenis_setor']); ?></td>
                                    <td class="py-3 px-6 text-center">
                                        <span class="px-2 py-1 font-semibold leading-tight rounded-full
                                            <?php
                                                // Logika untuk warna status (sesuaikan dengan status yang Anda miliki)
                                                if ($hafalan['status_hafalan'] == 'Mencapai Target') echo 'bg-green-100 text-green-800';
                                                elseif ($hafalan['status_hafalan'] == 'Masih Kurang') echo 'bg-yellow-100 text-yellow-800';
                                                elseif ($hafalan['status_hafalan'] == 'Belum Mulai') echo 'bg-red-110 text-red-800';
                                                else echo 'bg-gray-100 text-gray-800'; // Default
                                            ?>">
                                            <?php echo htmlspecialchars($hafalan['status_hafalan']); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-6"><?php echo htmlspecialchars(substr($hafalan['catatan'] ?? '-', 0, 50)); ?><?php echo (strlen($hafalan['catatan'] ?? '') > 50) ? '...' : ''; ?></td>
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
