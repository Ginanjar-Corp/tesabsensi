<?php
// project-root/admin/generate_payment_report_pdf.php

// Pastikan untuk menyertakan autoload Composer jika menggunakan Dompdf
// Jika Dompdf Anda hasil unduhan manual, gunakan:
require_once __DIR__ . '/../vendor/dompdf/autoload.inc.php'; // SESUAIKAN JALUR INI JIKA BERBEDA

// Sertakan konfigurasi dan helper yang diperlukan
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php'; // Ini seharusnya sudah mengandung tanggal_indo()
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php'; // Mungkin tidak diperlukan jika $pdo global
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/TagihanSiswa.php';
require_once __DIR__ . '/../classes/TahunAjaran.php';
require_once __DIR__ . '/../classes/Kelas.php'; // Digunakan untuk mendapatkan nama kelas siswa
require_once __DIR__ . '/../classes/SchoolSettings.php'; // Untuk mengambil info sekolah

// Menggunakan namespace Dompdf
use Dompdf\Dompdf;
use Dompdf\Options;

// Pastikan pengguna sudah login dan memiliki peran yang sesuai
require_login();
require_role(['Admin', 'TataUsaha', 'Guru']); // Admin, Tata Usaha, dan Guru bisa akses

// --- Fallback function for tanggal_indo() if not already defined ---
// Ideally, this function should be in includes/helpers.php
if (!function_exists('tanggal_indo')) {
    function tanggal_indo($tanggal, $cetak_hari = false)
    {
        $hari = array ( 1 =>    'Senin',
                    'Selasa',
                    'Rabu',
                    'Kamis',
                    'Jumat',
                    'Sabtu',
                    'Minggu'
                );
                
        $bulan = array (1 =>   'Januari',
                    'Februari',
                    'Maret',
                    'April',
                    'Mei',
                    'Juni',
                    'Juli',
                    'Agustus',
                    'September',
                    'Oktober',
                    'November',
                    'Desember'
                );
        $split    = explode('-', $tanggal);
        $tgl_indo = $split[2] . ' ' . $bulan[ (int)$split[1] ] . ' ' . $split[0];
        
        if ($cetak_hari) {
            $num = date('N', strtotime($tanggal));
            return $hari[$num] . ', ' . $tgl_indo;
        }
        return $tgl_indo;
    }
}
// --- End of Fallback function ---

// --- FUNGSI BARU UNTUK FORMAT MATA UANG (HAPUS ,00) ---
if (!function_exists('format_rupiah_without_zero_decimals')) {
    function format_rupiah_without_zero_decimals($amount) {
        // Format dengan 2 desimal terlebih dahulu
        $formatted = number_format($amount, 2, ',', '.');
        // Jika diakhiri dengan ',00', hapus bagian tersebut
        if (substr($formatted, -3) === ',00') {
            $formatted = substr($formatted, 0, -3);
        }
        return $formatted;
    }
}
// --- AKHIR FUNGSI BARU ---


// Inisialisasi koneksi database dan model
global $pdo;
$siswa_model = new Siswa($pdo);
$tagihan_siswa_model = new TagihanSiswa($pdo);
$tahun_ajaran_model = new TahunAjaran($pdo);
$kelas_model = new Kelas($pdo);
$school_settings_model = new SchoolSettings($pdo);

// Ambil pengaturan sekolah
$settings = $school_settings_model->getSettings();
$nama_sekolah = $settings['nama_sekolah'] ?? APP_NAME;
$alamat_sekolah = $settings['alamat_sekolah'] ?? '';
$kepala_sekolah = $settings['kepala_sekolah'] ?? '[Nama Kepala Sekolah]';
$nip_kepala_sekolah = $settings['nip_kepala_sekolah'] ?? '[NIP Kepala Sekolah]';

// Ambil parameter dari URL
$siswa_id = filter_input(INPUT_GET, 'siswa_id', FILTER_VALIDATE_INT);
$tahun_ajaran_id = filter_input(INPUT_GET, 'tahun_ajaran_id', FILTER_VALIDATE_INT);

