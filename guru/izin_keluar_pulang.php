<?php
// project-root/guru/izin_keluar_pulang.php

session_start(); // Pastikan session dimulai

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/IzinKeluarPulang.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/Guru.php';
require_once __DIR__ . '/../classes/User.php'; // Untuk mendapatkan username user admin/tata usaha yang memberi izin
require_once __DIR__ . '/../classes/Kelas.php'; // Untuk filter kelas

require_login();
// Hanya guru yang sedang login atau admin/tata usaha yang bisa mengakses halaman ini
// Guru yang login akan melihat data yang mereka catat atau data semua siswa
require_role(['Guru', 'Admin', 'TataUsaha']); 

$error_message = flash('error');
$success_message = flash('success');

$izin_model = null;
$siswa_model = null;
$guru_model = null;
$user_model = null;
$kelas_model = null;
$pdo = null;

$izin_data = [];
$all_siswa = [];
$all_guru = [];
$all_pemberi_izin = []; // Gabungan user dan guru
$all_kelas = [];
$edit_izin = null;

// Get current logged-in user info
// Pastikan $_SESSION['user'] ada setelah require_login()
$current_user = $_SESSION['user'] ?? null; 
$current_user_id = null;
$current_user_role = null;
$current_user_related_id = null;

// Safely assign values only if $current_user is an array
if (is_array($current_user)) {
// GANTI DENGAN INI (JIKA BELUM ADA)
$current_user_id = $current_user['id'] ?? null;
$current_user_role = $current_user['role_name'] ?? null;
$current_user_related_id = $current_user['related_id'] ?? null;
}

// Filter variables
$filter_tipe_pengguna = $_GET['filter_tipe_pengguna'] ?? '';
$filter_pengguna_id = isset($_GET['filter_pengguna_id']) ? (int)$_GET['filter_pengguna_id'] : null;
$filter_status_izin = $_GET['filter_status_izin'] ?? '';
$filter_start_date = $_GET['filter_start_date'] ?? '';
$filter_end_date = $_GET['filter_end_date'] ?? '';
$filter_kelas_id = isset($_GET['filter_kelas_id']) ? (int)$_GET['filter_kelas_id'] : null;

