<?php
// classes/PelanggaranSiswa.php

// Hapus require_once whatsapp_notifier.php karena akan dihandle oleh worker antrean
// require_once __DIR__ . '/../includes/whatsapp_notifier.php';
require_once __DIR__ . '/../classes/NotificationQueue.php'; // Pastikan ini ada
require_once __DIR__ . '/../classes/SchoolSettings.php'; // Tambahkan ini jika belum ada
require_once __DIR__ . '/../classes/Siswa.php'; // Untuk mendapatkan no_telp_ortu
require_once __DIR__ . '/../classes/JenisPelanggaran.php'; // Untuk mendapatkan nama jenis pelanggaran
require_once __DIR__ . '/../classes/Guru.php'; // Untuk mendapatkan nama petugas
require_once __DIR__ . '/../classes/TahunAjaran.php'; // Tambahkan ini

class PelanggaranSiswa {
    private $conn;
    private $table_name = "pelanggaran_siswa";
    private $table_siswa = "siswa";
    private $table_jenis_pelanggaran = "jenis_pelanggaran";
    private $table_guru = "guru"; // Asumsi petugas adalah dari tabel guru
    private $table_kelas = "kelas";
    private $table_users = "users"; // Asumsi tabel users untuk pemberi izin

    public $id;
    public $siswa_id;
    public $tahun_ajaran_id; // Tambahkan properti ini
    public $jenis_pelanggaran_id;
    public $tanggal_pelanggaran;
    public $waktu_pelanggaran;
    public $deskripsi_kejadian;
    public $poin_deduksi_aktual;
    public $petugas_id;
    public $status_tindak_lanjut;
    public $catatan_tindak_lanjut;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Metode untuk membaca semua pelanggaran siswa dengan detail
    public function getAll($filters = []) {
        $query = "SELECT ps.id, ps.tahun_ajaran_id, ps.tanggal_pelanggaran, ps.waktu_pelanggaran, ps.deskripsi_kejadian,
                         ps.poin_deduksi_aktual, ps.status_tindak_lanjut, ps.catatan_tindak_lanjut,
                         s.nisn, s.nama_lengkap AS nama_siswa, s.no_telp_ortu,
                         jp.nama_pelanggaran, jp.poin_deduksi AS default_poin_pelanggaran,
                         g.nama_lengkap AS nama_petugas,
                         ta.tahun_ajaran AS nama_tahun_ajaran
                  FROM " . $this->table_name . " ps
                  LEFT JOIN " . $this->table_siswa . " s ON ps.siswa_id = s.id
                  LEFT JOIN " . $this->table_jenis_pelanggaran . " jp ON ps.jenis_pelanggaran_id = jp.id
                  LEFT JOIN " . $this->table_guru . " g ON ps.petugas_id = g.id
                  LEFT JOIN tahun_ajaran ta ON ps.tahun_ajaran_id = ta.id
                  WHERE 1=1"; // Placeholder for dynamic filters

        $params = [];

        if (isset($filters['siswa_id']) && $filters['siswa_id']) {
            $query .= " AND ps.siswa_id = :siswa_id";
            $params[':siswa_id'] = $filters['siswa_id'];
        }
        if (isset($filters['jenis_pelanggaran_id']) && $filters['jenis_pelanggaran_id']) {
            $query .= " AND ps.jenis_pelanggaran_id = :jenis_pelanggaran_id";
            $params[':jenis_pelanggaran_id'] = $filters['jenis_pelanggaran_id'];
        }
        if (isset($filters['petugas_id']) && $filters['petugas_id']) {
            $query .= " AND ps.petugas_id = :petugas_id";
            $params[':petugas_id'] = $filters['petugas_id'];
        }
        if (isset($filters['status_tindak_lanjut']) && $filters['status_tindak_lanjut']) {
            $query .= " AND ps.status_tindak_lanjut = :status_tindak_lanjut";
            $params[':status_tindak_lanjut'] = $filters['status_tindak_lanjut'];
        }
        if (isset($filters['start_date']) && $filters['start_date']) {
            $query .= " AND ps.tanggal_pelanggaran >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        if (isset($filters['end_date']) && $filters['end_date']) {
            $query .= " AND ps.tanggal_pelanggaran <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }
        // Filter tahun ajaran
        if (isset($filters['tahun_ajaran_id']) && $filters['tahun_ajaran_id']) {
            $query .= " AND ps.tahun_ajaran_id = :tahun_ajaran_id";
            $params[':tahun_ajaran_id'] = $filters['tahun_ajaran_id'];
        }

        $query .= " ORDER BY ps.tanggal_pelanggaran DESC, ps.waktu_pelanggaran DESC";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Metode baru untuk mendapatkan data pelanggaran siswa khusus untuk laporan
    public function getForReport($siswa_id = null, $kelas_id = null, $start_date = null, $end_date = null, $tahun_ajaran_id = null) {
        $query = "SELECT ps.id, ps.siswa_id, ps.jenis_pelanggaran_id, ps.tanggal_pelanggaran, ps.waktu_pelanggaran,
                         ps.deskripsi_kejadian, ps.poin_deduksi_aktual, ps.petugas_id, ps.status_tindak_lanjut, ps.catatan_tindak_lanjut,
                         s.nisn, s.nama_lengkap AS nama_siswa,
                         jp.nama_pelanggaran, jp.poin_deduksi,
                         COALESCE(g.nama_lengkap, u.username) AS nama_petugas,
                         k.nama_kelas,
                         ta.tahun_ajaran AS nama_tahun_ajaran
                  FROM " . $this->table_name . " ps
                  LEFT JOIN " . $this->table_siswa . " s ON ps.siswa_id = s.id
                  LEFT JOIN " . $this->table_kelas . " k ON s.kelas_id = k.id
                  LEFT JOIN " . $this->table_jenis_pelanggaran . " jp ON ps.jenis_pelanggaran_id = jp.id
                  LEFT JOIN " . $this->table_guru . " g ON ps.petugas_id = g.id AND g.user_id IS NULL
                  LEFT JOIN " . $this->table_users . " u ON ps.petugas_id = u.id
                  LEFT JOIN tahun_ajaran ta ON ps.tahun_ajaran_id = ta.id
                  WHERE 1=1";

        $params = [];
        $where_clauses = [];

        if ($siswa_id) {
            $where_clauses[] = "ps.siswa_id = :siswa_id";
            $params[':siswa_id'] = $siswa_id;
        }
        if ($kelas_id) {
            $where_clauses[] = "s.kelas_id = :kelas_id";
            $params[':kelas_id'] = $kelas_id;
        }
        if ($tahun_ajaran_id) {
            $where_clauses[] = "ps.tahun_ajaran_id = :tahun_ajaran_id";
            $params[':tahun_ajaran_id'] = $tahun_ajaran_id;
        }
        if ($start_date) {
            $where_clauses[] = "ps.tanggal_pelanggaran >= :start_date";
            $params[':start_date'] = $start_date;
        }
        if ($end_date) {
            $where_clauses[] = "ps.tanggal_pelanggaran <= :end_date";
            $params[':end_date'] = $end_date;
        }

        if (!empty($where_clauses)) {
            $query .= " AND " . implode(" AND ", $where_clauses);
        }

        $query .= " ORDER BY ps.tanggal_pelanggaran DESC, s.nama_lengkap ASC";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    // Metode untuk membaca satu pelanggaran siswa berdasarkan ID
    public function getById($id) {
        $query = "SELECT ps.id, ps.siswa_id, ps.tahun_ajaran_id, ps.jenis_pelanggaran_id, ps.tanggal_pelanggaran,
                         ps.waktu_pelanggaran, ps.deskripsi_kejadian, ps.poin_deduksi_aktual,
                         ps.petugas_id, ps.status_tindak_lanjut, ps.catatan_tindak_lanjut,
                         s.nama_lengkap AS nama_siswa, s.nisn, s.no_telp_ortu,
                         jp.nama_pelanggaran, jp.poin_deduksi AS default_poin_pelanggaran,
                         g.nama_lengkap AS nama_petugas,
                         ta.tahun_ajaran AS nama_tahun_ajaran
                  FROM " . $this->table_name . " ps
                  LEFT JOIN " . $this->table_siswa . " s ON ps.siswa_id = s.id
                  LEFT JOIN " . $this->table_jenis_pelanggaran . " jp ON ps.jenis_pelanggaran_id = jp.id
                  LEFT JOIN " . $this->table_guru . " g ON ps.petugas_id = g.id
                  LEFT JOIN tahun_ajaran ta ON ps.tahun_ajaran_id = ta.id
                  WHERE ps.id = :id LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Metode baru untuk membaca semua data pelanggaran siswa untuk aplikasi orang tua
    // Memastikan data selalu difilter berdasarkan siswa_id yang diberikan
    public function getAllParent($siswa_id, $filters = []) {
        $query = "SELECT ps.*, s.nama_lengkap AS nama_siswa, s.nisn, kls.nama_kelas,
                         jp.nama_pelanggaran, jp.poin_deduksi AS poin_default,
                         g.nama_lengkap AS nama_petugas, ta.tahun_ajaran AS nama_tahun_ajaran
                  FROM " . $this->table_name . " ps
                  LEFT JOIN " . $this->table_siswa . " s ON ps.siswa_id = s.id
                  LEFT JOIN " . $this->table_kelas . " kls ON s.kelas_id = kls.id
                  LEFT JOIN " . $this->table_jenis_pelanggaran . " jp ON ps.jenis_pelanggaran_id = jp.id
                  LEFT JOIN " . $this->table_guru . " g ON ps.petugas_id = g.id
                  LEFT JOIN tahun_ajaran ta ON ps.tahun_ajaran_id = ta.id
                  WHERE ps.siswa_id = :siswa_id"; // Selalu filter berdasarkan siswa_id

        $params = [':siswa_id' => $siswa_id];

        if (isset($filters['jenis_pelanggaran_id']) && $filters['jenis_pelanggaran_id']) {
            $query .= " AND ps.jenis_pelanggaran_id = :jenis_pelanggaran_id";
            $params[':jenis_pelanggaran_id'] = $filters['jenis_pelanggaran_id'];
        }
        if (isset($filters['tahun_ajaran_id']) && $filters['tahun_ajaran_id']) {
            $query .= " AND ps.tahun_ajaran_id = :tahun_ajaran_id";
            $params[':tahun_ajaran_id'] = $filters['tahun_ajaran_id'];
        }

        $query .= " ORDER BY ps.tanggal_pelanggaran DESC, ps.waktu_pelanggaran DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Metode untuk menambah pelanggaran siswa baru
    public function create() {
        // Ambil tahun ajaran aktif secara otomatis
        $tahunAjaranModel = new TahunAjaran($this->conn);
        $activeTahunAjaran = $tahunAjaranModel->getActiveTahunAjaran();
        $this->tahun_ajaran_id = $activeTahunAjaran['id'] ?? null;

        $query = "INSERT INTO " . $this->table_name . " (siswa_id, tahun_ajaran_id, jenis_pelanggaran_id, tanggal_pelanggaran, waktu_pelanggaran, deskripsi_kejadian, poin_deduksi_aktual, petugas_id, status_tindak_lanjut, catatan_tindak_lanjut) VALUES (:siswa_id, :tahun_ajaran_id, :jenis_pelanggaran_id, :tanggal_pelanggaran, :waktu_pelanggaran, :deskripsi_kejadian, :poin_deduksi_aktual, :petugas_id, :status_tindak_lanjut, :catatan_tindak_lanjut)";
        $stmt = $this->conn->prepare($query);

        // Membersihkan data
        $this->siswa_id = htmlspecialchars(strip_tags($this->siswa_id));
        $this->tahun_ajaran_id = htmlspecialchars(strip_tags($this->tahun_ajaran_id)); // Bersihkan tahun_ajaran_id
        $this->jenis_pelanggaran_id = htmlspecialchars(strip_tags($this->jenis_pelanggaran_id));
        $this->tanggal_pelanggaran = htmlspecialchars(strip_tags($this->tanggal_pelanggaran));
        $this->waktu_pelanggaran = htmlspecialchars(strip_tags($this->waktu_pelanggaran));
        $this->deskripsi_kejadian = htmlspecialchars(strip_tags($this->deskripsi_kejadian));
        $this->poin_deduksi_aktual = htmlspecialchars(strip_tags($this->poin_deduksi_aktual));
        $this->petugas_id = htmlspecialchars(strip_tags($this->petugas_id));
        $this->status_tindak_lanjut = htmlspecialchars(strip_tags($this->status_tindak_lanjut));
        $this->catatan_tindak_lanjut = htmlspecialchars(strip_tags($this->catatan_tindak_lanjut));

        // Mengikat parameter
        $stmt->bindParam(':siswa_id', $this->siswa_id, PDO::PARAM_INT);
        $stmt->bindParam(':tahun_ajaran_id', $this->tahun_ajaran_id); // Bind tahun_ajaran_id
        $stmt->bindParam(':jenis_pelanggaran_id', $this->jenis_pelanggaran_id, PDO::PARAM_INT);
        $stmt->bindParam(':tanggal_pelanggaran', $this->tanggal_pelanggaran);
        $stmt->bindParam(':waktu_pelanggaran', $this->waktu_pelanggaran);
        $stmt->bindParam(':deskripsi_kejadian', $this->deskripsi_kejadian);
        $stmt->bindParam(':poin_deduksi_aktual', $this->poin_deduksi_aktual, PDO::PARAM_INT);
        $stmt->bindParam(':petugas_id', $this->petugas_id, PDO::PARAM_INT);
        $stmt->bindParam(':status_tindak_lanjut', $this->status_tindak_lanjut);
        $stmt->bindParam(':catatan_tindak_lanjut', $this->catatan_tindak_lanjut);

        if ($stmt->execute()) {
            // --- Tambahkan Notifikasi WhatsApp ke Antrean ---
            try {
                $notificationQueue = new NotificationQueue($this->conn); // Inisialisasi NotificationQueue

                // Dapatkan detail siswa, jenis pelanggaran, dan petugas untuk pesan notifikasi
                $siswaModel = new Siswa($this->conn);
                $siswaInfo = $siswaModel->findById($this->siswa_id);

                $jenisPelanggaranModel = new JenisPelanggaran($this->conn);
                $jpInfo = $jenisPelanggaranModel->getById($this->jenis_pelanggaran_id);

                $guruModel = new Guru($this->conn);
                $petugasInfo = $guruModel->findById($this->petugas_id); // Menggunakan findById()

                if ($siswaInfo && !empty($siswaInfo['no_telp_ortu'])) {
                    $recipientPhoneNumber = ltrim($siswaInfo['no_telp_ortu'], '+'); // Pastikan format bersih
                    $templateName = 'notifikasi_pelanggaran_siswa';
                    $templateParameters = [
                        $siswaInfo['nama_lengkap'],
                        $jpInfo['nama_pelanggaran'] ?? 'Tidak Diketahui',
                        $this->poin_deduksi_aktual,
                        $this->status_tindak_lanjut,
                        $petugasInfo['nama_lengkap'] ?? 'Tidak Diketahui'
                    ];

                    $queue_result = $notificationQueue->addNotificationToQueue($recipientPhoneNumber, $templateName, $templateParameters);

                    if ($queue_result['status'] === 'success') {
                        error_log("WhatsApp notification for pelanggaran siswa " . $siswaInfo['nama_lengkap'] . " added to queue.");
                    } else {
                        error_log("Failed to add WhatsApp notification for pelanggaran siswa " . $siswaInfo['nama_lengkap'] . " to queue: " . $queue_result['message']);
                    }
                } else {
                    error_log("Nomor telepon orang tua siswa tidak ditemukan atau data siswa tidak lengkap untuk notifikasi pelanggaran.");
                }
            } catch (Exception $e) {
                error_log("Error adding WhatsApp notification for pelanggaran to queue: " . $e->getMessage());
            }
            // --- Akhir Notifikasi WhatsApp ---
            return true;
        }
        return false;
    }

    // Metode untuk memperbarui pelanggaran siswa
    public function update() {
        // Ambil tahun ajaran aktif secara otomatis
        $tahunAjaranModel = new TahunAjaran($this->conn);
        $activeTahunAjaran = $tahunAjaranModel->getActiveTahunAjaran();
        $this->tahun_ajaran_id = $activeTahunAjaran['id'] ?? null;

        $query = "UPDATE " . $this->table_name . " SET
                    siswa_id = :siswa_id,
                    tahun_ajaran_id = :tahun_ajaran_id,
                    jenis_pelanggaran_id = :jenis_pelanggaran_id,
                    tanggal_pelanggaran = :tanggal_pelanggaran,
                    waktu_pelanggaran = :waktu_pelanggaran,
                    deskripsi_kejadian = :deskripsi_kejadian,
                    poin_deduksi_aktual = :poin_deduksi_aktual,
                    petugas_id = :petugas_id,
                    status_tindak_lanjut = :status_tindak_lanjut,
                    catatan_tindak_lanjut = :catatan_tindak_lanjut,
                    updated_at = CURRENT_TIMESTAMP
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        // Membersihkan data
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->siswa_id = htmlspecialchars(strip_tags($this->siswa_id));
        $this->tahun_ajaran_id = htmlspecialchars(strip_tags($this->tahun_ajaran_id)); // Bersihkan tahun_ajaran_id
        $this->jenis_pelanggaran_id = htmlspecialchars(strip_tags($this->jenis_pelanggaran_id));
        $this->tanggal_pelanggaran = htmlspecialchars(strip_tags($this->tanggal_pelanggaran));
        $this->waktu_pelanggaran = htmlspecialchars(strip_tags($this->waktu_pelanggaran));
        $this->deskripsi_kejadian = htmlspecialchars(strip_tags($this->deskripsi_kejadian));
        $this->poin_deduksi_aktual = htmlspecialchars(strip_tags($this->poin_deduksi_aktual));
        $this->petugas_id = htmlspecialchars(strip_tags($this->petugas_id));
        $this->status_tindak_lanjut = htmlspecialchars(strip_tags($this->status_tindak_lanjut));
        $this->catatan_tindak_lanjut = htmlspecialchars(strip_tags($this->catatan_tindak_lanjut));

        // Mengikat parameter
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        $stmt->bindParam(':siswa_id', $this->siswa_id, PDO::PARAM_INT);
        $stmt->bindParam(':tahun_ajaran_id', $this->tahun_ajaran_id); // Bind tahun_ajaran_id
        $stmt->bindParam(':jenis_pelanggaran_id', $this->jenis_pelanggaran_id, PDO::PARAM_INT);
        $stmt->bindParam(':tanggal_pelanggaran', $this->tanggal_pelanggaran);
        $stmt->bindParam(':waktu_pelanggaran', $this->waktu_pelanggaran);
        $stmt->bindParam(':deskripsi_kejadian', $this->deskripsi_kejadian);
        $stmt->bindParam(':poin_deduksi_aktual', $this->poin_deduksi_aktual, PDO::PARAM_INT);
        $stmt->bindParam(':petugas_id', $this->petugas_id, PDO::PARAM_INT);
        $stmt->bindParam(':status_tindak_lanjut', $this->status_tindak_lanjut);
        $stmt->bindParam(':catatan_tindak_lanjut', $this->catatan_tindak_lanjut);

        if ($stmt->execute()) {
            // --- Tambahkan Notifikasi WhatsApp ke Antrean (mirip dengan create) ---
            try {
                $notificationQueue = new NotificationQueue($this->conn); // Inisialisasi NotificationQueue

                $siswaModel = new Siswa($this->conn);
                $siswaInfo = $siswaModel->findById($this->siswa_id);

                $jenisPelanggaranModel = new JenisPelanggaran($this->conn);
                $jpInfo = $jenisPelanggaranModel->getById($this->jenis_pelanggaran_id);

                $guruModel = new Guru($this->conn);
                $petugasInfo = $guruModel->findById($this->petugas_id); // Menggunakan findById()

                if ($siswaInfo && !empty($siswaInfo['no_telp_ortu'])) {
                    $recipientPhoneNumber = ltrim($siswaInfo['no_telp_ortu'], '+'); // Pastikan format bersih
                    $templateName = 'notifikasi_pelanggaran_siswa'; // Gunakan template yang sama
                    $templateParameters = [
                        $siswaInfo['nama_lengkap'],
                        $jpInfo['nama_pelanggaran'] ?? 'Tidak Diketahui',
                        $this->poin_deduksi_aktual,
                        $this->status_tindak_lanjut,
                        $petugasInfo['nama_lengkap'] ?? 'Tidak Diketahui'
                    ];

                    $queue_result = $notificationQueue->addNotificationToQueue($recipientPhoneNumber, $templateName, $templateParameters);

                    if ($queue_result['status'] === 'success') {
                        error_log("WhatsApp notification updated for pelanggaran siswa " . $siswaInfo['nama_lengkap'] . " added to queue.");
                    } else {
                        error_log("Failed to add WhatsApp notification update for pelanggaran siswa " . $siswaInfo['nama_lengkap'] . " to queue: " . $queue_result['message']);
                    }
                } else {
                    error_log("Nomor telepon orang tua siswa tidak ditemukan atau data siswa tidak lengkap untuk notifikasi pelanggaran update.");
                }
            } catch (Exception $e) {
                error_log("Error adding WhatsApp notification for pelanggaran update to queue: " . $e->getMessage());
            }
            // --- Akhir Notifikasi WhatsApp ---
            return true;
        }
        return false;
    }

    // Metode untuk menghapus pelanggaran siswa
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }
}
