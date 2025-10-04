<?php
// project-root/api/guru_check_in.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/SchoolSettings.php';
require_once __DIR__ . '/../classes/Absensi.php';
require_once __DIR__ . '/../classes/NotificationQueue.php'; // Tambahkan ini
require_once __DIR__ . '/../classes/Guru.php';    // Tambahkan ini

// Fungsi untuk menghitung jarak antara dua koordinat (haversine formula)
function haversineGreatCircleDistance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo) {
    $earthRadius = 6371000;
    $latFrom = deg2rad($latitudeFrom);
    $lonFrom = deg2rad($longitudeFrom);
    $latTo = deg2rad($latitudeTo);
    $lonTo = deg2rad($longitudeTo);

    $latDelta = $latTo - $latFrom;
    $lonDelta = $lonTo - $lonFrom;

    $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
        cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
    return $angle * $earthRadius;
}

// Pastikan request adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan.']);
    exit;
}

$scanned_token = $_POST['qr_token'] ?? null;
$user_lat = $_POST['user_lat'] ?? null;
$user_long = $_POST['user_long'] ?? null;

// Validasi input
if (empty($scanned_token) || !isset($user_lat) || !isset($user_long)) {
    echo json_encode(['success' => false, 'message' => 'Data absensi tidak lengkap.']);
    exit;
}

// Inisialisasi koneksi database dan model
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $school_settings_model = new SchoolSettings($pdo);
    $absensi_model = new Absensi($pdo);
    $guru_model = new Guru($pdo);
    $notification_queue_model = new NotificationQueue($pdo);

    // Ambil token hash dan data lokasi dari database
    $settings = $school_settings_model->getSettings();
    $stored_token_hash = $settings['qr_token_hash'] ?? null;
    $school_lat = $settings['lokasi_sekolah_lat'] ?? null;
    $school_long = $settings['lokasi_sekolah_long'] ?? null;
    $radius = $settings['radius_absensi_meter'] ?? 0;

    if (empty($stored_token_hash)) {
        echo json_encode(['success' => false, 'message' => 'Pengaturan QR Code belum ada atau tidak lengkap.']);
        exit;
    }

    // Validasi token yang lebih kuat: bandingkan token yang discan dengan hash di database
    if (!password_verify($scanned_token, $stored_token_hash)) {
        echo json_encode(['success' => false, 'message' => 'QR Code tidak valid atau sudah kadaluarsa.']);
        exit;
    }

    // Cek apakah guru berada dalam radius yang diizinkan
    if ($school_lat && $school_long) {
        $distance = haversineGreatCircleDistance($school_lat, $school_long, $user_lat, $user_long);
        if ($distance > $radius) {
            echo json_encode(['success' => false, 'message' => 'Anda berada di luar area sekolah yang diizinkan. Jarak Anda ' . round($distance) . ' meter.']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Pengaturan lokasi sekolah belum diatur.']);
        exit;
    }

    // Cek apakah guru sudah login
    $guru_id = $_SESSION['related_id'] ?? null;
    if (!$guru_id) {
        echo json_encode(['success' => false, 'message' => 'Autentikasi gagal.']);
        exit;
    }

    // Proses absensi menggunakan metode dari kelas Absensi
    $absen_result = $absensi_model->absenMasukGuru($guru_id);
    
    // --- Tambahkan logika notifikasi di sini ---
    if ($absen_result['success']) {
        // Ambil data guru untuk notifikasi
        $guru_data = $guru_model->findById($guru_id);
        
        $tipe_absensi_teks = 'masuk';
        $status_kehadiran = $absen_result['status'] ?? 'N/A'; // Ambil status dari hasil absenMasukGuru()
        $waktu_absensi = date('H:i');
        $nama_guru = $guru_data['nama_lengkap'] ?? 'Guru';
        $guru_phone_number = $guru_data['no_telp'] ?? null;

        // Notifikasi ke guru itu sendiri
        if ($guru_phone_number) {
            $phone_number_clean = ltrim($guru_phone_number, '+');
            $template_name = 'absensi_notifikasi_guru'; // Template notifikasi WhatsApp untuk guru
            $template_params = [$nama_guru, $tipe_absensi_teks, $waktu_absensi, $status_kehadiran];

            $queue_result = $notification_queue_model->addNotificationToQueue(
                $phone_number_clean,
                $template_name,
                $template_params
            );
            if ($queue_result['status'] === 'success') {
                error_log("Notifikasi untuk guru " . $nama_guru . " ditambahkan ke antrean.");
            } else {
                error_log("Gagal menambahkan notifikasi untuk guru " . $nama_guru . " ke antrean: " . $queue_result['message']);
            }
        } else {
            error_log("Nomor telepon guru tidak ditemukan untuk notifikasi WhatsApp bagi guru " . $nama_guru . ".");
        }

        // Notifikasi ke Kepala Sekolah (nomor sekolah)
        $kepala_sekolah_phone = $settings['telepon_sekolah'] ?? null;
        if ($kepala_sekolah_phone && $kepala_sekolah_phone !== $guru_phone_number) {
            $phone_number_clean_ks = ltrim($kepala_sekolah_phone, '+');
            $template_name_ks = 'absensi_notifikasi_guru_kepsek'; // Template untuk Kepala Sekolah
            $template_params_ks = [$nama_guru, $tipe_absensi_teks, $waktu_absensi, $status_kehadiran];

            $queue_result_ks = $notification_queue_model->addNotificationToQueue(
                $phone_number_clean_ks,
                $template_name_ks,
                $template_params_ks
            );
            if ($queue_result_ks['status'] === 'success') {
                error_log("Notifikasi untuk guru " . $nama_guru . " ke Kepala Sekolah ditambahkan ke antrean.");
            } else {
                error_log("Gagal menambahkan notifikasi untuk guru " . $nama_guru . " ke Kepala Sekolah ke antrean: " . $queue_result_ks['message']);
            }
        }
    }
    // --- Akhir logika notifikasi ---

    echo json_encode($absen_result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan internal: ' . $e->getMessage()]);
    error_log('Error in guru_check_in.php: ' . $e->getMessage());
}