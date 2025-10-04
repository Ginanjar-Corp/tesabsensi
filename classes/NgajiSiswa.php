<?php
// classes/NgajiSiswa.php

require_once __DIR__ . '/../classes/NotificationQueue.php';
require_once __DIR__ . '/../classes/SchoolSettings.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/Guru.php';
require_once __DIR__ . '/../classes/TahunAjaran.php'; // Tambahkan ini

class NgajiSiswa {
    private $conn;
    private $table_name = "ngaji_siswa";
    private $table_siswa = "siswa";
    private $table_guru = "guru";
    private $table_kelas = "kelas";

    public $id;
    public $siswa_id;
    public $tahun_ajaran_id; // Tambahkan properti ini
    public $guru_id;
    public $tanggal_setor;
    public $jenis_materi;
    public $halaman_dari;
    public $halaman_sampai;
    public $catatan_guru;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Metode untuk membaca semua data ngaji dengan filter
    public function getAll($filters = []) {
        $query = "SELECT n.*, s.nama_lengkap AS nama_siswa, s.nisn, s.no_telp_ortu, k.nama_kelas, g.nama_lengkap AS nama_guru,
                         ta.tahun_ajaran AS nama_tahun_ajaran
                  FROM " . $this->table_name . " n
                  LEFT JOIN " . $this->table_siswa . " s ON n.siswa_id = s.id
                  LEFT JOIN " . $this->table_kelas . " k ON s.kelas_id = k.id
                  LEFT JOIN " . $this->table_guru . " g ON n.guru_id = g.id
                  LEFT JOIN tahun_ajaran ta ON n.tahun_ajaran_id = ta.id"; // Join tabel tahun_ajaran

        $where_clauses = [];
        $params = [];

        if (!empty($filters['siswa_id'])) {
            $where_clauses[] = "n.siswa_id = :siswa_id";
            $params[':siswa_id'] = $filters['siswa_id'];
        }
        if (!empty($filters['guru_id'])) {
            $where_clauses[] = "n.guru_id = :guru_id";
            $params[':guru_id'] = $filters['guru_id'];
        }
        if (!empty($filters['kelas_id'])) {
            $where_clauses[] = "s.kelas_id = :kelas_id";
            $params[':kelas_id'] = $filters['kelas_id'];
        }
        // Filter tahun ajaran
        if (isset($filters['tahun_ajaran_id']) && $filters['tahun_ajaran_id']) {
            $where_clauses[] = "n.tahun_ajaran_id = :tahun_ajaran_id";
            $params[':tahun_ajaran_id'] = $filters['tahun_ajaran_id'];
        }

        if (!empty($where_clauses)) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }

