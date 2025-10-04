<?php
// project-root/classes/KasKategori.php

class KasKategori {
    private $pdo;
    private $table_name = "kas_kategori";

    public function __construct(PDO $db) {
        $this->pdo = $db;
    }

    /**
     * Mendapatkan semua kategori kas.
     *
     * @param string|null $tipe_kategori Filter berdasarkan tipe kategori ('masuk', 'keluar', 'campuran').
     * @return array Array asosiatif dari semua kategori kas.
     */
    public function getAll($tipe_kategori = null) {
        $query = "SELECT id, nama_kategori, tipe_kategori FROM " . $this->table_name;
        $params = [];
        if ($tipe_kategori) {
            $query .= " WHERE tipe_kategori = :tipe_kategori OR tipe_kategori = 'campuran'";
            $params[':tipe_kategori'] = $tipe_kategori;
        }
        $query .= " ORDER BY nama_kategori ASC";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mendapatkan detail kategori berdasarkan ID.
     *
     * @param int $id ID kategori kas.
     * @return array|false Data kategori jika ditemukan, false jika tidak.
     */
    public function findById($id) {
        $query = "SELECT id, nama_kategori, tipe_kategori FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Membuat kategori kas baru.
     *
     * @param string $nama_kategori Nama kategori kas.
     * @param string $tipe_kategori Tipe kategori ('masuk', 'keluar', 'campuran').
     * @return bool True jika berhasil, false jika gagal.
     */
    public function create($nama_kategori, $tipe_kategori = 'campuran') {
        $query = "INSERT INTO " . $this->table_name . " (nama_kategori, tipe_kategori) VALUES (:nama_kategori, :tipe_kategori)";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':nama_kategori', $nama_kategori);
        $stmt->bindParam(':tipe_kategori', $tipe_kategori);
        return $stmt->execute();
    }

    /**
     * Memperbarui data kategori kas.
     *
     * @param int $id ID kategori kas.
     * @param string $nama_kategori Nama kategori kas baru.
     * @param string $tipe_kategori Tipe kategori baru ('masuk', 'keluar', 'campuran').
     * @return bool True jika berhasil, false jika gagal.
     */
    public function update($id, $nama_kategori, $tipe_kategori) {
        $query = "UPDATE " . $this->table_name . " SET nama_kategori = :nama_kategori, tipe_kategori = :tipe_kategori, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':nama_kategori', $nama_kategori);
        $stmt->bindParam(':tipe_kategori', $tipe_kategori);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Menghapus kategori kas.
     * Perhatian: Ini akan gagal jika ada transaksi yang terikat pada kategori ini.
     *
     * @param int $id ID kategori kas.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
