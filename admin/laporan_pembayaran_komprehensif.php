<?php
// project-root/admin/laporan_pembayaran_komprehensif.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/TahunAjaran.php';
require_once __DIR__ . '/../classes/Kelas.php';
require_once __DIR__ . '/../classes/JenisPembayaran.php';
require_once __DIR__ . '/../classes/TagihanSiswa.php'; // Kelas TagihanSiswa yang diperbarui

require_login();
require_role(['Admin', 'TataUsaha', 'Guru']); // Admin, Tata Usaha, dan Guru bisa akses

$error_message = flash('error');
$success_message = flash('success');

global $pdo;
$siswa_model = new Siswa($pdo);
$tahun_ajaran_model = new TahunAjaran($pdo);
$kelas_model = new Kelas($pdo);
$jenis_pembayaran_model = new JenisPembayaran($pdo);
$tagihan_siswa_model = new TagihanSiswa($pdo); // Instance model

$all_tahun_ajaran = $tahun_ajaran_model->getAllTahunAjaran();
$all_kelas = $kelas_model->getAll();
$all_jenis_pembayaran = $jenis_pembayaran_model->getAll();

$filtered_payments = [];
$filters = [];
$jenis_pembayaran_terpilih = null;
$report_type = 'bebas'; // Default ke laporan bebas

// Ambil filter dari GET request
$selected_tahun_ajaran_id = filter_input(INPUT_GET, 'tahun_ajaran_id', FILTER_VALIDATE_INT);
$selected_kelas_id = filter_input(INPUT_GET, 'kelas_id', FILTER_VALIDATE_INT);
$selected_jenis_pembayaran_id = filter_input(INPUT_GET, 'jenis_pembayaran_id', FILTER_VALIDATE_INT);
// Filter tanggal tidak lagi diperlukan

// Set filter untuk dikirim ke model
if ($selected_tahun_ajaran_id) {
    $filters['tahun_ajaran_id'] = $selected_tahun_ajaran_id;
}
if ($selected_kelas_id) {
    $filters['kelas_id'] = $selected_kelas_id;
}

// Periksa tipe pembayaran yang dipilih
if ($selected_jenis_pembayaran_id) {
    $jenis_pembayaran_terpilih = $jenis_pembayaran_model->getById($selected_jenis_pembayaran_id);
    if ($jenis_pembayaran_terpilih) {
        $filters['jenis_pembayaran_id'] = $selected_jenis_pembayaran_id;
        if ($jenis_pembayaran_terpilih['tipe_pembayaran'] === 'Rutin Bulanan') {
            $report_type = 'bulanan';
        } else {
            $report_type = 'bebas';
        }
    }
} else {
    // Jika tidak ada jenis pembayaran yang dipilih, default ke laporan bebas
    $report_type = 'bebas';
}


// Default filter: hanya pembayaran yang Lunas (sudah berhasil) untuk laporan bebas
// Untuk laporan bulanan, kita akan menampilkan status berdasarkan sisa_tagihan (0 = lunas, >0 = belum lunas)
if ($report_type === 'bebas') {
    $filters['status_pembayaran_detail'] = 'Lunas';
}


// Hanya ambil data jika filter utama (tahun ajaran dan jenis pembayaran) telah dipilih
if (!empty($selected_tahun_ajaran_id) && !empty($selected_jenis_pembayaran_id)) {
    if ($report_type === 'bulanan') {
        $filtered_payments = $tagihan_siswa_model->getMonthlyPaymentsForReport($filters);
    } else {
        $filtered_payments = $tagihan_siswa_model->getPaymentsForReport($filters);
    }
}


// Daftar bulan untuk laporan bulanan (sesuai format Juli-Juni)
$months = [
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni'
];


// Sertakan header admin
require_once __DIR__ . '/../components/header_admin.php';
?>

