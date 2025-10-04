<?php
// project-root/staff/manage_mapel.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/MataPelajaran.php';

// Pastikan pengguna sudah login dan memiliki peran Admin atau Tata Usaha
require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$mapel_model = null;
$pdo = null;
$all_mapel = [];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $mapel_model = new MataPelajaran($pdo);

    // Handle form submission for Add/Edit/Delete
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['action'])) {
            $action = $_POST['action'];

            // Common fields for add/edit (initialize to null for delete action)
            $id = (int)($_POST['id'] ?? 0);
            $nama_mapel = trim($_POST['nama_mapel'] ?? '');
            $kkm = (int)($_POST['kkm'] ?? null);
            if ($kkm === 0 && $_POST['kkm'] !== '0') $kkm = null; // Handle empty/non-numeric KKM

            if ($action == 'add') {
                // Basic validation for add
                if (empty($nama_mapel)) {
                    set_flash('error', 'Nama Mata Pelajaran harus diisi.');
                    redirect(ROOT_URL . 'staff/manage_mapel.php');
                }

                if ($mapel_model->create($nama_mapel, $kkm)) {
                    set_flash('success', 'Data mata pelajaran berhasil ditambahkan.');
                } else {
                    set_flash('error', 'Gagal menambahkan data mata pelajaran.');
                }
            } elseif ($action == 'edit' && $id > 0) {
                // Basic validation for edit
                if (empty($nama_mapel)) {
                    set_flash('error', 'Nama Mata Pelajaran harus diisi.');
                    redirect(ROOT_URL . 'staff/manage_mapel.php');
                }

                if ($mapel_model->update($id, $nama_mapel, $kkm)) {
                    set_flash('success', 'Data mata pelajaran berhasil diperbarui.');
                } else {
                    set_flash('error', 'Gagal memperbarui data mata pelajaran.');
                }
            } elseif ($action == 'delete') { // No need for nama_mapel validation here
                if ($id <= 0) { // Validate ID for delete action
                    set_flash('error', 'ID Mata Pelajaran tidak valid untuk dihapus.');
                    redirect(ROOT_URL . 'staff/manage_mapel.php');
                }
                    if ($mapel_model->deleteWithDependencies($id)) {
                    set_flash('success', 'Data mata pelajaran dan semua data terkait (jadwal, absensi) berhasil dihapus.');
                } else {
                    set_flash('error', 'Gagal menghapus data mata pelajaran. Mungkin ada data lain yang terkait atau terjadi kesalahan sistem.');
                }
            }
            redirect(ROOT_URL . 'staff/manage_mapel.php');
        }
    }

    // Always fetch all mapel after any action
    $all_mapel = $mapel_model->getAll();

} catch (PDOException $e) {
    error_log("Manage Mapel Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
    // No redirect here, let the page load with the error message
} catch (Exception $e) {
    error_log("Manage Mapel General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
    // No redirect here, let the page load with the error message
}

// Sertakan header
require_once __DIR__ . '/../components/header_staff.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manajemen Mata Pelajaran</h1>

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

    <!-- Form Tambah/Edit Mata Pelajaran -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-semibold mb-4" id="form-title">Tambah Mata Pelajaran Baru</h2>
        <form id="mapelForm" action="<?php echo ROOT_URL; ?>staff/manage_mapel.php" method="POST">
            <input type="hidden" name="action" id="form-action" value="add">
            <input type="hidden" name="id" id="mapel-id" value="">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="nama_mapel" class="block text-gray-700 text-sm font-bold mb-2">Nama Mata Pelajaran:</label>
                    <input type="text" id="nama_mapel" name="nama_mapel" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                <div>
                    <label for="kkm" class="block text-gray-700 text-sm font-bold mb-2">KKM (Kriteria Ketuntasan Minimal):</label>
                    <input type="number" id="kkm" name="kkm" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="Opsional (contoh: 75)">
                </div>
            </div>

            <div class="flex items-center justify-between mt-6">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Simpan Mata Pelajaran
                </button>
                <button type="button" id="cancelEdit" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline hidden">
                    Batal Edit
                </button>
            </div>
        </form>
    </div>

    <!-- Daftar Mata Pelajaran -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold mb-4">Daftar Mata Pelajaran</h2>
        <?php if (empty($all_mapel)): ?>
            <p class="text-gray-600">Belum ada data mata pelajaran.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                    <thead>
                        <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">No.</th>
                            <th class="py-3 px-6 text-left">Nama Mata Pelajaran</th>
                            <th class="py-3 px-6 text-left">KKM</th>
                            <th class="py-3 px-6 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm">
                        <?php $no = 1; ?>
                        <?php foreach ($all_mapel as $mapel): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($mapel['nama_mapel']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($mapel['kkm'] ?? '-'); ?></td>
                                <td class="py-3 px-6 text-center whitespace-nowrap">
                                    <button type="button" onclick="editMapel(<?php echo htmlspecialchars(json_encode($mapel)); ?>)" class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-1 px-3 rounded text-xs mr-2">Edit</button>
                                    <form action="<?php echo ROOT_URL; ?>staff/manage_mapel.php" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus mata pelajaran ini? Tindakan ini akan menghapus semua jadwal dan data absensi yang terkait secara permanen!');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($mapel['id']); ?>">
                                        <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-3 rounded text-xs">Hapus</button>
                                    </form>
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
    function editMapel(mapelData) {
        document.getElementById('form-title').textContent = 'Edit Data Mata Pelajaran';
        document.getElementById('form-action').value = 'edit';
        document.getElementById('mapel-id').value = mapelData.id;
        document.getElementById('nama_mapel').value = mapelData.nama_mapel;
        document.getElementById('kkm').value = mapelData.kkm || '';

        document.getElementById('cancelEdit').classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' }); // Scroll to top to see the form
    }

    document.getElementById('cancelEdit').addEventListener('click', function() {
        document.getElementById('form-title').textContent = 'Tambah Mata Pelajaran Baru';
        document.getElementById('form-action').value = 'add';
        document.getElementById('mapel-id').value = '';
        document.getElementById('mapelForm').reset(); // Reset form fields
        this.classList.add('hidden');
    });
</script>

<?php
// Sertakan footer
require_once __DIR__ . '/../components/footer.php';
?>
