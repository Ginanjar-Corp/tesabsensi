<?php
// project-root/admin/catat_pembayaran.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/Kelas.php';
require_once __DIR__ . '/../classes/JenisPembayaran.php';
require_once __DIR__ . '/../classes/TahunAjaran.php';
require_once __DIR__ . '/../classes/TagihanSiswa.php';
require_once __DIR__ . '/../classes/DetailPembayaranTransaksi.php';
require_once __DIR__ . '/../classes/User.php'; // Untuk mendapatkan nama petugas
require_once __DIR__ . '/../classes/NotificationQueue.php'; // Untuk antrean notifikasi WhatsApp
require_once __DIR__ . '/../classes/SchoolSettings.php'; // Untuk mendapatkan nomor telepon ortu

// Memastikan pengguna sudah login dan memiliki peran Admin atau Tata Usaha
require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$siswa_model = null;
$kelas_model = null;
$jenis_pembayaran_model = null;
$tahun_ajaran_model = null;
$tagihan_siswa_model = null;
$detail_pembayaran_transaksi_model = null;
$user_model = null;
$notification_queue_model = null;
$school_settings_model = null;
$pdo = null;

$all_siswa = [];
$all_tahun_ajaran = [];
$tagihan_siswa_data = [];
$selected_siswa_id = filter_input(INPUT_GET, 'siswa_id', FILTER_VALIDATE_INT);
$selected_tagihan_id = filter_input(INPUT_GET, 'tagihan_id', FILTER_VALIDATE_INT);
$detail_pembayaran_history = [];
$current_user_id = $_SESSION['user_id'] ?? null; // ID user yang sedang login

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    $siswa_model = new Siswa($pdo);
    $kelas_model = new Kelas($pdo);
    $jenis_pembayaran_model = new JenisPembayaran($pdo);
    $tahun_ajaran_model = new TahunAjaran($pdo);
    $tagihan_siswa_model = new TagihanSiswa($pdo);
    $detail_pembayaran_transaksi_model = new DetailPembayaranTransaksi($pdo);
    $user_model = new User($pdo);
    $notification_queue_model = new NotificationQueue($pdo);
    $school_settings_model = new SchoolSettings($pdo);

    $all_siswa = $siswa_model->getAll(); // Ambil semua siswa
    $all_tahun_ajaran = $tahun_ajaran_model->getAll();

    // Handle form submission untuk mencatat pembayaran
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_payment') {
        $tagihan_id = filter_input(INPUT_POST, 'tagihan_id', FILTER_VALIDATE_INT);
        $jumlah_bayar = (float)filter_input(INPUT_POST, 'jumlah_bayar', FILTER_VALIDATE_FLOAT);
        $metode_pembayaran = filter_input(INPUT_POST, 'metode_pembayaran', FILTER_SANITIZE_STRING);
        $tanggal_bayar = filter_input(INPUT_POST, 'tanggal_bayar', FILTER_SANITIZE_STRING);
        $catatan = filter_input(INPUT_POST, 'catatan', FILTER_SANITIZE_STRING) ?? null;
        $bukti_pembayaran_path = null; // Inisialisasi null

        // Cek jika ada upload bukti pembayaran
        if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../assets/uploads/bukti_pembayaran/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_extension = pathinfo($_FILES['bukti_pembayaran']['name'], PATHINFO_EXTENSION);
            $new_file_name = uniqid('bukti_') . '.' . $file_extension;
            $target_file = $upload_dir . $new_file_name;

            if (move_uploaded_file($_FILES['bukti_pembayaran']['tmp_name'], $target_file)) {
                $bukti_pembayaran_path = 'assets/uploads/bukti_pembayaran/' . $new_file_name;
            } else {
                set_flash('error', 'Gagal mengunggah bukti pembayaran.');
                redirect(ROOT_URL . 'admin/catat_pembayaran.php?siswa_id=' . $selected_siswa_id . '&tagihan_id=' . $tagihan_id);
            }
        }

        $detail_pembayaran_transaksi_model->tagihan_siswa_id = $tagihan_id;
        $detail_pembayaran_transaksi_model->tanggal_bayar = $tanggal_bayar;
        $detail_pembayaran_transaksi_model->jumlah_bayar = $jumlah_bayar;
        $detail_pembayaran_transaksi_model->metode_pembayaran = $metode_pembayaran;
        $detail_pembayaran_transaksi_model->petugas_id = $current_user_id;
        $detail_pembayaran_transaksi_model->bukti_pembayaran_path = $bukti_pembayaran_path;
        $detail_pembayaran_transaksi_model->catatan = $catatan;

        if ($detail_pembayaran_transaksi_model->create()) {
            set_flash('success', 'Pembayaran berhasil dicatat.');

            // --- Logika Notifikasi WhatsApp Asinkron ---
            // Setelah pembayaran dicatat dan sisa tagihan di-update, ambil lagi data tagihan terbaru
            $tagihan = $tagihan_siswa_model->getById($tagihan_id); 
            if ($tagihan) {
                $siswa_detail = $siswa_model->findById($tagihan['siswa_id']);
                if ($siswa_detail && !empty($siswa_detail['no_telp_ortu'])) {
                    $recipientPhoneNumber = ltrim($siswa_detail['no_telp_ortu'], '+'); // Pastikan tanpa '+'
                    $templateName = 'konfirmasi_pembayaran'; // Anda perlu membuat template ini
                    $templateParameters = [
                        $siswa_detail['nama_lengkap'],
                        $tagihan['nama_pembayaran'],
                        number_format($jumlah_bayar, 2, ',', '.'),
                        number_format($tagihan['sisa_tagihan'], 2, ',', '.'), // Ambil sisa tagihan yang sudah diperbarui dari DB
                        format_date_indonesian($tanggal_bayar),
                        $tagihan['periode_tagihan'] ?? '-'
                    ];
                    
                    $queue_result = $notification_queue_model->addNotificationToQueue(
                        $recipientPhoneNumber, 
                        $templateName, 
                        $templateParameters
                    );

                    if ($queue_result['status'] === 'success') {
                        set_flash('success', flash('success') . ' Notifikasi WhatsApp berhasil ditambahkan ke antrean.');
                    } else {
                        set_flash('error', flash('error') . ' Gagal menambahkan notifikasi WhatsApp ke antrean: ' . $queue_result['message']);
                    }
                } else {
                    error_log("Nomor telepon orang tua tidak ditemukan atau siswa tidak ditemukan untuk notifikasi WA.");
                }
            } else {
                error_log("Tagihan tidak ditemukan untuk notifikasi WA.");
            }
            // --- Akhir Logika Notifikasi WhatsApp ---

            redirect(ROOT_URL . 'admin/catat_pembayaran.php?siswa_id=' . $selected_siswa_id . '&tagihan_id=' . $tagihan_id);
        } else {
            set_flash('error', 'Gagal mencatat pembayaran.');
            redirect(ROOT_URL . 'admin/catat_pembayaran.php?siswa_id=' . $selected_siswa_id . '&tagihan_id=' . $tagihan_id);
        }
    }
    
    // Handle pembatalan transaksi pembayaran
    if (isset($_GET['action']) && $_GET['action'] === 'cancel_payment' && isset($_GET['detail_id'])) {
        $detail_id_to_cancel = filter_input(INPUT_GET, 'detail_id', FILTER_VALIDATE_INT);
        $cancel_result = $detail_pembayaran_transaksi_model->cancelPayment($detail_id_to_cancel);

        if ($cancel_result['status'] === 'success') {
            set_flash('success', $cancel_result['message']);
            // --- Notifikasi WA untuk pembatalan transaksi ---
            $canceled_detail = $detail_pembayaran_transaksi_model->getById($detail_id_to_cancel);
            if ($canceled_detail) {
                $tagihan_id_for_cancel = $canceled_detail['tagihan_siswa_id'];
                // Ambil tagihan terbaru setelah pembatalan untuk mendapatkan sisa tagihan yang sudah di-update
                $tagihan_after_cancel = $tagihan_siswa_model->getById($tagihan_id_for_cancel); 
                if ($tagihan_after_cancel) {
                    $siswa_detail_for_cancel = $siswa_model->findById($tagihan_after_cancel['siswa_id']);
                    if ($siswa_detail_for_cancel && !empty($siswa_detail_for_cancel['no_telp_ortu'])) {
                        $recipientPhoneNumber = ltrim($siswa_detail_for_cancel['no_telp_ortu'], '+');
                        $templateName = 'pembatalan_pembayaran'; // Template baru
                        $templateParameters = [
                            $siswa_detail_for_cancel['nama_lengkap'],
                            $tagihan_after_cancel['nama_pembayaran'],
                            number_format($canceled_detail['jumlah_bayar'], 2, ',', '.'),
                            number_format($tagihan_after_cancel['sisa_tagihan'], 2, ',', '.'),
                            format_date_indonesian($canceled_detail['tanggal_bayar']),
                            $tagihan_after_cancel['periode_tagihan'] ?? '-'
                        ];

                        $queue_result = $notification_queue_model->addNotificationToQueue(
                            $recipientPhoneNumber,
                            $templateName,
                            $templateParameters
                        );
                        if ($queue_result['status'] === 'success') {
                            set_flash('success', flash('success') . ' Notifikasi pembatalan WhatsApp berhasil ditambahkan ke antrean.');
                        } else {
                            set_flash('error', flash('error') . ' Gagal menambahkan notifikasi pembatalan WhatsApp ke antrean: ' . $queue_result['message']);
                        }
                    } else {
                        error_log("Nomor telepon orang tua tidak ditemukan atau siswa tidak ditemukan untuk notifikasi pembatalan WA.");
                    }
                }
            }
            // --- Akhir Notifikasi WA untuk pembatalan transaksi ---
        } else {
            set_flash('error', $cancel_result['message']);
        }
        // Redirect untuk memastikan halaman dimuat ulang dengan data terbaru
        redirect(ROOT_URL . 'admin/catat_pembayaran.php?siswa_id=' . $selected_siswa_id . '&tagihan_id=' . $selected_tagihan_id);
    }

    // Jika siswa dipilih, ambil tagihannya
    if ($selected_siswa_id) {
        // Hapus filter status_tagihan_exclude_lunas agar semua tagihan muncul
        // DAN PASTIKAN DATA DIAMBIL ULANG SETIAP KALI HALAMAN DI-LOAD DENGAN PARAMETER SISWA_ID
        $filters = ['siswa_id' => $selected_siswa_id]; 
        $tagihan_siswa_data = $tagihan_siswa_model->getAll($filters);

        // Setelah mendapatkan $tagihan_siswa_data, perbarui selected_tagihan_display jika ada
        $selected_tagihan_display = null; 
        if ($selected_tagihan_id) {
            foreach ($tagihan_siswa_data as $tagihan_item) {
                if ($tagihan_item['tagihan_id'] == $selected_tagihan_id) { 
                    $selected_tagihan_display = $tagihan_item;
                    break;
                }
            }
        }

        // Jika tagihan spesifik dipilih, ambil riwayat pembayarannya
        if ($selected_tagihan_id) {
            $detail_pembayaran_history = $detail_pembayaran_transaksi_model->getByTagihanSiswaId($selected_tagihan_id);
        }
    }
    
} catch (PDOException $e) {
    error_log("Catat Pembayaran Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Catat Pembayaran General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
}

require_once __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Catat Pembayaran Siswa</h1>

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

    <div class="bg-white p-6 rounded-lg shadow-md mb-8 border border-gray-200">
        <h2 class="text-xl font-semibold mb-4 text-gray-800">Pilih Siswa</h2>
        <form action="" method="GET" class="mb-4">
            <label for="siswa_id" class="block text-gray-700 text-sm font-bold mb-2">Siswa:</label>
            <div class="flex flex-col md:flex-row items-end md:space-x-4">
                <select id="siswa_id" name="siswa_id"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500 md:flex-1"
                        onchange="this.form.submit()">
                    <option value="">Pilih Siswa</option>
                    <?php foreach ($all_siswa as $siswa): ?>
                        <option value="<?php echo htmlspecialchars($siswa['id']); ?>"
                                <?php echo ($selected_siswa_id == $siswa['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($siswa['nama_lengkap']); ?> (NISN: <?php echo htmlspecialchars($siswa['nisn']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($selected_siswa_id): ?>
                    <button type="submit" class="hidden md:block bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                        Tampilkan Tagihan
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if ($selected_siswa_id): ?>
        <div class="bg-white p-6 rounded-lg shadow-md mb-8 border border-gray-200">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Tagihan Siswa: <?php echo htmlspecialchars($siswa_model->findById($selected_siswa_id)['nama_lengkap'] ?? ''); ?></h2>
            <?php if (empty($tagihan_siswa_data)): ?>
                <p class="text-gray-600">Tidak ada tagihan untuk siswa ini.</p>
            <?php else: ?>
                <div class="overflow-x-auto mb-4">
                    <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                                <th class="py-3 px-6 text-left">No.</th>
                                <th class="py-3 px-6 text-left">Pembayaran</th>
                                <th class="py-3 px-6 text-left">Tahun Ajaran</th>
                                <th class="py-3 px-6 text-left">Periode</th>
                                <th class="py-3 px-6 text-right">Jumlah Tagihan</th>
                                <th class="py-3 px-6 text-right">Sisa Tagihan</th>
                                <th class="py-3 px-6 text-center">Jatuh Tempo</th>
                                <th class="py-3 px-6 text-center">Status</th>
                                <th class="py-3 px-6 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 text-sm">
                            <?php $no = 1; ?>
                            <?php foreach ($tagihan_siswa_data as $tagihan): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                    <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                    <td class="py-3 px-6"><?php echo htmlspecialchars($tagihan['nama_pembayaran']); ?></td>
                                    <td class="py-3 px-6"><?php echo htmlspecialchars($tagihan['tahun_ajaran']); ?></td>
                                    <td class="py-3 px-6"><?php echo htmlspecialchars($tagihan['periode_tagihan'] ?? '-'); ?></td>
                                    <td class="py-3 px-6 text-right">Rp <?php echo number_format($tagihan['jumlah_tagihan'], 2, ',', '.'); ?></td>
                                    <td class="py-3 px-6 text-right">Rp <?php echo number_format($tagihan['sisa_tagihan'], 2, ',', '.'); ?></td>
                                    <td class="py-3 px-6 text-center"><?php echo htmlspecialchars(format_date_indonesian($tagihan['tanggal_jatuh_tempo'] ?? '-')); ?></td>
                                    <td class="py-3 px-6 text-center">
                                        <span class="px-2 py-1 font-semibold leading-tight rounded-full 
                                            <?php
                                                switch ($tagihan['status_tagihan']) {
                                                    case 'Lunas': echo 'bg-green-100 text-green-800'; break;
                                                    case 'Sebagian Lunas': echo 'bg-yellow-100 text-yellow-800'; break;
                                                    case 'Dibatalkan': echo 'bg-red-100 text-red-800'; break;
                                                    default: echo 'bg-gray-100 text-gray-800'; break; // Belum Lunas
                                                }
                                            ?>">
                                            <?php echo htmlspecialchars($tagihan['status_tagihan']); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-6 text-center whitespace-nowrap">
                                        <?php 
                                            // Menggunakan nilai 'sisa_tagihan' dan 'status_tagihan' dari data yang sudah di-refresh
                                            // Jika status_tagihan adalah 'Dibatalkan', tombol "Bayar" tidak akan muncul.
                                            // Jika sisa_tagihan > 0 dan status bukan 'Dibatalkan', tampilkan tombol "Bayar".
                                            if ($tagihan['sisa_tagihan'] > 0 && $tagihan['status_tagihan'] !== 'Dibatalkan'): 
                                        ?>
                                            <a href="?siswa_id=<?php echo htmlspecialchars($selected_siswa_id); ?>&tagihan_id=<?php echo htmlspecialchars($tagihan['tagihan_id']); ?>"
                                               class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-1 px-3 rounded text-xs">
                                                Bayar
                                            </a>
                                        <?php else: ?>
                                            <a href="?siswa_id=<?php echo htmlspecialchars($selected_siswa_id); ?>&tagihan_id=<?php echo htmlspecialchars($tagihan['tagihan_id']); ?>"
                                               class="bg-gray-300 text-gray-700 font-bold py-1 px-3 rounded text-xs cursor-pointer">
                                                Lihat
                                            </a>
                                        <?php endif; ?>
                                        <!-- Tombol edit tagihan selalu muncul -->
                                        <a href="<?php echo ROOT_URL; ?>admin/edit_tagihan.php?id=<?php echo htmlspecialchars($tagihan['tagihan_id']); ?>&siswa_id=<?php echo htmlspecialchars($selected_siswa_id); ?>"
                                           class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-1 px-3 rounded text-xs ml-1">
                                            Edit Tagihan
                                        </a>
                                        <!-- Tombol hapus tagihan (hanya jika belum ada pembayaran) -->
                                        <?php
                                            // Pastikan getTotalPaidForTagihan dipanggil dengan benar
                                            $total_paid_for_tagihan = $detail_pembayaran_transaksi_model->getTotalPaidForTagihan($tagihan['tagihan_id']);
                                            if ($total_paid_for_tagihan == 0):
                                        ?>
                                        <a href="#" onclick="confirmDeleteTagihan(<?php echo htmlspecialchars($tagihan['tagihan_id']); ?>, '<?php echo htmlspecialchars($selected_siswa_id); ?>')"
                                           class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-3 rounded text-xs ml-1">
                                            Hapus Tagihan
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($selected_tagihan_id && $selected_tagihan_display): ?>
                    <div class="bg-blue-50 border border-blue-200 text-blue-800 p-4 rounded-lg mb-6">
                        <h3 class="text-lg font-semibold mb-2">Detail Tagihan: <?php echo htmlspecialchars($selected_tagihan_display['nama_pembayaran']); ?> (<?php echo htmlspecialchars($selected_tagihan_display['periode_tagihan'] ?? '-'); ?>)</h3>
                        <p>Tahun Ajaran: <span class="font-medium"><?php echo htmlspecialchars($selected_tagihan_display['tahun_ajaran']); ?></span></p>
                        <p>Jumlah Tagihan: <span class="font-medium">Rp <?php echo number_format($selected_tagihan_display['jumlah_tagihan'], 2, ',', '.'); ?></span></p>
                        <p>Sisa Tagihan: <span class="font-medium text-red-600">Rp <?php echo number_format($selected_tagihan_display['sisa_tagihan'], 2, ',', '.'); ?></span></p>
                        <p>Status: <span class="font-medium"><?php echo htmlspecialchars($selected_tagihan_display['status_tagihan']); ?></span></p>
                    </div>

                    <?php if ($selected_tagihan_display['sisa_tagihan'] > 0 && $selected_tagihan_display['status_tagihan'] !== 'Dibatalkan'): ?>
                    <div class="bg-white p-6 rounded-lg shadow-md mb-8 border border-gray-200">
                        <h3 class="text-lg font-semibold mb-4 text-gray-800">Catat Pembayaran untuk Tagihan Ini</h3>
                        <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                            <input type="hidden" name="action" value="record_payment">
                            <input type="hidden" name="tagihan_id" value="<?php echo htmlspecialchars($selected_tagihan_display['tagihan_id']); ?>">
                            
                            <div>
                                <label for="jumlah_bayar" class="block text-gray-700 text-sm font-bold mb-2">Jumlah Dibayar (Rp):</label>
                                <input type="number" step="0.01" id="jumlah_bayar" name="jumlah_bayar"
                                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500"
                                       value="<?php echo htmlspecialchars($selected_tagihan_display['sisa_tagihan']); ?>" max="<?php echo htmlspecialchars($selected_tagihan_display['sisa_tagihan']); ?>" required>
                            </div>
                            <div>
                                <label for="metode_pembayaran" class="block text-gray-700 text-sm font-bold mb-2">Metode Pembayaran:</label>
                                <select id="metode_pembayaran" name="metode_pembayaran"
                                        class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500" required>
                                    <option value="Tunai">Tunai</option>
                                    <option value="Transfer">Transfer</option>
                                    <option value="POS">POS</option>
                                    <option value="Beasiswa">Beasiswa</option>
                                    <option value="Dispensasi">Dispensasi</option>
                                </select>
                            </div>
                            <div>
                                <label for="tanggal_bayar" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Pembayaran:</label>
                                <input type="date" id="tanggal_bayar" name="tanggal_bayar" value="<?php echo date('Y-m-d'); ?>"
                                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500" required>
                            </div>
                            <div>
                                <label for="bukti_pembayaran" class="block text-gray-700 text-sm font-bold mb-2">Bukti Pembayaran (Opsional, File Gambar):</label>
                                <input type="file" id="bukti_pembayaran" name="bukti_pembayaran" accept="image/*"
                                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500">
                            </div>
                            <div>
                                <label for="catatan" class="block text-gray-700 text-sm font-bold mb-2">Catatan (Opsional):</label>
                                <textarea id="catatan" name="catatan" rows="2"
                                          class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500"
                                          placeholder="Tambahkan catatan untuk pembayaran ini"></textarea>
                            </div>
                            <div class="flex items-center">
                                <button type="submit"
                                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                                    Catat Pembayaran
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>

                    <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200">
                        <h3 class="text-lg font-semibold mb-4 text-gray-800">Riwayat Pembayaran untuk Tagihan Ini</h3>
                        <?php if (empty($detail_pembayaran_history)): ?>
                            <p class="text-gray-600">Belum ada riwayat pembayaran untuk tagihan ini.</p>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                                    <thead>
                                        <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                                            <th class="py-3 px-6 text-left">No.</th>
                                            <th class="py-3 px-6 text-left">Tanggal Bayar</th>
                                            <th class="py-3 px-6 text-right">Jumlah</th>
                                            <th class="py-3 px-6 text-left">Metode</th>
                                            <th class="py-3 px-6 text-left">Oleh</th>
                                            <th class="py-3 px-6 text-center">Bukti</th>
                                            <th class="py-3 px-6 text-center">Status</th>
                                            <th class="py-3 px-6 text-left">Catatan</th>
                                            <th class="py-3 px-6 text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-gray-700 text-sm">
                                        <?php $no_history = 1; ?>
                                        <?php foreach ($detail_pembayaran_history as $history): ?>
                                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                                <td class="py-3 px-6 whitespace-nowrap"><?php echo $no_history++; ?></td>
                                                <td class="py-3 px-6"><?php echo htmlspecialchars(format_date_indonesian($history['tanggal_bayar'])); ?></td>
                                                <td class="py-3 px-6 text-right">Rp <?php echo number_format($history['jumlah_bayar'], 2, ',', '.'); ?></td>
                                                <td class="py-3 px-6"><?php echo htmlspecialchars($history['metode_pembayaran']); ?></td>
                                                <td class="py-3 px-6"><?php echo htmlspecialchars($history['nama_petugas'] ?? 'N/A'); ?></td>
                                                <td class="py-3 px-6 text-center">
                                                    <?php if (!empty($history['bukti_pembayaran_path'])): ?>
                                                        <a href="<?php echo ROOT_URL . htmlspecialchars($history['bukti_pembayaran_path']); ?>" target="_blank" class="text-blue-500 hover:underline">Lihat Bukti</a>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td class="py-3 px-6 text-center">
                                                    <span class="px-2 py-1 font-semibold leading-tight rounded-full 
                                                        <?php
                                                            if ($history['status_pembayaran_detail'] == 'Lunas') echo 'bg-green-100 text-green-800';
                                                            else if ($history['status_pembayaran_detail'] == 'Dibatalkan') echo 'bg-red-100 text-red-800';
                                                            else echo 'bg-gray-100 text-gray-800'; // Default, should not happen if only Lunas/Dibatalkan
                                                        ?>">
                                                        <?php echo htmlspecialchars($history['status_pembayaran_detail']); ?>
                                                    </span>
                                                </td>
                                                <td class="py-3 px-6"><?php echo htmlspecialchars($history['catatan'] ?? '-'); ?></td>
                                                <td class="py-3 px-6 text-center whitespace-nowrap">
                                                    <?php if ($history['status_pembayaran_detail'] !== 'Dibatalkan'): ?>
                                                        <a href="#" onclick="confirmCancelPayment(<?php echo htmlspecialchars($history['id']); ?>, '<?php echo htmlspecialchars($selected_siswa_id); ?>', '<?php echo htmlspecialchars($selected_tagihan_id); ?>')"
                                                           class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-3 rounded text-xs">
                                                            Batalkan
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-gray-500 text-xs">Dibatalkan</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    function confirmCancelPayment(detailId, siswaId, tagihanId) {
        if (confirm('Apakah Anda yakin ingin membatalkan transaksi pembayaran ini? Aksi ini akan mengembalikan sisa tagihan.')) {
            window.location.href = `<?php echo ROOT_URL; ?>admin/catat_pembayaran.php?action=cancel_payment&detail_id=${detailId}&siswa_id=${siswaId}&tagihan_id=${tagihanId}`;
        }
    }

    function confirmDeleteTagihan(tagihanId, siswaId) {
        if (confirm('Apakah Anda yakin ingin MENGHAPUS tagihan ini? Ini akan menghapus tagihan secara permanen dan hanya bisa dilakukan jika belum ada pembayaran terkait.')) {
            window.location.href = `<?php echo ROOT_URL; ?>admin/edit_tagihan.php?action=delete_tagihan&id=${tagihanId}&siswa_id=${siswaId}`; // Arahkan ke edit_tagihan.php untuk delete
        }
    }
</script>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
