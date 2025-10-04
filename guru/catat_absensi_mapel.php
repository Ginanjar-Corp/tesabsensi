<?php
// project-root/admin/catat_absensi_mapel.php

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/JadwalPelajaran.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/AbsensiMapelSiswa.php';
require_once __DIR__ . '/../classes/AbsensiMapelGuru.php';
require_once __DIR__ . '/../classes/Guru.php';
require_once __DIR__ . '/../classes/NotificationQueue.php';
require_once __DIR__ . '/../classes/SchoolSettings.php';
require_once __DIR__ . '/../includes/whatsapp_notifier.php';

require_login();
require_role(['Admin', 'TataUsaha', 'Guru']);

$error_message = flash('error');
$success_message = flash('success');

$jadwal_model = null;
$siswa_model = null;
$absensi_mapel_siswa_model = null;
$absensi_mapel_guru_model = null;
$guru_model = null;
$notification_queue_model = null;
$pdo = null;

$jadwal_id = (int)($_GET['jadwal_id'] ?? 0);
$selected_jadwal = null;
$list_siswa_kelas = [];
$current_attendance = [];
$guru_pengampu_data = null; 
$teaching_confirmation = null;
$user_role = $_SESSION['user_data']['role'] ?? null;
$user_id = $_SESSION['user_data']['id'] ?? null;

// Tentukan ID guru yang akan dicatat absensinya (selalu guru pengampu jadwal)
$guru_id_absen = null;

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $jadwal_model = new JadwalPelajaran($pdo);
    $siswa_model = new Siswa($pdo);
    $absensi_mapel_siswa_model = new AbsensiMapelSiswa($pdo);
    $absensi_mapel_guru_model = new AbsensiMapelGuru($pdo);
    $guru_model = new Guru($pdo);
    $notification_queue_model = new NotificationQueue($pdo);
    $school_settings = (new SchoolSettings($pdo))->getSettings();
    $guru_id_pencatat_user = $_SESSION['related_id'] ?? null;

    $selected_jadwal = $jadwal_model->getById($jadwal_id);

    if (!$selected_jadwal) {
        throw new Exception("Jadwal tidak ditemukan.");
    }
    
    // Check the schedule type and fetch students accordingly
    if ($selected_jadwal['tipe_jadwal'] === 'Siswa') {
        // For 'Siswa' type schedules, fetch students from the linking table
        $list_siswa_kelas = $jadwal_model->getStudentsByJadwalId($jadwal_id);
    } else {
        // For 'Kelas' type schedules, fetch all students in the class
        $list_siswa_kelas = $siswa_model->getByKelasId($selected_jadwal['kelas_id']);
    }

    // Pastikan guru_id dari jadwal ada dan valid
    if (empty($selected_jadwal['guru_id'])) {
        throw new Exception("ID guru pengampu tidak ditemukan di jadwal ini.");
    }
    
    // Set guru_id yang akan digunakan untuk pencatatan absensi
    $guru_id_absen = $selected_jadwal['guru_id'];
    
    // --- Validasi Absen Masuk hanya untuk peran 'Guru' ---
    if ($user_role === 'Guru') {
        $is_guru_checked_in = isGuruCheckedInToday($pdo, $guru_id_pencatat_user);
        if (!$is_guru_checked_in) {
            set_flash('error', 'Anda harus absen masuk terlebih dahulu sebelum dapat mencatat absensi siswa.');
            redirect(ROOT_URL . 'guru/dashboard.php');
            exit();
        }
    }
    // --- Akhir Validasi ---

    $guru_pengampu_data = $guru_model->getById($guru_id_absen);
    //$list_siswa_kelas = $siswa_model->getByKelasId($selected_jadwal['kelas_id']);
    $tanggal_absen = date('Y-m-d');
    $current_attendance = $absensi_mapel_siswa_model->getByJadwalIdAndTanggal($jadwal_id, $tanggal_absen);
    $teaching_confirmation = $absensi_mapel_guru_model->getTeachingConfirmation($guru_id_absen, $jadwal_id, date('Y-m-d'));

} catch (Exception $e) {
    $error_message = "Terjadi kesalahan: " . $e->getMessage();
}

