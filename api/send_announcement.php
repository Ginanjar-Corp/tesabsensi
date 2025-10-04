<?php
// project-root/api/send_announcement.php

// Pastikan hanya bisa diakses dari aplikasi Anda atau melalui metode yang aman (misal: token API)
// Untuk tujuan demo, saya tidak menambahkan otentikasi ketat, tapi ini PENTING di produksi.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Pengumuman.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/NotificationQueue.php';

// Atur header agar response jelas
header('Content-Type: application/json');

// Pastikan request adalah POST dan memiliki pengumuman_id
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['pengumuman_id'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit();
}

$pengumuman_id = filter_input(INPUT_POST, 'pengumuman_id', FILTER_VALIDATE_INT);

if (!$pengumuman_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid announcement ID.']);
    exit();
}

global $pdo;
$db = Database::getInstance();
$pdo = $db->getConnection();

$pengumuman_model = new Pengumuman($pdo);
$siswa_model = new Siswa($pdo);
$notification_queue = new NotificationQueue($pdo);

$pengumuman_data = $pengumuman_model->getById($pengumuman_id);

if (!$pengumuman_data) {
    http_response_code(404); // Not Found
    echo json_encode(['status' => 'error', 'message' => 'Announcement not found.']);
    exit();
}

// Ambil semua siswa untuk mendapatkan nomor telepon orang tua
// Anda mungkin ingin menambahkan filter (misal: berdasarkan kelas, status aktif)
$all_siswa = $siswa_model->getAll(); // Ambil semua siswa aktif atau yang relevan
$recipients_count = 0;
$failed_to_queue_count = 0;

$judul_pengumuman = $pengumuman_data['judul'];
$isi_pengumuman = $pengumuman_data['isi_pengumuman'];
$gambar_url = $pengumuman_data['gambar_url']; // Ambil URL gambar

foreach ($all_siswa as $siswa) {
    if (!empty($siswa['no_telp_ortu'])) {
        $recipientPhoneNumber = format_phone_number($siswa['no_telp_ortu']); // Pastikan format nomor telepon benar
        $templateName = 'pengumuman'; // Nama template untuk pengumuman
        $templateParameters = [
            $judul_pengumuman,
            $isi_pengumuman,
            // $siswa['nama_lengkap'] // Tambahan parameter jika template memerlukan nama siswa
        ];
        
        // Panggil addNotificationToQueue dengan mediaUrl
        $queue_result = $notification_queue->addNotificationToQueue($recipientPhoneNumber, $templateName, $templateParameters, $gambar_url);

        if ($queue_result['status'] === 'success') {
            $recipients_count++;
        } else {
            $failed_to_queue_count++;
            error_log("Failed to queue announcement for " . $siswa['nama_lengkap'] . ": " . $queue_result['message']);
        }
    }
}

// Update status pengumuman di database setelah semua berhasil di-queue
if ($recipients_count > 0 && $failed_to_queue_count == 0) {
    $pengumuman_model->updateStatus($pengumuman_id, 'terkirim'); 
} else if ($recipients_count == 0 && $failed_to_queue_count > 0) {
    $pengumuman_model->updateStatus($pengumuman_id, 'gagal');
} else if ($recipients_count > 0 && $failed_to_queue_count > 0) {
     $pengumuman_model->updateStatus($pengumuman_id, 'sebagian_terkirim'); // Anda bisa menambah status ini di enum tabel
} else {
     $pengumuman_model->updateStatus($pengumuman_id, 'gagal'); // Tidak ada penerima yang valid
}


http_response_code(200);
echo json_encode([
    'status' => 'success',
    'message' => 'Pengumuman berhasil diantrekan untuk pengiriman.',
    'queued_recipients' => $recipients_count,
    'failed_to_queue' => $failed_to_queue_count
]);
exit();
