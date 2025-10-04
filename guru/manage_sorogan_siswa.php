<?php
// project-root/guru/manage_sorogan_siswa.php

session_start(); // Pastikan session dimulai

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/SoroganSiswa.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/KitabNadhom.php';
require_once __DIR__ . '/../classes/Guru.php';
require_once __DIR__ . '/../classes/Kelas.php'; // Untuk filter kelas

require_login();
// Hanya guru yang sedang login atau admin/tata usaha yang bisa mengakses halaman ini
// Guru yang login akan melihat data yang mereka catat atau data semua siswa
require_role(['Guru', 'Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$sorogan_model = null;
$siswa_model = null;
$kitab_nadhom_model = null;
$guru_model = null;
$kelas_model = null;
$pdo = null;

$sorogan_data = [];
$all_siswa = [];
$all_kitab_nadhom = [];
$all_guru = [];
$all_kelas = [];
$edit_sorogan = null;

// Get current logged-in user info
// Pastikan $_SESSION['user'] ada setelah require_login()
$current_user = $_SESSION['user'] ?? null; // --- PERBAIKAN: Menggunakan null coalescing operator ---
$current_user_id = $current_user['id'] ?? null;
$current_user_role = $current_user['role_name'] ?? null;
$current_user_related_id = $current_user['related_id'] ?? null; // guru_id jika peran Guru

// Filter variables
$filter_siswa_id = isset($_GET['filter_siswa_id']) ? (int)$_GET['filter_siswa_id'] : null;
$filter_kitab_nadhom_id = isset($_GET['filter_kitab_nadhom_id']) ? (int)$_GET['filter_kitab_nadhom_id'] : null;
$filter_guru_id = isset($_GET['filter_guru_id']) ? (int)$_GET['filter_guru_id'] : null;
$filter_status_bacaan = $_GET['filter_status_bacaan'] ?? '';
$filter_start_date = $_GET['filter_start_date'] ?? '';
$filter_end_date = $_GET['filter_end_date'] ?? '';
$filter_kelas_id = isset($_GET['filter_kelas_id']) ? (int)$_GET['filter_kelas_id'] : null;

$status_bacaan_options = ['Lulus', 'Mengulang', 'Belum Selesai'];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $sorogan_model = new SoroganSiswa($pdo);
    $siswa_model = new Siswa($pdo);
    $kitab_nadhom_model = new KitabNadhom($pdo);
    $guru_model = new Guru($pdo);
    $kelas_model = new Kelas($pdo);

    $all_siswa = $siswa_model->getAll();
    $all_kitab_nadhom = $kitab_nadhom_model->getAll();
    $all_guru = $guru_model->getAll(); // --- PERBAIKAN: Inisialisasi $all_guru di sini ---
    $all_kelas = $kelas_model->getAll();

    // Handle form submission (add/edit)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            $sorogan_model->siswa_id = $_POST['siswa_id'];
            $sorogan_model->kitab_nadhom_id = $_POST['kitab_nadhom_id'];
            $sorogan_model->guru_id = $_POST['guru_id'];
            $sorogan_model->tanggal_setor = $_POST['tanggal_setor'];
            $sorogan_model->halaman_dari = $_POST['halaman_dari'];
            $sorogan_model->halaman_sampai = $_POST['halaman_sampai'];
            $sorogan_model->keterangan_bacaan = $_POST['keterangan_bacaan'];
            $sorogan_model->nilai = !empty($_POST['nilai']) ? $_POST['nilai'] : null; // Nilai bisa null
            $sorogan_model->status_bacaan = $_POST['status_bacaan'];
            $sorogan_model->catatan_guru = $_POST['catatan_guru'];

            // Jika guru yang login, pastikan guru_id adalah ID guru tersebut
            if ($current_user_role === 'Guru' && $current_user_related_id) {
                $sorogan_model->guru_id = $current_user_related_id;
            }

            if ($_POST['action'] === 'add') {
                if ($sorogan_model->create()) {
                    set_flash('success', 'Catatan sorogan berhasil ditambahkan dan notifikasi dikirim ke antrean.');
                } else {
                    set_flash('error', 'Gagal menambahkan catatan sorogan.');
                }
            } elseif ($_POST['action'] === 'edit') {
                $sorogan_model->id = $_POST['id'];
                // Pastikan guru hanya bisa mengedit sorogan yang dia catat
                $original_sorogan = $sorogan_model->getById($sorogan_model->id);
                if ($current_user_role === 'Guru' && $original_sorogan['guru_id'] != $current_user_related_id) {
                    set_flash('error', 'Anda tidak memiliki izin untuk mengedit catatan sorogan ini.');
                } else {
                    if ($sorogan_model->update()) {
                        set_flash('success', 'Catatan sorogan berhasil diperbarui dan notifikasi dikirim ke antrean.');
                    } else {
                        set_flash('error', 'Gagal memperbarui catatan sorogan.');
                    }
                }
            }
        }
        header('Location: ' . ROOT_URL . 'guru/manage_sorogan_siswa.php'); // Arahkan kembali ke halaman guru
        exit;
    }

    // Handle delete request
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $sorogan_id = $_GET['id'];
        $original_sorogan = $sorogan_model->getById($sorogan_id);
        // Pastikan guru hanya bisa menghapus sorogan yang dia catat
        if ($current_user_role === 'Guru' && $original_sorogan['guru_id'] != $current_user_related_id) {
            set_flash('error', 'Anda tidak memiliki izin untuk menghapus catatan sorogan ini.');
        } else {
            if ($sorogan_model->delete($sorogan_id)) {
                set_flash('success', 'Catatan sorogan berhasil dihapus.');
            } else {
                set_flash('error', 'Gagal menghapus catatan sorogan.');
            }
        }
        header('Location: ' . ROOT_URL . 'guru/manage_sorogan_siswa.php'); // Arahkan kembali ke halaman guru
        exit;
    }

    // Handle edit request
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $edit_sorogan = $sorogan_model->getById($_GET['id']);
        if (!$edit_sorogan) {
            set_flash('error', 'Catatan sorogan tidak ditemukan.');
            header('Location: ' . ROOT_URL . 'guru/manage_sorogan_siswa.php'); // Arahkan kembali ke halaman guru
            exit;
        }
        // Jika guru yang mengedit, pastikan dia adalah guru penyimak yang mencatat
        if ($current_user_role === 'Guru' && $edit_sorogan['guru_id'] != $current_user_related_id) {
            set_flash('error', 'Anda tidak memiliki izin untuk mengedit catatan sorogan ini.');
            header('Location: ' . ROOT_URL . 'guru/manage_sorogan_siswa.php'); // Arahkan kembali ke halaman guru
            exit;
        }
    }

    // Apply filters for fetching all data
    $filters = [
        'siswa_id' => $filter_siswa_id,
        'kitab_nadhom_id' => $filter_kitab_nadhom_id,
        'guru_id' => $filter_guru_id,
        'status_bacaan' => $filter_status_bacaan,
        'start_date' => $filter_start_date,
        'end_date' => $filter_end_date,
        'kelas_id' => $filter_kelas_id
    ];

    // Jika user adalah guru, otomatis filter berdasarkan guru_id nya
    if ($current_user_role === 'Guru' && $current_user_related_id) {
        // Tampilkan hanya sorogan yang dicatat oleh guru ini
        $filters['guru_id'] = $current_user_related_id;
    }

    $sorogan_data = $sorogan_model->getAll($filters);

} catch (PDOException $e) {
    error_log("Manajemen Sorogan Siswa Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Manajemen Sorogan Siswa General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
}

require_once __DIR__ . '/../components/header_guru.php'; // Menggunakan header_guru
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manajemen Sorogan Siswa</h1>

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
        <h2 class="text-xl font-semibold mb-4"><?php echo $edit_sorogan ? 'Edit Catatan Sorogan' : 'Tambah Catatan Sorogan Baru'; ?></h2>
        <form action="" method="POST" class="space-y-4">
            <?php if ($edit_sorogan): ?>
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_sorogan['id']); ?>">
                <input type="hidden" name="action" value="edit">
            <?php else: ?>
                <input type="hidden" name="action" value="add">
            <?php endif; ?>
            
            <div>
                <label for="siswa_id" class="block text-gray-700 text-sm font-bold mb-2">Siswa:</label>
                <select id="siswa_id" name="siswa_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <option value="">Pilih Siswa</option>
                    <?php foreach ($all_siswa as $siswa): ?>
                        <option value="<?php echo htmlspecialchars($siswa['id']); ?>" <?php echo (isset($edit_sorogan['siswa_id']) && $edit_sorogan['siswa_id'] == $siswa['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($siswa['nama_lengkap']); ?> (NISN: <?php echo htmlspecialchars($siswa['nisn'] ?? '-'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="kitab_nadhom_id" class="block text-gray-700 text-sm font-bold mb-2">Kitab/Nadhom:</label>
                <select id="kitab_nadhom_id" name="kitab_nadhom_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <option value="">Pilih Kitab/Nadhom</option>
                    <?php foreach ($all_kitab_nadhom as $kitab): ?>
                        <option value="<?php echo htmlspecialchars($kitab['id']); ?>" <?php echo (isset($edit_sorogan['kitab_nadhom_id']) && $edit_sorogan['kitab_nadhom_id'] == $kitab['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($kitab['nama_kitab']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="tanggal_setor" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Setor:</label>
                <input type="date" id="tanggal_setor" name="tanggal_setor" value="<?php echo htmlspecialchars($edit_sorogan['tanggal_setor'] ?? date('Y-m-d')); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div>
                <label for="halaman_dari" class="block text-gray-700 text-sm font-bold mb-2">Halaman/Bab Dari:</label>
                <input type="text" id="halaman_dari" name="halaman_dari" value="<?php echo htmlspecialchars($edit_sorogan['halaman_dari'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div>
                <label for="halaman_sampai" class="block text-gray-700 text-sm font-bold mb-2">Halaman/Bab Sampai:</label>
                <input type="text" id="halaman_sampai" name="halaman_sampai" value="<?php echo htmlspecialchars($edit_sorogan['halaman_sampai'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div>
                <label for="keterangan_bacaan" class="block text-gray-700 text-sm font-bold mb-2">Keterangan Bacaan (Opsional):</label>
                <textarea id="keterangan_bacaan" name="keterangan_bacaan" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?php echo htmlspecialchars($edit_sorogan['keterangan_bacaan'] ?? ''); ?></textarea>
            </div>
            <div>
                <label for="nilai" class="block text-gray-700 text-sm font-bold mb-2">Nilai (Opsional, 0-100):</label>
                <input type="number" step="0.01" min="0" max="100" id="nilai" name="nilai" value="<?php echo htmlspecialchars($edit_sorogan['nilai'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div>
                <label for="status_bacaan" class="block text-gray-700 text-sm font-bold mb-2">Status Bacaan:</label>
                <select id="status_bacaan" name="status_bacaan" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <?php foreach ($status_bacaan_options as $status): ?>
                        <option value="<?php echo htmlspecialchars($status); ?>" <?php echo (isset($edit_sorogan['status_bacaan']) && $edit_sorogan['status_bacaan'] == $status) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($status); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="catatan_guru" class="block text-gray-700 text-sm font-bold mb-2">Catatan Guru (Opsional):</label>
                <textarea id="catatan_guru" name="catatan_guru" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?php echo htmlspecialchars($edit_sorogan['catatan_guru'] ?? ''); ?></textarea>
            </div>
            <!-- Bidang "Guru Penyimak" akan disembunyikan untuk guru, karena akan otomatis terisi -->
            <?php if ($current_user_role !== 'Guru'): ?>
            <div>
                <label for="guru_id" class="block text-gray-700 text-sm font-bold mb-2">Guru Penyimak:</label>
                <select id="guru_id" name="guru_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <option value="">Pilih Guru</option>
                    <?php foreach ($all_guru as $guru): ?>
                        <option value="<?php echo htmlspecialchars($guru['id']); ?>" <?php echo (isset($edit_sorogan['guru_id']) && $edit_sorogan['guru_id'] == $guru['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($guru['nama_lengkap']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
                <input type="hidden" name="guru_id" value="<?php echo htmlspecialchars($current_user_related_id); ?>">
            <?php endif; ?>
            <div class="flex items-center space-x-4">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                    <?php echo $edit_sorogan ? 'Perbarui Sorogan' : 'Tambah Sorogan'; ?>
                </button>
                <?php if ($edit_sorogan): ?>
                    <a href="<?php echo ROOT_URL; ?>guru/manage_sorogan_siswa.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                        Batal
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-semibold mb-4">Filter Data Sorogan</h2>
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
            <div>
                <label for="filter_kelas_id" class="block text-gray-700 text-sm font-bold mb-2">Kelas Siswa:</label>
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
                <label for="filter_kitab_nadhom_id" class="block text-gray-700 text-sm font-bold mb-2">Kitab/Nadhom:</label>
                <select id="filter_kitab_nadhom_id" name="filter_kitab_nadhom_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Kitab/Nadhom</option>
                    <?php foreach ($all_kitab_nadhom as $kitab): ?>
                        <option value="<?php echo htmlspecialchars($kitab['id']); ?>" <?php echo ($filter_kitab_nadhom_id == $kitab['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($kitab['nama_kitab']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($current_user_role !== 'Guru'): ?>
            <div>
                <label for="filter_guru_id" class="block text-gray-700 text-sm font-bold mb-2">Guru Penyimak:</label>
                <select id="filter_guru_id" name="filter_guru_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Guru</option>
                    <?php foreach ($all_guru as $guru): ?>
                        <option value="<?php echo htmlspecialchars($guru['id']); ?>" <?php echo ($filter_guru_id == $guru['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($guru['nama_lengkap']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div>
                <label for="filter_status_bacaan" class="block text-gray-700 text-sm font-bold mb-2">Status Bacaan:</label>
                <select id="filter_status_bacaan" name="filter_status_bacaan" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Status</option>
                    <?php foreach ($status_bacaan_options as $status): ?>
                        <option value="<?php echo htmlspecialchars($status); ?>" <?php echo ($filter_status_bacaan == $status) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($status); ?>
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
        <h2 class="text-xl font-semibold mb-4">Daftar Catatan Sorogan Siswa</h2>
        <?php if (empty($sorogan_data)): ?>
            <p class="text-gray-600">Tidak ada catatan sorogan siswa.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                    <thead>
                        <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">No.</th>
                            <th class="py-3 px-6 text-left">Siswa</th>
                            <th class="py-3 px-6 text-left">Kelas</th>
                            <th class="py-3 px-6 text-left">Kitab/Nadhom</th>
                            <th class="py-3 px-6 text-left">Guru</th>
                            <th class="py-3 px-6 text-left">Tanggal</th>
                            <th class="py-3 px-6 text-left">Halaman/Bab</th>
                            <th class="py-3 px-6 text-center">Status</th>
                            <th class="py-3 px-6 text-center">Nilai</th>
                            <th class="py-3 px-6 text-left">Keterangan</th>
                            <th class="py-3 px-6 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm">
                        <?php $no = 1; ?>
                        <?php foreach ($sorogan_data as $sorogan): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($sorogan['nama_siswa']); ?> (NISN: <?php echo htmlspecialchars($sorogan['nisn'] ?? '-'); ?>)</td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($sorogan['nama_kelas'] ?? '-'); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($sorogan['nama_kitab']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($sorogan['nama_guru']); ?></td>
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars(format_date_indonesian($sorogan['tanggal_setor'])); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($sorogan['halaman_dari']); ?> - <?php echo htmlspecialchars($sorogan['halaman_sampai']); ?></td>
                                <td class="py-3 px-6 text-center">
                                    <span class="px-2 py-1 font-semibold leading-tight rounded-full 
                                        <?php
                                            switch ($sorogan['status_bacaan']) {
                                                case 'Lulus': echo 'bg-green-100 text-green-800'; break;
                                                case 'Mengulang': echo 'bg-yellow-100 text-yellow-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800'; break; // Belum Selesai
                                            }
                                        ?>">
                                        <?php echo htmlspecialchars($sorogan['status_bacaan']); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-6 text-center"><?php echo htmlspecialchars(number_format($sorogan['nilai'] ?? 0, 2)); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars(substr($sorogan['keterangan_bacaan'] ?? '-', 0, 50)); ?><?php echo (strlen($sorogan['keterangan_bacaan'] ?? '') > 50) ? '...' : ''; ?></td>
                                <td class="py-3 px-6 text-center whitespace-nowrap">
                                    <a href="<?php echo ROOT_URL; ?>guru/manage_sorogan_siswa.php?action=edit&id=<?php echo htmlspecialchars($sorogan['id']); ?>" class="text-blue-600 hover:text-blue-900 mr-3">Edit</a>
                                    <a href="<?php echo ROOT_URL; ?>guru/manage_sorogan_siswa.php?action=delete&id=<?php echo htmlspecialchars($sorogan['id']); ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Apakah Anda yakin ingin menghapus catatan sorogan ini?');">Hapus</a>
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
