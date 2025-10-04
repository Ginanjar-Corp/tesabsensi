<?php
// project-root/staff/manage_informasi_umum.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/InformasiUmum.php'; // Sertakan kelas InformasiUmum

// Pastikan pengguna sudah login dan memiliki peran Admin atau Tata Usaha
require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$informasi_umum_model = null;
$pdo = null;

$informasi_data = [];
$edit_informasi = null; // Untuk mode edit

// Filter variables
$filter_status_publikasi = $_GET['filter_status_publikasi'] ?? '';
$status_publikasi_options = ['Aktif', 'Tidak Aktif'];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $informasi_umum_model = new InformasiUmum($pdo);

    // Handle form submission (add/edit)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            $informasi_umum_model->judul = $_POST['judul'];
            $informasi_umum_model->konten = $_POST['konten'];
            $informasi_umum_model->tanggal_publikasi = $_POST['tanggal_publikasi'];
            $informasi_umum_model->status_publikasi = $_POST['status_publikasi'];

            if ($_POST['action'] === 'add') {
                if ($informasi_umum_model->create()) {
                    set_flash('success', 'Informasi umum berhasil ditambahkan.');
                } else {
                    set_flash('error', 'Gagal menambahkan informasi umum.');
                }
            } elseif ($_POST['action'] === 'edit') {
                $informasi_umum_model->id = $_POST['id'];
                if ($informasi_umum_model->update()) {
                    set_flash('success', 'Informasi umum berhasil diperbarui.');
                } else {
                    set_flash('error', 'Gagal memperbarui informasi umum.');
                }
            }
        }
        header('Location: ' . ROOT_URL . 'staff/manage_informasi_umum.php');
        exit;
    }

    // Handle delete request
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        if ($informasi_umum_model->delete($_GET['id'])) {
            set_flash('success', 'Informasi umum berhasil dihapus.');
        } else {
            set_flash('error', 'Gagal menghapus informasi umum.');
        }
        header('Location: ' . ROOT_URL . 'staff/manage_informasi_umum.php');
        exit;
    }

    // Handle edit request
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $edit_informasi = $informasi_umum_model->getById($_GET['id']);
        if (!$edit_informasi) {
            set_flash('error', 'Informasi umum tidak ditemukan.');
            header('Location: ' . ROOT_URL . 'staff/manage_informasi_umum.php');
            exit;
        }
    }

    // Apply filters for fetching all data
    $filters = [];
    if ($filter_status_publikasi) {
        $filters['status_publikasi'] = $filter_status_publikasi;
    }
    $informasi_data = $informasi_umum_model->getAll($filters);

} catch (PDOException $e) {
    error_log("Manajemen Informasi Umum Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Manajemen Informasi Umum General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
}

require_once __DIR__ . '/../components/header_staff.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manajemen Informasi Umum</h1>

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
        <h2 class="text-xl font-semibold mb-4"><?php echo $edit_informasi ? 'Edit Informasi Umum' : 'Tambah Informasi Umum Baru'; ?></h2>
        <form action="" method="POST" class="space-y-4">
            <?php if ($edit_informasi): ?>
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_informasi['id']); ?>">
                <input type="hidden" name="action" value="edit">
            <?php else: ?>
                <input type="hidden" name="action" value="add">
            <?php endif; ?>

            <div>
                <label for="judul" class="block text-gray-700 text-sm font-bold mb-2">Judul:</label>
                <input type="text" id="judul" name="judul" value="<?php echo htmlspecialchars($edit_informasi['judul'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div>
                <label for="konten" class="block text-gray-700 text-sm font-bold mb-2">Konten:</label>
                <textarea id="konten" name="konten" rows="5" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required><?php echo htmlspecialchars($edit_informasi['konten'] ?? ''); ?></textarea>
            </div>
            <div>
                <label for="tanggal_publikasi" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Publikasi:</label>
                <input type="date" id="tanggal_publikasi" name="tanggal_publikasi" value="<?php echo htmlspecialchars($edit_informasi['tanggal_publikasi'] ?? date('Y-m-d')); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div>
                <label for="status_publikasi" class="block text-gray-700 text-sm font-bold mb-2">Status Publikasi:</label>
                <select id="status_publikasi" name="status_publikasi" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <?php foreach ($status_publikasi_options as $status): ?>
                        <option value="<?php echo htmlspecialchars($status); ?>" <?php echo (isset($edit_informasi['status_publikasi']) && $edit_informasi['status_publikasi'] == $status) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($status); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-center space-x-4">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                    <?php echo $edit_informasi ? 'Perbarui Informasi' : 'Tambah Informasi'; ?>
                </button>
                <?php if ($edit_informasi): ?>
                    <a href="<?php echo ROOT_URL; ?>staff/manage_informasi_umum.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                        Batal
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-semibold mb-4">Filter Informasi Umum</h2>
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
            <div>
                <label for="filter_status_publikasi" class="block text-gray-700 text-sm font-bold mb-2">Status Publikasi:</label>
                <select id="filter_status_publikasi" name="filter_status_publikasi" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Status</option>
                    <?php foreach ($status_publikasi_options as $status): ?>
                        <option value="<?php echo htmlspecialchars($status); ?>" <?php echo ($filter_status_publikasi == $status) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($status); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex justify-start items-end md:col-span-1">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full md:w-auto">
                    Tampilkan Filter
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold mb-4">Daftar Informasi Umum</h2>
        <?php if (empty($informasi_data)): ?>
            <p class="text-gray-600">Tidak ada informasi umum yang tersedia.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                    <thead>
                        <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">No.</th>
                            <th class="py-3 px-6 text-left">Judul</th>
                            <th class="py-3 px-6 text-left">Konten</th>
                            <th class="py-3 px-6 text-left">Tanggal Publikasi</th>
                            <th class="py-3 px-6 text-center">Status</th>
                            <th class="py-3 px-6 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm">
                        <?php $no = 1; ?>
                        <?php foreach ($informasi_data as $info): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($info['judul']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars(substr($info['konten'], 0, 100)); ?><?php echo (strlen($info['konten']) > 100) ? '...' : ''; ?></td>
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars(format_date_indonesian($info['tanggal_publikasi'])); ?></td>
                                <td class="py-3 px-6 text-center">
                                    <span class="px-2 py-1 font-semibold leading-tight rounded-full
                                        <?php
                                            if ($info['status_publikasi'] == 'Aktif') echo 'bg-green-100 text-green-800';
                                            else echo 'bg-red-100 text-red-800';
                                        ?>">
                                        <?php echo htmlspecialchars($info['status_publikasi']); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-6 text-center whitespace-nowrap">
                                    <a href="<?php echo ROOT_URL; ?>staff/manage_informasi_umum.php?action=edit&id=<?php echo htmlspecialchars($info['id']); ?>" class="text-blue-600 hover:text-blue-900 mr-3">Edit</a>
                                    <a href="<?php echo ROOT_URL; ?>staff/manage_informasi_umum.php?action=delete&id=<?php echo htmlspecialchars($info['id']); ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Apakah Anda yakin ingin menghapus informasi ini?');">Hapus</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../components/footer.php';
?>
