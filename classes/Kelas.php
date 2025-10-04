<?php
// project-root/classes/Kelas.php

class Kelas {
    private $pdo;
    private $table_name = "kelas";

    public function __construct(PDO $db) {
        $this->pdo = $db;
    }

    /**
     * Mendapatkan semua kelas.
     *
     * @return array Array asosiatif dari semua kelas.
     */
    public function getAll() {
        $query = "SELECT id, nama_kelas, tingkat, jurusan FROM " . $this->table_name . " ORDER BY nama_kelas ASC";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mendapatkan detail kelas berdasarkan ID.
     *
     * @param int $id ID kelas.
     * @return array|false Data kelas jika ditemukan, false jika tidak.
     */
    public function findById($id) {
        $query = "SELECT id, nama_kelas, tingkat, jurusan FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Metode untuk membaca satu kelas berdasarkan ID
    public function getById($id) {
        $query = "SELECT id, nama_kelas, tingkat, jurusan FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Membuat kelas baru.
     *
     * @param string $nama_kelas Nama kelas.
     * @param string|null $tingkat Tingkat kelas (misal: 'X', 'XI').
     * @param string|null $jurusan Jurusan kelas (misal: 'IPA', 'IPS').
     * @return bool True jika berhasil, false jika gagal.
     */
    public function create($nama_kelas, $tingkat = null, $jurusan = null) {
        $query = "INSERT INTO " . $this->table_name . " (nama_kelas, tingkat, jurusan) VALUES (:nama_kelas, :tingkat, :jurusan)";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':nama_kelas', $nama_kelas);
        $stmt->bindParam(':tingkat', $tingkat);
        $stmt->bindParam(':jurusan', $jurusan);
        return $stmt->execute();
    }

    /**
     * Memperbarui data kelas.
     *
     * @param int $id ID kelas.
     * @param string $nama_kelas Nama kelas baru.
     * @param string|null $tingkat Tingkat kelas baru.
     * @param string|null $jurusan Jurusan kelas baru.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function update($id, $nama_kelas, $tingkat = null, $jurusan = null) {
        $query = "UPDATE " . $this->table_name . " SET nama_kelas = :nama_kelas, tingkat = :tingkat, jurusan = :jurusan, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':nama_kelas', $nama_kelas);
        $stmt->bindParam(':tingkat', $tingkat);
        $stmt->bindParam(':jurusan', $jurusan);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Menghapus kelas.
     *
     * @param int $id ID kelas.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function delete($id) {
        // Penting: Pastikan tidak ada siswa yang terkait dengan kelas ini sebelum menghapus
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
