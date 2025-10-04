<?php
// project-root/guru/gaji_guru.php

// Memastikan file konfigurasi dan helper yang diperlukan sudah di-include
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php'; // Untuk autentikasi

// --- Class Includes ---
require_once __DIR__ . '/../classes/Guru.php';
require_once __DIR__ . '/../classes/TahunAjaran.php';
require_once __DIR__ . '/../classes/AbsensiHarianGuru.php';
require_once __DIR__ . '/../classes/AbsensiMapelGuru.php';
require_once __DIR__ . '/../classes/TunjanganBulananGuru.php';

// --- PHP SPREADSHEET INTEGRATION START ---
// Pastikan vendor/autoload.php tersedia (instalasi Composer)
// Jika Composer tidak digunakan, pastikan Anda memasukkan library PHPSpreadsheet secara manual
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
// --- PHP SPREADSHEET INTEGRATION END ---

// --- Otentikasi dan Otorisasi Guru ---
require_login();
require_role('Guru');

// Ambil ID Guru dari data sesi
$guru_id_session = $_SESSION['user_data']['related_id'] ?? null;
$guru_data = null; 

// Jika ID guru tidak tersedia di sesi
if (!$guru_id_session) {
    die("ID Guru tidak ditemukan dalam sesi. Silakan hubungi administrator.");
}

// Inisialisasi objek kelas
$guru = new Guru($pdo);
$tahunAjaranObj = new TahunAjaran($pdo);
$absensiHarianGuru = new AbsensiHarianGuru($pdo);
$tunjanganBulananGuru = new TunjanganBulananGuru($pdo);

// Ambil data guru yang login
$guru_data = $guru->findById($guru_id_session);

if (!$guru_data) {
    die("Data guru tidak ditemukan.");
}

// Inisialisasi variabel filter dan data
$rekap_gaji_data = [];
$error_message = flash('error');
$success_message = flash('success');

// Daftar semester dan bulan
$semesters = [1 => 'Ganjil', 2 => 'Genap'];
$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Mendapatkan data tahun ajaran untuk dropdown
$tahun_ajaran_list = $tahunAjaranObj->getAllTahunAjaran();

// Set nilai filter default
$default_bulan = date('n');
$default_semester = ($default_bulan >= 7 && $default_bulan <= 12) ? 1 : 2;
$default_tahun_ajaran_id = null;
$active_ta = array_filter($tahun_ajaran_list, function($ta) { return $ta['is_active'] == 1; });
if (!empty($active_ta)) {
    $default_tahun_ajaran_id = reset($active_ta)['id'];
} elseif (!empty($tahun_ajaran_list)) {
    usort($tahun_ajaran_list, function($a, $b) { 
        return strtotime($b['tahun_ajaran']) - strtotime($a['tahun_ajaran']); 
    });
    $default_tahun_ajaran_id = $tahun_ajaran_list[0]['id'];
}

$selected_tahun_ajaran_id = $_GET['tahun_ajaran_id'] ?? $default_tahun_ajaran_id;
$selected_semester = $_GET['semester'] ?? $default_semester;
$selected_bulan = $_GET['bulan'] ?? $default_bulan;

// Mendapatkan string tahun ajaran berdasarkan ID yang dipilih
$selected_ta_string = '';
foreach ($tahun_ajaran_list as $ta) {
    if ($ta['id'] == $selected_tahun_ajaran_id) {
        $selected_ta_string = $ta['tahun_ajaran'];
        break;
    }
}
$selected_semester_string = $semesters[$selected_semester] ?? '';

// Tentukan rentang tanggal (Start & End Date of the Month)
$year_for_month = date('Y');
$start_date = null;
$end_date = null;

