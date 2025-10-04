<?php
// project-root/admin/pelanggaran_siswa.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/PelanggaranSiswa.php';
require_once __DIR__ . '/../classes/Siswa.php'; // Untuk dropdown siswa
require_once __DIR__ . '/../classes/JenisPelanggaran.php'; // Untuk dropdown jenis pelanggaran
require_once __DIR__ . '/../classes/Guru.php'; // Untuk dropdown petugas (guru)
require_once __DIR__ . '/../classes/TahunAjaran.php'; // Tambahkan ini

require_login();
require_role(['Admin', 'TataUsaha', 'Guru']); // Guru juga bisa mencatat pelanggaran

$error_message = flash('error');
$success_message = flash('success');

$pelanggaran_model = null;
$siswa_model = null;
$jenis_pelanggaran_model = null;
$guru_model = null;
$tahun_ajaran_model = null; // Tambahkan
$pdo = null;

$pelanggaran_data = [];
$all_siswa = [];
$all_jenis_pelanggaran = [];
$all_guru = [];
$all_tahun_ajaran = []; // Tambahkan
$edit_pelanggaran = null;

// Filter variables
$filter_siswa_id = isset($_GET['filter_siswa_id']) ? (int)$_GET['filter_siswa_id'] : null;
$filter_jenis_pelanggaran_id = isset($_GET['filter_jenis_pelanggaran_id']) ? (int)$_GET['filter_jenis_pelanggaran_id'] : null;
$filter_petugas_id = isset($_GET['filter_petugas_id']) ? (int)$_GET['filter_petugas_id'] : null;
$filter_status_tindak_lanjut = isset($_GET['filter_status_tindak_lanjut']) ? $_GET['filter_status_tindak_lanjut'] : '';
$filter_start_date = isset($_GET['filter_start_date']) ? $_GET['filter_start_date'] : '';
$filter_end_date = isset($_GET['filter_end_date']) ? $_GET['filter_end_date'] : '';
$filter_tahun_ajaran_id = isset($_GET['filter_tahun_ajaran_id']) ? (int)$_GET['filter_tahun_ajaran_id'] : null; // Tambahkan filter tahun ajaran

