<?php
// classes/KitabNadhom.php

class KitabNadhom {
    private $conn;
    private $table_name = "kitab_nadhom";

    public $id;
    public $nama_kitab;
    public $keterangan;
    public $jenis_kitab; // Properti baru
    public $target_hafalan; // Properti baru
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Metode untuk membaca semua kitab/nadhom
    public function getAll() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY nama_kitab ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Metode baru untuk membaca kitab berdasarkan jenisnya
    public function getKitabByJenis($jenis) {
        $query = "SELECT id, nama_kitab, keterangan, jenis_kitab, target_hafalan FROM " . $this->table_name . " WHERE jenis_kitab = :jenis ORDER BY nama_kitab ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':jenis', $jenis);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Metode untuk membaca satu kitab/nadhom berdasarkan ID
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Metode untuk menambah kitab/nadhom baru
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " (nama_kitab, keterangan, jenis_kitab, target_hafalan) VALUES (:nama_kitab, :keterangan, :jenis_kitab, :target_hafalan)";
        $stmt = $this->conn->prepare($query);

        $this->nama_kitab = htmlspecialchars(strip_tags($this->nama_kitab));
        $this->keterangan = htmlspecialchars(strip_tags($this->keterangan));
        $this->jenis_kitab = htmlspecialchars(strip_tags($this->jenis_kitab));
        $this->target_hafalan = htmlspecialchars(strip_tags($this->target_hafalan));

        $stmt->bindParam(':nama_kitab', $this->nama_kitab);
        $stmt->bindParam(':keterangan', $this->keterangan);
        $stmt->bindParam(':jenis_kitab', $this->jenis_kitab);
        $stmt->bindParam(':target_hafalan', $this->target_hafalan, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Metode untuk memperbarui kitab/nadhom
    public function update() {
        $query = "UPDATE " . $this->table_name . " SET nama_kitab = :nama_kitab, keterangan = :keterangan, jenis_kitab = :jenis_kitab, target_hafalan = :target_hafalan, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->nama_kitab = htmlspecialchars(strip_tags($this->nama_kitab));
        $this->keterangan = htmlspecialchars(strip_tags($this->keterangan));
        $this->jenis_kitab = htmlspecialchars(strip_tags($this->jenis_kitab));
        $this->target_hafalan = htmlspecialchars(strip_tags($this->target_hafalan));

        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        $stmt->bindParam(':nama_kitab', $this->nama_kitab);
        $stmt->bindParam(':keterangan', $this->keterangan);
        $stmt->bindParam(':jenis_kitab', $this->jenis_kitab);
        $stmt->bindParam(':target_hafalan', $this->target_hafalan, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Metode untuk menghapus kitab/nadhom
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }
}