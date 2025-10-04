<?php
// project-root/api/export_absensi_mapel_siswa_bulanan.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/AbsensiMapelSiswa.php'; // Pastikan kelas ini ada dan benar

require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

require_login();
require_role(['Admin', 'TataUsaha']);

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $absensi_mapel_siswa_model = new AbsensiMapelSiswa($pdo);

    $month = isset($_GET['month']) ? $_GET['month'] : date('m');
    $year = isset($_GET['year']) ? $_GET['year'] : date('Y');
    $kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : null;
    $mapel_id = isset($_GET['mapel_id']) ? (int)$_GET['mapel_id'] : null;
    $guru_id = isset($_GET['guru_id']) ? (int)$_GET['guru_id'] : null;

    // Validasi format bulan dan tahun
    if (!preg_match('/^\d{2}$/', $month) || !preg_match('/^\d{4}$/', $year)) {
        set_flash('error', 'Format bulan atau tahun tidak valid.');
        header('Location: ' . ROOT_URL . 'admin/laporan_absensi_mapel_siswa.php');
        exit;
    }

    // Mengambil data absensi bulanan dari model
    // Metode ini akan mengembalikan rekap per siswa per mata pelajaran per guru per hari dalam bulan
    $monthly_report_data = $absensi_mapel_siswa_model->getMonthlyStudentSubjectAttendanceReport(
        $month,
        $year,
        $kelas_id,
        $mapel_id,
        $guru_id
    );

    if (empty($monthly_report_data)) {
        set_flash('error', 'Tidak ada data absensi mata pelajaran siswa untuk filter yang dipilih pada bulan ' . date('F Y', mktime(0, 0, 0, $month, 1, $year)) . '.');
        header('Location: ' . ROOT_URL . 'admin/laporan_absensi_mapel_siswa.php');
        exit;
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Absensi Mapel Bulanan');

    // Title and sub-title
    $sheet->setCellValue('A1', 'Rekap Absensi Mata Pelajaran Siswa Bulanan');
    // Calculate end column for merging
    $num_days = cal_days_in_month(CAL_GREGORIAN, (int)$month, (int)$year);
    $end_col_index = 6 + $num_days - 1; // Calculate the column index (e.g., 6 for F, 7 for G, etc.)
    $end_col_letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($end_col_index);
    
    // Baris 67 yang dipermasalahkan
    $sheet->mergeCells('A1:' . $end_col_letter . '1'); 
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->setCellValue('A2', 'Bulan: ' . date('F Y', mktime(0, 0, 0, (int)$month, 1, (int)$year)));
    $sheet->mergeCells('A2:' . $end_col_letter . '2');
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Headers
    $headers = ['No.', 'NISN', 'Nama Siswa', 'Kelas', 'Mata Pelajaran', 'Guru'];
    for ($i = 1; $i <= $num_days; $i++) {
        $headers[] = str_pad($i, 2, '0', STR_PAD_LEFT); // Tanggal 01, 02, ...
    }
    $sheet->fromArray($headers, NULL, 'A4');

    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4F81BD']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ];
    $sheet->getStyle('A4:' . $end_col_letter . '4')->applyFromArray($headerStyle);

    // Data rows
    $row_index = 5;
    $no = 1;
    foreach ($monthly_report_data as $data) {
        $rowData = [
            $no++,
            $data['nisn'] ?? '-',
            $data['nama_siswa'],
            $data['nama_kelas'],
            $data['nama_mapel'],
            $data['nama_guru']
        ];
        
        for ($i = 1; $i <= $num_days; $i++) {
            $current_date_key = $year . '-' . $month . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
            // Status 'H', 'S', 'I', 'A'
            $status = $data['daily_statuses'][$current_date_key] ?? '-'; 
            $rowData[] = $status;
        }
        $sheet->fromArray($rowData, NULL, 'A' . $row_index);

        $dataStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ];
        $sheet->getStyle('A' . $row_index . ':' . $end_col_letter . $row_index)->applyFromArray($dataStyle);

        $row_index++;
    }

    // Auto-size columns
    foreach (range('A', $sheet->getHighestColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Set headers for download
    $filename = 'Laporan_Absensi_Mapel_Siswa_Bulanan_' . date('F_Y', mktime(0, 0, 0, (int)$month, 1, (int)$year)) . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (PDOException $e) {
    error_log("Export Absensi Mapel Siswa Bulanan Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database saat ekspor: ' . $e->getMessage());
    header('Location: ' . ROOT_URL . 'admin/laporan_absensi_mapel_siswa.php');
    exit;
} catch (Exception $e) {
    error_log("Export Absensi Mapel Siswa Bulanan General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem saat ekspor: ' . $e->getMessage());
    header('Location: ' . ROOT_URL . 'admin/laporan_absensi_mapel_siswa.php');
    exit;
}
