<?php
// project-root/api/update_waktu_selesai_ajar.php

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
require_once __DIR__ . '/../classes/AbsensiMapelGuru.php';

header('Content-Type: application/json'); // Atur header respons ke JSON

$response = ['status' => 'error', 'message' => 'Terjadi kesalahan tidak dikenal.'];

// Pastikan pengguna sudah login dan memiliki peran Guru
if (!is_logged_in() || !check_role('Guru')) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Anda harus login sebagai Guru.']);
    exit();
}

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $absensi_mapel_guru_model = new AbsensiMapelGuru($pdo);

    $jadwal_id = (int)($_POST['jadwal_id'] ?? 0);
    $guru_id = (int)($_POST['guru_id'] ?? 0);
    $tanggal_ajar = $_POST['tanggal_ajar'] ?? date('Y-m-d'); // Tanggal dari frontend

    // Validasi dasar
    if (empty($jadwal_id) || empty($guru_id) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_ajar)) {
        $response = ['status' => 'error', 'message' => 'Data yang dikirim tidak lengkap atau tidak valid.'];
        echo json_encode($response);
        exit();
    }

    // Pastikan guru_id yang dikirim sesuai dengan guru yang sedang login
    if ($guru_id !== ($_SESSION['related_id'] ?? 0)) {
        http_response_code(403); // Forbidden
        echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. ID Guru tidak cocok.']);
        exit();
    }

    $waktu_selesai_ajar = date('H:i:s'); // Waktu saat ini

    // Panggil metode untuk memperbarui waktu selesai ajar
    if ($absensi_mapel_guru_model->updateTeachingEndTime($guru_id, $jadwal_id, $tanggal_ajar, $waktu_selesai_ajar)) {
        $response = ['status' => 'success', 'message' => 'Waktu selesai mengajar berhasil dicatat.', 'waktu_selesai_ajar' => $waktu_selesai_ajar];
    } else {
        $response = ['status' => 'error', 'message' => 'Gagal mencatat waktu selesai mengajar.'];
    }

} catch (PDOException $e) {
    error_log("API Update Waktu Selesai Ajar Error: " . $e->getMessage());
    $response = ['status' => 'error', 'message' => 'Terjadi kesalahan database.'];
} catch (Exception $e) {
    error_log("API Update Waktu Selesai Ajar General Error: " . $e->getMessage());
    $response = ['status' => 'error', 'message' => 'Terjadi kesalahan sistem.'];
}

echo json_encode($response);