// Proses form saat data dikirim (POST)
// Proses form saat data dikirim (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$selected_jadwal || empty($guru_id_absen)) {
            throw new Exception("Data jadwal atau guru pengampu tidak valid.");
        }

        $tanggal_absen = date('Y-m-d');
        $waktu_absen = date('H:i:s');
        $operator_id = $_SESSION['user_data']['id'] ?? null;
        
        if (!$operator_id) {
            throw new Exception("ID operator tidak ditemukan.");
        }
        
        // Rekam konfirmasi pengajaran guru
        $absensi_mapel_guru_model->recordTeachingConfirmation($guru_id_absen, $jadwal_id, $tanggal_absen, $waktu_absen);

        // Mulai transaksi
        $pdo->beginTransaction();

        $success_count = 0;
        $failure_count = 0;

        // Loop melalui data yang dikirim dari form
        $absensi_data = $_POST['absensi'] ?? [];
        $catatan_data = $_POST['catatan'] ?? [];

        foreach ($absensi_data as $siswa_id => $status_kehadiran) {
            $catatan = trim($catatan_data[$siswa_id] ?? '');

            if ($absensi_mapel_siswa_model->recordAttendance($siswa_id, $jadwal_id, $guru_id_absen, $tanggal_absen, $waktu_absen, $status_kehadiran, $catatan)) {
                $success_count++;
                
                // Tambahkan logika notifikasi ke dalam loop
                $siswa_data = $siswa_model->getById($siswa_id);
                $guru_data = $guru_model->getById($guru_id_absen);
                $status_kehadiran_teks = '';
                switch ($status_kehadiran) {
                    case 'H': $status_kehadiran_teks = 'Hadir'; break;
                    case 'I': $status_kehadiran_teks = 'Izin'; break;
                    case 'S': $status_kehadiran_teks = 'Sakit'; break;
                    case 'A': $status_kehadiran_teks = 'Alpa'; break;
                    default: $status_kehadiran_teks = 'Tidak diketahui'; break;
                }
                
                if ($siswa_data && !empty($siswa_data['no_telp_ortu'])) {
                    $recipient_number = $siswa_data['no_telp_ortu'];
                    //$template_name = 'absensi_manual_mapel';
                    $template_params = [
                        $siswa_data['nama_lengkap'],
                        $selected_jadwal['nama_mapel'],
                        $selected_jadwal['nama_kelas'],
                        $status_kehadiran_teks,
                        $guru_data['nama_lengkap']
                    ];
                    //$notification_queue_model->addNotificationToQueue($recipient_number, $template_name, $template_params);
                }
            } else {
                $failure_count++;
                // Mungkin log kegagalan untuk debugging
            }
        }
        
        // Commit transaksi jika semua berhasil
        $pdo->commit();

        if ($failure_count === 0) {
            set_flash('success', "Absensi untuk semua siswa berhasil diperbarui.");
        } else {
            set_flash('error', "Absensi berhasil disimpan untuk {$success_count} siswa, tetapi gagal untuk {$failure_count} siswa.");
        }
        
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;

    } catch (Exception $e) {
        // Rollback jika terjadi kesalahan
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = "Terjadi kesalahan saat menyimpan absensi: " . $e->getMessage();
        set_flash('error', $error_message);
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}

require_once __DIR__ . '/../components/header_guru.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-white shadow-md rounded-lg p-6">
        <h2 class="text-2xl font-bold mb-4">Catat Absensi Mata Pelajaran</h2>
        <?php if ($selected_jadwal): ?>
            <div class="bg-gray-100 p-4 rounded-lg mb-4">
                <p><strong>Mata Pelajaran:</strong> <?php echo htmlspecialchars($selected_jadwal['nama_mapel']); ?></p>
                <p><strong>Kelas:</strong> <?php echo htmlspecialchars($selected_jadwal['nama_kelas']); ?></p>
                <p><strong>Guru Pengampu:</strong> <?php echo htmlspecialchars($guru_pengampu_data['nama_lengkap'] ?? 'N/A'); ?></p>
                <p><strong>Waktu Mulai Ajar:</strong> <span id="waktuMulaiAjar"><?php echo htmlspecialchars($teaching_confirmation && isset($teaching_confirmation['waktu_mulai_ajar']) ? substr($teaching_confirmation['waktu_mulai_ajar'], 0, 5) : '-'); ?></span></p>
                <p><strong>Waktu Selesai Ajar:</strong> <span id="waktuSelesaiAjar"><?php echo htmlspecialchars($teaching_confirmation && isset($teaching_confirmation['waktu_selesai_ajar']) ? substr($teaching_confirmation['waktu_selesai_ajar'], 0, 5) : '-'); ?></span></p>
                <div class="flex justify-end mt-4">
                    <button type="button" id="selesaiMengajarBtn" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                        Selesai Mengajar
                    </button>
                </div>
            </div>
        <?php endif; ?>

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

        <?php if (empty($list_siswa_kelas)): ?>
            <div class="text-center text-gray-500">
                <p>Tidak ada siswa di kelas ini.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
    <form action="" method="post">
        <table class="min-w-full leading-normal" id="absensiTable">
            <thead>
                </thead>
            <tbody>
                <?php $no = 1; ?>
                <?php foreach ($list_siswa_kelas as $siswa): ?>
                    <?php
                    $status_absensi_siswa = $current_attendance[$siswa['id']]['status'] ?? 'H';
                    $catatan_absensi_siswa = $current_attendance[$siswa['id']]['catatan'] ?? '';
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-6 border-b border-gray-200 text-sm"><?php echo $no++; ?></td>
                        <td class="py-3 px-6 border-b border-gray-200 text-sm"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
                        <td class="py-3 px-6 border-b border-gray-200 text-sm">
                            <div class="flex items-center space-x-4">
                                <div class="flex items-center">
                                    <input type="radio" id="hadir_<?php echo htmlspecialchars($siswa['id']); ?>" name="absensi[<?php echo htmlspecialchars($siswa['id']); ?>]" value="H" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300" <?php echo $status_absensi_siswa == 'H' ? 'checked' : ''; ?>>
                                    <label for="hadir_<?php echo htmlspecialchars($siswa['id']); ?>" class="ml-2 block text-sm font-medium text-gray-700">Hadir</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="radio" id="sakit_<?php echo htmlspecialchars($siswa['id']); ?>" name="absensi[<?php echo htmlspecialchars($siswa['id']); ?>]" value="S" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300" <?php echo $status_absensi_siswa == 'S' ? 'checked' : ''; ?>>
                                    <label for="sakit_<?php echo htmlspecialchars($siswa['id']); ?>" class="ml-2 block text-sm font-medium text-gray-700">Sakit</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="radio" id="izin_<?php echo htmlspecialchars($siswa['id']); ?>" name="absensi[<?php echo htmlspecialchars($siswa['id']); ?>]" value="I" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300" <?php echo $status_absensi_siswa == 'I' ? 'checked' : ''; ?>>
                                    <label for="izin_<?php echo htmlspecialchars($siswa['id']); ?>" class="ml-2 block text-sm font-medium text-gray-700">Izin</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="radio" id="alpa_<?php echo htmlspecialchars($siswa['id']); ?>" name="absensi[<?php echo htmlspecialchars($siswa['id']); ?>]" value="A" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300" <?php echo $status_absensi_siswa == 'A' ? 'checked' : ''; ?>>
                                    <label for="alpa_<?php echo htmlspecialchars($siswa['id']); ?>" class="ml-2 block text-sm font-medium text-gray-700">Alpa</label>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-6 border-b border-gray-200 text-sm">
                            <input type="text" name="catatan[<?php echo htmlspecialchars($siswa['id']); ?>]" value="<?php echo htmlspecialchars($catatan_absensi_siswa); ?>" class="form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        </td>
                        <td class="py-3 px-6 border-b border-gray-200 text-sm">
                            </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="flex justify-end mt-6">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-opacity-50">
                Simpan Absensi
            </button>
        </div>
    </form>
