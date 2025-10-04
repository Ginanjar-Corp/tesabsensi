<?php
// project-root/api/export_absensi_mapel_guru.php

// Pastikan ini adalah permintaan GET karena ini adalah unduhan file
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    die('Metode request tidak diizinkan.');
}

// Sertakan autoloader Composer untuk PhpSpreadsheet
require_once __DIR__ . '/../vendor/autoload.php';

// Sertakan file konfigurasi dan kelas yang diperlukan
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php'; // Untuk require_login dan require_role
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/AbsensiMapelGuru.php';
require_once __DIR__ . '/../classes/Kelas.php';
require_once __DIR__ . '/../classes/MataPelajaran.php';
require_once __DIR__ . '/../classes/Guru.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Pastikan pengguna sudah login dan memiliki peran Admin atau Tata Usaha
require_login();
require_role(['Admin', 'TataUsaha']);

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $absensi_mapel_guru_model = new AbsensiMapelGuru($pdo);

    // Ambil filter dari parameter GET
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    $selected_kelas_id = (isset($_GET['kelas_id']) && (int)$_GET['kelas_id'] > 0) ? (int)$_GET['kelas_id'] : null;
    $selected_mapel_id = (isset($_GET['mapel_id']) && (int)$_GET['mapel_id'] > 0) ? (int)$_GET['mapel_id'] : null;
    $selected_guru_id = (isset($_GET['guru_id']) && (int)$_GET['guru_id'] > 0) ? (int)$_GET['guru_id'] : null;

    // Validasi format tanggal
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        die('Format tanggal tidak valid.');
    }

    // Ambil data laporan
    $report_data = $absensi_mapel_guru_model->getGuruSubjectTeachingReport(
        $start_date,
        $end_date,
        $selected_guru_id,
        $selected_mapel_id,
        $selected_kelas_id
    );

    // Buat objek Spreadsheet baru
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Laporan Absensi Guru');

    // --- Header Laporan ---
    $sheet->setCellValue('A1', 'Laporan Konfirmasi Pengajaran Guru');
    $sheet->mergeCells('A1:I1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->setCellValue('A2', 'Periode: ' . format_date_indonesian($start_date) . ' - ' . format_date_indonesian($end_date));
    $sheet->mergeCells('A2:I2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A2')->getFont()->setBold(true);

    // --- Header Tabel ---
    $headers = [
        'No.',
        'Tanggal',
        'Guru',
        'Mata Pelajaran',
        'Kelas',
        'Hari Jadwal',
        'Waktu Jadwal',
        'Waktu Mulai Ajar',
        'Waktu Selesai Ajar'
    ];

    $header_row = 4; // Baris untuk header tabel
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . $header_row, $header);
        $col++;
    }

    // Gaya untuk header tabel
    $sheet->getStyle('A' . $header_row . ':' . chr(ord('A') + count($headers) - 1) . $header_row)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE0E0E0']], // Light gray background
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);

    // --- Isi Data Tabel ---
    $row_num = $header_row + 1;
    $no = 1;
    foreach ($report_data as $data) {
        $sheet->setCellValue('A' . $row_num, $no++);
        $sheet->setCellValue('B' . $row_num, format_date_indonesian($data['tanggal_ajar']));
        $sheet->setCellValue('C' . $row_num, $data['nama_guru'] . ' (NIP: ' . ($data['nip'] ?? '-') . ')');
        $sheet->setCellValue('D' . $row_num, $data['nama_mapel']);
        $sheet->setCellValue('E' . $row_num, $data['nama_kelas']);
        $sheet->setCellValue('F' . $row_num, $data['hari']);
        $sheet->setCellValue('G' . $row_num, substr($data['jadwal_mulai'], 0, 5) . ' - ' . substr($data['jadwal_selesai'], 0, 5));
        $sheet->setCellValue('H' . $row_num, $data['waktu_mulai_ajar'] ? substr($data['waktu_mulai_ajar'], 0, 5) : '-');
        $sheet->setCellValue('I' . $row_num, $data['waktu_selesai_ajar'] ? substr($data['waktu_selesai_ajar'], 0, 5) : '-');
        $row_num++;
    }

    // Gaya untuk data tabel
    $sheet->getStyle('A' . ($header_row + 1) . ':' . chr(ord('A') + count($headers) - 1) . ($row_num - 1))->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $sheet->getStyle('A' . ($header_row + 1) . ':A' . ($row_num - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // No.
    $sheet->getStyle('H' . ($header_row + 1) . ':I' . ($row_num - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Waktu Mulai/Selesai

    // Auto size columns
    foreach (range('A', $sheet->getHighestColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Set header untuk unduhan file Excel
    $filename = 'Laporan_Absensi_Guru_' . date('Ymd_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();

} catch (PDOException $e) {
    error_log("Export Absensi Mapel Guru Database Error: " . $e->getMessage());
    die('Terjadi kesalahan database saat membuat laporan: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Export Absensi Mapel Guru General Error: " . $e->getMessage());
    die('Terjadi kesalahan sistem saat membuat laporan: ' . $e->getMessage());
}
