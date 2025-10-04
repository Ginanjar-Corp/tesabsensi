<?php
// project-root/admin/manage_tunjangan_bulanan_guru.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/TunjanganBulananGuru.php';
require_once __DIR__ . '/../classes/Guru.php';
require_once __DIR__ . '/../classes/TahunAjaran.php';

require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$pdo = null;
$tunjangan_bulanan_model = null;
$guru_model = null;
$tahun_ajaran_model = null;

$selected_tahun_ajaran_id = isset($_GET['tahun_ajaran_id']) ? (int)$_GET['tahun_ajaran_id'] : null;
$selected_bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : null;
$tunjangan_data = [];
$gurus = [];
$tahun_ajarans = [];
$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $tunjangan_bulanan_model = new TunjanganBulananGuru($pdo);
    $guru_model = new Guru($pdo);
    $tahun_ajaran_model = new TahunAjaran($pdo);

    // Get all teachers and academic years
    $gurus = $guru_model->getAll();
    $tahun_ajarans = $tahun_ajaran_model->getAll();
    
    // Set default academic year and month if not selected
    if (!$selected_tahun_ajaran_id) {
        $active_tahun_ajaran = $tahun_ajaran_model->getActiveTahunAjaran();
        if ($active_tahun_ajaran) {
            $selected_tahun_ajaran_id = $active_tahun_ajaran['id'];
        }
    }
    if (!$selected_bulan) {
        $selected_bulan = date('n'); // Current month
    }
    
    // Fetch tunjangan data for the selected period
    $tunjangan_data = $tunjangan_bulanan_model->findByPeriod($selected_tahun_ajaran_id, $selected_bulan);

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['action'])) {
            $action = $_POST['action'];

            if ($action == 'create_or_update') {
                $guru_id = $_POST['guru_id'];
                $tahun_ajaran_id = $_POST['tahun_ajaran_id'];
                $bulan = $_POST['bulan'];
                $kegiatan_lainnya = $_POST['kegiatan_lainnya'];
                $tunjangan_kegiatan_lainnya = (int)str_replace(['Rp. ', '.', ','], ['', '', ''], $_POST['tunjangan_kegiatan_lainnya']);

                // Check if an entry already exists for the selected guru, year, and month
                $existing_entry = $tunjangan_bulanan_model->findByGuruAndPeriod($guru_id, $tahun_ajaran_id, $bulan);

                if ($existing_entry) {
                    // Update the existing entry
                    if ($tunjangan_bulanan_model->update($existing_entry['id'], [
                        'kegiatan_lainnya' => $kegiatan_lainnya,
                        'tunjangan_kegiatan_lainnya' => $tunjangan_kegiatan_lainnya
                    ])) {
                        set_flash('success', 'Tunjangan bulanan berhasil diperbarui.');
                    } else {
                        set_flash('error', 'Gagal memperbarui tunjangan bulanan.');
                    }
                } else {
                    // Create a new entry
                    if ($tunjangan_bulanan_model->create([
                        'guru_id' => $guru_id,
                        'tahun_ajaran_id' => $tahun_ajaran_id,
                        'bulan' => $bulan,
                        'kegiatan_lainnya' => $kegiatan_lainnya,
                        'tunjangan_kegiatan_lainnya' => $tunjangan_kegiatan_lainnya
                    ])) {
                        set_flash('success', 'Tunjangan bulanan berhasil ditambahkan.');
                    } else {
                        set_flash('error', 'Gagal menambahkan tunjangan bulanan.');
                    }
                }
            } elseif ($action == 'delete' && isset($_POST['id'])) {
                if ($tunjangan_bulanan_model->delete($_POST['id'])) {
                    set_flash('success', 'Tunjangan bulanan berhasil dihapus.');
                } else {
                    set_flash('error', 'Gagal menghapus tunjangan bulanan.');
                }
            }

            header('Location: ' . ROOT_URL . 'admin/manage_tunjangan_bulanan_guru.php?tahun_ajaran_id=' . $selected_tahun_ajaran_id . '&bulan=' . $selected_bulan);
            exit();
        }
    }

} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Include header
require_once __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4">Kelola Tunjangan Bulanan Guru</h1>

    <?php if ($error_message): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Error!</strong>
            <span class="block sm:inline"><?= htmlspecialchars($error_message) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Sukses!</strong>
            <span class="block sm:inline"><?= htmlspecialchars($success_message) ?></span>
        </div>
    <?php endif; ?>
    
    <div class="bg-white shadow-md rounded-lg p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Pilih Periode</h2>
        <form action="" method="GET" class="flex flex-wrap items-end space-x-4">
            <div class="mb-4">
                <label for="tahun_ajaran_id" class="block text-gray-700 text-sm font-bold mb-2">Tahun Ajaran:</label>
                <select name="tahun_ajaran_id" id="tahun_ajaran_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <?php foreach ($tahun_ajarans as $ta): ?>
                        <option value="<?= htmlspecialchars($ta['id']) ?>" <?= $ta['id'] == $selected_tahun_ajaran_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ta['tahun_ajaran']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label for="bulan" class="block text-gray-700 text-sm font-bold mb-2">Bulan:</label>
                <select name="bulan" id="bulan" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <?php foreach ($months as $key => $month): ?>
                        <option value="<?= $key ?>" <?= $key == $selected_bulan ? 'selected' : '' ?>>
                            <?= htmlspecialchars($month) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <button type="submit" class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300 shadow-md">
                    Filter
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white shadow-md rounded-lg p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Tambah/Edit Tunjangan</h2>
        <form id="tunjanganForm" action="" method="POST">
            <input type="hidden" name="action" value="create_or_update">
            <input type="hidden" name="id" id="tunjangan_id">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="guru_id" class="block text-gray-700 text-sm font-bold mb-2">Nama Guru:</label>
                    <select name="guru_id" id="guru_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        <option value="">Pilih Guru</option>
                        <?php foreach ($gurus as $guru): ?>
                            <option value="<?= htmlspecialchars($guru['id']) ?>">
                                <?= htmlspecialchars($guru['nama_lengkap']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="kegiatan_lainnya" class="block text-gray-700 text-sm font-bold mb-2">Kegiatan Lainnya:</label>
                    <input type="text" id="kegiatan_lainnya" name="kegiatan_lainnya"
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           placeholder="Cth: Mengisi rapor, kegiatan lomba" required>
                </div>
                <div>
                    <label for="tunjangan_kegiatan_lainnya" class="block text-gray-700 text-sm font-bold mb-2">Nominal Tunjangan (Rp):</label>
                    <input type="text" id="tunjangan_kegiatan_lainnya" name="tunjangan_kegiatan_lainnya"
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline currency-input"
                           placeholder="Masukkan nominal tunjangan" required>
                </div>
                <input type="hidden" name="tahun_ajaran_id" value="<?= htmlspecialchars($selected_tahun_ajaran_id) ?>">
                <input type="hidden" name="bulan" value="<?= htmlspecialchars($selected_bulan) ?>">
            </div>
            <div class="flex justify-end mt-4">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300 shadow-md">
                    Simpan Tunjangan
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white shadow-md rounded-lg p-6">
        <h2 class="text-xl font-semibold mb-4">Daftar Tunjangan Guru Bulan <?= htmlspecialchars($months[$selected_bulan]) ?> Tahun Ajaran <?= htmlspecialchars($tahun_ajarans[array_search($selected_tahun_ajaran_id, array_column($tahun_ajarans, 'id'))]['tahun_ajaran'] ?? 'N/A') ?></h2>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="py-2 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">No.</th>
                        <th class="py-2 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nama Guru</th>
                        <th class="py-2 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Kegiatan Lainnya</th>
                        <th class="py-2 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nominal Tunjangan</th>
                        <th class="py-2 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (count($tunjangan_data) > 0): ?>
                        <?php $i = 1; ?>
                        <?php foreach ($tunjangan_data as $data): ?>
                            <tr>
                                <td class="py-2 px-4 whitespace-nowrap"><?= $i++ ?></td>
                                <td class="py-2 px-4 whitespace-nowrap"><?= htmlspecialchars($data['nama_guru']) ?></td>
                                <td class="py-2 px-4 whitespace-nowrap"><?= htmlspecialchars($data['kegiatan_lainnya']) ?></td>
                                <td class="py-2 px-4 whitespace-nowrap">Rp. <?= number_format($data['tunjangan_kegiatan_lainnya'], 0, ',', '.') ?></td>
                                <td class="py-2 px-4 whitespace-nowrap">
                                    <button onclick="editTunjangan(<?= htmlspecialchars(json_encode($data)) ?>)" class="text-blue-500 hover:text-blue-700 font-semibold mr-2">Edit</button>
                                    <form action="" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data ini?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($data['id']) ?>">
                                        <input type="hidden" name="tahun_ajaran_id" value="<?= htmlspecialchars($selected_tahun_ajaran_id) ?>">
                                        <input type="hidden" name="bulan" value="<?= htmlspecialchars($selected_bulan) ?>">
                                        <button type="submit" class="text-red-500 hover:text-red-700 font-semibold">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="py-4 text-center text-gray-500">Tidak ada data tunjangan untuk periode ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function editTunjangan(data) {
        document.getElementById('tunjangan_id').value = data.id;
        document.getElementById('guru_id').value = data.guru_id;
        document.getElementById('kegiatan_lainnya').value = data.kegiatan_lainnya;
        
        // Format the number for the input field
        const formattedTunjangan = new Intl.NumberFormat('id-ID').format(data.tunjangan_kegiatan_lainnya);
        document.getElementById('tunjangan_kegiatan_lainnya').value = formattedTunjangan;
        
        // Scroll to the form
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }

    // Currency formatting on input
    const currencyInput = document.getElementById('tunjangan_kegiatan_lainnya');
    currencyInput.addEventListener('input', function(e) {
        let value = e.target.value;
        value = value.replace(/[^0-9]/g, '');
        e.target.value = new Intl.NumberFormat('id-ID').format(value);
    });
</script>

<?php
// Include footer
require_once __DIR__ . '/../components/footer.php';
?>