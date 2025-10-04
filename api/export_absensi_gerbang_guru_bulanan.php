<?php
// project-root/api/export_absensi_gerbang_guru_bulanan.php

// Sertakan file konfigurasi dan kelas yang diperlukan
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/AbsensiGerbang.php';
require_once __DIR__ . '/../classes/Guru.php';
require_once __DIR__ . '/../classes/KalenderPendidikan.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $absensi_gerbang_model = new AbsensiGerbang($pdo);
    $guru_model = new Guru($pdo);
    $kalender_model = new KalenderPendidikan($pdo);

    // --- 1. Ambil parameter filter dari GET request ---
    $selected_month = $_GET['month'] ?? date('m');
    $selected_year = $_GET['year'] ?? date('Y');
    $filter_tahun_ajaran = $_GET['tahun_ajaran'] ?? null;
    $filter_semester = $_GET['semester'] ?? null;

    // --- 2. Dapatkan informasi tambahan untuk header laporan ---
    $school_name = APP_NAME ?? 'Nama Sekolah';
    $month_name = date('F', mktime(0, 0, 0, $selected_month, 10));

    // --- 3. Generate daftar tanggal yang harus dimasukkan ke laporan (tidak termasuk hari Jumat & hari libur) ---
    $num_days_in_month = cal_days_in_month(CAL_GREGORIAN, $selected_month, $selected_year);
    $dates_in_month_to_report = [];
    $libur_dates = $kalender_model->getLiburInMonth($selected_month, $selected_year, $filter_tahun_ajaran, $filter_semester);
    $libur_dates_array = array_column($libur_dates, 'tanggal');

    for ($i = 1; $i <= $num_days_in_month; $i++) {
        $current_date_obj = new DateTime("{$selected_year}-{$selected_month}-{$i}");
        $current_date_str = $current_date_obj->format('Y-m-d');
        $day_of_week = $current_date_obj->format('N'); // 1 = Senin, 5 = Jumat, 7 = Minggu

        // Periksa apakah hari ini BUKAN hari Jumat (5) dan BUKAN hari libur yang tercatat
        if ($day_of_week != 5 && !in_array($current_date_str, $libur_dates_array)) {
            $dates_in_month_to_report[] = $current_date_str;
        }
    }

    // --- 4. Ambil semua guru yang relevan ---
    $all_teachers = $guru_model->getAll();

    // --- 5. Ambil semua catatan absensi guru untuk bulan, tahun ajaran, dan semester yang difilter ---
    $raw_attendance_data = $absensi_gerbang_model->getMonthlyGuruGateAttendanceReport(
        $selected_month,
        $selected_year,
        $filter_tahun_ajaran,
        $filter_semester
    );

    // --- 6. Pivot data absensi per guru dan per tanggal ---
    $pivoted_data = [];
    foreach ($all_teachers as $teacher) {
        $pivoted_data[$teacher['id']] = [
            'nip' => $teacher['nip'],
            'nama_lengkap' => $teacher['nama_lengkap'],
            'daily_records' => array_fill_keys($dates_in_month_to_report, ['status_masuk' => 'Alpha']),
            'counts' => ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0]
        ];
    }
    
    foreach ($raw_attendance_data as $record) {
        $guru_id = $record['guru_id'];
        $tanggal = $record['tanggal'];
        if (isset($pivoted_data[$guru_id]) && in_array($tanggal, $dates_in_month_to_report)) {
            $status = $record['status_masuk'] ?? 'Alpha';
            $pivoted_data[$guru_id]['daily_records'][$tanggal] = [
                'status_masuk' => $status
            ];
            // Hitung total kehadiran per guru
            if ($status === 'Hadir' || $status === 'Terlambat') {
                $pivoted_data[$guru_id]['counts']['H']++;
            } elseif ($status === 'Sakit') {
                $pivoted_data[$guru_id]['counts']['S']++;
            } elseif ($status === 'Izin') {
                $pivoted_data[$guru_id]['counts']['I']++;
            } elseif ($status === 'Alpha') {
                $pivoted_data[$guru_id]['counts']['A']++;
            }
        }
    }

    // --- 7. Buat file Excel ---
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Absensi Guru Bulanan');

    // Gaya untuk Header Utama
    $mainHeaderStyle = [
        'font' => ['bold' => true, 'size' => 14],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ];
    // Gaya untuk Sub Header
    $subHeaderStyle = [
        'font' => ['bold' => true, 'size' => 12],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ];
    // Gaya untuk Kolom Header
    $columnHeaderStyle = [
        'font' => ['bold' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE0E0E0']],
    ];
    // Gaya untuk Sel Data
    $dataCellStyle = [
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
    ];
    // Gaya untuk baris total
    $totalRowStyle = [
        'font' => ['bold' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFC0C0C0']],
    ];
    // Gaya khusus untuk hari libur (merah)
    $liburStyle = [
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFCCCC']],
        'font' => ['bold' => true, 'color' => ['argb' => 'FFCC0000']]
    ];

    // Hitung kolom terakhir secara dinamis
    $total_columns = 3 + count($dates_in_month_to_report) + 4; // Tambahan 4 kolom untuk total H, S, I, A
    $end_col_letter = Coordinate::stringFromColumnIndex($total_columns);

    // Baris 1: Nama Sekolah
    $sheet->setCellValue('A1', 'Nama Sekolah: ' . $school_name);
    $sheet->mergeCells('A1:' . $end_col_letter . '1');
    $sheet->getStyle('A1')->applyFromArray($mainHeaderStyle);

    // Baris 2: Judul Laporan
    $sheet->setCellValue('A2', 'Laporan Absensi Gerbang Guru Bulanan');
    $sheet->mergeCells('A2:' . $end_col_letter . '2');
    $sheet->getStyle('A2')->applyFromArray($mainHeaderStyle);

    // Baris 3: Detail Bulan, Tahun, Semester, Tahun Ajaran
    $sheet->setCellValue('A3', 'Bulan: ' . format_date_indonesian("{$selected_year}-{$selected_month}-01") . ' | Semester: ' . ($filter_semester ?? 'Semua') . ' | Tahun Ajaran: ' . ($filter_tahun_ajaran ?? 'Semua'));
    $sheet->mergeCells('A3:' . $end_col_letter . '3');
    $sheet->getStyle('A3')->applyFromArray($subHeaderStyle);

    // Baris 4: Header Kolom
    $column_headers = ['No.', 'NIP', 'Nama Guru'];
    foreach ($dates_in_month_to_report as $date) {
        $column_headers[] = date('d', strtotime($date));
    }
    $column_headers[] = 'Total H';
    $column_headers[] = 'Total S';
    $column_headers[] = 'Total I';
    $column_headers[] = 'Total A';
    $sheet->fromArray($column_headers, NULL, 'A4');
    $sheet->getStyle('A4:' . $sheet->getHighestColumn() . '4')->applyFromArray($columnHeaderStyle);
    $sheet->getRowDimension(4)->setRowHeight(30);

    // Isi Data Guru
    $row = 5;
    $grand_totals = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];
    $no = 1;
    foreach ($pivoted_data as $guru_id => $data) {
        $current_col = 'A';
        $sheet->setCellValue($current_col++ . $row, $no++);
        $sheet->setCellValue($current_col++ . $row, htmlspecialchars($data['nip'] ?? '-'));
        $sheet->setCellValue($current_col++ . $row, htmlspecialchars($data['nama_lengkap']));

        foreach ($dates_in_month_to_report as $date_key) {
            $status_masuk = $data['daily_records'][$date_key]['status_masuk'] ?? 'Alpha';
            $display_status = '-';
            if ($status_masuk == 'Hadir' || $status_masuk == 'Terlambat') {
                $display_status = 'H';
            } elseif ($status_masuk == 'Sakit') {
                $display_status = 'S';
            } elseif ($status_masuk == 'Izin') {
                $display_status = 'I';
            } elseif ($status_masuk == 'Alpha') {
                $display_status = 'A';
            }
            $sheet->setCellValue($current_col++ . $row, $display_status);
        }
        
        // Tambahkan kolom total per guru
        $sheet->setCellValue($current_col++ . $row, $data['counts']['H']);
        $sheet->setCellValue($current_col++ . $row, $data['counts']['S']);
        $sheet->setCellValue($current_col++ . $row, $data['counts']['I']);
        $sheet->setCellValue($current_col++ . $row, $data['counts']['A']);

        // Tambahkan total per guru ke grand total
        $grand_totals['H'] += $data['counts']['H'];
        $grand_totals['S'] += $data['counts']['S'];
        $grand_totals['I'] += $data['counts']['I'];
        $grand_totals['A'] += $data['counts']['A'];

        $sheet->getStyle('A' . $row . ':' . $sheet->getHighestColumn() . $row)->applyFromArray($dataCellStyle);
        $row++;
    }

    // Tambahkan baris grand total
    $start_col_summary = Coordinate::stringFromColumnIndex(count($dates_in_month_to_report) + 4);
    $sheet->setCellValue($start_col_summary . $row, 'Total');
    $sheet->mergeCells('A' . $row . ':' . Coordinate::stringFromColumnIndex(count($dates_in_month_to_report) + 3) . $row);
    
    $current_col = $start_col_summary;
    $sheet->setCellValue($current_col++ . $row, $grand_totals['H']);
    $sheet->setCellValue($current_col++ . $row, $grand_totals['S']);
    $sheet->setCellValue($current_col++ . $row, $grand_totals['I']);
    $sheet->setCellValue($current_col++ . $row, $grand_totals['A']);
    $sheet->getStyle('A' . $row . ':' . $sheet->getHighestColumn() . $row)->applyFromArray($totalRowStyle);

    // Atur lebar kolom otomatis
    foreach (range('A', $sheet->getHighestColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // --- 8. Siapkan untuk download ---
    $writer = new Xlsx($spreadsheet);
    $filename = 'Laporan_Absensi_Gerbang_Guru_Bulanan_' . $selected_year . '_' . $selected_month;
    if ($filter_tahun_ajaran) {
        $filename .= '_TA_' . str_replace('/', '-', $filter_tahun_ajaran);
    }
    if ($filter_semester) {
        $filename .= '_Sem_' . $filter_semester;
    }
    $filename .= '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $writer->save('php://output');
    exit();

} catch (PDOException $e) {
    error_log("Export Absensi Gerbang Guru Monthly Error: " . $e->getMessage());
    echo "Terjadi kesalahan database saat membuat laporan: " . $e->getMessage();
} catch (Exception $e) {
    error_log("Export Absensi Gerbang Guru Monthly General Error: " . $e->getMessage());
    echo "Terjadi kesalahan sistem saat membuat laporan: " . $e->getMessage();
}