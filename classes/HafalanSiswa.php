<?php
// classes/HafalanSiswa.php

require_once __DIR__ . '/../classes/NotificationQueue.php';
require_once __DIR__ . '/../classes/SchoolSettings.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/KitabNadhom.php';
require_once __DIR__ . '/../classes/Guru.php';
require_once __DIR__ . '/../classes/TahunAjaran.php'; // Tambahkan ini

class HafalanSiswa {
    private $conn;
    private $table_name = "hafalan_siswa";
    private $table_siswa = "siswa";
    private $table_kitab_nadhom = "kitab_nadhom";
    private $table_guru = "guru";
    private $table_kelas = "kelas";

    public $id;
    public $siswa_id;
    public $tahun_ajaran_id; // Tambahkan properti ini
    public $kitab_nadhom_id;
    public $tanggal_setor;
    public $bagian_hafalan;
    public $nilai;
    public $guru_id;
    public $catatan;
    // Tambahan: kolom baru
    public $jenis_setor;
    public $status_hafalan;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Metode untuk menghitung total hafalan siswa untuk satu kitab
    public function getTotalHafalan($siswa_id, $kitab_nadhom_id, $tahun_ajaran_id = null) {
        $query = "SELECT SUM(CAST(bagian_hafalan AS UNSIGNED)) AS total_hafalan_numerik
                  FROM " . $this->table_name . "
                  WHERE siswa_id = :siswa_id AND kitab_nadhom_id = :kitab_nadhom_id";

        if ($tahun_ajaran_id !== null) {
            $query .= " AND tahun_ajaran_id = :tahun_ajaran_id";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':siswa_id', $siswa_id);
        $stmt->bindParam(':kitab_nadhom_id', $kitab_nadhom_id);

        if ($tahun_ajaran_id !== null) {
            $stmt->bindParam(':tahun_ajaran_id', $tahun_ajaran_id);
        }

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total_hafalan_numerik'] ?? 0;
    }

