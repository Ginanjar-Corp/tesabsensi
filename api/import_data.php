<?php
// project-root/api/import_data.php

// Pastikan ini adalah permintaan POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak diizinkan.']);
    exit();
}

// Sertakan file konfigurasi dan kelas yang diperlukan
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/Kelas.php';

// --- PHPSpreadsheet Autoloader ---
// Anda perlu mengunduh PhpSpreadsheet dan menempatkannya di direktori yang dapat diakses.
// Jika Anda menggunakan Composer, ini akan otomatis. Jika tidak, sesuaikan path ini.
// Contoh jika Anda mengunduh ZIP dan mengekstraknya di project-root/vendor/phpoffice/phpspreadsheet
// require_once __DIR__ . '/../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Autoloader.php';
// PhpOffice\PhpSpreadsheet\Autoloader::register();

// Jika Anda mengunduh melalui Composer, autoloader Composer akan berada di sini:
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date; // Untuk mengonversi tanggal Excel

// Pastikan pengguna sudah login dan memiliki peran Admin atau Tata Usaha
if (!is_logged_in() || !check_role(['Admin', 'TataUsaha'])) {
    http_response_code(403); // Forbidden
    set_flash('error', 'Akses ditolak. Anda harus login sebagai Admin atau Tata Usaha.');
    redirect(ROOT_URL . 'admin/import_siswa.php');
}

$response = ['status' => 'error', 'message' => 'Terjadi kesalahan tidak dikenal.', 'results' => []];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $siswa_model = new Siswa($pdo);
    $kelas_model = new Kelas($pdo);

    $data_type = $_POST['data_type'] ?? ''; // 'siswa' atau 'guru'

    if ($data_type !== 'siswa') {
        set_flash('error', 'Tipe data import tidak didukung.');
        redirect(ROOT_URL . 'admin/import_siswa.php');
    }

    if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        set_flash('error', 'Gagal mengunggah file. Kode Error: ' . $_FILES['excel_file']['error']);
        redirect(ROOT_URL . 'admin/import_siswa.php');
    }

    $file_tmp_name = $_FILES['excel_file']['tmp_name'];
    $file_ext = pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION);

    // Validasi ekstensi file
    $allowed_extensions = ['xlsx', 'xls'];
    if (!in_array(strtolower($file_ext), $allowed_extensions)) {
        set_flash('error', 'Format file tidak didukung. Hanya file .xlsx atau .xls yang diizinkan.');
        redirect(ROOT_URL . 'admin/import_siswa.php');
    }

    // Load spreadsheet
    $spreadsheet = IOFactory::load($file_tmp_name);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestRow();
    $highestColumn = $sheet->getHighestColumn();

    // Ambil header kolom (baris pertama)
    $header = $sheet->rangeToArray('A1:' . $highestColumn . '1', NULL, TRUE, FALSE)[0];
    $header = array_map('trim', array_map('strtolower', $header)); // Normalize header names

    $expected_headers = [
        'nama_lengkap', 'kelas_nama', 'nisn', 'nis', 'rfid_tag',
        'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'alamat',
        'no_telp_ortu', 'email_ortu', 'is_active'
    ];

    // Basic header validation (check if required headers exist)
    if (!in_array('nama_lengkap', $header) || !in_array('kelas_nama', $header)) {
        set_flash('error', 'File Excel harus memiliki kolom "nama_lengkap" dan "kelas_nama".');
        redirect(ROOT_URL . 'admin/import_siswa.php');
    }

    $data_to_import = [];
    for ($row = 2; $row <= $highestRow; $row++) {
        $rowData = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, NULL, TRUE, FALSE)[0];
        $rowData = array_map(function($value) {
            return is_string($value) ? trim($value) : $value;
        }, $rowData); // Trim all string values

        $rowDataMapped = [];
        foreach ($header as $colIndex => $colName) {
            $rowDataMapped[$colName] = $rowData[$colIndex] ?? null;
        }

        // Convert Excel date to YYYY-MM-DD
        if (isset($rowDataMapped['tanggal_lahir']) && is_numeric($rowDataMapped['tanggal_lahir'])) {
            try {
                $phpDate = Date::excelToDateTimeObject($rowDataMapped['tanggal_lahir']);
                $rowDataMapped['tanggal_lahir'] = $phpDate->format('Y-m-d');
            } catch (Exception $e) {
                $rowDataMapped['tanggal_lahir'] = null; // Invalid date, set to null
            }
        } else {
            // Ensure date is in YYYY-MM-DD format if provided as string
            if (!empty($rowDataMapped['tanggal_lahir']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $rowDataMapped['tanggal_lahir'])) {
                $rowDataMapped['tanggal_lahir'] = null; // Invalid string date format
            }
        }
        
        // Convert is_active to 0 or 1
        if (isset($rowDataMapped['is_active'])) {
            $rowDataMapped['is_active'] = (int)($rowDataMapped['is_active'] == 1 || strtolower($rowDataMapped['is_active']) == 'aktif');
        } else {
            $rowDataMapped['is_active'] = 1; // Default to active
        }

        $data_to_import[] = $rowDataMapped;
    }

    // Get all classes to map class names to IDs
    $all_kelas = $kelas_model->getAll();
    $kelas_map = [];
    foreach ($all_kelas as $kelas) {
        $kelas_map[strtolower($kelas['nama_kelas'])] = $kelas['id'];
    }

    // Process bulk import
    // Perbaikan: Pastikan kelas_nama dari data_to_import juga di-lowercase sebelum dicari di $kelas_map
    foreach ($data_to_import as &$row) { // Use & to modify the array by reference
        if (isset($row['kelas_nama'])) {
            $row['kelas_nama'] = strtolower($row['kelas_nama']);
        }
    }
    unset($row); // Break the reference

    $import_results = $siswa_model->bulkCreateOrUpdate($data_to_import, $kelas_map);

    set_flash('success', 'Proses import data siswa selesai.');
    set_flash('import_results', $import_results); // Simpan hasil import ke sesi
    redirect(ROOT_URL . 'admin/import_siswa.php');

} catch (PhpOffice\PhpSpreadsheet\Exception $e) {
    error_log("PhpSpreadsheet Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan saat membaca file Excel: ' . $e->getMessage());
    redirect(ROOT_URL . 'admin/import_siswa.php');
} catch (PDOException $e) {
    error_log("Import Data Siswa PDO Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database saat import data: ' . $e->getMessage());
    redirect(ROOT_URL . 'admin/import_siswa.php');
} catch (Exception $e) {
    error_log("Import Data Siswa General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem saat import data: ' . $e->getMessage());
    redirect(ROOT_URL . 'admin/import_siswa.php');
}
