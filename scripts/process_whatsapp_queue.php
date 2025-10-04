<?php
// project-root/scripts/process_whatsapp_queue.php
// Skrip ini akan dijalankan sebagai cron job untuk memproses antrean notifikasi WhatsApp

// Sertakan file konfigurasi dan kelas yang diperlukan
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/NotificationQueue.php';
require_once __DIR__ . '/../includes/whatsapp_notifier.php'; // Fungsi pengirim WhatsApp yang sudah ada
require_once __DIR__ . '/../classes/SchoolSettings.php'; // Tambahkan ini untuk mengambil pengaturan sekolah

// Log mulai proses
error_log("Memulai pemrosesan antrean WhatsApp pada " . date('Y-m-d H:i:s'));

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $notification_queue_model = new NotificationQueue($pdo);
    $school_settings_model = new SchoolSettings($pdo); // Inisialisasi model SchoolSettings
    $settings = $school_settings_model->getSettings(); // Ambil pengaturan sekali di awal

    // Mulai transaksi untuk mengunci baris yang akan diproses
    $pdo->beginTransaction();

    // Ambil tugas yang tertunda (misal: 5 tugas sekaligus untuk menghindari proses terlalu lama)
    $pending_notifications = $notification_queue_model->getPendingNotifications(5);

    if (empty($pending_notifications)) {
        error_log("Tidak ada notifikasi WhatsApp yang tertunda ditemukan.");
        $pdo->commit(); // Commit transaksi meskipun tidak ada yang diproses
    } else {
        foreach ($pending_notifications as $notification_job) {
            $job_id = $notification_job['id'];
            $recipient_phone = $notification_job['recipient_phone_number'];
            $template_name = $notification_job['template_name'];
            $template_params = json_decode($notification_job['template_parameters_json'], true);

            error_log("Memproses tugas WhatsApp ID: {$job_id} untuk {$recipient_phone}");

            // Perbaikan: Panggil fungsi pengirim WhatsApp dengan PDO dan settings sebagai argumen pertama
            $whatsapp_send_result = sendWhatsAppNotification(
                $pdo,               // Argumen 1: PDO object
                $settings,          // Argumen 2: Settings array
                $recipient_phone,
                $template_name,
                $template_params
            );

            if ($whatsapp_send_result['status'] === 'success') {
                $notification_queue_model->updateNotificationStatus($job_id, 'sent');
                error_log("Tugas WhatsApp ID: {$job_id} berhasil dikirim.");
            } else {
                $error_message = $whatsapp_send_result['message'];
                $notification_queue_model->updateNotificationStatus($job_id, 'failed', $error_message);
                error_log("Tugas WhatsApp ID: {$job_id} gagal: " . $error_message);
            }
            // Tambahkan jeda di sini, misalnya 10 detik
            sleep(10); // Menunda eksekusi selama 10 detik
        }
        $pdo->commit(); // Commit transaksi setelah semua tugas dalam batch selesai diproses
    }

} catch (Exception $e) {
    // Rollback transaksi jika terjadi kesalahan
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Kesalahan selama pemrosesan antrean WhatsApp: " . $e->getMessage());
}

// Log selesai proses
error_log("Selesai pemrosesan antrean WhatsApp pada " . date('Y-m-d H:i:s'));
?>
