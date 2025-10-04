<?php
// classes/Siswa.php

require_once __DIR__ . '/../classes/Kelas.php'; // Pastikan kelas Kelas dimuat

class Siswa {
    private $pdo; // Menggunakan $pdo karena ini yang digunakan dalam file Siswa (8).php yang diunggah
    private $table_name = "siswa";
    private $table_kelas = "kelas";
    private $table_tahun_ajaran  = "tahun_ajaran"; // Diperlukan jika metode terkait tahun ajaran digunakan

    public function __construct(PDO $db) {
        $this->pdo = $db;
    }

    /**
     * Mendapatkan semua siswa.
     * Menggunakan LEFT JOIN untuk mengambil nama kelas.
     * @param array $filters Array filter (opsional).
     * @return array Array asosiatif dari semua siswa.
     */
    public function getAll() {
        // Metode getAll() ini diambil dari Siswa (8).php, yang hanya mengambil id, nisn, nama_lengkap
        // Untuk admin, kita mungkin ingin lebih detail, jadi kita akan sesuaikan
        $query = "SELECT s.id, s.nisn, s.username, s.nama_lengkap, s.jenis_kelamin, s.tanggal_lahir, 
                         s.tempat_lahir, s.alamat, s.kelas_id, k.nama_kelas, 
                         s.no_telp_ortu, s.created_at, s.updated_at, s.is_active, s.status_siswa
                  FROM " . $this->table_name . " s
                  LEFT JOIN " . $this->table_kelas . " k ON s.kelas_id = k.id
                  ORDER BY s.kelas_id ASC";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Mendapatkan data siswa berdasarkan daftar ID.
     *
     * @param array $siswaIds Array berisi ID siswa.
     * @return array Array asosiatif dari data siswa yang sesuai.
     */
    public function getSiswaByIds(array $siswaIds) {
        if (empty($siswaIds)) {
            return [];
        }
        
        // Buat string placeholder untuk query SQL
        $placeholders = implode(',', array_fill(0, count($siswaIds), '?'));
        
        $query = "SELECT * FROM " . $this->table_name . " WHERE id IN ($placeholders)";
        $stmt = $this->pdo->prepare($query);
        
        // Bind parameter
        foreach ($siswaIds as $index => $id) {
            $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Metode findById() dari Siswa (8).php juga sudah lebih lengkap, kita akan gunakan itu
    // Tetapi perlu disesuaikan untuk mengambil username dan password hash (untuk admin)
    public function findById($id) {
        $query = "SELECT s.id, s.nisn, s.nis, s.rfid_tag, s.username, s.password, s.nama_lengkap, s.tempat_lahir, s.tanggal_lahir,
                          s.jenis_kelamin, s.alamat, s.no_telp_ortu, s.email_ortu,
                          s.kelas_id, k.nama_kelas, s.is_active, s.status_siswa
                   FROM " . $this->table_name . " s
                   LEFT JOIN " . $this->table_kelas . " k ON s.kelas_id = k.id
                   WHERE s.id = :id LIMIT 0,1";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Metode getAllStudentsByKelas() dari Siswa (8).php, digunakan untuk filter siswa di halaman lain
    public function getAllStudentsByKelas($kelas_id = null) {
        $query = "SELECT s.id, s.nisn, s.nama_lengkap, s.kelas_id, k.nama_kelas, s.is_active, s.status_siswa
                  FROM " . $this->table_name . " s
                  LEFT JOIN " . $this->table_kelas . " k ON s.kelas_id = k.id";

        $params = [];
        if ($kelas_id) {
            $query .= " WHERE s.kelas_id = :kelas_id";
            $params[':kelas_id'] = $kelas_id;
        }
        $query .= " AND s.is_active = 1"; // Hanya siswa aktif
        $query .= " ORDER BY s.nama_lengkap ASC";

        $stmt = $this->pdo->prepare($query); // Menggunakan $this->pdo
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    //Fungsi untuk absensi manual
    public function getSiswaByKelas($kelas_id) {
        $stmt = $this->pdo->prepare("SELECT s.*, k.nama_kelas FROM siswa s JOIN kelas k ON s.kelas_id = k.id WHERE s.kelas_id = ? ORDER BY s.nama_lengkap ASC");
        $stmt->execute([$kelas_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getSiswaByJadwalId(int $jadwal_id, string $tipe_jadwal): array {
    if ($tipe_jadwal === 'Siswa') {
        // Ambil siswa dari tabel perantara `jadwal_siswa`
        $query = "
            SELECT 
                s.*, 
                k.nama_kelas
            FROM jadwal_siswa js
            JOIN siswa s ON js.siswa_id = s.id
            JOIN kelas k ON s.kelas_id = k.id
            WHERE js.jadwal_id = ?
            ORDER BY s.nama_lengkap ASC
        ";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$jadwal_id]);
    } else {
        // Ambil siswa dari tabel `siswa` berdasarkan `kelas_id` di `jadwal_pelajaran`
        $query = "
            SELECT 
                s.*,
                k.nama_kelas
            FROM jadwal_pelajaran jp
            JOIN siswa s ON jp.kelas_id = s.kelas_id
            JOIN kelas k ON s.kelas_id = k.id
            WHERE jp.id = ?
            ORDER BY s.nama_lengkap ASC
        ";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$jadwal_id]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
    
    /**
     * Mendapatkan detail siswa berdasarkan username.
     *
     * @param string $username Username siswa.
     * @return array|false Data siswa jika ditemukan, false jika tidak.
     */
    public function findByUsername($username) {
        $query = "SELECT id, username, nama_lengkap FROM " . $this->table_name . " WHERE username = :username LIMIT 0,1";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    /**
     * Membuat data siswa baru.
     * Secara default, siswa baru akan 'Aktif'.
     *
     * @param array $data Data siswa (nama_lengkap, kelas_id, nisn, nis, rfid_tag, username, password dll.).
     * @return bool True jika berhasil, false jika gagal.
     */
    public function create(array $data) {
        // Secara default, siswa baru akan 'Aktif' dan is_active = 1
        $data['status_siswa'] = $data['status_siswa'] ?? 'Aktif';
        $data['is_active'] = 1; // Siswa baru selalu aktif

        // Hash password jika disediakan
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        } else {
            $data['password'] = null; // Set null jika tidak ada password
        }

        $query = "INSERT INTO " . $this->table_name . " (
                    nama_lengkap, kelas_id, nisn, nis, rfid_tag,
                    username, password,
                    tempat_lahir, tanggal_lahir, jenis_kelamin, alamat,
                    no_telp_ortu, email_ortu, is_active, status_siswa
                ) VALUES (
                    :nama_lengkap, :kelas_id, :nisn, :nis, :rfid_tag,
                    :username, :password,
                    :tempat_lahir, :tanggal_lahir, :jenis_kelamin, :alamat,
                    :no_telp_ortu, :email_ortu, :is_active, :status_siswa
                )";
        $stmt = $this->pdo->prepare($query); // Menggunakan $this->pdo

        return $stmt->execute([
            ':nama_lengkap' => $data['nama_lengkap'] ?? null,
            ':kelas_id' => $data['kelas_id'] ?? null,
            ':nisn' => $data['nisn'] ?? null,
            ':nis' => $data['nis'] ?? null,
            ':rfid_tag' => $data['rfid_tag'] ?? null,
            ':username' => $data['username'] ?? null, // Bind username
            ':password' => $data['password'] ?? null, // Bind hashed password
            ':tempat_lahir' => $data['tempat_lahir'] ?? null,
            ':tanggal_lahir' => $data['tanggal_lahir'] ?? null,
            ':jenis_kelamin' => $data['jenis_kelamin'] ?? null,
            ':alamat' => $data['alamat'] ?? null,
            ':no_telp_ortu' => $data['no_telp_ortu'] ?? null,
            ':email_ortu' => $data['email_ortu'] ?? null,
            ':is_active' => $data['is_active'] ?? 1,
            ':status_siswa' => $data['status_siswa'] ?? 'Aktif'
        ]);
    }

    /**
     * Memperbarui data siswa yang sudah ada.
     * Metode ini akan secara otomatis mengatur 'is_active' berdasarkan 'status_siswa'.
     *
     * @param int $id ID siswa.
     * @param array $data Data siswa yang akan diperbarui. Dapat mencakup 'status_siswa', 'username', 'password'.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function update($id, array $data) {
        $set_parts = [];
        $params = [':id' => $id];

        // Tentukan is_active berdasarkan status_siswa yang diberikan atau yang sudah ada
        $current_siswa = $this->findById($id); // Ambil data siswa saat ini untuk mendapatkan status_siswa jika tidak diberikan
        $status_siswa_to_set = $data['status_siswa'] ?? ($current_siswa['status_siswa'] ?? 'Aktif');

        $is_active_to_set = 1; // Default aktif
        if ($status_siswa_to_set === 'Pindah' || $status_siswa_to_set === 'Lulus') {
            $is_active_to_set = 0;
        }
        $data['is_active'] = $is_active_to_set; // Setel is_active berdasarkan logika baru

        foreach ($data as $key => $value) {
            // Hindari memperbarui 'id', 'created_at', 'password' secara langsung di loop ini
            if ($key === 'id' || $key === 'created_at') {
                continue;
            }
            // Tangani password secara terpisah
            if ($key === 'password' && !empty($value)) {
                $set_parts[] = "$key = :$key";
                $params[":$key"] = password_hash($value, PASSWORD_BCRYPT);
            } elseif ($key === 'password' && empty($value)) {
                // Jangan update password jika kosong (berarti tidak ada perubahan)
                continue;
            }
            else {
                $set_parts[] = "$key = :$key";
                $params[":$key"] = $value;
            }
        }
        $set_parts[] = "updated_at = CURRENT_TIMESTAMP";

        if (empty($set_parts)) {
            return false; // Tidak ada data untuk diperbarui
        }

        $query = "UPDATE " . $this->table_name . " SET " . implode(', ', $set_parts) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($query); // Menggunakan $this->pdo
        return $stmt->execute($params);
    }

    /**
     * Menghapus data siswa.
     * Disarankan untuk menggunakan soft delete (mengubah status_siswa menjadi 'Pindah' atau 'Lulus'
     * dan is_active menjadi 0) daripada menghapus permanen, terutama jika ada data terkait.
     * Metode ini tetap melakukan hard delete seperti sebelumnya, tetapi ditambahkan
     * agar konsisten dengan `$this->pdo`.
     *
     * @param int $id ID siswa.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function delete($id) {
        $this->pdo->beginTransaction();
        try {
            // Hapus absensi mata pelajaran siswa
            $stmt_mapel = $this->pdo->prepare("DELETE FROM absensi_mapel_siswa WHERE siswa_id = :siswa_id");
            $stmt_mapel->bindParam(':siswa_id', $id, PDO::PARAM_INT);
            $stmt_mapel->execute();

            // Hapus absensi harian siswa
            $stmt_harian = $this->pdo->prepare("DELETE FROM absensi_harian_siswa WHERE siswa_id = :siswa_id");
            $stmt_harian->bindParam(':siswa_id', $id, PDO::PARAM_INT);
            $stmt_harian->execute();

            // Hapus siswa itu sendiri
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $result = $stmt->execute();

            $this->pdo->commit();
            return $result;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error deleting student ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    // Metode baru untuk autentikasi orang tua (siswa)
    public function authenticate($username, $password) {
        $query = "SELECT id, username, password, nama_lengkap, no_telp_ortu, kelas_id FROM " . $this->table_name . " WHERE username = :username LIMIT 0,1";
        $stmt = $this->pdo->prepare($query); // Menggunakan $this->pdo
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        $siswa_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($siswa_data && password_verify($password, $siswa_data['password'])) {
            // Autentikasi berhasil
            return [
                'id' => $siswa_data['id'],
                'username' => $siswa_data['username'],
                'nama_lengkap' => $siswa_data['nama_lengkap'],
                'no_telp_ortu' => $siswa_data['no_telp_ortu'],
                'kelas_id' => $siswa_data['kelas_id']
            ];
        }
        return false; // Autentikasi gagal
    }

    /**
     * Mendapatkan detail siswa berdasarkan NISN.
     * Menggunakan JOIN untuk mengambil nama kelas.
     *
     * @param string $nisn NISN siswa.
     * @return array|false Data siswa jika ditemukan, false jika tidak.
     */
    public function findByNisn($nisn) {
        $query = "SELECT s.id, s.nisn, s.nis, s.rfid_tag, s.username, s.password, s.nama_lengkap, s.tempat_lahir, s.tanggal_lahir,
                          s.jenis_kelamin, s.alamat, s.no_telp_ortu, s.email_ortu,
                          s.kelas_id, k.nama_kelas, s.is_active, s.status_siswa
                   FROM " . $this->table_name . " s
                   LEFT JOIN kelas k ON s.kelas_id = k.id
                   WHERE s.nisn = :nisn LIMIT 0,1";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':nisn', $nisn, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Mendapatkan detail siswa berdasarkan NIS.
     * Menggunakan JOIN untuk mengambil nama kelas.
     *
     * @param string $nis NIS siswa.
     * @return array|false Data siswa jika ditemukan, false jika tidak.
     */
    public function findByNis($nis) {
        $query = "SELECT s.id, s.nisn, s.nis, s.rfid_tag, s.username, s.password, s.nama_lengkap, s.tempat_lahir, s.tanggal_lahir,
                          s.jenis_kelamin, s.alamat, s.no_telp_ortu, s.email_ortu,
                          s.kelas_id, k.nama_kelas, s.is_active, s.status_siswa
                   FROM " . $this->table_name . " s
                   LEFT JOIN kelas k ON s.kelas_id = k.id
                   WHERE s.nis = :nis LIMIT 0,1";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':nis', $nis, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Mencari siswa berdasarkan RFID tag.
     * Menggunakan JOIN untuk mengambil nama kelas.
     *
     * @param string $rfid_tag RFID tag siswa.
     * @return array|false Data siswa jika ditemukan, false jika tidak.
     */
    public function findByRfidTag($rfid_tag) {
        $query = "SELECT s.id, s.nisn, s.nis, s.rfid_tag, s.username, s.password, s.nama_lengkap, s.kelas_id, k.nama_kelas, s.no_telp_ortu, s.status_siswa, s.is_active
                  FROM " . $this->table_name . " s
                  LEFT JOIN kelas k ON s.kelas_id = k.id
                  WHERE s.rfid_tag = :rfid_tag AND s.is_active = 1
                  LIMIT 0,1";

        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':rfid_tag', $rfid_tag);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Metode ini dari Siswa (8).php
    public function getDailyGateAttendance($siswa_id, $tanggal) {
        $query = "SELECT waktu_masuk, waktu_pulang, status_masuk, status_pulang
                  FROM absensi_harian_siswa
                  WHERE siswa_id = :siswa_id AND tanggal = :tanggal LIMIT 0,1";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':siswa_id', $siswa_id, PDO::PARAM_INT);
        $stmt->bindParam(':tanggal', $tanggal);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Metode ini dari Siswa (8).php
    public function getRecentSubjectAttendance($siswa_id, $limit = 10) {
        $query = "SELECT
                    ams.tanggal_absen,
                    ams.waktu_absen,
                    ams.status_kehadiran,
                    ams.catatan,
                    mp.nama_mapel,
                    g.nama_lengkap AS nama_guru
                  FROM
                    absensi_mapel_siswa ams
                  JOIN
                    jadwal_pelajaran jp ON ams.jadwal_id = jp.id
                  JOIN
                    mata_pelajaran mp ON jp.mapel_id = mp.id
                  JOIN
                    guru g ON jp.guru_id = g.id
                  WHERE
                    ams.siswa_id = :siswa_id
                  ORDER BY
                    ams.tanggal_absen DESC, ams.waktu_absen DESC
                  LIMIT :limit";

        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':siswa_id', $siswa_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Metode ini dari Siswa (8).php
    public function getFilteredSiswa($search_query = null, $status_filter = null) {
        $sql = "SELECT s.id, s.nisn, s.username, s.nama_lengkap, k.nama_kelas, k.tingkat, k.jurusan, s.rfid_tag, s.is_active, s.status_siswa
                FROM " . $this->table_name . " s
                JOIN kelas k ON s.kelas_id = k.id
                WHERE 1=1";

        $params = [];

        if ($search_query) {
            $sql .= " AND (s.nisn LIKE :search_query OR s.nama_lengkap LIKE :search_query_nama OR s.username LIKE :search_query_username)";
            $params[':search_query'] = '%' . $search_query . '%';
            $params[':search_query_nama'] = '%' . $search_query . '%';
            $params[':search_query_username'] = '%' . $search_query . '%'; // Tambah pencarian username
        }

        if ($status_filter && in_array($status_filter, ['Aktif', 'Pindah', 'Lulus'])) {
            $sql .= " AND s.status_siswa = :status_filter";
            $params[':status_filter'] = $status_filter;
        }

        $sql .= " ORDER BY k.tingkat ASC, k.nama_kelas ASC, s.nama_lengkap ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Metode ini dari Siswa (8).php
    public function getSiswaByKelasId($kelas_id) {
        $query = "SELECT id, nisn, username, nama_lengkap, is_active, status_siswa FROM " . $this->table_name . " WHERE kelas_id = :kelas_id AND is_active = 1 ORDER BY nama_lengkap ASC";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':kelas_id', $kelas_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Metode ini dari Siswa (8).php
    public function updateSiswaKelas($siswa_ids, $new_kelas_id) {
        if (empty($siswa_ids)) {
            return true;
        }
        $placeholders = implode(',', array_fill(0, count($siswa_ids), '?'));
        $query = "UPDATE " . $this->table_name . " SET kelas_id = ?, updated_at = CURRENT_TIMESTAMP, status_siswa = 'Aktif', is_active = 1 WHERE id IN ($placeholders)";
        $stmt = $this->pdo->prepare($query);
        $params = array_merge([$new_kelas_id], $siswa_ids);
        return $stmt->execute($params);
    }

    // Metode ini dari Siswa (8).php
    public function graduateSiswa($siswa_ids) {
        if (empty($siswa_ids)) {
            return true;
        }
        $placeholders = implode(',', array_fill(0, count($siswa_ids), '?'));
        $query = "UPDATE " . $this->table_name . " SET status_siswa = 'Lulus', is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id IN ($placeholders)";
        $stmt = $this->pdo->prepare($query);
        return $stmt->execute($siswa_ids);
    }

    // Metode ini dari Siswa (8).php
    public function getStudentsWithClassAndEnrollment($kelas_id = null, $tahun_ajaran_id = null) {
        $query = "SELECT s.id, s.nisn, s.nama_lengkap, k.nama_kelas, ta.tahun_ajaran
                  FROM " . $this->table_name . " s
                  JOIN " . $this->table_kelas . " k ON s.kelas_id = k.id
                  LEFT JOIN " . $this->table_tahun_ajaran . " ta ON ta.id = :tahun_ajaran_id_param ";

        $params = [];
        $params[':tahun_ajaran_id_param'] = $tahun_ajaran_id;

        $where_clauses = [];

        if ($kelas_id) {
            $where_clauses[] = "s.kelas_id = :kelas_id";
            $params[':kelas_id'] = $kelas_id;
        }

        if (!empty($where_clauses)) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }

        $query .= " ORDER BY s.nama_lengkap ASC";

        $stmt = $this->pdo->prepare($query);
        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getById($id) {
        $query = "SELECT s.*, k.nama_kelas
                  FROM " . $this->table_name . " s
                  LEFT JOIN kelas k ON s.kelas_id = k.id
                  WHERE s.id = :id
                  LIMIT 0,1";
    
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
        return $row;
    }
    
    /**
     * Mengambil daftar siswa berdasarkan ID kelas.
     * @param int $kelasId ID kelas.
     * @return array Daftar siswa dalam bentuk array asosiatif.
     */
    public function getByKelasId($kelasId) {
        $query = "SELECT s.*, k.nama_kelas
                  FROM " . $this->table_name . " s
                  LEFT JOIN kelas k ON s.kelas_id = k.id
                  WHERE s.kelas_id = :kelas_id
                  ORDER BY s.nama_lengkap ASC";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':kelas_id', $kelasId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}
