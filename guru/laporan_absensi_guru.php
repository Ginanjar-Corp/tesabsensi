<?php
// project-root/guru/laporan_absensi_guru.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Guru.php';
require_once __DIR__ . '/../classes/AbsensiHarianGuru.php'; // Include kelas baru

// Pastikan pengguna sudah login dan memiliki peran Guru
require_login();
require_role('Guru');

$error_message = flash('error');
$success_message = flash('success');

$guru_model = null;
$absensi_harian_guru_model = null;
$pdo = null;
$daily_attendance_records = [];
$guru_data = null;

// Filter tanggal
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default awal bulan ini
$end_date = $_GET['end_date'] ?? date('Y-m-d');   // Default hari ini

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $guru_model = new Guru($pdo);
    $absensi_harian_guru_model = new AbsensiHarianGuru($pdo);

    $user_id = $_SESSION['user_id'];
    $related_id = $_SESSION['related_id'] ?? null; // ID guru yang terkait dengan user

    if (!$related_id) {
        set_flash('error', 'Data guru tidak ditemukan untuk akun Anda. Silakan hubungi administrator.');
        redirect(ROOT_URL . 'logout.php');
    }

    $guru_data = $guru_model->findById($related_id);
    if (!$guru_data) {
        set_flash('error', 'Data guru tidak ditemukan. Silakan hubungi administrator.');
        redirect(ROOT_URL . 'logout.php');
    }

    // Ambil data absensi harian guru
    $daily_attendance_records = $absensi_harian_guru_model->getDailyAttendanceByGuru(
        $guru_data['id'],
        $start_date,
        $end_date
    );

} catch (PDOException $e) {
    error_log("Laporan Absensi Guru Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Laporan Absensi Guru General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
}

// Sertakan header guru
require_once __DIR__ . '/../components/header_guru.php';
?>

<div class="container mx-auto p-6 bg-white rounded-lg shadow-md">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Laporan Absensi Harian Saya</h1>

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

    <!-- Filter Form -->
    <form method="GET" action="" class="mb-6 bg-gray-50 p-4 rounded-lg shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
            <div>
                <label for="start_date" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Mulai:</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div>
                <label for="end_date" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Akhir:</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
        </div>
        <div class="mt-6 flex justify-end">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                Tampilkan Laporan
            </button>
        </div>
    </form>

    <!-- Tabel Laporan Absensi -->
    <?php if (!empty($daily_attendance_records)): ?>
        <div class="overflow-x-auto bg-white rounded-lg shadow overflow-y-auto relative" style="max-height: 500px;">
            <table class="min-w-full leading-normal">
                <thead>
                    <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                        <th class="py-3 px-6 text-left">Tanggal</th>
                        <th class="py-3 px-6 text-left">Waktu Masuk</th>
                        <th class="py-3 px-6 text-left">Waktu Pulang</th>
                        <th class="py-3 px-6 text-left">Durasi</th>
                        <th class="py-3 px-6 text-left">Status Kehadiran</th> <!-- Kolom baru -->
                    </tr>
                </thead>
                <tbody class="text-gray-600 text-sm font-light">
                    <?php foreach ($daily_attendance_records as $record): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars(format_tanggal_indo($record['tanggal'])); ?></td>
                            <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars(substr($record['waktu_masuk'], 0, 5) ?? '-'); ?></td>
                            <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars(substr($record['waktu_pulang'], 0, 5) ?? '-'); ?></td>
                            <td class="py-3 px-6 whitespace-nowrap">
                                <?php
                                    if ($record['waktu_masuk'] && $record['waktu_pulang']) {
                                        $masuk = new DateTime($record['tanggal'] . ' ' . $record['waktu_masuk']);
                                        $pulang = new DateTime($record['tanggal'] . ' ' . $record['waktu_pulang']);
                                        $interval = $masuk->diff($pulang);
                                        echo $interval->format('%h jam %i menit');
                                    } else {
                                        echo '-';
                                    }
                                ?>
                            </td>
                            <td class="py-3 px-6 whitespace-nowrap">
                                <?php
                                    // Tampilkan status dengan warna berbeda untuk visualisasi yang lebih baik
                                    $status_class = '';
                                    switch ($record['status_masuk']) {
                                        case 'Hadir':
                                            $status_class = 'text-green-600 font-semibold';
                                            break;
                                        case 'Sakit':
                                            $status_class = 'text-yellow-600 font-semibold';
                                            break;
                                        case 'Izin':
                                            $status_class = 'text-blue-600 font-semibold';
                                            break;
                                        case 'Alfa':
                                            $status_class = 'text-red-600 font-semibold';
                                            break;
                                        default:
                                            $status_class = 'text-gray-600';
                                            break;
                                    }
                                    echo '<span class="' . $status_class . '">' . htmlspecialchars($record['status_masuk'] ?? '-') . '</span>';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-gray-600 text-center py-4">Tidak ada data absensi harian yang ditemukan untuk periode ini.</p>
    <?php endif; ?>
</div>

<?php
// Sertakan footer
require_once __DIR__ . '/../components/footer.php';
?>
