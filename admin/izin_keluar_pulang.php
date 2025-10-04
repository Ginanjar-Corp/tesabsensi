<?php
// project-root/admin/izin_keluar_pulang.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/IzinKeluarPulang.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/Guru.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/TahunAjaran.php';
require_once __DIR__ . '/../classes/Kelas.php';

require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$izin_model = null;
$siswa_model = null;
$guru_model = null;
$user_model = null;
$tahun_ajaran_model = null;
$kelas_model = null;
$pdo = null;

$izin_data = [];
$all_siswa = [];
$all_pemberi_izin = [];
$all_tahun_ajaran = [];
$all_kelas = [];
$edit_izin = null;

// Filter variables
$filter_siswa_id = isset($_GET['filter_siswa_id']) ? (int)$_GET['filter_siswa_id'] : null;
$filter_jenis_izin = $_GET['filter_jenis_izin'] ?? '';
$filter_start_date = $_GET['filter_start_date'] ?? '';
$filter_end_date = $_GET['filter_end_date'] ?? '';
$filter_tahun_ajaran_id = isset($_GET['filter_tahun_ajaran_id']) ? (int)$_GET['filter_tahun_ajaran_id'] : null;
$filter_kelas_id = isset($_GET['filter_kelas_id']) ? (int)$_GET['filter_kelas_id'] : null;

