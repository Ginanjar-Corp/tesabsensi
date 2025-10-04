<?php
// project-root/guru/jadwal_saya.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/JadwalPelajaran.php';
require_once __DIR__ . '/../classes/Guru.php';

// Pastikan pengguna sudah login dan memiliki peran Guru
require_login();
require_role('Guru');

$error_message = flash('error');
$success_message = flash('success');

$jadwal_model = null;
$pdo = null;
$guru_id = $_SESSION['related_id'];
$teacher_schedules = [];

// Variabel untuk tahun ajaran dan semester aktif
$current_tahun_ajaran = null;
$current_semester = null;
$all_tahun_ajaran = [];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $jadwal_model = new JadwalPelajaran($pdo);

    $active_tahun_ajaran_data = getActiveTahunAjaran();
    if ($active_tahun_ajaran_data) {
        $current_tahun_ajaran = $active_tahun_ajaran_data['tahun_ajaran'];
        $current_semester = APP_SEMESTER_AKTIF;
    } else {
        set_flash('error', 'Tidak ada Tahun Ajaran aktif yang disetel. Harap hubungi administrator.');
    }

    $all_tahun_ajaran = getAllTahunAjaran();

    $filter_tahun_ajaran = $_GET['tahun_ajaran'] ?? $current_tahun_ajaran;
    $filter_semester = $_GET['semester'] ?? $current_semester;
    $filter_hari = $_GET['hari'] ?? null;

    if ($filter_tahun_ajaran && $filter_semester) {
        $teacher_schedules = $jadwal_model->getTeacherSchedule(
            $guru_id,
            $filter_hari,
            $filter_tahun_ajaran,
            $filter_semester
        );
    } else {
        $teacher_schedules = [];
    }

} catch (PDOException $e) {
    error_log("Guru Jadwal Saya Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Guru Jadwal Saya General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
}

require_once __DIR__ . '/../components/header_guru.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Jadwal Mengajar Saya</h1>

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
        <h2 class="text-xl font-semibold mb-4">Filter Jadwal</h2>
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label for="tahun_ajaran" class="block text-gray-700 text-sm font-bold mb-2">Tahun Ajaran:</label>
                <select id="tahun_ajaran" name="tahun_ajaran" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua</option>
                    <?php foreach ($all_tahun_ajaran as $ta): ?>
                        <option value="<?php echo htmlspecialchars($ta['tahun_ajaran']); ?>"
                                <?php echo ($filter_tahun_ajaran == $ta['tahun_ajaran']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ta['tahun_ajaran']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="semester" class="block text-gray-700 text-sm font-bold mb-2">Semester:</label>
                <select id="semester" name="semester" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua</option>
                    <option value="Ganjil" <?php echo ($filter_semester == 'Ganjil') ? 'selected' : ''; ?>>Ganjil</option>
                    <option value="Genap" <?php echo ($filter_semester == 'Genap') ? 'selected' : ''; ?>>Genap</option>
                </select>
            </div>
            <div>
                <label for="hari" class="block text-gray-700 text-sm font-bold mb-2">Hari:</label>
                <select id="hari" name="hari" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua</option>
                    <option value="Senin" <?php echo ($filter_hari == 'Senin') ? 'selected' : ''; ?>>Senin</option>
                    <option value="Selasa" <?php echo ($filter_hari == 'Selasa') ? 'selected' : ''; ?>>Selasa</option>
                    <option value="Rabu" <?php echo ($filter_hari == 'Rabu') ? 'selected' : ''; ?>>Rabu</option>
                    <option value="Kamis" <?php echo ($filter_hari == 'Kamis') ? 'selected' : ''; ?>>Kamis</option>
                    <option value="Jumat" <?php echo ($filter_hari == 'Jumat') ? 'selected' : ''; ?>>Jumat</option>
                    <option value="Sabtu" <?php echo ($filter_hari == 'Sabtu') ? 'selected' : ''; ?>>Sabtu</option>
                    <option value="Minggu" <?php echo ($filter_hari == 'Minggu') ? 'selected' : ''; ?>>Minggu</option>
                </select>
            </div>
            <div class="flex justify-start items-end">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full md:w-auto">
                    Tampilkan Jadwal
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold mb-4">Jadwal Mengajar</h2>
        <?php if (empty($teacher_schedules)): ?>
            <p class="text-gray-600">Tidak ada jadwal mengajar yang ditemukan untuk filter ini.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                    <thead>
                        <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">Hari</th>
                            <th class="py-3 px-6 text-left">Waktu</th>
                            <th class="py-3 px-6 text-left">Tipe Jadwal</th> <!-- Kolom baru -->
                            <th class="py-3 px-6 text-left">Mata Pelajaran</th>
                            <th class="py-3 px-6 text-left">Kelas</th>
                            <th class="py-3 px-6 text-left">Ruangan</th>
                            <th class="py-3 px-6 text-left">Tahun Ajaran</th>
                            <th class="py-3 px-6 text-left">Semester</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm">
                        <?php foreach ($teacher_schedules as $schedule): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars($schedule['hari']); ?></td>
                                <td class="py-3 px-6"><?php echo substr(htmlspecialchars($schedule['jam_mulai']), 0, 5) . ' - ' . substr(htmlspecialchars($schedule['jam_selesai']), 0, 5); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($schedule['tipe_jadwal']); ?></td> <!-- Menampilkan data tipe_jadwal -->
                                <td class="py-3 px-6"><?php echo htmlspecialchars($schedule['nama_mapel']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($schedule['nama_kelas']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($schedule['ruangan'] ?? '-'); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($schedule['tahun_ajaran']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($schedule['semester']); ?></td>
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
