<?php
// project-root/config/app_config.php

// Pastikan sesi dimulai di awal setiap request
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sertakan file konstanta yang berisi kredensial database dan lainnya
require_once __DIR__ . '/constants.php';

// Sertakan kelas Database dan SchoolSettings untuk mengambil konfigurasi dari DB
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/SchoolSettings.php';

// Konfigurasi Aplikasi Umum
define('APP_NAME', 'Sistem Absensi SMA IT AL-BURHANY');
define('APP_VERSION', '1.0.0');

// URL Dasar Aplikasi
// Sesuaikan ini jika Anda menginstal di subdirektori
// Contoh: Jika proyek Anda di 'D:\xampp73\htdocs\absensi-alburhany', maka BASE_URL = '/absensi-alburhany/'
define('BASE_URL', '/'); // Sesuaikan ini dengan nama folder proyek Anda

// Path Root Aplikasi (untuk keperluan require_once)
define('ROOT_PATH', __DIR__ . '/../');

// URL Root untuk aset (CSS, JS, gambar)
define('ASSETS_URL', BASE_URL . 'assets/');

// URL Root untuk redirect (seringkali sama dengan BASE_URL, tapi bisa berbeda jika ada rewrite rules)
define('ROOT_URL', BASE_URL);

// Konfigurasi zona waktu
date_default_timezone_set('Asia/Jakarta'); // Sesuaikan dengan zona waktu lokasi Anda

// Pengaturan Error Reporting (untuk pengembangan)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Ambil Tahun Ajaran dan Semester Aktif dari Database ---
$db_settings = [];
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $school_settings_model = new SchoolSettings($pdo);
    $db_settings = $school_settings_model->getSettings();
} catch (PDOException $e) {
    error_log("Error loading school settings from DB: " . $e->getMessage());
    // Fallback ke nilai default jika ada masalah koneksi/tabel belum ada
    $db_settings = [
        'tahun_ajaran_aktif' => '2024/2025',
        'semester_aktif' => 'Ganjil',
        'nama_sekolah' => 'SMA IT AL-BURHANY',
        'alamat_sekolah' => 'Jl. Raya Contoh No. 123',
        'kota' => 'Bandung',
        'provinsi' => 'Jawa Barat',
        'kode_pos' => '40123',
        'telepon_sekolah' => '(022) 1234567',
        'email_sekolah' => 'info@alburhany.sch.id',
        'website_sekolah' => 'www.alburhany.sch.id',
        'kepala_sekolah' => '-',
        'nip_kepala_sekolah' => '197001011995031001',
        'logo_sekolah_path' => null
    ];
}

// Definisikan konstanta APP_TAHUN_AJARAN_AKTIF dan APP_SEMESTER_AKTIF
define('APP_TAHUN_AJARAN_AKTIF', $db_settings['tahun_ajaran_aktif'] ?? '2024/2025');
define('APP_SEMESTER_AKTIF', $db_settings['semester_aktif'] ?? 'Ganjil');

// Definisikan konstanta lain dari pengaturan sekolah
define('SCHOOL_NAME', $db_settings['nama_sekolah'] ?? 'SMA IT AL-BURHANY');
define('SCHOOL_ADDRESS', $db_settings['alamat_sekolah'] ?? 'Jl. Raya Contoh No. 123');
define('SCHOOL_HEADMASTER', $db_settings['kepala_sekolah'] ?? 'Kepala Sekolah');
define('SCHOOL_NIP_HEADMASTER', $db_settings['nip_kepala_sekolah'] ?? '-');
define('SCHOOL_LOGO_PATH', $db_settings['logo_sekolah_path'] ?? null);

// Define RFID_MACHINE_API_KEY dari pengaturan sekolah
define('RFID_MACHINE_API_KEY', $app_settings['rfid_machine_api_key'] ?? 'DEFAULT_RFID_KEY_JANGAN_GUNAKAN_INI');
// Pastikan untuk mengisi nilai ini di pengaturan sekolah setelah instalasi!

// Anda bisa menambahkan konfigurasi lain di sini
// seperti pengaturan email, API key, dll.
