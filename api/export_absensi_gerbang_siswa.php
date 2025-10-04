<?php
// project-root/api/export_absensi_gerbang_siswa.php

// Sertakan file konfigurasi dan kelas yang diperlukan
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/AbsensiGerbang.php';
require_once __DIR__ . '/../classes/Kelas.php'; // Diperlukan untuk mendapatkan nama kelas
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $absensi_gerbang_model = new AbsensiGerbang($pdo);
    $kelas_model = new Kelas($pdo);

    // --- 1. Ambil parameter filter dari GET request ---
    $selected_date = $_GET['date'] ?? date('Y-m-d');
    $filter_tahun_ajaran = $_GET['tahun_ajaran'] ?? null;
    $filter_semester = $_GET['semester'] ?? null;
    $filter_kelas_id = $_GET['kelas_id'] ?? null;

    // --- 2. Panggil metode pengambilan data dengan semua filter ---
    $report_data = $absensi_gerbang_model->getDailyStudentGateAttendanceReport(
        $selected_date,
        $filter_tahun_ajaran,
        $filter_semester,
        $filter_kelas_id
    );

    // --- 3. Buat file Excel ---
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Laporan Absensi Gerbang Harian');

    // Header Kolom
    $headers = ['No.', 'NISN', 'Nama Siswa', 'Kelas', 'Jam Masuk', 'Jam Pulang', 'Status Masuk', 'Status Pulang'];
    $sheet->fromArray($headers, NULL, 'A1');

    // Isi Data
    $row = 2;
    $no = 1;
    foreach ($report_data as $data) {
        $sheet->setCellValue('A' . $row, $no++);
        $sheet->setCellValue('B' . $row, htmlspecialchars($data['nisn'] ?? '-'));
        $sheet->setCellValue('C' . $row, htmlspecialchars($data['nama_lengkap']));
        $sheet->setCellValue('D' . $row, htmlspecialchars($data['nama_kelas']));
        $sheet->setCellValue('E' . $row, htmlspecialchars($data['jam_masuk'] ? substr($data['jam_masuk'], 0, 5) : '-'));
        $sheet->setCellValue('F' . $row, htmlspecialchars($data['jam_pulang'] ? substr($data['jam_pulang'], 0, 5) : '-'));
        $sheet->setCellValue('G' . $row, htmlspecialchars($data['status_masuk']));
        $sheet->setCellValue('H' . $row, htmlspecialchars($data['status_pulang']));
        $row++;
    }

    // Atur lebar kolom otomatis
    foreach (range('A', $sheet->getHighestColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // --- 4. Siapkan untuk download ---
    $writer = new Xlsx($spreadsheet);
    $filename = 'Laporan_Absensi_Gerbang_Siswa_Harian_' . $selected_date;
    if ($filter_kelas_id) {
        $kelas_info = $kelas_model->findById($filter_kelas_id);
        if ($kelas_info) {
            $filename .= '_Kelas_' . str_replace(['/', ' '], ['-', '_'], $kelas_info['nama_kelas']);
        }
    }
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
    error_log("Export Absensi Gerbang Daily Error: " . $e->getMessage());
    echo "Terjadi kesalahan database saat membuat laporan: " . $e->getMessage();
} catch (Exception $e) {
    error_log("Export Absensi Gerbang Daily General Error: " . $e->getMessage());
    echo "Terjadi kesalahan sistem saat membuat laporan: " . $e->getMessage();
}
