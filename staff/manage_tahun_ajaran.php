<?php
// project-root/staff/manage_tahun_ajaran.php

require_once __DIR__ . '/../config/database.php'; // Atau constants.php jika koneksi ada di sana
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

// Pastikan pengguna sudah login dan memiliki peran yang sesuai
require_login();
require_role(['Admin', 'TataUsaha']); // Hanya Admin dan Tata Usaha yang bisa akses

$error_message = flash('error');
$success_message = flash('success');

// Asumsi $pdo sudah tersedia secara global dari config/database.php atau constants.php
global $pdo;

// --- Logika CRUD Tahun Ajaran ---

// Handle Tambah/Edit Tahun Ajaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $tahun_ajaran_input = trim($_POST['tahun_ajaran'] ?? '');
    $id = $_POST['id'] ?? null;

    if (empty($tahun_ajaran_input)) {
        set_flash('error', 'Tahun Ajaran tidak boleh kosong.');
    } else {
        try {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO tahun_ajaran (tahun_ajaran, created_at, updated_at) VALUES (?, NOW(), NOW())");
                $stmt->execute([$tahun_ajaran_input]);
                set_flash('success', 'Tahun Ajaran berhasil ditambahkan.');
            } elseif ($action === 'edit' && $id) {
                $stmt = $pdo->prepare("UPDATE tahun_ajaran SET tahun_ajaran = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$tahun_ajaran_input, $id]);
                set_flash('success', 'Tahun Ajaran berhasil diperbarui.');
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') { // Duplicate entry error code
                set_flash('error', 'Tahun Ajaran "' . htmlspecialchars($tahun_ajaran_input) . '" sudah ada.');
            } else {
                set_flash('error', 'Gagal menyimpan Tahun Ajaran: ' . $e->getMessage());
            }
            error_log("Error saving tahun_ajaran: " . $e->getMessage());
        }
    }
    redirect(ROOT_URL . 'staff/manage_tahun_ajaran.php');
}

// Handle Hapus Tahun Ajaran
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id_to_delete = $_GET['delete'];
    try {
        // Pastikan tidak menghapus tahun ajaran yang sedang aktif
        $stmt_check_active = $pdo->prepare("SELECT is_active FROM tahun_ajaran WHERE id = ?");
        $stmt_check_active->execute([$id_to_delete]);
        $is_active = $stmt_check_active->fetchColumn();

        if ($is_active) {
            set_flash('error', 'Tidak dapat menghapus Tahun Ajaran yang sedang aktif. Harap nonaktifkan terlebih dahulu.');
        } else {
            $stmt = $pdo->prepare("DELETE FROM tahun_ajaran WHERE id = ?");
            $stmt->execute([$id_to_delete]);
            set_flash('success', 'Tahun Ajaran berhasil dihapus.');
        }
    } catch (PDOException $e) {
        set_flash('error', 'Gagal menghapus Tahun Ajaran: ' . $e->getMessage());
        error_log("Error deleting tahun_ajaran: " . $e->getMessage());
    }
    redirect(ROOT_URL . 'staff/manage_tahun_ajaran.php');
}

// Handle Set Tahun Ajaran Aktif
if (isset($_GET['set_active']) && is_numeric($_GET['set_active'])) {
    $id_to_activate = $_GET['set_active'];
    try {
        $pdo->beginTransaction();
        // Set semua tahun ajaran menjadi tidak aktif
        $stmt_deactivate_all = $pdo->prepare("UPDATE tahun_ajaran SET is_active = 0");
        $stmt_deactivate_all->execute();

        // Set tahun ajaran yang dipilih menjadi aktif
        $stmt_activate_one = $pdo->prepare("UPDATE tahun_ajaran SET is_active = 1 WHERE id = ?");
        $stmt_activate_one->execute([$id_to_activate]);

        $pdo->commit();
        set_flash('success', 'Tahun Ajaran berhasil diatur sebagai aktif.');
    } catch (PDOException $e) {
        $pdo->rollBack();
        set_flash('error', 'Gagal mengatur Tahun Ajaran aktif: ' . $e->getMessage());
        error_log("Error setting active tahun_ajaran: " . $e->getMessage());
    }
    redirect(ROOT_URL . 'staff/manage_tahun_ajaran.php');
}

// Ambil data tahun ajaran untuk ditampilkan
$tahun_ajarans = [];
try {
    $stmt = $pdo->query("SELECT * FROM tahun_ajaran ORDER BY tahun_ajaran DESC");
    $tahun_ajarans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    set_flash('error', 'Gagal mengambil data Tahun Ajaran: ' . $e->getMessage());
    error_log("Error fetching tahun_ajarans: " . $e->getMessage());
}

