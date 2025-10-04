<?php
// project-root/classes/AbsensiGerbang.php

require_once __DIR__ . '/Siswa.php'; // Pastikan kelas Siswa tersedia
require_once __DIR__ . '/Guru.php';   // Pastikan kelas Guru tersedia

class AbsensiGerbang {
    private $pdo;
    private $table_name = "absensi_harian_siswa";
    private $table_absensi_guru = "absensi_harian_guru"; // Asumsi tabel absensi guru adalah absensi_gerbang
    private $table_siswa = "siswa";
    private $table_kelas = "kelas";
    private $table_guru = "guru";

    public function __construct(PDO $db) {
        $this->pdo = $db;
    }

    /**
     * Mencatat absensi masuk atau pulang siswa/guru ke tabel absensi_harian_siswa atau absensi_harian_guru.
     *
     * @param string $rfid_tag RFID tag siswa atau guru.
     * @param string $tipe 'masuk' atau 'pulang'.
     * @param string $jenis_pengguna 'siswa' atau 'guru'.
     * @param int $recorded_by_user_id ID user yang melakukan scan (admin/petugas).
     * @return array Hasil operasi (status, message, data).
     */
    public function recordAbsence($rfid_tag, $tipe, $jenis_pengguna, $recorded_by_user_id) {
        $this->pdo->beginTransaction();
        try {
            $related_id = null;
            $nama_pengguna = '';
            $table_name = '';
            $id_column = '';

            if ($jenis_pengguna == 'siswa') {
                $siswa_model = new Siswa($this->pdo);
                $data = $siswa_model->findByRfidTag($rfid_tag);
                if ($data) {
                    $related_id = $data['id'];
                    $nama_pengguna = $data['nama_lengkap'];
                    $table_name = 'absensi_harian_siswa';
                    $id_column = 'siswa_id';
                } else {
                    return ['status' => 'error', 'message' => 'RFID tag siswa tidak ditemukan atau siswa tidak aktif.'];
                }
            } elseif ($jenis_pengguna == 'guru') {
                $guru_model = new Guru($this->pdo);
                $data = $guru_model->findByRfidTag($rfid_tag);
                if ($data) {
                    $related_id = $data['id'];
                    $nama_pengguna = $data['nama_lengkap'];
                    $table_name = 'absensi_harian_guru';
                    $id_column = 'guru_id';
                } else {
                    return ['status' => 'error', 'message' => 'RFID tag guru tidak ditemukan atau guru tidak aktif.'];
                }
            } else {
                return ['status' => 'error', 'message' => 'Jenis pengguna tidak valid.'];
            }

            $tanggal = date('Y-m-d');
            $jam_sekarang = date('H:i:s');

            // Cek apakah sudah ada record untuk hari ini
            $check_query = "SELECT id, waktu_masuk, waktu_pulang FROM {$table_name}
                            WHERE {$id_column} = :related_id AND tanggal = :tanggal";
            $check_stmt = $this->pdo->prepare($check_query);
            $check_stmt->execute([
                ':related_id' => $related_id,
                ':tanggal' => $tanggal
            ]);
            $existing_record = $check_stmt->fetch(PDO::FETCH_ASSOC);

            $status_kehadiran = 'success';
            $message = '';

            if ($tipe == 'masuk') {
                if ($existing_record) {
                    if ($existing_record['waktu_masuk']) {
                        $status_kehadiran = 'warning';
                        $message = 'Absensi masuk untuk ' . $nama_pengguna . ' sudah tercatat hari ini pada ' . substr($existing_record['waktu_masuk'], 0, 5) . '.';
                    } else {
                        // Update waktu_masuk
                        $update_query = "UPDATE {$table_name} SET waktu_masuk = :jam, status_masuk = 'Hadir', updated_at = CURRENT_TIMESTAMP WHERE id = :id";
                        $update_stmt = $this->pdo->prepare($update_query);
                        $success = $update_stmt->execute([':jam' => $jam_sekarang, ':id' => $existing_record['id']]);
                        if ($success) {
                            $message = 'Absensi masuk untuk ' . $nama_pengguna . ' berhasil dicatat pada ' . substr($jam_sekarang, 0, 5) . '.';
                        } else {
                            $status_kehadiran = 'error';
                            $message = 'Gagal memperbarui absensi masuk ke database.';
                        }
                    }
                } else {
                    // Insert baru untuk absensi masuk
                    $insert_query = "INSERT INTO {$table_name} ({$id_column}, tanggal, waktu_masuk, status_masuk)
                                     VALUES (:related_id, :tanggal, :jam, 'Hadir')";
                    $insert_stmt = $this->pdo->prepare($insert_query);
                    $success = $insert_stmt->execute([':related_id' => $related_id, ':tanggal' => $tanggal, ':jam' => $jam_sekarang]);
                    if ($success) {
                        $message = 'Absensi masuk untuk ' . $nama_pengguna . ' berhasil dicatat pada ' . substr($jam_sekarang, 0, 5) . '.';
                    } else {
                        $status_kehadiran = 'error';
                        $message = 'Gagal menyimpan absensi masuk ke database.';
                    }
                }
            } elseif ($tipe == 'pulang') {
                if ($existing_record) {
                    if ($existing_record['waktu_pulang']) {
                        $status_kehadiran = 'warning';
                        $message = 'Absensi pulang untuk ' . $nama_pengguna . ' sudah tercatat hari ini pada ' . substr($existing_record['waktu_pulang'], 0, 5) . '.';
                    } else {
                        // Update waktu_pulang
                        $update_query = "UPDATE {$table_name} SET waktu_pulang = :jam, status_pulang = 'Pulang', updated_at = CURRENT_TIMESTAMP WHERE id = :id";
                        $update_stmt = $this->pdo->prepare($update_query);
                        $success = $update_stmt->execute([':jam' => $jam_sekarang, ':id' => $existing_record['id']]);
                        if ($success) {
                            $message = 'Absensi pulang untuk ' . $nama_pengguna . ' berhasil dicatat pada ' . substr($jam_sekarang, 0, 5) . '.';
                        } else {
                            $status_kehadiran = 'error';
                            $message = 'Gagal memperbarui absensi pulang ke database.';
                        }
                    }
                } else {
                    // Insert baru untuk absensi pulang (jika belum ada record masuk)
                    $insert_query = "INSERT INTO {$table_name} ({$id_column}, tanggal, waktu_pulang, status_pulang, status_masuk)
                                     VALUES (:related_id, :tanggal, :jam, 'Pulang', 'Tidak Absen')"; // Default status_masuk jika hanya absen pulang
                    $insert_stmt = $this->pdo->prepare($insert_query);
                    $success = $insert_stmt->execute([':related_id' => $related_id, ':tanggal' => $tanggal, ':jam' => $jam_sekarang]);
                    if ($success) {
                        $message = 'Absensi pulang untuk ' . $nama_pengguna . ' berhasil dicatat pada ' . substr($jam_sekarang, 0, 5) . '.';
                    } else {
                        $status_kehadiran = 'error';
                        $message = 'Gagal menyimpan absensi pulang ke database.';
                    }
                }
            } else {
                $status_kehadiran = 'error';
                $message = 'Tipe absensi tidak valid.';
            }

            $this->pdo->commit();
            return ['status' => $status_kehadiran, 'message' => $message, 'data' => ['nama_pengguna' => $nama_pengguna, 'tipe' => $tipe, 'jam' => $jam_sekarang]];

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Absensi Gerbang Error: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Terjadi kesalahan database: ' . $e->getMessage()];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Absensi Gerbang General Error: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()];
        }
    }

