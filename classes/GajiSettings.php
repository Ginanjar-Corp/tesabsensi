<?php
// project-root/classes/GajiSettings.php

class GajiSettings {
    private $pdo;
    private $table_name = "gaji_settings"; // Asumsi nama tabel adalah gaji_settings

    public function __construct(PDO $db) {
        $this->pdo = $db;
    }

    /**
     * Mendapatkan semua pengaturan gaji.
     * Karena diharapkan hanya ada satu baris pengaturan, ini akan mengambil baris pertama.
     *
     * @return array|false Data pengaturan gaji jika ditemukan, false jika tidak.
     */
    public function getSettings() {
        $query = "SELECT * FROM " . $this->table_name . " LIMIT 0,1";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Memperbarui nominal gaji per pertemuan.
     * Jika belum ada record, akan membuat yang baru (upsert-like behavior).
     *
     * @param float $nominal Nominal gaji yang akan diperbarui/disimpan.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function updateNominalGajiPerPertemuan(float $nominal) {
        // Cek apakah sudah ada record pengaturan
        $existing_settings = $this->getSettings();

        if ($existing_settings) {
            // Update record yang sudah ada (asumsi ID 1 untuk pengaturan utama)
            $query = "UPDATE " . $this->table_name . " SET
                        nominal_per_pertemuan = :nominal_per_pertemuan
                      WHERE id = :id"; // Asumsi ada kolom 'id'
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':id', $existing_settings['id'], PDO::PARAM_INT);
        } else {
            // Insert record baru
            $query = "INSERT INTO " . $this->table_name . " (nominal_per_pertemuan)
                      VALUES (:nominal_per_pertemuan)";
            $stmt = $this->pdo->prepare($query);
        }

        $stmt->bindParam(':nominal_per_pertemuan', $nominal);

        return $stmt->execute();
    }
}