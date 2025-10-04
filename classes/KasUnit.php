<?php
// project-root/classes/KasUnit.php

class KasUnit {
    private $pdo;
    private $table_name = "kas_unit";

    public function __construct(PDO $db) {
        $this->pdo = $db;
    }

    /**
     * Mendapatkan semua unit kas.
     *
     * @return array Array asosiatif dari semua unit kas.
     */
    public function getAll() {
        $query = "SELECT id, nama_unit FROM " . $this->table_name . " ORDER BY nama_unit ASC";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mendapatkan detail unit berdasarkan ID.
     *
     * @param int $id ID unit kas.
     * @return array|false Data unit jika ditemukan, false jika tidak.
     */
    public function findById($id) {
        $query = "SELECT id, nama_unit FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Membuat unit kas baru.
     *
     * @param string $nama_unit Nama unit kas.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function create($nama_unit) {
        $query = "INSERT INTO " . $this->table_name . " (nama_unit) VALUES (:nama_unit)";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':nama_unit', $nama_unit);
        return $stmt->execute();
    }

    /**
     * Memperbarui data unit kas.
     *
     * @param int $id ID unit kas.
     * @param string $nama_unit Nama unit kas baru.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function update($id, $nama_unit) {
        $query = "UPDATE " . $this->table_name . " SET nama_unit = :nama_unit, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':nama_unit', $nama_unit);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Menghapus unit kas.
     * Perhatian: Ini akan gagal jika ada transaksi yang terikat pada unit ini.
     *
     * @param int $id ID unit kas.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
