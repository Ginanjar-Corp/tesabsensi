<?php
// project-root/admin/generate_report_pdf.php

// Sertakan autoloader Dompdf
require_once __DIR__ . '/../vendor/dompdf/autoload.inc.php';

// Sertakan file konfigurasi dan kelas model yang diperlukan
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php'; // Pastikan format_tanggal_indo ada di sini
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/TahunAjaran.php';
require_once __DIR__ . '/../classes/Kelas.php';
require_once __DIR__ . '/../classes/HafalanSiswa.php'; // Model untuk laporan hafalan
require_once __DIR__ . '/../classes/SoroganSiswa.php'; // Model untuk laporan sorogan
require_once __DIR__ . '/../classes/PelanggaranSiswa.php'; // Model untuk laporan pelanggaran
require_once __DIR__ . '/../classes/IzinKeluarPulang.php'; // Model untuk laporan izin
require_once __DIR__ . '/../classes/NgajiSiswa.php'; // Model untuk laporan mengaji

use Dompdf\Dompdf;
use Dompdf\Options;

global $pdo;
$db = Database::getInstance();
$pdo = $db->getConnection();

$report_type = $_GET['report_type'] ?? null;
$siswa_id = filter_input(INPUT_GET, 'siswa_id', FILTER_VALIDATE_INT);
$kelas_id = filter_input(INPUT_GET, 'kelas_id', FILTER_VALIDATE_INT);
$tahun_ajaran_id = filter_input(INPUT_GET, 'tahun_ajaran_id', FILTER_VALIDATE_INT);

$siswa_model = new Siswa($pdo);
$siswa_data = $siswa_model->findById($siswa_id);
if (!$siswa_data) {
    die("Data siswa tidak ditemukan.");
}

$report_data = [];
$report_title = "Laporan Siswa";
$report_subtitle = "";

// Tanggal awal dan akhir tahun ajaran (jika diperlukan)
$start_date = null;
$end_date = null;
if ($tahun_ajaran_id) {
    $tahun_ajaran_model = new TahunAjaran($pdo);
    $tahun_ajaran_data = $tahun_ajaran_model->getTahunAjaranById($tahun_ajaran_id);
    if ($tahun_ajaran_data) {
        $start_date = $tahun_ajaran_data['tanggal_mulai'];
        $end_date = $tahun_ajaran_data['tanggal_selesai'];
        $report_subtitle = "Tahun Ajaran: " . htmlspecialchars($tahun_ajaran_data['tahun_ajaran']);
    }
}

switch ($report_type) {
    case 'hafalan':
        $hafalan_model = new HafalanSiswa($pdo);
        // Pass tahun_ajaran_id to the getForReport method
        $report_data = $hafalan_model->getForReport($siswa_id, $kelas_id, $start_date, $end_date, $tahun_ajaran_id);
        $report_title = "Laporan Hafalan Siswa";
        break;

    case 'sorogan':
        $sorogan_model = new SoroganSiswa($pdo);
        // Pass tahun_ajaran_id to the getForReport method
        $report_data = $sorogan_model->getForReport($siswa_id, $kelas_id, $start_date, $end_date, $tahun_ajaran_id);
        $report_title = "Laporan Sorogan Siswa";
        break;

    case 'mengaji':
        $ngaji_model = new NgajiSiswa($pdo);
        // Pass tahun_ajaran_id to the getReportData method
        $report_data = $ngaji_model->getReportData($siswa_id, $start_date, $end_date, $tahun_ajaran_id);
        $report_title = "Laporan Mengaji Siswa";
        break;

    case 'pelanggaran':
        $pelanggaran_model = new PelanggaranSiswa($pdo);
        // Pass tahun_ajaran_id to the getForReport method
        $report_data = $pelanggaran_model->getForReport($siswa_id, $kelas_id, $start_date, $end_date, $tahun_ajaran_id);
        $report_title = "Laporan Pelanggaran Siswa";
        break;

    case 'izin':
        $izin_model = new IzinKeluarPulang($pdo);
        // Pass tahun_ajaran_id to the getForReport method
        $report_data = $izin_model->getForReport($siswa_id, $kelas_id, $start_date, $end_date, $tahun_ajaran_id);
        $report_title = "Laporan Izin Keluar/Pulang Siswa";
        break;

    default:
        die("Tipe laporan tidak valid.");
}

// Inisialisasi Dompdf
$options = new Options();
$options->set('defaultFont', 'Inter'); // Pastikan font Inter tersedia atau gunakan font lain yang kompatibel
$dompdf = new Dompdf($options);

