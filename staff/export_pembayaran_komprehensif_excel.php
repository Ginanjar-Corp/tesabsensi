<?php
// project-root/admin/export_pembayaran_komprehensif_excel.php

require_once __DIR__ . '/../vendor/autoload.php'; // Pastikan Composer autoload ada
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Siswa.php'; // Mungkin tidak langsung digunakan tapi baik untuk include
require_once __DIR__ . '/../classes/TahunAjaran.php';
require_once __DIR__ . '/../classes/Kelas.php';
require_once __DIR__ . '/../classes/JenisPembayaran.php';
require_once __DIR__ . '/../classes/TagihanSiswa.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

require_login();
require_role(['Admin', 'TataUsaha', 'Guru']);

global $pdo;
$tagihan_siswa_model = new TagihanSiswa($pdo);
$tahun_ajaran_model = new TahunAjaran($pdo);
$kelas_model = new Kelas($pdo);
$jenis_pembayaran_model = new JenisPembayaran($pdo);

$filters = [];
$jenis_pembayaran_terpilih = null;
$report_type = 'bebas'; // Default ke laporan bebas

// Ambil filter dari GET request
$selected_tahun_ajaran_id = filter_input(INPUT_GET, 'tahun_ajaran_id', FILTER_VALIDATE_INT);
$selected_kelas_id = filter_input(INPUT_GET, 'kelas_id', FILTER_VALIDATE_INT);
$selected_jenis_pembayaran_id = filter_input(INPUT_GET, 'jenis_pembayaran_id', FILTER_VALIDATE_INT);
$start_date = filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_STRING);
$end_date = filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_STRING);


// Set filter untuk dikirim ke model
if ($selected_tahun_ajaran_id) {
    $filters['tahun_ajaran_id'] = $selected_tahun_ajaran_id;
    $tahun_ajaran_info = $tahun_ajaran_model->getById($selected_tahun_ajaran_id);
    $tahun_ajaran_label = $tahun_ajaran_info ? $tahun_ajaran_info['tahun_ajaran'] : 'Tidak Diketahui';
} else {
    $tahun_ajaran_label = 'Semua Tahun Ajaran';
}

if ($selected_kelas_id) {
    $filters['kelas_id'] = $selected_kelas_id;
    $kelas_info = $kelas_model->findById($selected_kelas_id);
    $kelas_label = $kelas_info ? $kelas_info['nama_kelas'] : 'Tidak Diketahui';
} else {
    $kelas_label = 'Semua Kelas';
}

if ($selected_jenis_pembayaran_id) {
    $filters['jenis_pembayaran_id'] = $selected_jenis_pembayaran_id;
    $jenis_pembayaran_info = $jenis_pembayaran_model->getById($selected_jenis_pembayaran_id);
    $jenis_pembayaran_label = $jenis_pembayaran_info ? $jenis_pembayaran_info['nama_pembayaran'] : 'Tidak Diketahui';
    if ($jenis_pembayaran_info && $jenis_pembayaran_info['tipe_pembayaran'] === 'Rutin Bulanan') {
        $report_type = 'bulanan';
        unset($filters['start_date']);
        unset($filters['end_date']);
    } else {
        if ($start_date) {
            $filters['start_date'] = $start_date;
        }
        if ($end_date) {
            $filters['end_date'] = $end_date;
        }
    }
} else {
    $report_type = 'bebas';
    if ($start_date) {
        $filters['start_date'] = $start_date;
    }
    if ($end_date) {
        $filters['end_date'] = $end_date;
    }
    $jenis_pembayaran_label = 'Semua Jenis Pembayaran'; // Untuk kasus ini, karena tipe tidak spesifik, label ini
}

// Default filter: hanya pembayaran yang Lunas (sudah berhasil) untuk laporan bebas
if ($report_type === 'bebas') {
    $filters['status_pembayaran_detail'] = 'Lunas';
    $filtered_payments = $tagihan_siswa_model->getPaymentsForReport($filters);
} else { // report_type === 'bulanan'
    $filtered_payments = $tagihan_siswa_model->getMonthlyPaymentsForReport($filters);
}


// Membuat objek Spreadsheet baru
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Laporan Pembayaran');

