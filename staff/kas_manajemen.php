<?php
// project-root/admin/kas_manajemen.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/KasKategori.php';
require_once __DIR__ . '/../classes/KasUnit.php';
require_once __DIR__ . '/../classes/KasTransaksi.php';
require_once __DIR__ . '/../classes/TagihanSiswa.php'; // Diperlukan untuk cek tagihan siswa

require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

global $pdo;
$kas_kategori_model = new KasKategori($pdo);
$kas_unit_model = new KasUnit($pdo);
$kas_transaksi_model = new KasTransaksi($pdo);
$tagihan_siswa_model = new TagihanSiswa($pdo); // Inisialisasi model tagihan siswa

$all_kategori = $kas_kategori_model->getAll();
$all_unit = $kas_unit_model->getAll();

$current_user_id = $_SESSION['user_data']['id'] ?? null;

// Handle form submission for adding/editing transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $tanggal_transaksi = filter_input(INPUT_POST, 'tanggal_transaksi', FILTER_SANITIZE_STRING);
    $deskripsi = filter_input(INPUT_POST, 'deskripsi', FILTER_SANITIZE_STRING);
    $jumlah = (float)filter_input(INPUT_POST, 'jumlah', FILTER_VALIDATE_FLOAT);
    $tipe_transaksi = filter_input(INPUT_POST, 'tipe_transaksi', FILTER_SANITIZE_STRING);
    $kategori_id = filter_input(INPUT_POST, 'kategori_id', FILTER_VALIDATE_INT);
    $unit_id = filter_input(INPUT_POST, 'unit_id', FILTER_VALIDATE_INT);

    if (empty($tanggal_transaksi) || empty($deskripsi) || $jumlah <= 0 || empty($tipe_transaksi) || !$kategori_id || !$unit_id) {
        set_flash('error', 'Semua field wajib diisi dan jumlah harus lebih dari 0.');
        redirect(ROOT_URL . 'admin/kas_manajemen.php');
    }

    if ($_POST['action'] === 'add') {
        if ($kas_transaksi_model->create($tanggal_transaksi, $deskripsi, $jumlah, $tipe_transaksi, $kategori_id, $unit_id, $current_user_id)) {
            set_flash('success', 'Transaksi kas berhasil ditambahkan.');
        } else {
            set_flash('error', 'Gagal menambahkan transaksi kas.');
        }
    } elseif ($_POST['action'] === 'edit') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id && $kas_transaksi_model->update($id, $tanggal_transaksi, $deskripsi, $jumlah, $tipe_transaksi, $kategori_id, $unit_id)) {
            set_flash('success', 'Transaksi kas berhasil diperbarui.');
        } else {
            set_flash('error', 'Gagal memperbarui transaksi kas.');
        }
    }
    redirect(ROOT_URL . 'admin/kas_manajemen.php');
}

// Handle delete transaction
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id && $kas_transaksi_model->delete($id)) {
        set_flash('success', 'Transaksi kas berhasil dihapus.');
    } else {
        set_flash('error', 'Gagal menghapus transaksi kas.');
    }
    redirect(ROOT_URL . 'admin/kas_manajemen.php');
}


// --- Data untuk ringkasan global ---
$total_kas_masuk_global = $kas_transaksi_model->getTotalKasMasukGlobal();
$total_kas_keluar_global = $kas_transaksi_model->getTotalKasKeluarGlobal();
$saldo_kas_global = $total_kas_masuk_global - $total_kas_keluar_global;

// --- Data untuk ringkasan kas per unit (BARU) ---
$unit_cash_summary = $kas_transaksi_model->getUnitCashSummary();


// --- Logic for displaying edit form ---
$edit_transaction = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit_form' && isset($_GET['id'])) {
    $id_to_edit = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id_to_edit) {
        $edit_transaction = $kas_transaksi_model->getById($id_to_edit);
    }
}

// --- Logic for filters and pagination ---
$limit = 10;
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?? 1;
$offset = ($page - 1) * $limit;

$filters = [
    'search' => filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?? '',
    'kategori_id' => filter_input(INPUT_GET, 'kategori_id', FILTER_VALIDATE_INT),
    'unit_id' => filter_input(INPUT_GET, 'unit_id', FILTER_VALIDATE_INT),
    'tipe_transaksi' => filter_input(INPUT_GET, 'tipe_transaksi', FILTER_SANITIZE_STRING) ?? '',
    'start_date' => filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_STRING) ?? '',
    'end_date' => filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_STRING) ?? '',
];

