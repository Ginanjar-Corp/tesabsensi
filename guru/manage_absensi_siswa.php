<?php
// project-root/staff/manage_absensi_siswa.php

session_start(); // Pastikan session dimulai jika belum di includes/auth.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/Kelas.php';
require_once __DIR__ . '/../classes/AbsensiHarian.php'; // Kelas AbsensiHarian untuk mencatat S/I/A
require_once __DIR__ . '/../classes/NotificationQueue.php'; // Tambahkan ini
require_once __DIR__ . '/../classes/SchoolSettings.php'; // Tambahkan ini
require_once __DIR__ . '/../includes/whatsapp_notifier.php'; // Tambahkan ini untuk template pesan

// Pastikan pengguna sudah login dan memiliki peran Admin atau Tata Usaha
require_login();
require_role(['Admin', 'TataUsaha', 'Guru']);

$error_message = flash('error');
$success_message = flash('success');

$siswa_model = null;
$kelas_model = null;
$absensi_harian_model = null;
$notification_queue_model = null; // Inisialisasi model notifikasi
$school_settings_model = null;   // Inisialisasi model pengaturan sekolah
$pdo = null;

$all_siswa = [];
$all_kelas = [];
$all_tahun_ajaran = [];
$selected_kelas_id = $_GET['kelas_id'] ?? null;
$selected_tanggal = $_GET['tanggal'] ?? date('Y-m-d');
$selected_tahun_ajaran = $_GET['tahun_ajaran'] ?? null;
$selected_semester = $_GET['semester'] ?? APP_SEMESTER_AKTIF;