if (!$siswa_id || !$tahun_ajaran_id) {
    // Redirect atau tampilkan error jika parameter tidak valid
    set_flash_message('error', 'Parameter siswa ID atau tahun ajaran ID tidak valid.');
    header('Location: ' . ROOT_URL . 'admin/laporan_pembayaran_siswa.php');
    exit;
}

// Ambil data siswa
$siswa = $siswa_model->findById($siswa_id);
if (!$siswa) {
    set_flash_message('error', 'Data siswa tidak ditemukan.');
    header('Location: ' . ROOT_URL . 'admin/laporan_pembayaran_siswa.php');
    exit;
}

// Ambil data tahun ajaran
$tahun_ajaran = $tahun_ajaran_model->getTahunAjaranById($tahun_ajaran_id);
if (!$tahun_ajaran) {
    set_flash_message('error', 'Data tahun ajaran tidak ditemukan.');
    header('Location: ' . ROOT_URL . 'admin/laporan_pembayaran_siswa.php');
    exit;
}

// Ambil semua tagihan untuk siswa dan tahun ajaran ini
$all_tagihan_siswa = $tagihan_siswa_model->getAllTagihanBySiswaAndTahunAjaran($siswa_id, $tahun_ajaran_id);

// Siapkan data untuk laporan
$total_tagihan_keseluruhan = 0;
$total_dibayar_keseluruhan = 0;
$total_sisa_tagihan_keseluruhan = 0;

foreach ($all_tagihan_siswa as $tagihan) {
    $total_tagihan_keseluruhan += $tagihan['jumlah_tagihan'];
    $total_dibayar_keseluruhan += $tagihan['jumlah_dibayar'];
    // Sisa tagihan sudah dihitung di query, bisa langsung digunakan
    $total_sisa_tagihan_keseluruhan += $tagihan['sisa_tagihan'];
}