// Header Laporan
$sheet->setCellValue('A1', 'Laporan Pembayaran Siswa Komprehensif');
$sheet->mergeCells('A1:L1'); // Akan disesuaikan nanti
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A3', 'Tahun Ajaran: ' . $tahun_ajaran_label);
$sheet->setCellValue('A4', 'Kelas: ' . $kelas_label);
$sheet->setCellValue('A5', 'Jenis Pembayaran: ' . $jenis_pembayaran_label);

// Hanya tampilkan filter tanggal untuk laporan bebas
if ($report_type === 'bebas') {
    $sheet->setCellValue('A6', 'Filter Tanggal Bayar: ' . ($start_date ? format_tanggal_indo($start_date) : 'Semua') . ' - ' . ($end_date ? format_tanggal_indo($end_date) : 'Semua'));
    $sheet->setCellValue('A7', 'Tanggal Cetak: ' . format_tanggal_indo(date('Y-m-d')));
    $start_data_row = 9; // Baris awal data untuk laporan bebas
} else {
    $sheet->setCellValue('A6', 'Tanggal Cetak: ' . format_tanggal_indo(date('Y-m-d')));
    $start_data_row = 8; // Baris awal data untuk laporan bulanan
}


// Daftar bulan untuk laporan bulanan
$months = [
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni'
];

