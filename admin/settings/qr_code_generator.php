<?php
// project-root/admin/settings/qr_code_generator.php

// Memuat file-file konfigurasi dan kelas yang diperlukan
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/SchoolSettings.php';

// Memuat library QR Code tanpa Composer
// Ganti path di bawah ini jika Anda mengunggah qrlib.php ke lokasi lain
require_once __DIR__ . '/../../vendor/phpqrcode/qrlib.php'; 

require_login();
require_role(['Admin', 'TataUsaha']);

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $school_settings_model = new SchoolSettings($pdo);

    // Dapatkan pengaturan saat ini
    // Fetch all existing settings first
    $current_settings = $school_settings_model->getSettings();

    // Generate a secure, unique token and its hash
    $token = bin2hex(random_bytes(32)); 
    $token_hash = password_hash($token, PASSWORD_DEFAULT);

    // Prepare file path for the QR code image
    $qr_code_dir = __DIR__ . '/../../assets/uploads/qrcodes/';
    $file_name = 'qrcode_' . time() . '.png';
    $file_path = $qr_code_dir . $file_name;
    
    // Ensure the directory exists
    if (!is_dir($qr_code_dir)) {
        mkdir($qr_code_dir, 0755, true);
    }

    // Generate the QR Code image
    QRcode::png($token, $file_path, QR_ECLEVEL_H, 10, 2);

    // Prepare the update data by merging existing settings with the new QR code data
    $update_data = $current_settings ?: []; // Use an empty array if no settings exist
    $update_data['qr_code_path'] = 'assets/uploads/qrcodes/' . $file_name;
    $update_data['qr_token_hash'] = $token_hash;
    
    // Handle the database operation (update or insert)
    if ($current_settings) {
        // Update existing record
        $update_data['id'] = $current_settings['id']; // Make sure to include the ID for the update query
        if ($school_settings_model->updateSettings($update_data)) {
            set_flash('success', 'QR Code absensi berhasil diperbarui. Guru sekarang dapat menggunakannya.');
        } else {
            set_flash('error', 'Gagal menyimpan jalur QR Code dan token hash ke database.');
        }
    } else {
        // Create new record
        if ($school_settings_model->updateSettings($update_data)) {
            set_flash('success', 'QR Code absensi berhasil dibuat.');
        } else {
            set_flash('error', 'Gagal membuat pengaturan sekolah dan QR Code.');
        }
    }

    // Redirect to the settings page
    redirect(ROOT_URL . 'admin/settings/school_settings.php');

} catch (Exception $e) {
    error_log('QR Code generation failed: ' . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan saat membuat QR Code. Silakan coba lagi.');
    redirect(ROOT_URL . 'admin/settings/school_settings.php');
}
