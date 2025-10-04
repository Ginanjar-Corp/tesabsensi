<?php
// project-root/api/export_absensi_mapel_siswa_harian.php

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

    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    $kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : null;
    $mapel_id = isset($_GET['mapel_id']) ? (int)$_GET['mapel_id'] : null;
    $guru_id = isset($_GET['guru_id']) ? (int)$_GET['guru_id'] : null;

    // Validasi format tanggal
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        set_flash('error', 'Format tanggal tidak valid.');
        header('Location: ' . ROOT_URL . 'admin/laporan_absensi_mapel_siswa.php');
        exit;
    }

    $report_data = $absensi_mapel_siswa_model->getStudentSubjectAttendanceReport(
        $start_date,
        $end_date,
        $kelas_id,
        $mapel_id,
        $guru_id
    );

    if (empty($report_data)) {
        set_flash('error', 'Tidak ada data absensi mata pelajaran siswa untuk filter yang dipilih.');
        header('Location: ' . ROOT_URL . 'admin/laporan_absensi_mapel_siswa.php');
        exit;
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Absensi Mapel Harian');

    // Title and sub-title
    $sheet->setCellValue('A1', 'Laporan Absensi Mata Pelajaran Siswa Harian');
    $sheet->mergeCells('A1:J1'); // Adjust based on max columns
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->setCellValue('A2', 'Periode: ' . format_date_indonesian($start_date) . ' - ' . format_date_indonesian($end_date));
    $sheet->mergeCells('A2:J2'); // Adjust based on max columns
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Headers
    $headers = [
        'No.', 'Tanggal', 'Waktu', 'Kelas', 'Mata Pelajaran', 'Guru',
        'NISN', 'Nama Siswa', 'Status', 'Catatan'
    ];
    $sheet->fromArray($headers, NULL, 'A4');

    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4F81BD']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ];
    $sheet->getStyle('A4:J4')->applyFromArray($headerStyle);

    // Data rows
    $row_index = 5;
    $no = 1;
    foreach ($report_data as $data) {
        $status_text = '';
        switch ($data['status_kehadiran']) {
            case 'H': $status_text = 'Hadir'; break;
            case 'S': $status_text = 'Sakit'; break;
            case 'I': $status_text = 'Izin'; break;
            case 'A': $status_text = 'Alpha'; break;
            default: $status_text = 'Tidak Diketahui'; break;
        }

        $rowData = [
            $no++,
            format_date_indonesian($data['tanggal_absen']),
            substr($data['waktu_absen'], 0, 5),
            $data['nama_kelas'],
            $data['nama_mapel'],
            $data['nama_guru'],
            $data['nisn'] ?? '-',
            $data['nama_siswa'],
            $status_text,
            $data['catatan'] ?? '-'
        ];
        $sheet->fromArray($rowData, NULL, 'A' . $row_index);

        $dataStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ];
        $sheet->getStyle('A' . $row_index . ':J' . $row_index)->applyFromArray($dataStyle);
        $sheet->getStyle('A' . $row_index . ':C' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Center align for No, Tanggal, Waktu
        $sheet->getStyle('I' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Center align for Status

        $row_index++;
    }

    // Auto-size columns
    foreach (range('A', $sheet->getHighestColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Set headers for download
    $filename = 'Laporan_Absensi_Mapel_Siswa_Harian_' . $start_date . '_to_' . $end_date . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (PDOException $e) {
    error_log("Export Absensi Mapel Siswa Harian Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database saat ekspor: ' . $e->getMessage());
    header('Location: ' . ROOT_URL . 'admin/laporan_absensi_mapel_siswa.php');
    exit;
} catch (Exception $e) {
    error_log("Export Absensi Mapel Siswa Harian General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem saat ekspor: ' . $e->getMessage());
    header('Location: ' . ROOT_URL . 'admin/laporan_absensi_mapel_siswa.php');
    exit;
}
?>
