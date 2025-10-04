<?php
// project-root/classes/InformasiUmum.php

class InformasiUmum {
    private $conn;
    private $table_name = "informasi_umum";

    public $id;
    public $judul;
    public $konten;
    public $tanggal_publikasi;
    public $status_publikasi;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Membuat entri informasi umum baru.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET
                    judul = :judul,
                    konten = :konten,
                    tanggal_publikasi = :tanggal_publikasi,
                    status_publikasi = :status_publikasi";

        $stmt = $this->conn->prepare($query);

        // Membersihkan data
        $this->judul = htmlspecialchars(strip_tags($this->judul));
        $this->konten = htmlspecialchars(strip_tags($this->konten));
        $this->tanggal_publikasi = htmlspecialchars(strip_tags($this->tanggal_publikasi));
        $this->status_publikasi = htmlspecialchars(strip_tags($this->status_publikasi));

        // Mengikat parameter
        $stmt->bindParam(':judul', $this->judul);
        $stmt->bindParam(':konten', $this->konten);
        $stmt->bindParam(':tanggal_publikasi', $this->tanggal_publikasi);
        $stmt->bindParam(':status_publikasi', $this->status_publikasi);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    /**
     * Membaca semua entri informasi umum, bisa dengan filter.
     * @param array $filters Array filter (opsional). Contoh: ['status_publikasi' => 'Aktif']
     * @return array Array asosiatif dari semua informasi umum.
     */
    public function getAll($filters = []) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE 1=1";
        $params = [];

        if (isset($filters['status_publikasi']) && !empty($filters['status_publikasi'])) {
            $query .= " AND status_publikasi = :status_publikasi";
            $params[':status_publikasi'] = $filters['status_publikasi'];
        }

        $query .= " ORDER BY tanggal_publikasi DESC, created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Membaca satu entri informasi umum berdasarkan ID.
     * @param int $id ID informasi umum.
     * @return array|false Array asosiatif dari informasi umum, atau false jika tidak ditemukan.
     */
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Memperbarui entri informasi umum.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET
                    judul = :judul,
                    konten = :konten,
                    tanggal_publikasi = :tanggal_publikasi,
                    status_publikasi = :status_publikasi,
                    updated_at = CURRENT_TIMESTAMP
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Membersihkan data
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->judul = htmlspecialchars(strip_tags($this->judul));
        $this->konten = htmlspecialchars(strip_tags($this->konten));
        $this->tanggal_publikasi = htmlspecialchars(strip_tags($this->tanggal_publikasi));
        $this->status_publikasi = htmlspecialchars(strip_tags($this->status_publikasi));

        // Mengikat parameter
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':judul', $this->judul);
        $stmt->bindParam(':konten', $this->konten);
        $stmt->bindParam(':tanggal_publikasi', $this->tanggal_publikasi);
        $stmt->bindParam(':status_publikasi', $this->status_publikasi);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    /**
     * Menghapus entri informasi umum.
     * @param int $id ID informasi umum.
     * @return bool True jika berhasil, false jika gagal.
     */
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
