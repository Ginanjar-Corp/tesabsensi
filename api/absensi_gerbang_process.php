<?php
// project-root/api/absensi_gerbang_process.php

// Pastikan ini adalah permintaan POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak diizinkan.']);
    exit();
}

// Sertakan file konfigurasi dan kelas yang diperlukan
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php'; // Diperlukan untuk getActiveTahunAjaran()
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Siswa.php'; // Perlu kelas Siswa untuk mencari berdasarkan RFID
require_once __DIR__ . '/../classes/Guru.php';    // Perlu kelas Guru untuk mencari berdasarkan RFID
require_once __DIR__ . '/../classes/AbsensiHarian.php';
require_once __DIR__ . '/../classes/SchoolSettings.php'; // Tambahkan ini untuk mengambil pengaturan sekolah
// require_once __DIR__ . '/../includes/whatsapp_notifier.php'; // Hapus atau komentari ini, tidak lagi dipanggil langsung
require_once __DIR__ . '/../classes/NotificationQueue.php'; // Tambahkan kelas NotificationQueue

header('Content-Type: application/json'); // Atur header respons ke JSON

$response = ['status' => 'error', 'message' => 'Terjadi kesalahan tidak dikenal.'];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $rfid_tag = trim($_POST['rfid_tag'] ?? '');
    $tipe_absensi = $_POST['tipe_absensi'] ?? ''; // 'masuk' atau 'pulang'
    $jenis_pengguna = $_POST['jenis_pengguna'] ?? ''; // 'siswa' atau 'guru'

    if (empty($rfid_tag) || empty($tipe_absensi) || empty($jenis_pengguna)) {
        $response = ['status' => 'error', 'message' => 'RFID/QR Tag, tipe absensi, dan jenis pengguna tidak boleh kosong.'];
        echo json_encode($response);
        exit();
    }

    $siswa_model = new Siswa($pdo);
    $guru_model = new Guru($pdo);
    $absensi_harian_model = new AbsensiHarian($pdo);
    $notification_queue_model = new NotificationQueue($pdo); // Inisialisasi model antrean

    $user_info = null;
    $found_user_type = null;

    // Ambil tahun ajaran aktif dan semester aktif
    $active_ta_data = getActiveTahunAjaran(); // Dari helpers.php
    $current_tahun_ajaran = $active_ta_data['tahun_ajaran'] ?? null;
    $current_semester = APP_SEMESTER_AKTIF; // Dari app_config.php

    if (!$current_tahun_ajaran) {
        $response = ['status' => 'error', 'message' => 'Tahun Ajaran aktif belum disetel. Harap hubungi administrator.'];
        echo json_encode($response);
        exit();
    }

    // Coba cari di tabel siswa jika jenis pengguna adalah 'siswa' atau tidak spesifik
    if ($jenis_pengguna === 'siswa') {
        $siswa = $siswa_model->findByRfidTag($rfid_tag);
        if ($siswa) {
            $user_info = $siswa;
            $found_user_type = 'siswa';
        }
    }
    
    // Coba cari di tabel guru jika jenis pengguna adalah 'guru' atau tidak spesifik dan belum ditemukan siswa
    if ($jenis_pengguna === 'guru' || ($jenis_pengguna === 'siswa' && !$user_info)) {
        $guru = $guru_model->findByRfidTag($rfid_tag);
        if ($guru) {
            $user_info = $guru;
            $found_user_type = 'guru';
        }
    }

    if ($user_info) {
        if ($found_user_type === 'siswa') {
            $siswa_kelas_id = $user_info['kelas_id'] ?? null; // Ambil kelas_id dari info siswa

            // Panggil metode absensi siswa dengan tipe absensi yang spesifik
            // Teruskan tahun_ajaran, semester, dan kelas_id ke metode ini
            $absensi_result = $absensi_harian_model->recordSiswaAttendanceWithType(
                $user_info['id'],
                $tipe_absensi,
                $current_tahun_ajaran,
                $current_semester,
                $siswa_kelas_id
            );
            $response = array_merge($absensi_result, [
                'user_type' => 'siswa',
                'nama' => $user_info['nama_lengkap'],
                'kelas' => $user_info['nama_kelas'] ?? 'N/A', // Asumsi nama_kelas diambil dari join di Siswa.php
                'waktu' => date('H:i:s')
            ]);
            
            // --- Tambahkan Notifikasi WhatsApp untuk Siswa ke Antrean ---
            if ($absensi_result['status'] === 'success') {
                $phone_number_to_notify = $user_info['no_telp_ortu'] ?? null; // Nomor telepon orang tua siswa
                $nama_pengguna_notifikasi = $user_info['nama_lengkap'];
                $waktu_absensi = date('H:i');
                $tipe_absensi_teks = ($tipe_absensi === 'masuk') ? 'masuk' : 'pulang';
                $status_kehadiran = $absensi_result['data']['status'] ?? 'N/A'; // Ambil status dari hasil absensi

                if ($phone_number_to_notify) {
                    $phone_number_clean = ltrim($phone_number_to_notify, '+');
                    $template_name = 'absensi_notifikasi'; // Nama template WhatsApp Anda
                    $template_params = [$nama_pengguna_notifikasi, $tipe_absensi_teks, $waktu_absensi, $status_kehadiran];

                    $queue_result = $notification_queue_model->addNotificationToQueue(
                        $phone_number_clean,
                        $template_name,
                        $template_params
                    );
                    if ($queue_result['status'] === 'success') {
                        error_log("Notifikasi untuk siswa " . $nama_pengguna_notifikasi . " ditambahkan ke antrean.");
                    } else {
                        error_log("Gagal menambahkan notifikasi untuk siswa " . $nama_pengguna_notifikasi . " ke antrean: " . $queue_result['message']);
                        $response['queue_error_siswa'] = $queue_result['message'];
                    }
                } else {
                    error_log("Nomor telepon orang tua tidak ditemukan untuk notifikasi WhatsApp bagi siswa " . $nama_pengguna_notifikasi . ".");
                }
            }
            // --- Akhir Penambahan Notifikasi WhatsApp Siswa ke Antrean ---
            
        } elseif ($found_user_type === 'guru') {
            // Panggil metode absensi guru dengan tipe absensi yang spesifik
            // Teruskan tahun_ajaran dan semester ke metode ini
            $absensi_result = $absensi_harian_model->recordGuruAttendanceWithType(
                $user_info['id'],
                $tipe_absensi,
                $current_tahun_ajaran,
                $current_semester
            );
            $response = array_merge($absensi_result, [
                'user_type' => 'guru',
                'nama' => $user_info['nama_lengkap'],
                'waktu' => date('H:i:s')
            ]);
            
            // --- Tambahkan Notifikasi WhatsApp untuk Guru (ke Guru itu sendiri) ke Antrean ---
            if ($absensi_result['status'] === 'success') {
                $guru_phone_number = $user_info['no_telp'] ?? null; // Asumsi kolom 'no_telp' ada di tabel guru
                $nama_pengguna_notifikasi = $user_info['nama_lengkap'];
                $waktu_absensi = date('H:i');
                $tipe_absensi_teks = ($tipe_absensi === 'masuk') ? 'masuk' : 'pulang';
                $status_kehadiran = $absensi_result['data']['status'] ?? 'N/A';

                if ($guru_phone_number) {
                    $phone_number_clean = ltrim($guru_phone_number, '+');
                    $template_name = 'absensi_notifikasi_guru'; // Template untuk notifikasi guru
                    $template_params = [$nama_pengguna_notifikasi, $tipe_absensi_teks, $waktu_absensi, $status_kehadiran];

                    $queue_result_guru = $notification_queue_model->addNotificationToQueue(
                        $phone_number_clean,
                        $template_name,
                        $template_params
                    );
                    if ($queue_result_guru['status'] === 'success') {
                        error_log("Notifikasi untuk guru " . $nama_pengguna_notifikasi . " ditambahkan ke antrean.");
                    } else {
                        error_log("Gagal menambahkan notifikasi untuk guru " . $nama_pengguna_notifikasi . " ke antrean: " . $queue_result_guru['message']);
                        $response['queue_error_guru_self'] = $queue_result_guru['message'];
                    }
                } else {
                    error_log("Nomor telepon guru tidak ditemukan untuk notifikasi WhatsApp bagi guru " . $nama_pengguna_notifikasi . ".");
                }

                // --- Tambahkan Notifikasi WhatsApp untuk Guru (ke Kepala Sekolah) ke Antrean ---
                $settings = (new SchoolSettings($pdo))->getSettings(); // Inisialisasi ulang untuk mendapatkan pengaturan terbaru
                $kepala_sekolah_phone = $settings['telepon_sekolah'] ?? null; 
                
                if ($kepala_sekolah_phone && $kepala_sekolah_phone !== $guru_phone_number) { // Hindari duplikasi jika nomornya sama
                    $phone_number_clean_ks = ltrim($kepala_sekolah_phone, '+');
                    $template_name_ks = 'absensi_notifikasi_guru_kepsek'; // Anda mungkin perlu membuat template khusus untuk kepala sekolah
                    $template_params_ks = [$nama_pengguna_notifikasi, $tipe_absensi_teks, $waktu_absensi, $status_kehadiran];

                    $queue_result_ks = $notification_queue_model->addNotificationToQueue(
                        $phone_number_clean_ks,
                        $template_name_ks,
                        $template_params_ks
                    );
                    if ($queue_result_ks['status'] === 'success') {
                        error_log("Notifikasi untuk guru " . $nama_pengguna_notifikasi . " ke Kepala Sekolah ditambahkan ke antrean.");
                    } else {
                        error_log("Gagal menambahkan notifikasi untuk guru " . $nama_pengguna_notifikasi . " ke Kepala Sekolah ke antrean: " . $queue_result_ks['message']);
                        $response['queue_error_guru_kepsek'] = $queue_result_ks['message'];
                    }
                } elseif ($kepala_sekolah_phone && $kepala_sekolah_phone === $guru_phone_number) {
                    error_log("Nomor telepon Kepala Sekolah sama dengan nomor guru, notifikasi ke Kepala Sekolah diabaikan untuk menghindari duplikasi.");
                } else {
                    error_log("Nomor telepon Kepala Sekolah tidak ditemukan di pengaturan untuk notifikasi WhatsApp guru.");
                }
            }
            // --- Akhir Penambahan Notifikasi WhatsApp Guru ke Antrean ---
            
        }
    } else {
        $response = ['status' => 'error', 'message' => 'RFID/QR Tag tidak terdaftar atau jenis pengguna tidak cocok.'];
    }

} catch (PDOException $e) {
    error_log("API Absensi Gerbang Error: " . $e->getMessage());
    $response = ['status' => 'error', 'message' => 'Terjadi kesalahan database: ' . $e->getMessage()];
} catch (Exception $e) {
    error_log("API Absensi Gerbang General Error: " . $e->getMessage());
    $response = ['status' => 'error', 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()];
}

echo json_encode($response);