$jenis_izin_options = ['Keluar', 'Pulang'];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $izin_model = new IzinKeluarPulang($pdo);
    $siswa_model = new Siswa($pdo);
    $guru_model = new Guru($pdo);
    $user_model = new User($pdo);
    $tahun_ajaran_model = new TahunAjaran($pdo);
    $kelas_model = new Kelas($pdo);

    $all_siswa = $siswa_model->getAll();
    $all_tahun_ajaran = $tahun_ajaran_model->getAllTahunAjaran();
    $all_kelas = $kelas_model->getAll();

    $all_pemberi_izin = [];
    foreach ($guru_model->getAll() as $guru) {
        $all_pemberi_izin[] = ['id' => $guru['id'], 'nama_tampil' => $guru['nama_lengkap'], 'tipe' => 'Guru'];
    }
    $all_users_for_roles = $user_model->getAllUsersByRole(['Admin', 'TataUsaha']);
    foreach ($all_users_for_roles as $user) {
        $all_pemberi_izin[] = ['id' => $user['id'], 'nama_tampil' => $user['username'], 'tipe' => 'User'];
    }

    // Handle form submission (add/edit)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            $izin_model->siswa_id = $_POST['siswa_id'];
            $izin_model->jenis_izin = $_POST['jenis_izin'];
            $izin_model->tanggal_izin = $_POST['tanggal_izin'];
            $izin_model->keperluan = $_POST['keperluan'];
            $izin_model->diberi_izin_oleh_id = $_POST['diberi_izin_oleh_id'];
            $izin_model->catatan_admin = $_POST['catatan_admin'];

            // Handle specific fields based on permit type
            if ($izin_model->jenis_izin === 'Keluar') {
                $izin_model->waktu_keluar = $_POST['waktu_keluar'];
                $izin_model->waktu_kembali = !empty($_POST['waktu_kembali']) ? $_POST['waktu_kembali'] : null;
                $izin_model->sampai_tanggal = null;
            } else { // 'Pulang'
                $izin_model->waktu_keluar = null;
                $izin_model->waktu_kembali = null;
                $izin_model->sampai_tanggal = $_POST['sampai_tanggal'];
            }

            if ($_POST['action'] === 'add') {
                if ($izin_model->create()) {
                    set_flash('success', 'Catatan izin berhasil ditambahkan dan notifikasi dikirim ke antrean.');
                } else {
                    set_flash('error', 'Gagal menambahkan catatan izin.');
                }
            } elseif ($_POST['action'] === 'edit') {
                $izin_model->id = $_POST['id'];
                if ($izin_model->update()) {
                    set_flash('success', 'Catatan izin berhasil diperbarui dan notifikasi dikirim ke antrean.');
                } else {
                    set_flash('error', 'Gagal memperbarui catatan izin.');
                }
            }
        }
        header('Location: ' . ROOT_URL . 'admin/izin_keluar_pulang.php');
        exit;
    }

    // Handle delete request
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        if ($izin_model->delete($_GET['id'])) {
            set_flash('success', 'Catatan izin berhasil dihapus.');
        } else {
            set_flash('error', 'Gagal menghapus catatan izin.');
        }
        header('Location: ' . ROOT_URL . 'admin/izin_keluar_pulang.php');
        exit;
    }

    // Handle edit request
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $edit_izin = $izin_model->getById($_GET['id']);
        if (!$edit_izin) {
            set_flash('error', 'Catatan izin tidak ditemukan.');
            header('Location: ' . ROOT_URL . 'admin/izin_keluar_pulang.php');
            exit;
        }
    }

    // Apply filters for fetching all data
    $filters = [
        'siswa_id' => $filter_siswa_id,
        'jenis_izin' => $filter_jenis_izin,
        'start_date' => $filter_start_date,
        'end_date' => $filter_end_date,
        'tahun_ajaran_id' => $filter_tahun_ajaran_id,
        'kelas_id' => $filter_kelas_id,
    ];
    $izin_data = $izin_model->getAll($filters);

} catch (PDOException $e) {
    error_log("Manajemen Izin Keluar/Pulang Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Manajemen Izin Keluar/Pulang General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
}

require_once __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manajemen Izin Keluar/Pulang Siswa</h1>

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
        <h2 class="text-xl font-semibold mb-4"><?php echo $edit_izin ? 'Edit Catatan Izin' : 'Tambah Catatan Izin Baru'; ?></h2>
        <form action="" method="POST" class="space-y-4">
            <?php if ($edit_izin): ?>
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_izin['id']); ?>">
                <input type="hidden" name="action" value="edit">
            <?php else: ?>
                <input type="hidden" name="action" value="add">
            <?php endif; ?>

            <div>
                <label for="jenis_izin" class="block text-gray-700 text-sm font-bold mb-2">Jenis Izin:</label>
                <select id="jenis_izin" name="jenis_izin" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <option value="Keluar" <?php echo (isset($edit_izin['jenis_izin']) && $edit_izin['jenis_izin'] == 'Keluar') ? 'selected' : ''; ?>>Keluar</option>
                    <option value="Pulang" <?php echo (isset($edit_izin['jenis_izin']) && $edit_izin['jenis_izin'] == 'Pulang') ? 'selected' : ''; ?>>Pulang</option>
                </select>
            </div>
            <div>
                <label for="siswa_id" class="block text-gray-700 text-sm font-bold mb-2">Pilih Siswa:</label>
                <select id="siswa_id" name="siswa_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <option value="">Pilih Siswa</option>
                    <?php foreach ($all_siswa as $siswa): ?>
                        <option value="<?php echo htmlspecialchars($siswa['id']); ?>"
                            <?php echo (isset($edit_izin['siswa_id']) && $edit_izin['siswa_id'] == $siswa['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($siswa['nama_lengkap']); ?> (NISN: <?php echo htmlspecialchars($siswa['nisn']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="tanggal_izin" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Izin:</label>
                <input type="date" id="tanggal_izin" name="tanggal_izin" value="<?php echo htmlspecialchars($edit_izin['tanggal_izin'] ?? date('Y-m-d')); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>

            <div id="fields_keluar" class="<?php echo (isset($edit_izin['jenis_izin']) && $edit_izin['jenis_izin'] == 'Pulang') ? 'hidden' : ''; ?>">
                 <div>
                    <label for="waktu_keluar" class="block text-gray-700 text-sm font-bold mb-2">Waktu Keluar:</label>
                    <input type="time" id="waktu_keluar" name="waktu_keluar" value="<?php echo htmlspecialchars($edit_izin['waktu_keluar'] ?? date('H:i')); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                 <div>
                    <label for="waktu_kembali" class="block text-gray-700 text-sm font-bold mb-2">Waktu Kembali (Opsional):</label>
                    <input type="time" id="waktu_kembali" name="waktu_kembali" value="<?php echo htmlspecialchars($edit_izin['waktu_kembali'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
            </div>

            <div id="fields_pulang" class="<?php echo (isset($edit_izin['jenis_izin']) && $edit_izin['jenis_izin'] == 'Pulang') ? '' : 'hidden'; ?>">
                <div>
                    <label for="sampai_tanggal" class="block text-gray-700 text-sm font-bold mb-2">Sampai Tanggal (Opsional):</label>
                    <input type="date" id="sampai_tanggal" name="sampai_tanggal" value="<?php echo htmlspecialchars($edit_izin['sampai_tanggal'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
            </div>

            <div>
                <label for="keperluan" class="block text-gray-700 text-sm font-bold mb-2">Keperluan Izin:</label>
                <textarea id="keperluan" name="keperluan" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required><?php echo htmlspecialchars($edit_izin['keperluan'] ?? ''); ?></textarea>
            </div>
            <div>
                <label for="diberi_izin_oleh_id" class="block text-gray-700 text-sm font-bold mb-2">Diberi Izin Oleh:</label>
                <select id="diberi_izin_oleh_id" name="diberi_izin_oleh_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <option value="">Pilih Pemberi Izin</option>
                    <?php foreach ($all_pemberi_izin as $pemberi_izin): ?>
                        <option value="<?php echo htmlspecialchars($pemberi_izin['id']); ?>" <?php echo (isset($edit_izin['diberi_izin_oleh_id']) && $edit_izin['diberi_izin_oleh_id'] == $pemberi_izin['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pemberi_izin['nama_tampil']); ?> (<?php echo htmlspecialchars($pemberi_izin['tipe']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="catatan_admin" class="block text-gray-700 text-sm font-bold mb-2">Catatan Admin (Opsional):</label>
                <textarea id="catatan_admin" name="catatan_admin" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?php echo htmlspecialchars($edit_izin['catatan_admin'] ?? ''); ?></textarea>
            </div>
            <div class="flex items-center space-x-4">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                    <?php echo $edit_izin ? 'Perbarui Izin' : 'Tambah Izin'; ?>
                </button>
                <?php if ($edit_izin): ?>
                    <a href="<?php echo ROOT_URL; ?>admin/izin_keluar_pulang.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                        Batal
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-semibold mb-4">Filter Data Izin</h2>
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
            <div>
                <label for="filter_siswa_id" class="block text-gray-700 text-sm font-bold mb-2">Siswa:</label>
                <select id="filter_siswa_id" name="filter_siswa_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Siswa</option>
                    <?php foreach ($all_siswa as $siswa): ?>
                        <option value="<?php echo htmlspecialchars($siswa['id']); ?>" <?php echo ($filter_siswa_id == $siswa['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($siswa['nama_lengkap']); ?> (NISN: <?php echo htmlspecialchars($siswa['nisn']); ?>)
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
                <label for="filter_jenis_izin" class="block text-gray-700 text-sm font-bold mb-2">Jenis Izin:</label>
                <select id="filter_jenis_izin" name="filter_jenis_izin" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Jenis</option>
                    <?php foreach ($jenis_izin_options as $jenis_izin): ?>
                        <option value="<?php echo htmlspecialchars($jenis_izin); ?>" <?php echo ($filter_jenis_izin == $jenis_izin) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($jenis_izin); ?>
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
        <h2 class="text-xl font-semibold mb-4">Daftar Catatan Izin Keluar/Pulang</h2>
        <?php if (empty($izin_data)): ?>
            <p class="text-gray-600">Tidak ada catatan izin keluar/pulang.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                    <thead>
                        <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">No.</th>
                            <th class="py-3 px-6 text-left">Tahun Ajaran</th>
                            <th class="py-3 px-6 text-left">Siswa</th>
                            <th class="py-3 px-6 text-left">Kelas</th>
                            <th class="py-3 px-6 text-left">Jenis & Tanggal</th>
                            <th class="py-3 px-6 text-left">Keperluan</th>
                            <th class="py-3 px-6 text-left">Oleh</th>
                            <th class="py-3 px-6 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm">
                        <?php $no = 1; ?>
                        <?php foreach ($izin_data as $izin): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($izin['nama_tahun_ajaran'] ?? '-'); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($izin['nama_siswa']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($izin['nama_kelas'] ?? '-'); ?></td>
                                <td class="py-3 px-6">
                                    <strong><?php echo htmlspecialchars($izin['jenis_izin']); ?></strong><br>
                                    <?php if ($izin['jenis_izin'] === 'Keluar'): ?>
                                        <?php echo htmlspecialchars(format_date_indonesian($izin['tanggal_izin'])); ?><br>
                                        <small class="text-gray-500">Keluar: <?php echo htmlspecialchars(substr($izin['waktu_keluar'], 0, 5)); ?></small><br>
                                        <?php if ($izin['waktu_kembali']): ?>
                                            <small class="text-gray-500">Kembali: <?php echo htmlspecialchars(substr($izin['waktu_kembali'], 0, 5)); ?></small>
                                        <?php else: ?>
                                            <small class="text-gray-500">Belum Kembali</small>
                                        <?php endif; ?>
                                    <?php else: // Jenis Izin: Pulang ?>
                                        <small class="text-gray-500">Dari: <?php echo htmlspecialchars(format_date_indonesian($izin['tanggal_izin'])); ?></small><br>
                                        <small class="text-gray-500">Sampai: <?php echo htmlspecialchars(format_date_indonesian($izin['sampai_tanggal'] ?? 'Tidak Ditentukan')); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars(substr($izin['keperluan'], 0, 75)); ?><?php echo (strlen($izin['keperluan']) > 75) ? '...' : ''; ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($izin['nama_pemberi_izin'] ?? '-'); ?></td>
                                <td class="py-3 px-6 text-center whitespace-nowrap">
                                    <a href="<?php echo ROOT_URL; ?>admin/izin_keluar_pulang.php?action=edit&id=<?php echo htmlspecialchars($izin['id']); ?>" class="text-blue-600 hover:text-blue-900 mr-3">Edit</a>
                                    <a href="<?php echo ROOT_URL; ?>admin/izin_keluar_pulang.php?action=delete&id=<?php echo htmlspecialchars($izin['id']); ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Apakah Anda yakin ingin menghapus catatan izin ini?');">Hapus</a>
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
        const jenisIzinSelect = document.getElementById('jenis_izin');
        const fieldsKeluar = document.getElementById('fields_keluar');
        const fieldsPulang = document.getElementById('fields_pulang');
        const waktuKeluarInput = document.getElementById('waktu_keluar');
        const waktuKembaliInput = document.getElementById('waktu_kembali');
        const sampaiTanggalInput = document.getElementById('sampai_tanggal');

        function toggleFields() {
            if (jenisIzinSelect.value === 'Keluar') {
                fieldsKeluar.classList.remove('hidden');
                fieldsPulang.classList.add('hidden');
                waktuKeluarInput.required = true;
                waktuKembaliInput.required = false;
                sampaiTanggalInput.required = false;
            } else { // Pulang
                fieldsKeluar.classList.add('hidden');
                fieldsPulang.classList.remove('hidden');
                waktuKeluarInput.required = false;
                waktuKembaliInput.required = false;
                sampaiTanggalInput.required = true; // <-- Ganti ini menjadi 'true'
            }
        }

        // Initial state
        toggleFields();

        jenisIzinSelect.addEventListener('change', toggleFields);
    });
</script>

<?php require_once __DIR__ . '/../components/footer.php'; ?>