// Remove null/empty values from filters
$filters = array_filter($filters, function($value) {
    return $value !== null && $value !== '';
});

$transactions = $kas_transaksi_model->getTransactions($filters, $limit, $offset);
$total_transactions = $kas_transaksi_model->countTransactions($filters);
$total_pages = ceil($total_transactions / $limit);


require_once __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manajemen Kas</h1>

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

    <!-- Ringkasan Kas Global -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-green-100 p-6 rounded-lg shadow-md border border-green-200">
            <p class="text-green-800 font-semibold">TOTAL KAS MASUK GLOBAL</p>
            <p class="text-green-900 text-2xl font-bold mt-2">Rp <?php echo number_format($total_kas_masuk_global, 2, ',', '.'); ?></p>
        </div>
        <div class="bg-red-100 p-6 rounded-lg shadow-md border border-red-200">
            <p class="text-red-800 font-semibold">TOTAL KAS KELUAR GLOBAL</p>
            <p class="text-red-900 text-2xl font-bold mt-2">Rp <?php echo number_format($total_kas_keluar_global, 2, ',', '.'); ?></p>
        </div>
        <div class="bg-blue-100 p-6 rounded-lg shadow-md border border-blue-200">
            <p class="text-blue-800 font-semibold">SALDO KAS GLOBAL</p>
            <p class="text-blue-900 text-2xl font-bold mt-2">Rp <?php echo number_format($saldo_kas_global, 2, ',', '.'); ?></p>
        </div>
    </div>

    <!-- Rincian Saldo Kas per Unit -->
    <h2 class="text-2xl font-bold text-gray-800 mb-4 mt-8">Rincian Saldo Kas per Unit</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-8">
        <?php if (!empty($unit_cash_summary)): ?>
            <?php foreach ($unit_cash_summary as $unit_summary): ?>
                <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200">
                    <h3 class="text-lg font-semibold mb-2 text-gray-800"><?php echo htmlspecialchars($unit_summary['nama_unit']); ?></h3>
                    <p class="text-green-600">Masuk: Rp <?php echo number_format($unit_summary['total_masuk'], 2, ',', '.'); ?></p>
                    <p class="text-red-600">Keluar: Rp <?php echo number_format($unit_summary['total_keluar'], 2, ',', '.'); ?></p>
                    <p class="text-blue-600 font-bold">Saldo: Rp <?php echo number_format($unit_summary['total_masuk'] - $unit_summary['total_keluar'], 2, ',', '.'); ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-gray-600">Tidak ada data ringkasan kas per unit.</p>
        <?php endif; ?>
    </div>

    <hr class="my-8">

    <!-- Form Tambah/Edit Transaksi -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8 border border-gray-200">
        <h2 class="text-xl font-semibold mb-4 text-gray-800"><?php echo $edit_transaction ? 'Edit Transaksi Kas' : 'Tambah Transaksi Kas Baru'; ?></h2>
        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="<?php echo $edit_transaction ? 'edit' : 'add'; ?>">
            <?php if ($edit_transaction): ?>
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_transaction['id']); ?>">
            <?php endif; ?>

            <div>
                <label for="tanggal_transaksi" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Transaksi:</label>
                <input type="date" id="tanggal_transaksi" name="tanggal_transaksi"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500"
                       value="<?php echo htmlspecialchars($edit_transaction['tanggal_transaksi'] ?? date('Y-m-d')); ?>" required>
            </div>
            <div>
                <label for="deskripsi" class="block text-gray-700 text-sm font-bold mb-2">Deskripsi:</label>
                <textarea id="deskripsi" name="deskripsi" rows="3"
                          class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500"
                          placeholder="Deskripsi transaksi" required><?php echo htmlspecialchars($edit_transaction['deskripsi'] ?? ''); ?></textarea>
            </div>
            <div>
                <label for="jumlah" class="block text-gray-700 text-sm font-bold mb-2">Jumlah (Rp):</label>
                <input type="number" step="0.01" id="jumlah" name="jumlah"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500"
                       value="<?php echo htmlspecialchars($edit_transaction['jumlah'] ?? ''); ?>" required min="0.01">
            </div>
            <div>
                <label for="tipe_transaksi" class="block text-gray-700 text-sm font-bold mb-2">Tipe Transaksi:</label>
                <select id="tipe_transaksi" name="tipe_transaksi"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500" required>
                    <option value="masuk" <?php echo (isset($edit_transaction['tipe_transaksi']) && $edit_transaction['tipe_transaksi'] == 'masuk') ? 'selected' : ''; ?>>Masuk</option>
                    <option value="keluar" <?php echo (isset($edit_transaction['tipe_transaksi']) && $edit_transaction['tipe_transaksi'] == 'keluar') ? 'selected' : ''; ?>>Keluar</option>
                </select>
            </div>
            <div>
                <label for="kategori_id" class="block text-gray-700 text-sm font-bold mb-2">Kategori:</label>
                <select id="kategori_id" name="kategori_id"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500" required>
                    <option value="">Pilih Kategori</option>
                    <?php foreach ($all_kategori as $kategori): ?>
                        <option value="<?php echo htmlspecialchars($kategori['id']); ?>"
                                <?php echo (isset($edit_transaction['kategori_id']) && $edit_transaction['kategori_id'] == $kategori['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($kategori['nama_kategori']); ?> (<?php echo htmlspecialchars(ucfirst($kategori['tipe_kategori'])); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="unit_id" class="block text-gray-700 text-sm font-bold mb-2">Unit Kas:</label>
                <select id="unit_id" name="unit_id"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500" required>
                    <option value="">Pilih Unit</option>
                    <?php foreach ($all_unit as $unit): ?>
                        <option value="<?php echo htmlspecialchars($unit['id']); ?>"
                                <?php echo (isset($edit_transaction['unit_id']) && $edit_transaction['unit_id'] == $unit['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($unit['nama_unit']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-center space-x-4">
                <button type="submit"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                    <?php echo $edit_transaction ? 'Perbarui Transaksi' : 'Tambah Transaksi'; ?>
                </button>
                <?php if ($edit_transaction): ?>
                    <a href="<?php echo ROOT_URL; ?>admin/kas_manajemen.php"
                       class="inline-block bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                        Batal
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <hr class="my-8">

    <!-- Filter Transaksi -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8 border border-gray-200">
        <h2 class="text-xl font-semibold mb-4 text-gray-800">Filter Transaksi</h2>
        <form action="" method="GET" class="space-y-4">
            <div>
                <label for="search" class="block text-gray-700 text-sm font-bold mb-2">Cari:</label>
                <input type="text" id="search" name="search"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500"
                       value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>" placeholder="Deskripsi, kategori, unit">
            </div>
            <div>
                <label for="kategori_id_filter" class="block text-gray-700 text-sm font-bold mb-2">Kategori:</label>
                <select id="kategori_id_filter" name="kategori_id"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($all_kategori as $kategori): ?>
                        <option value="<?php echo htmlspecialchars($kategori['id']); ?>"
                                <?php echo (isset($filters['kategori_id']) && $filters['kategori_id'] == $kategori['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($kategori['nama_kategori']); ?> (<?php echo htmlspecialchars(ucfirst($kategori['tipe_kategori'])); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="unit_id_filter" class="block text-gray-700 text-sm font-bold mb-2">Unit Kas:</label>
                <select id="unit_id_filter" name="unit_id"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                    <option value="">Semua Unit</option>
                    <?php foreach ($all_unit as $unit): ?>
                        <option value="<?php echo htmlspecialchars($unit['id']); ?>"
                                <?php echo (isset($filters['unit_id']) && $filters['unit_id'] == $unit['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($unit['nama_unit']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="tipe_transaksi_filter" class="block text-gray-700 text-sm font-bold mb-2">Tipe Transaksi:</label>
                <select id="tipe_transaksi_filter" name="tipe_transaksi"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                    <option value="">Semua Tipe</option>
                    <option value="masuk" <?php echo (isset($filters['tipe_transaksi']) && $filters['tipe_transaksi'] == 'masuk') ? 'selected' : ''; ?>>Masuk</option>
                    <option value="keluar" <?php echo (isset($filters['tipe_transaksi']) && $filters['tipe_transaksi'] == 'keluar') ? 'selected' : ''; ?>>Keluar</option>
                </select>
            </div>
            <div>
                <label for="start_date" class="block text-gray-700 text-sm font-bold mb-2">Dari Tanggal:</label>
                <input type="date" id="start_date" name="start_date"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500"
                       value="<?php echo htmlspecialchars($filters['start_date'] ?? ''); ?>">
            </div>
            <div>
                <label for="end_date" class="block text-gray-700 text-sm font-bold mb-2">Sampai Tanggal:</label>
                <input type="date" id="end_date" name="end_date"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500"
                       value="<?php echo htmlspecialchars($filters['end_date'] ?? ''); ?>">
            </div>
            <div class="flex items-center space-x-4">
                <button type="submit"
                        class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                    Terapkan Filter
                </button>
                <a href="<?php echo ROOT_URL; ?>admin/kas_manajemen.php"
                   class="inline-block bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                    Reset Filter
                </a>
            </div>
        </form>
    </div>

    <hr class="my-8">

    <!-- Tabel Transaksi -->
    <h2 class="text-2xl font-bold text-gray-800 mb-4 mt-8">Daftar Transaksi Kas</h2>
    <?php if (empty($transactions)): ?>
        <p class="text-gray-600 text-center py-4">Tidak ada transaksi kas yang ditemukan.</p>
    <?php else: ?>
        <div class="overflow-x-auto bg-white rounded-lg shadow-md border border-gray-200">
            <table class="min-w-full leading-normal">
                <thead>
                    <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                        <th class="py-3 px-6 text-left">Tanggal</th>
                        <th class="py-3 px-6 text-left">Deskripsi</th>
                        <th class="py-3 px-6 text-right">Jumlah (Rp)</th>
                        <th class="py-3 px-6 text-center">Tipe</th>
                        <th class="py-3 px-6 text-left">Kategori</th>
                        <th class="py-3 px-6 text-left">Unit</th>
                        <th class="py-3 px-6 text-left">Oleh</th>
                        <th class="py-3 px-6 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700 text-sm">
                    <?php foreach ($transactions as $transaksi): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars(format_date_indonesian($transaksi['tanggal_transaksi'])); ?></td>
                            <td class="py-3 px-6"><?php echo htmlspecialchars($transaksi['deskripsi']); ?></td>
                            <td class="py-3 px-6 text-right">
                                <?php
                                    $color_class = ($transaksi['tipe_transaksi'] == 'masuk') ? 'text-green-600' : 'text-red-600';
                                    echo "<span class='" . $color_class . "'>Rp " . number_format($transaksi['jumlah'], 2, ',', '.') . "</span>";
                                ?>
                            </td>
                            <td class="py-3 px-6 text-center">
                                <span class="px-2 py-1 font-semibold leading-tight rounded-full 
                                    <?php echo ($transaksi['tipe_transaksi'] == 'masuk') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo htmlspecialchars(ucfirst($transaksi['tipe_transaksi'])); ?>
                                </span>
                            </td>
                            <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars($transaksi['nama_kategori']); ?></td>
                            <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars($transaksi['nama_unit']); ?></td>
                            <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars($transaksi['recorded_by_user'] ?? 'Sistem'); ?></td>
                            <td class="py-3 px-6 text-center whitespace-nowrap">
                                <a href="<?php echo ROOT_URL; ?>admin/kas_manajemen.php?action=edit_form&id=<?php echo htmlspecialchars($transaksi['id']); ?>"
                                   class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-1 px-3 rounded-lg text-xs">Edit</a>
                                <a href="#" onclick="confirmDelete(<?php echo htmlspecialchars($transaksi['id']); ?>)"
                                   class="bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-3 rounded-lg text-xs ml-1">Hapus</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="flex justify-between items-center mt-4">
            <div class="text-sm text-gray-600">
                Menampilkan <?php echo min($limit, $total_transactions - $offset); ?> dari <?php echo $total_transactions; ?> transaksi
            </div>
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                       class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        <span class="sr-only">Previous</span>
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                       class="<?php echo ($i == $page) ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?> relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                       class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        <span class="sr-only">Next</span>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    <?php endif; ?>
</div>

<script>
    function confirmDelete(id) {
        if (confirm('Apakah Anda yakin ingin menghapus transaksi ini?')) {
            window.location.href = `<?php echo ROOT_URL; ?>admin/kas_manajemen.php?action=delete&id=${id}`;
        }
    }
</script>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
