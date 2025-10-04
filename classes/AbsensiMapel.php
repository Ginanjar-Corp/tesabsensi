<?php
// project-root/classes/AbsensiMapel.php

class AbsensiMapel {
    private $pdo;

    public function __construct(PDO $db) {
        $this->pdo = $db;
    }

    /**
     * Mencatat absensi siswa untuk mata pelajaran tertentu.
     *
     * @param int $siswa_id ID siswa.
     * @param int $jadwal_id ID jadwal pelajaran.
     * @param int $guru_id ID guru yang mencatat.
     * @param string $status_kehadiran Status kehadiran ('H', 'S', 'I', 'A').
     * @param string|null $catatan Catatan tambahan.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function recordSiswaAbsence($siswa_id, $jadwal_id, $guru_id, $status_kehadiran, $catatan = null) {
        $tanggal_absen = date('Y-m-d');
        $waktu_absen = date('H:i:s');

        // Cek apakah sudah ada record absensi untuk siswa ini pada sesi jadwal ini hari ini
        $query_check = "SELECT id FROM absensi_mapel_siswa 
                        WHERE siswa_id = :siswa_id AND jadwal_id = :jadwal_id AND tanggal_absen = :tanggal_absen LIMIT 0,1";
        $stmt_check = $this->pdo->prepare($query_check);
        $stmt_check->bindParam(':siswa_id', $siswa_id, PDO::PARAM_INT);
        $stmt_check->bindParam(':jadwal_id', $jadwal_id, PDO::PARAM_INT);
        $stmt_check->bindParam(':tanggal_absen', $tanggal_absen);
        $stmt_check->execute();
        $record_exists = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($record_exists) {
            // Update record yang sudah ada
            $query = "UPDATE absensi_mapel_siswa SET
                      status_kehadiran = :status_kehadiran,
                      catatan = :catatan,
                      updated_at = CURRENT_TIMESTAMP
                      WHERE id = :id";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':id', $record_exists['id'], PDO::PARAM_INT);
        } else {
            // Buat record baru
            $query = "INSERT INTO absensi_mapel_siswa (siswa_id, jadwal_id, guru_id, tanggal_absen, waktu_absen, status_kehadiran, catatan)
                      VALUES (:siswa_id, :jadwal_id, :guru_id, :tanggal_absen, :waktu_absen, :status_kehadiran, :catatan)";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':siswa_id', $siswa_id, PDO::PARAM_INT);
            $stmt->bindParam(':jadwal_id', $jadwal_id, PDO::PARAM_INT);
            $stmt->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
            $stmt->bindParam(':tanggal_absen', $tanggal_absen);
            $stmt->bindParam(':waktu_absen', $waktu_absen);
        }
        
        $stmt->bindParam(':status_kehadiran', $status_kehadiran);
        $stmt->bindParam(':catatan', $catatan);

        return $stmt->execute();
    }

    /**
     * Mencatat konfirmasi pengajaran guru untuk jadwal tertentu.
     * Jika sudah ada record untuk hari ini, update waktu selesai ajar.
     * Jika belum ada, buat record baru dengan waktu mulai ajar.
     *
     * @param int $guru_id ID guru.
     * @param int $jadwal_id ID jadwal pelajaran.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function recordGuruTeachingConfirmation($guru_id, $jadwal_id) {
        $tanggal_ajar = date('Y-m-d');
        $waktu_sekarang = date('H:i:s');

        // Cek apakah sudah ada record konfirmasi untuk guru ini pada jadwal ini hari ini
        $query_check = "SELECT id, waktu_mulai_ajar FROM absensi_mapel_guru 
                        WHERE guru_id = :guru_id AND jadwal_id = :jadwal_id AND tanggal_ajar = :tanggal_ajar LIMIT 0,1";
        $stmt_check = $this->pdo->prepare($query_check);
        $stmt_check->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
        $stmt_check->bindParam(':jadwal_id', $jadwal_id, PDO::PARAM_INT);
        $stmt_check->bindParam(':tanggal_ajar', $tanggal_ajar);
        $stmt_check->execute();
        $record = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($record) {
            // Update waktu selesai ajar
            $query = "UPDATE absensi_mapel_guru SET waktu_selesai_ajar = :waktu_selesai_ajar, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':waktu_selesai_ajar', $waktu_sekarang);
            $stmt->bindParam(':id', $record['id'], PDO::PARAM_INT);
        } else {
            // Buat record baru
            $query = "INSERT INTO absensi_mapel_guru (guru_id, jadwal_id, tanggal_ajar, waktu_mulai_ajar)
                      VALUES (:guru_id, :jadwal_id, :tanggal_ajar, :waktu_mulai_ajar)";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
            $stmt->bindParam(':jadwal_id', $jadwal_id, PDO::PARAM_INT);
            $stmt->bindParam(':tanggal_ajar', $tanggal_ajar);
            $stmt->bindParam(':waktu_mulai_ajar', $waktu_sekarang);
        }
        
        return $stmt->execute();
    }

    /**
     * Mendapatkan status absensi siswa untuk jadwal tertentu pada hari ini.
     * Digunakan untuk mengisi form absensi dengan data yang sudah ada.
     *
     * @param int $jadwal_id ID jadwal pelajaran.
     * @param string $tanggal Tanggal absensi.
     * @return array Array asosiatif (siswa_id => status_kehadiran)
     */
    public function getSiswaAbsenceStatusForSchedule($jadwal_id, $tanggal) {
        $query = "SELECT siswa_id, status_kehadiran, catatan 
                  FROM absensi_mapel_siswa 
                  WHERE jadwal_id = :jadwal_id AND tanggal_absen = :tanggal";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':jadwal_id', $jadwal_id, PDO::PARAM_INT);
        $stmt->bindParam(':tanggal', $tanggal);
        $stmt->execute();
        
        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[$row['siswa_id']] = [
                'status' => $row['status_kehadiran'],
                'catatan' => $row['catatan']
            ];
        }
        return $results;
    }

    /**
     * Mendapatkan laporan absensi mata pelajaran siswa yang diajar oleh guru tertentu.
     *
     * @param int $guru_id ID guru.
     * @param string $start_date Tanggal mulai (YYYY-MM-DD).
     * @param string $end_date Tanggal selesai (YYYY-MM-DD).
     * @param int|null $kelas_id Filter berdasarkan kelas_id (opsional).
     * @param int|null $mapel_id Filter berdasarkan mapel_id (opsional).
     * @param string|null $status_kehadiran Filter berdasarkan status kehadiran (opsional, 'H', 'S', 'I', 'A').
     * @return array Array asosiatif dari data laporan.
     */
    public function getStudentAttendanceReportForTeacher($guru_id, $start_date, $end_date, $kelas_id = null, $mapel_id = null, $status_kehadiran = null) {
        $query = "SELECT
                    ams.tanggal_absen,
                    ams.waktu_absen,
                    ams.status_kehadiran,
                    ams.catatan,
                    s.nisn,
                    s.nama_lengkap AS nama_siswa,
                    COALESCE(k.nama_kelas, 'Multiple') AS nama_kelas,
                    mp.nama_mapel,
                    jp.jam_mulai,
                    jp.jam_selesai,
                    jp.hari
                  FROM
                    absensi_mapel_siswa ams
                  JOIN
                    siswa s ON ams.siswa_id = s.id
                  JOIN
                    jadwal_pelajaran jp ON ams.jadwal_id = jp.id
                  LEFT JOIN
                    kelas k ON jp.kelas_id = k.id
                  JOIN
                    mata_pelajaran mp ON jp.mapel_id = mp.id
                  WHERE
                    ams.guru_id = :guru_id AND ams.tanggal_absen BETWEEN :start_date AND :end_date";
        
        if ($kelas_id) {
            $query .= " AND jp.kelas_id = :kelas_id";
        }
        if ($mapel_id) {
            $query .= " AND jp.mapel_id = :mapel_id";
        }
        if ($status_kehadiran) {
            $query .= " AND ams.status_kehadiran = :status_kehadiran";
        }

        $query .= " ORDER BY ams.tanggal_absen DESC, jp.jam_mulai ASC, s.nama_lengkap ASC";

        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);

        if ($kelas_id) {
            $stmt->bindParam(':kelas_id', $kelas_id, PDO::PARAM_INT);
        }
        if ($mapel_id) {
            $stmt->bindParam(':mapel_id', $mapel_id, PDO::PARAM_INT);
        }
        if ($status_kehadiran) {
            $stmt->bindParam(':status_kehadiran', $status_kehadiran);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
