<?php
// project-root/admin/manage_ngaji_siswa.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/NgajiSiswa.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/Guru.php';
require_once __DIR__ . '/../classes/Kelas.php';
require_once __DIR__ . '/../classes/TahunAjaran.php'; // Tambahkan ini

require_login();
require_role(['Admin', 'TataUsaha', 'Guru']);

$error_message = flash('error');
$success_message = flash('success');

$ngaji_model = null;
$siswa_model = null;
$guru_model = null;
$kelas_model = null;
$tahun_ajaran_model = null; // Tambahkan
$pdo = null;

$ngaji_data = [];
$all_siswa = [];
$all_guru = [];
$all_kelas = [];
$all_tahun_ajaran = []; // Tambahkan
$edit_ngaji = null;

// Filter variables
$filter_siswa_id = isset($_GET['filter_siswa_id']) ? (int)$_GET['filter_siswa_id'] : null;
$filter_guru_id = isset($_GET['filter_guru_id']) ? (int)$_GET['filter_guru_id'] : null;
$filter_kelas_id = isset($_GET['filter_kelas_id']) ? (int)$_GET['filter_kelas_id'] : null;
$filter_tahun_ajaran_id = isset($_GET['filter_tahun_ajaran_id']) ? (int)$_GET['filter_tahun_ajaran_id'] : null; // Tambahkan filter tahun ajaran

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $ngaji_model = new NgajiSiswa($pdo);
    $siswa_model = new Siswa($pdo);
    $guru_model = new Guru($pdo);
    $kelas_model = new Kelas($pdo);
    $tahun_ajaran_model = new TahunAjaran($pdo); // Inisialisasi model TahunAjaran

    // Dapatkan data untuk dropdown form dengan metode getAll()
    $all_siswa = $siswa_model->getAll();
    $all_guru = $guru_model->getAll();
    $all_kelas = $kelas_model->getAll();
    $all_tahun_ajaran = $tahun_ajaran_model->getAll(); // Ambil semua tahun ajaran

    // Handle form submission (add/edit)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            $data = [
                'siswa_id' => $_POST['siswa_id'],
                // tahun_ajaran_id akan diisi otomatis di kelas NgajiSiswa::create() atau update()
                'guru_id' => $_POST['guru_id'],
                'tanggal_setor' => $_POST['tanggal_setor'],
                'jenis_materi' => $_POST['jenis_materi'],
                'halaman_dari' => $_POST['halaman_dari'],
                'halaman_sampai' => $_POST['halaman_sampai'],
                'catatan_guru' => $_POST['catatan_guru']
            ];

            if ($_POST['action'] === 'add') {
                if ($ngaji_model->create($data)) {
                    set_flash('success', 'Catatan ngaji berhasil ditambahkan!');
                } else {
                    set_flash('error', 'Gagal menambahkan catatan ngaji.');
                }
            } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
                $id = $_POST['id'];
                if ($ngaji_model->update($id, $data)) {
                    set_flash('success', 'Catatan ngaji berhasil diperbarui!');
                } else {
                    set_flash('error', 'Gagal memperbarui catatan ngaji.');
                }
            }
        }
        header('Location: ' . ROOT_URL . 'admin/manage_ngaji_siswa.php');
        exit;
    }

    // Handle delete action
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $id = $_GET['id'];
        if ($ngaji_model->delete($id)) {
            set_flash('success', 'Catatan ngaji berhasil dihapus.');
        } else {
            set_flash('error', 'Gagal menghapus catatan ngaji.');
        }
        header('Location: ' . ROOT_URL . 'admin/manage_ngaji_siswa.php');
        exit;
    }

    // Handle edit action
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $edit_ngaji = $ngaji_model->getById($_GET['id']);
        if (!$edit_ngaji) {
            set_flash('error', 'Data ngaji tidak ditemukan.');
            header('Location: ' . ROOT_URL . 'admin/manage_ngaji_siswa.php');
            exit;
        }
    }

    // Dapatkan data ngaji dengan filter
    $filters = [];
    if ($filter_siswa_id) {
        $filters['siswa_id'] = $filter_siswa_id;
    }
    if ($filter_guru_id) {
        $filters['guru_id'] = $filter_guru_id;
    }
    if ($filter_kelas_id) {
        $filters['kelas_id'] = $filter_kelas_id;
    }
    if ($filter_tahun_ajaran_id) {
        $filters['tahun_ajaran_id'] = $filter_tahun_ajaran_id;
    }
    $ngaji_data = $ngaji_model->getAll($filters);

} catch (PDOException $e) {
    error_log("Ngaji Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Ngaji General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
}

require_once __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Kelola Catatan Ngaji</h1>

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
        <h2 class="text-xl font-semibold mb-4"><?php echo $edit_ngaji ? 'Edit Catatan Ngaji' : 'Tambah Catatan Ngaji Baru'; ?></h2>
        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="<?php echo $edit_ngaji ? 'edit' : 'add'; ?>">
            <?php if ($edit_ngaji): ?>
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_ngaji['id']); ?>">
            <?php endif; ?>

            <div>
                <label for="siswa_id" class="block text-gray-700 text-sm font-bold mb-2">Siswa:</label>
                <select id="siswa_id" name="siswa_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <option value="">-- Pilih Siswa --</option>
                    <?php foreach ($all_siswa as $siswa): ?>
                        <option value="<?php echo htmlspecialchars($siswa['id']); ?>"
                            <?php echo ($edit_ngaji && $edit_ngaji['siswa_id'] == $siswa['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($siswa['nama_lengkap']); ?> (NISN: <?php echo htmlspecialchars($siswa['nisn'] ?? '-'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="guru_id" class="block text-gray-700 text-sm font-bold mb-2">Guru:</label>
                <select id="guru_id" name="guru_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <option value="">-- Pilih Guru --</option>
                    <?php foreach ($all_guru as $guru): ?>
                        <option value="<?php echo htmlspecialchars($guru['id']); ?>"
                            <?php echo ($edit_ngaji && $edit_ngaji['guru_id'] == $guru['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($guru['nama_lengkap']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="tanggal_setor" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Setor:</label>
                <input type="date" id="tanggal_setor" name="tanggal_setor" value="<?php echo htmlspecialchars($edit_ngaji['tanggal_setor'] ?? date('Y-m-d')); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>

            <div>
                <label for="jenis_materi" class="block text-gray-700 text-sm font-bold mb-2">Jenis Materi:</label>
                <select id="jenis_materi" name="jenis_materi" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <option value="">-- Pilih Materi --</option>
                    <?php $materi_options = ['Iqro', 'Juz Amma', 'Al-Qur\'an']; ?>
                    <?php foreach ($materi_options as $materi): ?>
                        <option value="<?php echo htmlspecialchars($materi); ?>"
                            <?php echo ($edit_ngaji && $edit_ngaji['jenis_materi'] == $materi) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($materi); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="halaman_dari" class="block text-gray-700 text-sm font-bold mb-2">Bagian (Dari):</label>
                <input type="text" id="halaman_dari" name="halaman_dari" value="<?php echo htmlspecialchars($edit_ngaji['halaman_dari'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>

            <div>
                <label for="halaman_sampai" class="block text-gray-700 text-sm font-bold mb-2">Bagian (Sampai):</label>
                <input type="text" id="halaman_sampai" name="halaman_sampai" value="<?php echo htmlspecialchars($edit_ngaji['halaman_sampai'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>

            <div>
                <label for="catatan_guru" class="block text-gray-700 text-sm font-bold mb-2">Catatan Guru:</label>
                <textarea id="catatan_guru" name="catatan_guru" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?php echo htmlspecialchars($edit_ngaji['catatan_guru'] ?? ''); ?></textarea>
            </div>

            <div class="flex items-center justify-between">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <?php echo $edit_ngaji ? 'Perbarui Data' : 'Tambah Data'; ?>
                </button>
                <?php if ($edit_ngaji): ?>
                    <a href="<?php echo ROOT_URL; ?>admin/manage_ngaji_siswa.php" class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
                        Batal
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-semibold mb-4">Filter Data</h2>
        <form action="" method="GET" class="space-y-4 md:space-y-0 md:flex md:gap-4 items-end">
            <div>
                <label for="filter_siswa_id" class="block text-gray-700 text-sm font-bold mb-2">Siswa:</label>
                <select id="filter_siswa_id" name="filter_siswa_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Siswa</option>
                    <?php foreach ($all_siswa as $siswa): ?>
                        <option value="<?php echo htmlspecialchars($siswa['id']); ?>" <?php echo ($filter_siswa_id == $siswa['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($siswa['nama_lengkap']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="filter_guru_id" class="block text-gray-700 text-sm font-bold mb-2">Guru:</label>
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
                <label for="filter_kelas_id" class="block text-gray-700 text-sm font-bold mb-2">Kelas:</label>
                <select id="filter_kelas_id" name="filter_kelas_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($all_kelas as $kelas): ?>
                        <option value="<?php echo htmlspecialchars($kelas['id']); ?>" <?php echo ($filter_kelas_id == $kelas['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
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

            <div>
                <button type="submit" class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Filter
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold mb-4">Daftar Catatan Ngaji</h2>
        <?php if (empty($ngaji_data)): ?>
            <p class="text-gray-600">Belum ada catatan ngaji yang tersedia.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto">
                    <thead>
                        <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">No.</th>
                            <th class="py-3 px-6 text-left">Tahun Ajaran</th>
                            <th class="py-3 px-6 text-left">Siswa (Kelas)</th>
                            <th class="py-3 px-6 text-left">Tanggal</th>
                            <th class="py-3 px-6 text-left">Materi</th>
                            <th class="py-3 px-6 text-left">Bagian</th>
                            <th class="py-3 px-6 text-left">Guru</th>
                            <th class="py-3 px-6 text-left">Catatan</th>
                            <th class="py-3 px-6 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm">
                        <?php $no = 1; ?>
                        <?php foreach ($ngaji_data as $ngaji): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($ngaji['nama_tahun_ajaran'] ?? '-'); ?></td>
                                <td class="py-3 px-6 whitespace-nowrap">
                                    <?php echo htmlspecialchars($ngaji['nama_siswa'] ?? 'N/A'); ?>
                                    (Kelas: <?php echo htmlspecialchars($ngaji['nama_kelas'] ?? 'N/A'); ?>)
                                </td>
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars(format_date_indonesian($ngaji['tanggal_setor'])); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($ngaji['jenis_materi']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($ngaji['halaman_dari'] . ' - ' . $ngaji['halaman_sampai']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($ngaji['nama_guru'] ?? 'N/A'); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($ngaji['catatan_guru'] ?? '-'); ?></td>
                                <td class="py-3 px-6 text-center whitespace-nowrap">
                                    <a href="?action=edit&id=<?php echo htmlspecialchars($ngaji['id']); ?>" class="text-blue-600 hover:text-blue-900 mr-3">Edit</a>
                                    <a href="?action=delete&id=<?php echo htmlspecialchars($ngaji['id']); ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Apakah Anda yakin ingin menghapus catatan ngaji ini?');">Hapus</a>
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
