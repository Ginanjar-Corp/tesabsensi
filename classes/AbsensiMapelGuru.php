<?php
// project-root/classes/AbsensiMapelGuru.php

class AbsensiMapelGuru {
    private $pdo;
    private $table_name = "absensi_mapel_guru";

    public function __construct(PDO $db) {
        $this->pdo = $db;
    }

    /**
     * Mencatat atau memperbarui konfirmasi pengajaran guru untuk jadwal dan tanggal tertentu.
     * Ini menandakan bahwa guru telah memulai sesi pengajaran dan mencatat absensi.
     *
     * @param int $guru_id ID guru.
     * @param int $jadwal_id ID jadwal pelajaran.
     * @param string $tanggal_ajar Tanggal mengajar (YYYY-MM-DD).
     * @param string $waktu_mulai_ajar Waktu mulai mengajar (HH:MM:SS).
     * @return bool True jika berhasil, false jika gagal.
     */
    public function recordTeachingConfirmation($guru_id, $jadwal_id, $tanggal_ajar, $waktu_mulai_ajar) {
        // Cek apakah sudah ada konfirmasi untuk guru, jadwal, dan tanggal yang sama
        $check_query = "SELECT id FROM " . $this->table_name . "
                        WHERE guru_id = :guru_id AND jadwal_id = :jadwal_id AND tanggal_ajar = :tanggal_ajar";
        $check_stmt = $this->pdo->prepare($check_query);
        $check_stmt->execute([
            ':guru_id' => $guru_id,
            ':jadwal_id' => $jadwal_id,
            ':tanggal_ajar' => $tanggal_ajar
        ]);
        $existing_record = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_record) {
            // Jika sudah ada, update waktu_mulai_ajar (jika perlu) dan updated_at
            $query = "UPDATE " . $this->table_name . " SET
                        waktu_mulai_ajar = :waktu_mulai_ajar,
                        updated_at = CURRENT_TIMESTAMP
                      WHERE id = :id";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':id', $existing_record['id'], PDO::PARAM_INT);
        } else {
            // Jika belum ada, insert record baru
            $query = "INSERT INTO " . $this->table_name . " (guru_id, jadwal_id, tanggal_ajar, waktu_mulai_ajar)
                      VALUES (:guru_id, :jadwal_id, :tanggal_ajar, :waktu_mulai_ajar)";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
            $stmt->bindParam(':jadwal_id', $jadwal_id, PDO::PARAM_INT);
            $stmt->bindParam(':tanggal_ajar', $tanggal_ajar);
        }
        
        $stmt->bindParam(':waktu_mulai_ajar', $waktu_mulai_ajar);

