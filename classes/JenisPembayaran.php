<?php
// project-root/classes/JenisPembayaran.php

class JenisPembayaran {
    private $pdo;
    private $table_name = "jenis_pembayaran";
    private $table_kas_unit = "kas_unit"; // Tambahkan tabel kas_unit untuk JOIN

    // Properti untuk menyimpan data jenis pembayaran
    public $id;
    public $nama_pembayaran;
    public $unit_id; // Properti baru untuk unit_id
    public $deskripsi;
    public $tipe_pembayaran; // 'Rutin Bulanan' atau 'Bebas'
    public $is_active;
    public $created_at;
    public $updated_at;

    public function __construct(PDO $db) {
        $this->pdo = $db;
    }

    /**
     * Mengambil semua jenis pembayaran dari database, termasuk nama unit kas.
     *
     * @return array Array asosiatif dari semua jenis pembayaran.
     */
    public function getAll() {
        $query = "SELECT jp.id, jp.nama_pembayaran, jp.unit_id, ku.nama_unit, jp.deskripsi, jp.tipe_pembayaran, jp.is_active, jp.created_at, jp.updated_at
                  FROM " . $this->table_name . " jp
                  LEFT JOIN " . $this->table_kas_unit . " ku ON jp.unit_id = ku.id
                  ORDER BY jp.nama_pembayaran ASC";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mengambil satu jenis pembayaran berdasarkan ID, termasuk nama unit kas.
     *
     * @param int $id ID jenis pembayaran.
     * @return array|false Data jenis pembayaran jika ditemukan, false jika tidak.
     */
    public function getById($id) {
        $query = "SELECT jp.id, jp.nama_pembayaran, jp.unit_id, ku.nama_unit, jp.deskripsi, jp.tipe_pembayaran, jp.is_active
                  FROM " . $this->table_name . " jp
                  LEFT JOIN " . $this->table_kas_unit . " ku ON jp.unit_id = ku.id
                  WHERE jp.id = :id LIMIT 0,1";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Membuat jenis pembayaran baru.
     *
     * @param string $nama_pembayaran Nama jenis pembayaran.
     * @param int|null $unit_id ID unit kas terkait (opsional).
     * @param string $deskripsi Deskripsi jenis pembayaran.
     * @param string $tipe_pembayaran Tipe pembayaran ('Rutin Bulanan' atau 'Bebas').
     * @param int $is_active Status aktif (1 atau 0).
     * @return bool True jika berhasil, false jika gagal.
     */
    public function create($nama_pembayaran, $unit_id, $deskripsi, $tipe_pembayaran, $is_active) {
        $query = "INSERT INTO " . $this->table_name . "
                  (nama_pembayaran, unit_id, deskripsi, tipe_pembayaran, is_active, created_at, updated_at)
                  VALUES (:nama_pembayaran, :unit_id, :deskripsi, :tipe_pembayaran, :is_active, NOW(), NOW())";
        
        $stmt = $this->pdo->prepare($query);

        // Membersihkan data
        $nama_pembayaran = htmlspecialchars(strip_tags($nama_pembayaran));
        // unit_id tidak perlu strip_tags karena sudah divalidasi sebagai INT
        $deskripsi = htmlspecialchars(strip_tags($deskripsi));
        $tipe_pembayaran = htmlspecialchars(strip_tags($tipe_pembayaran));
        $is_active = htmlspecialchars(strip_tags($is_active));

        // Binding parameter
        $stmt->bindParam(':nama_pembayaran', $nama_pembayaran);
        $stmt->bindParam(':unit_id', $unit_id, PDO::PARAM_INT); // Bind as INT, bisa NULL
        $stmt->bindParam(':deskripsi', $deskripsi);
        $stmt->bindParam(':tipe_pembayaran', $tipe_pembayaran);
        $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    /**
     * Memperbarui jenis pembayaran.
     *
     * @param int $id ID jenis pembayaran.
     * @param string $nama_pembayaran Nama jenis pembayaran baru.
     * @param int|null $unit_id ID unit kas terkait baru (opsional).
     * @param string $deskripsi Deskripsi jenis pembayaran baru.
     * @param string $tipe_pembayaran Tipe pembayaran baru.
     * @param int $is_active Status aktif baru.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function update($id, $nama_pembayaran, $unit_id, $deskripsi, $tipe_pembayaran, $is_active) {
        $query = "UPDATE " . $this->table_name . "
                  SET
                      nama_pembayaran = :nama_pembayaran,
                      unit_id = :unit_id, -- Tambahkan unit_id
                      deskripsi = :deskripsi,
                      tipe_pembayaran = :tipe_pembayaran,
                      is_active = :is_active,
                      updated_at = NOW()
                  WHERE id = :id";
        
        $stmt = $this->pdo->prepare($query);

        // Membersihkan data
        $nama_pembayaran = htmlspecialchars(strip_tags($nama_pembayaran));
        // unit_id tidak perlu strip_tags
        $deskripsi = htmlspecialchars(strip_tags($deskripsi));
        $tipe_pembayaran = htmlspecialchars(strip_tags($tipe_pembayaran));
        $is_active = htmlspecialchars(strip_tags($is_active));
        $id = htmlspecialchars(strip_tags($id)); // ID juga perlu dibersihkan

        // Binding parameter
        $stmt->bindParam(':nama_pembayaran', $nama_pembayaran);
        $stmt->bindParam(':unit_id', $unit_id, PDO::PARAM_INT); // Bind as INT, bisa NULL
        $stmt->bindParam(':deskripsi', $deskripsi);
        $stmt->bindParam(':tipe_pembayaran', $tipe_pembayaran);
        $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    /**
     * Menghapus jenis pembayaran.
     *
     * @param int $id ID jenis pembayaran.
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
