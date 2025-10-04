<?php
// project-root/classes/SchoolSettings.php

class SchoolSettings {
    private $pdo;
    private $table_name = "school_settings";

    public function __construct(PDO $db) {
        $this->pdo = $db;
    }

    /**
     * Mendapatkan semua pengaturan sekolah.
     * Karena diharapkan hanya ada satu baris pengaturan, ini akan mengambil baris pertama.
     *
     * @return array|false Data pengaturan sekolah jika ditemukan, false jika tidak.
     */
    public function getSettings() {
        // Add rfid_machine_api_key to the SELECT query
        $query = "SELECT id, nama_sekolah, alamat_sekolah, kota, provinsi, kode_pos,
                         telepon_sekolah, email_sekolah, website_sekolah, kepala_sekolah,
                         nip_kepala_sekolah, logo_sekolah_path, tahun_ajaran_aktif, semester_aktif,
                         jam_masuk_sekolah_default, toleransi_terlambat_menit,
                         whatsapp_api_url, whatsapp_api_key, whatsapp_sender_number,
                         rfid_machine_api_key, -- New column added here
                         lokasi_sekolah_lat, lokasi_sekolah_long, radius_absensi_meter, qr_code_path,  qr_token_hash, -- NEW
                         created_at, updated_at
                  FROM " . $this->table_name . " LIMIT 0,1";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Memperbarui pengaturan sekolah.
     * Jika belum ada record, akan membuat yang baru (upsert-like behavior).
     *
     * @param array $data Data pengaturan yang akan diperbarui/disimpan.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function updateSettings(array $data) {
        // Cek apakah sudah ada record pengaturan
        $existing_settings = $this->getSettings();

        if ($existing_settings) {
            // Update record yang sudah ada (asumsi ID 1 untuk pengaturan utama)
            $query = "UPDATE " . $this->table_name . " SET
                        nama_sekolah = :nama_sekolah,
                        alamat_sekolah = :alamat_sekolah,
                        kota = :kota,
                        provinsi = :provinsi,
                        kode_pos = :kode_pos,
                        telepon_sekolah = :telepon_sekolah,
                        email_sekolah = :email_sekolah,
                        website_sekolah = :website_sekolah,
                        kepala_sekolah = :kepala_sekolah,
                        nip_kepala_sekolah = :nip_kepala_sekolah,
                        logo_sekolah_path = :logo_sekolah_path,
                        tahun_ajaran_aktif = :tahun_ajaran_aktif,
                        semester_aktif = :semester_aktif,
                        jam_masuk_sekolah_default = :jam_masuk_sekolah_default,
                        toleransi_terlambat_menit = :toleransi_terlambat_menit,
                        whatsapp_api_url = :whatsapp_api_url,
                        whatsapp_api_key = :whatsapp_api_key,
                        whatsapp_sender_number = :whatsapp_sender_number,
                        rfid_machine_api_key = :rfid_machine_api_key, 
                        lokasi_sekolah_lat = :lokasi_sekolah_lat,
                        lokasi_sekolah_long = :lokasi_sekolah_long,
                        radius_absensi_meter = :radius_absensi_meter,
                        qr_code_path = :qr_code_path,
                        qr_token_hash = :qr_token_hash,  /* --- FIX IS HERE --- */
                        updated_at = CURRENT_TIMESTAMP
                       WHERE id = :id";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $existing_settings['id'], PDO::PARAM_INT);
    } else {
        // Insert record baru jika belum ada
        $query = "INSERT INTO " . $this->table_name . " (
                        nama_sekolah, alamat_sekolah, kota, provinsi, kode_pos,
                        telepon_sekolah, email_sekolah, website_sekolah, kepala_sekolah,
                        nip_kepala_sekolah, logo_sekolah_path, tahun_ajaran_aktif, semester_aktif,
                        jam_masuk_sekolah_default, toleransi_terlambat_menit,
                        whatsapp_api_url, whatsapp_api_key, whatsapp_sender_number,
                        rfid_machine_api_key,
                        lokasi_sekolah_lat, lokasi_sekolah_long, radius_absensi_meter, qr_code_path, qr_token_hash,  /* --- FIX IS HERE --- */
                        created_at, updated_at
                       ) VALUES (
                         :nama_sekolah, :alamat_sekolah, :kota, :provinsi, :kode_pos,
                         :telepon_sekolah, :email_sekolah, :website_sekolah, :kepala_sekolah,
                         :nip_kepala_sekolah, :logo_sekolah_path, :tahun_ajaran_aktif, :semester_aktif,
                         :jam_masuk_sekolah_default, :toleransi_terlambat_menit,
                         :whatsapp_api_url, :whatsapp_api_key, :whatsapp_sender_number,
                         :rfid_machine_api_key,
                         :lokasi_sekolah_lat, :lokasi_sekolah_long, :radius_absensi_meter, :qr_code_path, :qr_token_hash, /* --- FIX IS HERE --- */
                         CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                       )";
        $stmt = $this->pdo->prepare($query);
    }

    $stmt->bindParam(':nama_sekolah', $data['nama_sekolah']);
        $stmt->bindParam(':alamat_sekolah', $data['alamat_sekolah']);
        $stmt->bindParam(':kota', $data['kota']);
        $stmt->bindParam(':provinsi', $data['provinsi']);
        $stmt->bindParam(':kode_pos', $data['kode_pos']);
        $stmt->bindParam(':telepon_sekolah', $data['telepon_sekolah']);
        $stmt->bindParam(':email_sekolah', $data['email_sekolah']);
        $stmt->bindParam(':website_sekolah', $data['website_sekolah']);
        $stmt->bindParam(':kepala_sekolah', $data['kepala_sekolah']);
        $stmt->bindParam(':nip_kepala_sekolah', $data['nip_kepala_sekolah']);
        $stmt->bindParam(':logo_sekolah_path', $data['logo_sekolah_path']);
        $stmt->bindParam(':tahun_ajaran_aktif', $data['tahun_ajaran_aktif']);
        $stmt->bindParam(':semester_aktif', $data['semester_aktif']);
        // Bind existing new parameters
        $stmt->bindParam(':jam_masuk_sekolah_default', $data['jam_masuk_sekolah_default']);
        $stmt->bindParam(':toleransi_terlambat_menit', $data['toleransi_terlambat_menit'], PDO::PARAM_INT);
        $stmt->bindParam(':whatsapp_api_url', $data['whatsapp_api_url']);
        $stmt->bindParam(':whatsapp_api_key', $data['whatsapp_api_key']);
        $stmt->bindParam(':whatsapp_sender_number', $data['whatsapp_sender_number']);
        // Bind the new RFID_MACHINE_API_KEY parameter
        $stmt->bindParam(':rfid_machine_api_key', $data['rfid_machine_api_key']); // New parameter bind here
        $stmt->bindParam(':lokasi_sekolah_lat', $data['lokasi_sekolah_lat']);
        $stmt->bindParam(':lokasi_sekolah_long', $data['lokasi_sekolah_long']);
        $stmt->bindParam(':radius_absensi_meter', $data['radius_absensi_meter'], PDO::PARAM_INT);
        $stmt->bindParam(':qr_code_path', $data['qr_code_path']);
        $stmt->bindParam(':qr_token_hash', $data['qr_token_hash']); /* --- FIX IS HERE --- */

    return $stmt->execute();
    }
}