        return $stmt->execute();
    }

    /**
     * Memperbarui waktu selesai mengajar untuk konfirmasi yang sudah ada.
     *
     * @param int $guru_id ID guru.
     * @param int $jadwal_id ID jadwal pelajaran.
     * @param string $tanggal_ajar Tanggal mengajar (YYYY-MM-DD).
     * @param string $waktu_selesai_ajar Waktu selesai mengajar (HH:MM:SS).
     * @return bool True jika berhasil, false jika gagal.
     */
    public function updateTeachingEndTime($guru_id, $jadwal_id, $tanggal_ajar, $waktu_selesai_ajar) {
        $query = "UPDATE " . $this->table_name . " SET
                    waktu_selesai_ajar = :waktu_selesai_ajar,
                    updated_at = CURRENT_TIMESTAMP
                  WHERE guru_id = :guru_id AND jadwal_id = :jadwal_id AND tanggal_ajar = :tanggal_ajar";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':waktu_selesai_ajar', $waktu_selesai_ajar);
        $stmt->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
        $stmt->bindParam(':jadwal_id', $jadwal_id, PDO::PARAM_INT);
        $stmt->bindParam(':tanggal_ajar', $tanggal_ajar);

        return $stmt->execute();
    }
    
     /**
     * Mencatat atau memperbarui konfirmasi pengajaran GURU yang diaudit oleh Admin/TU.
     * Menggunakan tanggal_ajar yang disediakan (untuk backdating).
     *
     * @param int $guru_id ID guru pengampu.
     * @param int $jadwal_id ID jadwal pelajaran.
     * @param string $tanggal_ajar Tanggal mengajar (YYYY-MM-DD).
     * @param string $waktu_mulai_ajar Waktu mulai mengajar (HH:MM:SS).
     * @return bool True jika berhasil, false jika gagal.
     */
    // Di dalam class AbsensiMapelGuru
    public function recordAuditTeachingConfirmation($guru_id, $jadwal_id, $tanggal_ajar, $waktu_mulai_ajar) {
        // Cek apakah sudah ada konfirmasi untuk guru, jadwal, dan tanggal yang sama
        $check_query = "SELECT id FROM " . $this->table_name . "
                        WHERE guru_id = :guru_id AND jadwal_id = :jadwal_id AND tanggal_ajar = :tanggal_ajar";
        $check_stmt = $this->pdo->prepare($check_query);
        $check_stmt->execute([
            ':guru_id' => $guru_id,
            ':jadwal_id' => $jadwal_id,
            ':tanggal_ajar' => $tanggal_ajar
        ]);
        $existing_record = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
        if ($existing_record) {
            // Jika sudah ada, update waktu_mulai_ajar dan updated_at
            $query = "UPDATE " . $this->table_name . " SET
                        waktu_mulai_ajar = :waktu_mulai_ajar,
                        updated_at = CURRENT_TIMESTAMP
                      WHERE id = :id";
            $stmt = $this->pdo->prepare($query);
            return $stmt->execute([
                ':waktu_mulai_ajar' => $waktu_mulai_ajar,
                ':id' => $existing_record['id']
            ]);
        } else {
            // Jika belum ada, insert record baru
            $query = "INSERT INTO " . $this->table_name . " (guru_id, jadwal_id, tanggal_ajar, waktu_mulai_ajar)
                      VALUES (:guru_id, :jadwal_id, :tanggal_ajar, :waktu_mulai_ajar)";
            $stmt = $this->pdo->prepare($query);
            return $stmt->execute([
                ':guru_id' => $guru_id,
                ':jadwal_id' => $jadwal_id,
                ':tanggal_ajar' => $tanggal_ajar,
                ':waktu_mulai_ajar' => $waktu_mulai_ajar
            ]);
        }
    }


    // Tambahkan fungsi ini di dalam class AbsensiMapelGuru:
    /**
     * Memperbarui waktu selesai ajar oleh Admin/TU (audit) untuk jadwal dan tanggal tertentu.
     * Hanya akan memperbarui jika waktu_selesai_ajar sebelumnya masih NULL.
     *
     * @param int $guru_id ID guru pengampu.
     * @param int $jadwal_id ID jadwal pelajaran.
     * @param string $tanggal_ajar Tanggal mengajar (YYYY-MM-DD).
     * @param string $waktu_selesai_ajar Waktu selesai mengajar (HH:MM:SS).
     * @return bool True jika berhasil, false jika gagal.
     */
    public function updateAuditTeachingEndTime($guru_id, $jadwal_id, $tanggal_ajar, $waktu_selesai_ajar) {
        // Audit harus menggunakan klausa WHERE yang ketat, termasuk tanggal ajar.
        $query = "UPDATE " . $this->table_name . " 
                  SET waktu_selesai_ajar = :waktu_selesai_ajar 
                  WHERE guru_id = :guru_id AND jadwal_id = :jadwal_id AND tanggal_ajar = :tanggal_ajar AND waktu_selesai_ajar IS NULL";
    
        $stmt = $this->pdo->prepare($query);
        return $stmt->execute([
            ':waktu_selesai_ajar' => $waktu_selesai_ajar,
            ':guru_id' => $guru_id,
            ':jadwal_id' => $jadwal_id,
            ':tanggal_ajar' => $tanggal_ajar
        ]);
    }

    /**
     * Mendapatkan konfirmasi pengajaran guru untuk jadwal dan tanggal tertentu.
     *
     * @param int $guru_id ID guru.
     * @param int $jadwal_id ID jadwal pelajaran.
     * @param string $tanggal_ajar Tanggal mengajar (YYYY-MM-DD).
     * @return array|false Data konfirmasi jika ditemukan, false jika tidak.
     */
    public function getTeachingConfirmation($guru_id, $jadwal_id, $tanggal_ajar) {
        $query = "SELECT id, waktu_mulai_ajar, waktu_selesai_ajar FROM " . $this->table_name . "
                  WHERE guru_id = :guru_id AND jadwal_id = :jadwal_id AND tanggal_ajar = :tanggal_ajar LIMIT 0,1";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
        $stmt->bindParam(':jadwal_id', $jadwal_id, PDO::PARAM_INT);
        $stmt->bindParam(':tanggal_ajar', $tanggal_ajar);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Mendapatkan laporan konfirmasi pengajaran guru berdasarkan filter.
     *
     * @param string $start_date Tanggal mulai (YYYY-MM-DD).
     * @param string $end_date Tanggal selesai (YYYY-MM-DD).
     * @param int|null $guru_id ID guru (opsional).
     * @param int|null $mapel_id ID mata pelajaran (opsional).
     * @param int|null $kelas_id ID kelas (opsional).
     * @return array Array asosiatif data laporan.
     */
    public function getGuruSubjectTeachingReport($start_date, $end_date, $guru_id = null, $mapel_id = null, $kelas_id = null) {
        $query = "SELECT
                    g.nip,
                    g.nama_lengkap AS nama_guru,
                    COALESCE(k.nama_kelas, 'Multiple') AS nama_kelas,
                    mp.nama_mapel,
                    jp.hari,
                    jp.jam_mulai AS jadwal_mulai,
                    jp.jam_selesai AS jadwal_selesai,
                    amg.tanggal_ajar,
                    amg.waktu_mulai_ajar,
                    amg.waktu_selesai_ajar
                  FROM
                    " . $this->table_name . " amg
                  JOIN
                    guru g ON amg.guru_id = g.id
                  JOIN
                    jadwal_pelajaran jp ON amg.jadwal_id = jp.id
                  LEFT JOIN
                    kelas k ON jp.kelas_id = k.id
                  JOIN
                    mata_pelajaran mp ON jp.mapel_id = mp.id
                  WHERE
                    amg.tanggal_ajar BETWEEN :start_date AND :end_date";
        
        $params = [
            ':start_date' => $start_date,
            ':end_date' => $end_date
        ];

        if ($guru_id !== null && $guru_id > 0) {
            $query .= " AND amg.guru_id = :guru_id";
            $params[':guru_id'] = $guru_id;
        }
        if ($mapel_id !== null && $mapel_id > 0) {
            $query .= " AND jp.mapel_id = :mapel_id";
            $params[':mapel_id'] = $mapel_id;
        }
        if ($kelas_id !== null && $kelas_id > 0) {
            $query .= " AND jp.kelas_id = :kelas_id";
            $params[':kelas_id'] = $kelas_id;
        }

        $query .= " ORDER BY amg.tanggal_ajar DESC, g.nama_lengkap, nama_kelas, mp.nama_mapel ASC";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Mendapatkan data absensi guru berdasarkan ID jadwal dan tanggal.
     *
     * @param int $jadwal_id ID jadwal pelajaran.
     * @param string $tanggal_ajar Tanggal mengajar (YYYY-MM-DD).
     * @return array|false Data absensi jika ditemukan, false jika tidak.
     */
    public function getAbsensiByJadwalAndDate($jadwal_id, $tanggal_ajar) {
        $query = "SELECT id, waktu_mulai_ajar, waktu_selesai_ajar FROM " . $this->table_name . "
                  WHERE jadwal_id = :jadwal_id AND tanggal_ajar = :tanggal_ajar
                  LIMIT 1";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':jadwal_id', $jadwal_id, PDO::PARAM_INT);
        $stmt->bindParam(':tanggal_ajar', $tanggal_ajar, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
