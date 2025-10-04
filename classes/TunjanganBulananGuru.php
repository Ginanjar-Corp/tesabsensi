<?php
// project-root/classes/TunjanganBulananGuru.php

class TunjanganBulananGuru {
    private $pdo;
    private $table_name = "tunjangan_bulanan_guru";

    public function __construct(PDO $db) {
        $this->pdo = $db;
    }

    /**
     * Finds tunjangan bulanan by guru ID, academic year ID, and month.
     * @param int $guru_id The guru's ID.
     * @param int $tahun_ajaran_id The academic year's ID.
     * @param int $bulan The month (1-12).
     * @return array|false The tunjangan data or false if not found.
     */
    public function findByGuruAndPeriod($guru_id, $tahun_ajaran_id, $bulan) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE guru_id = :guru_id AND tahun_ajaran_id = :tahun_ajaran_id AND bulan = :bulan LIMIT 1";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
        $stmt->bindParam(':tahun_ajaran_id', $tahun_ajaran_id, PDO::PARAM_INT);
        $stmt->bindParam(':bulan', $bulan, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Fetches all tunjangan data for a specific academic year and month.
     *
     * @param int $tahun_ajaran_id The academic year's ID.
     * @param int $bulan The month (1-12).
     * @return array An array of tunjangan data.
     */
    public function findByPeriod($tahun_ajaran_id, $bulan) {
        $query = "
            SELECT
                tbg.*,
                g.nama_lengkap AS nama_guru,
                ta.tahun_ajaran
            FROM
                " . $this->table_name . " tbg
            JOIN
                guru g ON tbg.guru_id = g.id
            JOIN
                tahun_ajaran ta ON tbg.tahun_ajaran_id = ta.id
            WHERE
                tbg.tahun_ajaran_id = :tahun_ajaran_id AND tbg.bulan = :bulan
            ORDER BY
                g.nama_lengkap
        ";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':tahun_ajaran_id', $tahun_ajaran_id, PDO::PARAM_INT);
        $stmt->bindParam(':bulan', $bulan, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Creates a new tunjangan entry.
     * @param array $data The data to be inserted.
     * @return bool True on success, false on failure.
     */
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " (guru_id, tahun_ajaran_id, bulan, kegiatan_lainnya, tunjangan_kegiatan_lainnya) VALUES (:guru_id, :tahun_ajaran_id, :bulan, :kegiatan_lainnya, :tunjangan_kegiatan_lainnya)";
        $stmt = $this->pdo->prepare($query);
        return $stmt->execute([
            ':guru_id' => $data['guru_id'],
            ':tahun_ajaran_id' => $data['tahun_ajaran_id'],
            ':bulan' => $data['bulan'],
            ':kegiatan_lainnya' => $data['kegiatan_lainnya'],
            ':tunjangan_kegiatan_lainnya' => $data['tunjangan_kegiatan_lainnya']
        ]);
    }
    
    /**
     * Updates an existing tunjangan entry.
     *
     * @param int $id The tunjangan entry's ID.
     * @param array $data The data to update.
     * @return bool True on success, false on failure.
     */
    public function update($id, $data) {
        $query = "UPDATE " . $this->table_name . " SET
            kegiatan_lainnya = :kegiatan_lainnya,
            tunjangan_kegiatan_lainnya = :tunjangan_kegiatan_lainnya,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = :id";
        $stmt = $this->pdo->prepare($query);
        return $stmt->execute([
            ':kegiatan_lainnya' => $data['kegiatan_lainnya'],
            ':tunjangan_kegiatan_lainnya' => $data['tunjangan_kegiatan_lainnya'],
            ':id' => $id
        ]);
    }

    /**
     * Deletes a tunjangan entry.
     *
     * @param int $id The tunjangan entry's ID.
     * @return bool True on success, false on failure.
     */
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
    
    /**
     * Finds a tunjangan entry by its ID.
     *
     * @param int $id The tunjangan entry's ID.
     * @return array|false The tunjangan data or false if not found.
     */
    public function findById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}