<?php
// project-root/api/send_bill_reminders.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/TagihanSiswa.php';
require_once __DIR__ . '/../classes/NotificationQueue.php';
require_once __DIR__ . '/../classes/SchoolSettings.php'; // Diperlukan untuk sendWhatsAppNotification

require_login();
require_role(['Admin', 'TataUsaha']);

$siswa_id = $_GET['siswa_id'] ?? null;

if (!$siswa_id) {
    set_flash('error', 'ID Siswa tidak ditemukan untuk mengirim pengingat.');
    redirect_to_previous_page(); // Redirect kembali ke halaman sebelumnya
}

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    $siswa_model = new Siswa($pdo);
    $tagihan_siswa_model = new TagihanSiswa($pdo);
    $notification_queue_model = new NotificationQueue($pdo);
    $school_settings_model = new SchoolSettings($pdo); // Inisialisasi model pengaturan sekolah

    $siswa_detail = $siswa_model->findById($siswa_id);
    if (!$siswa_detail) {
        set_flash('error', 'Data siswa tidak ditemukan.');
        redirect_to_previous_page();
    }

    if (empty($siswa_detail['no_telp_ortu'])) {
        set_flash('error', 'Nomor telepon orang tua siswa tidak ditemukan untuk mengirim pengingat.');
        redirect_to_previous_page();
    }

    $current_date = date('Y-m-d');
    $reminders_sent_count = 0;
    $errors_count = 0;

    // Ambil semua tagihan bulanan yang BELUM LUNAS atau SEBAGIAN LUNAS
    $filters = [
        'siswa_id' => $siswa_id,
        'status_tagihan_exclude_lunas' => true // Ini akan mengambil 'Belum Lunas' dan 'Sebagian Lunas'
    ];
    $tagihan_bulanan = $tagihan_siswa_model->getAll($filters);

    $settings = $school_settings_model->getSettings(); // Ambil pengaturan sekolah untuk WhatsApp API
    $recipientPhoneNumber = ltrim($siswa_detail['no_telp_ortu'], '+');

    foreach ($tagihan_bulanan as $tagihan) {
        // Hanya proses tagihan yang jenis pembayarannya 'Bulanan'
        // (Asumsi kolom tipe_pembayaran ada di tabel jenis_pembayaran dan di-join ke tagihan)
        if ($tagihan['tipe_pembayaran'] === 'Bulanan' && $tagihan['tanggal_jatuh_tempo'] <= $current_date) {
            $templateName = 'pengingat_tagihan_bulanan';
            $templateParameters = [
                $siswa_detail['nama_lengkap'],
                $tagihan['nama_pembayaran'],
                $tagihan['periode_tagihan'] ?? '-',
                number_format($tagihan['jumlah_tagihan'], 2, ',', '.'),
                number_format($tagihan['sisa_tagihan'], 2, ',', '.'),
                format_date_indonesian($tagihan['tanggal_jatuh_tempo'])
            ];

            $queue_result = $notification_queue_model->addNotificationToQueue(
                $recipientPhoneNumber,
                $templateName,
                $templateParameters
            );

            if ($queue_result['status'] === 'success') {
                $reminders_sent_count++;
            } else {
                $errors_count++;
                error_log("Gagal menambahkan pengingat WA untuk tagihan ID {$tagihan['id']}: " . $queue_result['message']);
            }
        }
    }

    if ($reminders_sent_count > 0) {
        set_flash('success', "Berhasil menambahkan {$reminders_sent_count} pengingat tagihan bulanan ke antrean WhatsApp.");
    } else if ($errors_count > 0) {
        set_flash('error', "Gagal mengirim pengingat. Terjadi {$errors_count} kesalahan.");
    } else {
        set_flash('info', 'Tidak ada tagihan bulanan yang belum lunas atau sebagian lunas untuk siswa ini hingga bulan ini.');
    }

    redirect(ROOT_URL . 'admin/catat_pembayaran.php?siswa_id=' . $siswa_id);

} catch (PDOException $e) {
    error_log("Send Bill Reminders Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database saat mengirim pengingat: ' . $e->getMessage());
    redirect_to_previous_page();
} catch (Exception $e) {
    error_log("Send Bill Reminders General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem saat mengirim pengingat: ' . $e->getMessage());
    redirect_to_previous_page();
}

/**
 * Helper function to redirect back to the previous page.
 */
function redirect_to_previous_page() {
    if (isset($_SERVER['HTTP_REFERER'])) {
        header('Location: ' . $_SERVER['HTTP_REFERER']);
    } else {
        header('Location: ' . ROOT_URL . 'admin/catat_pembayaran.php');
    }
    exit;
}
?>