    /**
     * Mendapatkan laporan absensi gerbang harian untuk siswa dari absensi_harian_siswa.
     *
     * @param string $tanggal Tanggal laporan (YYYY-MM-DD).
     * @return array Array asosiatif data laporan.
     */
     public function getDailyStudentGateAttendanceReport($date, $tahunAjaran = null, $semester = null, $kelasId = null) {
        try {
            $sql = "SELECT
                        s.nisn,
                        s.nama_lengkap,
                        k.nama_kelas,
                        ahs.waktu_masuk AS jam_masuk,
                        ahs.waktu_pulang AS jam_pulang,
                        ahs.status_masuk,
                        ahs.status_pulang
                    FROM
                        siswa s
                    JOIN
                        kelas k ON s.kelas_id = k.id
                    LEFT JOIN
                        absensi_harian_siswa ahs ON s.id = ahs.siswa_id
                        AND ahs.tanggal = :date"; // Filter tanggal tetap di JOIN untuk kecocokan absensi harian

            $params = [':date' => $date];

            // Tambahkan filter tahun ajaran dan semester ke klausa ON dari LEFT JOIN
            // Asumsi kolom tahun_ajaran dan semester ada di tabel 'absensi_harian_siswa' (ahs)
            if ($tahunAjaran) {
                $sql .= " AND ahs.tahun_ajaran = :tahun_ajaran";
                $params[':tahun_ajaran'] = $tahunAjaran;
            }

            if ($semester) {
                $sql .= " AND ahs.semester = :semester";
                $params[':semester'] = $semester;
            }
            
            // Tambahkan filter kelas_id ke klausa ON dari LEFT JOIN
            // Asumsi kolom kelas_id ada di tabel 'absensi_harian_siswa' (ahs)
            if ($kelasId) {
                $sql .= " AND ahs.kelas_id = :kelas_id";
                $params[':kelas_id'] = $kelasId;
            }

            $sql .= " ORDER BY k.nama_kelas, s.nama_lengkap ASC";

            // Debugging: Catat SQL query dan parameter
            error_log("DEBUG AbsensiGerbang (Final Kelas): SQL Query: " . $sql);
            error_log("DEBUG AbsensiGerbang (Final Kelas): Parameters: " . print_r($params, true));

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getDailyStudentGateAttendanceReport (Final Kelas): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Mendapatkan laporan absensi gerbang harian untuk guru dari absensi_harian_guru.
     *
     * @param string $tanggal Tanggal laporan (YYYY-MM-DD).
     * @return array Array asosiatif data laporan.
     */
    public function getDailyGuruGateAttendanceReport($date, $tahunAjaran = null, $semester = null) {
        try {
            $sql = "SELECT
                        g.nip,
                        g.nama_lengkap,
                        ahg.waktu_masuk AS jam_masuk,
                        ahg.waktu_pulang AS jam_pulang,
                        ahg.status_masuk,
                        ahg.status_pulang -- Asumsi ada status_masuk/pulang di absensi_harian_guru
                    FROM
                        guru g
                    LEFT JOIN
                        absensi_harian_guru ahg ON g.id = ahg.guru_id
                        AND ahg.tanggal = :date";

            $params = [':date' => $date];

            // Tambahkan filter tahun ajaran dan semester ke klausa ON dari LEFT JOIN
            // Asumsi kolom tahun_ajaran dan semester ada di tabel 'absensi_harian_guru' (ahg)
            if ($tahunAjaran) {
                $sql .= " AND ahg.tahun_ajaran = :tahun_ajaran";
                $params[':tahun_ajaran'] = $tahunAjaran;
            }

            if ($semester) {
                $sql .= " AND ahg.semester = :semester";
                $params[':semester'] = $semester;
            }

            $sql .= " ORDER BY g.nama_lengkap ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getDailyGuruGateAttendanceReport: " . $e->getMessage());
            return [];
        }
    }



    public function getMonthlyStudentGateAttendanceReport($month, $year, $tahunAjaran = null, $semester = null, $kelasId = null) {
    try {
        $sql = "SELECT
                    ahs.siswa_id, -- Pastikan ini ada!
                    s.nisn,
                    s.nama_lengkap,
                    k.nama_kelas,
                    ahs.tanggal,
                    ahs.waktu_masuk AS jam_masuk,
                    ahs.waktu_pulang AS jam_pulang,
                    ahs.status_masuk,
                    ahs.status_pulang
                FROM
                    siswa s
                JOIN
                    kelas k ON s.kelas_id = k.id
                LEFT JOIN
                    absensi_harian_siswa ahs ON s.id = ahs.siswa_id
                    AND MONTH(ahs.tanggal) = :month
                    AND YEAR(ahs.tanggal) = :year";

        $params = [
            ':month' => $month,
            ':year' => $year
        ];

        if ($tahunAjaran) {
            $sql .= " AND ahs.tahun_ajaran = :tahun_ajaran";
            $params[':tahun_ajaran'] = $tahunAjaran;
        }

        if ($semester) {
            $sql .= " AND ahs.semester = :semester";
            $params[':semester'] = $semester;
        }

        if ($kelasId) {
            $sql .= " AND ahs.kelas_id = :kelas_id"; // Filter absensi berdasarkan kelas_id
            $params[':kelas_id'] = $kelasId;
        }

        $sql .= " ORDER BY k.nama_kelas, s.nama_lengkap, ahs.tanggal ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error in getMonthlyStudentGateAttendanceReport: " . $e->getMessage());
        return [];
    }
}
    
