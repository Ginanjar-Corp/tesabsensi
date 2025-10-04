<?php
// project-root/admin/rekap_gaji_dan_transport.php

// Memastikan file konfigurasi dan helper yang diperlukan sudah di-include
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/GajiSettings.php';

// --- PHP SPREADSHEET INTEGRATION START ---
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
// --- PHP SPREADSHEET INTEGRATION END ---

// Memastikan pengguna sudah login dan memiliki peran yang sesuai
require_login();
require_role(['Admin', 'TataUsaha']);

// Inisialisasi variabel
$pdo = null;
$rekap_data = []; // Untuk rekap bisyaroh
$rekap_data_bulanan = []; // Untuk rekap gaji bulanan
$detail_data_for_excel = []; // Untuk detail absensi di Excel
$error_message = flash('error');
$success_message = flash('success');
$report_type = $_POST['report_type'] ?? 'bisyaroh'; // Default: bisyaroh

// Daftar bulan
$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Daftar semester (untuk rekap gaji bulanan)
$semesters = [
    1 => 'Ganjil',
    2 => 'Genap'
];

// Inisialisasi koneksi database
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan koneksi database. Silakan coba lagi nanti.');
}

// Ambil tahun ajaran yang tersedia dari database
$available_academic_years = [];
$selected_academic_year_id = null;
$selected_academic_year_str = '';
$selected_month = null;
$selected_semester = null;