</div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selesaiMengajarBtn = document.getElementById('selesaiMengajarBtn');
    const waktuSelesaiAjarSpan = document.getElementById('waktuSelesaiAjar');
    const absensiTable = document.getElementById('absensiTable');
    const waktuSelesaiAjarTercatat = <?php echo json_encode($teaching_confirmation['waktu_selesai_ajar'] ?? null); ?>;

    const jadwalId = <?php echo json_encode($jadwal_id); ?>;
    const guruId = <?php echo json_encode($guru_id_absen); ?>;
    const tanggalAjar = <?php echo json_encode(date('Y-m-d')); ?>;

    // Fungsi untuk menonaktifkan form dan tombol
    function disableTableAndButtons() {
        if (absensiTable) {
            const formElements = absensiTable.querySelectorAll('input, select, button');
            formElements.forEach(element => {
                element.disabled = true;
                element.classList.add('opacity-50', 'cursor-not-allowed');
            });
        }
        if (selesaiMengajarBtn) {
            selesaiMengajarBtn.disabled = true;
            selesaiMengajarBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }
    }
    
    // Cek apakah waktu selesai ajar sudah tercatat saat halaman dimuat
    if (waktuSelesaiAjarTercatat) {
        disableTableAndButtons();
        const messageDiv = document.createElement('div');
        messageDiv.className = 'bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4';
        messageDiv.innerHTML = '<span class="block sm:inline">Sesi mengajar ini sudah ditandai selesai. Absensi tidak dapat diubah lagi.</span>';
        absensiTable.parentNode.insertBefore(messageDiv, absensiTable);
    }


    // Event listener untuk tombol "Selesai Mengajar"
    if (selesaiMengajarBtn) {
        selesaiMengajarBtn.addEventListener('click', function() {
            if (confirm('Apakah Anda yakin ingin menandai sesi mengajar ini selesai? Setelah ini, absensi tidak dapat diubah lagi.')) {
                fetch('<?php echo ROOT_URL; ?>api/update_waktu_selesai_ajar.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `jadwal_id=${jadwalId}&guru_id=${guruId}&tanggal_ajar=${tanggalAjar}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(data.message);
                        waktuSelesaiAjarSpan.textContent = data.waktu_selesai_ajar.substring(0, 5);
                        disableTableAndButtons();
                        window.location.reload();
                    } else {
                        alert('Gagal menandai sesi selesai: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan jaringan atau server saat menandai sesi selesai.');
                });
            }
        });
    }

});
</script>

<?php
require_once __DIR__ . '/../components/footer.php';
?>