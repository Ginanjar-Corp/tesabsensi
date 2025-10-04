<?php
// project-root/staff/manage_absensi_guru.php

session_start(); // Pastikan session dimulai jika belum di includes/auth.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Guru.php'; // Kelas Guru
require_once __DIR__ . '/../classes/AbsensiHarian.php'; // Kelas AbsensiHarian (bukan AbsensiGuru)
require_once __DIR__ . '/../classes/NotificationQueue.php'; // Tambahkan ini
require_once __DIR__ . '/../classes/SchoolSettings.php'; // Tambahkan ini
require_once __DIR__ . '/../includes/whatsapp_notifier.php'; // Tambahkan ini untuk template pesan

// Pastikan pengguna sudah login dan memiliki peran Admin atau Tata Usaha
require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$guru_model = null;
$absensi_harian_model = null; // Ganti nama variabel agar konsisten dengan kelas
$notification_queue_model = null; // Inisialisasi model notifikasi
$school_settings_model = null;   // Inisialisasi model pengaturan sekolah
$pdo = null;

$all_guru = [];
$all_tahun_ajaran = [];
$selected_tanggal = $_GET['tanggal'] ?? date('Y-m-d');
$selected_tahun_ajaran = $_GET['tahun_ajaran'] ?? null;
$selected_semester = $_GET['semester'] ?? APP_SEMESTER_AKTIF;

