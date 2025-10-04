<?php
// classes/SoroganSiswa.php

require_once __DIR__ . '/../classes/NotificationQueue.php';
require_once __DIR__ . '/../classes/SchoolSettings.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/KitabNadhom.php';
require_once __DIR__ . '/../classes/Guru.php';
require_once __DIR__ . '/../classes/TahunAjaran.php'; // Tambahkan ini

class SoroganSiswa {
    private $conn;
    private $table_name = "sorogan_siswa";
    private $table_siswa = "siswa";
    private $table_kitab_nadhom = "kitab_nadhom";
    private $table_guru = "guru";
    private $table_kelas = "kelas";

    public $id;
    public $siswa_id;
    public $tahun_ajaran_id; // Tambahkan properti ini
    public $kitab_nadhom_id;
    public $guru_id;
    public $tanggal_setor;
    public $halaman_dari;
    public $halaman_sampai;
    public $keterangan_bacaan;
    public $nilai;
    public $status_bacaan;
    public $catatan_guru;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Metode untuk membaca semua data sorogan dengan filter dan join
    public function getAll($filters = []) {
        $query = "SELECT s.id, s.tanggal_setor, s.halaman_dari, s.halaman_sampai, s.nilai, s.status_bacaan, s.keterangan_bacaan,
                         t.nama_lengkap AS nama_siswa, t.nisn,
                         k.nama_kitab AS nama_kitab,
                         g.nama_lengkap AS nama_guru,
                         kls.nama_kelas,
                         ta.tahun_ajaran AS nama_tahun_ajaran
                  FROM " . $this->table_name . " s
                  JOIN " . $this->table_siswa . " t ON s.siswa_id = t.id
                  LEFT JOIN " . $this->table_kelas . " kls ON t.kelas_id = kls.id
                  JOIN " . $this->table_kitab_nadhom . " k ON s.kitab_nadhom_id = k.id
                  JOIN " . $this->table_guru . " g ON s.guru_id = g.id
                  LEFT JOIN tahun_ajaran ta ON s.tahun_ajaran_id = ta.id"; // Join tabel tahun_ajaran

        $where_clauses = [];
        $params = [];

        if (!empty($filters['siswa_id'])) {
            $where_clauses[] = "s.siswa_id = :siswa_id";
            $params[':siswa_id'] = $filters['siswa_id'];
        }
        if (!empty($filters['kitab_nadhom_id'])) {
            $where_clauses[] = "s.kitab_nadhom_id = :kitab_nadhom_id";
            $params[':kitab_nadhom_id'] = $filters['kitab_nadhom_id'];
        }
        if (!empty($filters['guru_id'])) {
            $where_clauses[] = "s.guru_id = :guru_id";
            $params[':guru_id'] = $filters['guru_id'];
        }
        if (!empty($filters['status_bacaan'])) {
            $where_clauses[] = "s.status_bacaan = :status_bacaan";
            $params[':status_bacaan'] = $filters['status_bacaan'];
        }
        if (!empty($filters['kelas_id'])) {
            $where_clauses[] = "kls.id = :kelas_id";
            $params[':kelas_id'] = $filters['kelas_id'];
        }
        if (isset($filters['tahun_ajaran_id']) && $filters['tahun_ajaran_id']) {
            $where_clauses[] = "s.tahun_ajaran_id = :tahun_ajaran_id";
            $params[':tahun_ajaran_id'] = $filters['tahun_ajaran_id'];
        }

        // Filter tambahan untuk laporan berdasarkan tanggal
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $where_clauses[] = "s.tanggal_setor BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $filters['start_date'];
            $params[':end_date'] = $filters['end_date'];
        }