    /**
     * Mengambil laporan absensi gerbang guru bulanan.
     * @param string $month Bulan laporan (MM).
     * @param string $year Tahun laporan (YYYY).
     * @param string|null $tahunAjaran Tahun ajaran untuk filter.
     * @param string|null $semester Semester untuk filter.
     * @return array Data laporan absensi.
     */
    public function getMonthlyGuruGateAttendanceReport($month, $year, $tahunAjaran = null, $semester = null) {
        try {
            $sql = "SELECT
                        ahg.guru_id, -- Diperlukan untuk pivoting data
                        g.nip,
                        g.nama_lengkap,
                        ahg.tanggal,
                        ahg.waktu_masuk AS jam_masuk,
                        ahg.waktu_pulang AS jam_pulang,
                        ahg.status_masuk,
                        ahg.status_pulang
                    FROM
                        guru g
                    LEFT JOIN
                        absensi_harian_guru ahg ON g.id = ahg.guru_id
                        AND MONTH(ahg.tanggal) = :month
                        AND YEAR(ahg.tanggal) = :year";

            $params = [
                ':month' => $month,
                ':year' => $year
            ];

            // Tambahkan filter tahun ajaran dan semester ke klausa ON dari LEFT JOIN
            if ($tahunAjaran) {
                $sql .= " AND ahg.tahun_ajaran = :tahun_ajaran";
                $params[':tahun_ajaran'] = $tahunAjaran;
            }

            if ($semester) {
                $sql .= " AND ahg.semester = :semester";
                $params[':semester'] = $semester;
            }

            $sql .= " ORDER BY g.nama_lengkap, ahg.tanggal ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getMonthlyGuruGateAttendanceReport: " . $e->getMessage());
            return [];
        }
    }


    
}