if ($report_type === 'bulanan') {
    // Header Kolom Tabel untuk Laporan Bulanan
    $header_columns_bulanan = [
        'NISN', 'Nama Siswa', 'Kelas',
    ];
    foreach ($months as $month) {
        $header_columns_bulanan[] = $month . ' (Jumlah)';
        $header_columns_bulanan[] = $month . ' (Tanggal)';
    }

    $col = 'A';
    foreach ($header_columns_bulanan as $header) {
        $sheet->setCellValue($col . ($start_data_row -1), $header);
        $col++;
    }

    // Styling header kolom bulanan
    $sheet->getStyle('A' . ($start_data_row -1) . ':' . Coordinate::stringFromColumnIndex(count($header_columns_bulanan)) . ($start_data_row -1))->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']], // Warna biru
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);

    // Merge cell header laporan utama jika perlu
    $sheet->mergeCells('A1:' . Coordinate::stringFromColumnIndex(count($header_columns_bulanan)) . '1');


    // Data rows untuk Laporan Bulanan
    $row = $start_data_row;
    foreach ($filtered_payments as $siswa_data) {
        $sheet->setCellValue('A' . $row, $siswa_data['nisn']);
        $sheet->setCellValue('B' . $row, $siswa_data['nama_lengkap']);
        $sheet->setCellValue('C' . $row, $siswa_data['nama_kelas']);

        $col_idx = 3; // Dimulai dari kolom D
        foreach ($months as $month) {
            $jumlah_col = Coordinate::stringFromColumnIndex($col_idx + 1);
            $tanggal_col = Coordinate::stringFromColumnIndex($col_idx + 2);

            if (isset($siswa_data['bulan'][$month]) && $siswa_data['bulan'][$month]['jumlah_dibayar'] > 0) {
                $sheet->setCellValue($jumlah_col . $row, $siswa_data['bulan'][$month]['jumlah_dibayar']);
                $sheet->setCellValue($tanggal_col . $row, format_tanggal_indo($siswa_data['bulan'][$month]['tanggal_bayar_terakhir']));
                // Apply number format to amount
                $sheet->getStyle($jumlah_col . $row)->getNumberFormat()->setFormatCode('#,##0');
            } else {
                $sheet->setCellValue($jumlah_col . $row, '-');
                $sheet->setCellValue($tanggal_col . $row, '-');
            }
            $col_idx += 2;
        }
        $row++;
    }

    // Auto-size kolom dan styling border untuk semua data bulanan
    for ($i = 1; $i <= count($header_columns_bulanan); $i++) { $col = Coordinate::stringFromColumnIndex($i);
        $sheet->getColumnDimension($col)->setAutoSize(true);
        $sheet->getStyle($col . $start_data_row . ':' . $col . ($row - 1))->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
    }

} else { // report_type === 'bebas'
    // Header Kolom Tabel untuk Laporan Bebas (Mirip sebelumnya)
    $header_columns_bebas = [
        'NISN', 'Nama Siswa', 'Kelas', 'Tahun Ajaran', 'Jenis Pembayaran',
        'Periode Tagihan', 'Jumlah Tagihan', 'Jumlah Dibayar', 'Sisa Tagihan',
        'Tanggal Bayar', 'Metode Pembayaran', 'Petugas Pencatat', 'Status Pembayaran'
    ];
    $col = 'A';
    foreach ($header_columns_bebas as $header) {
        $sheet->setCellValue($col . ($start_data_row - 1), $header);
        $col++;
    }

    // Styling header kolom bebas
    $sheet->getStyle('A' . ($start_data_row - 1) . ':' . Coordinate::stringFromColumnIndex(count($header_columns_bebas)) . ($start_data_row - 1))->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']], // Warna biru
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);

    // Merge cell header laporan utama jika perlu
    $sheet->mergeCells('A1:' . Coordinate::stringFromColumnIndex(count($header_columns_bebas)) . '1');


    // Data rows untuk Laporan Bebas
    $row = $start_data_row;
    $total_jumlah_tagihan_sum = 0;
    $total_jumlah_dibayar_sum = 0;

    foreach ($filtered_payments as $payment) {
        $sisa_tagihan = $payment['jumlah_tagihan'] - $payment['jumlah_bayar'];
        $sheet->setCellValue('A' . $row, $payment['nisn']);
        $sheet->setCellValue('B' . $row, $payment['nama_lengkap']);
        $sheet->setCellValue('C' . $row, $payment['nama_kelas']);
        $sheet->setCellValue('D' . $row, $payment['tahun_ajaran']);
        $sheet->setCellValue('E' . $row, $payment['nama_pembayaran']);
        $sheet->setCellValue('F' . $row, $payment['periode_tagihan']);
        $sheet->setCellValue('G' . $row, $payment['jumlah_tagihan']);
        $sheet->setCellValue('H' . $row, $payment['jumlah_bayar']);
        $sheet->setCellValue('I' . $row, $sisa_tagihan);
        $sheet->setCellValue('J' . $row, format_tanggal_indo($payment['tanggal_bayar']));
        $sheet->setCellValue('K' . $row, $payment['metode_pembayaran']);
        $sheet->setCellValue('L' . $row, $payment['petugas_pencatat'] ?? '-');
        $sheet->setCellValue('M' . $row, $payment['status_pembayaran_detail']);

        // Styling untuk kolom jumlah tagihan, dibayar, dan sisa tagihan
        $sheet->getStyle('G' . $row . ':I' . $row)->getNumberFormat()->setFormatCode('#,##0');

        $total_jumlah_tagihan_sum += $payment['jumlah_tagihan'];
        $total_jumlah_dibayar_sum += $payment['jumlah_bayar'];

        $row++;
    }

    // Baris Total (Opsional) untuk Laporan Bebas
    if (!empty($filtered_payments)) {
        $sheet->setCellValue('F' . $row, 'TOTAL:');
        $sheet->setCellValue('G' . $row, $total_jumlah_tagihan_sum);
        $sheet->setCellValue('H' . $row, $total_jumlah_dibayar_sum);
        $sheet->setCellValue('I' . $row, $total_jumlah_tagihan_sum - $total_jumlah_dibayar_sum); // Total sisa
        $sheet->getStyle('F' . $row . ':I' . $row)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DCE6F1']], // Warna abu-abu muda
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
        $sheet->getStyle('G' . $row . ':I' . $row)->getNumberFormat()->setFormatCode('#,##0');
    }

    // Auto-size kolom dan styling border untuk semua data bebas
    for ($i = 1; $i <= count($header_columns_bebas); $i++) { $col = Coordinate::stringFromColumnIndex($i);
        $sheet->getColumnDimension($col)->setAutoSize(true);
        $sheet->getStyle($col . $start_data_row . ':' . $col . ($row - 1))->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
    }
    // Apply border to total row as well
    if (!empty($filtered_payments)) {
        for ($i = 1; $i <= count($header_columns_bebas); $i++) { $col = Coordinate::stringFromColumnIndex($i);
            $sheet->getStyle($col . $row)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
            ]);
        }
    }
}


// Mengatur header HTTP untuk unduhan file Excel
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Laporan_Pembayaran_Siswa_Komprehensif_' . date('Ymd_His') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
