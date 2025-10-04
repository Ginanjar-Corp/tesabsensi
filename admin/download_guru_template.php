<?php
// project-root/admin/download_guru_template.php

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

// Buat objek Spreadsheet baru
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Definisikan header kolom yang diharapkan
// Headers disesuaikan untuk mencakup kolom baru: Transport Jabatan, Hari Piket, Transport Piket
$headers = [
    'Nama Lengkap',
    'NIP',
    'RFID Tag',
    'Tempat Lahir',
    'Tanggal Lahir (YYYY-MM-DD)',
    'Jenis Kelamin (Laki-laki/Perempuan)',
    'Alamat',
    'No. Telepon',
    'Email',
    'Gaji Per Pertemuan (Angka)',
    'Aktif (Ya/Tidak)',
    'Jabatan',
    'Tunjangan Jabatan (Angka)',
    'Kegiatan Lainnya',
    'Tunjangan Kegiatan Lainnya (Angka)',
    // --- NEW HEADERS ADDED ---
    'Transport Jabatan (Angka)', // Kolom baru
    'Hari Piket (cth: Senin, Kamis)', // Kolom baru
    'Transport Piket (Angka)', // Kolom baru
    'Tunjangan Sertifikasi (Angka)' // Kolom baru
    // -------------------------
];

// Tulis header ke baris pertama
$sheet->fromArray($headers, NULL, 'A1');

// Opsional: Atur lebar kolom agar lebih mudah dibaca
foreach (range('A', $sheet->getHighestColumn()) as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Opsional: Tambahkan beberapa gaya untuk header
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['argb' => 'FF000000'], // Hitam
    ],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FFE0E0E0'], // Abu-abu terang
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


// Nama file yang akan diunduh
$filename = 'template_import_guru_' . date('Ymd_His') . '.xlsx';

// Set header HTTP untuk mengunduh file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Buat objek Writer dan simpan Spreadsheet ke output
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;