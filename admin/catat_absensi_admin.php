<?php
// project-root/admin/catat_absensi_admin.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/JadwalPelajaran.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/AbsensiMapelSiswa.php';
require_once __DIR__ . '/../classes/Guru.php';
require_once __DIR__ . '/../classes/NotificationQueue.php';
require_once __DIR__ . '/../classes/SchoolSettings.php';

// Pastikan pengguna sudah login dan memiliki peran Admin atau Tata Usaha
require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$jadwal_model = null;
$siswa_model = null;
$absensi_mapel_siswa_model = null;
$guru_model = null;
$notification_queue_model = null;
$pdo = null;

$jadwal_id = (int)($_GET['jadwal_id'] ?? 0);
$selected_jadwal = null;
$list_siswa_kelas = [];
$current_attendance = [];
$guru_pengampu_data = null; 

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $jadwal_model = new JadwalPelajaran($pdo);
    $siswa_model = new Siswa($pdo);
    $absensi_mapel_siswa_model = new AbsensiMapelSiswa($pdo);
    $guru_model = new Guru($pdo);
    $notification_queue_model = new NotificationQueue($pdo);
    $school_settings = (new SchoolSettings($pdo))->getSettings();

    // Dapatkan jadwal yang dipilih
    $selected_jadwal = $jadwal_model->getById($jadwal_id);

    if (!$selected_jadwal) {
        throw new Exception("Jadwal tidak ditemukan.");
    }
    
    // Ambil data guru pengampu dari jadwal
    $guru_pengampu_data = $guru_model->getById($selected_jadwal['guru_id']);

    // Dapatkan daftar siswa di kelas tersebut
    $list_siswa_kelas = $siswa_model->getByKelasId($selected_jadwal['kelas_id']);

    // Dapatkan absensi yang sudah tercatat untuk jadwal ini
    $tanggal_absen = date('Y-m-d');
    $current_attendance = $absensi_mapel_siswa_model->getByJadwalIdAndTanggal($jadwal_id, $tanggal_absen);

} catch (Exception $e) {
    $error_message = "Terjadi kesalahan: " . $e->getMessage();
}

// Proses form saat data dikirim (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$selected_jadwal) {
            throw new Exception("Jadwal tidak valid.");
        }

        $tanggal_absen = date('Y-m-d');
        $waktu_absen = date('H:i:s');
        
        // **PERBAIKAN UTAMA DI SINI**
        // Ambil ID pengguna dari sesi, bukan variabel yang tidak terdefinisi
        $operator_id = $_SESSION['user_data']['id'] ?? null;
        if (!$operator_id) {
            throw new Exception("ID operator tidak ditemukan.");
        }

        $success_count = 0;
        $fail_count = 0;

        foreach ($list_siswa_kelas as $siswa) {
            $siswa_id = $siswa['id'];
            $status_kehadiran = $_POST['status_kehadiran'][$siswa_id] ?? 'A'; // Default Alpha
            $catatan = trim($_POST['catatan'][$siswa_id] ?? null);

            // Mendapatkan nama status kehadiran yang lebih deskriptif
            $status_kehadiran_teks = '';
            switch ($status_kehadiran) {
                case 'H': $status_kehadiran_teks = 'Hadir'; break;
                case 'I': $status_kehadiran_teks = 'Izin'; break;
                case 'S': $status_kehadiran_teks = 'Sakit'; break;
                case 'A': $status_kehadiran_teks = 'Alpa'; break;
                default: $status_kehadiran_teks = 'Tidak diketahui'; break;
            }
            
            // Catat ID guru pengampu dari jadwal ke tabel absensi
            $guru_id_jadwal = $selected_jadwal['guru_id'];
            
            // Panggil metode recordAttendance dengan parameter yang benar
            if ($absensi_mapel_siswa_model->recordAttendance($siswa_id, $jadwal_id, $guru_id_jadwal, $tanggal_absen, $waktu_absen, $status_kehadiran, $catatan)) {
                $success_count++;

                try {
                    // Ambil data siswa yang lebih lengkap, termasuk nomor HP orang tua
                    $siswa_data = $siswa_model->getById($siswa_id);
                    
                    // Periksa apakah data penting ada
                    if ($siswa_data && !empty($siswa_data['no_telp_ortu'])) {
                        $recipient_number = $siswa_data['no_telp_ortu'];
                        $template_name = 'absensi_manual_mapel'; // Template baru
                        $template_params = [
                            $siswa_data['nama_lengkap'],
                            $selected_jadwal['nama_mapel'],
                            $selected_jadwal['nama_kelas'],
                            $status_kehadiran_teks,
                            $guru_pengampu_data['nama_lengkap'] 
                        ];
                        
                        $queue_result = $notification_queue_model->addNotificationToQueue($recipient_number, $template_name, $template_params);

                        if ($queue_result['status'] === 'success') {
                            error_log("Notifikasi absensi manual mapel untuk siswa {$siswa_data['nama_lengkap']} berhasil ditambahkan ke antrean.");
                        } else {
                            error_log("Gagal menambahkan notifikasi absensi manual mapel untuk siswa {$siswa_data['nama_lengkap']} ke antrean: " . $queue_result['message']);
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error menambahkan notifikasi absensi manual mapel ke antrean: " . $e->getMessage());
                }
            } else {
                $fail_count++;
            }
        }

        if ($success_count > 0) {
            set_flash('success', "Berhasil mencatat absensi untuk $success_count siswa.");
        }
        if ($fail_count > 0) {
            set_flash('error', "Gagal mencatat absensi untuk $fail_count siswa.");
        }

        // Redirect kembali ke halaman ini untuk menghindari double-post
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;

    } catch (Exception $e) {
        $error_message = "Terjadi kesalahan saat menyimpan absensi: " . $e->getMessage();
    }
}

