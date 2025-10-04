<?php
// classes/JenisPelanggaran.php

class JenisPelanggaran {
    private $conn;
    private $table_name = "jenis_pelanggaran";

    public $id;
    public $nama_pelanggaran;
    public $poin_deduksi;
    public $keterangan;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Metode untuk membaca semua jenis pelanggaran
    public function getAll() {
        $query = "SELECT id, nama_pelanggaran, poin_deduksi, keterangan FROM " . $this->table_name . " ORDER BY nama_pelanggaran ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Metode untuk membaca satu jenis pelanggaran berdasarkan ID
    public function getById($id) {
        $query = "SELECT id, nama_pelanggaran, poin_deduksi, keterangan FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Metode untuk menambah jenis pelanggaran baru
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " (nama_pelanggaran, poin_deduksi, keterangan) VALUES (:nama_pelanggaran, :poin_deduksi, :keterangan)";
        $stmt = $this->conn->prepare($query);

        // Membersihkan data
        $this->nama_pelanggaran = htmlspecialchars(strip_tags($this->nama_pelanggaran));
        $this->poin_deduksi = htmlspecialchars(strip_tags($this->poin_deduksi));
        $this->keterangan = htmlspecialchars(strip_tags($this->keterangan));

        // Mengikat parameter
        $stmt->bindParam(':nama_pelanggaran', $this->nama_pelanggaran);
        $stmt->bindParam(':poin_deduksi', $this->poin_deduksi, PDO::PARAM_INT);
        $stmt->bindParam(':keterangan', $this->keterangan);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Metode untuk memperbarui jenis pelanggaran
    public function update() {
        $query = "UPDATE " . $this->table_name . " SET nama_pelanggaran = :nama_pelanggaran, poin_deduksi = :poin_deduksi, keterangan = :keterangan, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        // Membersihkan data
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->nama_pelanggaran = htmlspecialchars(strip_tags($this->nama_pelanggaran));
        $this->poin_deduksi = htmlspecialchars(strip_tags($this->poin_deduksi));
        $this->keterangan = htmlspecialchars(strip_tags($this->keterangan));

        // Mengikat parameter
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':nama_pelanggaran', $this->nama_pelanggaran);
        $stmt->bindParam(':poin_deduksi', $this->poin_deduksi, PDO::PARAM_INT);
        $stmt->bindParam(':keterangan', $this->keterangan);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Metode untuk menghapus jenis pelanggaran
    public function delete($id) {
        // Pertimbangkan untuk memeriksa apakah ada pelanggaran siswa yang terkait sebelum menghapus jenis pelanggaran
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }
}
