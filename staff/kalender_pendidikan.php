<?php
// project-root/staff/kalender_pendidikan.php

// Sertakan file-file yang diperlukan
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/KalenderPendidikan.php';

// Pastikan pengguna sudah login dan memiliki peran Admin atau Tata Usaha
require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$kalender_model = null;
$all_libur = [];
$edit_data = null;
$all_tahun_ajaran = [];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $kalender_model = new KalenderPendidikan($pdo);

    // Ambil semua tahun ajaran dari database (asumsi ada fungsi ini)
    $all_tahun_ajaran = getAllTahunAjaran();

    // Tangani aksi POST (Tambah, Edit, Hapus)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            $action = $_POST['action'];

            if ($action === 'add' || $action === 'edit') {
                $tanggal = $_POST['tanggal'];
                $keterangan = $_POST['keterangan'];
                $tahun_ajaran = $_POST['tahun_ajaran'] ?? null;
                $semester = $_POST['semester'] ?? null;

                if (empty($tanggal) || empty($keterangan) || empty($tahun_ajaran) || empty($semester)) {
                    set_flash('error', 'Tanggal, Keterangan, Tahun Ajaran, dan Semester wajib diisi.');
                    redirect_to('staff/kalender_pendidikan.php');
                }

                if ($action === 'add') {
                    // Cek duplikasi tanggal dalam tahun ajaran yang sama
                    if ($kalender_model->findByDate($tanggal, $tahun_ajaran)) {
                        set_flash('error', 'Tanggal ' . htmlspecialchars($tanggal) . ' sudah ada di kalender untuk tahun ajaran ini.');
                    } else {
                        $kalender_model->addLibur($tanggal, $keterangan, $tahun_ajaran, $semester);
                        set_flash('success', 'Hari libur berhasil ditambahkan.');
                    }
                } elseif ($action === 'edit') {
                    $id = $_POST['id'];
                    $kalender_model->updateLibur($id, $tanggal, $keterangan, $tahun_ajaran, $semester);
                    set_flash('success', 'Hari libur berhasil diperbarui.');
                }
            } elseif ($action === 'delete') {
                $id = $_POST['id'];
                $kalender_model->deleteLibur($id);
                set_flash('success', 'Hari libur berhasil dihapus.');
            }
            redirect_to('staff/kalender_pendidikan.php');
        }
    }

    // Tangani aksi GET (untuk menampilkan form edit)
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $id = $_GET['id'];
        $edit_data = $kalender_model->findById($id);
        if (!$edit_data) {
            set_flash('error', 'Hari libur tidak ditemukan.');
            redirect_to('staff/kalender_pendidikan.php');
        }
    }

    // Ambil semua data hari libur untuk ditampilkan
    $all_libur = $kalender_model->getAllLibur();

} catch (PDOException $e) {
    error_log("Kalender Pendidikan Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Kalender Pendidikan General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
}

// Sertakan header
require_once __DIR__ . '/../components/header_staff.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Kelola Kalender Pendidikan</h1>

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
        <h2 class="text-xl font-semibold mb-4"><?php echo $edit_data ? 'Edit Hari Libur' : 'Tambah Hari Libur Baru'; ?></h2>
        <form action="kalender_pendidikan.php" method="POST">
            <input type="hidden" name="action" value="<?php echo $edit_data ? 'edit' : 'add'; ?>">
            <?php if ($edit_data): ?>
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_data['id']); ?>">
            <?php endif; ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="mb-4 md:col-span-1">
                    <label for="tanggal" class="block text-gray-700 text-sm font-bold mb-2">Tanggal:</label>
                    <input type="date" id="tanggal" name="tanggal" value="<?php echo htmlspecialchars($edit_data['tanggal'] ?? ''); ?>" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-4 md:col-span-3">
                    <label for="keterangan" class="block text-gray-700 text-sm font-bold mb-2">Keterangan:</label>
                    <input type="text" id="keterangan" name="keterangan" value="<?php echo htmlspecialchars($edit_data['keterangan'] ?? ''); ?>" placeholder="Contoh: Libur Hari Raya Idul Fitri" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-4 md:col-span-2">
                    <label for="tahun_ajaran" class="block text-gray-700 text-sm font-bold mb-2">Tahun Ajaran:</label>
                    <select id="tahun_ajaran" name="tahun_ajaran" required class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="">Pilih Tahun Ajaran</option>
                        <?php foreach ($all_tahun_ajaran as $ta): ?>
                            <option value="<?php echo htmlspecialchars($ta['tahun_ajaran']); ?>"
                                    <?php echo ($edit_data['tahun_ajaran'] ?? '') == $ta['tahun_ajaran'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ta['tahun_ajaran']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4 md:col-span-2">
                    <label for="semester" class="block text-gray-700 text-sm font-bold mb-2">Semester:</label>
                    <select id="semester" name="semester" required class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="">Pilih Semester</option>
                        <option value="Ganjil" <?php echo ($edit_data['semester'] ?? '') == 'Ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                        <option value="Genap" <?php echo ($edit_data['semester'] ?? '') == 'Genap' ? 'selected' : ''; ?>>Genap</option>
                    </select>
                </div>
            </div>
            
            <div class="flex items-center justify-between mt-4">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <?php echo $edit_data ? 'Perbarui' : 'Tambah'; ?>
                </button>
                <?php if ($edit_data): ?>
                    <a href="kalender_pendidikan.php" class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
                        Batal
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold mb-4">Daftar Hari Libur</h2>
        <?php if (empty($all_libur)): ?>
            <p class="text-gray-600">Tidak ada hari libur yang tercatat.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                    <thead>
                        <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">No.</th>
                            <th class="py-3 px-6 text-left">Tanggal</th>
                            <th class="py-3 px-6 text-left">Keterangan</th>
                            <th class="py-3 px-6 text-left">Tahun Ajaran</th>
                            <th class="py-3 px-6 text-left">Semester</th>
                            <th class="py-3 px-6 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm">
                        <?php $no = 1; ?>
                        <?php foreach ($all_libur as $libur): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars(format_date_indonesian($libur['tanggal'])); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($libur['keterangan']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($libur['tahun_ajaran'] ?? '-'); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($libur['semester'] ?? '-'); ?></td>
                                <td class="py-3 px-6 text-center whitespace-nowrap">
                                    <a href="?action=edit&id=<?php echo htmlspecialchars($libur['id']); ?>" class="text-blue-600 hover:text-blue-900 mr-2">Edit</a>
                                    <form action="kalender_pendidikan.php" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus hari libur ini?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($libur['id']); ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-900">Hapus</button>
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

<?php
// Sertakan footer
require_once __DIR__ . '/../components/footer.php';
?>