// Mulai output buffering
ob_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($report_title); ?></title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            font-size: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header img {
            max-width: 80px;
            margin-bottom: 10px;
        }
        .header h1 {
            font-size: 18px;
            margin: 0;
        }
        .header h2 {
            font-size: 14px;
            margin: 0;
        }
        .siswa-info {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .siswa-info td {
            padding: 5px;
            vertical-align: top;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo htmlspecialchars($report_title); ?></h1>
        <p><?php echo htmlspecialchars($report_subtitle); ?></p>
        <p><strong>Nama:</strong> <?php echo htmlspecialchars($siswa_data['nama_lengkap']); ?> (NISN: <?php echo htmlspecialchars($siswa_data['nisn']); ?>)
           <br><strong>Kelas:</strong> <?php echo htmlspecialchars($siswa_data['nama_kelas']); ?></p>
    </div>

    <div class="report-content">
        <?php
        switch ($report_type) {
            case 'hafalan':
                ?>
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal Setor</th>
                            <th>Kitab/Nadhom</th>
                            <th>Bagian Hafalan</th>
                            <th>Nilai</th>
                            <th>Guru</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($report_data)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">Tidak ada data hafalan.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($report_data as $data): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(format_tanggal_indo($data['tanggal_setor'])); ?></td>
                                    <td><?php echo htmlspecialchars($data['nama_kitab']); ?></td>
                                    <td><?php echo htmlspecialchars($data['bagian_hafalan']); ?></td>
                                    <td><?php echo htmlspecialchars($data['nilai']); ?></td>
                                    <td><?php echo htmlspecialchars($data['nama_guru']); ?></td>
                                    <td><?php echo htmlspecialchars($data['catatan']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php
                break;

            case 'sorogan':
                ?>
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal Setor</th>
                            <th>Kitab/Nadhom</th>
                            <th>Halaman</th>
                            <th>Status Bacaan</th>
                            <th>Nilai</th>
                            <th>Guru</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($report_data)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">Tidak ada data sorogan.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($report_data as $data): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(format_tanggal_indo($data['tanggal_setor'])); ?></td>
                                    <td><?php echo htmlspecialchars($data['nama_kitab']); ?></td>
                                    <td><?php echo htmlspecialchars($data['halaman_dari'] . '-' . $data['halaman_sampai']); ?></td>
                                    <td><?php echo htmlspecialchars($data['status_bacaan']); ?></td>
                                    <td><?php echo htmlspecialchars($data['nilai']); ?></td>
                                    <td><?php echo htmlspecialchars($data['nama_guru']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php
                break;

            case 'mengaji':
                ?>
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal Mengaji</th>
                            <th>Jenis Materi</th>
                            <th>Halaman</th>
                            <th>Catatan</th>
                            <th>Guru</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($report_data)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">Tidak ada data mengaji.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($report_data as $data): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(format_tanggal_indo($data['tanggal_setor'])); ?></td>
                                    <td><?php echo htmlspecialchars($data['jenis_materi']); ?></td>
                                    <td><?php echo htmlspecialchars($data['halaman_dari'] . '-' . $data['halaman_sampai']); ?></td>
                                    <td><?php echo htmlspecialchars($data['catatan_guru']); ?></td>
                                    <td><?php echo htmlspecialchars($data['nama_guru']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php
                break;

            case 'pelanggaran':
                ?>
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Jenis Pelanggaran</th>
                            <th>Poin Deduksi</th>
                            <th>Petugas</th>
                            <th>Deskripsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($report_data)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">Tidak ada data pelanggaran.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($report_data as $data): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(format_tanggal_indo($data['tanggal_pelanggaran'])); ?></td>
                                    <td><?php echo htmlspecialchars($data['nama_pelanggaran']); ?></td>
                                    <td><?php echo htmlspecialchars($data['poin_deduksi_aktual']); ?></td>
                                    <td><?php echo htmlspecialchars($data['nama_petugas']); ?></td>
                                    <td><?php echo htmlspecialchars($data['deskripsi_kejadian']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php
                break;

            case 'izin':
                ?>
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal Izin</th>
                            <th>Waktu Keluar</th>
                            <th>Waktu Kembali</th>
                            <th>Keperluan</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($report_data)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">Tidak ada data izin.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($report_data as $data): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(format_tanggal_indo($data['tanggal_izin'])); ?></td>
                                    <td><?php echo htmlspecialchars(date('H:i', strtotime($data['waktu_keluar']))); ?></td>
                                    <td><?php echo htmlspecialchars(date('H:i', strtotime($data['waktu_kembali']))); ?></td>
                                    <td><?php echo htmlspecialchars($data['keperluan']); ?></td>
                                    <td><?php echo htmlspecialchars($data['status_izin']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php
                break;

            default:
                echo '<p class="text-center" style="color: red; font-weight: bold;">Tipe laporan tidak valid.</p>';
                break;
        }
        ?>
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->get_font("Inter", "normal");
            $pdf->page_text(540, 780, "Halaman {PAGE_NUM} dari {PAGE_COUNT}", $font, 8, array(0,0,0));
            $pdf->page_text(60, 780, "Dicetak pada: <?php echo date('d-m-Y H:i:s'); ?>", $font, 8, array(0,0,0));
        }
    </script>
</body>
</html>
<?php
$html = ob_get_clean();

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream($report_title . "_Siswa_" . $siswa_data['nisn'] . ".pdf", array("Attachment" => 0));
exit();
?>
