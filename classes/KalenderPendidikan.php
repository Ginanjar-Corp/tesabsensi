<?php
// project-root/classes/KalenderPendidikan.php

class KalenderPendidikan
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Mengambil semua data hari libur dari database.
     * @return array
     */
    public function getAllLibur()
    {
        $stmt = $this->pdo->query("SELECT * FROM kalender_pendidikan ORDER BY tanggal DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Menambahkan hari libur baru ke database.
     * @param string $tanggal
     * @param string $keterangan
     * @param string|null $tahun_ajaran
     * @param string|null $semester
     * @return bool
     */
    public function addLibur($tanggal, $keterangan, $tahun_ajaran = null, $semester = null)
    {
        $sql = "INSERT INTO kalender_pendidikan (tanggal, keterangan, tahun_ajaran, semester) VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$tanggal, $keterangan, $tahun_ajaran, $semester]);
    }

    /**
     * Mencari hari libur berdasarkan ID.
     * @param int $id
     * @return array|false
     */
    public function findById($id)
    {
        $sql = "SELECT * FROM kalender_pendidikan WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Mencari hari libur berdasarkan tanggal.
     * @param string $tanggal
     * @param string|null $tahun_ajaran
     * @return array|false
     */
    public function findByDate($tanggal, $tahun_ajaran = null)
    {
        $sql = "SELECT * FROM kalender_pendidikan WHERE tanggal = ? AND tahun_ajaran = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tanggal, $tahun_ajaran]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Memperbarui data hari libur.
     * @param int $id
     * @param string $tanggal
     * @param string $keterangan
     * @param string|null $tahun_ajaran
     * @param string|null $semester
     * @return bool
     */
    public function updateLibur($id, $tanggal, $keterangan, $tahun_ajaran = null, $semester = null)
    {
        $sql = "UPDATE kalender_pendidikan SET tanggal = ?, keterangan = ?, tahun_ajaran = ?, semester = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$tanggal, $keterangan, $tahun_ajaran, $semester, $id]);
    }

    /**
     * Menghapus hari libur dari database.
     * @param int $id
     * @return bool
     */
    public function deleteLibur($id)
    {
        $sql = "DELETE FROM kalender_pendidikan WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    /**
     * Mengambil daftar tanggal hari libur dalam rentang bulan dan tahun tertentu.
     * @param int $bulan
     * @param int $tahun
     * @param string|null $tahun_ajaran
     * @param string|null $semester
     * @return array
     */
    public function getLiburInMonth($bulan, $tahun, $tahun_ajaran = null, $semester = null)
    {
        $startDate = sprintf('%04d-%02d-01', $tahun, $bulan);
        $endDate = sprintf('%04d-%02d-%02d', $tahun, $bulan, cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun));
        
        $sql = "SELECT tanggal, keterangan FROM kalender_pendidikan WHERE tanggal BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
        
        if ($tahun_ajaran) {
            $sql .= " AND tahun_ajaran = ?";
            $params[] = $tahun_ajaran;
        }
        if ($semester) {
            $sql .= " AND semester = ?";
            $params[] = $semester;
        }
        
        $sql .= " ORDER BY tanggal ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}