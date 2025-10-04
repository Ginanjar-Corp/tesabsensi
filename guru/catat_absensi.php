<?php
// project-root/guru/catat_absensi.php

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


// Pastikan pengguna sudah login dan memiliki peran Guru
require_login();
require_role('Guru');

$error_message = flash('error');
$success_message = flash('success');

$jadwal_model = null;
$siswa_model = null;
$absensi_mapel_siswa_model = null;
$absensi_mapel_guru_model = null;
$guru_model = null;
$pdo = null;

$jadwal_id = (int)($_GET['jadwal_id'] ?? 0);
$selected_jadwal = null;
$list_siswa_kelas = [];
$current_attendance = [];
$guru_id_pencatat = $_SESSION['related_id']; // ID guru yang sedang login
$teaching_confirmation = null; // Untuk menyimpan status waktu mulai/selesai ajar guru

if ($jadwal_id === 0) {
    set_flash('error', 'Jadwal tidak ditemukan.');
    redirect(ROOT_URL . 'guru/absensi_mapel.php');
}

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $jadwal_model = new JadwalPelajaran($pdo);
    $siswa_model = new Siswa($pdo);
    $absensi_mapel_siswa_model = new AbsensiMapelSiswa($pdo);
    $absensi_mapel_guru_model = new AbsensiMapelGuru($pdo);
    $guru_model = new Guru($pdo);
    $school_settings = (new SchoolSettings($pdo))->getSettings();

    // --- VALIDASI PENTING: Cek apakah guru sudah absen masuk hari ini ---
    $is_guru_checked_in = isGuruCheckedInToday($pdo, $guru_id_pencatat);
    if (!$is_guru_checked_in) {
        set_flash('error', 'Anda harus absen masuk terlebih dahulu sebelum dapat mencatat absensi siswa.');
        redirect(ROOT_URL . 'guru/dashboard.php'); // Arahkan kembali ke dashboard guru
        exit(); // Pastikan skrip berhenti di sini
    }
    // --- Akhir Validasi ---

    $selected_jadwal = $jadwal_model->findById($jadwal_id);

    if (!$selected_jadwal) {
        set_flash('error', 'Jadwal tidak ditemukan.');
        redirect(ROOT_URL . 'guru/absensi_mapel.php');
    }

    // Pastikan guru yang login adalah guru pengampu jadwal ini
    // Atau setidaknya guru yang berhak mencatat absensi (misal: guru piket)
    // Untuk saat ini, kita asumsikan guru yang login adalah guru_id di jadwal
    if ($selected_jadwal['guru_id'] != $guru_id_pencatat) {
        set_flash('error', 'Anda tidak memiliki akses untuk mencatat absensi jadwal ini.');
        redirect(ROOT_URL . 'guru/absensi_mapel.php');
    }

    $list_siswa_kelas = $jadwal_model->getStudentsInClass($selected_jadwal['kelas_id']);
    $current_attendance_raw = $absensi_mapel_siswa_model->getAttendanceForSchedule($jadwal_id, date('Y-m-d'));

    // Dapatkan status konfirmasi pengajaran guru untuk hari ini
    $teaching_confirmation = $absensi_mapel_guru_model->getTeachingConfirmation($guru_id_pencatat, $jadwal_id, date('Y-m-d'));


    // Ubah array absensi saat ini menjadi map untuk akses mudah
    foreach ($current_attendance_raw as $att) {
        $current_attendance[$att['siswa_id']] = $att;
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $tanggal_absen = date('Y-m-d');
        $waktu_absen = date('H:i:s');

        // Rekam konfirmasi pengajaran guru (jika belum ada, buat; jika sudah ada, update waktu mulai)
        $absensi_mapel_guru_model->recordTeachingConfirmation($guru_id_pencatat, $jadwal_id, $tanggal_absen, $waktu_absen);

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

    if ($absensi_mapel_siswa_model->recordAttendance($siswa_id, $jadwal_id, $guru_id_pencatat, $tanggal_absen, $waktu_absen, $status_kehadiran, $catatan)) {
        $success_count++;

        try {
            // Ambil data siswa yang lebih lengkap, termasuk nomor HP orang tua
            $siswa_data = $siswa_model->getById($siswa_id);
            // Ambil data guru yang mencatat
            $guru_data = $guru_model->getById($guru_id_pencatat);
            
            // Periksa apakah data penting ada
            if ($siswa_data && !empty($siswa_data['no_telp_ortu'])) {
                $recipient_number = $siswa_data['no_telp_ortu'];
                $template_name = 'notifikasi_absensi_mapel';
                $template_params = [
                    $siswa_data['nama_lengkap'],
                    $selected_jadwal['nama_mapel'],
                    $selected_jadwal['nama_kelas'],
                    $status_kehadiran_teks,
                    $guru_data['nama_lengkap']
                ];
                $message_text = ''; // Kosongkan, akan dibuat di whatsapp_notifier
                $notification_queue_model = new NotificationQueue($pdo);
                $queue_result = $notification_queue_model->addNotificationToQueue($recipient_number, $template_name, $template_params);

                if ($queue_result['status'] === 'success') {
                    error_log("Notifikasi absensi mapel untuk siswa {$siswa_data['nama_lengkap']} berhasil ditambahkan ke antrean.");
                } else {
                    error_log("Gagal menambahkan notifikasi absensi mapel untuk siswa {$siswa_data['nama_lengkap']} ke antrean: " . $queue_result['message']);
                }
            } else {
                error_log("Nomor telepon orang tua siswa tidak ditemukan atau data siswa tidak lengkap untuk notifikasi absensi.");
            }
        } catch (Exception $e) {
            error_log("Error menambahkan notifikasi absensi mapel ke antrean: " . $e->getMessage());
        }
    } else {
        $fail_count++;
    }
}

        if ($fail_count === 0) {
            set_flash('success', 'Absensi berhasil dicatat untuk semua siswa.');
        } else {
            set_flash('warning', "Absensi dicatat dengan $success_count berhasil dan $fail_count gagal.");
        }
        redirect(ROOT_URL . 'guru/catat_absensi.php?jadwal_id=' . $jadwal_id);
    }

} catch (PDOException $e) {
    error_log("Guru Catat Absensi Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
    redirect(ROOT_URL . 'guru/absensi_mapel.php');
} catch (Exception $e) {
    error_log("Guru Catat Absensi General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
    redirect(ROOT_URL . 'guru/absensi_mapel.php');
}

// Sertakan header
require_once __DIR__ . '/../components/header_guru.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Catat Absensi Siswa</h1>

    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-semibold mb-4">Detail Jadwal</h2>
        <p><strong>Mata Pelajaran:</strong> <?php echo htmlspecialchars($selected_jadwal['nama_mapel']); ?></p>
        <p><strong>Kelas:</strong> <?php echo htmlspecialchars($selected_jadwal['nama_kelas']); ?></p>
        <p><strong>Guru Pengajar:</strong> <?php echo htmlspecialchars($selected_jadwal['nama_guru']); ?></p>
        <p><strong>Hari, Waktu:</strong> <?php echo htmlspecialchars($selected_jadwal['hari']); ?>, <?php echo substr(htmlspecialchars($selected_jadwal['jam_mulai']), 0, 5) . ' - ' . substr(htmlspecialchars($selected_jadwal['jam_selesai']), 0, 5); ?></p>
        <p><strong>Ruangan:</strong> <?php echo htmlspecialchars($selected_jadwal['ruangan'] ?? '-'); ?></p>
        <p><strong>Tanggal Absen:</strong> <?php echo htmlspecialchars(format_date_indonesian(date('Y-m-d'))); ?></p>
        <p><strong>Waktu Mulai Ajar:</strong> <span id="waktuMulaiAjar"><?php echo htmlspecialchars($teaching_confirmation && isset($teaching_confirmation['waktu_mulai_ajar']) ? substr($teaching_confirmation['waktu_mulai_ajar'], 0, 5) : '-'); ?></span></p>
        <p><strong>Waktu Selesai Ajar:</strong> <span id="waktuSelesaiAjar"><?php echo htmlspecialchars($teaching_confirmation && isset($teaching_confirmation['waktu_selesai_ajar']) ? substr($teaching_confirmation['waktu_selesai_ajar'], 0, 5) : '-'); ?></span></p>
    </div>

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

    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold mb-4">Daftar Siswa Kelas <?php echo htmlspecialchars($selected_jadwal['nama_kelas']); ?></h2>
        <?php if (empty($list_siswa_kelas)): ?>
            <p class="text-gray-600">Tidak ada siswa di kelas ini.</p>
        <?php else: ?>
            <form id="absensiSiswaForm" action="<?php echo ROOT_URL; ?>guru/catat_absensi.php?jadwal_id=<?php echo htmlspecialchars($jadwal_id); ?>" method="POST">
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                                <th class="py-3 px-6 text-left">No.</th>
                                <th class="py-3 px-6 text-left">NISN</th>
                                <th class="py-3 px-6 text-left">Nama Siswa</th>
                                <th class="py-3 px-6 text-center">Status Kehadiran</th>
                                <th class="py-3 px-6 text-left">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 text-sm">
                            <?php $no = 1; ?>
                            <?php foreach ($list_siswa_kelas as $siswa): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                    <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                    <td class="py-3 px-6"><?php echo htmlspecialchars($siswa['nisn'] ?? '-'); ?></td>
                                    <td class="py-3 px-6"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
                                    <td class="py-3 px-6 text-center">
                                        <?php $current_status = $current_attendance[$siswa['id']]['status_kehadiran'] ?? 'H'; // Default Hadir ?>
                                        <select name="status_kehadiran[<?php echo htmlspecialchars($siswa['id']); ?>]" class="shadow border rounded py-1 px-2 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                            <option value="H" <?php echo ($current_status == 'H') ? 'selected' : ''; ?>>Hadir</option>
                                            <option value="S" <?php echo ($current_status == 'S') ? 'selected' : ''; ?>>Sakit</option>
                                            <option value="I" <?php echo ($current_status == 'I') ? 'selected' : ''; ?>>Izin</option>
                                            <option value="A" <?php echo ($current_status == 'A') ? 'selected' : ''; ?>>Alpha</option>
                                        </select>
                                    </td>
                                    <td class="py-3 px-6">
                                        <input type="text" name="catatan[<?php echo htmlspecialchars($siswa['id']); ?>]" value="<?php echo htmlspecialchars($current_attendance[$siswa['id']]['catatan'] ?? ''); ?>" class="shadow appearance-none border rounded w-full py-1 px-2 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="Catatan (opsional)">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="flex justify-end mt-6 space-x-4">
                    <button type="submit" id="simpanAbsensiBtn" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                        Simpan Absensi
                    </button>
                    <button type="button" id="selesaiMengajarBtn" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                        Selesai Mengajar
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const absensiSiswaForm = document.getElementById('absensiSiswaForm');
    const simpanAbsensiBtn = document.getElementById('simpanAbsensiBtn');
    const selesaiMengajarBtn = document.getElementById('selesaiMengajarBtn');
    const waktuMulaiAjarSpan = document.getElementById('waktuMulaiAjar');
    const waktuSelesaiAjarSpan = document.getElementById('waktuSelesaiAjar');

    const jadwalId = <?php echo json_encode($jadwal_id); ?>;
    const guruId = <?php echo json_encode($guru_id_pencatat); ?>;
    const tanggalAjar = <?php echo json_encode(date('Y-m-d')); ?>;
    const waktuSelesaiAjarTercatat = <?php echo json_encode($teaching_confirmation['waktu_selesai_ajar'] ?? null); ?>;

    // Fungsi untuk menonaktifkan form dan tombol
    function disableFormAndButtons() {
        if (absensiSiswaForm) {
            const formElements = absensiSiswaForm.elements;
            for (let i = 0; i < formElements.length; i++) {
                formElements[i].disabled = true;
            }
        }
        if (simpanAbsensiBtn) {
            simpanAbsensiBtn.disabled = true;
            simpanAbsensiBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }
        if (selesaiMengajarBtn) {
            selesaiMengajarBtn.disabled = true;
            selesaiMengajarBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }
    }

    // Cek apakah waktu selesai ajar sudah tercatat saat halaman dimuat
    if (waktuSelesaiAjarTercatat) {
        disableFormAndButtons();
        // Tampilkan pesan bahwa absensi sudah selesai untuk sesi ini
        const messageDiv = document.createElement('div');
        messageDiv.className = 'bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4';
        messageDiv.innerHTML = '<span class="block sm:inline">Sesi mengajar ini sudah ditandai selesai. Absensi tidak dapat diubah lagi.</span>';
        absensiSiswaForm.parentNode.insertBefore(messageDiv, absensiSiswaForm);
    }

    // Event listener untuk tombol "Selesai Mengajar"
    if (selesaiMengajarBtn) {
        selesaiMengajarBtn.addEventListener('click', function() {
            if (confirm('Apakah Anda yakin ingin menandai sesi mengajar ini selesai? Setelah ini, absensi tidak dapat diubah lagi.')) {
                // Kirim permintaan AJAX untuk memperbarui waktu_selesai_ajar
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
                        // Ganti alert dengan modal custom jika memungkinkan
                        alert(data.message);
                        waktuSelesaiAjarSpan.textContent = data.waktu_selesai_ajar.substring(0, 5);
                        disableFormAndButtons();
                        window.location.reload(); // Refresh untuk menampilkan status terbaru
                    } else {
                        // Ganti alert dengan modal custom jika memungkinkan
                        alert('Gagal menandai sesi selesai: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Ganti alert dengan modal custom jika memungkinkan
                    alert('Terjadi kesalahan jaringan atau server saat menandai sesi selesai.');
                });
            }
        });
    }
});
</script>

<?php
// Sertakan footer
require_once __DIR__ . '/../components/footer.php';
?>
