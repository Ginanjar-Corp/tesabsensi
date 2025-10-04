<?php
// project-root/api/absensi_mapel_process.php

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
require_once __DIR__ . '/../classes/AbsensiMapel.php';

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
    $absensi_mapel_model = new AbsensiMapel($pdo);

    $jadwal_id = (int)($_POST['jadwal_id'] ?? 0);
    $guru_id = (int)($_POST['guru_id'] ?? 0);
    $siswa_ids = $_POST['siswa_id'] ?? [];
    $status_kehadiran_array = $_POST['status_kehadiran'] ?? [];
    $catatan_array = $_POST['catatan'] ?? [];

    // Validasi dasar
    if (empty($jadwal_id) || empty($guru_id) || empty($siswa_ids) || !is_array($siswa_ids)) {
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

    $all_success = true;
    foreach ($siswa_ids as $siswa_id) {
        $status = $status_kehadiran_array[$siswa_id] ?? 'A'; // Default Alpha jika tidak ada status
        $catatan = trim($catatan_array[$siswa_id] ?? '');

        // Validasi status kehadiran
        if (!in_array($status, ['H', 'S', 'I', 'A'])) {
            $status = 'A'; // Default ke Alpha jika status tidak valid
        }

        if (!$absensi_mapel_model->recordSiswaAbsence($siswa_id, $jadwal_id, $guru_id, $status, $catatan)) {
            $all_success = false;
            // Log error untuk setiap siswa yang gagal disimpan
            error_log("Gagal mencatat absensi siswa ID: {$siswa_id} untuk jadwal ID: {$jadwal_id}");
        }
    }

    // Catat konfirmasi pengajaran guru
    if ($absensi_mapel_model->recordGuruTeachingConfirmation($guru_id, $jadwal_id)) {
        if ($all_success) {
            $response = ['status' => 'success', 'message' => 'Absensi mata pelajaran berhasil disimpan.'];
        } else {
            $response = ['status' => 'warning', 'message' => 'Absensi disimpan, tetapi ada beberapa siswa yang gagal diproses.'];
        }
    } else {
        $response = ['status' => 'error', 'message' => 'Gagal mencatat konfirmasi pengajaran guru.'];
    }

} catch (PDOException $e) {
    error_log("API Absensi Mapel Error: " . $e->getMessage());
    $response = ['status' => 'error', 'message' => 'Terjadi kesalahan database.'];
} catch (Exception $e) {
    error_log("API Absensi Mapel General Error: " . $e->getMessage());
    $response = ['status' => 'error', 'message' => 'Terjadi kesalahan sistem.'];
}

echo json_encode($response);