if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, tahun_ajaran FROM tahun_ajaran ORDER BY tahun_ajaran DESC");
        $available_academic_years = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($available_academic_years)) {
            $default_academic_year_id = APP_TAHUN_AJARAN_AKTIF ?? ($available_academic_years[0]['id'] ?? null);
            $selected_academic_year_id = $_POST['academic_year_id'] ?? $default_academic_year_id;
            if (!is_numeric($selected_academic_year_id)) {
                $selected_academic_year_id = null;
            }

            foreach ($available_academic_years as $year) {
                if ($year['id'] == $selected_academic_year_id) {
                    $selected_academic_year_str = $year['tahun_ajaran'];
                    break;
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Database error fetching academic years: " . $e->getMessage());
        set_flash('error', 'Terjadi kesalahan database saat mengambil tahun ajaran.');
    }
}

// Ambil nominal gaji per pertemuan
$nominal_gaji_per_pertemuan = 0;
if ($pdo) {
    try {
        $gaji_settings_model = new GajiSettings($pdo);
        $gaji_settings = $gaji_settings_model->getSettings();
        if ($gaji_settings && isset($gaji_settings['nominal_per_pertemuan'])) {
            $nominal_gaji_per_pertemuan = (float)$gaji_settings['nominal_per_pertemuan'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching Gaji Settings: " . $e->getMessage());
    }
}

/**
 * Fungsi untuk mengambil data absensi mengajar guru untuk bulan dan tahun ajaran tertentu.
 * Mengambil waktu_mulai_ajar dan waktu_selesai_ajar dari absensi_mapel_guru.
 *
 * @param int $month Bulan (1-12)
 * @param string $academic_year_str Tahun Ajaran (misal: "2023/2024")
 * @return array Array data mengajar guru
 */
function getGuruMonthlyTeachingData($month, $academic_year_str) {
    global $pdo;

    $data = [];
    if (!$pdo) {
        error_log("PDO connection not available.");
        return [];
    }
    
    $sql = "
        SELECT
            g.id AS id_guru,
            g.nama_lengkap,
            amg.waktu_mulai_ajar,
            amg.waktu_selesai_ajar,
            amg.tanggal_ajar,
            mp.nama_mapel,
            COALESCE(k.nama_kelas, 'Pribadi') AS nama_kelas,
            jp.jumlah_jam
        FROM
            guru g
        JOIN
            absensi_mapel_guru amg ON g.id = amg.guru_id
        JOIN
            jadwal_pelajaran jp ON amg.jadwal_id = jp.id
        JOIN
            mata_pelajaran mp ON jp.mapel_id = mp.id
        LEFT JOIN
            kelas k ON jp.kelas_id = k.id
        WHERE
            g.is_active = 1
            AND jp.tahun_ajaran = :academic_year_str
            AND MONTH(amg.tanggal_ajar) = :month
        ORDER BY
            g.nama_lengkap, amg.tanggal_ajar, amg.waktu_mulai_ajar
    ";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':academic_year_str', $academic_year_str);
        $stmt->bindParam(':month', $month);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error in getGuruMonthlyTeachingData: " . $e->getMessage());
        return [];
    }
    return $data;
}

/**
 * Mengambil data jadwal mengajar guru untuk tahun ajaran dan semester tertentu,
 * mengagregasi data berdasarkan bulan.
 *
 * @param string $academic_year_str Tahun Ajaran (misal: "2023/2024")
 * @param int|null $semester Semester (1=Ganjil, 2=Genap)
 * @return array
 */
function getGuruRecapByMonth($academic_year_str, $semester) {
    global $pdo, $nominal_gaji_per_pertemuan;
    $rekap = [];

    if (!$pdo) {
        error_log("PDO connection not available in getGuruRecapByMonth.");
        return [];
    }

    $sql = "
        SELECT
            g.id AS id_guru,
            g.nama_lengkap,
            jp.jumlah_jam,
            mp.nama_mapel,
            jp.tanggal_mulai,
            jp.tanggal_selesai
        FROM
            jadwal_pelajaran jp
        JOIN
            guru g ON jp.guru_id = g.id
        JOIN
            mata_pelajaran mp ON jp.mapel_id = mp.id
        WHERE
            jp.tahun_ajaran = :academic_year_str
            AND g.is_active = 1
    ";
    
    if ($semester !== null) {
        $sql .= " AND jp.semester = :semester";
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':academic_year_str', $academic_year_str);
        if ($semester !== null) {
            $stmt->bindParam(':semester', $semester, PDO::PARAM_INT);
        }
        $stmt->execute();
        $jadwal_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($jadwal_data as $jadwal) {
            $guru_id = $jadwal['id_guru'];
            $nama_guru = $jadwal['nama_lengkap'];
            $nama_mapel = $jadwal['nama_mapel'];
            $jumlah_jam = (int)$jadwal['jumlah_jam'];
            
            $start_date = new DateTime($jadwal['tanggal_mulai']);
            $end_date = new DateTime($jadwal['tanggal_selesai']);

            while ($start_date <= $end_date) {
                $month_key = $start_date->format('Y-m-01');
                
                if (!isset($rekap[$guru_id][$month_key])) {
                    $rekap[$guru_id][$month_key] = [
                        'nama_guru' => $nama_guru,
                        'month_start_date' => $month_key,
                        'jumlah_pertemuan' => 0,
                        'total_jam_mengajar' => 0,
                        'total_gaji' => 0,
                        'unique_subjects' => [],
                    ];
                }
                
                $rekap[$guru_id][$month_key]['jumlah_pertemuan']++;
                $rekap[$guru_id][$month_key]['total_jam_mengajar'] += $jumlah_jam;
                $rekap[$guru_id][$month_key]['total_gaji'] += $jumlah_jam * $nominal_gaji_per_pertemuan;

                if (!in_array($nama_mapel, $rekap[$guru_id][$month_key]['unique_subjects'])) {
                    $rekap[$guru_id][$month_key]['unique_subjects'][] = $nama_mapel;
                }
                
                $start_date->modify('+1 day');
            }
        }
    } catch (PDOException $e) {
        error_log("Error in getGuruRecapByMonth: " . $e->getMessage());
        return [];
    }

    return $rekap;
}


// Proses form submission
if (isset($_POST['generate_report']) || isset($_POST['export_excel'])) {
    $selected_academic_year_id_post = filter_input(INPUT_POST, 'academic_year_id', FILTER_VALIDATE_INT);
    $report_type = $_POST['report_type'] ?? 'bisyaroh'; // Pastikan ini terambil dengan benar

    // Update selected_academic_year_id and selected_academic_year_str based on POST
    $selected_academic_year_id = $selected_academic_year_id_post;
    $selected_academic_year_str = '';
    foreach ($available_academic_years as $year) {
        if ($year['id'] == $selected_academic_year_id) {
            $selected_academic_year_str = $year['tahun_ajaran'];
            break;
        }
    }

    if (empty($available_academic_years) || !$selected_academic_year_id) {
        set_flash('error', 'Pilihan tahun ajaran tidak valid.');
    } else {
        if ($report_type === 'bisyaroh') {
            $selected_month = filter_input(INPUT_POST, 'month', FILTER_VALIDATE_INT);
            if (!$selected_month) {
                set_flash('error', 'Pilihan bulan tidak valid.');
            } else {
                $guru_teaching_data = getGuruMonthlyTeachingData($selected_month, $selected_academic_year_str);
                
                foreach ($guru_teaching_data as $entry) {
                    $guru_id = $entry['id_guru'];
                    $nama_guru = $entry['nama_lengkap'];
                    $nama_mapel = $entry['nama_mapel'];
                    $nama_kelas = $entry['nama_kelas'];
                    $tanggal_ajar = $entry['tanggal_ajar'];
                    $waktu_mulai_ajar = new DateTime($entry['waktu_mulai_ajar']);
                    $waktu_selesai_ajar = new DateTime($entry['waktu_selesai_ajar']);
                    $jumlah_jam_jadwal = (int)$entry['jumlah_jam'];

                    if (!isset($rekap_data[$guru_id])) {
                        $rekap_data[$guru_id] = [
                            'nama_guru' => $nama_guru,
                            'jumlah_kehadiran' => 0,
                            'total_jam_dibayar' => 0,
                            'total_gaji' => 0.0,
                            'unique_subjects' => [],
                        ];
                    }
                    $rekap_data[$guru_id]['jumlah_kehadiran']++;
                    $rekap_data[$guru_id]['total_jam_dibayar'] += $jumlah_jam_jadwal;
                    $rekap_data[$guru_id]['total_gaji'] = $rekap_data[$guru_id]['total_jam_dibayar'] * $nominal_gaji_per_pertemuan;

                    if (!in_array($nama_mapel, $rekap_data[$guru_id]['unique_subjects'])) {
                        $rekap_data[$guru_id]['unique_subjects'][] = $nama_mapel;
                    }

                    $pertemuan_duration_in_minutes = 0;
                    if ($waktu_selesai_ajar > $waktu_mulai_ajar) {
                        $pertemuan_interval = $waktu_mulai_ajar->diff($waktu_selesai_ajar);
                        $pertemuan_duration_in_minutes = ($pertemuan_interval->h * 60) + $pertemuan_interval->i;
                    }

                    $detail_data_for_excel[] = [
                        'nama_guru' => $nama_guru,
                        'nama_mapel' => $nama_mapel,
                        'nama_kelas' => $nama_kelas,
                        'tanggal_ajar' => $tanggal_ajar,
                        'waktu_mulai' => substr($waktu_mulai_ajar->format('H:i:s'), 0, 5),
                        'waktu_selesai' => substr($waktu_selesai_ajar->format('H:i:s'), 0, 5),
                        'durasi_menit' => $pertemuan_duration_in_minutes,
                        'jam_jadwal' => $jumlah_jam_jadwal,
                        'status_absen' => 'Hadir'
                    ];
                }
                foreach ($rekap_data as $guru_id => $data) {
                    sort($rekap_data[$guru_id]['unique_subjects']);
                }
            }
        } elseif ($report_type === 'gaji_bulanan') {
            $selected_semester = filter_input(INPUT_POST, 'semester', FILTER_VALIDATE_INT);
            $rekap_data_raw = getGuruRecapByMonth($selected_academic_year_str, $selected_semester);
            
            foreach ($rekap_data_raw as $guru_id => $months_data) {
                foreach ($months_data as $month_data) {
                    $rekap_data_bulanan[] = $month_data;
                }
            }
            // Sort the array by guru name and then by month
            usort($rekap_data_bulanan, function($a, $b) {
                $cmp_guru = strcmp($a['nama_guru'], $b['nama_guru']);
                if ($cmp_guru !== 0) {
                    return $cmp_guru;
                }
                return strtotime($a['month_start_date']) - strtotime($b['month_start_date']);
            });
        }
        
        // Export Excel
        if (isset($_POST['export_excel'])) {
            try {
                $spreadsheet = new Spreadsheet();
                if ($report_type === 'bisyaroh') {
                    // Export Bisyaroh Report (Sheet 1)
                    $sheet = $spreadsheet->getActiveSheet();
                    $sheet->setTitle('Rekap Bisyaroh Guru');
                    
                    $sheet->setCellValue('A1', 'Rekap Bisyaroh Guru Mengajar');
                    $sheet->mergeCells('A1:E1');
                    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $sheet->setCellValue('A2', 'Bulan: ' . $months[$selected_month] . ' Tahun Ajaran: ' . $selected_academic_year_str);
                    $sheet->mergeCells('A2:E2');
                    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
                    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $sheet->setCellValue('A4', 'Nama Guru');
                    $sheet->setCellValue('B4', 'Jumlah Kehadiran');
                    $sheet->setCellValue('C4', 'Total Jam Mengajar');
                    $sheet->setCellValue('D4', 'Total Bisyaroh (Rp)');
                    $sheet->setCellValue('E4', 'Mata Pelajaran Diampu');

                    $headerStyle = [
                        'font' => ['bold' => true],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE0E0E0']],
                    ];
                    $sheet->getStyle('A4:E4')->applyFromArray($headerStyle);
                    
                    $row_num = 5;
                    foreach ($rekap_data as $data) {
                        $sheet->setCellValue('A' . $row_num, htmlspecialchars_decode($data['nama_guru']));
                        $sheet->setCellValue('B' . $row_num, $data['jumlah_kehadiran']);
                        $sheet->setCellValue('C' . $row_num, $data['total_jam_dibayar'] . ' jam');
                        $sheet->setCellValue('D' . $row_num, $data['total_gaji']);
                        $sheet->setCellValue('E' . $row_num, implode(', ', $data['unique_subjects']));
                        $sheet->getStyle('A' . $row_num . ':E' . $row_num)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                        $row_num++;
                    }
                    
                    foreach (range('A', 'E') as $column) {
                        $sheet->getColumnDimension($column)->setAutoSize(true);
                    }
                    $sheet->getStyle('B5:B' . ($row_num - 1))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
                    $sheet->getStyle('D5:D' . ($row_num - 1))->getNumberFormat()->setFormatCode('"Rp "#,##0');

                    // Export Detail Absensi (Sheet 2)
                    $spreadsheet->createSheet();
                    $spreadsheet->setActiveSheetIndex(1);
                    $detail_sheet = $spreadsheet->getActiveSheet();
                    $detail_sheet->setTitle('Detail Absensi');

                    $detail_sheet->setCellValue('A1', 'Detail Absensi Mengajar Guru');
                    $detail_sheet->mergeCells('A1:I1');
                    $detail_sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                    $detail_sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $detail_sheet->setCellValue('A2', 'Bulan: ' . $months[$selected_month] . ' Tahun Ajaran: ' . $selected_academic_year_str);
                    $detail_sheet->mergeCells('A2:I2');
                    $detail_sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
                    $detail_sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    
                    $detail_sheet->setCellValue('A4', 'Nama Guru');
                    $detail_sheet->setCellValue('B4', 'Mata Pelajaran');
                    $detail_sheet->setCellValue('C4', 'Kelas');
                    $detail_sheet->setCellValue('D4', 'Tanggal Ajar');
                    $detail_sheet->setCellValue('E4', 'Waktu Mulai');
                    $detail_sheet->setCellValue('F4', 'Waktu Selesai');
                    $detail_sheet->setCellValue('G4', 'Durasi Aktual (Menit)');
                    $detail_sheet->setCellValue('H4', 'Jam Jadwal');
                    $detail_sheet->setCellValue('I4', 'Status Absen');
                    
                    $detail_sheet->getStyle('A4:I4')->applyFromArray($headerStyle);
                    
                    $row_num_detail = 5;
                    foreach ($detail_data_for_excel as $data) {
                        $detail_sheet->setCellValue('A' . $row_num_detail, htmlspecialchars_decode($data['nama_guru']));
                        $detail_sheet->setCellValue('B' . $row_num_detail, htmlspecialchars_decode($data['nama_mapel']));
                        $detail_sheet->setCellValue('C' . $row_num_detail, htmlspecialchars_decode($data['nama_kelas']));
                        $detail_sheet->setCellValue('D' . $row_num_detail, $data['tanggal_ajar']);
                        $detail_sheet->setCellValue('E' . $row_num_detail, $data['waktu_mulai']);
                        $detail_sheet->setCellValue('F' . $row_num_detail, $data['waktu_selesai']);
                        $detail_sheet->setCellValue('G' . $row_num_detail, $data['durasi_menit']);
                        $detail_sheet->setCellValue('H' . $row_num_detail, $data['jam_jadwal']);
                        $detail_sheet->setCellValue('I' . $row_num_detail, $data['status_absen']);
                        $detail_sheet->getStyle('A' . $row_num_detail . ':I' . $row_num_detail)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                        $row_num_detail++;
                    }
                    foreach (range('A', 'I') as $column) {
                        $detail_sheet->getColumnDimension($column)->setAutoSize(true);
                    }
                    
                    $spreadsheet->setActiveSheetIndex(0);
                    $filename = "rekap_bisyaroh_guru_" . str_replace('/', '-', $selected_academic_year_str) . "_Bulan_" . $months[$selected_month] . ".xlsx";
                    
                } elseif ($report_type === 'gaji_bulanan') {
                    // Export Gaji Bulanan Report
                    $sheet = $spreadsheet->getActiveSheet();
                    $sheet->setTitle('Rekap Gaji Bulanan');

                    $sheet->setCellValue('A1', 'Rekap Gaji Guru Berdasarkan Jadwal Mengajar');
                    $sheet->mergeCells('A1:G1');
                    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $sub_title = "Tahun Ajaran: {$selected_academic_year_str}";
                    if ($selected_semester !== null) {
                        $sub_title .= " Semester: {$semesters[$selected_semester]}";
                    }
                    $sheet->setCellValue('A2', $sub_title);
                    $sheet->mergeCells('A2:G2');
                    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
                    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $header_row = 4;
                    $sheet->setCellValue('A' . $header_row, 'No');
                    $sheet->setCellValue('B' . $header_row, 'Nama Guru');
                    $sheet->setCellValue('C' . $header_row, 'Bulan & Tahun');
                    $sheet->setCellValue('D' . $header_row, 'Jumlah Pertemuan');
                    $sheet->setCellValue('E' . $header_row, 'Total Jam Mengajar');
                    $sheet->setCellValue('F' . $header_row, 'Total Gaji (Rp)');
                    $sheet->setCellValue('G' . $header_row, 'Mata Pelajaran Diampu');

                    $headerStyle = [
                        'font' => ['bold' => true],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE0E0E0']],
                    ];
                    $sheet->getStyle('A' . $header_row . ':G' . $header_row)->applyFromArray($headerStyle);
                    
                    $row_num = 5;
                    $no = 1;
                    foreach ($rekap_data_bulanan as $data) {
                        $sheet->setCellValue('A' . $row_num, $no++);
                        $sheet->setCellValue('B' . $row_num, htmlspecialchars_decode($data['nama_guru']));
                        $sheet->setCellValue('C' . $row_num, $months[date('n', strtotime($data['month_start_date']))] . ' ' . date('Y', strtotime($data['month_start_date'])));
                        $sheet->setCellValue('D' . $row_num, $data['jumlah_pertemuan']);
                        $sheet->setCellValue('E' . $row_num, $data['total_jam_mengajar'] . ' jam');
                        $sheet->setCellValue('F' . $row_num, $data['total_gaji']);
                        $sheet->setCellValue('G' . $row_num, implode(', ', $data['unique_subjects']));
                        $sheet->getStyle('A' . $row_num . ':G' . $row_num)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                        $row_num++;
                    }

                    foreach (range('A', 'G') as $column) {
                        $sheet->getColumnDimension($column)->setAutoSize(true);
                    }
                    $sheet->getStyle('F5:F' . ($row_num - 1))->getNumberFormat()->setFormatCode('"Rp "#,##0');
                    
                    $filename = "rekap_gaji_bulanan_" . str_replace('/', '-', $selected_academic_year_str) . ".xlsx";
                }

                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Cache-Control: max-age=0');
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
                exit();

            } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
                error_log("Error generating XLSX: " . $e->getMessage());
                echo "<script>alert('Terjadi kesalahan saat membuat file Excel: " . addslashes($e->getMessage()) . ".');</script>";
            }
        }
    }
}

