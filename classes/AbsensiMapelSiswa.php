<?php
// project-root/classes/AbsensiMapelSiswa.php

class AbsensiMapelSiswa {
    private $pdo;
    private $table_name = "absensi_mapel_siswa";
    private $table_siswa = "siswa";
    private $table_kelas = "kelas";
    private $table_mapel = "mata_pelajaran"; // Asumsi nama tabel mata pelajaran
    private $table_guru = "guru"; // Asumsi nama tabel guru
    private $table_jadwal = "jadwal_pelajaran"; // Asumsi nama tabel jadwal pelajaran

    public function __construct(PDO $db) {
        $this->pdo = $db;
    }
    
    /**
     * Mencatat absensi siswa untuk mata pelajaran tertentu.
     *
     * @param int $siswa_id ID siswa.
     * @param int $jadwal_id ID jadwal pelajaran.
     * @param int $guru_id ID guru yang mencatat (bisa berbeda dari guru pengampu jadwal).
     * @param string $tanggal_absen Tanggal absensi (YYYY-MM-DD).
     * @param string $waktu_absen Waktu absensi (HH:MM:SS).
     * @param string $status_kehadiran Status kehadiran ('H', 'S', 'I', 'A').
     * @param string|null $catatan Catatan tambahan.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function recordAttendance($siswa_id, $jadwal_id, $guru_id, $tanggal_absen, $waktu_absen, $status_kehadiran, $catatan = null) {
        // Cek apakah absensi untuk siswa, jadwal, dan tanggal yang sama sudah ada
        $check_query = "SELECT id FROM " . $this->table_name . "
                        WHERE siswa_id = :siswa_id AND jadwal_id = :jadwal_id AND tanggal_absen = :tanggal_absen";
        $check_stmt = $this->pdo->prepare($check_query);
        $check_stmt->execute([
            ':siswa_id' => $siswa_id,
            ':jadwal_id' => $jadwal_id,
            ':tanggal_absen' => $tanggal_absen
        ]);
        $existing_record = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_record) {
            // Jika sudah ada, update record
            $query = "UPDATE " . $this->table_name . " SET
                        guru_id = :guru_id,
                        waktu_absen = :waktu_absen,
                        status_kehadiran = :status_kehadiran,
                        catatan = :catatan,
                        updated_at = CURRENT_TIMESTAMP
                      WHERE id = :id";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':id', $existing_record['id'], PDO::PARAM_INT);
        } else {
            // Jika belum ada, insert record baru
            $query = "INSERT INTO " . $this->table_name . " (siswa_id, jadwal_id, guru_id, tanggal_absen, waktu_absen, status_kehadiran, catatan)
                      VALUES (:siswa_id, :jadwal_id, :guru_id, :tanggal_absen, :waktu_absen, :status_kehadiran, :catatan)";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':siswa_id', $siswa_id, PDO::PARAM_INT);
            $stmt->bindParam(':jadwal_id', $jadwal_id, PDO::PARAM_INT);
            $stmt->bindParam(':tanggal_absen', $tanggal_absen);
        }
        
        $stmt->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
        $stmt->bindParam(':waktu_absen', $waktu_absen);
        $stmt->bindParam(':status_kehadiran', $status_kehadiran);
        $stmt->bindParam(':catatan', $catatan);

        return $stmt->execute();
    }
    
    // Tambahkan fungsi ini di dalam class AbsensiMapelSiswa:
    /**
     * Memperbarui status absensi siswa (audit) untuk jadwal dan tanggal tertentu.
     *
     * @param int $siswa_id ID siswa.
     * @param int $jadwal_id ID jadwal pelajaran.
     * @param string $tanggal_absen Tanggal absensi (YYYY-MM-DD).
     * @param string $status_kehadiran Status kehadiran ('H', 'S', 'I', 'A').
     * @param string|null $catatan Catatan tambahan.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function auditAttendance($siswa_id, $jadwal_id, $tanggal_absen, $status_kehadiran, $catatan) {
        // Fungsi ini diasumsikan HANYA MENGUPDATE data yang sudah ada.
        $query = "UPDATE " . $this->table_name . " 
                  SET status_kehadiran = :status_kehadiran, catatan = :catatan 
                  WHERE siswa_id = :siswa_id AND jadwal_id = :jadwal_id AND tanggal_absen = :tanggal_absen";
    
        $stmt = $this->pdo->prepare($query);
        return $stmt->execute([
            ':status_kehadiran' => $status_kehadiran,
            ':catatan' => $catatan,
            ':siswa_id' => $siswa_id,
            ':jadwal_id' => $jadwal_id,
            ':tanggal_absen' => $tanggal_absen
        ]);
    }

    /**
     * Mendapatkan absensi siswa untuk jadwal dan tanggal tertentu.
     * Digunakan untuk menampilkan status absensi saat guru ingin mencatat.
     *
     * @param int $jadwal_id ID jadwal pelajaran.
     * @param string $tanggal_absen Tanggal absensi (YYYY-MM-DD).
     * @return array Array asosiatif dari absensi siswa.
     */
    public function getAttendanceForSchedule($jadwal_id, $tanggal_absen) {
        $query = "SELECT ams.siswa_id, s.nama_lengkap, s.nisn, ams.status_kehadiran, ams.catatan
                  FROM " . $this->table_name . " ams
                  JOIN siswa s ON ams.siswa_id = s.id
                  WHERE ams.jadwal_id = :jadwal_id AND ams.tanggal_absen = :tanggal_absen
                  ORDER BY s.nama_lengkap ASC";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':jadwal_id', $jadwal_id, PDO::PARAM_INT);
        $stmt->bindParam(':tanggal_absen', $tanggal_absen);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mengambil laporan absensi mata pelajaran siswa berdasarkan filter.
     * Digunakan untuk tampilan harian dan ekspor harian.
     *
     * @param string $start_date Tanggal mulai (YYYY-MM-DD)
     * @param string $end_date Tanggal selesai (YYYY-MM-DD)
     * @param int|null $kelas_id ID Kelas (opsional)
     * @param int|null $mapel_id ID Mata Pelajaran (opsional)
     * @param int|null $guru_id ID Guru (opsional)
     * @return array Data laporan absensi
     */
    public function getStudentSubjectAttendanceReport($start_date, $end_date, $kelas_id = null, $mapel_id = null, $guru_id = null) {
        $query = "SELECT asm.id, asm.tanggal_absen, asm.waktu_absen, asm.status_kehadiran, asm.catatan,
                         s.nisn, s.nama_lengkap AS nama_siswa,
                         k.nama_kelas,
                         mp.nama_mapel,
                         g.nama_lengkap AS nama_guru
                  FROM " . $this->table_name . " asm
                  LEFT JOIN " . $this->table_siswa . " s ON asm.siswa_id = s.id
                  LEFT JOIN " . $this->table_kelas . " k ON s.kelas_id = k.id
                  LEFT JOIN " . $this->table_jadwal . " jp ON asm.jadwal_id = jp.id
                  LEFT JOIN " . $this->table_mapel . " mp ON jp.mapel_id = mp.id
                  LEFT JOIN " . $this->table_guru . " g ON jp.guru_id = g.id
                  WHERE asm.tanggal_absen BETWEEN :start_date AND :end_date";

        $params = [
            ':start_date' => $start_date,
            ':end_date' => $end_date
        ];

        if ($kelas_id) {
            $query .= " AND s.kelas_id = :kelas_id";
            $params[':kelas_id'] = $kelas_id;
        }
        if ($mapel_id) {
            $query .= " AND jp.mapel_id = :mapel_id";
            $params[':mapel_id'] = $mapel_id;
        }
        if ($guru_id) {
            $query .= " AND jp.guru_id = :guru_id";
            $params[':guru_id'] = $guru_id;
        }

        $query .= " ORDER BY asm.tanggal_absen ASC, k.nama_kelas ASC, mp.nama_mapel ASC, s.nama_lengkap ASC";

        $stmt = $this->pdo->prepare($query);
        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mengambil laporan absensi mata pelajaran siswa per bulan.
     * Mengembalikan rekap status kehadiran per siswa, per mata pelajaran, per guru, per hari dalam bulan.
     *
     * @param string $month Bulan (format 'MM')
     * @param string $year Tahun (format 'YYYY')
     * @param int|null $kelas_id ID Kelas (opsional)
     * @param int|null $mapel_id ID Mata Pelajaran (opsional)
     * @param int|null $guru_id ID Guru (opsional)
     * @return array Data laporan bulanan
     */
    public function getMonthlyStudentSubjectAttendanceReport($month, $year, $kelas_id = null, $mapel_id = null, $guru_id = null) {
        $start_date_full = $year . '-' . $month . '-01 00:00:00';
        $end_date_full = date('Y-m-t 23:59:59', strtotime($year . '-' . $month . '-01'));
        $num_days = cal_days_in_month(CAL_GREGORIAN, (int)$month, (int)$year);

        // Query untuk mendapatkan semua kombinasi siswa, kelas, mapel, guru yang relevan
        $query_base = "SELECT s.id as id_siswa_pk, s.nisn, s.nama_lengkap AS nama_siswa,
                              k.nama_kelas,
                              mp.id as id_mapel_pk, mp.nama_mapel,
                              g.id as id_guru_pk, g.nama_lengkap AS nama_guru
                       FROM " . $this->table_siswa . " s
                       LEFT JOIN " . $this->table_kelas . " k ON s.kelas_id = k.id
                       LEFT JOIN " . $this->table_jadwal . " jp ON s.kelas_id = jp.kelas_id " . // Join jadwal untuk filter mapel/guru
                       "LEFT JOIN " . $this->table_mapel . " mp ON jp.mapel_id = mp.id
                       LEFT JOIN " . $this->table_guru . " g ON jp.guru_id = g.id
                       WHERE 1=1"; // Placeholder for dynamic WHERE clauses

        $params_base = [];

        if ($kelas_id) {
            $query_base .= " AND s.kelas_id = :kelas_id";
            $params_base[':kelas_id'] = $kelas_id;
        }
        if ($mapel_id) {
            $query_base .= " AND jp.mapel_id = :mapel_id";
            $params_base[':mapel_id'] = $mapel_id;
        }
        if ($guru_id) {
            $query_base .= " AND jp.guru_id = :guru_id";
            $params_base[':guru_id'] = $guru_id;
        }
        
        // Group by untuk mendapatkan kombinasi unik siswa-mapel-guru
        $query_base .= " GROUP BY s.id, mp.id, g.id
                         ORDER BY k.nama_kelas, mp.nama_mapel, g.nama_lengkap, s.nama_lengkap";

        $stmt_base = $this->pdo->prepare($query_base);
        foreach ($params_base as $key => &$val) {
            $stmt_base->bindParam($key, $val);
        }
        $stmt_base->execute();
        $combinations = $stmt_base->fetchAll(PDO::FETCH_ASSOC);

        $report_data = [];

        foreach ($combinations as $combo) {
            $combo_data = [
                'id_siswa' => $combo['id_siswa_pk'],
                'nisn' => $combo['nisn'],
                'nama_siswa' => $combo['nama_siswa'],
                'nama_kelas' => $combo['nama_kelas'],
                'id_mapel' => $combo['id_mapel_pk'],
                'nama_mapel' => $combo['nama_mapel'],
                'id_guru' => $combo['id_guru_pk'],
                'nama_guru' => $combo['nama_guru'],
                'daily_statuses' => [] // Status kehadiran per hari
            ];

            // Inisialisasi status 'Alpha' untuk setiap hari dalam bulan
            for ($d = 1; $d <= $num_days; $d++) {
                $date_key = $year . '-' . $month . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
                $combo_data['daily_statuses'][$date_key] = 'A'; // Default Alpha
            }

            // Ambil data absensi spesifik untuk kombinasi ini di bulan ini
            $query_absensi = "SELECT DATE(tanggal_absen) as tanggal, status_kehadiran
                              FROM " . $this->table_name . " asm
                              LEFT JOIN " . $this->table_jadwal . " jp ON asm.jadwal_id = jp.id
                              WHERE asm.siswa_id = :siswa_id
                              AND jp.mapel_id = :mapel_id
                              AND jp.guru_id = :guru_id
                              AND asm.tanggal_absen BETWEEN :start_date AND :end_date
                              GROUP BY DATE(asm.tanggal_absen)
                              ORDER BY asm.tanggal_absen ASC";

            $stmt_absensi = $this->pdo->prepare($query_absensi);
            $stmt_absensi->bindParam(':siswa_id', $combo['id_siswa_pk']);
            $stmt_absensi->bindParam(':mapel_id', $combo['id_mapel_pk']);
            $stmt_absensi->bindParam(':guru_id', $combo['id_guru_pk']);
            $stmt_absensi->bindParam(':start_date', $start_date_full);
            $stmt_absensi->bindParam(':end_date', $end_date_full);
            $stmt_absensi->execute();
            $attendances = $stmt_absensi->fetchAll(PDO::FETCH_ASSOC);

            // Perbarui status harian berdasarkan data absensi yang ditemukan
            foreach ($attendances as $att) {
                $date_key = $att['tanggal'];
                $combo_data['daily_statuses'][$date_key] = $att['status_kehadiran'];
            }
            $report_data[] = $combo_data;
        }
        return $report_data;
    }
    
    /**
     * Mendapatkan absensi yang sudah tercatat untuk jadwal dan tanggal tertentu.
     * @param int $jadwalId ID jadwal pelajaran.
     * @param string $tanggal Tanggal absensi (format 'Y-m-d').
     * @return array Daftar absensi dengan siswa_id sebagai kunci.
     */
    public function getByJadwalIdAndTanggal($jadwalId, $tanggal) {
        $query = "SELECT siswa_id, status_kehadiran as status, catatan FROM " . $this->table_name . " WHERE jadwal_id = :jadwal_id AND tanggal_absen = :tanggal_absen";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':jadwal_id', $jadwalId, PDO::PARAM_INT);
        $stmt->bindParam(':tanggal_absen', $tanggal);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Mengubah array menjadi array asosiatif dengan siswa_id sebagai kunci
        $attendance_map = [];
        foreach ($results as $row) {
            $attendance_map[$row['siswa_id']] = $row;
        }
        
        return $attendance_map;
    }
    
    /**
     * Mendapatkan semua absensi mata pelajaran untuk seorang siswa dalam rentang tanggal tertentu.
     * @param int $siswaId ID siswa.
     * @param string $startDate Tanggal mulai (format 'Y-m-d').
     * @param string $endDate Tanggal akhir (format 'Y-m-d').
     * @return array Data absensi mata pelajaran yang digabungkan.
     */
    public function getAbsensiMapelBySiswaId($siswaId, $startDate, $endDate) {
        $query = "SELECT
                    ams.tanggal_absen,
                    ams.waktu_absen,
                    ams.status_kehadiran,
                    ams.catatan,
                    mp.nama_mapel,
                    g.nama_lengkap AS nama_guru
                  FROM
                    " . $this->table_name . " ams
                  JOIN
                    " . $this->table_jadwal . " jp ON ams.jadwal_id = jp.id
                  JOIN
                    " . $this->table_mapel . " mp ON jp.mapel_id = mp.id
                  JOIN
                    " . $this->table_guru . " g ON ams.guru_id = g.id
                  WHERE
                    ams.siswa_id = :siswa_id
                    AND ams.tanggal_absen BETWEEN :start_date AND :end_date
                  ORDER BY
                    ams.tanggal_absen DESC, ams.waktu_absen DESC";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':siswa_id', $siswaId, PDO::PARAM_INT);
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}