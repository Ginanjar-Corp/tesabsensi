<?php
// project-root/admin/rekap_absensi.php

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/Kelas.php';
require_once __DIR__ . '/../classes/AbsensiHarian.php';
require_once __DIR__ . '/../classes/NotificationQueue.php';

require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$pdo = Database::getInstance()->getConnection();
$siswa_model = new Siswa($pdo);
$kelas_model = new Kelas($pdo);
$absensi_harian_model = new AbsensiHarian($pdo);
$notification_queue_model = new NotificationQueue($pdo);

$all_kelas = $kelas_model->getAll();

$selected_kelas_id = $_POST['kelas_id'] ?? ($_GET['kelas_id'] ?? null);
$startDate = $_POST['start_date'] ?? ($_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days')));
$endDate = $_POST['end_date'] ?? ($_GET['end_date'] ?? date('Y-m-d'));

$siswa_absensi_data = [];

$days_in_id = [
    'Sunday' => 'Minggu',
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu'
];

$months_in_id = [
    'January' => 'Januari',
    'February' => 'Februari',
    'March' => 'Maret',
    'April' => 'April',
    'May' => 'Mei',
    'June' => 'Juni',
    'July' => 'Juli',
    'August' => 'Agustus',
    'September' => 'September',
    'October' => 'Oktober',
    'November' => 'November',
    'December' => 'Desember'
];

// Logika untuk menampilkan data absensi (saat filter di-submit)
if (isset($_POST['action']) && $_POST['action'] === 'view_report') {
    if (!empty($selected_kelas_id)) {
        $siswa_by_kelas = $siswa_model->getSiswaByKelas($selected_kelas_id);
        
        foreach ($siswa_by_kelas as $siswa) {
            $absensi_mingguan = $absensi_harian_model->getAbsensiHarianBySiswaId($siswa['id'], $startDate, $endDate);
            
            $siswa_absensi_data[] = [
                'siswa' => $siswa,
                'absensi' => $absensi_mingguan
            ];
        }
    } else {
        set_flash('error', 'Silakan pilih kelas terlebih dahulu.');
        redirect(ROOT_URL . 'admin/rekap_absensi.php');
    }
}

// Logika untuk mengirim notifikasi (saat tombol kirim di-submit)
if (isset($_POST['action']) && $_POST['action'] === 'send_weekly_summary') {
    $selected_siswa_ids = $_POST['selected_siswa'] ?? [];

    if (empty($selected_siswa_ids)) {
        set_flash('error', 'Tidak ada siswa yang dipilih.');
    } else {
        try {
            $count_sent = 0;
            // Ambil data siswa yang dipilih
            $selected_siswa_data = $siswa_model->getSiswaByIds($selected_siswa_ids);
            
            foreach ($selected_siswa_data as $siswa) {
                $no_telp_ortu = $siswa['no_telp_ortu'] ?? null;
                $siswa_id = $siswa['id'];
                
                if ($no_telp_ortu) {
                    $absensi_mingguan = $absensi_harian_model->getAbsensiHarianBySiswaId($siswa_id, $startDate, $endDate);

                    if (!empty($absensi_mingguan)) {
                        $message_lines = ["Rekapitulasi Absensi Mingguan", "Nama Siswa: " . $siswa['nama_lengkap'], "Periode: " . $startDate . " s/d " . $endDate, "---"];
                        foreach ($absensi_mingguan as $record) {
                            $date = new DateTime($record['tanggal']);
                            $day_name_en = $date->format('l');
                            $month_name_en = $date->format('F');
                            
                            $day_name_id = $days_in_id[$day_name_en];
                            $month_name_id = $months_in_id[$month_name_en];
                            $formatted_date_id = $day_name_id . ', ' . $date->format('d') . ' ' . $month_name_id . ' ' . $date->format('Y');
                        
                            $status = $record['status_masuk'];
                            $message_lines[] = "Hari: " . $formatted_date_id;
                            $message_lines[] = "Status: " . $status;
                            $message_lines[] = "---";
                        }
                        $message_content = implode("\n", $message_lines);


                        $phone_number_clean = ltrim($no_telp_ortu, '+');
                        $template_name = 'absensi_mingguan_rekap';
                        $template_params = [$siswa['nama_lengkap'], $message_content];

                        $notification_queue_model->addNotificationToQueue($phone_number_clean, $template_name, $template_params);
                        $count_sent++;
                    }
                }
            }
            set_flash('success', "Berhasil menambahkan $count_sent notifikasi rekapitulasi ke antrean.");
        } catch (Exception $e) {
            set_flash('error', 'Terjadi kesalahan saat memproses permintaan: ' . $e->getMessage());
        }
    }
    redirect(ROOT_URL . 'admin/rekap_absensi.php?kelas_id=' . urlencode($selected_kelas_id) . '&start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate));
}

$page_title = "Rekapitulasi Absensi Siswa";
include __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold mb-6 text-gray-800">Rekapitulasi Absensi Siswa</h1>

    <?php if ($success_message): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
            <p><?php echo htmlspecialchars($success_message); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-semibold mb-4">Pilih Kelas dan Periode</h2>
        <form action="" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <input type="hidden" name="action" value="view_report">
            <div>
                <label for="kelas_id" class="block text-gray-700 text-sm font-bold mb-2">Pilih Kelas:</label>
                <select id="kelas_id" name="kelas_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <option value="">-- Pilih Kelas --</option>
                    <?php foreach ($all_kelas as $kelas): ?>
                        <option value="<?php echo htmlspecialchars($kelas['id']); ?>" <?php echo ($selected_kelas_id == $kelas['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="start_date" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Mulai:</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div>
                <label for="end_date" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Akhir:</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div>
                <button type="submit" class="w-full bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                    Tampilkan Rekapitulasi
                </button>
            </div>
        </form>
    </div>

    <?php if (!empty($siswa_absensi_data)): ?>
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-xl font-semibold mb-4">Pratinjau Rekapitulasi Absensi</h2>
            
            <form action="" method="POST">
                <input type="hidden" name="action" value="send_weekly_summary">
                <input type="hidden" name="kelas_id" value="<?php echo htmlspecialchars($selected_kelas_id); ?>">
                <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
                <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
                
                <div class="overflow-x-auto">
                    <table class="min-w-full leading-normal">
                        <thead>
                            <tr>
                                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    <input type="checkbox" id="select_all" class="mr-2">Pilih
                                </th>
                                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Nama Siswa
                                </th>
                                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Rekap Absensi
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($siswa_absensi_data as $data): ?>
                                <tr>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        <input type="checkbox" name="selected_siswa[]" value="<?php echo htmlspecialchars($data['siswa']['id']); ?>">
                                    </td>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        <p class="text-gray-900 whitespace-no-wrap"><?php echo htmlspecialchars($data['siswa']['nama_lengkap']); ?></p>
                                    </td>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        <?php if (empty($data['absensi'])): ?>
                                            <p class="text-gray-500 italic">Tidak ada catatan absensi dalam periode ini.</p>
                                        <?php else: ?>
                                            <ul class="list-disc list-inside">
                                                <?php foreach ($data['absensi'] as $absensi_record): ?>
                                                    <li>
                                                        <?php
                                                            $date = new DateTime($absensi_record['tanggal']);
                                                            $day_name_en = $date->format('l'); // 'l' untuk nama hari dalam bahasa Inggris
                                                            $month_name_en = $date->format('F'); // 'F' untuk nama bulan dalam bahasa Inggris
                                                            
                                                            $day_name_id = $days_in_id[$day_name_en];
                                                            $month_name_id = $months_in_id[$month_name_en];
                                                            $formatted_date_id = $day_name_id . ', ' . $date->format('d') . ' ' . $month_name_id . ' ' . $date->format('Y');
                                                        ?>
                                                        <span class="font-bold"><?php echo htmlspecialchars($formatted_date_id); ?>:</span>
                                                        <?php echo htmlspecialchars($absensi_record['status_masuk']); ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 text-right">
                    <button type="submit" class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                        Kirim Rekapitulasi ke WhatsApp
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
    document.getElementById('select_all').addEventListener('change', function() {
        let checkboxes = document.querySelectorAll('input[name="selected_siswa[]"]');
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = this.checked;
        }.bind(this));
    });
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>