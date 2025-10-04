<?php
// classes/IzinKeluarPulang.php

require_once __DIR__ . '/../classes/NotificationQueue.php';
require_once __DIR__ . '/../classes/SchoolSettings.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/Guru.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/TahunAjaran.php';
require_once __DIR__ . '/../classes/Kelas.php';

class IzinKeluarPulang {
    private $conn;
    private $table_name = "izin_keluar_pulang";
    private $table_siswa = "siswa";
    private $table_guru = "guru";
    private $table_users = "users";
    private $table_kelas = "kelas";

    public $id;
    public $tahun_ajaran_id;
    public $siswa_id;
    public $jenis_izin;
    public $tanggal_izin;
    public $sampai_tanggal;
    public $waktu_keluar;
    public $waktu_kembali;
    public $keperluan;
    public $diberi_izin_oleh_id;
    public $catatan_admin;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Read all data with filters and joins
    public function getAll($filters = []) {
        $query = "SELECT ikp.id, ikp.tahun_ajaran_id, ikp.siswa_id, ikp.jenis_izin, ikp.tanggal_izin, ikp.sampai_tanggal,
                              ikp.waktu_keluar, ikp.waktu_kembali, ikp.keperluan, ikp.catatan_admin,
                              s.nama_lengkap AS nama_siswa, s.nisn, k.nama_kelas,
                              COALESCE(u.username, g_pemberi.nama_lengkap) AS nama_pemberi_izin,
                              ta.tahun_ajaran AS nama_tahun_ajaran
                      FROM " . $this->table_name . " ikp
                      LEFT JOIN " . $this->table_siswa . " s ON ikp.siswa_id = s.id
                      LEFT JOIN " . $this->table_kelas . " k ON s.kelas_id = k.id
                      LEFT JOIN " . $this->table_users . " u ON ikp.diberi_izin_oleh_id = u.id
                      LEFT JOIN " . $this->table_guru . " g_pemberi ON ikp.diberi_izin_oleh_id = g_pemberi.id
                      LEFT JOIN tahun_ajaran ta ON ikp.tahun_ajaran_id = ta.id
                      WHERE 1=1";

        $params = [];

        if (isset($filters['siswa_id']) && !empty($filters['siswa_id'])) {
            $query .= " AND ikp.siswa_id = :siswa_id";
            $params[':siswa_id'] = $filters['siswa_id'];
        }
        if (isset($filters['jenis_izin']) && !empty($filters['jenis_izin'])) {
            $query .= " AND ikp.jenis_izin = :jenis_izin";
            $params[':jenis_izin'] = $filters['jenis_izin'];
        }
        if (isset($filters['start_date']) && !empty($filters['start_date'])) {
            $query .= " AND ikp.tanggal_izin >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        if (isset($filters['end_date']) && !empty($filters['end_date'])) {
            $query .= " AND ikp.tanggal_izin <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }
        if (isset($filters['tahun_ajaran_id']) && $filters['tahun_ajaran_id']) {
            $query .= " AND ikp.tahun_ajaran_id = :tahun_ajaran_id";
            $params[':tahun_ajaran_id'] = $filters['tahun_ajaran_id'];
        }
        if (isset($filters['kelas_id']) && $filters['kelas_id']) {
            $query .= " AND s.kelas_id = :kelas_id";
            $params[':kelas_id'] = $filters['kelas_id'];
        }

        $query .= " ORDER BY ikp.tanggal_izin DESC, ikp.waktu_keluar DESC";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Read all data for parents' app
    public function getAllParent($siswa_id, $filters = []) {
        $query = "SELECT ikp.*, s.nama_lengkap AS nama_siswa, s.nisn, k.nama_kelas,
                              COALESCE(u.username, g_pemberi.nama_lengkap) AS nama_pemberi_izin,
                              ta.tahun_ajaran AS nama_tahun_ajaran
                      FROM " . $this->table_name . " ikp
                      LEFT JOIN " . $this->table_siswa . " s ON ikp.siswa_id = s.id
                      LEFT JOIN " . $this->table_kelas . " k ON s.kelas_id = k.id
                      LEFT JOIN " . $this->table_users . " u ON ikp.diberi_izin_oleh_id = u.id
                      LEFT JOIN " . $this->table_guru . " g_pemberi ON ikp.diberi_izin_oleh_id = g_pemberi.id
                      LEFT JOIN tahun_ajaran ta ON ikp.tahun_ajaran_id = ta.id
                      WHERE ikp.siswa_id = :siswa_id";

        $params = [':siswa_id' => $siswa_id];

        if (isset($filters['jenis_izin']) && $filters['jenis_izin']) {
            $query .= " AND ikp.jenis_izin = :jenis_izin";
            $params[':jenis_izin'] = $filters['jenis_izin'];
        }
        if (isset($filters['tahun_ajaran_id']) && $filters['tahun_ajaran_id']) {
            $query .= " AND ikp.tahun_ajaran_id = :tahun_ajaran_id";
            $params[':tahun_ajaran_id'] = $filters['tahun_ajaran_id'];
        }
        if (isset($filters['kelas_id']) && $filters['kelas_id']) {
            $query .= " AND s.kelas_id = :kelas_id";
            $params[':kelas_id'] = $filters['kelas_id'];
        }

        $query .= " ORDER BY ikp.tanggal_izin DESC, ikp.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Read single data by ID
    public function getById($id) {
        $query = "SELECT ikp.*, s.nama_lengkap AS nama_siswa, s.nisn, s.no_telp_ortu, k.nama_kelas,
                              COALESCE(u.username, g_pemberi.nama_lengkap) AS nama_pemberi_izin,
                              ta.tahun_ajaran AS nama_tahun_ajaran
                      FROM " . $this->table_name . " ikp
                      LEFT JOIN " . $this->table_siswa . " s ON ikp.siswa_id = s.id
                      LEFT JOIN " . $this->table_kelas . " k ON s.kelas_id = k.id
                      LEFT JOIN " . $this->table_users . " u ON ikp.diberi_izin_oleh_id = u.id
                      LEFT JOIN " . $this->table_guru . " g_pemberi ON ikp.diberi_izin_oleh_id = g_pemberi.id
                      LEFT JOIN tahun_ajaran ta ON ikp.tahun_ajaran_id = ta.id
                      WHERE ikp.id = :id LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Add new permit data
    public function create() {
        $tahunAjaranModel = new TahunAjaran($this->conn);
        $activeTahunAjaran = $tahunAjaranModel->getActiveTahunAjaran();
        $this->tahun_ajaran_id = $activeTahunAjaran['id'] ?? null;

        $query = "INSERT INTO " . $this->table_name . " (tahun_ajaran_id, siswa_id, jenis_izin, tanggal_izin, sampai_tanggal, waktu_keluar, waktu_kembali, keperluan, diberi_izin_oleh_id, catatan_admin) VALUES (:tahun_ajaran_id, :siswa_id, :jenis_izin, :tanggal_izin, :sampai_tanggal, :waktu_keluar, :waktu_kembali, :keperluan, :diberi_izin_oleh_id, :catatan_admin)";
        $stmt = $this->conn->prepare($query);

        $this->tahun_ajaran_id = htmlspecialchars(strip_tags($this->tahun_ajaran_id));
        $this->siswa_id = htmlspecialchars(strip_tags($this->siswa_id));
        $this->jenis_izin = htmlspecialchars(strip_tags($this->jenis_izin));
        $this->tanggal_izin = htmlspecialchars(strip_tags($this->tanggal_izin));
        $this->sampai_tanggal = htmlspecialchars(strip_tags($this->sampai_tanggal));
        $this->waktu_keluar = htmlspecialchars(strip_tags($this->waktu_keluar));
        $this->waktu_kembali = htmlspecialchars(strip_tags($this->waktu_kembali));
        $this->keperluan = htmlspecialchars(strip_tags($this->keperluan));
        $this->diberi_izin_oleh_id = htmlspecialchars(strip_tags($this->diberi_izin_oleh_id));
        $this->catatan_admin = htmlspecialchars(strip_tags($this->catatan_admin));

        $stmt->bindParam(':tahun_ajaran_id', $this->tahun_ajaran_id, PDO::PARAM_INT);
        $stmt->bindParam(':siswa_id', $this->siswa_id, PDO::PARAM_INT);
        $stmt->bindParam(':jenis_izin', $this->jenis_izin);
        $stmt->bindParam(':tanggal_izin', $this->tanggal_izin);
        $stmt->bindParam(':sampai_tanggal', $this->sampai_tanggal);
        $stmt->bindParam(':waktu_keluar', $this->waktu_keluar);
        $stmt->bindParam(':waktu_kembali', $this->waktu_kembali);
        $stmt->bindParam(':keperluan', $this->keperluan);
        $stmt->bindParam(':diberi_izin_oleh_id', $this->diberi_izin_oleh_id, PDO::PARAM_INT);
        $stmt->bindParam(':catatan_admin', $this->catatan_admin);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            $this->sendWhatsAppNotification();
            return true;
        }
        return false;
    }

    // Update permit data
    public function update() {
        $tahunAjaranModel = new TahunAjaran($this->conn);
        $activeTahunAjaran = $tahunAjaranModel->getActiveTahunAjaran();
        $this->tahun_ajaran_id = $activeTahunAjaran['id'] ?? null;

        $query = "UPDATE " . $this->table_name . " SET
                      tahun_ajaran_id = :tahun_ajaran_id,
                      siswa_id = :siswa_id,
                      jenis_izin = :jenis_izin,
                      tanggal_izin = :tanggal_izin,
                      sampai_tanggal = :sampai_tanggal,
                      waktu_keluar = :waktu_keluar,
                      waktu_kembali = :waktu_kembali,
                      keperluan = :keperluan,
                      diberi_izin_oleh_id = :diberi_izin_oleh_id,
                      catatan_admin = :catatan_admin,
                      updated_at = CURRENT_TIMESTAMP
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->tahun_ajaran_id = htmlspecialchars(strip_tags($this->tahun_ajaran_id));
        $this->siswa_id = htmlspecialchars(strip_tags($this->siswa_id));
        $this->jenis_izin = htmlspecialchars(strip_tags($this->jenis_izin));
        $this->tanggal_izin = htmlspecialchars(strip_tags($this->tanggal_izin));
        $this->sampai_tanggal = htmlspecialchars(strip_tags($this->sampai_tanggal));
        $this->waktu_keluar = htmlspecialchars(strip_tags($this->waktu_keluar));
        $this->waktu_kembali = htmlspecialchars(strip_tags($this->waktu_kembali));
        $this->keperluan = htmlspecialchars(strip_tags($this->keperluan));
        $this->diberi_izin_oleh_id = htmlspecialchars(strip_tags($this->diberi_izin_oleh_id));
        $this->catatan_admin = htmlspecialchars(strip_tags($this->catatan_admin));

        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        $stmt->bindParam(':tahun_ajaran_id', $this->tahun_ajaran_id, PDO::PARAM_INT);
        $stmt->bindParam(':siswa_id', $this->siswa_id, PDO::PARAM_INT);
        $stmt->bindParam(':jenis_izin', $this->jenis_izin);
        $stmt->bindParam(':tanggal_izin', $this->tanggal_izin);
        $stmt->bindParam(':sampai_tanggal', $this->sampai_tanggal);
        $stmt->bindParam(':waktu_keluar', $this->waktu_keluar);
        $stmt->bindParam(':waktu_kembali', $this->waktu_kembali);
        $stmt->bindParam(':keperluan', $this->keperluan);
        $stmt->bindParam(':diberi_izin_oleh_id', $this->diberi_izin_oleh_id, PDO::PARAM_INT);
        $stmt->bindParam(':catatan_admin', $this->catatan_admin);

        if ($stmt->execute()) {
            $this->sendWhatsAppNotification();
            return true;
        }
        return false;
    }

    // Delete permit data
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Send WhatsApp notification
    private function sendWhatsAppNotification() {
        try {
            $izinInfo = $this->getById($this->id);

            if (!$izinInfo) {
                error_log("Data izin tidak ditemukan untuk notifikasi.");
                return;
            }

            $nama_siswa = $izinInfo['nama_siswa'] ?? 'Tidak Diketahui';
            $recipientPhoneNumber = '';

            $siswaModel = new Siswa($this->conn);
            $siswaInfo = $siswaModel->findById($izinInfo['siswa_id']);
            $recipientPhoneNumber = $siswaInfo['no_telp_ortu'] ?? '';


            if (empty($recipientPhoneNumber)) {
                error_log("Nomor telepon notifikasi orang tua siswa tidak ditemukan untuk " . $nama_siswa . ".");
                return;
            }

            $recipientPhoneNumber = ltrim($recipientPhoneNumber, '+');

            $notificationQueue = new NotificationQueue($this->conn);
            $templateName = 'notifikasi_izin_keluar_pulang'; // Sesuai dengan nama template di whatsapp_notifier.php
            $nama_pemberi_izin = $izinInfo['nama_pemberi_izin'] ?? 'Admin';
            $status_izin = 'Diterima'; // Asumsi status adalah Diterima saat ditambahkan atau diperbarui

            // Menyesuaikan parameter berdasarkan jenis izin
            $templateParameters = [
                $nama_siswa,
                'Siswa',
                format_tanggal_indo($izinInfo['tanggal_izin']),
                $izinInfo['jenis_izin'],
                date('H:i', strtotime($izinInfo['waktu_keluar'] ?? '00:00')),
                date('H:i', strtotime($izinInfo['waktu_kembali'] ?? '00:00')),
                format_tanggal_indo($izinInfo['sampai_tanggal']),
                $izinInfo['keperluan'],
                $nama_pemberi_izin,
                $status_izin
            ];

            $queue_result = $notificationQueue->addNotificationToQueue($recipientPhoneNumber, $templateName, $templateParameters);

            if ($queue_result['status'] === 'success') {
                error_log("WhatsApp notification updated for izin siswa " . $nama_siswa . " added to queue.");
            } else {
                error_log("Failed to add WhatsApp notification update for izin siswa " . $nama_siswa . " to queue: " . $queue_result['message']);
            }
        } catch (Exception $e) {
            error_log("Error adding WhatsApp notification for izin update to queue: " . $e->getMessage());
        }
    }

    /**
     * Get exit/go home permit data specifically for reports.
     *
     * @param int $siswa_id Student ID.
     * @param int|null $kelas_id Class ID (optional).
     * @param string|null $start_date Report start date.
     * @param string|null $end_date Report end date.
     * @param int|null $tahun_ajaran_id Academic Year ID.
     * @return array Permit report data.
     */
    public function getForReport($siswa_id = null, $kelas_id = null, $start_date = null, $end_date = null, $tahun_ajaran_id = null) {
        $query = "SELECT ikp.jenis_izin, ikp.tanggal_izin, ikp.sampai_tanggal, ikp.waktu_keluar, ikp.waktu_kembali, ikp.keperluan,
                              ta.tahun_ajaran AS nama_tahun_ajaran,
                              s.nama_lengkap AS nama_siswa, k.nama_kelas
                      FROM " . $this->table_name . " ikp
                      LEFT JOIN " . $this->table_siswa . " s ON ikp.siswa_id = s.id
                      LEFT JOIN " . $this->table_kelas . " k ON s.kelas_id = k.id
                      LEFT JOIN tahun_ajaran ta ON ikp.tahun_ajaran_id = ta.id
                      WHERE 1=1";

        $params = [];
        $where_clauses = [];

        if ($siswa_id) {
            $where_clauses[] = "ikp.siswa_id = :siswa_id";
            $params[':siswa_id'] = $siswa_id;
        }
        if ($kelas_id) {
            $where_clauses[] = "s.kelas_id = :kelas_id";
            $params[':kelas_id'] = $kelas_id;
        }
        if ($tahun_ajaran_id) {
            $where_clauses[] = "ikp.tahun_ajaran_id = :tahun_ajaran_id";
            $params[':tahun_ajaran_id'] = $tahun_ajaran_id;
        }
        if ($start_date) {
            $where_clauses[] = "ikp.tanggal_izin >= :start_date";
            $params[':start_date'] = $start_date;
        }
        if ($end_date) {
            $where_clauses[] = "ikp.tanggal_izin <= :end_date";
            $params[':end_date'] = $end_date;
        }

        if (!empty($where_clauses)) {
            $query .= " AND " . implode(" AND ", $where_clauses);
        }

        $query .= " ORDER BY ikp.tanggal_izin ASC, ikp.waktu_keluar ASC";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}