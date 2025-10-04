<?php
// project-root/admin/laporan_siswa.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/TahunAjaran.php';
require_once __DIR__ . '/../classes/Kelas.php';

require_login();
require_role(['Admin', 'TataUsaha', 'Guru']);

$error_message = flash('error');
$success_message = flash('success');

$siswa_model = null;
$tahun_ajaran_model = null;
$kelas_model = null;
$pdo = null;

$all_siswa = [];
$all_tahun_ajaran = [];
$all_kelas = [];

$selected_tahun_ajaran_id = filter_input(INPUT_GET, 'tahun_ajaran_id', FILTER_VALIDATE_INT);
$selected_kelas_id = filter_input(INPUT_GET, 'kelas_id', FILTER_VALIDATE_INT);

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $siswa_model = new Siswa($pdo);
    $tahun_ajaran_model = new TahunAjaran($pdo);
    $kelas_model = new Kelas($pdo);

    $all_tahun_ajaran = $tahun_ajaran_model->getAllTahunAjaran();
    $all_kelas = $kelas_model->getAll();
    $all_siswa = $siswa_model->getAllStudentsByKelas($selected_kelas_id);

} catch (Exception $e) {
    error_log("Error in laporan_siswa: " . $e->getMessage());
    $error_message = 'Terjadi kesalahan: ' . $e->getMessage();
}

require_once __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Laporan Siswa</h1>

    <?php if ($error_message): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
        </div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-semibold mb-4">Filter Laporan</h2>
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="tahun_ajaran_id" class="block text-sm font-medium text-gray-700">Tahun Ajaran</label>
                <select id="tahun_ajaran_id" name="tahun_ajaran_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                    <option value="">-- Pilih Tahun Ajaran --</option>
                    <?php foreach ($all_tahun_ajaran as $ta): ?>
                        <option value="<?php echo htmlspecialchars($ta['id']); ?>" <?php echo ($selected_tahun_ajaran_id == $ta['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ta['tahun_ajaran']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="kelas_id" class="block text-sm font-medium text-gray-700">Kelas</label>
                <select id="kelas_id" name="kelas_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                    <option value="">-- Semua Kelas --</option>
                    <?php foreach ($all_kelas as $kelas): ?>
                        <option value="<?php echo htmlspecialchars($kelas['id']); ?>" <?php echo ($selected_kelas_id == $kelas['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Terapkan Filter
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold mb-4">Daftar Siswa</h2>
        <?php if (empty($all_siswa)): ?>
            <p class="text-gray-600">Tidak ada data siswa ditemukan.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto">
                    <thead>
                        <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">NISN</th>
                            <th class="py-3 px-6 text-left">Nama</th>
                            <th class="py-3 px-6 text-left">Kelas</th>
                            <th class="py-3 px-6 text-center">Aksi Laporan</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm">
                        <?php foreach ($all_siswa as $siswa): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars($siswa['nisn']); ?></td>
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars($siswa['nama_kelas']); ?></td>
                                <td class="py-3 px-6 text-center whitespace-nowrap">
                                    <a href="<?php echo ROOT_URL; ?>admin/generate_report_pdf.php?report_type=hafalan&siswa_id=<?php echo htmlspecialchars($siswa['id']); ?>&tahun_ajaran_id=<?php echo htmlspecialchars($selected_tahun_ajaran_id); ?>&kelas_id=<?php echo htmlspecialchars($selected_kelas_id); ?>"
                                       target="_blank"
                                       class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                                       title="Unduh Laporan Hafalan PDF">
                                        <i class="fas fa-file-pdf mr-1"></i> Hafalan
                                    </a>
                                    <a href="<?php echo ROOT_URL; ?>admin/generate_report_pdf.php?report_type=sorogan&siswa_id=<?php echo htmlspecialchars($siswa['id']); ?>&tahun_ajaran_id=<?php echo htmlspecialchars($selected_tahun_ajaran_id); ?>&kelas_id=<?php echo htmlspecialchars($selected_kelas_id); ?>"
                                       target="_blank"
                                       class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                       title="Unduh Laporan Sorogan PDF">
                                        <i class="fas fa-file-pdf mr-1"></i> Sorogan
                                    </a>
                                    <a href="<?php echo ROOT_URL; ?>admin/generate_report_pdf.php?report_type=mengaji&siswa_id=<?php echo htmlspecialchars($siswa['id']); ?>&tahun_ajaran_id=<?php echo htmlspecialchars($selected_tahun_ajaran_id); ?>&kelas_id=<?php echo htmlspecialchars($selected_kelas_id); ?>"
                                       target="_blank"
                                       class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500"
                                       title="Unduh Laporan Mengaji PDF">
                                        <i class="fas fa-file-pdf mr-1"></i> Mengaji
                                    </a>
                                    <a href="<?php echo ROOT_URL; ?>admin/generate_report_pdf.php?report_type=izin&siswa_id=<?php echo htmlspecialchars($siswa['id']); ?>&tahun_ajaran_id=<?php echo htmlspecialchars($selected_tahun_ajaran_id); ?>&kelas_id=<?php echo htmlspecialchars($selected_kelas_id); ?>"
                                       target="_blank"
                                       class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500"
                                       title="Unduh Laporan Izin Keluar/Pulang PDF">
                                        <i class="fas fa-file-pdf mr-1"></i> Izin
                                    </a>
                                    <a href="<?php echo ROOT_URL; ?>admin/generate_report_pdf.php?report_type=pelanggaran&siswa_id=<?php echo htmlspecialchars($siswa['id']); ?>&tahun_ajaran_id=<?php echo htmlspecialchars($selected_tahun_ajaran_id); ?>&kelas_id=<?php echo htmlspecialchars($selected_kelas_id); ?>"
                                       target="_blank"
                                       class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                       title="Unduh Laporan Pelanggaran PDF">
                                        <i class="fas fa-file-pdf mr-1"></i> Pelanggaran
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
require_once __DIR__ . '/../components/footer.php';
?>
