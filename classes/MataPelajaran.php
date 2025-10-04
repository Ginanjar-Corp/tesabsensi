<?php
// project-root/classes/MataPelajaran.php

class MataPelajaran {
    private $pdo;
    private $table_name = "mata_pelajaran";

    public function __construct(PDO $db) {
        $this->pdo = $db;
    }

    /**
     * Mendapatkan semua mata pelajaran.
     *
     * @return array Array asosiatif dari semua mata pelajaran.
     */
    public function getAll() {
        $query = "SELECT id, nama_mapel, kkm FROM " . $this->table_name . " ORDER BY nama_mapel ASC";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mendapatkan detail mata pelajaran berdasarkan ID.
     *
     * @param int $id ID mata pelajaran.
     * @return array|false Data mata pelajaran jika ditemukan, false jika tidak.
     */
    public function findById($id) {
        $query = "SELECT id, nama_mapel, kkm FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Membuat mata pelajaran baru.
     *
     * @param string $nama_mapel Nama mata pelajaran.
     * @param int|null $kkm Kriteria Ketuntasan Minimal (opsional).
     * @return bool True jika berhasil, false jika gagal.
     */
    public function create($nama_mapel, $kkm = null) {
        $query = "INSERT INTO " . $this->table_name . " (nama_mapel, kkm) VALUES (:nama_mapel, :kkm)";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':nama_mapel', $nama_mapel);
        $stmt->bindParam(':kkm', $kkm, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Memperbarui data mata pelajaran.
     *
     * @param int $id ID mata pelajaran.
     * @param string $nama_mapel Nama mata pelajaran baru.
     * @param int|null $kkm Kriteria Ketuntasan Minimal baru (opsional).
     * @return bool True jika berhasil, false jika gagal.
     */
    public function update($id, $nama_mapel, $kkm = null) {
        $query = "UPDATE " . $this->table_name . " SET nama_mapel = :nama_mapel, kkm = :kkm, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':nama_mapel', $nama_mapel);
        $stmt->bindParam(':kkm', $kkm, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Menghapus mata pelajaran.
     *
     * @param int $id ID mata pelajaran.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function delete($id) {
        // Pertimbangkan untuk memeriksa foreign key constraint sebelum menghapus
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
    
    // project-root/classes/MataPelajaran.php

// ... (kode yang sudah ada di dalam kelas MataPelajaran)

/**
 * Menghapus mata pelajaran dan semua data terkait.
 * Menggunakan transaksi untuk memastikan integritas data.
 *
 * @param int $id ID mata pelajaran.
 * @return bool True jika berhasil, false jika gagal.
 */
public function deleteWithDependencies($id) {
    try {
        // Mulai transaksi
        $this->pdo->beginTransaction();

        // 1. Hapus data absensi guru yang terkait dengan jadwal mata pelajaran ini
        $this->pdo->prepare("DELETE FROM absensi_mapel_guru WHERE jadwal_id IN (SELECT id FROM jadwal_pelajaran WHERE mapel_id = ?)")->execute([$id]);

        // 2. Hapus data absensi siswa yang terkait
        $this->pdo->prepare("DELETE FROM absensi_mapel_siswa WHERE jadwal_id IN (SELECT id FROM jadwal_pelajaran WHERE mapel_id = ?)")->execute([$id]);

        // 3. Hapus semua jadwal pelajaran yang terkait dengan mata pelajaran ini
        $stmt_jadwal = $this->pdo->prepare("DELETE FROM jadwal_pelajaran WHERE mapel_id = ?");
        $stmt_jadwal->execute([$id]);

        // 4. Hapus data mata pelajaran itu sendiri
        $stmt_mapel = $this->pdo->prepare("DELETE FROM " . $this->table_name . " WHERE id = ?");
        $stmt_mapel->execute([$id]);

        // Commit transaksi jika semua operasi berhasil
        $this->pdo->commit();
        return true;
    } catch (PDOException $e) {
        // Rollback transaksi jika terjadi kesalahan
        $this->pdo->rollBack();
        error_log("Gagal menghapus mata pelajaran dengan dependensi: " . $e->getMessage());
        return false;
    }
}
}