// Mulai buffer output untuk menangkap HTML
ob_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Pembayaran Siswa - <?php echo htmlspecialchars($siswa['nama_lengkap']); ?></title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            font-size: 12px;
            position: relative;
        }
        .container {
            width: 90%;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header img {
            max-height: 80px;
            margin-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            color: #333;
        }
        .header h2 {
            margin: 0;
            font-size: 16px;
            color: #555;
        }
        .header p {
            margin: 2px 0;
            color: #777;
        }
        /* Penyesuaian lebar th di informasi siswa untuk tata letak berjejer */
        .student-info th {
            width: 120px; /* Lebar default untuk label */
            color: #555;
        }
        .student-info td {
            width: auto; /* Biarkan konten menyesuaikan lebar */
        }
        /* Menyesuaikan lebar untuk kolom kedua di baris yang sama */
        .student-info th:nth-child(3) { /* Untuk 'NIS' dan 'Kelas' */
            width: 80px; /* Mungkin lebih kecil */
        }

        .section-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .footer {
            position: fixed;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 10px;
            color: #888;
        }
        .signature-block {
            margin-top: 50px;
            float: right;
            width: 300px;
            text-align: center;
        }
        .signature-block .name {
            font-weight: bold;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
            margin-top: 80px; /* Space for signature */
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>LAPORAN PEMBAYARAN SISWA</h1>
            <h2><?php echo htmlspecialchars($nama_sekolah); ?></h2>
            <p><?php echo htmlspecialchars($alamat_sekolah); ?></p>
            <p>Tahun Ajaran: <?php echo htmlspecialchars($tahun_ajaran['tahun_ajaran']); ?></p>
        </div>

        <div class="section-title">Informasi Siswa</div>
        <table class="student-info">
            <tr>
                <th>NISN</th>
                <td><?php echo htmlspecialchars($siswa['nisn']); ?></td>
                <th>NIS</th>
                <td><?php echo htmlspecialchars($siswa['nis']); ?></td>
            </tr>
            <tr>
                <th>Nama Lengkap</th>
                <td><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
                <th>Kelas</th>
                <td><?php echo htmlspecialchars($siswa['nama_kelas']); ?></td>
            </tr>
        </table>

        <div class="section-title">Rincian Tagihan</div>
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Jenis Pembayaran</th>
                    <th>Periode Tagihan</th>
                    <th class="text-right">Jumlah Tagihan</th>
                    <th class="text-right">Jumlah Dibayar</th>
                    <th class="text-right">Sisa Tagihan</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; ?>
                <?php foreach ($all_tagihan_siswa as $tagihan): ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo htmlspecialchars($tagihan['nama_pembayaran']); ?></td>
                        <td><?php echo htmlspecialchars($tagihan['periode_tagihan']); ?></td>
                        <!-- Gunakan fungsi format_rupiah_without_zero_decimals -->
                        <td class="text-right">Rp <?php echo format_rupiah_without_zero_decimals($tagihan['jumlah_tagihan']); ?></td>
                        <td class="text-right">Rp <?php echo format_rupiah_without_zero_decimals($tagihan['jumlah_dibayar']); ?></td>
                        <td class="text-right">Rp <?php echo format_rupiah_without_zero_decimals($tagihan['sisa_tagihan']); ?></td>
                        <td>
                            <?php 
                                // Tentukan status berdasarkan sisa tagihan
                                if ($tagihan['sisa_tagihan'] <= 0) {
                                    echo 'Lunas';
                                } elseif ($tagihan['jumlah_dibayar'] > 0) {
                                    echo 'Sebagian Lunas';
                                } else {
                                    echo 'Belum Lunas';
                                }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($all_tagihan_siswa)): ?>
                    <tr>
                        <td colspan="7" class="text-center">Tidak ada data tagihan untuk siswa ini pada tahun ajaran yang dipilih.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" class="text-right">Total Keseluruhan</th>
                    <!-- Gunakan fungsi format_rupiah_without_zero_decimals untuk total -->
                    <th class="text-right">Rp <?php echo format_rupiah_without_zero_decimals($total_tagihan_keseluruhan); ?></th>
                    <th class="text-right">Rp <?php echo format_rupiah_without_zero_decimals($total_dibayar_keseluruhan); ?></th>
                    <th class="text-right">Rp <?php echo format_rupiah_without_zero_decimals($total_sisa_tagihan_keseluruhan); ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>

        <div class="signature-block">
            <p>Cirebon, <?php echo tanggal_indo(date('Y-m-d')); ?></p>
            <p>Mengetahui,</p>
            <p style="margin-top: 100px;">
                <span class="name"><?php echo htmlspecialchars($kepala_sekolah); ?></span><br>
                NIP. <?php echo htmlspecialchars($nip_kepala_sekolah); ?>
            </p>
        </div>

        <div class="footer">
            <p>Laporan ini dibuat secara otomatis oleh sistem <?php echo APP_NAME; ?>.</p>
        </div>
    </div>
</body>
</html>
<?php
$html = ob_get_clean(); // Ambil konten HTML dari output buffer

// --- Konfigurasi dan Generate PDF dengan Dompdf ---
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Inter'); // Set font default jika tersedia
// Jika Anda memiliki masalah dengan gambar atau CSS eksternal, pastikan ini true:
// $options->set('chroot', realpath('')); // Uncomment dan sesuaikan jika ada masalah path
// $options->set('enable_css_filter', true); // Aktifkan filter CSS (misal background-blend-mode)

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// (Opsional) Set ukuran dan orientasi kertas
$dompdf->setPaper('A4', 'portrait');

// Render HTML menjadi PDF
$dompdf->render();

// Output PDF ke browser
$filename = 'Laporan_Pembayaran_Siswa_' . str_replace(' ', '_', $siswa['nama_lengkap']) . '_' . str_replace('/', '-', $tahun_ajaran['tahun_ajaran']) . '.pdf';
$dompdf->stream($filename, ["Attachment" => false]);
exit(0);