$status_options = [
    'Pending', 'Disetujui', 'Ditolak', 'Selesai'
];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $izin_model = new IzinKeluarPulang($pdo);
    $siswa_model = new Siswa($pdo);
    $guru_model = new Guru($pdo);
    $user_model = new User($pdo); // Inisialisasi User model
    $kelas_model = new Kelas($pdo);

    $all_siswa = $siswa_model->getAll();
    $all_guru = $guru_model->getAll();
    $all_kelas = $kelas_model->getAll();

    // Menggabungkan daftar guru dan user (Admin/Tata Usaha) sebagai 'pemberi izin'
    $all_pemberi_izin = [];
    foreach ($all_guru as $guru) {
        $all_pemberi_izin[] = ['id' => $guru['id'], 'nama_tampil' => $guru['nama_lengkap'], 'tipe' => 'Guru'];
    }
    // Ambil user dengan peran Admin atau TataUsaha atau Guru (jika guru juga bisa menjadi pemberi izin dari user_id)
    $all_users_for_roles = $user_model->getAllUsersByRole(['Admin', 'TataUsaha', 'Guru']); 
    foreach ($all_users_for_roles as $user) {
        // Hanya tambahkan jika user tersebut bukan guru yang sudah ditambahkan dari tabel guru
        // Ini untuk menghindari duplikasi jika guru juga punya akun di tabel users
        $is_guru_already_added = false;
        foreach ($all_guru as $guru_check) {
            if ($user['related_id'] == $guru_check['id'] && $user['role_name'] == 'Guru') {
                $is_guru_already_added = true;
                break;
            }
        }
        if (!$is_guru_already_added) {
            $all_pemberi_izin[] = ['id' => $user['id'], 'nama_tampil' => $user['username'], 'tipe' => 'User (' . $user['role_name'] . ')']; 
        }
    }


    // Handle form submission (add/edit)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            $izin_model->tipe_pengguna = $_POST['tipe_pengguna'];
            $izin_model->pengguna_id = $_POST['pengguna_id'];
            $izin_model->tanggal_izin = $_POST['tanggal_izin'];
            $izin_model->waktu_keluar = $_POST['waktu_keluar'];
            $izin_model->waktu_kembali = !empty($_POST['waktu_kembali']) ? $_POST['waktu_kembali'] : null; // Bisa null
            $izin_model->keperluan = $_POST['keperluan'];
            $izin_model->diberi_izin_oleh_id = $_POST['diberi_izin_oleh_id'];
            $izin_model->status_izin = $_POST['status_izin'];
            $izin_model->catatan_admin = $_POST['catatan_admin'];

            // Jika guru yang login, pastikan diberi_izin_oleh_id adalah ID guru tersebut
            // Ini akan memastikan bahwa guru hanya bisa mencatat izin atas nama mereka sendiri
            // Admin/TU bisa memilih dari daftar
            if ($current_user_role === 'Guru' && $current_user_related_id !== null) { // --- PERBAIKAN: Tambahkan pemeriksaan null ---
                $izin_model->diberi_izin_oleh_id = $current_user_related_id;
            }

            if ($_POST['action'] === 'add') {
                if ($izin_model->create()) {
                    set_flash('success', 'Catatan izin berhasil ditambahkan dan notifikasi dikirim ke antrean.');
                } else {
                    set_flash('error', 'Gagal menambahkan catatan izin.');
                }
            } elseif ($_POST['action'] === 'edit') {
                $izin_model->id = $_POST['id'];
                // Pastikan guru hanya bisa mengedit izin yang dia berikan
                $original_izin = $izin_model->getById($izin_model->id);
                if ($current_user_role === 'Guru' && $current_user_related_id !== null && $original_izin['diberi_izin_oleh_id'] != $current_user_related_id) { // --- PERBAIKAN: Tambahkan pemeriksaan null ---
                    set_flash('error', 'Anda tidak memiliki izin untuk mengedit catatan izin ini.');
                } else {
                    if ($izin_model->update()) {
                        set_flash('success', 'Catatan izin berhasil diperbarui dan notifikasi dikirim ke antrean.');
                    } else {
                        set_flash('error', 'Gagal memperbarui catatan izin.');
                    }
                }
            }
        }
        header('Location: ' . ROOT_URL . 'guru/izin_keluar_pulang.php'); // Arahkan kembali ke halaman guru
        exit;
    }

    // Handle delete request
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $izin_id = $_GET['id'];
        $original_izin = $izin_model->getById($izin_id);
        // Pastikan guru hanya bisa menghapus izin yang dia berikan
        if ($current_user_role === 'Guru' && $current_user_related_id !== null && $original_izin['diberi_izin_oleh_id'] != $current_user_related_id) { // --- PERBAIKAN: Tambahkan pemeriksaan null ---
            set_flash('error', 'Anda tidak memiliki izin untuk menghapus catatan izin ini.');
        } else {
            if ($izin_model->delete($izin_id)) {
                set_flash('success', 'Catatan izin berhasil dihapus.');
            } else {
                set_flash('error', 'Gagal menghapus catatan izin.');
            }
        }
        header('Location: ' . ROOT_URL . 'guru/izin_keluar_pulang.php'); // Arahkan kembali ke halaman guru
        exit;
    }

    // Handle edit request
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $edit_izin = $izin_model->getById($_GET['id']);
        if (!$edit_izin) {
            set_flash('error', 'Catatan izin tidak ditemukan.');
            header('Location: ' . ROOT_URL . 'guru/izin_keluar_pulang.php'); // Arahkan kembali ke halaman guru
            exit;
        }
        // Jika guru yang mengedit, pastikan dia adalah pemberi izin
        if ($current_user_role === 'Guru' && $current_user_related_id !== null && $edit_izin['diberi_izin_oleh_id'] != $current_user_related_id) { // --- PERBAIKAN: Tambahkan pemeriksaan null ---
            set_flash('error', 'Anda tidak memiliki izin untuk mengedit catatan izin ini.');
            header('Location: ' . ROOT_URL . 'guru/izin_keluar_pulang.php'); // Arahkan kembali ke halaman guru
            exit;
        }
    }

    // Apply filters for fetching all data
    $filters = [
        'tipe_pengguna' => $filter_tipe_pengguna,
        'pengguna_id' => $filter_pengguna_id,
        'status_izin' => $filter_status_izin,
        'start_date' => $filter_start_date,
        'end_date' => $filter_end_date,
        'kelas_id' => $filter_kelas_id // Menambahkan filter kelas
    ];

    // Jika user adalah guru, otomatis filter berdasarkan guru_id nya
    if ($current_user_role === 'Guru' && $current_user_related_id !== null) { // --- PERBAIKAN: Tambahkan pemeriksaan null ---
        // Tampilkan hanya izin yang diberikan oleh guru ini
        $filters['diberi_izin_oleh_id'] = $current_user_related_id;
    }

    $izin_data = $izin_model->getAll($filters);

} catch (PDOException $e) {
    error_log("Manajemen Izin Keluar/Pulang Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Manajemen Izin Keluar/Pulang General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
}

require_once __DIR__ . '/../components/header_guru.php'; // Menggunakan header_guru
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manajemen Izin Keluar/Pulang</h1>

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
                <label for="tipe_pengguna" class="block text-gray-700 text-sm font-bold mb-2">Tipe Pengguna:</label>
                <select id="tipe_pengguna" name="tipe_pengguna" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <option value="">Pilih Tipe</option>
                    <option value="Siswa" <?php echo (isset($edit_izin['tipe_pengguna']) && $edit_izin['tipe_pengguna'] == 'Siswa') ? 'selected' : ''; ?>>Siswa</option>
                    <option value="Guru" <?php echo (isset($edit_izin['tipe_pengguna']) && $edit_izin['tipe_pengguna'] == 'Guru') ? 'selected' : ''; ?>>Guru</option>
                </select>
            </div>
            <div id="pengguna_wrapper" class="hidden">
                <label for="pengguna_id" class="block text-gray-700 text-sm font-bold mb-2">Pilih Pengguna:</label>
                <select id="pengguna_id" name="pengguna_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <option value="">Pilih Siswa/Guru</option>
                    <!-- Options akan diisi oleh JavaScript -->
                </select>
            </div>
            <div>
                <label for="tanggal_izin" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Izin:</label>
                <input type="date" id="tanggal_izin" name="tanggal_izin" value="<?php echo htmlspecialchars($edit_izin['tanggal_izin'] ?? date('Y-m-d')); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div>
                <label for="waktu_keluar" class="block text-gray-700 text-sm font-bold mb-2">Waktu Keluar:</label>
                <input type="time" id="waktu_keluar" name="waktu_keluar" value="<?php echo htmlspecialchars($edit_izin['waktu_keluar'] ?? date('H:i')); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div>
                <label for="waktu_kembali" class="block text-gray-700 text-sm font-bold mb-2">Waktu Kembali (Opsional):</label>
                <input type="time" id="waktu_kembali" name="waktu_kembali" value="<?php echo htmlspecialchars($edit_izin['waktu_kembali'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div>
                <label for="keperluan" class="block text-gray-700 text-sm font-bold mb-2">Keperluan Izin:</label>
                <textarea id="keperluan" name="keperluan" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required><?php echo htmlspecialchars($edit_izin['keperluan'] ?? ''); ?></textarea>
            </div>
            <!-- Bidang "Diberi Izin Oleh" akan disembunyikan untuk guru, karena akan otomatis terisi -->
            <?php if ($current_user_role !== 'Guru'): ?>
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
            <?php else: ?>
                <input type="hidden" name="diberi_izin_oleh_id" value="<?php echo htmlspecialchars($current_user_related_id); ?>">
            <?php endif; ?>
            <div>
                <label for="status_izin" class="block text-gray-700 text-sm font-bold mb-2">Status Izin:</label>
                <select id="status_izin" name="status_izin" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <?php foreach ($status_options as $status): ?>
                        <option value="<?php echo htmlspecialchars($status); ?>" <?php echo (isset($edit_izin['status_izin']) && $edit_izin['status_izin'] == $status) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($status); ?>
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
                    <a href="<?php echo ROOT_URL; ?>guru/izin_keluar_pulang.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
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
                <label for="filter_tipe_pengguna" class="block text-gray-700 text-sm font-bold mb-2">Tipe Pengguna:</label>
                <select id="filter_tipe_pengguna" name="filter_tipe_pengguna" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Tipe</option>
                    <option value="Siswa" <?php echo ($filter_tipe_pengguna == 'Siswa') ? 'selected' : ''; ?>>Siswa</option>
                    <option value="Guru" <?php echo ($filter_tipe_pengguna == 'Guru') ? 'selected' : ''; ?>>Guru</option>
                </select>
            </div>
            <div id="filter_pengguna_wrapper">
                <label for="filter_pengguna_id" class="block text-gray-700 text-sm font-bold mb-2">Pengguna:</label>
                <select id="filter_pengguna_id" name="filter_pengguna_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Pengguna</option>
                    <!-- Options akan diisi oleh JavaScript -->
                </select>
            </div>
            <div>
                <label for="filter_kelas_id" class="block text-gray-700 text-sm font-bold mb-2">Kelas Siswa (Filter Khusus Siswa):</label>
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
                <label for="filter_status_izin" class="block text-gray-700 text-sm font-bold mb-2">Status Izin:</label>
                <select id="filter_status_izin" name="filter_status_izin" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Status</option>
                    <?php foreach ($status_options as $status): ?>
                        <option value="<?php echo htmlspecialchars($status); ?>" <?php echo ($filter_status_izin == $status) ? 'selected' : ''; ?>>
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
        <h2 class="text-xl font-semibold mb-4">Daftar Catatan Izin Keluar/Pulang</h2>
        <?php if (empty($izin_data)): ?>
            <p class="text-gray-600">Tidak ada catatan izin keluar/pulang.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                    <thead>
                        <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">No.</th>
                            <th class="py-3 px-6 text-left">Tipe</th>
                            <th class="py-3 px-6 text-left">Pengguna</th>
                            <th class="py-3 px-6 text-left">Kelas</th>
                            <th class="py-3 px-6 text-left">Tanggal & Waktu</th>
                            <th class="py-3 px-6 text-left">Keperluan</th>
                            <th class="py-3 px-6 text-left">Oleh</th>
                            <th class="py-3 px-6 text-center">Status</th>
                            <th class="py-3 px-6 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm">
                        <?php $no = 1; ?>
                        <?php foreach ($izin_data as $izin): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($izin['tipe_pengguna']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($izin['nama_pengguna']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($izin['nama_kelas'] ?? '-'); ?></td>
                                <td class="py-3 px-6">
                                    <?php echo htmlspecialchars(format_date_indonesian($izin['tanggal_izin'])); ?><br>
                                    <small class="text-gray-500">Keluar: <?php echo htmlspecialchars(substr($izin['waktu_keluar'], 0, 5)); ?></small><br>
                                    <?php if ($izin['waktu_kembali']): ?>
                                        <small class="text-gray-500">Kembali: <?php echo htmlspecialchars(substr($izin['waktu_kembali'], 0, 5)); ?></small>
                                    <?php else: ?>
                                        <small class="text-gray-500">Belum Kembali</small>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars(substr($izin['keperluan'], 0, 75)); ?><?php echo (strlen($izin['keperluan']) > 75) ? '...' : ''; ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($izin['nama_pemberi_izin'] ?? '-'); ?></td>
                                <td class="py-3 px-6 text-center">
                                    <span class="px-2 py-1 font-semibold leading-tight rounded-full 
                                        <?php
                                            switch ($izin['status_izin']) {
                                                case 'Disetujui': echo 'bg-green-100 text-green-800'; break;
                                                case 'Ditolak': echo 'bg-red-100 text-red-800'; break;
                                                case 'Selesai': echo 'bg-blue-100 text-blue-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800'; break; // Pending
                                            }
                                        ?>">
                                        <?php echo htmlspecialchars($izin['status_izin']); ?>
                                    </span>
                                    <?php if (!empty($izin['catatan_admin'])): ?>
                                        <br><small class="text-gray-500" title="<?php echo htmlspecialchars($izin['catatan_admin']); ?>">(Catatan Admin)</small>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-6 text-center whitespace-nowrap">
                                    <a href="<?php echo ROOT_URL; ?>guru/izin_keluar_pulang.php?action=edit&id=<?php echo htmlspecialchars($izin['id']); ?>" class="text-blue-600 hover:text-blue-900 mr-3">Edit</a>
                                    <a href="<?php echo ROOT_URL; ?>guru/izin_keluar_pulang.php?action=delete&id=<?php echo htmlspecialchars($izin['id']); ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Apakah Anda yakin ingin menghapus catatan izin ini?');">Hapus</a>
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
        const tipePenggunaSelect = document.getElementById('tipe_pengguna');
        const penggunaIdSelect = document.getElementById('pengguna_id');
        const penggunaWrapper = document.getElementById('pengguna_wrapper');

        const filterTipePenggunaSelect = document.getElementById('filter_tipe_pengguna');
        const filterPenggunaIdSelect = document.getElementById('filter_pengguna_id');
        const filterPenggunaWrapper = document.getElementById('filter_pengguna_wrapper');

        const allSiswa = <?php echo json_encode($all_siswa); ?>;
        const allGuru = <?php echo json_encode($all_guru); ?>;
        const initialEditPenggunaId = '<?php echo htmlspecialchars($edit_izin['pengguna_id'] ?? ''); ?>';
        const initialEditTipePengguna = '<?php echo htmlspecialchars($edit_izin['tipe_pengguna'] ?? ''); ?>';
        const initialFilterPenggunaId = '<?php echo htmlspecialchars($filter_pengguna_id ?? ''); ?>';
        const initialFilterTipePengguna = '<?php echo htmlspecialchars($filter_tipe_pengguna ?? ''); ?>';

        function populatePenggunaSelect(selectElement, tipe, selectedId = null) {
            selectElement.innerHTML = '<option value="">Pilih ' + (tipe === 'Siswa' ? 'Siswa' : 'Guru') + '</option>';
            let data = [];
            if (tipe === 'Siswa') {
                data = allSiswa;
            } else if (tipe === 'Guru') {
                data = allGuru;
            }

            data.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.nama_lengkap + (item.nisn ? ' (NISN: ' + item.nisn + ')' : '') + (item.nip ? ' (NIP: ' + item.nip + ')' : '');
                if (selectedId && item.id == selectedId) {
                    option.selected = true;
                }
                selectElement.appendChild(option);
            });

            if (data.length > 0 || (selectElement.id === 'filter_pengguna_id' && tipe === '')) { // Always show filter dropdown if no type selected
                if (selectElement.closest('#pengguna_wrapper')) {
                    penggunaWrapper.classList.remove('hidden');
                }
                if (selectElement.closest('#filter_pengguna_wrapper')) {
                    filterPenggunaWrapper.classList.remove('hidden');
                }
            } else {
                 if (selectElement.closest('#pengguna_wrapper')) {
                    penggunaWrapper.classList.add('hidden');
                }
                if (selectElement.closest('#filter_pengguna_wrapper')) {
                    filterPenggunaWrapper.classList.add('hidden');
                }
            }
        }

        // Initialize on page load for form
        if (tipePenggunaSelect.value) {
            populatePenggunaSelect(penggunaIdSelect, tipePenggunaSelect.value, initialEditPenggunaId);
        }
        tipePenggunaSelect.addEventListener('change', function() {
            populatePenggunaSelect(penggunaIdSelect, this.value);
        });

        // Initialize on page load for filter
        if (filterTipePenggunaSelect.value) {
            populatePenggunaSelect(filterPenggunaIdSelect, filterTipePenggunaSelect.value, initialFilterPenggunaId);
        } else {
            filterPenggunaWrapper.classList.remove('hidden'); // Show filter always, but empty if no type selected
            filterPenggunaIdSelect.innerHTML = '<option value="">Semua Pengguna</option>';
        }
        filterTipePenggunaSelect.addEventListener('change', function() {
            populatePenggunaSelect(filterPenggunaIdSelect, this.value);
            if (!this.value) { // If "Semua Tipe" is selected
                filterPenggunaIdSelect.innerHTML = '<option value="">Semua Pengguna</option>';
            }
        });
    });
</script>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