        if (!empty($where_clauses)) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }

        $query .= " ORDER BY s.tanggal_setor DESC, s.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Metode baru untuk mendapatkan data sorogan siswa khusus untuk laporan
    public function getForReport($siswa_id = null, $kelas_id = null, $start_date = null, $end_date = null, $tahun_ajaran_id = null) {
        $query = "SELECT ss.id, ss.siswa_id, ss.kitab_nadhom_id, ss.guru_id, ss.tanggal_setor, ss.halaman_dari,
                         ss.halaman_sampai, ss.keterangan_bacaan, ss.nilai, ss.status_bacaan, ss.catatan_guru,
                         s.nama_lengkap AS nama_siswa, s.nisn,
                         k.nama_kelas,
                         kn.nama_kitab,
                         g.nama_lengkap AS nama_guru,
                         ta.tahun_ajaran AS nama_tahun_ajaran
                  FROM " . $this->table_name . " ss
                  LEFT JOIN " . $this->table_siswa . " s ON ss.siswa_id = s.id
                  LEFT JOIN " . $this->table_kelas . " k ON s.kelas_id = k.id
                  LEFT JOIN " . $this->table_kitab_nadhom . " kn ON ss.kitab_nadhom_id = kn.id
                  LEFT JOIN " . $this->table_guru . " g ON ss.guru_id = g.id
                  LEFT JOIN tahun_ajaran ta ON ss.tahun_ajaran_id = ta.id
                  WHERE kn.jenis_kitab = 'Sorogan'";

        $params = [];
        $where_clauses = [];

        if ($siswa_id) {
            $where_clauses[] = "ss.siswa_id = :siswa_id";
            $params[':siswa_id'] = $siswa_id;
        }
        if ($kelas_id) {
            $where_clauses[] = "s.kelas_id = :kelas_id";
            $params[':kelas_id'] = $kelas_id;
        }
        if ($tahun_ajaran_id) {
            $where_clauses[] = "ss.tahun_ajaran_id = :tahun_ajaran_id";
            $params[':tahun_ajaran_id'] = $tahun_ajaran_id;
        }
        if ($start_date) {
            $where_clauses[] = "ss.tanggal_setor >= :start_date";
            $params[':start_date'] = $start_date;
        }
        if ($end_date) {
            $where_clauses[] = "ss.tanggal_setor <= :end_date";
            $params[':end_date'] = $end_date;
        }


        if (!empty($where_clauses)) {
            $query .= " AND " . implode(" AND ", $where_clauses);
        }

        $query .= " ORDER BY ss.tanggal_setor DESC, s.nama_lengkap ASC, kn.nama_kitab ASC";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Metode untuk membaca satu data sorogan berdasarkan ID
    public function getById($id) {
        $query = "SELECT ss.id, ss.siswa_id, ss.tahun_ajaran_id, ss.kitab_nadhom_id, ss.guru_id, ss.tanggal_setor,
                         ss.halaman_dari, ss.halaman_sampai, ss.keterangan_bacaan, ss.nilai,
                         ss.status_bacaan, ss.catatan_guru,
                         s.nama_lengkap AS nama_siswa, s.nisn, s.no_telp_ortu,
                         kn.nama_kitab,
                         g.nama_lengkap AS nama_guru,
                         ta.tahun_ajaran AS nama_tahun_ajaran
                  FROM " . $this->table_name . " ss
                  LEFT JOIN " . $this->table_siswa . " s ON ss.siswa_id = s.id
                  LEFT JOIN " . $this->table_kitab_nadhom . " kn ON ss.kitab_nadhom_id = kn.id
                  LEFT JOIN " . $this->table_guru . " g ON ss.guru_id = g.id
                  LEFT JOIN tahun_ajaran ta ON ss.tahun_ajaran_id = ta.id
                  WHERE ss.id = :id AND kn.jenis_kitab = 'Sorogan' LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Metode baru untuk membaca semua data sorogan siswa untuk aplikasi orang tua
    // Memastikan data selalu difilter berdasarkan siswa_id yang diberikan
    public function getAllParent($siswa_id, $filters = []) {
        $query = "SELECT ss.id, ss.tanggal_setor, ss.halaman_dari, ss.halaman_sampai, ss.nilai, ss.status_bacaan, ss.keterangan_bacaan,
                         s.nama_lengkap AS nama_siswa, s.nisn,
                         kn.nama_kitab AS nama_kitab,
                         g.nama_lengkap AS nama_guru,
                         kls.nama_kelas,
                         ta.tahun_ajaran AS nama_tahun_ajaran
                  FROM " . $this->table_name . " ss
                  LEFT JOIN " . $this->table_siswa . " s ON ss.siswa_id = s.id
                  LEFT JOIN " . $this->table_kelas . " kls ON s.kelas_id = kls.id
                  LEFT JOIN " . $this->table_kitab_nadhom . " kn ON ss.kitab_nadhom_id = kn.id
                  LEFT JOIN " . $this->table_guru . " g ON ss.guru_id = g.id
                  LEFT JOIN tahun_ajaran ta ON ss.tahun_ajaran_id = ta.id
                  WHERE ss.siswa_id = :siswa_id"; // Selalu filter berdasarkan siswa_id

        $params = [':siswa_id' => $siswa_id];

        if (isset($filters['kitab_nadhom_id']) && $filters['kitab_nadhom_id']) {
            $query .= " AND ss.kitab_nadhom_id = :kitab_nadhom_id";
            $params[':kitab_nadhom_id'] = $filters['kitab_nadhom_id'];
        }
        if (isset($filters['tahun_ajaran_id']) && $filters['tahun_ajaran_id']) {
            $query .= " AND ss.tahun_ajaran_id = :tahun_ajaran_id";
            $params[':tahun_ajaran_id'] = $filters['tahun_ajaran_id'];
        }

        $query .= " ORDER BY ss.tanggal_setor DESC, ss.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Metode untuk menambah data sorogan baru
    public function create() {
        // Ambil tahun ajaran aktif secara otomatis
        $tahunAjaranModel = new TahunAjaran($this->conn);
        $activeTahunAjaran = $tahunAjaranModel->getActiveTahunAjaran();
        $this->tahun_ajaran_id = $activeTahunAjaran['id'] ?? null;

        $query = "INSERT INTO " . $this->table_name . " (siswa_id, tahun_ajaran_id, kitab_nadhom_id, guru_id, tanggal_setor, halaman_dari, halaman_sampai, keterangan_bacaan, nilai, status_bacaan, catatan_guru) VALUES (:siswa_id, :tahun_ajaran_id, :kitab_nadhom_id, :guru_id, :tanggal_setor, :halaman_dari, :halaman_sampai, :keterangan_bacaan, :nilai, :status_bacaan, :catatan_guru)";
        $stmt = $this->conn->prepare($query);

        $this->siswa_id = htmlspecialchars(strip_tags($this->siswa_id));
        $this->tahun_ajaran_id = htmlspecialchars(strip_tags($this->tahun_ajaran_id)); // Bersihkan tahun_ajaran_id
        $this->kitab_nadhom_id = htmlspecialchars(strip_tags($this->kitab_nadhom_id));
        $this->guru_id = htmlspecialchars(strip_tags($this->guru_id));
        $this->tanggal_setor = htmlspecialchars(strip_tags($this->tanggal_setor));
        $this->halaman_dari = htmlspecialchars(strip_tags($this->halaman_dari));
        $this->halaman_sampai = htmlspecialchars(strip_tags($this->halaman_sampai));
        $this->keterangan_bacaan = htmlspecialchars(strip_tags($this->keterangan_bacaan));
        $this->nilai = htmlspecialchars(strip_tags($this->nilai));
        $this->status_bacaan = htmlspecialchars(strip_tags($this->status_bacaan));
        $this->catatan_guru = htmlspecialchars(strip_tags($this->catatan_guru));

        $stmt->bindParam(':siswa_id', $this->siswa_id, PDO::PARAM_INT);
        $stmt->bindParam(':tahun_ajaran_id', $this->tahun_ajaran_id); // Bind tahun_ajaran_id
        $stmt->bindParam(':kitab_nadhom_id', $this->kitab_nadhom_id, PDO::PARAM_INT);
        $stmt->bindParam(':guru_id', $this->guru_id, PDO::PARAM_INT);
        $stmt->bindParam(':tanggal_setor', $this->tanggal_setor);
        $stmt->bindParam(':halaman_dari', $this->halaman_dari);
        $stmt->bindParam(':halaman_sampai', $this->halaman_sampai);
        $stmt->bindParam(':keterangan_bacaan', $this->keterangan_bacaan);
        $stmt->bindParam(':nilai', $this->nilai);
        $stmt->bindParam(':status_bacaan', $this->status_bacaan);
        $stmt->bindParam(':catatan_guru', $this->catatan_guru);

        if ($stmt->execute()) {
            try {
                $notificationQueue = new NotificationQueue($this->conn);
                $siswaModel = new Siswa($this->conn);
                $siswaInfo = $siswaModel->findById($this->siswa_id);
                $kitabNadhomModel = new KitabNadhom($this->conn);
                $kitabInfo = $kitabNadhomModel->getById($this->kitab_nadhom_id);
                $guruModel = new Guru($this->conn);
                $guruInfo = $guruModel->findById($this->guru_id);

                if ($siswaInfo && !empty($siswaInfo['no_telp_ortu'])) {
                    $recipientPhoneNumber = ltrim($siswaInfo['no_telp_ortu'], '+');
                    $templateName = 'notifikasi_sorogan_siswa';
                    $templateParameters = [
                        $siswaInfo['nama_lengkap'],
                        $kitabInfo['nama_kitab'] ?? 'Tidak Diketahui',
                        $this->halaman_dari,
                        $this->halaman_sampai,
                        $this->status_bacaan,
                        $this->nilai ?? 'Tidak Ada',
                        $guruInfo['nama_lengkap'] ?? 'Tidak Diketahui'
                    ];

                    $queue_result = $notificationQueue->addNotificationToQueue($recipientPhoneNumber, $templateName, $templateParameters);

                    if ($queue_result['status'] === 'success') {
                        error_log("WhatsApp notification for sorogan siswa " . $siswaInfo['nama_lengkap'] . " added to queue.");
                    } else {
                        error_log("Failed to add WhatsApp notification for sorogan siswa " . $siswaInfo['nama_lengkap'] . " to queue: " . $queue_result['message']);
                    }
                } else {
                    error_log("Nomor telepon orang tua siswa tidak ditemukan atau data siswa tidak lengkap untuk notifikasi sorogan.");
                }
            } catch (Exception $e) {
                error_log("Error adding WhatsApp notification for sorogan to queue: " . $e->getMessage());
            }
            return true;
        }
        return false;
    }

    // Metode untuk memperbarui data sorogan
    public function update() {
        // Ambil tahun ajaran aktif secara otomatis
        $tahunAjaranModel = new TahunAjaran($this->conn);
        $activeTahunAjaran = $tahunAjaranModel->getActiveTahunAjaran();
        $this->tahun_ajaran_id = $activeTahunAjaran['id'] ?? null;

        $query = "UPDATE " . $this->table_name . " SET
                    siswa_id = :siswa_id,
                    tahun_ajaran_id = :tahun_ajaran_id,
                    kitab_nadhom_id = :kitab_nadhom_id,
                    guru_id = :guru_id,
                    tanggal_setor = :tanggal_setor,
                    halaman_dari = :halaman_dari,
                    halaman_sampai = :halaman_sampai,
                    keterangan_bacaan = :keterangan_bacaan,
                    nilai = :nilai,
                    status_bacaan = :status_bacaan,
                    catatan_guru = :catatan_guru,
                    updated_at = CURRENT_TIMESTAMP
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->siswa_id = htmlspecialchars(strip_tags($this->siswa_id));
        $this->tahun_ajaran_id = htmlspecialchars(strip_tags($this->tahun_ajaran_id)); // Bersihkan tahun_ajaran_id
        $this->kitab_nadhom_id = htmlspecialchars(strip_tags($this->kitab_nadhom_id));
        $this->guru_id = htmlspecialchars(strip_tags($this->guru_id));
        $this->tanggal_setor = htmlspecialchars(strip_tags($this->tanggal_setor));
        $this->halaman_dari = htmlspecialchars(strip_tags($this->halaman_dari));
        $this->halaman_sampai = htmlspecialchars(strip_tags($this->halaman_sampai));
        $this->keterangan_bacaan = htmlspecialchars(strip_tags($this->keterangan_bacaan));
        $this->nilai = htmlspecialchars(strip_tags($this->nilai));
        $this->status_bacaan = htmlspecialchars(strip_tags($this->status_bacaan));
        $this->catatan_guru = htmlspecialchars(strip_tags($this->catatan_guru));

        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        $stmt->bindParam(':siswa_id', $this->siswa_id, PDO::PARAM_INT);
        $stmt->bindParam(':tahun_ajaran_id', $this->tahun_ajaran_id); // Bind tahun_ajaran_id
        $stmt->bindParam(':kitab_nadhom_id', $this->kitab_nadhom_id, PDO::PARAM_INT);
        $stmt->bindParam(':guru_id', $this->guru_id, PDO::PARAM_INT);
        $stmt->bindParam(':tanggal_setor', $this->tanggal_setor);
        $stmt->bindParam(':halaman_dari', $this->halaman_dari);
        $stmt->bindParam(':halaman_sampai', $this->halaman_sampai);
        $stmt->bindParam(':keterangan_bacaan', $this->keterangan_bacaan);
        $stmt->bindParam(':nilai', $this->nilai);
        $stmt->bindParam(':status_bacaan', $this->status_bacaan);
        $stmt->bindParam(':catatan_guru', $this->catatan_guru);

        if ($stmt->execute()) {
            try {
                $notificationQueue = new NotificationQueue($this->conn);
                $siswaModel = new Siswa($this->conn);
                $siswaInfo = $siswaModel->findById($this->siswa_id);
                $kitabNadhomModel = new KitabNadhom($this->conn);
                $kitabInfo = $kitabNadhomModel->getById($this->kitab_nadhom_id);
                $guruModel = new Guru($this->conn);
                $guruInfo = $guruModel->findById($this->guru_id);

                if ($siswaInfo && !empty($siswaInfo['no_telp_ortu'])) {
                    $recipientPhoneNumber = ltrim($siswaInfo['no_telp_ortu'], '+');
                    $templateName = 'notifikasi_sorogan_siswa';
                    $templateParameters = [
                        $siswaInfo['nama_lengkap'],
                        $kitabInfo['nama_kitab'] ?? 'Tidak Diketahui',
                        $this->halaman_dari,
                        $this->halaman_sampai,
                        $this->status_bacaan,
                        $this->nilai ?? 'Tidak Ada',
                        $guruInfo['nama_lengkap'] ?? 'Tidak Diketahui'
                    ];

                    $queue_result = $notificationQueue->addNotificationToQueue($recipientPhoneNumber, $templateName, $templateParameters);

                    if ($queue_result['status'] === 'success') {
                        error_log("WhatsApp notification updated for sorogan siswa " . $siswaInfo['nama_lengkap'] . " added to queue.");
                    } else {
                        error_log("Failed to add WhatsApp notification update for sorogan siswa " . $siswaInfo['nama_lengkap'] . " to queue: " . $queue_result['message']);
                    }
                } else {
                    error_log("Nomor telepon orang tua siswa tidak ditemukan atau data siswa tidak lengkap untuk notifikasi sorogan update.");
                }
            } catch (Exception $e) {
                error_log("Error adding WhatsApp notification for sorogan update to queue: " . $e->getMessage());
            }
            return true;
        }
        return false;
    }

    // Metode untuk menghapus data sorogan
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
