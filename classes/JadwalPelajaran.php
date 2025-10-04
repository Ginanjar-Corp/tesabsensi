<?php
// project-root/classes/JadwalPelajaran.php

class JadwalPelajaran {
    private $pdo;
    private $table_name = "jadwal_pelajaran";

    public function __construct(PDO $db) {
        $this->pdo = $db;
    }

    /**
     * Mendapatkan jadwal pelajaran untuk guru tertentu pada hari ini.
     *
     * @param int $guru_id ID guru.
     * @param string $hari Hari saat ini (misal: 'Senin').
     * @param string $tahun_ajaran Tahun ajaran aktif.
     * @param string $semester Semester aktif.
     * @return array Array asosiatif dari jadwal guru.
     */
    public function getScheduleForTeacherToday($guru_id, $hari, $tahun_ajaran, $semester) {
        $query = "SELECT jp.id, jp.kelas_id, k.nama_kelas, jp.mapel_id, mp.nama_mapel,
                         jp.guru_id, g.nama_lengkap AS nama_guru,
                         jp.jam_mulai, jp.jam_selesai, jp.jumlah_jam, jp.ruangan, jp.tipe_jadwal
                  FROM " . $this->table_name . " jp
                  LEFT JOIN kelas k ON jp.kelas_id = k.id
                  JOIN mata_pelajaran mp ON jp.mapel_id = mp.id
                  JOIN guru g ON jp.guru_id = g.id
                  WHERE jp.guru_id = :guru_id
                  AND jp.hari = :hari
                  AND jp.tahun_ajaran = :tahun_ajaran
                  AND jp.semester = :semester
                  ORDER BY jp.jam_mulai ASC";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
        $stmt->bindParam(':hari', $hari);
        $stmt->bindParam(':tahun_ajaran', $tahun_ajaran);
        $stmt->bindParam(':semester', $semester);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mendapatkan semua jadwal pelajaran untuk guru tertentu, dengan opsi filter.
     *
     * @param int $guru_id ID guru.
     * @param string|null $hari Hari (opsional).
     * @param string|null $tahun_ajaran Tahun ajaran (opsional).
     * @param string|null $semester Semester (opsional).
     * @return array Array asosiatif dari jadwal guru.
     */
    public function getTeacherSchedule($guru_id, $hari = null, $tahun_ajaran = null, $semester = null) {
        $query = "SELECT 
                     jp.id, 
                     jp.kelas_id, 
                     COALESCE(k.nama_kelas, 'Muliple') AS nama_kelas,
                     jp.mapel_id, 
                     COALESCE(mp.nama_mapel, 'Tidak Ditemukan') AS nama_mapel,
                     jp.guru_id, 
                     COALESCE(g.nama_lengkap, 'Tidak Ditemukan') AS nama_guru,
                     jp.hari, 
                     jp.jam_mulai, 
                     jp.jam_selesai,
                     jp.jumlah_jam,
                     jp.ruangan,
                     jp.semester, 
                     jp.tahun_ajaran, 
                     jp.tipe_jadwal
                  FROM " . $this->table_name . " jp
                  LEFT JOIN kelas k ON jp.kelas_id = k.id
                  LEFT JOIN mata_pelajaran mp ON jp.mapel_id = mp.id
                  LEFT JOIN guru g ON jp.guru_id = g.id
                  WHERE jp.guru_id = :guru_id";
        
        if ($hari) {
            $query .= " AND jp.hari = :hari";
        }
        if ($tahun_ajaran) {
            $query .= " AND jp.tahun_ajaran = :tahun_ajaran";
        }
        if ($semester) {
            $query .= " AND jp.semester = :semester";
        }
        
        $query .= " ORDER BY FIELD(jp.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), jp.jam_mulai ASC";

        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
        if ($hari) {
            $stmt->bindParam(':hari', $hari);
        }
        if ($tahun_ajaran) {
            $stmt->bindParam(':tahun_ajaran', $tahun_ajaran);
        }
        if ($semester) {
            $stmt->bindParam(':semester', $semester);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * Mendapatkan daftar siswa dalam kelas tertentu.
     *
     * @param int $kelas_id ID kelas.
     * @return array Array asosiatif dari siswa di kelas tersebut.
     */
    public function getStudentsInClass($kelas_id) {
        $query = "SELECT id, nisn, nama_lengkap, jenis_kelamin FROM siswa WHERE kelas_id = :kelas_id AND is_active = 1 ORDER BY nama_lengkap ASC";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':kelas_id', $kelas_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mendapatkan detail jadwal pelajaran berdasarkan ID.
     *
     * @param int $id ID jadwal pelajaran.
     * @return array|false Data jadwal jika ditemukan, false jika tidak.
     */
    public function findById($id) {
        $query = "SELECT jp.id, jp.kelas_id, k.nama_kelas, jp.mapel_id, mp.nama_mapel,
                         jp.guru_id, g.nama_lengkap AS nama_guru,
                         jp.hari, jp.jam_mulai, jp.jam_selesai, jp.jumlah_jam, jp.ruangan,
                         jp.semester, jp.tahun_ajaran
                  FROM " . $this->table_name . " jp
                  JOIN kelas k ON jp.kelas_id = k.id
                  JOIN mata_pelajaran mp ON jp.mapel_id = mp.id
                  JOIN guru g ON jp.guru_id = g.id
                  WHERE jp.id = :id LIMIT 0,1";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /** untuk audit dan cek absensi mapel siswa dan guru
     */
    public function findByIdJadwal($id) {
        $query = "SELECT jp.id, jp.kelas_id, k.nama_kelas, jp.mapel_id, mp.nama_mapel,
                         jp.guru_id, g.nama_lengkap AS nama_guru,
                         jp.hari, jp.jam_mulai, jp.jam_selesai, jp.jumlah_jam, jp.ruangan,
                         jp.semester, jp.tahun_ajaran, jp.tipe_jadwal
                  FROM " . $this->table_name . " jp
                  LEFT JOIN kelas k ON jp.kelas_id = k.id
                  LEFT JOIN mata_pelajaran mp ON jp.mapel_id = mp.id
                  LEFT JOIN guru g ON jp.guru_id = g.id
                  WHERE jp.id = :id LIMIT 0,1";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    /**
     * Mendapatkan semua jadwal pelajaran.
     *
     * @return PDOStatement Mengembalikan objek PDOStatement yang berisi semua jadwal pelajaran.
     */
    public function getAll() {
        $query = "SELECT
                    jp.id, jp.hari, jp.jam_mulai, jp.jam_selesai, jp.jumlah_jam, jp.ruangan,
                    jp.semester, jp.tahun_ajaran,
                    k.id as kelas_id, k.nama_kelas,
                    mp.id as mapel_id, mp.nama_mapel,
                    g.id as guru_id, g.nama_lengkap AS nama_guru
                  FROM
                    " . $this->table_name . " jp
                  LEFT JOIN
                    kelas k ON jp.kelas_id = k.id
                  LEFT JOIN
                    mata_pelajaran mp ON jp.mapel_id = mp.id
                  LEFT JOIN
                    guru g ON jp.guru_id = g.id
                  ORDER BY
                    FIELD(jp.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'),
                    jp.jam_mulai ASC";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC); // Mengembalikan array asosiatif
    }
    
    /**
     * Menyimpan hubungan jadwal dengan siswa.
     * @param int $jadwal_id ID jadwal yang baru dibuat.
     * @param array $siswa_ids Array dari ID siswa.
     */
    public function linkStudentsToJadwal(int $jadwal_id, array $siswa_ids) {
        // Hapus hubungan lama (penting untuk fungsi update)
        $this->pdo->prepare("DELETE FROM jadwal_siswa WHERE jadwal_id = ?")->execute([$jadwal_id]);
        
        if (empty($siswa_ids)) {
            return;
        }

        $query = "INSERT INTO jadwal_siswa (jadwal_id, siswa_id) VALUES (?, ?)";
        $stmt = $this->pdo->prepare($query);
        
        foreach ($siswa_ids as $siswa_id) {
            $stmt->execute([$jadwal_id, $siswa_id]);
        }
    }
    
    public function getStudentsByJadwalId($jadwal_id) {
    $query = "SELECT s.* FROM siswa s
              JOIN jadwal_siswa js ON s.id = js.siswa_id
              WHERE js.jadwal_id = ?";
    
    $stmt = $this->pdo->prepare($query);
    $stmt->execute([$jadwal_id]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
    
    /**
     * Membuat jadwal pelajaran baru.
     *
     * @param int $kelas_id
     * @param int $mapel_id
     * @param int $guru_id
     * @param string $hari
     * @param string $jam_mulai
     * @param string $jam_selesai
     * @param string $jumlah_jam
     * @param string|null $ruangan
     * @param string $semester
     * @param string $tahun_ajaran
     * @return bool True jika berhasil dibuat, false jika gagal.
     */
    public function create($data) {
        $this->pdo->beginTransaction();
        try {
            $query = "INSERT INTO " . $this->table_name . " 
                      (kelas_id, mapel_id, guru_id, hari, jam_mulai, jam_selesai, jumlah_jam, ruangan, semester, tahun_ajaran, tipe_jadwal) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([
                $data['tipe_jadwal'] === 'Kelas' ? $data['kelas_id'] : null, // Masukkan null jika per siswa
                $data['mapel_id'], 
                $data['guru_id'], 
                $data['hari'], 
                $data['jam_mulai'], 
                $data['jam_selesai'],
                $data['jumlah_jam'],
                $data['ruangan'],
                $data['semester'],
                $data['tahun_ajaran'],
                $data['tipe_jadwal']
            ]);
            
            $jadwal_id = $this->pdo->lastInsertId();

            if ($data['tipe_jadwal'] === 'Siswa' && !empty($data['siswa_ids'])) {
                $this->linkStudentsToJadwal($jadwal_id, $data['siswa_ids']);
            }
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    /**
     * Memperbarui jadwal pelajaran yang sudah ada.
     *
     * @param int $id ID jadwal pelajaran yang akan diperbarui.
     * @param int $kelas_id
     * @param int $mapel_id
     * @param int $guru_id
     * @param string $hari
     * @param string $jam_mulai
     * @param string $jam_selesai
     * @param string $jumlah_jam
     * @param string|null $ruangan
     * @param string $semester
     * @param string $tahun_ajaran
     * @return bool True jika berhasil diperbarui, false jika gagal.
     */
    public function update($id, $data) {
        $this->pdo->beginTransaction();
        try {
            $query = "UPDATE " . $this->table_name . " 
                      SET kelas_id = ?, mapel_id = ?, guru_id = ?, hari = ?, jam_mulai = ?, jam_selesai = ?, jumlah_jam = ?, ruangan = ?, semester = ?, tahun_ajaran = ?, tipe_jadwal = ?
                      WHERE id = ?";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([
                $data['tipe_jadwal'] === 'Kelas' ? $data['kelas_id'] : null,
                $data['mapel_id'], 
                $data['guru_id'], 
                $data['hari'], 
                $data['jam_mulai'], 
                $data['jam_selesai'],
                $data['jumlah_jam'],
                $data['ruangan'],
                $data['semester'],
                $data['tahun_ajaran'],
                $data['tipe_jadwal'],
                $id
            ]);
            
            if ($data['tipe_jadwal'] === 'Siswa' && !empty($data['siswa_ids'])) {
                $this->linkStudentsToJadwal($id, $data['siswa_ids']);
            }
            
            $this->pdo->commit();
            return true;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Menghapus jadwal pelajaran berdasarkan ID.
     *
     * @param int $id ID jadwal pelajaran yang akan dihapus.
     * @return bool True jika berhasil dihapus, false jika gagal.
     */
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->pdo->prepare($query);

        // Bind parameter
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        try {
            if ($stmt->execute()) {
                return true;
            }
        } catch (PDOException $e) {
            error_log("Error deleting schedule: " . $e->getMessage());
        }
        return false;
    }
    
    // Di dalam class JadwalPelajaran
    public function getJadwalByGuruAndDay($guruId, $dayName) {
        $query = "SELECT jp.id, jp.jam_mulai, jp.jam_selesai, jp.jumlah_jam,
                         m.nama_mapel, k.nama_kelas, jp.ruangan, jp.tipe_jadwal
                  FROM jadwal_pelajaran jp
                  JOIN mata_pelajaran m ON jp.mapel_id = m.id
                  LEFT JOIN kelas k ON jp.kelas_id = k.id
                  WHERE jp.guru_id = :guru_id AND jp.hari = :hari
                  ORDER BY jp.jam_mulai ASC";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':guru_id', $guruId, PDO::PARAM_INT);
        $stmt->bindParam(':hari', $dayName, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

        public function getJadwalByGuruId($guruId) {
        // Query untuk mengambil jadwal berdasarkan guru_id.
        // Asumsi: Setiap baris di tabel jadwal_pelajaran merepresentasikan satu sesi pertemuan.
        // Jika ada kolom 'is_active' di tabel jadwal_pelajaran untuk menandai jadwal yang aktif,
        // Anda bisa menambahkannya ke klausa WHERE (misal: AND is_active = 1).
        // Saat ini, query mengambil semua jadwal yang terkait dengan guru tersebut.
        $query = "SELECT id, kelas_id, mapel_id, guru_id, hari, jam_mulai, jam_selesai, jumlah_jam, ruangan, semester, tahun_ajaran
                  FROM " . $this->table_name . "
                  WHERE guru_id = :guru_id";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':guru_id', $guruId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mengambil semua jadwal pelajaran.
     * Berguna untuk daftar lengkap jadwal.
     *
     * @return array Array berisi semua data jadwal pelajaran.
     */
    public function getAllJadwal() {
        $query = "SELECT jp.id, k.nama_kelas, mp.nama_mapel, g.nama_lengkap AS nama_guru,
                         jp.hari, jp.jam_mulai, jp.jam_selesai, jp.jumlah_jam, jp.ruangan, jp.semester, jp.tahun_ajaran
                  FROM " . $this->table_name . " jp
                  JOIN kelas k ON jp.kelas_id = k.id
                  JOIN mata_pelajaran mp ON jp.mapel_id = mp.id
                  JOIN guru g ON jp.guru_id = g.id
                  ORDER BY jp.hari, jp.jam_mulai";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getJadwalHariIni($tanggal, $tahun_ajaran, $semester) {
        $query = "SELECT jp.*, k.nama_kelas, m.nama_mapel, g.nama_lengkap as nama_guru
                  FROM " . $this->table_name . " jp
                  LEFT JOIN kelas k ON jp.kelas_id = k.id
                  LEFT JOIN mata_pelajaran m ON jp.mapel_id = m.id
                  LEFT JOIN guru g ON jp.guru_id = g.id
                  WHERE jp.hari = :hari AND jp.tahun_ajaran = :tahun_ajaran AND jp.semester = :semester";
        
        $stmt = $this->pdo->prepare($query);

        // Dapatkan nama hari dalam Bahasa Indonesia dari angka
        $nama_hari = getDayName(date('N', strtotime($tanggal)));

        $stmt->bindParam(':hari', $nama_hari);
        $stmt->bindParam(':tahun_ajaran', $tahun_ajaran);
        $stmt->bindParam(':semester', $semester);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Mengambil satu baris data jadwal pelajaran berdasarkan ID.
     * @param int $id ID jadwal.
     * @return array|null Data jadwal atau null jika tidak ditemukan.
     */
    public function getById($id) {
        $query = "SELECT
                    jp.id, jp.kelas_id, jp.mapel_id, jp.guru_id, jp.hari,
                    jp.jam_mulai, jp.jam_selesai, jp.jumlah_jam, jp.ruangan, jp.tipe_jadwal,
                    k.nama_kelas, mp.nama_mapel, g.nama_lengkap AS nama_guru
                  FROM
                    " . $this->table_name . " jp
                  LEFT JOIN
                    kelas k ON jp.kelas_id = k.id
                  LEFT JOIN
                    mata_pelajaran mp ON jp.mapel_id = mp.id
                  LEFT JOIN
                    guru g ON jp.guru_id = g.id
                  WHERE
                    jp.id = ?
                  LIMIT 1";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row;
    }
    
    /**
     * Mendapatkan semua jadwal pelajaran untuk hari, tahun ajaran, dan semester tertentu.
     *
     * @param string $hari Hari saat ini (misal: 'Senin').
     * @param string $tahun_ajaran Tahun ajaran aktif.
     * @param string $semester Semester aktif.
     * @return array Array asosiatif dari jadwal.
     */
    public function getScheduleByDay($hari, $tahun_ajaran, $semester) {
        $query = "SELECT jp.id, jp.kelas_id, k.nama_kelas, jp.mapel_id, mp.nama_mapel,
                         jp.guru_id, g.nama_lengkap AS nama_guru,
                         jp.jam_mulai, jp.jam_selesai, jp.jumlah_jam, jp.ruangan, jp.tipe_jadwal
                  FROM " . $this->table_name . " jp
                  LEFT JOIN kelas k ON jp.kelas_id = k.id
                  JOIN mata_pelajaran mp ON jp.mapel_id = mp.id
                  JOIN guru g ON jp.guru_id = g.id
                  WHERE jp.hari = :hari AND jp.tahun_ajaran = :tahun_ajaran AND jp.semester = :semester
                  ORDER BY jp.jam_mulai ASC";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([
            ':hari' => $hari,
            ':tahun_ajaran' => $tahun_ajaran,
            ':semester' => $semester
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
 * Menghapus jadwal pelajaran dan semua data absensi terkait.
 * Menggunakan transaksi untuk memastikan integritas data.
 *
 * @param int $id ID jadwal pelajaran.
 * @return bool True jika berhasil, false jika gagal.
 */
public function deleteWithDependencies($id) {
    try {
        // Mulai transaksi
        $this->pdo->beginTransaction();

        // 1. Hapus data absensi guru yang terkait dengan jadwal ini
        $stmt_guru = $this->pdo->prepare("DELETE FROM absensi_mapel_guru WHERE jadwal_id = ?");
        $stmt_guru->execute([$id]);

        // 2. Hapus data absensi siswa yang terkait
        $stmt_siswa = $this->pdo->prepare("DELETE FROM absensi_mapel_siswa WHERE jadwal_id = ?");
        $stmt_siswa->execute([$id]);

        // 3. Hapus data jadwal pelajaran itu sendiri
        $stmt_jadwal = $this->pdo->prepare("DELETE FROM " . $this->table_name . " WHERE id = ?");
        $stmt_jadwal->execute([$id]);

        // Commit transaksi jika semua operasi berhasil
        $this->pdo->commit();
        return true;
    } catch (PDOException $e) {
        // Rollback transaksi jika terjadi kesalahan
        $this->pdo->rollBack();
        error_log("Gagal menghapus jadwal pelajaran dengan dependensi: " . $e->getMessage());
        return false;
    }
}
    // Anda bisa menambahkan metode CRUD lainnya untuk jadwal pelajaran di sini (create, update, delete)
}
