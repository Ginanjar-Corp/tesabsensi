<?php
// project-root/api/record_rfid_attendance.php

// URL API ini akan diakses oleh mesin RFID di:
// https://www.absensi.codene.my.id/api/record_rfid_attendance.php

// Pastikan error reporting diatur dengan benar di lingkungan produksi
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Set header untuk memberitahu klien bahwa respons adalah JSON
header('Content-Type: application/json');

// Izinkan akses dari domain tertentu (jika perlu, untuk CORS)
// Jika mesin RFID adalah perangkat keras, ini mungkin tidak terlalu krusial
// header('Access-Control-Allow-Origin: *'); // HATI-HATI: Jangan gunakan '*' di produksi tanpa alasan kuat
// header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
// header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Hanya izinkan metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak diizinkan.']);
    exit();
}

// Sertakan file konfigurasi dan kelas yang diperlukan
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php'; // Untuk RFID_MACHINE_API_KEY
require_once __DIR__ . '/../includes/helpers.php'; // Jika ada helper yang dibutuhkan
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/Guru.php';
require_once __DIR__ . '/../classes/AbsensiGerbang.php'; // Kelas yang menangani logika absensi

$response = ['status' => 'error', 'message' => 'Terjadi kesalahan yang tidak diketahui.'];

try {
    // Ambil data dari body request JSON (disarankan untuk API)
    $input = json_decode(file_get_contents('php://input'), true);

    $rfid_tag = $input['rfid_tag'] ?? null;
    $api_key = $input['api_key'] ?? null;
    // Anda bisa juga mengirimkan timestamp dari perangkat jika perangkat memiliki RTC yang akurat
    // $device_timestamp = $input['timestamp'] ?? null;

    // Validasi kunci API
    if (empty($api_key) || $api_key !== RFID_MACHINE_API_KEY) {
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'error', 'message' => 'Kunci API tidak valid.']);
        exit();
    }

    // Validasi RFID Tag
    if (empty($rfid_tag)) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'RFID Tag tidak boleh kosong.']);
        exit();
    }

    // Inisialisasi koneksi database dan model
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $siswa_model = new Siswa($pdo);
    $guru_model = new Guru($pdo);
    $absensi_gerbang_model = new AbsensiGerbang($pdo);

    $entity_id = null;
    $entity_name = '';
    $entity_type = ''; // 'siswa' atau 'guru'

    // Coba temukan RFID Tag di tabel siswa
    $siswa_data = $siswa_model->findByRfidTag($rfid_tag);

    if ($siswa_data) {
        $entity_id = $siswa_data['id'];
        $entity_name = $siswa_data['nama_lengkap'];
        $entity_type = 'siswa';
        // Pastikan siswa aktif
        if ($siswa_data['status_siswa'] != 'Aktif') {
            http_response_code(403); // Forbidden
            echo json_encode(['status' => 'error', 'message' => 'Siswa ' . htmlspecialchars($entity_name) . ' berstatus "' . htmlspecialchars($siswa_data['status_siswa']) . '". Absensi tidak diizinkan.']);
            exit();
        }
    } else {
        // Jika tidak ditemukan di siswa, coba temukan di tabel guru
        $guru_data = $guru_model->findByRfidTag($rfid_tag);
        if ($guru_data) {
            $entity_id = $guru_data['id'];
            $entity_name = $guru_data['nama_lengkap'];
            $entity_type = 'guru';
            // Asumsi guru selalu aktif untuk absensi gerbang, atau tambahkan kolom is_active di tabel guru
            if (isset($guru_data['is_active']) && $guru_data['is_active'] == 0) {
                 http_response_code(403); // Forbidden
                 echo json_encode(['status' => 'error', 'message' => 'Guru ' . htmlspecialchars($entity_name) . ' tidak aktif. Absensi tidak diizinkan.']);
                 exit();
            }
        }
    }

    if (!$entity_id) {
        http_response_code(404); // Not Found
        echo json_encode(['status' => 'error', 'message' => 'RFID Tag tidak terdaftar untuk siswa atau guru mana pun.']);
        exit();
    }

    // Gunakan waktu server untuk absensi (lebih andal daripada waktu perangkat)
    $tanggal_absensi = date('Y-m-d');
    $waktu_absensi = date('H:i:s');

    // ID pengguna yang merekam absensi (bisa ID user khusus 'sistem' atau admin default)
    // Anda perlu menentukan ID ini, misalnya ID user 'admin' atau 'system'
    // Untuk contoh, saya akan menggunakan ID 1 (asumsi ID admin default)
    // Atau Anda bisa membuat user khusus 'Mesin RFID' di tabel users Anda
    $recorded_by_user_id = 1; // GANTI DENGAN ID USER YANG SESUAI UNTUK MESIN RFID

    // Panggil fungsi recordAbsence dari kelas AbsensiGerbang
    // Fungsi recordAbsence sudah menangani logika masuk/pulang
    $absensi_result = $absensi_gerbang_model->recordAbsence($rfid_tag, 'masuk', $entity_type, $recorded_by_user_id);
    // Kita passing 'masuk' sebagai tipe awal, biarkan recordAbsence yang menentukan apakah itu masuk atau pulang

    if ($absensi_result['status'] === 'success' || $absensi_result['status'] === 'warning') {
        http_response_code(200); // OK
        $response = [
            'status' => 'success',
            'message' => $absensi_result['message'],
            'data' => [
                'nama' => $entity_name,
                'tipe_absensi' => $absensi_result['data']['tipe'], // 'masuk' atau 'pulang'
                'jam_absensi' => $absensi_result['data']['jam'] // Waktu yang tercatat
            ]
        ];
    } else {
        http_response_code(500); // Internal Server Error atau 400 Bad Request tergantung jenis error
        $response = ['status' => 'error', 'message' => $absensi_result['message']];
    }

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    error_log("RFID Attendance API Error: " . $e->getMessage());
    $response = ['status' => 'error', 'message' => 'Terjadi kesalahan server.'];
}

echo json_encode($response);
exit();
