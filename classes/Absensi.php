<?php
// project-root/classes/Absensi.php

// Pastikan Anda memuat kelas-kelas yang diperlukan
require_once __DIR__ . '/SchoolSettings.php';
require_once __DIR__ . '/TahunAjaran.php'; // Pastikan file ini ada

class Absensi {
    private $pdo;
    private $table_name = "absensi_harian_guru";

    public function __construct(PDO $db) {
        $this->pdo = $db;
    }

    /**
     * Mencatat absensi masuk untuk guru.
     *
     * @param int $guru_id ID guru yang melakukan absensi.
     * @return array Hasil operasi dengan 'success', 'message', dan 'status' (jika berhasil).
     */
    public function absenMasukGuru($guru_id) {
        // Cek apakah guru sudah absen hari ini
        $query_check = "SELECT id FROM " . $this->table_name . " WHERE guru_id = :guru_id AND tanggal = CURDATE() LIMIT 1";
        $stmt_check = $this->pdo->prepare($query_check);
        $stmt_check->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
        $stmt_check->execute();

        if ($stmt_check->fetch(PDO::FETCH_ASSOC)) {
            return ['success' => false, 'message' => 'Anda sudah absen masuk hari ini.'];
        }

        // Ambil tahun ajaran dari tabel 'tahun_ajaran' menggunakan class yang sudah ada
        $tahun_ajaran_model = new TahunAjaran($this->pdo);
        $tahun_ajaran_aktif = $tahun_ajaran_model->getActiveTahunAjaran();
        if (!$tahun_ajaran_aktif) {
            return ['success' => false, 'message' => 'Tahun ajaran aktif belum ditentukan.'];
        }
        $tahun_ajaran = $tahun_ajaran_aktif['tahun_ajaran'];

        // Ambil semester dari tabel 'school_settings'
        $settings_model = new SchoolSettings($this->pdo);
        $settings = $settings_model->getSettings();
        if (!$settings) {
            return ['success' => false, 'message' => 'Pengaturan sekolah belum ada.'];
        }
        $semester = $settings['semester_aktif'];

        // Ambil jam masuk sekolah dan toleransi keterlambatan dari pengaturan
        $settings_model = new SchoolSettings($this->pdo);
        $settings = $settings_model->getSettings();
        $jam_masuk_default = $settings['jam_masuk_sekolah_default'] ?? '07:00:00';
        $toleransi_menit = $settings['toleransi_terlambat_menit'] ?? 15;

        // Hitung batas waktu toleransi
        $jam_masuk_timestamp = strtotime($jam_masuk_default);
        $batas_toleransi_timestamp = $jam_masuk_timestamp + ($toleransi_menit * 60);
        $waktu_sekarang = time();

        $status_masuk = ($waktu_sekarang > $batas_toleransi_timestamp) ? 'Terlambat' : 'Hadir';

        $query = "INSERT INTO " . $this->table_name . "
                  (guru_id, tanggal, tahun_ajaran, semester, waktu_masuk, status_masuk, created_at)
                  VALUES (:guru_id, CURDATE(), :tahun_ajaran, :semester, NOW(), :status_masuk, NOW())";

        $stmt = $this->pdo->prepare($query);

        $stmt->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
        $stmt->bindParam(':tahun_ajaran', $tahun_ajaran);
        $stmt->bindParam(':semester', $semester);
        $stmt->bindParam(':status_masuk', $status_masuk);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Absensi masuk berhasil dicatat.', 'status' => $status_masuk];
        } else {
            return ['success' => false, 'message' => 'Gagal mencatat absensi. Silakan coba lagi.'];
        }
    }
    
    /**
 * Mencatat absensi pulang untuk guru.
 *
 * @param int $guru_id ID guru yang melakukan absensi.
 * @return array Hasil operasi dengan 'success', 'message', dan 'waktu_pulang' (jika berhasil).
 */
public function absenKeluarGuru($guru_id) {
    // Cek apakah guru sudah absen masuk hari ini
    $query_check_masuk = "SELECT id, waktu_masuk FROM " . $this->table_name . " WHERE guru_id = :guru_id AND tanggal = CURDATE() LIMIT 1";
    $stmt_check_masuk = $this->pdo->prepare($query_check_masuk);
    $stmt_check_masuk->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
    $stmt_check_masuk->execute();
    $absensi_masuk = $stmt_check_masuk->fetch(PDO::FETCH_ASSOC);

    if (!$absensi_masuk) {
        return ['success' => false, 'message' => 'Anda belum absen masuk hari ini.'];
    }

    // Cek apakah guru sudah absen pulang hari ini
    $query_check_keluar = "SELECT id FROM " . $this->table_name . " WHERE guru_id = :guru_id AND tanggal = CURDATE() AND waktu_pulang IS NOT NULL LIMIT 1";
    $stmt_check_keluar = $this->pdo->prepare($query_check_keluar);
    $stmt_check_keluar->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
    $stmt_check_keluar->execute();

    if ($stmt_check_keluar->fetch(PDO::FETCH_ASSOC)) {
        return ['success' => false, 'message' => 'Anda sudah absen pulang hari ini.'];
    }

    // Ambil jam pulang sekolah dari pengaturan
    $settings_model = new SchoolSettings($this->pdo);
    $settings = $settings_model->getSettings();
    $jam_pulang_default = $settings['jam_pulang_sekolah_default'] ?? '12:40:00';

    // Tentukan status pulang berdasarkan waktu_keluar saat ini
    $waktu_pulang_sekarang = date('H:i:s');
    $status_pulang = ($waktu_pulang_sekarang < $jam_pulang_default) ? 'Pulang' : 'Belum Pulang';

    // Perbaikan query: menggunakan NOW() untuk waktu_pulang dan variabel status_pulang untuk kolom status_pulang
    $query = "UPDATE " . $this->table_name . " SET
              waktu_pulang = NOW(),
              status_pulang = :status_pulang,
              updated_at = NOW()
              WHERE id = :absensi_id";

    $stmt = $this->pdo->prepare($query);

    // Bind parameter yang benar
    $stmt->bindParam(':status_pulang', $status_pulang);
    $stmt->bindParam(':absensi_id', $absensi_masuk['id'], PDO::PARAM_INT);

    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Absensi pulang berhasil dicatat.', 'status_pulang' => $status_pulang];
    } else {
        return ['success' => false, 'message' => 'Gagal mencatat absensi pulang. Silakan coba lagi.'];
    }
}
    
    /**
     * Memeriksa apakah guru sudah absen masuk hari ini.
     *
     * @param int $guru_id ID guru.
     * @return bool True jika sudah absen, false jika belum.
     */
    public function hasAbsenMasukHariIni($guru_id) {
        $query = "SELECT COUNT(*) FROM " . $this->table_name . " WHERE guru_id = :guru_id AND tanggal = CURDATE()";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Memeriksa apakah guru sudah absen pulang hari ini.
     *
     * @param int $guru_id ID guru.
     * @return bool True jika sudah absen, false jika belum.
     */
    public function hasAbsenKeluarHariIni($guru_id) {
        $query = "SELECT COUNT(*) FROM " . $this->table_name . " WHERE guru_id = :guru_id AND tanggal = CURDATE() AND waktu_pulang IS NOT NULL";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Mengambil riwayat absensi guru.
     *
     * @param int $guru_id ID guru.
     * @return array Riwayat absensi.
     */
    public function getAbsensiByGuruId($guru_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE guru_id = :guru_id ORDER BY tanggal DESC, waktu_masuk DESC";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}