// Status kehadiran yang tersedia untuk input manual
// Status kehadiran yang tersedia untuk input manual
$status_kehadiran_options = ['Hadir', 'Sakit', 'Izin', 'Alpha', 'Terlambat', 'Pulang'];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $siswa_model = new Siswa($pdo);
    $kelas_model = new Kelas($pdo);
    $absensi_harian_model = new AbsensiHarian($pdo);
    $notification_queue_model = new NotificationQueue($pdo); // Inisialisasi
    $school_settings_model = new SchoolSettings($pdo);       // Inisialisasi
    $settings = $school_settings_model->getSettings();       // Ambil pengaturan

    $all_kelas = $kelas_model->getAll();
    $all_tahun_ajaran = getAllTahunAjaran(); // Dari includes/helpers.php

    // Ambil tahun ajaran aktif sebagai default jika belum dipilih
    if (!$selected_tahun_ajaran) {
        $active_ta_data = getActiveTahunAjaran();
        $selected_tahun_ajaran = $active_ta_data['tahun_ajaran'] ?? null;
    }

    // Handle form submission untuk mencatat absensi manual secara massal
    // Handle form submission untuk mencatat absensi manual secara massal
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_absensi_batch') {
        $absensi_data = $_POST['siswa_absensi'] ?? [];
        $tanggal_absensi = trim($_POST['tanggal_absensi'] ?? date('Y-m-d'));
        
        $success_count = 0;
        $error_count = 0;
    
        foreach ($absensi_data as $siswa_id => $data) {
            $siswa_id = (int) $siswa_id;
            $status_absensi = trim($data['status'] ?? '');
            $kelas_id_siswa = (int)($data['kelas_id'] ?? null);
    
            // Validasi input
            if (empty($siswa_id) || empty($status_absensi) || !in_array($status_absensi, $status_kehadiran_options)) {
                $error_count++;
                error_log("Data absensi tidak lengkap atau status tidak valid untuk siswa ID: " . $siswa_id);
                continue; // Lanjut ke siswa berikutnya
            }
    
            // Tentukan tipe absensi berdasarkan status yang dipilih
            if ($status_absensi === 'Pulang') {
                $tipe_absensi = 'pulang';
                $status_manual = 'Pulang';
            } else {
                $tipe_absensi = 'manual';
                $status_manual = $status_absensi;
            }
    
            // Panggil metode untuk mencatat absensi dengan tipe
            $result = $absensi_harian_model->recordSiswaAttendanceWithType(
                $siswa_id,
                $tipe_absensi, // Tipe absensi: 'manual' atau 'pulang'
                $selected_tahun_ajaran,
                $selected_semester,
                $kelas_id_siswa,
                $status_manual // Status yang akan dicatat
            );

            if ($result['status'] === 'success') {
                $success_count++;

                // --- Tambahkan Notifikasi WhatsApp ke Antrean ---
                //$siswa_info = $siswa_model->findById($siswa_id);
                if ($siswa_info && ($siswa_info['no_telp_ortu'] ?? null)) {
                    $phone_number_clean = ltrim($siswa_info['no_telp_ortu'], '+');
                    $nama_siswa = $siswa_info['nama_lengkap'];
                    $waktu_absensi_manual = date('H:i');

                    //$template_name = 'absensi_notifikasi_manual_siswa';
                    //$template_params = [
                    //    $nama_siswa,
                    //    $status_absensi,
                    //    $tanggal_absensi,
                    //    $waktu_absensi_manual
                    //];

                    //$queue_result = $notification_queue_model->addNotificationToQueue(
                    //    $phone_number_clean,
                    //     $template_name,
                    //     $template_params
                    //);

                    if ($queue_result['status'] !== 'success') {
                        error_log("Gagal menambahkan notifikasi manual siswa " . $nama_siswa . " ke antrean: " . $queue_result['message']);
                    }
                }
            } else {
                $error_count++;
                error_log("Gagal mencatat absensi untuk siswa ID " . $siswa_id . ": " . $result['message']);
            }
        }
        
        // Berikan pesan feedback gabungan
        if ($success_count > 0) {
            set_flash('success', $success_count . ' data absensi berhasil disimpan. ' . ($error_count > 0 ? $error_count . ' data gagal disimpan.' : ''));
        } elseif ($error_count > 0) {
            set_flash('error', 'Gagal menyimpan ' . $error_count . ' data absensi.');
        } else {
            set_flash('error', 'Tidak ada data absensi yang diproses.');
        }

        // Redirect kembali ke halaman dengan filter yang sama
        redirect(ROOT_URL . 'guru/manage_absensi_siswa.php?kelas_id=' . urlencode($selected_kelas_id ?? '') . '&tanggal=' . urlencode($tanggal_absensi) . '&tahun_ajaran=' . urlencode($selected_tahun_ajaran) . '&semester=' . urlencode($selected_semester));
    }

    // Ambil daftar siswa berdasarkan filter kelas yang dipilih
    if ($selected_kelas_id) {
        $all_siswa = $siswa_model->getSiswaByKelas($selected_kelas_id);
    } else {
        // Perbaikan: Menggunakan getFilteredSiswa untuk mengambil siswa aktif
        $all_siswa = $siswa_model->getFilteredSiswa('', 'Aktif'); 
    }

    // Ambil status absensi siswa untuk tanggal yang dipilih
    $absensi_data_today = [];
    if (!empty($all_siswa) && $selected_tahun_ajaran && $selected_semester) {
        $stmt = $pdo->prepare("SELECT siswa_id, status_masuk, waktu_masuk, waktu_pulang FROM absensi_harian_siswa WHERE tanggal = ? AND tahun_ajaran = ? AND semester = ?");
        $stmt->execute([$selected_tanggal, $selected_tahun_ajaran, $selected_semester]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $absensi_data_today[$row['siswa_id']] = $row;
        }
    }

} catch (PDOException $e) {
    error_log("Manajemen Absensi Siswa Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Manajemen Absensi Siswa General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
}

// Sertakan header admin
require_once __DIR__ . '/../components/header_guru.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manajemen Absensi Siswa (Manual)</h1>

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
        <h2 class="text-xl font-semibold mb-4">Filter Absensi</h2>
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label for="tanggal" class="block text-gray-700 text-sm font-bold mb-2">Tanggal:</label>
                <input type="date" id="tanggal" name="tanggal" value="<?php echo htmlspecialchars($selected_tanggal); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div>
                <label for="kelas_id" class="block text-gray-700 text-sm font-bold mb-2">Pilih Kelas:</label>
                <select id="kelas_id" name="kelas_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($all_kelas as $kelas): ?>
                        <option value="<?php echo htmlspecialchars($kelas['id']); ?>" <?php echo ($selected_kelas_id == $kelas['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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
            <div class="md:col-span-4 flex justify-end">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                    Tampilkan Siswa
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold mb-4">Daftar Siswa Kelas <?php echo $selected_kelas_id ? htmlspecialchars($kelas_model->findById($selected_kelas_id)['nama_kelas']) : 'Semua'; ?> (Tanggal: <?php echo htmlspecialchars(format_date_indonesian($selected_tanggal)); ?>)</h2>
        
        <?php if (empty($all_siswa)): ?>
            <p class="text-gray-600">Tidak ada siswa ditemukan untuk kelas ini atau tidak ada siswa aktif.</p>
        <?php else: ?>
            <form action="" method="POST">
                <input type="hidden" name="action" value="record_absensi_batch">
                <input type="hidden" name="tanggal_absensi" value="<?php echo htmlspecialchars($selected_tanggal); ?>">

                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                                <th class="py-3 px-6 text-left">No.</th>
                                <!--<th class="py-3 px-6 text-left">NISN</th>-->
                                <th class="py-3 px-6 text-left">Nama Siswa</th>
                                <th class="py-3 px-6 text-center">Ubah Status</th>
                                <th class="py-3 px-6 text-left">Kelas</th>
                                <th class="py-3 px-6 text-center">Status Absensi</th>
                                <th class="py-3 px-6 text-center">Waktu Masuk</th>
                                <th class="py-3 px-6 text-center">Waktu Pulang</th>
                                
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 text-sm">
                            <?php $no = 1; ?>
                            <?php foreach ($all_siswa as $siswa): ?>
                                <?php
                                    $current_status = $absensi_data_today[$siswa['id']]['status_masuk'] ?? 'Belum Absen';
                                    $waktu_masuk = $absensi_data_today[$siswa['id']]['waktu_masuk'] ?? '-';
                                    $waktu_pulang = $absensi_data_today[$siswa['id']]['waktu_pulang'] ?? '-';
                                ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                    <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                    <!--<td class="py-3 px-6"><?php echo htmlspecialchars($siswa['nisn']); ?></td>-->
                                    <td class="py-3 px-6"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
                                    <td class="py-3 px-6 text-center whitespace-nowrap">
                                        <input type="hidden" name="siswa_absensi[<?php echo htmlspecialchars($siswa['id']); ?>][kelas_id]" value="<?php echo htmlspecialchars($siswa['kelas_id']); ?>">
                                        <select name="siswa_absensi[<?php echo htmlspecialchars($siswa['id']); ?>][status]" class="shadow border rounded py-1 px-2 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-xs">
                                            <?php foreach ($status_kehadiran_options as $status): ?>
                                                <option value="<?php echo htmlspecialchars($status); ?>"
                                                    <?php echo ($current_status == $status) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($status); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="py-3 px-6"><?php echo htmlspecialchars($siswa['nama_kelas']); ?></td>
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
                                    
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="submit" class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                        Simpan Semua Absensi
                    </button>
                </div>
            </form>
            <?php endif; ?>
    </div>
</div>

<?php
// Sertakan footer
require_once __DIR__ . '/../components/footer.php';
?>