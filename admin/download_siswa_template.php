<?php
// project-root/admin/download_siswa_template.php

// Memastikan file konfigurasi dan helper yang diperlukan sudah di-include
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

// Memastikan pengguna sudah login dan memiliki peran yang sesuai
require_login();
require_role(['Admin', 'TataUsaha']); // Hanya Admin dan Tata Usaha yang bisa akses

// --- PHP SPREADSHEET INTEGRATION START ---
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
// --- PHP SPREADSHEET INTEGRATION END ---

// Create new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Define expected column headers
$headers = [
    'Nama Lengkap',
    'Kelas ID', // Siswa harus dihubungkan dengan ID Kelas yang sudah ada
    'NISN',
    'NIS',
    'RFID/QR Tag',
    'Username', // Tambahkan kolom Username
    'Password', // Tambahkan kolom Password
    'Tempat Lahir',
    'Tanggal Lahir (YYYY-MM-DD)',
    'Jenis Kelamin (L/P)',
    'Alamat',
    'No. Telp Orang Tua',
    'Email Orang Tua',
    'Status Siswa (Aktif/Pindah/Lulus)'
];

// Write headers to the first row
$sheet->fromArray($headers, NULL, 'A1');

// Optional: Set column widths for better readability
foreach (range('A', $sheet->getHighestColumn()) as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Optional: Add some styling for headers
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['argb' => 'FF000000'], // Black
    ],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FFE0E0E0'], // Light gray
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => 'FF000000'],
        ],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
    ],
];
$sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray($headerStyle);


// Filename for download
$filename = 'template_import_siswa_' . date('Ymd_His') . '.xlsx';

// Set HTTP headers for file download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Create Writer object and save Spreadsheet to output
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

