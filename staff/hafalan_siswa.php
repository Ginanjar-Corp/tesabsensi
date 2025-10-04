<?php
// project-root/admin/hafalan_siswa.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/HafalanSiswa.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/KitabNadhom.php';
require_once __DIR__ . '/../classes/Guru.php';
require_once __DIR__ . '/../classes/TahunAjaran.php'; // Tambahkan ini

require_login();
require_role(['Admin', 'TataUsaha', 'Guru']);

$error_message = flash('error');
$success_message = flash('success');

$hafalan_model = null;
$siswa_model = null;
$kitab_nadhom_model = null;
$guru_model = null;
$tahun_ajaran_model = null; // Tambahkan
$pdo = null;

$hafalan_data = [];
$all_siswa = [];
$all_kitab_nadhom = [];
$all_guru = [];
$all_tahun_ajaran = []; // Tambahkan
$edit_hafalan = null;

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $hafalan_model = new HafalanSiswa($pdo);
    $siswa_model = new Siswa($pdo);
    $kitab_nadhom_model = new KitabNadhom($pdo);
    $guru_model = new Guru($pdo);
    $tahun_ajaran_model = new TahunAjaran($pdo); // Inisialisasi

    $all_siswa = $siswa_model->getAll();
    $all_kitab_nadhom = $kitab_nadhom_model->getAll();
    $all_guru = $guru_model->getAll();
    $all_tahun_ajaran = $tahun_ajaran_model->getAll(); // Ambil semua tahun ajaran

    // Handle form submission (add/edit)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            $tahun_ajaran_id = $_POST['tahun_ajaran_id'] ?? null;
            $kitab_nadhom_id = $_POST['kitab_nadhom_id'] ?? null;
            $bagian_hafalan = $_POST['bagian_hafalan'] ?? null;
            $nilai = $_POST['nilai'] ?? null;
            $catatan = $_POST['catatan'] ?? null;
            $jenis_setor = $_POST['jenis_setor'] ?? 'Setoran Baru';
            $siswa_id = $_POST['siswa_id'] ?? null;
            $guru_id = $_POST['guru_id'] ?? null;

            if ($_POST['action'] === 'add') {
                $hafalan_model->siswa_id = $siswa_id;
                $hafalan_model->tahun_ajaran_id = $tahun_ajaran_id;
                $hafalan_model->kitab_nadhom_id = $kitab_nadhom_id;
                $hafalan_model->tanggal_setor = date('Y-m-d');
                $hafalan_model->bagian_hafalan = $bagian_hafalan;
                $hafalan_model->nilai = $nilai;
                $hafalan_model->guru_id = $guru_id;
                $hafalan_model->catatan = $catatan;
                $hafalan_model->jenis_setor = $jenis_setor;

                if ($hafalan_model->create()) {
                    set_flash('success', 'Catatan hafalan berhasil ditambahkan.');
                } else {
                    set_flash('error', 'Gagal menambahkan catatan hafalan.');
                }
            } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
                $hafalan_model->id = $_POST['id'];
                $hafalan_model->siswa_id = $siswa_id;
                $hafalan_model->tahun_ajaran_id = $tahun_ajaran_id;
                $hafalan_model->kitab_nadhom_id = $kitab_nadhom_id;
                $hafalan_model->tanggal_setor = $_POST['tanggal_setor'];
                $hafalan_model->bagian_hafalan = $bagian_hafalan;
                $hafalan_model->nilai = $nilai;
                $hafalan_model->guru_id = $guru_id;
                $hafalan_model->catatan = $catatan;
                $hafalan_model->jenis_setor = $jenis_setor;

                if ($hafalan_model->update()) {
                    set_flash('success', 'Catatan hafalan berhasil diperbarui.');
                } else {
                    set_flash('error', 'Gagal memperbarui catatan hafalan.');
                }
            }
            header("Location: " . ROOT_URL . "admin/hafalan_siswa.php");
            exit();
        }
    }

    // Handle delete action
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        if ($hafalan_model->delete($_GET['id'])) {
            set_flash('success', 'Catatan hafalan berhasil dihapus.');
        } else {
            set_flash('error', 'Gagal menghapus catatan hafalan.');
        }
        header("Location: " . ROOT_URL . "admin/hafalan_siswa.php");
        exit();
    }

    // Handle edit form population
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $edit_hafalan = $hafalan_model->getById($_GET['id']);
    }

    // Handle search/filter
    $filter_siswa_id = $_GET['siswa_id'] ?? '';
    $filter_kitab_id = $_GET['kitab_id'] ?? '';
    $filter_tanggal_mulai = $_GET['tanggal_mulai'] ?? '';
    $filter_tanggal_selesai = $_GET['tanggal_selesai'] ?? '';
    $filter_tahun_ajaran = $_GET['tahun_ajaran'] ?? '';
    $filter_jenis_setor = $_GET['jenis_setor'] ?? '';

    $hafalan_data = $hafalan_model->getFiltered(
        $filter_siswa_id,
        $filter_kitab_id,
        $filter_tanggal_mulai,
        $filter_tanggal_selesai,
        $filter_tahun_ajaran,
        $filter_jenis_setor
    );

} catch (Exception $e) {
    error_log("Error in hafalan_siswa.php: " . $e->getMessage());
    $error_message = "Terjadi kesalahan pada database.";
}

