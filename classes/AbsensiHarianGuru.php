<?php
// project-root/classes/AbsensiHarianGuru.php

class AbsensiHarianGuru {
    private $pdo;
    private $table_name = "absensi_harian_guru"; // Asumsi ada tabel ini

    public function __construct(PDO $db) {
        $this->pdo = $db;
    }

    /**
     * Mencatat absensi masuk guru.
     * Secara default, status akan diatur sebagai 'Hadir' saat check-in.
     * @param int $guru_id ID guru.
     * @param string $tanggal Tanggal absensi (YYYY-MM-DD).
     * @param string $waktu Waktu absensi (HH:MM:SS).
     * @param string $status_kehadiran Status awal kehadiran (default 'Hadir').
     * @return bool True jika berhasil, false jika gagal.
     */
    public function recordCheckIn($guru_id, $tanggal, $waktu, $status_masuk = 'Hadir') {
        // Periksa apakah sudah ada absensi masuk untuk guru pada tanggal tersebut
        $query_check = "SELECT id FROM " . $this->table_name . " WHERE guru_id = :guru_id AND tanggal = :tanggal AND waktu_masuk IS NOT NULL";
        $stmt_check = $this->pdo->prepare($query_check);
        $stmt_check->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
        $stmt_check->bindParam(':tanggal', $tanggal, PDO::PARAM_STR);
        $stmt_check->execute();

        if ($stmt_check->fetch(PDO::FETCH_ASSOC)) {
            // Sudah ada absensi masuk, jangan catat lagi
            return false;
        }

        $query = "INSERT INTO " . $this->table_name . " (guru_id, tanggal, waktu_masuk, status_masuk, created_at) VALUES (:guru_id, :tanggal, :waktu_masuk, :status_masuk, NOW())";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
        $stmt->bindParam(':tanggal', $tanggal, PDO::PARAM_STR);
        $stmt->bindParam(':waktu_masuk', $waktu, PDO::PARAM_STR);
        $stmt->bindParam(':status_masuk', $status_masuk, PDO::PARAM_STR); // Bind status_kehadiran
        return $stmt->execute();
    }