// Status kehadiran yang tersedia untuk input manual
$status_kehadiran_options = ['Hadir', 'Sakit', 'Izin', 'Alpha', 'Terlambat'];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $guru_model = new Guru($pdo);
    $absensi_harian_model = new AbsensiHarian($pdo); // Gunakan AbsensiHarian
    $notification_queue_model = new NotificationQueue($pdo); // Inisialisasi
    $school_settings_model = new SchoolSettings($pdo);       // Inisialisasi
    $settings = $school_settings_model->getSettings();       // Ambil pengaturan

    $all_tahun_ajaran = getAllTahunAjaran(); // Dari includes/helpers.php

    // Ambil tahun ajaran aktif sebagai default jika belum dipilih
    if (!$selected_tahun_ajaran) {
        $active_ta_data = getActiveTahunAjaran();
        $selected_tahun_ajaran = $active_ta_data['tahun_ajaran'] ?? null;
    }

    // Handle form submission untuk mencatat absensi manual
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_absensi') {
        $guru_id = (int)($_POST['guru_id'] ?? 0);
        $status_absensi = trim($_POST['status_absensi'] ?? '');
        $tanggal_absensi = trim($_POST['tanggal_absensi'] ?? date('Y-m-d'));

        // Validasi input
        if (empty($guru_id) || empty($status_absensi) || !in_array($status_absensi, $status_kehadiran_options)) {
            set_flash('error', 'Data absensi tidak lengkap atau status tidak valid.');
        } else {
            // Panggil metode untuk mencatat absensi dengan tipe
            $result = $absensi_harian_model->recordGuruAttendanceWithType(
                $guru_id,
                'manual', // Tipe absensi: 'manual'
                $selected_tahun_ajaran, // Gunakan tahun ajaran yang sedang aktif/terpilih
                $selected_semester,      // Gunakan semester yang sedang aktif/terpilih
                $status_absensi          // Teruskan status absensi sebagai status manual
            );

            if ($result['status'] === 'success') {
                set_flash('success', $result['message']);

                // --- Tambahkan Notifikasi WhatsApp ke Antrean ---
                $guru_info = $guru_model->findById($guru_id); // Ambil info guru untuk notifikasi
                if ($guru_info) {
                    $guru_phone_number = $guru_info['no_telp'] ?? null; // Nomor telepon guru
                    $nama_guru = $guru_info['nama_lengkap'];
                    $waktu_absensi_manual = date('H:i'); // Waktu saat admin mencatat

                    // Notifikasi ke Guru itu sendiri
                    if ($guru_phone_number) {
                        $phone_number_clean = ltrim($guru_phone_number, '+');
                        $template_name_guru = 'absensi_notifikasi_manual_guru'; // Template baru untuk notifikasi manual guru
                        $template_params_guru = [
                            $nama_guru,
                            $status_absensi,
                            $tanggal_absensi,
                            $waktu_absensi_manual
                        ];

                        $queue_result_guru = $notification_queue_model->addNotificationToQueue(
                            $phone_number_clean,
                            $template_name_guru,
                            $template_params_guru
                        );
                        if ($queue_result_guru['status'] === 'success') {
                            error_log("Notifikasi manual guru " . $nama_guru . " ke diri sendiri dengan status " . $status_absensi . " ditambahkan ke antrean.");
                        } else {
                            error_log("Gagal menambahkan notifikasi manual guru " . $nama_guru . " ke antrean (diri sendiri): " . $queue_result_guru['message']);
                        }
                    } else {
                        error_log("Nomor telepon guru " . $nama_guru . " tidak ditemukan untuk notifikasi WhatsApp.");
                    }

                    // Notifikasi ke Kepala Sekolah (jika nomornya berbeda dengan guru)
                    $kepala_sekolah_phone = $settings['telepon_sekolah'] ?? null; // Asumsi ini nomor kepsek
                    if ($kepala_sekolah_phone && $kepala_sekolah_phone !== $guru_phone_number) {
                        $phone_number_clean_ks = ltrim($kepala_sekolah_phone, '+');
                        $template_name_ks = 'absensi_notifikasi_manual_guru_kepsek'; // Template baru untuk kepsek
                        $template_params_ks = [
                            $nama_guru,
                            $status_absensi,
                            $tanggal_absensi,
                            $waktu_absensi_manual
                        ];

                        $queue_result_ks = $notification_queue_model->addNotificationToQueue(
                            $phone_number_clean_ks,
                            $template_name_ks,
                            $template_params_ks
                        );
                        if ($queue_result_ks['status'] === 'success') {
                            error_log("Notifikasi manual guru " . $nama_guru . " ke Kepala Sekolah dengan status " . $status_absensi . " ditambahkan ke antrean.");
                        } else {
                            error_log("Gagal menambahkan notifikasi manual guru " . $nama_guru . " ke Kepala Sekolah ke antrean: " . $queue_result_ks['message']);
                        }
                    } elseif ($kepala_sekolah_phone && $kepala_sekolah_phone === $guru_phone_number) {
                        error_log("Nomor telepon Kepala Sekolah sama dengan nomor guru, notifikasi ke Kepala Sekolah diabaikan untuk menghindari duplikasi.");
                    } else {
                        error_log("Nomor telepon Kepala Sekolah tidak ditemukan di pengaturan untuk notifikasi WhatsApp guru.");
                    }
                }
                // --- Akhir Notifikasi WhatsApp ---

            } else {
                set_flash('error', $result['message']);
            }
        }
        // Redirect kembali ke halaman dengan filter yang sama
        redirect(ROOT_URL . 'staff/manage_absensi_guru.php?tanggal=' . urlencode($tanggal_absensi) . '&tahun_ajaran=' . urlencode($selected_tahun_ajaran) . '&semester=' . urlencode($selected_semester));
    }

    // Ambil semua daftar guru aktif (is_active = 1)
    // Menggunakan metode getAll() dari Guru.php dan memfilter yang aktif
    $all_guru_raw = $guru_model->getAll();
    $all_guru = array_filter($all_guru_raw, function($guru) {
        return $guru['is_active'] == 1;
    });

    // Ambil status absensi guru untuk tanggal yang dipilih
    $absensi_data_today = [];
    if (!empty($all_guru) && $selected_tahun_ajaran && $selected_semester) {
        $stmt = $pdo->prepare("SELECT guru_id, status_masuk, waktu_masuk, waktu_pulang FROM absensi_harian_guru WHERE tanggal = ? AND tahun_ajaran = ? AND semester = ?");
        $stmt->execute([$selected_tanggal, $selected_tahun_ajaran, $selected_semester]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $absensi_data_today[$row['guru_id']] = $row;
        }
    }

} catch (PDOException $e) {
    error_log("Manajemen Absensi Guru Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Manajemen Absensi Guru General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
}

// Sertakan header admin
require_once __DIR__ . '/../components/header_staff.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manajemen Absensi Guru (Manual)</h1>

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

    <!-- Filter Absensi -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-semibold mb-4">Filter Absensi</h2>
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div>
                <label for="tanggal" class="block text-gray-700 text-sm font-bold mb-2">Tanggal:</label>
                <input type="date" id="tanggal" name="tanggal" value="<?php echo htmlspecialchars($selected_tanggal); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div>
                <label for="tahun_ajaran" class="block text-gray-700 text-sm font-bold mb-2">Tahun Ajaran:</label>
                <select id="tahun_ajaran" name="tahun_ajaran" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <?php foreach ($all_tahun_ajaran as $ta): ?>
                        <option value="<?php echo htmlspecialchars($ta['tahun_ajaran']); ?>"
                                <?php echo ($selected_tahun_ajaran == $ta['tahun_ajaran']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ta['tahun_ajaran']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="semester" class="block text-gray-700 text-sm font-bold mb-2">Semester:</label>
                <select id="semester" name="semester" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="Ganjil" <?php echo ($selected_semester == 'Ganjil') ? 'selected' : ''; ?>>Ganjil</option>
                    <option value="Genap" <?php echo ($selected_semester == 'Genap') ? 'selected' : ''; ?>>Genap</option>
                </select>
            </div>
            <div class="md:col-span-3 flex justify-end">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                    Tampilkan Guru
                </button>
            </div>
        </form>
    </div>

    <!-- Daftar Guru dan Form Absensi -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold mb-4">Daftar Guru (Tanggal: <?php echo htmlspecialchars(format_date_indonesian($selected_tanggal)); ?>)</h2>
        
        <?php if (empty($all_guru)): ?>
            <p class="text-gray-600">Tidak ada guru ditemukan atau tidak ada guru aktif.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                    <thead>
                        <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">No.</th>
                            <th class="py-3 px-6 text-left">NIP</th>
                            <th class="py-3 px-6 text-left">Nama Guru</th>
                            <th class="py-3 px-6 text-center">Status Absensi</th>
                            <th class="py-3 px-6 text-center">Waktu Masuk</th>
                            <th class="py-3 px-6 text-center">Waktu Pulang</th>
                            <th class="py-3 px-6 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm">
                        <?php $no = 1; ?>
                        <?php foreach ($all_guru as $guru): ?>
                            <?php
                                $current_status = $absensi_data_today[$guru['id']]['status_masuk'] ?? 'Belum Absen';
                                $waktu_masuk = $absensi_data_today[$guru['id']]['waktu_masuk'] ?? '-';
                                $waktu_pulang = $absensi_data_today[$guru['id']]['waktu_pulang'] ?? '-';
                            ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($guru['nip']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($guru['nama_lengkap']); ?></td>
                                <td class="py-3 px-6 text-center">
                                    <span class="px-2 py-1 font-semibold leading-tight rounded-full 
                                        <?php 
                                            if ($current_status == 'Hadir') echo 'bg-green-100 text-green-800';
                                            else if ($current_status == 'Terlambat') echo 'bg-yellow-100 text-yellow-800';
                                            else if ($current_status == 'Sakit') echo 'bg-blue-100 text-blue-800';
                                            else if ($current_status == 'Izin') echo 'bg-purple-100 text-purple-800';
                                            else if ($current_status == 'Alpha') echo 'bg-red-100 text-red-800';
                                            else echo 'bg-gray-100 text-gray-800'; // Belum Absen
                                        ?>">
                                        <?php echo htmlspecialchars($current_status); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-6 text-center"><?php echo $waktu_masuk !== '-' ? substr(htmlspecialchars($waktu_masuk), 0, 5) : '-'; ?></td>
                                <td class="py-3 px-6 text-center"><?php echo $waktu_pulang !== '-' ? substr(htmlspecialchars($waktu_pulang), 0, 5) : '-'; ?></td>
                                <td class="py-3 px-6 text-center whitespace-nowrap">
                                    <form action="" method="POST" class="inline-block">
                                        <input type="hidden" name="action" value="record_absensi">
                                        <input type="hidden" name="guru_id" value="<?php echo htmlspecialchars($guru['id']); ?>">
                                        <input type="hidden" name="tanggal_absensi" value="<?php echo htmlspecialchars($selected_tanggal); ?>">
                                        <select name="status_absensi" class="shadow border rounded py-1 px-2 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-xs">
                                            <?php foreach ($status_kehadiran_options as $status): ?>
                                                <option value="<?php echo htmlspecialchars($status); ?>"
                                                    <?php echo ($current_status == $status) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($status); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-1 px-3 rounded text-xs ml-2">
                                            Simpan
                                        </button>
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
