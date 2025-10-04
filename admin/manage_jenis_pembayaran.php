<?php
// project-root/admin/manage_jenis_pembayaran.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/JenisPembayaran.php';
require_once __DIR__ . '/../classes/KasUnit.php'; // Penting: Include KasUnit

// Memastikan pengguna sudah login dan memiliki peran Admin atau Tata Usaha
require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$jenis_pembayaran_model = null;
$kas_unit_model = null; // Deklarasi model KasUnit
$pdo = null;

$all_jenis_pembayaran = [];
$edit_jenis_pembayaran = null;
$all_kas_unit = []; // Untuk menyimpan daftar unit kas

// Opsi untuk tipe pembayaran
$tipe_pembayaran_options = ['Rutin Bulanan', 'Bebas'];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $jenis_pembayaran_model = new JenisPembayaran($pdo);
    $kas_unit_model = new KasUnit($pdo); // Inisialisasi KasUnit model

    $all_kas_unit = $kas_unit_model->getAll(); // Ambil semua unit kas

    // Handle form submission (add/edit)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            $nama_pembayaran = filter_input(INPUT_POST, 'nama_pembayaran', FILTER_SANITIZE_STRING);
            $unit_id = filter_input(INPUT_POST, 'unit_id', FILTER_VALIDATE_INT); // Ambil unit_id
            $deskripsi = filter_input(INPUT_POST, 'deskripsi', FILTER_SANITIZE_STRING);
            $tipe_pembayaran = filter_input(INPUT_POST, 'tipe_pembayaran', FILTER_SANITIZE_STRING);
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            // Validasi sederhana
            if (empty($nama_pembayaran) || empty($tipe_pembayaran)) {
                set_flash('error', 'Nama Pembayaran dan Tipe Pembayaran wajib diisi.');
                redirect(ROOT_URL . 'admin/manage_jenis_pembayaran.php');
            }

            // Jika unit_id adalah 0 atau null dari form, setel ke null untuk database
            if ($unit_id === 0 || $unit_id === null) {
                $unit_id = null;
            }

            if ($_POST['action'] === 'add') {
                // Panggil metode create dengan parameter yang benar
                if ($jenis_pembayaran_model->create($nama_pembayaran, $unit_id, $deskripsi, $tipe_pembayaran, $is_active)) {
                    set_flash('success', 'Jenis pembayaran berhasil ditambahkan.');
                } else {
                    set_flash('error', 'Gagal menambahkan jenis pembayaran.');
                }
            } elseif ($_POST['action'] === 'edit') {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                if ($id) {
                    // Panggil metode update dengan parameter yang benar
                    if ($jenis_pembayaran_model->update($id, $nama_pembayaran, $unit_id, $deskripsi, $tipe_pembayaran, $is_active)) {
                        set_flash('success', 'Jenis pembayaran berhasil diperbarui.');
                    } else {
                        set_flash('error', 'Gagal memperbarui jenis pembayaran.');
                    }
                } else {
                    set_flash('error', 'ID Jenis Pembayaran tidak valid untuk diperbarui.');
                }
            }
        }
        redirect(ROOT_URL . 'admin/manage_jenis_pembayaran.php');
    }

    // Handle delete request
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $id_to_delete = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if ($id_to_delete && $jenis_pembayaran_model->delete($id_to_delete)) {
            set_flash('success', 'Jenis pembayaran berhasil dihapus.');
        } else {
            set_flash('error', 'Gagal menghapus jenis pembayaran. Mungkin ada tagihan yang terkait.');
        }
        redirect(ROOT_URL . 'admin/manage_jenis_pembayaran.php');
    }

    // Handle edit request (untuk menampilkan form dengan data yang sudah ada)
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $edit_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if ($edit_id) {
            $edit_jenis_pembayaran = $jenis_pembayaran_model->getById($edit_id);
            if (!$edit_jenis_pembayaran) {
                set_flash('error', 'Jenis pembayaran tidak ditemukan.');
                redirect(ROOT_URL . 'admin/manage_jenis_pembayaran.php');
            }
        }
    }

    // Ambil semua jenis pembayaran untuk ditampilkan
    $all_jenis_pembayaran = $jenis_pembayaran_model->getAll();

} catch (PDOException $e) {
    error_log("Manage Jenis Pembayaran Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
    // Inisialisasi ulang $all_kas_unit jika terjadi error agar tidak kosong di tampilan
    $all_kas_unit = $kas_unit_model->getAll();
} catch (Exception $e) {
    error_log("Manage Jenis Pembayaran General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
    // Inisialisasi ulang $all_kas_unit jika terjadi error agar tidak kosong di tampilan
    $all_kas_unit = $kas_unit_model->getAll();
}

require_once __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manajemen Jenis Pembayaran</h1>

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

    <div class="bg-white p-6 rounded-lg shadow-md mb-8 border border-gray-200">
        <h2 class="text-xl font-semibold mb-4 text-gray-800">
            <?php echo $edit_jenis_pembayaran ? 'Edit Jenis Pembayaran' : 'Tambah Jenis Pembayaran Baru'; ?>
        </h2>
        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="<?php echo $edit_jenis_pembayaran ? 'edit' : 'add'; ?>">
            <?php if ($edit_jenis_pembayaran): ?>
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_jenis_pembayaran['id']); ?>">
            <?php endif; ?>
            
            <div>
                <label for="nama_pembayaran" class="block text-gray-700 text-sm font-bold mb-2">Nama Pembayaran:</label>
                <input type="text" id="nama_pembayaran" name="nama_pembayaran" value="<?php echo htmlspecialchars($edit_jenis_pembayaran['nama_pembayaran'] ?? ''); ?>"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500"
                       placeholder="Contoh: SPP Bulanan, Uang Gedung, LKS Semester Ganjil" required>
            </div>
            <div>
                <label for="unit_id" class="block text-gray-700 text-sm font-bold mb-2">Unit Kas Terkait:</label>
                <select id="unit_id" name="unit_id"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                    <option value="">-- Pilih Unit Kas (Opsional) --</option>
                    <?php foreach ($all_kas_unit as $unit): ?>
                        <option value="<?php echo htmlspecialchars($unit['id']); ?>"
                                <?php echo (isset($edit_jenis_pembayaran['unit_id']) && $edit_jenis_pembayaran['unit_id'] == $unit['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($unit['nama_unit']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="deskripsi" class="block text-gray-700 text-sm font-bold mb-2">Deskripsi (Opsional):</label>
                <textarea id="deskripsi" name="deskripsi" rows="3"
                          class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500"
                          placeholder="Deskripsi singkat jenis pembayaran"><?php echo htmlspecialchars($edit_jenis_pembayaran['deskripsi'] ?? ''); ?></textarea>
            </div>
            
            <div>
                <label for="tipe_pembayaran" class="block text-gray-700 text-sm font-bold mb-2">Tipe Pembayaran:</label>
                <select id="tipe_pembayaran" name="tipe_pembayaran"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500" required>
                    <option value="">Pilih Tipe Pembayaran</option>
                    <?php foreach ($tipe_pembayaran_options as $tipe): ?>
                        <option value="<?php echo htmlspecialchars($tipe); ?>"
                                <?php echo (isset($edit_jenis_pembayaran['tipe_pembayaran']) && $edit_jenis_pembayaran['tipe_pembayaran'] == $tipe) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tipe); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-center">
                <input type="checkbox" id="is_active" name="is_active" class="mr-2 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                       <?php echo (isset($edit_jenis_pembayaran['is_active']) && $edit_jenis_pembayaran['is_active'] == 1) ? 'checked' : ''; ?>>
                <label for="is_active" class="text-gray-700 text-sm font-bold">Aktif</label>
            </div>
            <div class="flex items-center space-x-4">
                <button type="submit"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                    <?php echo $edit_jenis_pembayaran ? 'Perbarui Jenis Pembayaran' : 'Tambah Jenis Pembayaran'; ?>
                </button>
                <?php if ($edit_jenis_pembayaran): ?>
                    <a href="<?php echo ROOT_URL; ?>admin/manage_jenis_pembayaran.php"
                       class="inline-block align-baseline font-bold text-sm text-red-500 hover:text-red-800">
                        Batal Edit
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200">
        <h2 class="text-xl font-semibold mb-4 text-gray-800">Daftar Jenis Pembayaran</h2>
        <?php if (empty($all_jenis_pembayaran)): ?>
            <p class="text-gray-600">Belum ada jenis pembayaran yang ditambahkan.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                    <thead>
                        <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">Nama Pembayaran</th>
                            <th class="py-3 px-6 text-left">Unit Kas</th> <!-- Tambah kolom Unit Kas -->
                            <th class="py-3 px-6 text-left">Deskripsi</th>
                            <th class="py-3 px-6 text-center">Tipe</th>
                            <th class="py-3 px-6 text-center">Status</th>
                            <th class="py-3 px-6 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm">
                        <?php foreach ($all_jenis_pembayaran as $jp): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars($jp['nama_pembayaran']); ?></td>
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars($jp['nama_unit'] ?? '-'); ?></td> <!-- Tampilkan nama unit -->
                                <td class="py-3 px-6"><?php echo htmlspecialchars(substr($jp['deskripsi'] ?? '-', 0, 75)); ?><?php echo (strlen($jp['deskripsi'] ?? '') > 75) ? '...' : ''; ?></td>
                                <td class="py-3 px-6 text-center">
                                    <span class="px-2 py-1 font-semibold leading-tight rounded-full 
                                        <?php echo ($jp['tipe_pembayaran'] == 'Rutin Bulanan') ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'; ?>">
                                        <?php echo htmlspecialchars($jp['tipe_pembayaran']); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-6 text-center">
                                    <?php if ($jp['is_active']): ?>
                                        <span class="bg-green-100 text-green-800 py-1 px-3 rounded-full text-xs">Aktif</span>
                                    <?php else: ?>
                                        <span class="bg-red-100 text-red-800 py-1 px-3 rounded-full text-xs">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-6 text-center whitespace-nowrap">
                                    <div class="flex item-center justify-center space-x-2">
                                        <a href="<?php echo ROOT_URL; ?>admin/manage_jenis_pembayaran.php?action=edit&id=<?php echo htmlspecialchars($jp['id']); ?>"
                                           class="w-8 h-8 rounded-full bg-yellow-200 text-yellow-800 flex items-center justify-center hover:bg-yellow-300 transition duration-300" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="<?php echo ROOT_URL; ?>admin/manage_jenis_pembayaran.php?action=delete&id=<?php echo htmlspecialchars($jp['id']); ?>"
                                           onclick="return confirm('Apakah Anda yakin ingin menghapus jenis pembayaran ini? Ini akan mempengaruhi tagihan yang terkait!');"
                                           class="w-8 h-8 rounded-full bg-red-200 text-red-800 flex items-center justify-center hover:bg-red-300 transition duration-300" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
