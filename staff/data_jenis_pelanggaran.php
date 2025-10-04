<?php
// project-root/admin/data_jenis_pelanggaran.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/JenisPelanggaran.php';

require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$jenis_pelanggaran_model = null;
$pdo = null;
$jenis_pelanggaran_data = [];
$edit_jenis_pelanggaran = null;

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $jenis_pelanggaran_model = new JenisPelanggaran($pdo);

    // Handle form submission (add/edit)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
                $jenis_pelanggaran_model->nama_pelanggaran = $_POST['nama_pelanggaran'];
                $jenis_pelanggaran_model->poin_deduksi = $_POST['poin_deduksi'];
                $jenis_pelanggaran_model->keterangan = $_POST['keterangan'];

                if ($_POST['action'] === 'add') {
                    if ($jenis_pelanggaran_model->create()) {
                        set_flash('success', 'Jenis Pelanggaran berhasil ditambahkan.');
                    } else {
                        set_flash('error', 'Gagal menambahkan Jenis Pelanggaran.');
                    }
                } elseif ($_POST['action'] === 'edit') {
                    $jenis_pelanggaran_model->id = $_POST['id'];
                    if ($jenis_pelanggaran_model->update()) {
                        set_flash('success', 'Jenis Pelanggaran berhasil diperbarui.');
                    } else {
                        set_flash('error', 'Gagal memperbarui Jenis Pelanggaran.');
                    }
                }
            }
        }
        header('Location: ' . ROOT_URL . 'admin/data_jenis_pelanggaran.php');
        exit;
    }

    // Handle delete request
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        if ($jenis_pelanggaran_model->delete($_GET['id'])) {
            set_flash('success', 'Jenis Pelanggaran berhasil dihapus.');
        } else {
            set_flash('error', 'Gagal menghapus Jenis Pelanggaran (Mungkin ada data pelanggaran siswa terkait).');
        }
        header('Location: ' . ROOT_URL . 'admin/data_jenis_pelanggaran.php');
        exit;
    }

    // Handle edit request
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $edit_jenis_pelanggaran = $jenis_pelanggaran_model->getById($_GET['id']);
        if (!$edit_jenis_pelanggaran) {
            set_flash('error', 'Jenis Pelanggaran tidak ditemukan.');
            header('Location: ' . ROOT_URL . 'admin/data_jenis_pelanggaran.php');
            exit;
        }
    }

    $jenis_pelanggaran_data = $jenis_pelanggaran_model->getAll();

} catch (PDOException $e) {
    error_log("Data Jenis Pelanggaran Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Data Jenis Pelanggaran General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
}

require_once __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manajemen Data Jenis Pelanggaran</h1>

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
        <h2 class="text-xl font-semibold mb-4"><?php echo $edit_jenis_pelanggaran ? 'Edit Jenis Pelanggaran' : 'Tambah Jenis Pelanggaran Baru'; ?></h2>
        <form action="" method="POST" class="space-y-4">
            <?php if ($edit_jenis_pelanggaran): ?>
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_jenis_pelanggaran['id']); ?>">
                <input type="hidden" name="action" value="edit">
            <?php else: ?>
                <input type="hidden" name="action" value="add">
            <?php endif; ?>
            
            <div>
                <label for="nama_pelanggaran" class="block text-gray-700 text-sm font-bold mb-2">Nama Pelanggaran:</label>
                <input type="text" id="nama_pelanggaran" name="nama_pelanggaran" value="<?php echo htmlspecialchars($edit_jenis_pelanggaran['nama_pelanggaran'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div>
                <label for="poin_deduksi" class="block text-gray-700 text-sm font-bold mb-2">Poin Deduksi:</label>
                <input type="number" id="poin_deduksi" name="poin_deduksi" value="<?php echo htmlspecialchars($edit_jenis_pelanggaran['poin_deduksi'] ?? 0); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" min="0" required>
            </div>
            <div>
                <label for="keterangan" class="block text-gray-700 text-sm font-bold mb-2">Keterangan:</label>
                <textarea id="keterangan" name="keterangan" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?php echo htmlspecialchars($edit_jenis_pelanggaran['keterangan'] ?? ''); ?></textarea>
            </div>
            <div class="flex items-center space-x-4">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                    <?php echo $edit_jenis_pelanggaran ? 'Perbarui Jenis Pelanggaran' : 'Tambah Jenis Pelanggaran'; ?>
                </button>
                <?php if ($edit_jenis_pelanggaran): ?>
                    <a href="<?php echo ROOT_URL; ?>admin/data_jenis_pelanggaran.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                        Batal
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold mb-4">Daftar Jenis Pelanggaran</h2>
        <?php if (empty($jenis_pelanggaran_data)): ?>
            <p class="text-gray-600">Tidak ada data Jenis Pelanggaran.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                    <thead>
                        <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">No.</th>
                            <th class="py-3 px-6 text-left">Nama Pelanggaran</th>
                            <th class="py-3 px-6 text-center">Poin Deduksi</th>
                            <th class="py-3 px-6 text-left">Keterangan</th>
                            <th class="py-3 px-6 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm">
                        <?php $no = 1; ?>
                        <?php foreach ($jenis_pelanggaran_data as $jp): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($jp['nama_pelanggaran']); ?></td>
                                <td class="py-3 px-6 text-center"><?php echo htmlspecialchars($jp['poin_deduksi']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($jp['keterangan'] ?? '-'); ?></td>
                                <td class="py-3 px-6 text-center">
                                    <a href="<?php echo ROOT_URL; ?>admin/data_jenis_pelanggaran.php?action=edit&id=<?php echo htmlspecialchars($jp['id']); ?>" class="text-blue-600 hover:text-blue-900 mr-3">Edit</a>
                                    <a href="<?php echo ROOT_URL; ?>admin/data_jenis_pelanggaran.php?action=delete&id=<?php echo htmlspecialchars($jp['id']); ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Apakah Anda yakin ingin menghapus Jenis Pelanggaran ini? Ini juga akan menghapus catatan pelanggaran siswa yang terkait!');">Hapus</a>
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
