<?php
// project-root/admin/data_kitab_nadhom.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/KitabNadhom.php';

require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$kitab_nadhom_model = null;
$pdo = null;
$kitab_nadhom_data = [];
$edit_kitab = null;

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $kitab_nadhom_model = new KitabNadhom($pdo);

    // Handle form submission (add/edit)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
                $kitab_nadhom_model->nama_kitab = $_POST['nama_kitab'];
                $kitab_nadhom_model->keterangan = $_POST['keterangan'];
                // Tangani input baru
                $kitab_nadhom_model->jenis_kitab = $_POST['jenis_kitab'];
                $kitab_nadhom_model->target_hafalan = ($kitab_nadhom_model->jenis_kitab === 'Hafalan') ? $_POST['target_hafalan'] : null;

                if ($_POST['action'] === 'add') {
                    if ($kitab_nadhom_model->create()) {
                        set_flash('success', 'Kitab/Nadhom berhasil ditambahkan.');
                    } else {
                        set_flash('error', 'Gagal menambahkan Kitab/Nadhom.');
                    }
                } elseif ($_POST['action'] === 'edit') {
                    $kitab_nadhom_model->id = $_POST['id'];
                    if ($kitab_nadhom_model->update()) {
                        set_flash('success', 'Kitab/Nadhom berhasil diperbarui.');
                    } else {
                        set_flash('error', 'Gagal memperbarui Kitab/Nadhom.');
                    }
                }
            }
        }
        header('Location: ' . ROOT_URL . 'admin/data_kitab_nadhom.php');
        exit;
    }

    // Handle delete request
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        if ($kitab_nadhom_model->delete($_GET['id'])) {
            set_flash('success', 'Kitab/Nadhom berhasil dihapus.');
        } else {
            set_flash('error', 'Gagal menghapus Kitab/Nadhom.');
        }
        header('Location: ' . ROOT_URL . 'admin/data_kitab_nadhom.php');
        exit;
    }

    // Handle edit request
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $edit_kitab = $kitab_nadhom_model->getById($_GET['id']);
        if (!$edit_kitab) {
            set_flash('error', 'Kitab/Nadhom tidak ditemukan.');
            header('Location: ' . ROOT_URL . 'admin/data_kitab_nadhom.php');
            exit;
        }
    }

    $kitab_nadhom_data = $kitab_nadhom_model->getAll();

} catch (PDOException $e) {
    error_log("Data Kitab/Nadhom Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Data Kitab/Nadhom General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
}

require_once __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manajemen Data Kitab/Nadhom</h1>

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
        <h2 class="text-xl font-semibold mb-4"><?php echo $edit_kitab ? 'Edit Kitab/Nadhom' : 'Tambah Kitab/Nadhom Baru'; ?></h2>
        <form action="" method="POST" class="space-y-4">
            <?php if ($edit_kitab): ?>
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_kitab['id']); ?>">
                <input type="hidden" name="action" value="edit">
            <?php else: ?>
                <input type="hidden" name="action" value="add">
            <?php endif; ?>
            
            <div>
                <label for="nama_kitab" class="block text-gray-700 text-sm font-bold mb-2">Nama Kitab/Nadhom:</label>
                <input type="text" id="nama_kitab" name="nama_kitab" value="<?php echo htmlspecialchars($edit_kitab['nama_kitab'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div>
                <label for="keterangan" class="block text-gray-700 text-sm font-bold mb-2">Keterangan:</label>
                <textarea id="keterangan" name="keterangan" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?php echo htmlspecialchars($edit_kitab['keterangan'] ?? ''); ?></textarea>
            </div>
            
            <div>
                <label for="jenis_kitab" class="block text-gray-700 text-sm font-bold mb-2">Jenis Kitab:</label>
                <select id="jenis_kitab" name="jenis_kitab" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <option value="Sorogan" <?php echo (isset($edit_kitab['jenis_kitab']) && $edit_kitab['jenis_kitab'] === 'Sorogan') ? 'selected' : ''; ?>>Sorogan</option>
                    <option value="Hafalan" <?php echo (isset($edit_kitab['jenis_kitab']) && $edit_kitab['jenis_kitab'] === 'Hafalan') ? 'selected' : ''; ?>>Hafalan</option>
                </select>
            </div>

            <div id="target_hafalan_container" class="hidden">
                <label for="target_hafalan" class="block text-gray-700 text-sm font-bold mb-2">Target Hafalan (Contoh: Jumlah Bait):</label>
                <input type="number" min="1" id="target_hafalan" name="target_hafalan" value="<?php echo htmlspecialchars($edit_kitab['target_hafalan'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>

            <div class="flex items-center space-x-4">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                    <?php echo $edit_kitab ? 'Perbarui Kitab/Nadhom' : 'Tambah Kitab/Nadhom'; ?>
                </button>
                <?php if ($edit_kitab): ?>
                    <a href="<?php echo ROOT_URL; ?>admin/data_kitab_nadhom.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                        Batal
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold mb-4">Daftar Kitab/Nadhom</h2>
        <?php if (empty($kitab_nadhom_data)): ?>
            <p class="text-gray-600">Tidak ada data Kitab/Nadhom.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                    <thead>
                        <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">No.</th>
                            <th class="py-3 px-6 text-left">Nama Kitab/Nadhom</th>
                            <th class="py-3 px-6 text-left">Jenis</th>
                            <th class="py-3 px-6 text-left">Target</th>
                            <th class="py-3 px-6 text-left">Keterangan</th>
                            <th class="py-3 px-6 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm">
                        <?php $no = 1; ?>
                        <?php foreach ($kitab_nadhom_data as $kitab): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($kitab['nama_kitab']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($kitab['jenis_kitab'] ?? '-'); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($kitab['target_hafalan'] ?? '-'); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($kitab['keterangan'] ?? '-'); ?></td>
                                <td class="py-3 px-6 text-center">
                                    <a href="<?php echo ROOT_URL; ?>admin/data_kitab_nadhom.php?action=edit&id=<?php echo htmlspecialchars($kitab['id']); ?>" class="text-blue-600 hover:text-blue-900 mr-3">Edit</a>
                                    <a href="<?php echo ROOT_URL; ?>admin/data_kitab_nadhom.php?action=delete&id=<?php echo htmlspecialchars($kitab['id']); ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Apakah Anda yakin ingin menghapus Kitab/Nadhom ini?');">Hapus</a>
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
    document.addEventListener('DOMContentLoaded', function() {
        const jenisKitabSelect = document.getElementById('jenis_kitab');
        const targetHafalanContainer = document.getElementById('target_hafalan_container');

        function toggleTargetHafalan() {
            if (jenisKitabSelect.value === 'Hafalan') {
                targetHafalanContainer.classList.remove('hidden');
                document.getElementById('target_hafalan').setAttribute('required', 'required');
            } else {
                targetHafalanContainer.classList.add('hidden');
                document.getElementById('target_hafalan').removeAttribute('required');
            }
        }

        jenisKitabSelect.addEventListener('change', toggleTargetHafalan);
        // Panggil saat halaman dimuat untuk setel status awal
        toggleTargetHafalan();
    });
</script>

<?php require_once __DIR__ . '/../components/footer.php'; ?>