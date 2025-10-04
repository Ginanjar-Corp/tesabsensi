<?php
// project-root/admin/rekap_gaji_guru.php

// Memastikan file konfigurasi dan helper yang diperlukan sudah di-include
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php'; // Asumsi untuk autentikasi

// --- Class Includes ---
require_once __DIR__ . '/../classes/Guru.php';
require_once __DIR__ . '/../classes/TahunAjaran.php';
require_once __DIR__ . '/../classes/AbsensiHarianGuru.php';
require_once __DIR__ . '/../classes/AbsensiMapelGuru.php';
require_once __DIR__ . '/../classes/TunjanganBulananGuru.php';

// --- PHP SPREADSHEET INTEGRATION START ---
// Pastikan vendor/autoload.php tersedia (instalasi Composer)
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
// --- PHP SPREADSHEET INTEGRATION END ---

require_login();
require_role(['Admin', 'TataUsaha']);

// Inisialisasi objek kelas
$guru = new Guru($pdo);
$tahunAjaranObj = new TahunAjaran($pdo);
$absensiHarianGuru = new AbsensiHarianGuru($pdo);
$tunjanganBulananGuru = new TunjanganBulananGuru($pdo);

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
// Tentukan semester default berdasarkan bulan saat ini (Jul-Des Ganjil, Jan-Jun Genap)
$default_semester = ($default_bulan >= 7 && $default_bulan <= 12) ? 1 : 2;
$default_tahun_ajaran_id = null;
$active_ta = array_filter($tahun_ajaran_list, function($ta) { return $ta['is_active'] == 1; });
if (!empty($active_ta)) {
    $default_tahun_ajaran_id = reset($active_ta)['id'];
} elseif (!empty($tahun_ajaran_list)) {
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
$year_for_month = date('Y'); // Default ke tahun saat ini
if (!empty($selected_ta_string) && $selected_bulan) {
    $ta_parts = explode('/', $selected_ta_string);
    $year_start = (int)($ta_parts[0] ?? date('Y'));
    $year_end = (int)($ta_parts[1] ?? date('Y') + 1);
    
    // Bulan 1 (Jan) - 6 (Jun) = Tahun kedua TA (Semester Genap)
    // Bulan 7 (Jul) - 12 (Des) = Tahun pertama TA (Semester Ganjil)
    if ($selected_bulan >= 1 && $selected_bulan <= 6) {
        $year_for_month = $year_end;
    } else {
        $year_for_month = $year_start;
    }

    $start_date = date('Y-m-d', strtotime("{$year_for_month}-{$selected_bulan}-01"));
    $end_date = date('Y-m-t', strtotime("{$year_for_month}-{$selected_bulan}-01"));
}

// --- DATA PROCESSING & CALCULATION ---
if ($selected_tahun_ajaran_id && $selected_semester && $selected_bulan && isset($start_date) && isset($end_date)) {
    
    // 1. Ambil semua data guru aktif
    $all_teachers = $guru->getAll(false); 
    
    foreach ($all_teachers as $teacher) {
        $guru_id = $teacher['id'];
        
        // --- CALCULATION 1: Gaji Berdasarkan Jadwal (Total Jam Jadwal * Gaji/Jam) ---
        // Jumlahkan nilai kolom jumlah_jam dari tabel jadwal_pelajaran
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
        // jumlahkan jumlah_jam jadwal berdasarkan absensi_mapel_guru (hanya yang sudah dikonfirmasi)
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
            AND amg.waktu_mulai_ajar IS NOT NULL -- Memastikan sesi sudah dimulai/dikonfirmasi
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
        // diambil dari kolom tunjangan_jabatan dari tabel guru
        $gaji_jabatan = (int)($teacher['tunjangan_jabatan'] ?? 0);
        $jabatan_string = $teacher['jabatan'] ?? '-'; // Asumsi ada kolom jabatan di tabel guru

        // --- CALCULATION 4 & 5: Tunjangan Jabatan, Sertifikasi & Piket (Absensi Harian) ---
        $transport_jabatan = (int)($teacher['transport_jabatan'] ?? 0);
        $transport_piket = (int)($teacher['transport_piket'] ?? 0);
        $hari_piket_guru_indo = strtolower(trim($teacher['hari_piket'] ?? '')); // Misalnya 'senin', 'selasa'
        $tunjangan_sertifikasi =  (int)($teacher['tunjangan_sertifikasi'] ?? 0); 

        // 1. Mendapatkan semua tanggal Hadir guru dalam periode (menggunakan metode yang ada)
        // Kita akan melakukan filtering Hari Jumat di PHP setelah ini.
        $absensi_harian = $absensiHarianGuru->getValidAttendanceDatesExcludingOffDays($guru_id, $start_date, $end_date); 
        
        $jumlah_kehadiran_transport_jabatan = 0; // Kehadiran untuk Transport Jabatan (Sabtu-Kamis)
        $jumlah_kehadiran_tunjangan_sertifikasi = 0; // Kehadiran untuk Tunjangan Sertifikasi (Sabtu-Kamis)
        $jumlah_kehadiran_piket = 0;             // Kehadiran untuk Piket (Sesuai hari piket guru)
        $hari_libur_sekolah = ['jumat']; // Hari libur/non-sekolah yang dikecualikan

        foreach ($absensi_harian as $absensi_date) {
            // Dapatkan nama hari dalam bahasa Indonesia untuk tanggal absensi
            $day_of_week_num = date('N', strtotime($absensi_date)); // 1 (Mon) to 7 (Sun)
            $day_name_indo = strtolower(getDayName($day_of_week_num)); // Asumsi getDayName di helpers.php

            // LOGIKA 1: PERHITUNGAN KEHADIRAN UNTUK TRANSPORT JABATAN
            // Hitung kehadiran hanya jika hari itu BUKAN hari libur sekolah (Jumat)
            if (!in_array($day_name_indo, $hari_libur_sekolah)) {
                $jumlah_kehadiran_transport_jabatan++;
            }
            
            if (!in_array($day_name_indo, $hari_libur_sekolah)) {
                $jumlah_kehadiran_tunjangan_sertifikasi++;
            }
            
            // LOGIKA 2: PERHITUNGAN KEHADIRAN UNTUK PIKET
            // Cek apakah tanggal ini adalah hari piket guru DAN BUKAN hari libur sekolah (Jumat)
            if ($day_name_indo == $hari_piket_guru_indo && $hari_piket_guru_indo !== '') {
                // Catatan: Jika hari piket guru adalah Jumat, maka tidak akan pernah tercatat di sini 
                // karena hari Jumat adalah hari libur, *kecuali* Anda membolehkan piket di hari libur.
                // Sesuai permintaan: Gaji Piket dihitung jika hadir pada Hari Piket.
                // Karena hari piket haruslah hari kerja, dan Jumat adalah hari libur, maka hari piket tidak boleh Jumat.
                // Asumsi: Guru hanya piket pada hari kerja (Sabtu-Kamis).
                $jumlah_kehadiran_piket++;
            }
        }
        
        // --- CATATAN PENTING ---
        // $jumlah_kehadiran_harian (semua hari) tidak lagi relevan untuk Gaji Tunjangan Jabatan, 
        // tapi kita simpan data kehadiran total (termasuk Jumat jika hadir) untuk keperluan display.
        $jumlah_kehadiran_total = count($absensi_harian);


        // 4. Gaji Tunjangan Jabatan
        // jumlah kehadiran pada hari kerja (Sabtu-Kamis) * transport_jabatan
        $gaji_tunjangan_jabatan = $jumlah_kehadiran_transport_jabatan * $transport_jabatan;
        
        // 5. Gaji Piket
        // jumlah kehadiran pada hari piket * transport_piket
        $gaji_piket = $jumlah_kehadiran_piket * $transport_piket;
        
        // 6. Gaji Tunjangan Sertifikasi
        // jumlah kehadiran pada hari kerja (Sabtu-Kamis) * tunjangan_sertifikasi
        $gaji_tunjangan_sertifikasi = $jumlah_kehadiran_tunjangan_sertifikasi * $tunjangan_sertifikasi;
        
        // ... (lanjutkan ke CALCULATION 7)
        
        // --- CALCULATION 7: Tunjangan Bulanan Lainnya ---
        // diambil dari tabel tunjangan_bulanan_guru
        $tunjangan_bulanan_data = $tunjanganBulananGuru->findByGuruAndPeriod($guru_id, $selected_tahun_ajaran_id, $selected_bulan);
        $nama_kegiatan_lainnya = $tunjangan_bulanan_data['kegiatan_lainnya'] ?? '-';
        $tunjangan_kegiatan_lainnya = (int)($tunjangan_bulanan_data['tunjangan_kegiatan_lainnya'] ?? 0);
        $gaji_kegiatan_lainnya = $tunjangan_kegiatan_lainnya;
        
        // --- TOTAL GAJI ---
        $total_gaji = $gaji_berdasarkan_jadwal + $gaji_berdasarkan_ptm + $gaji_jabatan + $gaji_tunjangan_jabatan + $gaji_piket + $gaji_tunjangan_sertifikasi + $gaji_kegiatan_lainnya;

        // Simpan data rekap
        $rekap_gaji_data[] = [
            'guru_id' => $guru_id,
            'nama_guru' => htmlspecialchars($teacher['nama_lengkap']),
            
            // Raw Rates (Untuk kebutuhan display detail di tabel)
            'gaji_per_pertemuan_raw' => $gaji_per_pertemuan,
            'tunjangan_transport_ptm_raw' => $tunjangan_transport_ptm,
            'transport_jabatan_raw' => $transport_jabatan,
            'transport_piket_raw' => $transport_piket,
            'tunjangan_sertifikasi_raw' => $tunjangan_sertifikasi,
            
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
    
    // Urutkan data berdasarkan nama guru
    usort($rekap_gaji_data, function($a, $b) {
        return strcmp($a['nama_guru'], $b['nama_guru']);
    });
}

// --- EXCEL EXPORT LOGIC ---
if (isset($_GET['action']) && $_GET['action'] === 'export' && !empty($rekap_gaji_data)) {
    
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Judul
    $sheet->setCellValue('A1', 'REKAP GAJI GURU');
    $sheet->mergeCells('A1:S1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->setCellValue('A2', 'Periode: ' . $months[$selected_bulan] . ' ' . $year_for_month . ' | Semester: ' . $selected_semester_string . ' | Tahun Ajaran: ' . $selected_ta_string);
    $sheet->mergeCells('A2:S2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Header Tabel (Multi-level)
    $headers = [
        // Level 1
        ['No', 'Nama', 'Berdasarkan Jadwal', '', '', 'Berdasarkan Absensi PTM', '', '', 'Jabatan', '', 'Tunjangan Jabatan', '', '', 'Piket', '', '', 'Tunjangan Lainnya (Bulanan)', '', 'Tunjangan Sertifikasi', 'Jumlah Gaji'],
        // Level 2 (Sub-headers)
        ['', '', 'Jml Jam', 'Gaji/Jam', 'Total Gaji', 'Jml PTM', 'Mapel diampu', 'Total Gaji', 'Nama Jabatan', 'Gaji', 'Kehadiran', 'Transport Jabatan', 'Total Gaji', 'Kehadiran Piket', 'Transport Piket', 'Total Gaji', 'Nama Kegiatan', 'Tunjangan', 'Kehadiran', 'Transport Jabatan', 'Total Gaji',''],
    ];

    $start_row = 4;
    $sheet->fromArray($headers[0], NULL, 'A' . $start_row);
    $sheet->fromArray($headers[1], NULL, 'A' . ($start_row + 1));
    $header_row_1 = $start_row;
    $header_row_2 = $start_row + 1;

    // Merge Level 1 Headers
    $sheet->mergeCells("C{$header_row_1}:E{$header_row_1}"); // Berdasarkan Jadwal (3 columns)
    $sheet->mergeCells("F{$header_row_1}:H{$header_row_1}"); // Berdasarkan Absensi PTM (3 columns)
    $sheet->mergeCells("I{$header_row_1}:J{$header_row_1}"); // Jabatan (2 columns)
    $sheet->mergeCells("K{$header_row_1}:M{$header_row_1}"); // Tunjangan Jabatan (3 columns)
    $sheet->mergeCells("N{$header_row_1}:P{$header_row_1}"); // Piket (3 columns)
    $sheet->mergeCells("Q{$header_row_1}:R{$header_row_1}"); // Tunjangan Lainnya (2 columns)
    $sheet->mergeCells("S{$header_row_1}:U{$header_row_1}"); // Tunjangan Sertifikasi (3 columns)    
    // Merge single cells vertically (Level 1 & 2)
    $sheet->mergeCells("A{$header_row_1}:A{$header_row_2}"); // No
    $sheet->mergeCells("B{$header_row_1}:B{$header_row_2}"); // Nama
    $sheet->mergeCells("V{$header_row_1}:V{$header_row_2}"); // Jumlah Gaji
    
    // Style Headers
    $styleArray = [
        'font' => ['bold' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE0E0E0']],
    ];
    $sheet->getStyle('A' . $header_row_1 . ':T' . $header_row_2)->applyFromArray($styleArray);
    
    // Data
    $row = $header_row_2 + 1;
    $no = 1;
    $grand_total = 0;
    foreach ($rekap_gaji_data as $data) {
        
        $grand_total += $data['total_gaji'];
        
        $sheet->setCellValue('A' . $row, $no++);
        $sheet->setCellValue('B' . $row, $data['nama_guru']);

        // C-E: Berdasarkan Jadwal
        $sheet->setCellValue('C' . $row, $data['total_jam_jadwal']); // Jml Jam
        $sheet->setCellValue('D' . $row, $data['gaji_per_pertemuan_raw']); // Gaji/Jam
        $sheet->setCellValue('E' . $row, $data['gaji_jadwal']); // Total Gaji

        // F-H: Berdasarkan Absensi PTM
        $sheet->setCellValue('F' . $row, $data['jumlah_ptm']); // Jml PTM
        $sheet->setCellValue('G' . $row, $data['mapel_diampu_ptm']); // Mapel diampu
        $sheet->setCellValue('H' . $row, $data['gaji_ptm']); // Total Gaji

        // I-J: Jabatan
        $sheet->setCellValue('I' . $row, $data['nama_jabatan']); // Nama Jabatan
        $sheet->setCellValue('J' . $row, $data['gaji_jabatan']); // Gaji

        // K-M: Tunjangan Jabatan (Absensi Harian)
        $sheet->setCellValue('K' . $row, $data['kehadiran_harian']); // Kehadiran
        $sheet->setCellValue('L' . $row, $data['transport_jabatan_raw']); // Transport Jabatan
        $sheet->setCellValue('M' . $row, $data['gaji_tunjangan_jabatan']); // Total Gaji

        // N-P: Piket
        $sheet->setCellValue('N' . $row, $data['kehadiran_piket']); // Kehadiran Piket
        $sheet->setCellValue('O' . $row, $data['transport_piket_raw']); // Transport Piket
        $sheet->setCellValue('P' . $row, $data['gaji_piket']); // Total Gaji
        
        // Q-R: Tunjangan Lainnya
        $sheet->setCellValue('Q' . $row, $data['nama_kegiatan_lainnya']); // Nama Kegiatan
        $sheet->setCellValue('R' . $row, $data['tunjangan_kegiatan_lainnya']); // Tunjangan

        // S-U: Tunjangan Sertifikasi (Absensi Harian)
        $sheet->setCellValue('S' . $row, $data['kehadiran_harian_sertifikasi']); // Kehadiran
        $sheet->setCellValue('T' . $row, $data['tunjangan_sertifikasi_raw']); // Transport Jabatan
        $sheet->setCellValue('U' . $row, $data['gaji_tunjangan_sertifikasi']); // Total Gaji
        
        // V: Total Gaji
        $sheet->setCellValue('V' . $row, $data['total_gaji']);
        
        // Format angka (Kolom D, E, H, J, L, M, O, P, R, S)
        $money_cols = ['D', 'E', 'H', 'J', 'L', 'M', 'O', 'P', 'R', 'S', 'T', 'U'];
        foreach ($money_cols as $col) {
            $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        }
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('K' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('N' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row++;
    }
    
    // Baris Grand Total
    $sheet->setCellValue('A' . $row, 'GRAND TOTAL');
    $sheet->mergeCells('A' . $row . ':R' . $row);
    $sheet->setCellValue('U' . $row, $grand_total);
    $sheet->getStyle('A' . $row . ':S' . $row)->getFont()->setBold(true);
    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('S' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $sheet->getStyle('U' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $sheet->getStyle('A' . $row . ':T' . $row)->getBorders()->getTop()->setBorderStyle(Border::BORDER_DOUBLE);

    // Auto-size columns
    foreach (range('A', 'T') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }
    
    // Set borders for data
    $styleData = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
            ],
        ],
    ];
    $sheet->getStyle('A' . ($header_row_2 + 1) . ':T' . ($row))->applyFromArray($styleData);
    
    // Excel Output
    $filename = 'Rekap_Gaji_Guru_' . $months[$selected_bulan] . '_' . $year_for_month . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}


// Menyertakan header (yang sekarang berisi sidebar dan pembuka div konten utama)
require_once __DIR__ . '/../components/header_admin.php';
?>

<style>
/* Gaya CSS tambahan untuk form dan tabel */
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
    overflow-x: auto; /* Untuk responsivitas tabel */
}
table {
    width: 100%;
    border-collapse: collapse;
}
th, td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #e2e8f0; /* gray-200 */
}
th {
    background-color: #edf2f7; /* gray-100 */
    font-weight: 600;
    color: #2d3748; /* gray-800 */
    text-transform: uppercase;
    font-size: 0.875rem; /* text-sm */
}
tbody tr:last-child td {
    border-bottom: none;
}
tbody tr:hover {
    background-color: #f7fafc; /* gray-50 */
}
</style>

<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
    <h1 class="text-3xl font-semibold text-gray-800 mb-4 sm:mb-0">Rekap Bisyaroh Guru Bulanan</h1>
</div>

<div class="container-fluid">

    <div class="card-shadow mb-4 p-4 rounded-lg bg-white shadow-md">
    <div class="card-header py-3 border-b border-gray-200">
        <h6 class="m-0 font-bold text-lg text-blue-600">Filter Data</h6>
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
                        <a href="?tahun_ajaran_id=<?= $selected_tahun_ajaran_id ?>&semester=<?= $selected_semester ?>&bulan=<?= $selected_bulan ?>&action=export" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-300">
                             Export ke Excel
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Rekap Gaji Guru Periode: <?= htmlspecialchars($months[$selected_bulan] . ' ' . $year_for_month . ' - ' . $selected_semester_string . ' ' . $selected_ta_string) ?></h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-sm" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th rowspan="2" class="text-center align-middle">No</th>
                            <th rowspan="2" class="text-center align-middle">Nama</th>
                            
                            <th colspan="3" class="text-center">Berdasarkan Jadwal</th>
                            <th colspan="3" class="text-center">Berdasarkan Absensi PTM</th>
                            <th colspan="2" class="text-center">Jabatan</th>
                            <th colspan="3" class="text-center">Tunjangan Jabatan</th>
                            <th colspan="3" class="text-center">Tunjangan Sertifikasi</th>
                            <th colspan="3" class="text-center">Piket</th>
                            <th colspan="2" class="text-center">Tunjangan Lainnya (Bulanan)</th>
                            
                            <th rowspan="2" class="text-center align-middle">Jumlah Gaji</th>
                        </tr>
                        <tr>
                            <th class="text-center">Jml Jam</th>
                            <th class="text-center">Gaji/Jam</th>
                            <th class="text-center">Total Gaji</th>
                            
                            <th class="text-center">Jml PTM</th>
                            <th class="text-center">Mapel Diampu</th>
                            <th class="text-center">Total Gaji</th>
                            
                            <th class="text-center">Nama Jabatan</th>
                            <th class="text-center">Gaji</th>
                            
                            <th class="text-center">Kehadiran</th>
                            <th class="text-center">Transport Jabatan</th>
                            <th class="text-center">Total Gaji</th>
                            
                            <th class="text-center">Kehadiran</th>
                            <th class="text-center">Tunjangan Sertfikasi</th>
                            <th class="text-center">Total Gaji</th>
                            
                            <th class="text-center">Kehadiran Piket</th>
                            <th class="text-center">Transport Piket</th>
                            <th class="text-center">Total Gaji</th>
                            
                            <th class="text-center">Nama Kegiatan</th>
                            <th class="text-center">Tunjangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rekap_gaji_data)): ?>
                            <tr>
                                <td colspan="19" class="text-center">Silakan pilih filter dan klik Tampilkan untuk melihat data.</td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $no = 1;
                            $grand_total = 0;
                            foreach ($rekap_gaji_data as $data): 
                                $grand_total += $data['total_gaji'];
                            ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <td><?= $data['nama_guru'] ?></td>
                                
                                <td class="text-center"><?= number_format($data['total_jam_jadwal'], 0) ?></td>
                                <td class="text-right"><?= 'Rp ' . number_format($data['gaji_per_pertemuan_raw'], 0, ',', '.') ?></td>
                                <td class="text-right font-weight-bold"><?= 'Rp ' . number_format($data['gaji_jadwal'], 0, ',', '.') ?></td>
                                
                                <td class="text-center"><?= number_format($data['jumlah_ptm'], 0) ?></td>
                                <td><?= htmlspecialchars($data['mapel_diampu_ptm']) ?></td>
                                <td class="text-right font-weight-bold"><?= 'Rp ' . number_format($data['gaji_ptm'], 0, ',', '.') ?></td>
                                
                                <td><?= htmlspecialchars($data['nama_jabatan']) ?></td>
                                <td class="text-right"><?= 'Rp ' . number_format($data['gaji_jabatan'], 0, ',', '.') ?></td>
                                
                                <td class="text-center"><?= number_format($data['kehadiran_harian'], 0) ?></td>
                                <td class="text-right"><?= 'Rp ' . number_format($data['transport_jabatan_raw'], 0, ',', '.') ?></td>
                                <td class="text-right font-weight-bold"><?= 'Rp ' . number_format($data['gaji_tunjangan_jabatan'], 0, ',', '.') ?></td>
                                
                                <td class="text-center"><?= number_format($data['kehadiran_harian_sertifikasi'], 0) ?></td>
                                <td class="text-right"><?= 'Rp ' . number_format($data['tunjangan_sertifikasi_raw'], 0, ',', '.') ?></td>
                                <td class="text-right font-weight-bold"><?= 'Rp ' . number_format($data['gaji_tunjangan_sertifikasi'], 0, ',', '.') ?></td>

                                <td class="text-center" title="Hari Piket: <?= $data['hari_piket'] ?>"><?= number_format($data['kehadiran_piket'], 0) ?></td>
                                <td class="text-right"><?= 'Rp ' . number_format($data['transport_piket_raw'], 0, ',', '.') ?></td>
                                <td class="text-right font-weight-bold"><?= 'Rp ' . number_format($data['gaji_piket'], 0, ',', '.') ?></td>
                                
                                <td><?= $data['nama_kegiatan_lainnya'] ?></td>
                                <td class="text-right"><?= 'Rp ' . number_format($data['tunjangan_kegiatan_lainnya'], 0, ',', '.') ?></td>

                                <td class="text-right text-success font-weight-bold"><?= 'Rp ' . number_format($data['total_gaji'], 0, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td colspan="18" class="text-right font-weight-bold">GRAND TOTAL</td>
                                <td class="text-right text-danger font-weight-bold"><?= 'Rp ' . number_format($grand_total, 0, ',', '.') ?></td>
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