    // Metode untuk membaca semua hafalan siswa dengan detail
    public function getAll($filters = []) {
        $query = "SELECT hs.id, hs.tanggal_setor, hs.bagian_hafalan, hs.nilai, hs.catatan, hs.jenis_setor, hs.status_hafalan,
                         s.nisn, s.nama_lengkap AS nama_siswa, s.no_telp_ortu,
                         kn.nama_kitab, kn.keterangan AS kitab_keterangan, kn.jenis_kitab, kn.target_hafalan,
                         g.nip, g.nama_lengkap AS nama_guru,
                         ta.tahun_ajaran AS nama_tahun_ajaran
                  FROM " . $this->table_name . " hs
                  LEFT JOIN " . $this->table_siswa . " s ON hs.siswa_id = s.id
                  LEFT JOIN " . $this->table_kitab_nadhom . " kn ON hs.kitab_nadhom_id = kn.id
                  LEFT JOIN " . $this->table_guru . " g ON hs.guru_id = g.id
                  LEFT JOIN tahun_ajaran ta ON hs.tahun_ajaran_id = ta.id
                  WHERE 1=1";

        $params = [];

        if (isset($filters['siswa_id']) && $filters['siswa_id']) {
            $query .= " AND hs.siswa_id = :siswa_id";
            $params[':siswa_id'] = $filters['siswa_id'];
        }
        if (isset($filters['kitab_nadhom_id']) && $filters['kitab_nadhom_id']) {
            $query .= " AND hs.kitab_nadhom_id = :kitab_nadhom_id";
            $params[':kitab_nadhom_id'] = $filters['kitab_nadhom_id'];
        }
        if (isset($filters['guru_id']) && $filters['guru_id']) {
            $query .= " AND hs.guru_id = :guru_id";
            $params[':guru_id'] = $filters['guru_id'];
        }
        if (isset($filters['start_date']) && $filters['start_date']) {
            $query .= " AND hs.tanggal_setor >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        if (isset($filters['end_date']) && $filters['end_date']) {
            $query .= " AND hs.tanggal_setor <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }
        if (isset($filters['jenis_setor']) && $filters['jenis_setor']) {
            $query .= " AND hs.jenis_setor = :jenis_setor";
            $params[':jenis_setor'] = $filters['jenis_setor'];
        }
        if (isset($filters['status_hafalan']) && $filters['status_hafalan']) {
            $query .= " AND hs.status_hafalan = :status_hafalan";
            $params[':status_hafalan'] = $filters['status_hafalan'];
        }
        // Filter tahun ajaran
        if (isset($filters['tahun_ajaran_id']) && $filters['tahun_ajaran_id']) {
            $query .= " AND hs.tahun_ajaran_id = :tahun_ajaran_id";
            $params[':tahun_ajaran_id'] = $filters['tahun_ajaran_id'];
        }


        $query .= " ORDER BY hs.tanggal_setor DESC, s.nama_lengkap ASC";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Metode untuk membaca satu hafalan siswa berdasarkan ID
    public function getById($id) {
        $query = "SELECT hs.id, hs.siswa_id, hs.tahun_ajaran_id, hs.kitab_nadhom_id, hs.tanggal_setor, hs.bagian_hafalan, hs.nilai, hs.guru_id, hs.catatan, hs.jenis_setor, hs.status_hafalan,
                         s.nama_lengkap AS nama_siswa, s.nisn, s.no_telp_ortu,
                         kn.nama_kitab, kn.jenis_kitab, kn.target_hafalan,
                         g.nama_lengkap AS nama_guru,
                         ta.tahun_ajaran AS nama_tahun_ajaran
                  FROM " . $this->table_name . " hs
                  LEFT JOIN " . $this->table_siswa . " s ON hs.siswa_id = s.id
                  LEFT JOIN " . $this->table_kitab_nadhom . " kn ON hs.kitab_nadhom_id = kn.id
                  LEFT JOIN " . $this->table_guru . " g ON hs.guru_id = g.id
                  LEFT JOIN tahun_ajaran ta ON hs.tahun_ajaran_id = ta.id
                  WHERE hs.id = :id LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Metode baru untuk mendapatkan data hafalan siswa khusus untuk laporan
    public function getForReport($siswa_id = null, $kelas_id = null, $start_date = null, $end_date = null, $tahun_ajaran_id = null) {
        $query = "SELECT hs.id, hs.siswa_id, hs.kitab_nadhom_id, hs.tanggal_setor, hs.bagian_hafalan, hs.nilai, hs.guru_id, hs.catatan, hs.jenis_setor, hs.status_hafalan,
                         s.nisn, s.nama_lengkap AS nama_siswa,
                         kn.nama_kitab,
                         g.nama_lengkap AS nama_guru,
                         k.nama_kelas,
                         ta.tahun_ajaran AS nama_tahun_ajaran
                  FROM " . $this->table_name . " hs
                  LEFT JOIN " . $this->table_siswa . " s ON hs.siswa_id = s.id
                  LEFT JOIN " . $this->table_kelas . " k ON s.kelas_id = k.id
                  LEFT JOIN " . $this->table_kitab_nadhom . " kn ON hs.kitab_nadhom_id = kn.id
                  LEFT JOIN " . $this->table_guru . " g ON hs.guru_id = g.id
                  LEFT JOIN tahun_ajaran ta ON hs.tahun_ajaran_id = ta.id
                  WHERE 1=1";

        $params = [];
        $where_clauses = [];

        if ($siswa_id) {
            $where_clauses[] = "hs.siswa_id = :siswa_id";
            $params[':siswa_id'] = $siswa_id;
        }
        if ($kelas_id) {
            $where_clauses[] = "s.kelas_id = :kelas_id";
            $params[':kelas_id'] = $kelas_id;
        }
        if ($tahun_ajaran_id) {
            $where_clauses[] = "hs.tahun_ajaran_id = :tahun_ajaran_id";
            $params[':tahun_ajaran_id'] = $tahun_ajaran_id;
        }

        if (!empty($where_clauses)) {
            $query .= " AND " . implode(" AND ", $where_clauses);
        }

        $query .= " ORDER BY hs.tanggal_setor DESC, s.nama_lengkap ASC";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Metode baru untuk membaca semua data hafalan siswa untuk aplikasi orang tua
    // Memastikan data selalu difilter berdasarkan siswa_id yang diberikan
    public function getAllParent($siswa_id, $filters = []) {
        $query = "SELECT h.*, s.nama_lengkap AS nama_siswa, kn.nama_kitab, kn.jenis_kitab, g.nama_lengkap AS nama_guru, ta.tahun_ajaran AS nama_tahun_ajaran
                  FROM " . $this->table_name . " h
                  LEFT JOIN " . $this->table_siswa . " s ON h.siswa_id = s.id
                  LEFT JOIN " . ((isset($this->table_kitab_nadhom)) ? $this->table_kitab_nadhom : 'kitab_nadhom') . " kn ON h.kitab_nadhom_id = kn.id
                  LEFT JOIN " . ((isset($this->table_guru)) ? $this->table_guru : 'guru') . " g ON h.guru_id = g.id
                  LEFT JOIN tahun_ajaran ta ON h.tahun_ajaran_id = ta.id
                  WHERE h.siswa_id = :siswa_id"; // Selalu filter berdasarkan siswa_id

        $params = [':siswa_id' => $siswa_id];

        if (isset($filters['kitab_nadhom_id']) && $filters['kitab_nadhom_id']) {
            $query .= " AND h.kitab_nadhom_id = :kitab_nadhom_id";
            $params[':kitab_nadhom_id'] = $filters['kitab_nadhom_id'];
        }
        if (isset($filters['tahun_ajaran_id']) && $filters['tahun_ajaran_id']) {
            $query .= " AND h.tahun_ajaran_id = :tahun_ajaran_id";
            $params[':tahun_ajaran_id'] = $filters['tahun_ajaran_id'];
        }

        $query .= " ORDER BY h.tanggal_setor DESC, h.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Metode baru untuk membaca hafalan siswa dengan filter
    public function getFiltered($filters = []) {
        $query = "SELECT
                    h.id, h.siswa_id, h.tahun_ajaran_id, h.kitab_nadhom_id, h.tanggal_setor, h.bagian_hafalan, h.nilai, h.guru_id, h.catatan, h.jenis_setor, h.status_hafalan, h.created_at, h.updated_at,
                    s.nama_lengkap AS nama_siswa,
                    kn.nama_kitab AS nama_kitab,
                    g.nama_lengkap AS nama_guru
                  FROM
                    " . $this->table_name . " h
                    LEFT JOIN " . $this->table_siswa . " s ON h.siswa_id = s.id
                    LEFT JOIN " . $this->table_kitab_nadhom . " kn ON h.kitab_nadhom_id = kn.id
                    LEFT JOIN " . $this->table_guru . " g ON h.guru_id = g.id";

        $conditions = [];
        $params = [];

        if (!empty($filters['tahun_ajaran_id'])) {
            $conditions[] = "h.tahun_ajaran_id = :tahun_ajaran_id";
            $params[':tahun_ajaran_id'] = $filters['tahun_ajaran_id'];
        }
        if (!empty($filters['siswa_id'])) {
            $conditions[] = "h.siswa_id = :siswa_id";
            $params[':siswa_id'] = $filters['siswa_id'];
        }
        if (!empty($filters['kitab_nadhom_id'])) {
            $conditions[] = "h.kitab_nadhom_id = :kitab_nadhom_id";
            $params[':kitab_nadhom_id'] = $filters['kitab_nadhom_id'];
        }
        if (!empty($filters['guru_id'])) {
            $conditions[] = "h.guru_id = :guru_id";
            $params[':guru_id'] = $filters['guru_id'];
        }
        if (!empty($filters['tanggal_mulai'])) {
            $conditions[] = "h.tanggal_setor >= :tanggal_mulai";
            $params[':tanggal_mulai'] = $filters['tanggal_mulai'];
        }
        if (!empty($filters['tanggal_akhir'])) {
            $conditions[] = "h.tanggal_setor <= :tanggal_akhir";
            $params[':tanggal_akhir'] = $filters['tanggal_akhir'];
        }

        if (count($conditions) > 0) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }

        $query .= " ORDER BY h.tanggal_setor DESC";
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt;
    }

    // Metode untuk menambah hafalan siswa baru
    public function create() {
        // Ambil data kitab untuk menentukan target
        $kitabNadhomModel = new KitabNadhom($this->conn);
        $kitabInfo = $kitabNadhomModel->getById($this->kitab_nadhom_id);

        // Ambil tahun ajaran aktif secara otomatis
        $tahunAjaranModel = new TahunAjaran($this->conn); // Gunakan $this->conn bukan $this->pdo
        $activeTahunAjaran = $tahunAjaranModel->getActiveTahunAjaran();
        $this->tahun_ajaran_id = $activeTahunAjaran['id'] ?? null; // Set tahun_ajaran_id

        // Hitung total hafalan siswa
        $totalHafalanSebelumnya = $this->getTotalHafalan($this->siswa_id, $this->kitab_nadhom_id, $this->tahun_ajaran_id);
        $totalHafalanBaru = $totalHafalanSebelumnya + (int)$this->bagian_hafalan;
        $this->status_hafalan = $this->determineStatusHafalan($totalHafalanBaru, $kitabInfo['target_hafalan']);

        $query = "INSERT INTO " . $this->table_name . " (siswa_id, tahun_ajaran_id, kitab_nadhom_id, tanggal_setor, bagian_hafalan, nilai, guru_id, catatan, jenis_setor, status_hafalan) VALUES (:siswa_id, :tahun_ajaran_id, :kitab_nadhom_id, :tanggal_setor, :bagian_hafalan, :nilai, :guru_id, :catatan, :jenis_setor, :status_hafalan)";
        $stmt = $this->conn->prepare($query);

        // Membersihkan data
        $this->siswa_id = htmlspecialchars(strip_tags($this->siswa_id));
        $this->tahun_ajaran_id = htmlspecialchars(strip_tags($this->tahun_ajaran_id)); // Bersihkan tahun_ajaran_id
        $this->kitab_nadhom_id = htmlspecialchars(strip_tags($this->kitab_nadhom_id));
        $this->tanggal_setor = htmlspecialchars(strip_tags($this->tanggal_setor));
        $this->bagian_hafalan = htmlspecialchars(strip_tags($this->bagian_hafalan));
        $this->nilai = htmlspecialchars(strip_tags($this->nilai));
        $this->guru_id = htmlspecialchars(strip_tags($this->guru_id));
        $this->catatan = htmlspecialchars(strip_tags($this->catatan));
        $this->jenis_setor = htmlspecialchars(strip_tags($this->jenis_setor)); // Tambahan

        // Mengikat parameter
        $stmt->bindParam(':siswa_id', $this->siswa_id);
        $stmt->bindParam(':tahun_ajaran_id', $this->tahun_ajaran_id); // Bind parameter tahun_ajaran_id
        $stmt->bindParam(':kitab_nadhom_id', $this->kitab_nadhom_id);
        $stmt->bindParam(':tanggal_setor', $this->tanggal_setor);
        $stmt->bindParam(':bagian_hafalan', $this->bagian_hafalan);
        $stmt->bindParam(':nilai', $this->nilai);
        $stmt->bindParam(':guru_id', $this->guru_id);
        $stmt->bindParam(':catatan', $this->catatan);
        $stmt->bindParam(':jenis_setor', $this->jenis_setor); // Bind parameter baru
        $stmt->bindParam(':status_hafalan', $this->status_hafalan); // Bind parameter baru

        if ($stmt->execute()) {
            // --- Tambahkan Notifikasi WhatsApp ke Antrean ---
            try {
                $notificationQueue = new NotificationQueue($this->conn);

                $siswaModel = new Siswa($this->conn);
                $siswaInfo = $siswaModel->findById($this->siswa_id);

                $kitabInfo = $kitabNadhomModel->getById($this->kitab_nadhom_id);

                $guruModel = new Guru($this->conn);
                $guruInfo = $guruModel->findById($this->guru_id);

                if ($siswaInfo && !empty($siswaInfo['no_telp_ortu'])) {
                    $recipientPhoneNumber = ltrim($siswaInfo['no_telp_ortu'], '+');
                    $templateName = 'notifikasi_hafalan_siswa';
                    $templateParameters = [
                        $siswaInfo['nama_lengkap'],
                        $kitabInfo['nama_kitab'] ?? 'Tidak Diketahui',
                        $this->bagian_hafalan,
                        $this->nilai,
                        $guruInfo['nama_lengkap'] ?? 'Tidak Diketahui'
                    ];
                    $queue_result = $notificationQueue->addNotificationToQueue($recipientPhoneNumber, $templateName, $templateParameters);
                    if ($queue_result['status'] === 'success') {
                        error_log("WhatsApp notification for hafalan siswa " . $siswaInfo['nama_lengkap'] . " added to queue.");
                    } else {
                        error_log("Failed to add WhatsApp notification for hafalan siswa " . $siswaInfo['nama_lengkap'] . " to queue: " . $queue_result['message']);
                    }
                } else {
                    error_log("Nomor telepon orang tua siswa tidak ditemukan atau data siswa tidak lengkap untuk notifikasi hafalan.");
                }
            } catch (Exception $e) {
                error_log("Error adding WhatsApp notification for hafalan to queue: " . $e->getMessage());
            }
            return true;
        }
        return false;
    }

    // Metode untuk memperbarui hafalan siswa
    public function update() {
        // Ambil data kitab untuk menentukan target
        $kitabNadhomModel = new KitabNadhom($this->conn);
        $kitabInfo = $kitabNadhomModel->getById($this->kitab_nadhom_id);

        // Ambil tahun ajaran aktif secara otomatis
        $tahunAjaranModel = new TahunAjaran($this->conn);
        $activeTahunAjaran = $tahunAjaranModel->getActiveTahunAjaran();
        $this->tahun_ajaran_id = $activeTahunAjaran['id'] ?? null; // Set tahun_ajaran_id

        // Hitung total hafalan siswa
        $totalHafalanSaatIni = $this->getTotalHafalan($this->siswa_id, $this->kitab_nadhom_id, $this->tahun_ajaran_id);
        $hafalanLama = $this->getById($this->id);
        $totalHafalanBaru = $totalHafalanSaatIni - (int)$hafalanLama['bagian_hafalan'] + (int)$this->bagian_hafalan;
        $this->status_hafalan = $this->determineStatusHafalan($totalHafalanBaru, $kitabInfo['target_hafalan']);

        $query = "UPDATE " . $this->table_name . " SET siswa_id = :siswa_id, tahun_ajaran_id = :tahun_ajaran_id, kitab_nadhom_id = :kitab_nadhom_id, tanggal_setor = :tanggal_setor, bagian_hafalan = :bagian_hafalan, nilai = :nilai, guru_id = :guru_id, catatan = :catatan, jenis_setor = :jenis_setor, status_hafalan = :status_hafalan, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        // Membersihkan data
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->siswa_id = htmlspecialchars(strip_tags($this->siswa_id));
        $this->tahun_ajaran_id = htmlspecialchars(strip_tags($this->tahun_ajaran_id)); // Bersihkan tahun_ajaran_id
        $this->kitab_nadhom_id = htmlspecialchars(strip_tags($this->kitab_nadhom_id));
        $this->tanggal_setor = htmlspecialchars(strip_tags($this->tanggal_setor));
        $this->bagian_hafalan = htmlspecialchars(strip_tags($this->bagian_hafalan));
        $this->nilai = htmlspecialchars(strip_tags($this->nilai));
        $this->guru_id = htmlspecialchars(strip_tags($this->guru_id));
        $this->catatan = htmlspecialchars(strip_tags($this->catatan));
        $this->jenis_setor = htmlspecialchars(strip_tags($this->jenis_setor)); // Tambahan

        // Mengikat parameter
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':siswa_id', $this->siswa_id);
        $stmt->bindParam(':tahun_ajaran_id', $this->tahun_ajaran_id); // Bind parameter tahun_ajaran_id
        $stmt->bindParam(':kitab_nadhom_id', $this->kitab_nadhom_id);
        $stmt->bindParam(':tanggal_setor', $this->tanggal_setor);
        $stmt->bindParam(':bagian_hafalan', $this->bagian_hafalan);
        $stmt->bindParam(':nilai', $this->nilai);
        $stmt->bindParam(':guru_id', $this->guru_id);
        $stmt->bindParam(':catatan', $this->catatan);
        $stmt->bindParam(':jenis_setor', $this->jenis_setor); // Bind parameter baru
        $stmt->bindParam(':status_hafalan', $this->status_hafalan); // Bind parameter baru

        if ($stmt->execute()) {
            // --- Tambahkan Notifikasi WhatsApp ke Antrean (mirip dengan create) ---
            try {
                $notificationQueue = new NotificationQueue($this->conn);

                $siswaModel = new Siswa($this->conn);
                $siswaInfo = $siswaModel->findById($this->siswa_id);

                $kitabInfo = $kitabNadhomModel->getById($this->kitab_nadhom_id);

                $guruModel = new Guru($this->conn);
                $guruInfo = $guruModel->findById($this->guru_id);

                if ($siswaInfo && !empty($siswaInfo['no_telp_ortu'])) {
                    $recipientPhoneNumber = ltrim($siswaInfo['no_telp_ortu'], '+');
                    $templateName = 'notifikasi_hafalan_siswa';
                    $templateParameters = [
                        $siswaInfo['nama_lengkap'],
                        $kitabInfo['nama_kitab'] ?? 'Tidak Diketahui',
                        $this->bagian_hafalan,
                        $this->nilai,
                        $guruInfo['nama_lengkap'] ?? 'Tidak Diketahui'
                    ];
                    $queue_result = $notificationQueue->addNotificationToQueue($recipientPhoneNumber, $templateName, $templateParameters);
                    if ($queue_result['status'] === 'success') {
                        error_log("WhatsApp notification updated for hafalan siswa " . $siswaInfo['nama_lengkap'] . " added to queue.");
                    } else {
                        error_log("Failed to add WhatsApp notification update for hafalan siswa " . $siswaInfo['nama_lengkap'] . " to queue: " . $queue_result['message']);
                    }
                } else {
                    error_log("Nomor telepon orang tua siswa tidak ditemukan atau data siswa tidak lengkap untuk notifikasi hafalan update.");
                }
            } catch (Exception $e) {
                error_log("Error adding WhatsApp notification for hafalan update to queue: " . $e->getMessage());
            }
            return true;
        }
        return false;
    }
    
    /**
     * Memperbarui status hafalan siswa berdasarkan total hafalan yang sudah disetorkan.
     * Hanya menghitung jenis setoran 'Setoran Baru'.
     * @param int $siswaId
     * @param int $kitabNadhomId
     */
    public function updateStatusHafalan($siswaId, $kitabNadhomId) {
        $tahun_ajaran_aktif = getActiveTahunAjaran();
        if (!$tahun_ajaran_aktif) {
            error_log("Tidak ada tahun ajaran aktif ditemukan.");
            return false;
        }

        // Dapatkan semua setoran hafalan untuk siswa dan kitab/nadhom tertentu
        $query = "SELECT jenis_setor, bagian_hafalan FROM " . $this->table_name . " WHERE siswa_id = :siswa_id AND kitab_nadhom_id = :kitab_nadhom_id AND tahun_ajaran_id = :tahun_ajaran_id ORDER BY tanggal_setor ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':siswa_id', $siswaId, PDO::PARAM_INT);
        $stmt->bindParam(':kitab_nadhom_id', $kitabNadhomId, PDO::PARAM_INT);
        $stmt->bindParam(':tahun_ajaran_id', $tahun_ajaran_aktif['id'], PDO::PARAM_INT);
        $stmt->execute();
        $all_hafalan = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalHafalan = 0;
        foreach ($all_hafalan as $hafalan) {
            // Hanya akumulasikan hafalan jika jenis setornya bukan 'Mengulang'
            if ($hafalan['jenis_setor'] !== 'Mengulang') {
                $totalHafalan += $hafalan['bagian_hafalan'];
            }
        }

        // Dapatkan target hafalan dari kitab/nadhom
        $kitab_nadhom_model = new KitabNadhom($this->conn);
        $kitabInfo = $kitab_nadhom_model->getById($kitabNadhomId);
        $target = $kitabInfo['target_hafalan'] ?? null;

        // Tentukan status hafalan
        $statusHafalan = $this->determineStatusHafalan($totalHafalan, $target);

        // Update status hafalan di tabel siswa
        $query = "UPDATE " . $this->table_siswa . " SET status_hafalan = :status_hafalan, total_hafalan = :total_hafalan WHERE id = :siswa_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status_hafalan', $statusHafalan);
        $stmt->bindParam(':total_hafalan', $totalHafalan);
        $stmt->bindParam(':siswa_id', $siswaId, PDO::PARAM_INT);
        $update_result = $stmt->execute();

        if ($update_result) {
            // Kirim notifikasi WhatsApp jika target sudah tercapai
            if ($statusHafalan === 'Mencapai Target') {
                $this->sendWhatsAppNotification($siswaId, 'Hafalan Selesai', $kitabInfo['nama_kitab']);
            }
        }

        return $update_result;
    }

    // Metode untuk menghapus hafalan siswa
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Metode baru untuk menentukan status hafalan
    private function determineStatusHafalan($totalHafalan, $target) {
        if ($target === null) {
            return 'Tidak Berlaku';
        }
        if ($totalHafalan >= $target) {
            return 'Mencapai Target';
        } elseif ($totalHafalan > 0) {
            return 'Masih Kurang';
        } else {
            return 'Belum Setor';
        }
    }
}