// Sertakan header
require_once __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-white shadow-md rounded-lg p-6">
        <h2 class="text-2xl font-bold mb-4">Catat Absensi Mata Pelajaran</h2>
        <?php if ($selected_jadwal): ?>
            <div class="bg-gray-100 p-4 rounded-lg mb-4">
                <p><strong>Mata Pelajaran:</strong> <?php echo htmlspecialchars($selected_jadwal['nama_mapel']); ?></p>
                <p><strong>Kelas:</strong> <?php echo htmlspecialchars($selected_jadwal['nama_kelas']); ?></p>
                <p><strong>Guru Pengampu:</strong> <?php echo htmlspecialchars($guru_pengampu_data['nama_lengkap'] ?? 'N/A'); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <?php if (empty($list_siswa_kelas)): ?>
            <div class="text-center text-gray-500">
                <p>Tidak ada siswa di kelas ini.</p>
            </div>
        <?php else: ?>
            <form action="" method="post">
                <div class="overflow-x-auto">
                    <table class="min-w-full leading-normal">
                        <thead>
                            <tr>
                                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    No.
                                </th>
                                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Nama Siswa
                                </th>
                                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Status Kehadiran
                                </th>
                                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Catatan
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; ?>
                            <?php foreach ($list_siswa_kelas as $siswa): ?>
                                <?php
                                $status_absensi_siswa = $current_attendance[$siswa['id']]['status'] ?? 'H'; // Default Hadir
                                $catatan_absensi_siswa = $current_attendance[$siswa['id']]['catatan'] ?? '';
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="py-3 px-6 border-b border-gray-200 text-sm"><?php echo $no++; ?></td>
                                    <td class="py-3 px-6 border-b border-gray-200 text-sm"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
                                    <td class="py-3 px-6 border-b border-gray-200 text-sm">
                                        <select name="status_kehadiran[<?php echo htmlspecialchars($siswa['id']); ?>]" class="form-select mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                            <option value="H" <?php echo $status_absensi_siswa == 'H' ? 'selected' : ''; ?>>Hadir</option>
                                            <option value="I" <?php echo $status_absensi_siswa == 'I' ? 'selected' : ''; ?>>Izin</option>
                                            <option value="S" <?php echo $status_absensi_siswa == 'S' ? 'selected' : ''; ?>>Sakit</option>
                                            <option value="A" <?php echo $status_absensi_siswa == 'A' ? 'selected' : ''; ?>>Alpa</option>
                                        </select>
                                    </td>
                                    <td class="py-3 px-6 border-b border-gray-200 text-sm">
                                        <input type="text" name="catatan[<?php echo htmlspecialchars($siswa['id']); ?>]" value="<?php echo htmlspecialchars($catatan_absensi_siswa); ?>" class="form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-opacity-50">
                        Simpan Absensi
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