<div class="min-h-screen bg-gray-100 p-8">
    <div class="max-w-7xl mx-auto bg-white p-6 rounded-lg shadow-md">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Laporan Pembayaran Siswa Komprehensif</h1>

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

        <!-- Filter Form -->
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 items-end">
            <div>
                <label for="tahun_ajaran_id" class="block text-sm font-medium text-gray-700">Tahun Ajaran</label>
                <select id="tahun_ajaran_id" name="tahun_ajaran_id"
                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                    <option value="">Pilih Tahun Ajaran</option>
                    <?php foreach ($all_tahun_ajaran as $ta): ?>
                        <option value="<?php echo htmlspecialchars($ta['id']); ?>"
                            <?php echo ($selected_tahun_ajaran_id == $ta['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ta['tahun_ajaran']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="kelas_id" class="block text-sm font-medium text-gray-700">Kelas</label>
                <select id="kelas_id" name="kelas_id"
                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($all_kelas as $kelas): ?>
                        <option value="<?php echo htmlspecialchars($kelas['id']); ?>"
                            <?php echo ($selected_kelas_id == $kelas['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="jenis_pembayaran_id" class="block text-sm font-medium text-gray-700">Jenis Pembayaran</label>
                <select id="jenis_pembayaran_id" name="jenis_pembayaran_id"
                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                    <option value="">Pilih Jenis Pembayaran</option>
                    <?php foreach ($all_jenis_pembayaran as $jp): ?>
                        <option value="<?php echo htmlspecialchars($jp['id']); ?>"
                            <?php echo ($selected_jenis_pembayaran_id == $jp['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($jp['nama_pembayaran']) . " (" . htmlspecialchars($jp['tipe_pembayaran']) . ")"; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-span-full flex justify-end space-x-2">
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-filter mr-2"></i> Tampilkan Laporan
                </button>
                <?php if (!empty($filtered_payments)): ?>
                    <a href="export_pembayaran_komprehensif_excel.php?<?php echo http_build_query($_GET); // Menggunakan $_GET agar semua filter ikut terkirim ?>"
                       class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <i class="fas fa-file-excel mr-2"></i> Unduh Excel
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (empty($selected_tahun_ajaran_id) || empty($selected_jenis_pembayaran_id)): ?>
            <p class="text-gray-600 text-center py-4">Silakan pilih **Tahun Ajaran** dan **Jenis Pembayaran** untuk menampilkan laporan.</p>
        <?php elseif (empty($filtered_payments)): ?>
            <p class="text-gray-600 text-center py-4">Tidak ada data pembayaran yang ditemukan dengan filter tersebut.</p>
        <?php else: ?>
            <div class="overflow-x-auto shadow-md sm:rounded-lg mt-8">
                <?php if ($report_type === 'bulanan'): ?>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    NISN
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Nama Siswa
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Kelas
                                </th>
                                <?php foreach ($months as $month): ?>
                                    <th scope="col" class="px-2 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <?php echo $month; ?><br><small>Jumlah/Tgl</small>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($filtered_payments as $siswa_data): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($siswa_data['nisn']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($siswa_data['nama_lengkap']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($siswa_data['nama_kelas']); ?>
                                    </td>
                                    <?php foreach ($months as $month): ?>
                                        <td class="px-2 py-4 whitespace-nowrap text-center text-sm">
                                            <?php
                                            if (isset($siswa_data['bulan'][$month]) && $siswa_data['bulan'][$month]['jumlah_dibayar'] > 0) {
                                                echo number_format($siswa_data['bulan'][$month]['jumlah_dibayar']) . '<br>';
                                                echo '<span class="text-xs text-gray-500">' . format_tanggal_indo($siswa_data['bulan'][$month]['tanggal_bayar_terakhir']) . '</span>';
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: // report_type === 'bebas' ?>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    NISN
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Nama Siswa
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Kelas
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Tahun Ajaran
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Jenis Pembayaran
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Periode Tagihan
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Jumlah Tagihan
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Jumlah Dibayar
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Sisa Tagihan
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Tanggal Bayar
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Metode Pembayaran
                                </th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($filtered_payments as $payment): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($payment['nisn']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($payment['nama_lengkap']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($payment['nama_kelas']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($payment['tahun_ajaran']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($payment['nama_pembayaran']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($payment['periode_tagihan']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">
                                        <?php echo number_format($payment['jumlah_tagihan']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-green-600 font-semibold">
                                        <?php echo number_format($payment['jumlah_bayar']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-red-600 font-semibold">
                                        <?php echo number_format($payment['jumlah_tagihan'] - $payment['jumlah_bayar']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo format_tanggal_indo($payment['tanggal_bayar']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($payment['metode_pembayaran']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($payment['petugas_pencatat'] ?? '-'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                            <?php
                                                if ($payment['status_pembayaran_detail'] == 'Lunas') echo 'bg-green-100 text-green-800';
                                                elseif ($payment['status_pembayaran_detail'] == 'Dibatalkan') echo 'bg-red-100 text-red-800';
                                                else echo 'bg-gray-100 text-gray-800';
                                            ?>">
                                            <?php echo htmlspecialchars($payment['status_pembayaran_detail']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Sertakan footer
require_once __DIR__ . '/../components/footer.php';
?>