        $query .= " ORDER BY n.tanggal_setor DESC, n.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Metode untuk membaca satu data ngaji berdasarkan ID
    public function getById($id) {
        $query = "SELECT n.*, s.nama_lengkap AS nama_siswa, s.nisn, s.no_telp_ortu, k.nama_kelas, g.nama_lengkap AS nama_guru,
                         ta.tahun_ajaran AS nama_tahun_ajaran
                  FROM " . $this->table_name . " n
                  LEFT JOIN " . $this->table_siswa . " s ON n.siswa_id = s.id
                  LEFT JOIN " . $this->table_kelas . " k ON s.kelas_id = k.id
                  LEFT JOIN " . $this->table_guru . " g ON n.guru_id = g.id
                  LEFT JOIN tahun_ajaran ta ON n.tahun_ajaran_id = ta.id
                  WHERE n.id = :id LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Mengambil data mengaji siswa untuk laporan.
     *
     * @param int $siswa_id ID siswa.
     * @param string|null $start_date Tanggal mulai laporan.
     * @param string|null $end_date Tanggal selesai laporan.
     * @param int|null $tahun_ajaran_id ID Tahun Ajaran.
     * @return array Data laporan mengaji.
     */
    public function getReportData($siswa_id, $start_date = null, $end_date = null, $tahun_ajaran_id = null) {
        $query = "SELECT n.tanggal_setor, n.jenis_materi, n.halaman_dari, n.halaman_sampai, n.catatan_guru, g.nama_lengkap AS nama_guru,
                         ta.tahun_ajaran AS nama_tahun_ajaran
                  FROM " . $this->table_name . " n
                  JOIN guru g ON n.guru_id = g.id
                  LEFT JOIN tahun_ajaran ta ON n.tahun_ajaran_id = ta.id
                  WHERE n.siswa_id = :siswa_id";

        $params = [':siswa_id' => $siswa_id];

        if ($start_date && $end_date) {
            $query .= " AND n.tanggal_setor BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $start_date;
            $params[':end_date'] = $end_date;
        }
        if ($tahun_ajaran_id) {
            $query .= " AND n.tahun_ajaran_id = :tahun_ajaran_id";
            $params[':tahun_ajaran_id'] = $tahun_ajaran_id;
        }

        $query .= " ORDER BY n.tanggal_setor ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Metode baru untuk membaca semua data ngaji siswa untuk aplikasi orang tua
    // Memastikan data selalu difilter berdasarkan siswa_id yang diberikan
    public function getAllParent($siswa_id, $filters = []) {
        $query = "SELECT n.*, s.nama_lengkap AS nama_siswa, s.nisn, k.nama_kelas, g.nama_lengkap AS nama_guru, ta.tahun_ajaran AS nama_tahun_ajaran
                  FROM " . $this->table_name . " n
                  LEFT JOIN " . $this->table_siswa . " s ON n.siswa_id = s.id
                  LEFT JOIN " . $this->table_kelas . " k ON s.kelas_id = k.id
                  LEFT JOIN " . $this->table_guru . " g ON n.guru_id = g.id
                  LEFT JOIN tahun_ajaran ta ON n.tahun_ajaran_id = ta.id
                  WHERE n.siswa_id = :siswa_id"; // Selalu filter berdasarkan siswa_id

        $params = [':siswa_id' => $siswa_id];

        if (isset($filters['guru_id']) && $filters['guru_id']) {
            $query .= " AND n.guru_id = :guru_id";
            $params[':guru_id'] = $filters['guru_id'];
        }
        if (isset($filters['tahun_ajaran_id']) && $filters['tahun_ajaran_id']) {
            $query .= " AND n.tahun_ajaran_id = :tahun_ajaran_id";
            $params[':tahun_ajaran_id'] = $filters['tahun_ajaran_id'];
        }
        if (isset($filters['jenis_materi']) && $filters['jenis_materi']) {
            $query .= " AND n.jenis_materi = :jenis_materi";
            $params[':jenis_materi'] = $filters['jenis_materi'];
        }

        $query .= " ORDER BY n.tanggal_setor DESC, n.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Metode untuk menambah catatan ngaji baru
    public function create(array $data) {
        try {
            // Ambil tahun ajaran aktif secara otomatis
            $tahunAjaranModel = new TahunAjaran($this->conn);
            $activeTahunAjaran = $tahunAjaranModel->getActiveTahunAjaran();
            $tahun_ajaran_id = $activeTahunAjaran['id'] ?? null;

            $query = "INSERT INTO " . $this->table_name . " SET
                      siswa_id = :siswa_id,
                      tahun_ajaran_id = :tahun_ajaran_id,
                      guru_id = :guru_id,
                      tanggal_setor = :tanggal_setor,
                      jenis_materi = :jenis_materi,
                      halaman_dari = :halaman_dari,
                      halaman_sampai = :halaman_sampai,
                      catatan_guru = :catatan_guru";
            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(':siswa_id', $data['siswa_id']);
            $stmt->bindParam(':tahun_ajaran_id', $tahun_ajaran_id); // Bind tahun_ajaran_id
            $stmt->bindParam(':guru_id', $data['guru_id']);
            $stmt->bindParam(':tanggal_setor', $data['tanggal_setor']);
            $stmt->bindParam(':jenis_materi', $data['jenis_materi']);
            $stmt->bindParam(':halaman_dari', $data['halaman_dari']);
            $stmt->bindParam(':halaman_sampai', $data['halaman_sampai']);
            $stmt->bindParam(':catatan_guru', $data['catatan_guru']);

            if ($stmt->execute()) {
                $last_id = $this->conn->lastInsertId();
                // --- Mulai Notifikasi WhatsApp (Opsional, Anda bisa menambahkan ini nanti) ---
                $this->sendNotification($last_id, 'create');
                // --- Akhir Notifikasi WhatsApp ---
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error creating ngaji record: " . $e->getMessage());
            return false;
        }
    }

    // Metode untuk memperbarui catatan ngaji
    public function update($id, array $data) {
        try {
            // Ambil tahun ajaran aktif secara otomatis
            $tahunAjaranModel = new TahunAjaran($this->conn);
            $activeTahunAjaran = $tahunAjaranModel->getActiveTahunAjaran();
            $tahun_ajaran_id = $activeTahunAjaran['id'] ?? null;

            $query = "UPDATE " . $this->table_name . " SET
                      siswa_id = :siswa_id,
                      tahun_ajaran_id = :tahun_ajaran_id,
                      guru_id = :guru_id,
                      tanggal_setor = :tanggal_setor,
                      jenis_materi = :jenis_materi,
                      halaman_dari = :halaman_dari,
                      halaman_sampai = :halaman_sampai,
                      catatan_guru = :catatan_guru,
                      updated_at = CURRENT_TIMESTAMP
                      WHERE id = :id";
            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':siswa_id', $data['siswa_id']);
            $stmt->bindParam(':tahun_ajaran_id', $tahun_ajaran_id); // Bind tahun_ajaran_id
            $stmt->bindParam(':guru_id', $data['guru_id']);
            $stmt->bindParam(':tanggal_setor', $data['tanggal_setor']);
            $stmt->bindParam(':jenis_materi', $data['jenis_materi']);
            $stmt->bindParam(':halaman_dari', $data['halaman_dari']);
            $stmt->bindParam(':halaman_sampai', $data['halaman_sampai']);
            $stmt->bindParam(':catatan_guru', $data['catatan_guru']);

            if ($stmt->execute()) {
                // --- Mulai Notifikasi WhatsApp (Opsional, Anda bisa menambahkan ini nanti) ---
                $this->sendNotification($id, 'update');
                // --- Akhir Notifikasi WhatsApp ---
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error updating ngaji record: " . $e->getMessage());
            return false;
        }
    }

    // Metode untuk menghapus catatan ngaji
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    private function sendNotification($ngajiId, $action) {
        try {
            $ngajiInfo = $this->getById($ngajiId);
            if (!$ngajiInfo || empty($ngajiInfo['no_telp_ortu'])) {
                error_log("Nomor telepon orang tua siswa tidak ditemukan atau data ngaji tidak lengkap untuk notifikasi.");
                return;
            }

            $recipientPhoneNumber = ltrim($ngajiInfo['no_telp_ortu'], '+');

            $notificationQueue = new NotificationQueue($this->conn);
            $jenis_materi = $ngajiInfo['jenis_materi'];
            $halaman_dari = $ngajiInfo['halaman_dari'];
            $halaman_sampai = $ngajiInfo['halaman_sampai'];
            $nama_guru = $ngajiInfo['nama_guru'] ?? 'Tidak Diketahui';
            $nama_siswa = $ngajiInfo['nama_siswa'];

            $templateName = 'ngaji_notifikasi';
            $templateParameters = [$nama_siswa, $jenis_materi, $halaman_dari, $halaman_sampai, $nama_guru];

            $queue_result = $notificationQueue->addNotificationToQueue($recipientPhoneNumber, $templateName, $templateParameters);

            if ($queue_result['status'] === 'success') {
                error_log("WhatsApp notification for ngaji siswa " . $nama_siswa . " added to queue.");
            } else {
                error_log("Failed to add WhatsApp notification for ngaji siswa " . $nama_siswa . " to queue: " . $queue_result['message']);
            }
        } catch (Exception $e) {
            error_log("Error adding WhatsApp notification for ngaji: " . $e->getMessage());
        }
    }
}