$status_options = [
    'Belum Ditindak', 'Peringatan Lisan', 'Peringatan Tertulis', 'Skorsing', 'Selesai'
];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $pelanggaran_model = new PelanggaranSiswa($pdo);
    $siswa_model = new Siswa($pdo);
    $jenis_pelanggaran_model = new JenisPelanggaran($pdo);
    $guru_model = new Guru($pdo);
    $tahun_ajaran_model = new TahunAjaran($pdo); // Inisialisasi model TahunAjaran

    $all_siswa = $siswa_model->getAll();
    $all_jenis_pelanggaran = $jenis_pelanggaran_model->getAll();
    $all_guru = $guru_model->getAll(); // Guru sebagai petugas
    $all_tahun_ajaran = $tahun_ajaran_model->getAll(); // Ambil semua tahun ajaran

    // Handle form submission (add/edit)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            $pelanggaran_model->siswa_id = $_POST['siswa_id'];
            // tahun_ajaran_id akan diisi otomatis di kelas PelanggaranSiswa::create() atau update()
            $pelanggaran_model->jenis_pelanggaran_id = $_POST['jenis_pelanggaran_id'];
            $pelanggaran_model->tanggal_pelanggaran = $_POST['tanggal_pelanggaran'];
            $pelanggaran_model->waktu_pelanggaran = $_POST['waktu_pelanggaran'];
            $pelanggaran_model->deskripsi_kejadian = $_POST['deskripsi_kejadian'];
            $pelanggaran_model->poin_deduksi_aktual = $_POST['poin_deduksi_aktual'];
            $pelanggaran_model->petugas_id = $_POST['petugas_id'];
            $pelanggaran_model->status_tindak_lanjut = $_POST['status_tindak_lanjut'];
            $pelanggaran_model->catatan_tindak_lanjut = $_POST['catatan_tindak_lanjut'];

            if ($_POST['action'] === 'add') {
                if ($pelanggaran_model->create()) {
                    set_flash('success', 'Catatan pelanggaran berhasil ditambahkan.');
                } else {
                    set_flash('error', 'Gagal menambahkan catatan pelanggaran.');
                }
            } elseif ($_POST['action'] === 'edit') {
                $pelanggaran_model->id = $_POST['id'];
                if ($pelanggaran_model->update()) {
                    set_flash('success', 'Catatan pelanggaran berhasil diperbarui.');
                } else {
                    set_flash('error', 'Gagal memperbarui catatan pelanggaran.');
                }
            }
        }
        header('Location: ' . ROOT_URL . 'admin/pelanggaran_siswa.php');
        exit;
    }

    // Handle delete request
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        if ($pelanggaran_model->delete($_GET['id'])) {
            set_flash('success', 'Catatan pelanggaran berhasil dihapus.');
        } else {
            set_flash('error', 'Gagal menghapus catatan pelanggaran.');
        }
        header('Location: ' . ROOT_URL . 'admin/pelanggaran_siswa.php');
        exit;
    }

    // Handle edit request
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $edit_pelanggaran = $pelanggaran_model->getById($_GET['id']);
        if (!$edit_pelanggaran) {
            set_flash('error', 'Catatan pelanggaran tidak ditemukan.');
            header('Location: ' . ROOT_URL . 'admin/pelanggaran_siswa.php');
            exit;
        }
    }

    // Apply filters for fetching all data
    $filters = [
        'siswa_id' => $filter_siswa_id,
        'jenis_pelanggaran_id' => $filter_jenis_pelanggaran_id,
        'petugas_id' => $filter_petugas_id,
        'status_tindak_lanjut' => $filter_status_tindak_lanjut,
        'start_date' => $filter_start_date,
        'end_date' => $filter_end_date,
        'tahun_ajaran_id' => $filter_tahun_ajaran_id, // Tambahkan filter tahun ajaran
    ];
    $pelanggaran_data = $pelanggaran_model->getAll($filters);

} catch (PDOException $e) {
    error_log("Manajemen Pelanggaran Siswa Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Manajemen Pelanggaran Siswa General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
}

require_once __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manajemen Pelanggaran Siswa</h1>

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
        <h2 class="text-xl font-semibold mb-4"><?php echo $edit_pelanggaran ? 'Edit Catatan Pelanggaran' : 'Tambah Catatan Pelanggaran Baru'; ?></h2>
        <form action="" method="POST" class="space-y-4">
            <?php if ($edit_pelanggaran): ?>
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_pelanggaran['id']); ?>">
                <input type="hidden" name="action" value="edit">
            <?php else: ?>
                <input type="hidden" name="action" value="add">
            <?php endif; ?>

            <div>
                <label for="siswa_id" class="block text-gray-700 text-sm font-bold mb-2">Siswa:</label>
                <select id="siswa_id" name="siswa_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <option value="">Pilih Siswa</option>
                    <?php foreach ($all_siswa as $siswa): ?>
                        <option value="<?php echo htmlspecialchars($siswa['id']); ?>" <?php echo (isset($edit_pelanggaran['siswa_id']) && $edit_pelanggaran['siswa_id'] == $siswa['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($siswa['nama_lengkap']); ?> (NISN: <?php echo htmlspecialchars($siswa['nisn'] ?? '-'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="jenis_pelanggaran_id" class="block text-gray-700 text-sm font-bold mb-2">Jenis Pelanggaran:</label>
                <select id="jenis_pelanggaran_id" name="jenis_pelanggaran_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <option value="">Pilih Jenis Pelanggaran</option>
                    <?php foreach ($all_jenis_pelanggaran as $jp): ?>
                        <option value="<?php echo htmlspecialchars($jp['id']); ?>"
                                data-poin="<?php echo htmlspecialchars($jp['poin_deduksi']); ?>"
                                <?php echo (isset($edit_pelanggaran['jenis_pelanggaran_id']) && $edit_pelanggaran['jenis_pelanggaran_id'] == $jp['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($jp['nama_pelanggaran']); ?> (Poin: <?php echo htmlspecialchars($jp['poin_deduksi']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="tanggal_pelanggaran" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Pelanggaran:</label>
                <input type="date" id="tanggal_pelanggaran" name="tanggal_pelanggaran" value="<?php echo htmlspecialchars($edit_pelanggaran['tanggal_pelanggaran'] ?? date('Y-m-d')); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div>
                <label for="waktu_pelanggaran" class="block text-gray-700 text-sm font-bold mb-2">Waktu Pelanggaran:</label>
                <input type="time" id="waktu_pelanggaran" name="waktu_pelanggaran" value="<?php echo htmlspecialchars($edit_pelanggaran['waktu_pelanggaran'] ?? date('H:i')); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div>
                <label for="deskripsi_kejadian" class="block text-gray-700 text-sm font-bold mb-2">Deskripsi Kejadian:</label>
                <textarea id="deskripsi_kejadian" name="deskripsi_kejadian" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?php echo htmlspecialchars($edit_pelanggaran['deskripsi_kejadian'] ?? ''); ?></textarea>
            </div>
            <div>
                <label for="poin_deduksi_aktual" class="block text-gray-700 text-sm font-bold mb-2">Poin Deduksi Aktual:</label>
                <input type="number" id="poin_deduksi_aktual" name="poin_deduksi_aktual" value="<?php echo htmlspecialchars($edit_pelanggaran['poin_deduksi_aktual'] ?? 0); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div>
                <label for="petugas_id" class="block text-gray-700 text-sm font-bold mb-2">Petugas Pencatat:</label>
                <select id="petugas_id" name="petugas_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <option value="">Pilih Petugas</option>
                    <?php foreach ($all_guru as $guru): // Asumsi petugas adalah guru ?>
                        <option value="<?php echo htmlspecialchars($guru['id']); ?>" <?php echo (isset($edit_pelanggaran['petugas_id']) && $edit_pelanggaran['petugas_id'] == $guru['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($guru['nama_lengkap']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="status_tindak_lanjut" class="block text-gray-700 text-sm font-bold mb-2">Status Tindak Lanjut:</label>
                <select id="status_tindak_lanjut" name="status_tindak_lanjut" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <?php foreach ($status_options as $status): ?>
                        <option value="<?php echo htmlspecialchars($status); ?>" <?php echo (isset($edit_pelanggaran['status_tindak_lanjut']) && $edit_pelanggaran['status_tindak_lanjut'] == $status) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($status); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="catatan_tindak_lanjut" class="block text-gray-700 text-sm font-bold mb-2">Catatan Tindak Lanjut:</label>
                <textarea id="catatan_tindak_lanjut" name="catatan_tindak_lanjut" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?php echo htmlspecialchars($edit_pelanggaran['catatan_tindak_lanjut'] ?? ''); ?></textarea>
            </div>
            <div class="flex items-center space-x-4">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                    <?php echo $edit_pelanggaran ? 'Perbarui Catatan' : 'Tambah Catatan'; ?>
                </button>
                <?php if ($edit_pelanggaran): ?>
                    <a href="<?php echo ROOT_URL; ?>admin/pelanggaran_siswa.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                        Batal
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-semibold mb-4">Filter Data Pelanggaran</h2>
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 items-end">
            <div>
                <label for="filter_siswa_id" class="block text-gray-700 text-sm font-bold mb-2">Siswa:</label>
                <select id="filter_siswa_id" name="filter_siswa_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Siswa</option>
                    <?php foreach ($all_siswa as $siswa): ?>
                        <option value="<?php echo htmlspecialchars($siswa['id']); ?>" <?php echo ($filter_siswa_id == $siswa['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($siswa['nama_lengkap']); ?> (NISN: <?php echo htmlspecialchars($siswa['nisn'] ?? '-'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_jenis_pelanggaran_id" class="block text-gray-700 text-sm font-bold mb-2">Jenis Pelanggaran:</label>
                <select id="filter_jenis_pelanggaran_id" name="filter_jenis_pelanggaran_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Jenis Pelanggaran</option>
                    <?php foreach ($all_jenis_pelanggaran as $jp): ?>
                        <option value="<?php echo htmlspecialchars($jp['id']); ?>" <?php echo ($filter_jenis_pelanggaran_id == $jp['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($jp['nama_pelanggaran']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_petugas_id" class="block text-gray-700 text-sm font-bold mb-2">Petugas Pencatat:</label>
                <select id="filter_petugas_id" name="filter_petugas_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Petugas</option>
                    <?php foreach ($all_guru as $guru): ?>
                        <option value="<?php echo htmlspecialchars($guru['id']); ?>" <?php echo ($filter_petugas_id == $guru['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($guru['nama_lengkap']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_status_tindak_lanjut" class="block text-gray-700 text-sm font-bold mb-2">Status Tindak Lanjut:</label>
                <select id="filter_status_tindak_lanjut" name="filter_status_tindak_lanjut" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Status</option>
                    <?php foreach ($status_options as $status): ?>
                        <option value="<?php echo htmlspecialchars($status); ?>" <?php echo ($filter_status_tindak_lanjut == $status) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($status); ?>
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
                <label for="filter_start_date" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Mulai:</label>
                <input type="date" id="filter_start_date" name="filter_start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div>
                <label for="filter_end_date" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Selesai:</label>
                <input type="date" id="filter_end_date" name="filter_end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="md:col-span-1 lg:col-span-1 flex justify-start items-end">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full md:w-auto">
                    Tampilkan Filter
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold mb-4">Daftar Catatan Pelanggaran Siswa</h2>
        <?php if (empty($pelanggaran_data)): ?>
            <p class="text-gray-600">Tidak ada catatan pelanggaran siswa.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                    <thead>
                        <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">No.</th>
                            <th class="py-3 px-6 text-left">Tahun Ajaran</th>
                            <th class="py-3 px-6 text-left">Tanggal</th>
                            <th class="py-3 px-6 text-left">Siswa</th>
                            <th class="py-3 px-6 text-left">Jenis Pelanggaran</th>
                            <th class="py-3 px-6 text-center">Poin</th>
                            <th class="py-3 px-6 text-left">Deskripsi Kejadian</th>
                            <th class="py-3 px-6 text-left">Petugas</th>
                            <th class="py-3 px-6 text-center">Tindak Lanjut</th>
                            <th class="py-3 px-6 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm">
                        <?php $no = 1; ?>
                        <?php foreach ($pelanggaran_data as $pelanggaran): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($pelanggaran['nama_tahun_ajaran'] ?? '-'); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars(format_date_indonesian($pelanggaran['tanggal_pelanggaran'])); ?><br><small class="text-gray-500"><?php echo htmlspecialchars(substr($pelanggaran['waktu_pelanggaran'], 0, 5)); ?></small></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($pelanggaran['nama_siswa']); ?> (NISN: <?php echo htmlspecialchars($pelanggaran['nisn'] ?? '-'); ?>)</td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($pelanggaran['nama_pelanggaran']); ?></td>
                                <td class="py-3 px-6 text-center"><?php echo htmlspecialchars($pelanggaran['poin_deduksi_aktual']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars(substr($pelanggaran['deskripsi_kejadian'] ?? '-', 0, 50)); ?><?php echo (strlen($pelanggaran['deskripsi_kejadian'] ?? '') > 50) ? '...' : ''; ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($pelanggaran['nama_petugas']); ?></td>
                                <td class="py-3 px-6 text-center">
                                    <span class="px-2 py-1 font-semibold leading-tight rounded-full
                                        <?php
                                            switch ($pelanggaran['status_tindak_lanjut']) {
                                                case 'Selesai': echo 'bg-green-100 text-green-800'; break;
                                                case 'Peringatan Tertulis':
                                                case 'Skorsing': echo 'bg-red-100 text-red-800'; break;
                                                case 'Peringatan Lisan': echo 'bg-yellow-100 text-yellow-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800'; break;
                                            }
                                        ?>">
                                        <?php echo htmlspecialchars($pelanggaran['status_tindak_lanjut']); ?>
                                    </span>
                                    <?php if (!empty($pelanggaran['catatan_tindak_lanjut'])): ?>
                                        <br><small class="text-gray-500" title="<?php echo htmlspecialchars($pelanggaran['catatan_tindak_lanjut']); ?>">(Catatan)</small>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-6 text-center whitespace-nowrap">
                                    <a href="<?php echo ROOT_URL; ?>admin/pelanggaran_siswa.php?action=edit&id=<?php echo htmlspecialchars($pelanggaran['id']); ?>" class="text-blue-600 hover:text-blue-900 mr-3">Edit</a>
                                    <a href="<?php echo ROOT_URL; ?>admin/pelanggaran_siswa.php?action=delete&id=<?php echo htmlspecialchars($pelanggaran['id']); ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Apakah Anda yakin ingin menghapus catatan pelanggaran ini?');">Hapus</a>
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
    // Script untuk mengisi Poin Deduksi Aktual secara otomatis berdasarkan Jenis Pelanggaran
    document.addEventListener('DOMContentLoaded', function() {
        const jenisPelanggaranSelect = document.getElementById('jenis_pelanggaran_id');
        const poinDeduksiAktualInput = document.getElementById('poin_deduksi_aktual');

        if (jenisPelanggaranSelect && poinDeduksiAktualInput) {
            function updatePoinDeduksi() {
                const selectedOption = jenisPelanggaranSelect.options[jenisPelanggaranSelect.selectedIndex];
                const defaultPoin = selectedOption.dataset.poin || 0;
                poinDeduksiAktualInput.value = defaultPoin;
            }

            // Panggil saat halaman dimuat (untuk mode edit jika sudah ada nilai)
            updatePoinDeduksi();

            // Panggil saat pilihan jenis pelanggaran berubah
            jenisPelanggaranSelect.addEventListener('change', updatePoinDeduksi);
        }
    });
</script>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