$pageTitle = "Manajemen Hafalan Siswa";
include_once __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto px-4 mt-8">
    <h1 class="text-2xl font-bold mb-4">Manajemen Hafalan Siswa</h1>

    <?php if ($error_message) : ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo $error_message; ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success_message) : ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo $success_message; ?></span>
        </div>
    <?php endif; ?>

    <!-- Form Tambah/Edit Hafalan -->
    <div class="bg-white shadow-md rounded-lg p-6 mb-8">
        <h2 class="text-xl font-semibold mb-4"><?php echo $edit_hafalan ? 'Edit' : 'Tambah'; ?> Catatan Hafalan</h2>
        <form action="<?php echo ROOT_URL; ?>admin/hafalan_siswa.php" method="POST">
            <input type="hidden" name="action" value="<?php echo $edit_hafalan ? 'edit' : 'add'; ?>">
            <?php if ($edit_hafalan) : ?>
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_hafalan['id']); ?>">
            <?php endif; ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-4">
                    <label for="siswa_id" class="block text-gray-700 text-sm font-bold mb-2">Nama Siswa</label>
                    <select name="siswa_id" id="siswa_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        <option value="">Pilih Siswa</option>
                        <?php foreach ($all_siswa as $siswa) : ?>
                            <option value="<?php echo htmlspecialchars($siswa['id']); ?>" <?php echo $edit_hafalan && $edit_hafalan['siswa_id'] == $siswa['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($siswa['nama_lengkap']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="tahun_ajaran_id" class="block text-gray-700 text-sm font-bold mb-2">Tahun Ajaran</label>
                    <select name="tahun_ajaran_id" id="tahun_ajaran_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        <option value="">Pilih Tahun Ajaran</option>
                        <?php foreach ($all_tahun_ajaran as $ta) : ?>
                            <option value="<?php echo htmlspecialchars($ta['id']); ?>" <?php echo $edit_hafalan && $edit_hafalan['tahun_ajaran_id'] == $ta['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ta['tahun_ajaran']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="kitab_nadhom_id" class="block text-gray-700 text-sm font-bold mb-2">Kitab/Nadhom</label>
                    <select name="kitab_nadhom_id" id="kitab_nadhom_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        <option value="">Pilih Kitab/Nadhom</option>
                        <?php foreach ($all_kitab_nadhom as $kitab) : ?>
                            <option value="<?php echo htmlspecialchars($kitab['id']); ?>" data-jenis="<?php echo htmlspecialchars($kitab['jenis_kitab']); ?>" <?php echo $edit_hafalan && $edit_hafalan['kitab_nadhom_id'] == $kitab['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($kitab['nama_kitab']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Kolom Jenis Setor, hanya muncul jika jenis kitab adalah 'Hafalan' -->
                <div class="mb-4 hidden" id="jenis_setor_container">
                    <label for="jenis_setor" class="block text-gray-700 text-sm font-bold mb-2">Jenis Setor</label>
                    <select name="jenis_setor" id="jenis_setor" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        <option value="Setoran Baru" <?php echo $edit_hafalan && $edit_hafalan['jenis_setor'] === 'Setoran Baru' ? 'selected' : ''; ?>>Setoran Baru</option>
                        <option value="Mengulang" <?php echo $edit_hafalan && $edit_hafalan['jenis_setor'] === 'Mengulang' ? 'selected' : ''; ?>>Mengulang</option>
                    </select>
                </div>
                <!-- Kolom Nilai, hanya muncul jika Jenis Setor adalah 'Mengulang' -->
                <div class="mb-4 hidden" id="nilai_container">
                    <label for="nilai" class="block text-gray-700 text-sm font-bold mb-2">Nilai</label>
                    <input type="number" step="0.01" name="nilai" id="nilai" value="<?php echo $edit_hafalan ? htmlspecialchars($edit_hafalan['nilai']) : ''; ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" min="0" max="100">
                </div>
                <div class="mb-4">
                    <label for="tanggal_setor" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Setor</label>
                    <input type="date" name="tanggal_setor" id="tanggal_setor" value="<?php echo $edit_hafalan ? htmlspecialchars($edit_hafalan['tanggal_setor']) : date('Y-m-d'); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                <div class="mb-4">
                    <label for="bagian_hafalan" class="block text-gray-700 text-sm font-bold mb-2">Bagian Hafalan</label>
                    <input type="text" name="bagian_hafalan" id="bagian_hafalan" value="<?php echo $edit_hafalan ? htmlspecialchars($edit_hafalan['bagian_hafalan']) : ''; ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                <div class="mb-4">
                    <label for="guru_id" class="block text-gray-700 text-sm font-bold mb-2">Guru</label>
                    <select name="guru_id" id="guru_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        <option value="">Pilih Guru</option>
                        <?php foreach ($all_guru as $guru) : ?>
                            <option value="<?php echo htmlspecialchars($guru['id']); ?>" <?php echo $edit_hafalan && $edit_hafalan['guru_id'] == $guru['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($guru['nama_lengkap']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4 col-span-1 md:col-span-2">
                    <label for="catatan" class="block text-gray-700 text-sm font-bold mb-2">Catatan</label>
                    <textarea name="catatan" id="catatan" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?php echo $edit_hafalan ? htmlspecialchars($edit_hafalan['catatan']) : ''; ?></textarea>
                </div>
            </div>
            <div class="flex items-center justify-between">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <?php echo $edit_hafalan ? 'Perbarui' : 'Tambah'; ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Filter/Pencarian -->
    <div class="bg-white shadow-md rounded-lg p-6 mb-8">
        <h2 class="text-xl font-semibold mb-4">Filter Data Hafalan</h2>
        <form action="<?php echo ROOT_URL; ?>admin/hafalan_siswa.php" method="GET">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="mb-4">
                    <label for="filter_siswa_id" class="block text-gray-700 text-sm font-bold mb-2">Siswa</label>
                    <select name="siswa_id" id="filter_siswa_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="">Semua Siswa</option>
                        <?php foreach ($all_siswa as $siswa) : ?>
                            <option value="<?php echo htmlspecialchars($siswa['id']); ?>" <?php echo $filter_siswa_id == $siswa['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($siswa['nama_lengkap']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="filter_kitab_id" class="block text-gray-700 text-sm font-bold mb-2">Kitab/Nadhom</label>
                    <select name="kitab_id" id="filter_kitab_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="">Semua Kitab</option>
                        <?php foreach ($all_kitab_nadhom as $kitab) : ?>
                            <option value="<?php echo htmlspecialchars($kitab['id']); ?>" <?php echo $filter_kitab_id == $kitab['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($kitab['nama_kitab']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="filter_tahun_ajaran" class="block text-gray-700 text-sm font-bold mb-2">Tahun Ajaran</label>
                    <select name="tahun_ajaran" id="filter_tahun_ajaran" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="">Semua Tahun Ajaran</option>
                        <?php foreach ($all_tahun_ajaran as $ta) : ?>
                            <option value="<?php echo htmlspecialchars($ta['id']); ?>" <?php echo $filter_tahun_ajaran == $ta['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ta['nama']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="filter_jenis_setor" class="block text-gray-700 text-sm font-bold mb-2">Jenis Setor</label>
                    <select name="jenis_setor" id="filter_jenis_setor" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="">Semua Jenis</option>
                        <option value="Setoran Baru" <?php echo $filter_jenis_setor === 'Setoran Baru' ? 'selected' : ''; ?>>Setoran Baru</option>
                        <option value="Mengulang" <?php echo $filter_jenis_setor === 'Mengulang' ? 'selected' : ''; ?>>Mengulang</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="filter_tanggal_mulai" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Mulai</label>
                    <input type="date" name="tanggal_mulai" id="filter_tanggal_mulai" value="<?php echo htmlspecialchars($filter_tanggal_mulai); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-4">
                    <label for="filter_tanggal_selesai" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Selesai</label>
                    <input type="date" name="tanggal_selesai" id="filter_tanggal_selesai" value="<?php echo htmlspecialchars($filter_tanggal_selesai); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
            </div>
            <div class="flex items-center justify-end">
                <button type="submit" class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Tabel Data Hafalan -->
    <div class="bg-white shadow-md rounded-lg p-6">
        <h2 class="text-xl font-semibold mb-4">Daftar Catatan Hafalan</h2>
        <?php if (empty($hafalan_data)) : ?>
            <p class="text-gray-500">Tidak ada data hafalan yang ditemukan.</p>
        <?php else : ?>
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto border-collapse">
                    <thead>
                        <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">Siswa</th>
                            <th class="py-3 px-6 text-left">Kitab</th>
                            <th class="py-3 px-6 text-left">Tgl Setor</th>
                            <th class="py-3 px-6 text-left">Jenis Setor</th>
                            <th class="py-3 px-6 text-left">Bagian</th>
                            <th class="py-3 px-6 text-left">Nilai</th>
                            <th class="py-3 px-6 text-left">Guru</th>
                            <th class="py-3 px-6 text-left">Catatan</th>
                            <th class="py-3 px-6 text-center">Status</th>
                            <th class="py-3 px-6 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 text-sm font-light">
                        <?php foreach ($hafalan_data as $hafalan) : ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-100">
                                <td class="py-3 px-6 text-left whitespace-nowrap"><?php echo htmlspecialchars($hafalan['nama_siswa']); ?></td>
                                <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($hafalan['nama_kitab']); ?></td>
                                <td class="py-3 px-6 text-left"><?php echo format_tanggal_indo($hafalan['tanggal_setor']); ?></td>
                                <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($hafalan['jenis_setor']); ?></td>
                                <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($hafalan['bagian_hafalan']); ?></td>
                                <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($hafalan['nilai']); ?></td>
                                <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($hafalan['nama_guru']); ?></td>
                                <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($hafalan['catatan']); ?></td>
                                <td class="py-3 px-6 text-center">
                                    <span class="py-1 px-3 rounded-full text-xs <?php
                                                                                switch ($hafalan['status_hafalan']) {
                                                                                    case 'Mencapai Target':
                                                                                        echo 'bg-green-200 text-green-600';
                                                                                        break;
                                                                                    case 'Masih Kurang':
                                                                                        echo 'bg-yellow-200 text-yellow-600';
                                                                                        break;
                                                                                    case 'Tidak Berlaku':
                                                                                        echo 'bg-gray-200 text-gray-600';
                                                                                        break;
                                                                                    case 'Belum Setor':
                                                                                    default:
                                                                                        echo 'bg-red-200 text-red-600';
                                                                                        break;
                                                                                }
                                                                                ?>">
                                        <?php echo htmlspecialchars($hafalan['status_hafalan']); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-6 text-center whitespace-nowrap">
                                    <a href="<?php echo ROOT_URL; ?>admin/hafalan_siswa.php?action=edit&id=<?php echo htmlspecialchars($hafalan['id']); ?>" class="text-blue-600 hover:text-blue-900 mr-2">Edit</a>
                                    <a href="<?php echo ROOT_URL; ?>admin/hafalan_siswa.php?action=delete&id=<?php echo htmlspecialchars($hafalan['id']); ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Apakah Anda yakin ingin menghapus catatan hafalan ini?');">Hapus</a>
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
        const kitabNadhomSelect = document.getElementById('kitab_nadhom_id');
        const jenisSetorContainer = document.getElementById('jenis_setor_container');
        const jenisSetorSelect = document.getElementById('jenis_setor');
        const nilaiContainer = document.getElementById('nilai_container');
        const nilaiInput = document.getElementById('nilai');

        function toggleNilai() {
            if (jenisSetorSelect.value === 'Mengulang') {
                nilaiContainer.classList.remove('hidden');
                nilaiInput.setAttribute('required', 'required');
            } else {
                nilaiContainer.classList.add('hidden');
                nilaiInput.removeAttribute('required');
            }
        }

        function toggleJenisSetor() {
            const selectedOption = kitabNadhomSelect.options[kitabNadhomSelect.selectedIndex];
            const jenisKitab = selectedOption.getAttribute('data-jenis');

            if (jenisKitab === 'Hafalan') {
                jenisSetorContainer.classList.remove('hidden');
                jenisSetorSelect.setAttribute('required', 'required');
            } else {
                jenisSetorContainer.classList.add('hidden');
                jenisSetorSelect.removeAttribute('required');
            }

            // Panggil toggleNilai setiap kali jenis kitab berubah untuk memastikan nilai juga disesuaikan
            toggleNilai();
        }

        kitabNadhomSelect.addEventListener('change', toggleJenisSetor);
        jenisSetorSelect.addEventListener('change', toggleNilai);

        // Panggil saat halaman dimuat untuk setel status awal
        toggleJenisSetor();
    });
</script>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