    /**
     * Mencatat absensi pulang guru.
     * @param int $guru_id ID guru.
     * @param string $tanggal Tanggal absensi (YYYY-MM-DD).
     * @param string $waktu Waktu absensi (HH:MM:SS).
     * @return bool True jika berhasil, false jika gagal.
     */
    public function recordCheckOut($guru_id, $tanggal, $waktu) {
        // Perbarui absensi yang sudah ada untuk guru pada tanggal tersebut
        $query = "UPDATE " . $this->table_name . " SET waktu_pulang = :waktu_pulang, updated_at = NOW() WHERE guru_id = :guru_id AND tanggal = :tanggal";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':waktu_pulang', $waktu, PDO::PARAM_STR);
        $stmt->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
        $stmt->bindParam(':tanggal', $tanggal, PDO::PARAM_STR);
        return $stmt->execute();
    }

    /**
     * Memperbarui status kehadiran harian guru.
     * Ini bisa digunakan oleh admin untuk mengubah status dari Hadir menjadi Sakit/Izin/Alfa, dll.
     * @param int $id ID catatan absensi harian.
     * @param string $new_status Status kehadiran baru.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function updateStatus($id, $new_status) {
        $query = "UPDATE " . $this->table_name . " SET status_masuk = :new_status, updated_at = NOW() WHERE id = :id";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':new_status', $new_status, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Mendapatkan riwayat absensi harian guru.
     * Mengembalikan juga nama guru dan status kehadiran.
     * @param int $guru_id ID guru.
     * @param string|null $startDate Tanggal mulai filter (YYYY-MM-DD).
     * @param string|null $endDate Tanggal akhir filter (YYYY-MM-DD).
     * @return array Daftar absensi harian guru.
     */
    public function getDailyAttendanceByGuru($guru_id, $startDate = null, $endDate = null) {
        $query = "SELECT ahg.id, ahg.tanggal, ahg.waktu_masuk, ahg.waktu_pulang, ahg.status_masuk,
                         g.nama_lengkap AS nama_guru
                  FROM " . $this->table_name . " ahg
                  JOIN guru g ON ahg.guru_id = g.id
                  WHERE ahg.guru_id = :guru_id";

        $params = [':guru_id' => $guru_id];

        if ($startDate) {
            $query .= " AND ahg.tanggal >= :start_date";
            $params[':start_date'] = $startDate;
        }
        if ($endDate) {
            $query .= " AND ahg.tanggal <= :end_date";
            $params[':end_date'] = $endDate;
        }

        $query .= " ORDER BY ahg.tanggal DESC, ahg.waktu_masuk DESC";

        $stmt = $this->pdo->prepare($query);
        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Mendapatkan daftar tanggal absensi harian yang valid (Hadir) untuk perhitungan gaji.
     * @param int $guru_id ID guru.
     * @param string $startDate Tanggal mulai filter (YYYY-MM-DD).
     * @param string $endDate Tanggal akhir filter (YYYY-MM-DD).
     * @return array Daftar tanggal (YYYY-MM-DD) absensi yang valid (status_masuk = 'Hadir').
     */
    public function getValidAttendanceDates($guru_id, $startDate, $endDate) {
        $query = "SELECT tanggal 
                  FROM " . $this->table_name . "
                  WHERE guru_id = :guru_id
                  AND tanggal BETWEEN :start_date AND :end_date
                  AND status_masuk = 'Hadir'
                  ORDER BY tanggal ASC"; 

        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0); // Hanya ambil kolom 'tanggal'
    }
    
    /**
     * Mendapatkan daftar tanggal absensi harian yang valid (Hadir) untuk perhitungan gaji, 
     * mengabaikan Hari Libur Sekolah (Jumat).
     * @param int $guru_id ID guru.
     * @param string $startDate Tanggal mulai filter (YYYY-MM-DD).
     * @param string $endDate Tanggal akhir filter (YYYY-MM-DD).
     * @param array $offDays Array hari yang merupakan hari libur sekolah (misal: ['Friday']).
     * @return array Daftar tanggal (YYYY-MM-DD) absensi yang valid (status_masuk = 'Hadir') dan bukan hari libur.
     */
    public function getValidAttendanceDatesExcludingOffDays($guru_id, $startDate, $endDate, $offDays = ['Friday']) {
        // MySQL WEEKDAY(): 0=Senin, 1=Selasa, ..., 5=Sabtu, 6=Minggu (Ini tidak standar)
        // Sebaiknya kita gunakan filter di PHP setelah mendapatkan semua data, 
        // atau gunakan fungsi hari yang lebih universal di MySQL/PDO.
        
        // Mari kita gunakan cara yang lebih kompatibel dengan SQL dan sederhana:
        // Filter semua kehadiran yang 'Hadir' terlebih dahulu, lalu filter Jumat di PHP.
        
        $query = "SELECT tanggal 
                  FROM " . $this->table_name . "
                  WHERE guru_id = :guru_id
                  AND tanggal BETWEEN :start_date AND :end_date
                  AND status_masuk = 'Hadir'
                  ORDER BY tanggal ASC"; 
    
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
        $stmt->execute();
        
        $dates = $stmt->fetchAll(PDO::FETCH_COLUMN); // Ambil hanya kolom 'tanggal'
        
        // Filter hari Jumat di PHP
        $filteredDates = [];
        foreach ($dates as $date) {
            // format('l') mengembalikan nama hari dalam bahasa Inggris (e.g., 'Friday')
            $dayName = (new DateTime($date))->format('l'); 
            if (!in_array($dayName, $offDays)) {
                $filteredDates[] = $date;
            }
        }
        
        return $filteredDates;
    }
}
