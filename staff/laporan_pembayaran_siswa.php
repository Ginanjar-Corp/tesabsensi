<?php
// project-root/admin/laporan_pembayaran_siswa.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/TahunAjaran.php';
require_once __DIR__ . '/../classes/Kelas.php';

require_login();
require_role(['Admin', 'TataUsaha', 'Guru']); // Admin, Tata Usaha, dan Guru bisa akses

$error_message = flash('error');
$success_message = flash('success');

global $pdo;
$siswa_model = new Siswa($pdo);
$tahun_ajaran_model = new TahunAjaran($pdo);
$kelas_model = new Kelas($pdo);

$all_siswa = [];
$all_tahun_ajaran = $tahun_ajaran_model->getAllTahunAjaran();
$all_kelas = $kelas_model->getAll();

$selected_tahun_ajaran_id = filter_input(INPUT_GET, 'tahun_ajaran_id', FILTER_VALIDATE_INT);
$selected_kelas_id = filter_input(INPUT_GET, 'kelas_id', FILTER_VALIDATE_INT);

// Ambil tahun ajaran aktif jika tidak ada yang dipilih
if (!$selected_tahun_ajaran_id) {
    $tahun_ajaran_aktif = $tahun_ajaran_model->getActiveTahunAjaran();
    if ($tahun_ajaran_aktif) {
        $selected_tahun_ajaran_id = $tahun_ajaran_aktif['id'];
    }
}

// Filter siswa berdasarkan tahun ajaran dan kelas jika ada
if ($selected_tahun_ajaran_id) {
    // Kita perlu mendapatkan siswa yang terdaftar di kelas untuk tahun ajaran tertentu
    // Untuk tujuan laporan, kita bisa mengambil semua siswa aktif dan menampilkan kelas terakhir mereka.
    // Atau, jika ada tabel `siswa_kelas_tahun_ajaran`, kita bisa join ke sana.
    // Untuk simplisitas, kita ambil semua siswa aktif dan filter by kelas yang dipilih.
    $all_siswa = $siswa_model->getAllStudentsByKelas($selected_kelas_id);
} else {
    $all_siswa = $siswa_model->getAll(); // Ambil semua siswa jika tidak ada filter tahun ajaran/kelas
}

// Sertakan header
require_once __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Laporan Pembayaran Siswa</h1>

    <?php if ($error_message): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Error!</strong>
            <span class="block sm:inline"><?php echo $error_message; ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Sukses!</strong>
            <span class="block sm:inline"><?php echo $success_message; ?></span>
        </div>
    <?php endif; ?>

    <div class="bg-white shadow-md rounded-lg p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-700 mb-4">Filter Laporan</h2>
        <form action="" method="GET" class="flex flex-wrap items-end gap-4">
            <div>
                <label for="tahun_ajaran_id" class="block text-sm font-medium text-gray-700">Tahun Ajaran:</label>
                <select id="tahun_ajaran_id" name="tahun_ajaran_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-purple-500 focus:border-purple-500 sm:text-sm rounded-md shadow-sm">
                    <?php foreach ($all_tahun_ajaran as $ta): ?>
                        <option value="<?php echo htmlspecialchars($ta['id']); ?>"
                            <?php echo ($selected_tahun_ajaran_id == $ta['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ta['tahun_ajaran']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="kelas_id" class="block text-sm font-medium text-gray-700">Kelas:</label>
                <select id="kelas_id" name="kelas_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-purple-500 focus:border-purple-500 sm:text-sm rounded-md shadow-sm">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($all_kelas as $kelas): ?>
                        <option value="<?php echo htmlspecialchars($kelas['id']); ?>"
                            <?php echo ($selected_kelas_id == $kelas['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                <i class="fas fa-filter mr-2"></i> Filter
            </button>
        </form>
    </div>

    <div class="bg-white shadow-md rounded-lg p-6">
        <h2 class="text-xl font-semibold text-gray-700 mb-4">Daftar Siswa</h2>
        <?php if (empty($all_siswa)): ?>
            <p class="text-gray-600 text-center">Tidak ada siswa ditemukan berdasarkan filter yang dipilih.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                NISN
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Nama Lengkap
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Kelas
                            </th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($all_siswa as $siswa): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($siswa['nisn']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($siswa['nama_lengkap']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($siswa['nama_kelas']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <a href="<?php echo ROOT_URL; ?>admin/generate_payment_report_pdf.php?siswa_id=<?php echo htmlspecialchars($siswa['id']); ?>&tahun_ajaran_id=<?php echo htmlspecialchars($selected_tahun_ajaran_id); ?>"
                                       target="_blank"
                                       class="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                       title="Unduh Laporan Pembayaran PDF">
                                        <i class="fas fa-file-pdf mr-2"></i> Unduh PDF
                                    </a>
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