// Menyertakan header
require_once __DIR__ . '/../components/header_admin.php';
?>

<style>
/* Gaya CSS tambahan */
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
</style>

<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
    <h1 class="text-3xl font-semibold text-gray-800 mb-4 sm:mb-0">Rekap Gaji dan Bisyaroh Guru</h1>
</div>

<div class="form-container">
    <form method="POST" action="">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label for="report_type" class="block text-sm font-medium text-gray-700 mb-1">Jenis Rekap:</label>
                <select id="report_type" name="report_type" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md shadow-sm" onchange="toggleFormFields()">
                    <option value="bisyaroh" <?= $report_type == 'bisyaroh' ? 'selected' : '' ?>>Rekap Bisyaroh (Kehadiran)</option>
                    <option value="gaji_bulanan" <?= $report_type == 'gaji_bulanan' ? 'selected' : '' ?>>Rekap Gaji Bulanan (Jadwal)</option>
                </select>
            </div>
            <div id="month-field" style="display: <?= $report_type == 'bisyaroh' ? 'block' : 'none' ?>">
                <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Bulan:</label>
                <select id="month" name="month" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md shadow-sm">
                    <?php
                    $selected_month = $_POST['month'] ?? date('n');
                    foreach ($months as $num => $name) {
                        $selected = ($num == $selected_month) ? 'selected' : '';
                        echo "<option value=\"$num\" $selected>$name</option>";
                    }
                    ?>
                </select>
            </div>
            <div id="semester-field" style="display: <?= $report_type == 'gaji_bulanan' ? 'block' : 'none' ?>">
                <label for="semester" class="block text-sm font-medium text-gray-700 mb-1">Semester:</label>
                <select id="semester" name="semester" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md shadow-sm">
                    <option value="">-- Pilih Semua --</option>
                    <?php
                    $selected_semester = $_POST['semester'] ?? null;
                    foreach ($semesters as $num => $name) {
                        $selected = ($num == $selected_semester) ? 'selected' : '';
                        echo "<option value=\"$num\" $selected>$name</option>";
                    }
                    ?>
                </select>
            </div>
            <div>
                <label for="academic_year_id" class="block text-sm font-medium text-gray-700 mb-1">Tahun Ajaran:</label>
                <select id="academic_year_id" name="academic_year_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md shadow-sm">
                    <?php if (!empty($available_academic_years)): ?>
                        <?php foreach ($available_academic_years as $year): ?>
                            <option value="<?php echo htmlspecialchars($year['id']); ?>" <?php echo ($selected_academic_year_id == $year['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($year['tahun_ajaran']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="">Tidak ada tahun ajaran tersedia</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="flex space-x-2">
                <button type="submit" name="generate_report" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-300">
                    <i class="fas fa-search mr-2"></i> Tampilkan Rekap
                </button>
                <?php if ((isset($rekap_data) && !empty($rekap_data)) || (isset($rekap_data_bulanan) && !empty($rekap_data_bulanan))): ?>
                <button type="submit" name="export_excel" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-300">
                    <i class="fas fa-file-excel mr-2"></i> Export Excel
                </button>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<script>
    function toggleFormFields() {
        var reportType = document.getElementById('report_type').value;
        var monthField = document.getElementById('month-field');
        var semesterField = document.getElementById('semester-field');

        if (reportType === 'bisyaroh') {
            monthField.style.display = 'block';
            semesterField.style.display = 'none';
        } else if (reportType === 'gaji_bulanan') {
            monthField.style.display = 'none';
            semesterField.style.display = 'block';
        }
    }
    // Call the function on page load to ensure correct initial state
    window.onload = toggleFormFields;
</script>

<?php if (isset($_POST['generate_report']) || isset($_POST['export_excel'])): ?>
    <div class="table-container mb-8">
        <?php if ($report_type === 'bisyaroh'): ?>
            <h3 class="text-xl font-semibold text-gray-800 mb-4 p-4">Rekap Bisyaroh Guru Mengajar Bulan <?= $months[$selected_month] ?> Tahun Ajaran <?= $selected_academic_year_str ?></h3>
            <p class="text-gray-600 mb-4 p-4">Nominal Bisyaroh per Jam Mengajar: **Rp <?= number_format($nominal_gaji_per_pertemuan, 0, ',', '.') ?>**</p>
            <?php if (!empty($rekap_data)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Nama Guru</th>
                            <th>Jumlah Kehadiran</th>
                            <th>Total Jam Mengajar</th>
                            <th>Total Bisyaroh (Rp)</th>
                            <th>Mata Pelajaran Diampu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rekap_data as $data): ?>
                        <tr>
                            <td><?= htmlspecialchars($data['nama_guru']) ?></td>
                            <td><?= number_format($data['jumlah_kehadiran'], 0) ?></td>
                            <td><?= number_format($data['total_jam_dibayar'], 0) ?> jam</td>
                            <td><?= 'Rp ' . number_format($data['total_gaji'], 0, ',', '.') ?></td>
                            <td><?= htmlspecialchars(implode(', ', $data['unique_subjects'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="p-4 text-gray-600 text-center">Tidak ada data rekap untuk bulan dan tahun ajaran yang dipilih.</p>
            <?php endif; ?>
        <?php elseif ($report_type === 'gaji_bulanan'): ?>
            <h3 class="text-xl font-semibold text-gray-800 mb-4 p-4">Rekap Gaji Bulanan Guru Berdasarkan Jadwal Mengajar</h3>
            <p class="text-gray-600 mb-4 p-4">Nominal Bisyaroh per Jam Mengajar: **Rp <?= number_format($nominal_gaji_per_pertemuan, 0, ',', '.') ?>**</p>
            <?php if (!empty($rekap_data_bulanan)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Guru</th>
                            <th>Bulan & Tahun</th>
                            <th>Jumlah Pertemuan</th>
                            <th>Total Jam Mengajar</th>
                            <th>Total Gaji (Rp)</th>
                            <th>Mata Pelajaran Diampu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        foreach ($rekap_data_bulanan as $data): ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td><?= htmlspecialchars($data['nama_guru']) ?></td>
                            <td><?= $months[date('n', strtotime($data['month_start_date']))] . ' ' . date('Y', strtotime($data['month_start_date'])) ?></td>
                            <td class="text-center"><?= number_format($data['jumlah_pertemuan'], 0) ?></td>
                            <td><?= number_format($data['total_jam_mengajar'], 0) ?> jam</td>
                            <td class="text-right"><?= 'Rp ' . number_format($data['total_gaji'], 0, ',', '.') ?></td>
                            <td><?= htmlspecialchars(implode(', ', $data['unique_subjects'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="p-4 text-gray-600 text-center">Tidak ada data rekap untuk tahun ajaran dan semester yang dipilih.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
// Menyertakan footer
require_once __DIR__ . '/../components/footer.php';
?>