// Sertakan header admin
require_once __DIR__ . '/../components/header_staff.php';
?>

        <!-- Konten Manajemen Tahun Ajaran -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
            <h1 class="text-3xl font-semibold text-gray-800 mb-4 sm:mb-0">Manajemen Tahun Ajaran</h1>
        </div>

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

        <!-- Form Tambah/Edit Tahun Ajaran -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8 border border-gray-200">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">
                <?php echo isset($_GET['edit']) ? 'Edit Tahun Ajaran' : 'Tambah Tahun Ajaran Baru'; ?>
            </h2>
            <?php
            $edit_id = $_GET['edit'] ?? null;
            $edit_tahun_ajaran = '';
            if ($edit_id) {
                try {
                    $stmt_edit = $pdo->prepare("SELECT tahun_ajaran FROM tahun_ajaran WHERE id = ?");
                    $stmt_edit->execute([$edit_id]);
                    $edit_data = $stmt_edit->fetch(PDO::FETCH_ASSOC);
                    if ($edit_data) {
                        $edit_tahun_ajaran = htmlspecialchars($edit_data['tahun_ajaran']);
                    }
                } catch (PDOException $e) {
                    error_log("Error fetching tahun_ajaran for edit: " . $e->getMessage());
                }
            }
            ?>
            <form action="<?php echo ROOT_URL; ?>staff/manage_tahun_ajaran.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="<?php echo isset($_GET['edit']) ? 'edit' : 'add'; ?>">
                <?php if (isset($_GET['edit'])): ?>
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_id); ?>">
                <?php endif; ?>

                <div>
                    <label for="tahun_ajaran" class="block text-gray-700 text-sm font-bold mb-2">Tahun Ajaran:</label>
                    <input type="text" id="tahun_ajaran" name="tahun_ajaran" value="<?php echo $edit_tahun_ajaran; ?>"
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500"
                           placeholder="Contoh: 2023/2024" required>
                </div>
                <div class="flex items-center justify-between">
                    <button type="submit"
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                        <?php echo isset($_GET['edit']) ? 'Perbarui Tahun Ajaran' : 'Tambah Tahun Ajaran'; ?>
                    </button>
                    <?php if (isset($_GET['edit'])): ?>
                        <a href="<?php echo ROOT_URL; ?>staff/manage_tahun_ajaran.php"
                           class="inline-block align-baseline font-bold text-sm text-red-500 hover:text-red-800">
                            Batal Edit
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tabel Daftar Tahun Ajaran -->
        <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Daftar Tahun Ajaran</h2>
            <?php if (empty($tahun_ajarans)): ?>
                <p class="text-gray-600">Belum ada data tahun ajaran.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                                <th class="py-3 px-6 text-left">Tahun Ajaran</th>
                                <th class="py-3 px-6 text-left">Status</th>
                                <th class="py-3 px-6 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 text-sm">
                            <?php foreach ($tahun_ajarans as $ta): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                    <td class="py-3 px-6 text-left whitespace-nowrap">
                                        <?php echo htmlspecialchars($ta['tahun_ajaran']); ?>
                                    </td>
                                    <td class="py-3 px-6 text-left">
                                        <?php if ($ta['is_active']): ?>
                                            <span class="bg-green-200 text-green-800 py-1 px-3 rounded-full text-xs">Aktif</span>
                                        <?php else: ?>
                                            <span class="bg-gray-200 text-gray-800 py-1 px-3 rounded-full text-xs">Tidak Aktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-6 text-center">
                                        <div class="flex item-center justify-center space-x-2">
                                            <a href="<?php echo ROOT_URL; ?>staff/manage_tahun_ajaran.php?edit=<?php echo $ta['id']; ?>"
                                               class="w-8 h-8 rounded-full bg-yellow-200 text-yellow-800 flex items-center justify-center hover:bg-yellow-300 transition duration-300" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if (!$ta['is_active']): ?>
                                                <a href="<?php echo ROOT_URL; ?>staff/manage_tahun_ajaran.php?set_active=<?php echo $ta['id']; ?>"
                                                   class="w-8 h-8 rounded-full bg-blue-200 text-blue-800 flex items-center justify-center hover:bg-blue-300 transition duration-300" title="Set Aktif">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="<?php echo ROOT_URL; ?>staff/manage_tahun_ajaran.php?delete=<?php echo $ta['id']; ?>"
                                                   onclick="return confirm('Apakah Anda yakin ingin menghapus tahun ajaran ini?');"
                                                   class="w-8 h-8 rounded-full bg-red-200 text-red-800 flex items-center justify-center hover:bg-red-300 transition duration-300" title="Hapus">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

<?php
// Sertakan footer
require_once __DIR__ . '/../components/footer.php';
?>
