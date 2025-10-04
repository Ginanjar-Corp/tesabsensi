<?php
// project-root/staff/manage_kelas.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Kelas.php';

// Pastikan pengguna sudah login dan memiliki peran Admin atau Tata Usaha
require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$kelas_model = null;
$pdo = null;
$all_kelas = [];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $kelas_model = new Kelas($pdo);

    // Handle form submission for Add/Edit/Delete
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['action'])) {
            $action = $_POST['action'];

            // Common fields for add/edit (initialize to null for delete action)
            $id = (int)($_POST['id'] ?? 0);
            $nama_kelas = trim($_POST['nama_kelas'] ?? '');
            $tingkat = trim($_POST['tingkat'] ?? null);
            $jurusan = trim($_POST['jurusan'] ?? null);

            if ($action == 'add') {
                // Basic validation for add
                if (empty($nama_kelas)) {
                    set_flash('error', 'Nama Kelas harus diisi.');
                    redirect(ROOT_URL . 'staff/manage_kelas.php');
                }

                if ($kelas_model->create($nama_kelas, $tingkat, $jurusan)) {
                    set_flash('success', 'Data kelas berhasil ditambahkan.');
                } else {
                    set_flash('error', 'Gagal menambahkan data kelas.');
                }
            } elseif ($action == 'edit' && $id > 0) {
                // Basic validation for edit
                if (empty($nama_kelas)) {
                    set_flash('error', 'Nama Kelas harus diisi.');
                    redirect(ROOT_URL . 'staff/manage_kelas.php');
                }

                if ($kelas_model->update($id, $nama_kelas, $tingkat, $jurusan)) {
                    set_flash('success', 'Data kelas berhasil diperbarui.');
                } else {
                    set_flash('error', 'Gagal memperbarui data kelas.');
                }
            } elseif ($action == 'delete') { // No need for nama_kelas validation here
                if ($id <= 0) { // Validate ID for delete action
                    set_flash('error', 'ID Kelas tidak valid untuk dihapus.');
                    redirect(ROOT_URL . 'staff/manage_kelas.php');
                }
                
                // Perhatian: Menghapus kelas akan berdampak pada siswa yang terkait.
                // Disarankan untuk memindahkan siswa terlebih dahulu atau menggunakan soft delete.
                // Anda mungkin ingin menambahkan logika untuk memeriksa keterkaitan siswa di sini.
                // Contoh:
                // $siswa_terkait = $siswa_model->countSiswaByKelas($id); // Asumsi ada metode ini di Siswa.php
                // if ($siswa_terkait > 0) {
                //     set_flash('error', 'Tidak dapat menghapus kelas karena masih ada ' . $siswa_terkait . ' siswa yang terkait.');
                // } else {
                    if ($kelas_model->delete($id)) {
                        set_flash('success', 'Data kelas berhasil dihapus.');
                    } else {
                        set_flash('error', 'Gagal menghapus data kelas. Mungkin ada data lain yang terkait.');
                    }
                // }
            }
            redirect(ROOT_URL . 'staff/manage_kelas.php');
        }
    }

    // Always fetch all classes after any action
    $all_kelas = $kelas_model->getAll();

} catch (PDOException $e) {
    error_log("Manage Kelas Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
    // No redirect here, let the page load with the error message
} catch (Exception $e) {
    error_log("Manage Kelas General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
    // No redirect here, let the page load with the error message
}

// Sertakan header
require_once __DIR__ . '/../components/header_staff.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manajemen Kelas</h1>

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

    <!-- Form Tambah/Edit Kelas -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-semibold mb-4" id="form-title">Tambah Kelas Baru</h2>
        <form id="kelasForm" action="<?php echo ROOT_URL; ?>staff/manage_kelas.php" method="POST">
            <input type="hidden" name="action" id="form-action" value="add">
            <input type="hidden" name="id" id="kelas-id" value="">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="nama_kelas" class="block text-gray-700 text-sm font-bold mb-2">Nama Kelas:</label>
                    <input type="text" id="nama_kelas" name="nama_kelas" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                <div>
                    <label for="tingkat" class="block text-gray-700 text-sm font-bold mb-2">Tingkat (contoh: X, XI, XII):</label>
                    <input type="text" id="tingkat" name="tingkat" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="md:col-span-2">
                    <label for="jurusan" class="block text-gray-700 text-sm font-bold mb-2">Jurusan (contoh: IPA, IPS, Bahasa):</label>
                    <input type="text" id="jurusan" name="jurusan" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
            </div>

            <div class="flex items-center justify-between mt-6">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Simpan Kelas
                </button>
                <button type="button" id="cancelEdit" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline hidden">
                    Batal Edit
                </button>
            </div>
        </form>
    </div>

    <!-- Daftar Kelas -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold mb-4">Daftar Kelas</h2>
        <?php if (empty($all_kelas)): ?>
            <p class="text-gray-600">Belum ada data kelas.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                    <thead>
                        <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">No.</th>
                            <th class="py-3 px-6 text-left">ID Kelas</th>
                            <th class="py-3 px-6 text-left">Nama Kelas</th>
                            <th class="py-3 px-6 text-left">Tingkat</th>
                            <th class="py-3 px-6 text-left">Jurusan</th>
                            <th class="py-3 px-6 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm">
                        <?php $no = 1; ?>
                        <?php foreach ($all_kelas as $kelas): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($kelas['id']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($kelas['nama_kelas']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($kelas['tingkat'] ?? '-'); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($kelas['jurusan'] ?? '-'); ?></td>
                                <td class="py-3 px-6 text-center whitespace-nowrap">
                                    <button type="button" onclick="editKelas(<?php echo htmlspecialchars(json_encode($kelas)); ?>)" class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-1 px-3 rounded text-xs mr-2">Edit</button>
                                    <form action="<?php echo ROOT_URL; ?>staff/manage_kelas.php" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kelas ini? Menghapus kelas dapat berdampak pada data siswa yang terkait.!');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($kelas['id']); ?>">
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
    function editKelas(kelasData) {
        document.getElementById('form-title').textContent = 'Edit Data Kelas';
        document.getElementById('form-action').value = 'edit';
        document.getElementById('kelas-id').value = kelasData.id;
        document.getElementById('nama_kelas').value = kelasData.nama_kelas;
        document.getElementById('tingkat').value = kelasData.tingkat || '';
        document.getElementById('jurusan').value = kelasData.jurusan || '';

        document.getElementById('cancelEdit').classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' }); // Scroll to top to see the form
    }

    document.getElementById('cancelEdit').addEventListener('click', function() {
        document.getElementById('form-title').textContent = 'Tambah Kelas Baru';
        document.getElementById('form-action').value = 'add';
        document.getElementById('kelas-id').value = '';
        document.getElementById('kelasForm').reset(); // Reset form fields
        this.classList.add('hidden');
    });
</script>

<?php
// Sertakan footer
require_once __DIR__ . '/../components/footer.php';
?>
