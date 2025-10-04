<?php
// project-root/api/audit_absensi_mapel_process.php

// Ini akan menampilkan semua error PHP yang tersembunyi
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

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
require_once __DIR__ . '/../includes/auth.php'; // Untuk memeriksa login dan peran
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/AbsensiMapelSiswa.php';
require_once __DIR__ . '/../classes/AbsensiMapelGuru.php';
require_once __DIR__ . '/../classes/JadwalPelajaran.php'; // Diperlukan untuk mencari Guru Pengampu

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Terjadi kesalahan tidak dikenal.'];

// Pastikan pengguna sudah login dan memiliki peran Admin atau TataUsaha
if (!is_logged_in() || !check_role(['Admin', 'TataUsaha'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Anda harus login sebagai Admin atau Tata Usaha.']);
    exit();
}

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    $absensi_siswa_model = new AbsensiMapelSiswa($pdo);
    $absensi_guru_model = new AbsensiMapelGuru($pdo);
    $jadwal_model = new JadwalPelajaran($pdo);

    $jadwal_id = (int)($_POST['jadwal_id'] ?? 0);
    $guru_pencatat_id = (int)($_POST['guru_id'] ?? 0); // Ini adalah ID Admin/TU
    $tanggal_ajar = $_POST['tanggal_ajar'] ?? date('Y-m-d'); 
    $attendance_data = $_POST['attendance'] ?? [];

    // Validasi dasar
    if (empty($jadwal_id) || $guru_pencatat_id <= 0 || empty($attendance_data) || !is_array($attendance_data) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_ajar)) {
        $response = ['status' => 'error', 'message' => 'Data yang dikirim tidak lengkap atau tidak valid. (Kode: Mismatch Data Absensi)'];
        echo json_encode($response);
        exit();
    }
    
    // ⭐⭐ PERBAIKAN KRITIS: Ambil ID guru pengampu dan waktu absen SEBELUM loop. ⭐⭐
    $jadwal_data = $jadwal_model->findById($jadwal_id); 
    if (!$jadwal_data || empty($jadwal_data['guru_id'])) {
        throw new Exception("Jadwal atau Guru Pengampu tidak ditemukan.");
    }
    $guru_pengampu_id = (int)$jadwal_data['guru_id'];
    $waktu_absen = date('H:i:s'); // Waktu saat Admin/TU mencatat
    
    $all_success = true;
    
    // 1. Catat Absensi Siswa
    foreach ($attendance_data as $siswa_id => $data) {
        $siswa_id = (int)$siswa_id; 
        $status = $data['status_kehadiran'] ?? 'A';
        $catatan = trim($data['catatan'] ?? '');

        if (!in_array($status, ['H', 'S', 'I', 'A'])) {
            $status = 'A'; 
        }

        // Panggil recordAttendance dengan $guru_pengampu_id yang sudah didefinisikan
        if (!$absensi_siswa_model->recordAttendance($siswa_id, $jadwal_id, $guru_pengampu_id, $tanggal_ajar, $waktu_absen, $status, $catatan)) {
            $all_success = false;
            error_log("Gagal mengaudit absensi siswa ID: {$siswa_id} untuk jadwal ID: {$jadwal_id} tanggal: {$tanggal_ajar}");
        }
    }
    
    // 2. Catat Konfirmasi Pengajaran Guru (Audit)
    if ($absensi_guru_model->recordAuditTeachingConfirmation($guru_pengampu_id, $jadwal_id, $tanggal_ajar, $waktu_absen)) {
        if ($all_success) {
            $response = ['status' => 'success', 'message' => 'Absensi mata pelajaran berhasil diaudit dan disimpan.'];
        } else {
            $response = ['status' => 'warning', 'message' => 'Absensi diaudit dan disimpan, tetapi ada beberapa siswa yang gagal diproses.'];
        }
    } else {
        $response = ['status' => 'error', 'message' => 'Gagal mencatat konfirmasi pengajaran audit.'];
    }

} catch (PDOException $e) {
    error_log("API Audit Absensi Mapel Error: " . $e->getMessage());
    $response = ['status' => 'error', 'message' => 'Terjadi kesalahan database: ' . $e->getMessage()];
} catch (Exception $e) {
    error_log("API Audit Absensi Mapel General Error: " . $e->getMessage());
    $response = ['status' => 'error', 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()];
}

echo json_encode($response);