if (!empty($selected_ta_string) && $selected_bulan) {
    $ta_parts = explode('/', $selected_ta_string);
    $year_start = (int)($ta_parts[0] ?? date('Y'));
    $year_end = (int)($ta_parts[1] ?? date('Y') + 1);
    
    if ($selected_bulan >= 1 && $selected_bulan <= 6) {
        $year_for_month = $year_end;
    } else {
        $year_for_month = $year_start;
    }

    $start_date = date('Y-m-d', strtotime("{$year_for_month}-{$selected_bulan}-01"));
    $end_date = date('Y-m-t', strtotime("{$year_for_month}-{$selected_bulan}-01"));
}

// Mendefinisikan getDayName() jika belum ada (asumsi di helpers.php)
if (!function_exists('getDayName')) {
    function getDayName($day_num) {
        $days = [
            1 => 'senin', 2 => 'selasa', 3 => 'rabu', 4 => 'kamis', 5 => 'jumat', 6 => 'sabtu', 7 => 'minggu'
        ];
        return $days[$day_num] ?? '';
    }
}


// --- DATA PROCESSING & CALCULATION (Hanya untuk guru yang login) ---
if ($guru_data && $selected_tahun_ajaran_id && $selected_semester && $selected_bulan && isset($start_date) && isset($end_date)) {
    
    $teacher = $guru_data;
    $guru_id = $teacher['id'];
    
    // --- CALCULATION 1: Gaji Berdasarkan Jadwal (Total Jam Jadwal * Gaji/Jam) ---
    $stmt_jadwal = $pdo->prepare("
        SELECT SUM(jp.jumlah_jam) AS total_jam_jadwal
        FROM jadwal_pelajaran jp
        WHERE jp.guru_id = :guru_id
        AND jp.semester = :semester_string
        AND jp.tahun_ajaran = :tahun_ajaran_string
    ");
    $stmt_jadwal->execute([
        ':guru_id' => $guru_id,
        ':semester_string' => $selected_semester_string,
        ':tahun_ajaran_string' => $selected_ta_string
    ]);
    $total_jam_jadwal = (int)$stmt_jadwal->fetchColumn();
    $gaji_per_pertemuan = (int)($teacher['gaji_per_pertemuan'] ?? 0);
    $gaji_berdasarkan_jadwal = $total_jam_jadwal * $gaji_per_pertemuan;

    // --- CALCULATION 2: Gaji Berdasarkan Absensi PTM (Total Jam PTM * Tunjangan Transport) ---
    $stmt_ptm = $pdo->prepare("
        SELECT 
            SUM(jp.jumlah_jam) AS total_jam_ptm,
            COUNT(amg.id) AS jumlah_pertemuan,
            GROUP_CONCAT(DISTINCT mp.nama_mapel SEPARATOR ', ') AS unique_subjects
        FROM absensi_mapel_guru amg
        JOIN jadwal_pelajaran jp ON amg.jadwal_id = jp.id
        LEFT JOIN mata_pelajaran mp ON jp.mapel_id = mp.id
        WHERE amg.guru_id = :guru_id
        AND amg.tanggal_ajar BETWEEN :start_date AND :end_date
        AND amg.waktu_mulai_ajar IS NOT NULL
    ");
    $stmt_ptm->execute([
        ':guru_id' => $guru_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $ptm_data = $stmt_ptm->fetch(PDO::FETCH_ASSOC);
    $total_jam_ptm = (int)($ptm_data['total_jam_ptm'] ?? 0);
    $jumlah_ptm = (int)($ptm_data['jumlah_pertemuan'] ?? 0);
    $unique_subjects = $ptm_data['unique_subjects'] ?? '-';
    $tunjangan_transport_ptm = (int)($teacher['tunjangan_transport'] ?? 0);
    $gaji_berdasarkan_ptm = $total_jam_ptm * $tunjangan_transport_ptm;
    
    // --- CALCULATION 3: Gaji Jabatan (Fixed Tunjangan Jabatan) ---
    $gaji_jabatan = (int)($teacher['tunjangan_jabatan'] ?? 0);
    $jabatan_string = $teacher['jabatan'] ?? '-'; 

    // --- CALCULATION 4 & 5: Tunjangan Jabatan & Piket (Absensi Harian) ---
    $transport_jabatan = (int)($teacher['transport_jabatan'] ?? 0);
    $transport_piket = (int)($teacher['transport_piket'] ?? 0);
    $hari_piket_guru_indo = strtolower(trim($teacher['hari_piket'] ?? ''));
    $tunjangan_sertifikasi = (int)($teacher['tunjangan_sertifikasi'] ?? 0);

    // 1. Mendapatkan semua tanggal Hadir guru dalam periode
    $absensi_harian = $absensiHarianGuru->getValidAttendanceDatesExcludingOffDays($guru_id, $start_date, $end_date); 
    
    $jumlah_kehadiran_transport_jabatan = 0;
    $jumlah_kehadiran_tunjangan_sertifikasi = 0;
    $jumlah_kehadiran_piket = 0;             
    $hari_libur_sekolah = ['jumat']; // Hari libur/non-sekolah yang dikecualikan

    foreach ($absensi_harian as $absensi_date) {
        $day_of_week_num = date('N', strtotime($absensi_date));
        $day_name_indo = strtolower(getDayName($day_of_week_num));

        // LOGIKA 1: PERHITUNGAN KEHADIRAN UNTUK TRANSPORT JABATAN
        if (!in_array($day_name_indo, $hari_libur_sekolah)) {
            $jumlah_kehadiran_transport_jabatan++;
        }
        
        if (!in_array($day_name_indo, $hari_libur_sekolah)) {
            $jumlah_kehadiran_tunjangan_sertifikasi++;
        }
        
        // LOGIKA 2: PERHITUNGAN KEHADIRAN UNTUK PIKET
        if ($day_name_indo == $hari_piket_guru_indo && $hari_piket_guru_indo !== '') {
            $jumlah_kehadiran_piket++;
        }
    }
    
    // 4. Gaji Tunjangan Jabatan
    $gaji_tunjangan_jabatan = $jumlah_kehadiran_transport_jabatan * $transport_jabatan;
    
    // 5. Gaji Piket
    $gaji_piket = $jumlah_kehadiran_piket * $transport_piket;
    
    // 6. Gaji Tunjangan Sertifikasi
    $gaji_tunjangan_sertifikasi = $jumlah_kehadiran_tunjangan_sertifikasi * $tunjangan_sertifikasi;
    
    // --- CALCULATION 7: Tunjangan Bulanan Lainnya ---
    $tunjangan_bulanan_data = $tunjanganBulananGuru->findByGuruAndPeriod($guru_id, $selected_tahun_ajaran_id, $selected_bulan);
    $nama_kegiatan_lainnya = $tunjangan_bulanan_data['kegiatan_lainnya'] ?? '-';
    $tunjangan_kegiatan_lainnya = (int)($tunjangan_bulanan_data['tunjangan_kegiatan_lainnya'] ?? 0);
    $gaji_kegiatan_lainnya = $tunjangan_kegiatan_lainnya;
    
    // --- TOTAL GAJI ---
    $total_gaji = $gaji_berdasarkan_jadwal + $gaji_berdasarkan_ptm + $gaji_jabatan + $gaji_tunjangan_jabatan + $gaji_piket + $gaji_kegiatan_lainnya + $gaji_tunjangan_sertifikasi;

    // Simpan data rekap (sebagai array tunggal)
    $rekap_gaji_data = [
        'guru_id' => $guru_id,
        'nama_guru' => htmlspecialchars($teacher['nama_lengkap']),
        
        // Raw Rates
        'gaji_per_pertemuan_raw' => $gaji_per_pertemuan,
        'tunjangan_transport_ptm_raw' => $tunjangan_transport_ptm,
        'transport_jabatan_raw' => $transport_jabatan,
        'tunjangan_sertifikasi_raw' => $tunjangan_sertifikasi,
        'transport_piket_raw' => $transport_piket,
        
        // Berdasarkan Jadwal
        'total_jam_jadwal' => $total_jam_jadwal,
        'gaji_jadwal' => $gaji_berdasarkan_jadwal,
        
        // Berdasarkan Absensi PTM
        'jumlah_ptm' => $jumlah_ptm,
        'mapel_diampu_ptm' => $unique_subjects,
        'gaji_ptm' => $gaji_berdasarkan_ptm,

        // Jabatan (Fixed)
        'nama_jabatan' => $jabatan_string,
        'gaji_jabatan' => $gaji_jabatan,
        
        // Tunjangan Jabatan (Absensi Harian)
        'kehadiran_harian' => $jumlah_kehadiran_transport_jabatan,
        'gaji_tunjangan_jabatan' => $gaji_tunjangan_jabatan,
        
        // Tunjangan Sertifikasi (Absensi Harian)
        'kehadiran_harian_sertifikasi' => $jumlah_kehadiran_tunjangan_sertifikasi,
        'gaji_tunjangan_sertifikasi' => $gaji_tunjangan_sertifikasi,
        
        // Piket (Absensi Harian)
        'hari_piket' => $hari_piket_guru_indo ? ucwords($hari_piket_guru_indo) : '-',
        'kehadiran_piket' => $jumlah_kehadiran_piket,
        'gaji_piket' => $gaji_piket,
        
        // Tunjangan Lainnya
        'nama_kegiatan_lainnya' => htmlspecialchars($nama_kegiatan_lainnya),
        'tunjangan_kegiatan_lainnya' => $tunjangan_kegiatan_lainnya,
        
        // Total
        'total_gaji' => $total_gaji,
    ];
}


// --- LOGIKA EXPORT EXCEL KHUSUS UNTUK GURU YANG SEDANG LOGIN ---
if (isset($_GET['export']) && $_GET['export'] == 'excel' && !empty($rekap_gaji_data)) {
    // Gunakan $rekap_gaji_data sebagai data yang akan diekspor
    $data_ekspor = $rekap_gaji_data; 
    
    // Membuat objek Spreadsheet baru
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    $bulan_string = $months[$selected_bulan] ?? 'Unknown';
    $judul = "REKAP BISYAROH INDIVIDU";
    $periode = "Periode: {$bulan_string} {$year_for_month} ({$selected_semester_string} {$selected_ta_string})";
    $guru_info = "Nama Guru: " . $data_ekspor['nama_guru'];

    // Header Dokumen
    $sheet->setCellValue('A1', $judul);
    $sheet->setCellValue('A2', $periode);
    $sheet->setCellValue('A3', $guru_info);
    $sheet->mergeCells('A1:E1');
    $sheet->mergeCells('A2:E2');
    $sheet->mergeCells('A3:E3');
    $sheet->getStyle('A1:A3')->getFont()->setBold(true);
    $sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Header Tabel
    $header_style = [
        'font' => ['bold' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEBEBEB']],
    ];
    $sheet->setCellValue('A5', 'Komponen Bisyaroh')->mergeCells('A5:A6');
    $sheet->setCellValue('B5', 'Rincian')->mergeCells('B5:D5');
    $sheet->setCellValue('E5', 'Total Penerimaan')->mergeCells('E5:E6');
    
    $sheet->setCellValue('B6', 'Satuan / Kehadiran');
    $sheet->setCellValue('C6', 'Nilai Satuan (Rp)');
    $sheet->setCellValue('D6', 'Dasar Perhitungan');
    
    $sheet->getStyle('A5:E6')->applyFromArray($header_style);

    // Data Bisyaroh
    $row = 7;
    $number_format = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1; // Format Rupiah (tanpa Rp)

    $rows_data = [
        ['Bisyaroh Mengajar (Berdasarkan Jam Jadwal)', $data_ekspor['total_jam_jadwal'] . ' Jam', $data_ekspor['gaji_per_pertemuan_raw'], 'Jumlah Jam Jadwal per Bulan', $data_ekspor['gaji_jadwal']],
        ['Transport Mengajar (Berdasarkan Absensi PTM)', $data_ekspor['jumlah_ptm'] . ' Pertemuan', $data_ekspor['tunjangan_transport_ptm_raw'], 'Jumlah Pertemuan Tatap Muka (Mapel: ' . $data_ekspor['mapel_diampu_ptm'] . ')', $data_ekspor['gaji_ptm']],
        ['Tunjangan Jabatan (Fixed)', '1 Bulan', $data_ekspor['gaji_jabatan'], 'Jabatan: ' . $data_ekspor['nama_jabatan'], $data_ekspor['gaji_jabatan']],
        ['Transport Jabatan (Kehadiran)', $data_ekspor['kehadiran_harian'] . ' Hari', $data_ekspor['transport_jabatan_raw'], 'Jumlah Kehadiran Hari Kerja (Sabtu-Kamis)', $data_ekspor['gaji_tunjangan_jabatan']],
        ['Tunjangan Sertifikasi (Kehadiran)', $data_ekspor['kehadiran_harian_sertifikasi'] . ' Hari', $data_ekspor['tunjangan_sertifikasi_raw'], 'Jumlah Kehadiran Hari Kerja (Sabtu-Kamis)', $data_ekspor['gaji_tunjangan_sertifikasi']],
        ['Transport Piket', $data_ekspor['kehadiran_piket'] . ' Hari', $data_ekspor['transport_piket_raw'], 'Jumlah Kehadiran pada Hari Piket (Hari Piket: ' . $data_ekspor['hari_piket'] . ')', $data_ekspor['gaji_piket']],
        ['Tunjangan Kegiatan Lainnya', '1 Bulan', $data_ekspor['tunjangan_kegiatan_lainnya'], 'Kegiatan: ' . $data_ekspor['nama_kegiatan_lainnya'], $data_ekspor['tunjangan_kegiatan_lainnya']],
    ];

    foreach ($rows_data as $data) {
        $sheet->setCellValue('A' . $row, $data[0]);
        $sheet->setCellValue('B' . $row, $data[1]);
        $sheet->setCellValue('C' . $row, $data[2]);
        $sheet->setCellValue('D' . $row, $data[3]);
        $sheet->setCellValue('E' . $row, $data[4]);

        $sheet->getStyle('A' . $row . ':E' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('C' . $row . ':E' . $row)->getNumberFormat()->setFormatCode($number_format);
        $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C' . $row . ':E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $row++;
    }

    // Baris Total
    $sheet->setCellValue('A' . $row, 'TOTAL BISYAROH KOTOR ANDA')->mergeCells('A' . $row . ':D' . $row);
    $sheet->setCellValue('E' . $row, $data_ekspor['total_gaji']);
    $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode($number_format);
    $sheet->getStyle('A' . $row . ':E' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // Auto size columns
    foreach (range('A', 'E') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
    
    // Set nama file
    $filename = "Bisyaroh_{$data_ekspor['nama_guru']}_{$bulan_string}_{$year_for_month}.xlsx";
    
    // Output file Excel
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
// --- AKHIR LOGIKA EXPORT EXCEL ---


// Menyertakan header (yang sekarang berisi sidebar dan pembuka div konten utama)
require_once __DIR__ . '/../components/header_guru.php';
?>

<style>
/* ... (Gaya CSS tetap sama) ... */
.form-container {
    background-color: #ffffff;
    border-radius: 0.75rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}
.table-container {
    background-color: #ffffff;
    border-radius: 0.75rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    overflow-x: auto; 
}
table {
    width: 100%;
    border-collapse: collapse;
}
th, td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #e2e8f0; 
}
th {
    background-color: #edf2f7; 
    font-weight: 600;
    color: #2d3748; 
    text-transform: uppercase;
    font-size: 0.875rem; 
}
tbody tr:last-child td {
    border-bottom: none;
}
tbody tr:hover {
    background-color: #f7fafc; 
}
.font-weight-bold { font-weight: 700; }
.text-center { text-align: center; }
.text-right { text-align: right; }
.text-success { color: #10B981; } /* Green */
.text-danger { color: #EF4444; } /* Red */
.align-middle { vertical-align: middle; }
</style>

<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
    <h1 class="text-3xl font-semibold text-gray-800 mb-4 sm:mb-0">Rekap Bisyaroh Bulanan</h1>
</div>

<div class="container-fluid">

    <div class="card-shadow mb-4 p-4 rounded-lg bg-white shadow-md">
    <div class="card-header py-3 border-b border-gray-200">
        <h6 class="m-0 font-bold text-lg text-blue-600">Pilih Periode Bisyaroh</h6>
    </div>
    <div class="card-body pt-4">
        <form method="GET" action="">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                
                <div>
                    <label for="tahun_ajaran_id" class="block text-sm font-medium text-gray-700 mb-1">Tahun Ajaran:</label>
                    <select name="tahun_ajaran_id" id="tahun_ajaran_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md shadow-sm">
                        <?php foreach ($tahun_ajaran_list as $ta): ?>
                            <option value="<?= $ta['id'] ?>" <?= ($selected_tahun_ajaran_id == $ta['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ta['tahun_ajaran']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="semester" class="block text-sm font-medium text-gray-700 mb-1">Semester:</label>
                    <select name="semester" id="semester" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md shadow-sm">
                        <?php foreach ($semesters as $id => $name): ?>
                            <option value="<?= $id ?>" <?= ($selected_semester == $id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="bulan" class="block text-sm font-medium text-gray-700 mb-1">Bulan:</label>
                    <select name="bulan" id="bulan" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md shadow-sm">
                        <?php foreach ($months as $id => $name): ?>
                            <option value="<?= $id ?>" <?= ($selected_bulan == $id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex space-x-2">
                    <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-300">
                        Tampilkan
                    </button>
                    <?php if (!empty($rekap_gaji_data)): ?>
                        <a href="?tahun_ajaran_id=<?= $selected_tahun_ajaran_id ?>&semester=<?= $selected_semester ?>&bulan=<?= $selected_bulan ?>&export=excel"
                           class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-300"
                           target="_blank">
                            Export Excel
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Rekap Bisyaroh Anda Periode: <?= htmlspecialchars($months[$selected_bulan] . ' ' . $year_for_month . ' - ' . $selected_semester_string . ' ' . $selected_ta_string) ?></h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-sm" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th rowspan="2" class="text-center align-middle">Komponen Bisyaroh</th>
                            <th colspan="3" class="text-center">Rincian</th>
                            <th rowspan="2" class="text-center align-middle">Total Penerimaan</th>
                        </tr>
                        <tr>
                            <th class="text-center">Satuan / Kehadiran</th>
                            <th class="text-center">Nilai Satuan (Rp)</th>
                            <th class="text-center">Dasar Perhitungan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rekap_gaji_data)): ?>
                            <tr>
                                <td colspan="5" class="text-center">Silakan pilih filter dan klik Tampilkan untuk melihat data bisyaroh Anda.</td>
                            </tr>
                        <?php else: ?>
                            <?php $data = $rekap_gaji_data; ?>
                            
                            <tr>
                                <td>**Bisyaroh Mengajar (Berdasarkan Jam Jadwal)**</td>
                                <td class="text-center"><?= number_format($data['total_jam_jadwal'], 0) ?> Jam</td>
                                <td class="text-right"><?= 'Rp ' . number_format($data['gaji_per_pertemuan_raw'], 0, ',', '.') ?></td>
                                <td>Jumlah Jam Jadwal per Bulan</td>
                                <td class="text-right font-weight-bold"><?= 'Rp ' . number_format($data['gaji_jadwal'], 0, ',', '.') ?></td>
                            </tr>
                            
                            <tr>
                                <td>**Transport Mengajar (Berdasarkan Absensi PTM)**</td>
                                <td class="text-center"><?= number_format($data['jumlah_ptm'], 0) ?> Pertemuan</td>
                                <td class="text-right"><?= 'Rp ' . number_format($data['tunjangan_transport_ptm_raw'], 0, ',', '.') ?></td>
                                <td>Jumlah Pertemuan Tatap Muka (Mapel: <?= htmlspecialchars($data['mapel_diampu_ptm']) ?>)</td>
                                <td class="text-right font-weight-bold"><?= 'Rp ' . number_format($data['gaji_ptm'], 0, ',', '.') ?></td>
                            </tr>
                            
                            <tr>
                                <td>**Tunjangan Jabatan (Fixed)**</td>
                                <td class="text-center">1 Bulan</td>
                                <td class="text-right"><?= 'Rp ' . number_format($data['gaji_jabatan'], 0, ',', '.') ?></td>
                                <td>Jabatan: <?= htmlspecialchars($data['nama_jabatan']) ?></td>
                                <td class="text-right font-weight-bold"><?= 'Rp ' . number_format($data['gaji_jabatan'], 0, ',', '.') ?></td>
                            </tr>
                            
                            <tr>
                                <td>**Transport Jabatan (Kehadiran)**</td>
                                <td class="text-center"><?= number_format($data['kehadiran_harian'], 0) ?> Hari</td>
                                <td class="text-right"><?= 'Rp ' . number_format($data['transport_jabatan_raw'], 0, ',', '.') ?></td>
                                <td>Jumlah Kehadiran Hari Kerja (Sabtu-Kamis)</td>
                                <td class="text-right font-weight-bold"><?= 'Rp ' . number_format($data['gaji_tunjangan_jabatan'], 0, ',', '.') ?></td>
                            </tr>
                            
                            <tr>
                                <td>**Tunjangan Sertifikasi (Kehadiran)**</td>
                                <td class="text-center"><?= number_format($data['kehadiran_harian_sertifikasi'], 0) ?> Hari</td>
                                <td class="text-right"><?= 'Rp ' . number_format($data['tunjangan_sertifikasi_raw'], 0, ',', '.') ?></td>
                                <td>Jumlah Kehadiran Hari Kerja (Sabtu-Kamis)</td>
                                <td class="text-right font-weight-bold"><?= 'Rp ' . number_format($data['gaji_tunjangan_sertifikasi'], 0, ',', '.') ?></td>
                            </tr>
                            
                            <tr>
                                <td>**Transport Piket**</td>
                                <td class="text-center"><?= number_format($data['kehadiran_piket'], 0) ?> Hari</td>
                                <td class="text-right"><?= 'Rp ' . number_format($data['transport_piket_raw'], 0, ',', '.') ?></td>
                                <td>Jumlah Kehadiran pada Hari Piket (Hari Piket: <?= $data['hari_piket'] ?>)</td>
                                <td class="text-right font-weight-bold"><?= 'Rp ' . number_format($data['gaji_piket'], 0, ',', '.') ?></td>
                            </tr>
                            
                            <tr>
                                <td>**Tunjangan Kegiatan Lainnya**</td>
                                <td class="text-center">1 Bulan</td>
                                <td class="text-right"><?= 'Rp ' . number_format($data['tunjangan_kegiatan_lainnya'], 0, ',', '.') ?></td>
                                <td>Kegiatan: <?= $data['nama_kegiatan_lainnya'] ?></td>
                                <td class="text-right font-weight-bold"><?= 'Rp ' . number_format($data['tunjangan_kegiatan_lainnya'], 0, ',', '.') ?></td>
                            </tr>

                            <tr>
                                <td colspan="4" class="text-right font-weight-bold text-lg pt-4">TOTAL BISYAROH KOTOR ANDA</td>
                                <td class="text-right text-success font-weight-bold text-lg pt-4"><?= 'Rp ' . number_format($data['total_gaji'], 0, ',', '.') ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php
// Menyertakan footer
require_once __DIR__ . '/../components/footer.php';
?>