<?php
// project-root/classes/TahunAjaran.php

class TahunAjaran {
    private $pdo;
    private $table_name = "tahun_ajaran";

    // Properti untuk menyimpan data tahun ajaran
    public $id;
    public $tahun_ajaran; // Contoh: "2023/2024"
    public $is_active; // 1 untuk aktif, 0 untuk tidak aktif

    public function __construct(PDO $db) {
        $this->pdo = $db;
    }

    /**
     * Mengambil semua tahun ajaran dari database.
     *
     * @return array Array asosiatif dari semua tahun ajaran.
     */
    public function getAll() {
        $query = "SELECT id, tahun_ajaran, is_active, created_at, updated_at
                  FROM " . $this->table_name . "
                  ORDER BY tahun_ajaran DESC"; // Urutkan dari tahun ajaran terbaru
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    
    /**
     * Mendapatkan semua tahun ajaran.
     * @return array Daftar semua tahun ajaran.
     */
    public function getAllTahunAjaran() {
        $query = "SELECT id, tahun_ajaran, is_active FROM " . $this->table_name . " ORDER BY tahun_ajaran DESC";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mengambil tahun ajaran aktif dari database.
     *
     * @return array|false Data tahun ajaran aktif jika ditemukan, false jika tidak.
     */
    public function getActiveTahunAjaran() {
        $query = "SELECT id, tahun_ajaran FROM " . $this->table_name . " WHERE is_active = 1 LIMIT 0,1";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Mengambil satu tahun ajaran berdasarkan ID.
     *
     * @param int $id ID tahun ajaran.
     * @return array|false Data tahun ajaran jika ditemukan, false jika tidak.
     */
    public function getById($id) {
        $query = "SELECT id, tahun_ajaran, is_active
                  FROM " . $this->table_name . "
                  WHERE id = :id LIMIT 0,1";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
        /**
     * Mengambil satu tahun ajaran berdasarkan ID.
     * Ini adalah metode yang dulunya bernama `getById` dan sekarang diganti namanya.
     *
     * @param int $id ID tahun ajaran.
     * @return array|false Data tahun ajaran jika ditemukan, false jika tidak.
     */
    public function getTahunAjaranById($id) { // Nama metode diubah dari getById()
        $query = "SELECT id, tahun_ajaran, is_active
                  FROM " . $this->table_name . "
                  WHERE id = :id LIMIT 0,1";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Membuat tahun ajaran baru.
     *
     * @return bool True jika berhasil, false jika gagal.
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  (tahun_ajaran, is_active, created_at, updated_at)
                  VALUES (:tahun_ajaran, :is_active, NOW(), NOW())";
        
        $stmt = $this->pdo->prepare($query);

        // Membersihkan data
        $this->tahun_ajaran = htmlspecialchars(strip_tags($this->tahun_ajaran));
        $this->is_active = htmlspecialchars(strip_tags($this->is_active));

        // Binding parameter
        $stmt->bindParam(':tahun_ajaran', $this->tahun_ajaran);
        $stmt->bindParam(':is_active', $this->is_active, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    /**
     * Memperbarui tahun ajaran.
     *
     * @return bool True jika berhasil, false jika gagal.
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET
                      tahun_ajaran = :tahun_ajaran,
                      is_active = :is_active,
                      updated_at = NOW()
                  WHERE id = :id";
        
        $stmt = $this->pdo->prepare($query);

        // Membersihkan data
        $this->tahun_ajaran = htmlspecialchars(strip_tags($this->tahun_ajaran));
        $this->is_active = htmlspecialchars(strip_tags($this->is_active));
        $this->id = htmlspecialchars(strip_tags($this->id));

        // Binding parameter
        $stmt->bindParam(':tahun_ajaran', $this->tahun_ajaran);
        $stmt->bindParam(':is_active', $this->is_active, PDO::PARAM_INT);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    /**
     * Menghapus tahun ajaran.
     * Perhatian: Ini akan gagal jika ada data lain (misal: tagihan) yang terikat pada tahun ajaran ini.
     *
     * @param int $id ID tahun ajaran.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }
    
    
}
