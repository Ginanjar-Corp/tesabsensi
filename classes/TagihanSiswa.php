<?php
// project-root/classes/TagihanSiswa.php

class TagihanSiswa {
    private $pdo;
    private $table_name = "tagihan_siswa"; // Tabel utama tagihan
    private $table_siswa = "siswa";
    private $table_jenis_pembayaran = "jenis_pembayaran";
    private $table_tahun_ajaran = "tahun_ajaran";
    private $table_kelas = "kelas";
    private $table_detail_pembayaran = "detail_pembayaran_transaksi"; // Tabel detail transaksi pembayaran
    private $pembayaran_table = "detail_pembayaran_transaksi"; // Menggunakan tabel yang sama untuk riwayat pembayaran
    private $table_kas_unit = "kas_unit"; // Tambahkan tabel kas_unit

    public $id;
    public $siswa_id;
    public $jenis_pembayaran_id;
    public $tahun_ajaran_id;
    public $kelas_id_saat_tagih; // Kelas siswa saat tagihan dibuat
    public $jumlah_tagihan;
    public $sisa_tagihan;
    public $periode_tagihan; // Contoh: "Juli 2024" atau "Ujian Semester Ganjil"
    public $tanggal_jatuh_tempo;
    public $status_tagihan; // 'Belum Lunas', 'Sebagian Lunas', 'Lunas', 'Dibatalkan'
    public $created_at;
    public $updated_at;

    public function __construct(PDO $db) {
        $this->pdo = $db;
    }

    /**
     * Mengambil semua tagihan siswa berdasarkan filter.
     * Termasuk informasi jenis pembayaran, tahun ajaran, kelas, dan unit kas.
     * Juga menghitung jumlah yang sudah dibayar dan sisa tagihan.
     *
     * @param array $filters Array filter (siswa_id, tahun_ajaran_id, status_tagihan, status_tagihan_exclude_lunas)
     * @return array Array asosiatif dari tagihan siswa.
     */
    public function getAll(array $filters = []) {
        $query = "
            SELECT
                ts.id AS tagihan_id,
                ts.siswa_id,
                s.nama_lengkap AS nama_siswa,
                s.nisn,
                s.no_telp_ortu,
                ts.jenis_pembayaran_id,
                jp.nama_pembayaran,
                jp.unit_id, -- Ambil unit_id dari jenis_pembayaran
                ku.nama_unit, -- Ambil nama_unit dari kas_unit
                ts.tahun_ajaran_id,
                ta.tahun_ajaran,
                ts.kelas_id_saat_tagih,
                k.nama_kelas,
                ts.jumlah_tagihan,
                ts.periode_tagihan,
                ts.tanggal_jatuh_tempo,
                ts.status_tagihan,
                ts.created_at,
                ts.updated_at,
                COALESCE(SUM(dpt.jumlah_bayar), 0) AS jumlah_dibayar,
                (ts.jumlah_tagihan - COALESCE(SUM(dpt.jumlah_bayar), 0)) AS sisa_tagihan
            FROM
                " . $this->table_name . " ts
            JOIN
                " . $this->table_siswa . " s ON ts.siswa_id = s.id
            JOIN
                " . $this->table_jenis_pembayaran . " jp ON ts.jenis_pembayaran_id = jp.id
            LEFT JOIN
                " . $this->table_kas_unit . " ku ON jp.unit_id = ku.id -- JOIN ke kas_unit
            JOIN
                " . $this->table_tahun_ajaran . " ta ON ts.tahun_ajaran_id = ta.id
            LEFT JOIN
                " . $this->table_kelas . " k ON ts.kelas_id_saat_tagih = k.id
            LEFT JOIN
                " . $this->table_detail_pembayaran . " dpt ON dpt.tagihan_siswa_id = ts.id AND dpt.status_pembayaran_detail = 'Lunas'
            WHERE 1=1
        ";
        $params = [];

        if (isset($filters['siswa_id']) && $filters['siswa_id']) {
            $query .= " AND ts.siswa_id = :siswa_id";
            $params[':siswa_id'] = $filters['siswa_id'];
        }
        if (isset($filters['tahun_ajaran_id']) && $filters['tahun_ajaran_id']) {
            $query .= " AND ts.tahun_ajaran_id = :tahun_ajaran_id";
            $params[':tahun_ajaran_id'] = $filters['tahun_ajaran_id'];
        }
        if (isset($filters['status_tagihan']) && $filters['status_tagihan']) {
            $query .= " AND ts.status_tagihan = :status_tagihan";
            $params[':status_tagihan'] = $filters['status_tagihan'];
        }
        if (isset($filters['status_tagihan_exclude_lunas']) && $filters['status_tagihan_exclude_lunas'] === true) {
            $query .= " AND ts.status_tagihan != 'Lunas'";
        }

        $query .= "
            GROUP BY
                ts.id, s.nama_lengkap, s.nisn, s.no_telp_ortu, jp.nama_pembayaran, jp.unit_id, ku.nama_unit,
                ta.tahun_ajaran, k.nama_kelas, ts.jumlah_tagihan, ts.periode_tagihan,
                ts.tanggal_jatuh_tempo, ts.status_tagihan, ts.created_at, ts.updated_at
            ORDER BY
                ts.tanggal_jatuh_tempo ASC, s.nama_lengkap ASC
        ";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mengambil detail satu tagihan berdasarkan ID.
     * Menggunakan alias 'tagihan_id' untuk konsistensi.
     *
     * @param int $id ID tagihan.
     * @return array|false Data tagihan jika ditemukan, false jika tidak.
     */
    public function getById($id) {
        $query = "
            SELECT
                ts.id AS tagihan_id,
                ts.siswa_id,
                s.nama_lengkap AS nama_siswa,
                s.nisn,
                s.no_telp_ortu,
                ts.jenis_pembayaran_id,
                jp.nama_pembayaran,
                jp.unit_id,
                ku.nama_unit,
                ts.tahun_ajaran_id,
                ta.tahun_ajaran,
                ts.kelas_id_saat_tagih,
                k.nama_kelas,
                ts.jumlah_tagihan,
                ts.periode_tagihan,
                ts.tanggal_jatuh_tempo,
                ts.status_tagihan,
                ts.created_at,
                ts.updated_at,
                COALESCE(SUM(dpt.jumlah_bayar), 0) AS jumlah_dibayar,
                (ts.jumlah_tagihan - COALESCE(SUM(dpt.jumlah_bayar), 0)) AS sisa_tagihan
            FROM
                " . $this->table_name . " ts
            JOIN
                " . $this->table_siswa . " s ON ts.siswa_id = s.id
            JOIN
                " . $this->table_jenis_pembayaran . " jp ON ts.jenis_pembayaran_id = jp.id
            LEFT JOIN
                " . $this->table_kas_unit . " ku ON jp.unit_id = ku.id
            JOIN
                " . $this->table_tahun_ajaran . " ta ON ts.tahun_ajaran_id = ta.id
            LEFT JOIN
                " . $this->table_kelas . " k ON ts.kelas_id_saat_tagih = k.id
            LEFT JOIN
                " . $this->table_detail_pembayaran . " dpt ON dpt.tagihan_siswa_id = ts.id AND dpt.status_pembayaran_detail = 'Lunas'
            WHERE ts.id = :id
            GROUP BY
                ts.id, s.nama_lengkap, s.nisn, s.no_telp_ortu, jp.nama_pembayaran, jp.unit_id, ku.nama_unit,
                ta.tahun_ajaran, k.nama_kelas, ts.jumlah_tagihan, ts.periode_tagihan,
                ts.tanggal_jatuh_tempo, ts.status_tagihan, ts.created_at, ts.updated_at
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Memperbarui sisa tagihan dan status tagihan di tabel tagihan_siswa.
     * Dipanggil setelah pembayaran atau pembatalan.
     *
     * @param int $tagihanSiswaId ID tagihan siswa.
     * @param float $totalDibayarTerkini Total jumlah yang sudah dibayar terkini.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function updateSisaTagihanAndStatus($tagihanSiswaId, $totalDibayarTerkini) {
        // Ambil jumlah tagihan asli
        $stmt_get_jumlah = $this->pdo->prepare("SELECT jumlah_tagihan FROM " . $this->table_name . " WHERE id = :id");
        $stmt_get_jumlah->bindParam(':id', $tagihanSiswaId, PDO::PARAM_INT);
        $stmt_get_jumlah->execute();
        $tagihan_asli = $stmt_get_jumlah->fetch(PDO::FETCH_ASSOC);

        if (!$tagihan_asli) {
            error_log("Tagihan dengan ID " . $tagihanSiswaId . " tidak ditemukan saat update status.");
            return false;
        }

        $jumlah_tagihan_asli = (float)$tagihan_asli['jumlah_tagihan'];
        $sisa_tagihan_baru = $jumlah_tagihan_asli - $totalDibayarTerkini;

        $status_tagihan_baru = 'Belum Lunas';
        if ($sisa_tagihan_baru <= 0) {
            $status_tagihan_baru = 'Lunas';
        } elseif ($totalDibayarTerkini > 0 && $sisa_tagihan_baru > 0) {
            $status_tagihan_baru = 'Sebagian Lunas';
        }
        // Tambahan: Jika sisa_tagihan_baru sama dengan jumlah_tagihan_asli dan tidak ada pembayaran sama sekali
        // (ini kasus untuk memastikan status 'Belum Lunas' yang benar)
        // Jika status_tagihan sudah 'Dibatalkan', mungkin kita tidak ingin mengubahnya otomatis di sini
        // Logika ini harus dipertimbangkan jika ada kebutuhan khusus untuk status 'Dibatalkan'

        $query = "UPDATE " . $this->table_name . " SET
                    sisa_tagihan = :sisa_tagihan,
                    status_tagihan = :status_tagihan,
                    updated_at = NOW()
                  WHERE id = :id";
        
        $stmt = $this->pdo->prepare($query);

        $stmt->bindParam(':sisa_tagihan', $sisa_tagihan_baru);
        $stmt->bindParam(':status_tagihan', $status_tagihan_baru);
        $stmt->bindParam(':id', $tagihanSiswaId, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return true;
        }
        error_log("Gagal memperbarui sisa tagihan dan status untuk tagihan ID: " . $tagihanSiswaId);
        return false;
    }


    /**
     * Mendapatkan semua tagihan untuk siswa tertentu dalam tahun ajaran tertentu,
     * termasuk jumlah yang sudah dibayar.
     *
     * @param int $siswaId ID siswa.
     * @param int $tahunAjaranId ID tahun ajaran.
     * @return array Daftar tagihan dengan detail pembayaran.
     */
    public function getTagihanBySiswaAndTahunAjaran($siswaId, $tahunAjaranId) {
        // Query untuk mendapatkan semua tagihan dan menjumlahkan pembayaran yang terkait.
        // SUM(COALESCE(ps.jumlah_pembayaran, 0)) digunakan untuk menangani kasus di mana belum ada pembayaran
        // untuk tagihan tertentu (akan menghasilkan 0 daripada NULL).
        $query = "
            SELECT
                ts.id AS tagihan_id,
                ts.jenis_pembayaran_id,
                jp.nama_pembayaran,
                jp.unit_id, -- Ambil unit_id dari jenis_pembayaran
                ku.nama_unit, -- Ambil nama_unit dari kas_unit
                ts.periode_tagihan,
                ts.jumlah_tagihan,
                COALESCE(SUM(dpt.jumlah_bayar), 0) AS jumlah_dibayar, -- Hitung jumlah dibayar
                (ts.jumlah_tagihan - COALESCE(SUM(dpt.jumlah_bayar), 0)) AS sisa_tagihan -- Hitung sisa tagihan
            FROM
                " . $this->table_name . " ts
            JOIN
                " . $this->table_jenis_pembayaran . " jp ON ts.jenis_pembayaran_id = jp.id
            LEFT JOIN
                " . $this->table_kas_unit . " ku ON jp.unit_id = ku.id -- JOIN ke kas_unit
            LEFT JOIN
                " . $this->table_detail_pembayaran . " dpt ON dpt.tagihan_siswa_id = ts.id AND dpt.status_pembayaran_detail = 'Lunas'
            WHERE
                ts.siswa_id = :siswa_id AND ts.tahun_ajaran_id = :tahun_ajaran_id
            GROUP BY
                ts.id, jp.nama_pembayaran, jp.unit_id, ku.nama_unit, ts.periode_tagihan, ts.jumlah_tagihan
            ORDER BY
                ts.periode_tagihan ASC, jp.nama_pembayaran ASC
        ";

        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':siswa_id', $siswaId, PDO::PARAM_INT);
        $stmt->bindParam(':tahun_ajaran_id', $tahunAjaranId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * Membuat tagihan baru.
     *
     * @return bool True jika berhasil, false jika gagal.
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " (siswa_id, jenis_pembayaran_id, tahun_ajaran_id, kelas_id_saat_tagih, jumlah_tagihan, sisa_tagihan, periode_tagihan, tanggal_jatuh_tempo, status_tagihan) VALUES (:siswa_id, :jenis_pembayaran_id, :tahun_ajaran_id, :kelas_id_saat_tagih, :jumlah_tagihan, :sisa_tagihan, :periode_tagihan, :tanggal_jatuh_tempo, :status_tagihan)";
        $stmt = $this->pdo->prepare($query);

        // Membersihkan data
        $this->siswa_id = htmlspecialchars(strip_tags($this->siswa_id));
        $this->jenis_pembayaran_id = htmlspecialchars(strip_tags($this->jenis_pembayaran_id));
        $this->tahun_ajaran_id = htmlspecialchars(strip_tags($this->tahun_ajaran_id));
        $this->kelas_id_saat_tagih = htmlspecialchars(strip_tags($this->kelas_id_saat_tagih));
        $this->jumlah_tagihan = htmlspecialchars(strip_tags($this->jumlah_tagihan));
        $this->sisa_tagihan = htmlspecialchars(strip_tags($this->sisa_tagihan));
        $this->periode_tagihan = htmlspecialchars(strip_tags($this->periode_tagihan));
        $this->tanggal_jatuh_tempo = htmlspecialchars(strip_tags($this->tanggal_jatuh_tempo));
        $this->status_tagihan = htmlspecialchars(strip_tags($this->status_tagihan));


        // Binding parameter
        $stmt->bindParam(':siswa_id', $this->siswa_id, PDO::PARAM_INT);
        $stmt->bindParam(':jenis_pembayaran_id', $this->jenis_pembayaran_id, PDO::PARAM_INT);
        $stmt->bindParam(':tahun_ajaran_id', $this->tahun_ajaran_id, PDO::PARAM_INT);
        $stmt->bindParam(':kelas_id_saat_tagih', $this->kelas_id_saat_tagih, PDO::PARAM_INT);
        $stmt->bindParam(':jumlah_tagihan', $this->jumlah_tagihan);
        $stmt->bindParam(':sisa_tagihan', $this->sisa_tagihan);
        $stmt->bindParam(':periode_tagihan', $this->periode_tagihan);
        $stmt->bindParam(':tanggal_jatuh_tempo', $this->tanggal_jatuh_tempo);
        $stmt->bindParam(':status_tagihan', $this->status_tagihan);


        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    /**
     * Memperbarui detail tagihan siswa.
     *
     * @return bool True jika berhasil, false jika gagal.
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . " SET
                    siswa_id = :siswa_id,
                    jenis_pembayaran_id = :jenis_pembayaran_id,
                    tahun_ajaran_id = :tahun_ajaran_id,
                    kelas_id_saat_tagih = :kelas_id_saat_tagih,
                    jumlah_tagihan = :jumlah_tagihan,
                    sisa_tagihan = :sisa_tagihan,
                    periode_tagihan = :periode_tagihan,
                    tanggal_jatuh_tempo = :tanggal_jatuh_tempo,
                    status_tagihan = :status_tagihan,
                    updated_at = NOW()
                  WHERE id = :id";
        
        $stmt = $this->pdo->prepare($query);

        // Membersihkan data
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->siswa_id = htmlspecialchars(strip_tags($this->siswa_id));
        $this->jenis_pembayaran_id = htmlspecialchars(strip_tags($this->jenis_pembayaran_id));
        $this->tahun_ajaran_id = htmlspecialchars(strip_tags($this->tahun_ajaran_id));
        $this->kelas_id_saat_tagih = htmlspecialchars(strip_tags($this->kelas_id_saat_tagih));
        $this->jumlah_tagihan = htmlspecialchars(strip_tags($this->jumlah_tagihan));
        $this->sisa_tagihan = htmlspecialchars(strip_tags($this->sisa_tagihan));
        $this->periode_tagihan = htmlspecialchars(strip_tags($this->periode_tagihan));
        $this->tanggal_jatuh_tempo = htmlspecialchars(strip_tags($this->tanggal_jatuh_tempo));
        $this->status_tagihan = htmlspecialchars(strip_tags($this->status_tagihan));

        // Binding parameter
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        $stmt->bindParam(':siswa_id', $this->siswa_id, PDO::PARAM_INT);
        $stmt->bindParam(':jenis_pembayaran_id', $this->jenis_pembayaran_id, PDO::PARAM_INT);
        $stmt->bindParam(':tahun_ajaran_id', $this->tahun_ajaran_id, PDO::PARAM_INT);
        $stmt->bindParam(':kelas_id_saat_tagih', $this->kelas_id_saat_tagih, PDO::PARAM_INT);
        $stmt->bindParam(':jumlah_tagihan', $this->jumlah_tagihan);
        $stmt->bindParam(':sisa_tagihan', $this->sisa_tagihan);
        $stmt->bindParam(':periode_tagihan', $this->periode_tagihan);
        $stmt->bindParam(':tanggal_jatuh_tempo', $this->tanggal_jatuh_tempo);
        $stmt->bindParam(':status_tagihan', $this->status_tagihan);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    /**
     * Menghapus tagihan siswa berdasarkan ID.
     * Hanya boleh dilakukan jika belum ada pembayaran terkait.
     *
     * @param int $id ID tagihan.
     * @return array Hasil operasi (status dan pesan).
     */
    public function delete($id) {
        // Mulai transaksi
        $this->pdo->beginTransaction();
        try {
            // Periksa apakah ada pembayaran terkait
            $stmt_check_payments = $this->pdo->prepare("SELECT COUNT(*) FROM " . $this->table_detail_pembayaran . " WHERE tagihan_siswa_id = :tagihan_id AND status_pembayaran_detail = 'Lunas'");
            $stmt_check_payments->bindParam(':tagihan_id', $id, PDO::PARAM_INT);
            $stmt_check_payments->execute();
            if ($stmt_check_payments->fetchColumn() > 0) {
                $this->pdo->rollBack();
                return ['status' => 'error', 'message' => 'Tidak dapat menghapus tagihan karena sudah ada pembayaran terkait. Harap batalkan pembayaran terlebih dahulu.'];
            }

            // Hapus tagihan
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $this->pdo->commit();
                return ['status' => 'success', 'message' => 'Tagihan berhasil dihapus.'];
            } else {
                $this->pdo->rollBack();
                return ['status' => 'error', 'message' => 'Gagal menghapus tagihan.'];
            }
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error deleting tagihan: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Terjadi kesalahan database saat menghapus tagihan.'];
        }
    }

    /**
     * Memeriksa apakah ada tagihan dengan jenis pembayaran tertentu.
     * Digunakan sebelum menghapus jenis pembayaran.
     *
     * @param int $jenisPembayaranId ID jenis pembayaran.
     * @return bool True jika ada tagihan, false jika tidak.
     */
    public function hasTagihanByJenisPembayaranId($jenisPembayaranId) {
        $query = "SELECT COUNT(*) FROM " . $this->table_name . " WHERE jenis_pembayaran_id = :jenis_pembayaran_id";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':jenis_pembayaran_id', $jenisPembayaranId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Menghitung total sisa tagihan (outstanding balance) untuk siswa dan tahun ajaran tertentu.
     * Hanya menghitung tagihan yang belum lunas atau sebagian lunas.
     *
     * @param int $siswa_id ID siswa.
     * @param int $tahun_ajaran_id ID tahun ajaran.
     * @return float Total sisa tagihan.
     */
    public function getStudentTotalOutstandingBillsForAcademicYear($siswa_id, $tahun_ajaran_id) {
        $query = "SELECT SUM(ts.jumlah_tagihan - COALESCE(dpt.jumlah_bayar, 0)) AS total_outstanding
                  FROM " . $this->table_name . " ts
                  LEFT JOIN (
                      SELECT tagihan_siswa_id, SUM(jumlah_bayar) AS jumlah_bayar
                      FROM " . $this->table_detail_pembayaran . "
                      WHERE status_pembayaran_detail = 'Lunas' -- Hanya hitung pembayaran yang lunas
                      GROUP BY tagihan_siswa_id
                  ) dpt ON ts.id = dpt.tagihan_siswa_id
                  WHERE ts.siswa_id = :siswa_id
                  AND ts.tahun_ajaran_id = :tahun_ajaran_id
                  HAVING total_outstanding > 0"; // Hanya hitung yang masih outstanding

        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':siswa_id', $siswa_id, PDO::PARAM_INT);
        $stmt->bindParam(':tahun_ajaran_id', $tahun_ajaran_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (float)($result['total_outstanding'] ?? 0);
    }

    /**
     * Mendapatkan rincian tagihan yang belum lunas untuk seorang siswa pada tahun ajaran tertentu.
     * Termasuk nama unit kas.
     *
     * @param int $siswa_id ID siswa.
     * @param int $tahun_ajaran_id ID tahun ajaran.
     * @return array Array asosiatif dari detail tagihan yang belum lunas.
     * Setiap item akan berisi: 'nama_pembayaran', 'jumlah_tagihan', 'sisa_tagihan', 'nama_unit'.
     */
    public function getDetailedOutstandingBillsForAcademicYear($siswa_id, $tahun_ajaran_id) {
        $query = "SELECT 
                    jp.nama_pembayaran, -- Menggunakan nama_pembayaran dari tabel jenis_pembayaran
                    jp.unit_id,         -- Ambil unit_id
                    ku.nama_unit,       -- Ambil nama_unit
                    ta.tahun_ajaran,    -- Menambahkan tahun_ajaran dari tabel tahun_ajaran
                    ts.jumlah_tagihan,
                    (ts.jumlah_tagihan - COALESCE(dpt_sum.jumlah_bayar, 0)) AS sisa_tagihan
                  FROM " . $this->table_name . " ts
                  JOIN " . $this->table_jenis_pembayaran . " jp ON ts.jenis_pembayaran_id = jp.id -- JOIN ke tabel jenis_pembayaran
                  LEFT JOIN " . $this->table_kas_unit . " ku ON jp.unit_id = ku.id -- JOIN ke kas_unit
                  JOIN " . $this->table_tahun_ajaran . " ta ON ts.tahun_ajaran_id = ta.id -- JOIN ke tabel tahun_ajaran
                  LEFT JOIN (
                      SELECT tagihan_siswa_id, SUM(jumlah_bayar) AS jumlah_bayar
                      FROM " . $this->table_detail_pembayaran . "
                      WHERE status_pembayaran_detail = 'Lunas' -- Hanya hitung pembayaran yang lunas
                      GROUP BY tagihan_siswa_id
                  ) dpt_sum ON ts.id = dpt_sum.tagihan_siswa_id
                  WHERE ts.siswa_id = :siswa_id
                  AND ts.tahun_ajaran_id = :tahun_ajaran_id
                  HAVING sisa_tagihan > 0
                  ORDER BY ta.tahun_ajaran ASC, jp.nama_pembayaran ASC"; // Urutkan agar rapi

        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':siswa_id', $siswa_id, PDO::PARAM_INT);
        $stmt->bindParam(':tahun_ajaran_id', $tahun_ajaran_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Mengambil semua tagihan untuk seorang siswa dalam satu tahun ajaran tertentu,
     * termasuk jumlah yang sudah dibayar, sisa tagihan, dan nama unit kas.
     *
     * @param int $siswaId ID siswa.
     * @param int $tahunAjaranId ID tahun ajaran.
     * @return array Daftar tagihan dengan status pembayaran.
     */
    public function getAllTagihanBySiswaAndTahunAjaran($siswaId, $tahunAjaranId) {
        // Query untuk mendapatkan semua tagihan dan menjumlahkan pembayaran yang terkait.
        // SUM(COALESCE(dpt.jumlah_bayar, 0)) digunakan untuk menangani kasus di mana belum ada pembayaran
        // untuk tagihan tertentu (akan menghasilkan 0 daripada NULL).
        $query = "
            SELECT
                ts.id AS tagihan_id,
                ts.jenis_pembayaran_id,
                jp.nama_pembayaran,
                jp.unit_id, -- Ambil unit_id dari jenis_pembayaran
                ku.nama_unit, -- Ambil nama_unit dari kas_unit
                ts.periode_tagihan,
                ts.jumlah_tagihan,
                COALESCE(SUM(dpt.jumlah_bayar), 0) AS jumlah_dibayar, -- Hitung jumlah dibayar
                (ts.jumlah_tagihan - COALESCE(SUM(dpt.jumlah_bayar), 0)) AS sisa_tagihan -- Hitung sisa tagihan
            FROM
                " . $this->table_name . " ts
            JOIN
                " . $this->table_jenis_pembayaran . " jp ON ts.jenis_pembayaran_id = jp.id
            LEFT JOIN
                " . $this->table_kas_unit . " ku ON jp.unit_id = ku.id -- JOIN ke kas_unit
            LEFT JOIN
                " . $this->table_detail_pembayaran . " dpt ON dpt.tagihan_siswa_id = ts.id AND dpt.status_pembayaran_detail = 'Lunas'
            WHERE
                ts.siswa_id = :siswa_id AND ts.tahun_ajaran_id = :tahun_ajaran_id
            GROUP BY
                ts.id, jp.nama_pembayaran, jp.unit_id, ku.nama_unit, ts.periode_tagihan, ts.jumlah_tagihan
            ORDER BY
                ts.periode_tagihan ASC, jp.nama_pembayaran ASC
        ";

        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':siswa_id', $siswaId, PDO::PARAM_INT);
        $stmt->bindParam(':tahun_ajaran_id', $tahunAjaranId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Mengambil detail pembayaran tagihan siswa berdasarkan berbagai filter.
     * Digunakan untuk laporan komprehensif.
     *
     * @param array $filters Array filter (jenis_pembayaran_id, tahun_ajaran_id, kelas_id, status_pembayaran_detail, search_nama_nisn).
     * @return array Array asosiatif dari detail pembayaran.
     */
    public function getPaymentsForReport(array $filters = []) {
        $query = "
            SELECT
                dpt.id AS detail_pembayaran_id,
                s.nisn,
                s.nama_lengkap,
                k.nama_kelas,
                jp.nama_pembayaran,
                ta.tahun_ajaran,
                ts.periode_tagihan,
                ts.jumlah_tagihan,
                dpt.jumlah_bayar,
                dpt.tanggal_bayar,
                dpt.metode_pembayaran,
                dpt.catatan,
                dpt.status_pembayaran_detail
            FROM
                " . $this->table_detail_pembayaran . " dpt
            JOIN
                " . $this->table_name . " ts ON dpt.tagihan_siswa_id = ts.id
            JOIN
                " . $this->table_siswa . " s ON ts.siswa_id = s.id
            JOIN
                " . $this->table_kelas . " k ON s.kelas_id = k.id
            JOIN
                " . $this->table_jenis_pembayaran . " jp ON ts.jenis_pembayaran_id = jp.id
            JOIN
                " . $this->table_tahun_ajaran . " ta ON ts.tahun_ajaran_id = ta.id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['jenis_pembayaran_id'])) {
            $query .= " AND jp.id = :jenis_pembayaran_id";
            $params[':jenis_pembayaran_id'] = $filters['jenis_pembayaran_id'];
        }
        if (!empty($filters['tahun_ajaran_id'])) {
            $query .= " AND ta.id = :tahun_ajaran_id";
            $params[':tahun_ajaran_id'] = $filters['tahun_ajaran_id'];
        }
        if (!empty($filters['kelas_id'])) {
            $query .= " AND k.id = :kelas_id";
            $params[':kelas_id'] = $filters['kelas_id'];
        }
        if (!empty($filters['status_pembayaran_detail'])) {
            $query .= " AND dpt.status_pembayaran_detail = :status_pembayaran_detail";
            $params[':status_pembayaran_detail'] = $filters['status_pembayaran_detail'];
        }
        // Filter tanggal bayar
        if (!empty($filters['start_date'])) {
            $query .= " AND dpt.tanggal_bayar >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $query .= " AND dpt.tanggal_bayar <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }

        $query .= " ORDER BY ta.tahun_ajaran DESC, k.nama_kelas ASC, s.nama_lengkap ASC, dpt.tanggal_bayar ASC";

        $stmt = $this->pdo->prepare($query);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Mengambil detail pembayaran bulanan untuk laporan.
     * Mengembalikan data yang diorganisir per siswa per bulan.
     *
     * @param array $filters Array filter (jenis_pembayaran_id, tahun_ajaran_id, kelas_id).
     * @return array Array multidimensional yang diorganisir per siswa.
     */
    public function getMonthlyPaymentsForReport(array $filters = []) {
        $query = "
            SELECT
                s.id AS siswa_id,
                s.nisn,
                s.nama_lengkap,
                k.nama_kelas,
                jp.nama_pembayaran,
                ts.periode_tagihan,
                ts.jumlah_tagihan,
                COALESCE(SUM(dpt.jumlah_bayar), 0) AS jumlah_dibayar,
                MAX(dpt.tanggal_bayar) AS tanggal_bayar_terakhir,
                (ts.jumlah_tagihan - COALESCE(SUM(dpt.jumlah_bayar), 0)) AS sisa_tagihan
            FROM
                " . $this->table_name . " ts
            JOIN
                " . $this->table_siswa . " s ON ts.siswa_id = s.id
            JOIN
                " . $this->table_kelas . " k ON s.kelas_id = k.id
            JOIN
                " . $this->table_jenis_pembayaran . " jp ON ts.jenis_pembayaran_id = jp.id
            LEFT JOIN
                " . $this->table_detail_pembayaran . " dpt ON dpt.tagihan_siswa_id = ts.id AND dpt.status_pembayaran_detail = 'Lunas'
            WHERE
                jp.tipe_pembayaran = 'Rutin Bulanan'
        ";

        $params = [];

        if (!empty($filters['jenis_pembayaran_id'])) {
            $query .= " AND jp.id = :jenis_pembayaran_id";
            $params[':jenis_pembayaran_id'] = $filters['jenis_pembayaran_id'];
        }
        if (!empty($filters['tahun_ajaran_id'])) {
            $query .= " AND ts.tahun_ajaran_id = :tahun_ajaran_id";
            $params[':tahun_ajaran_id'] = $filters['tahun_ajaran_id'];
        }
        if (!empty($filters['kelas_id'])) {
            $query .= " AND k.id = :kelas_id";
            $params[':kelas_id'] = $filters['kelas_id'];
        }

        $query .= "
            GROUP BY
                s.id, s.nisn, s.nama_lengkap, k.nama_kelas, jp.nama_pembayaran,
                ts.periode_tagihan, ts.jumlah_tagihan
            ORDER BY
                s.nama_lengkap ASC, FIELD(ts.periode_tagihan,
                    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
                    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni'
                ) ASC
        ";

        $stmt = $this->pdo->prepare($query);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mengorganisir data per siswa
        $organized_data = [];
        foreach ($results as $row) {
            $siswa_id = $row['siswa_id'];
            if (!isset($organized_data[$siswa_id])) {
                $organized_data[$siswa_id] = [
                    'nisn' => $row['nisn'],
                    'nama_lengkap' => $row['nama_lengkap'],
                    'nama_kelas' => $row['nama_kelas'],
                    'jenis_pembayaran' => $row['nama_pembayaran'],
                    'bulan' => [] // Untuk menyimpan data per bulan
                ];
            }
            $bulan_periode = explode(' ', $row['periode_tagihan'])[0]; // Ambil nama bulan saja
            $organized_data[$siswa_id]['bulan'][$bulan_periode] = [
                'jumlah_tagihan' => $row['jumlah_tagihan'],
                'jumlah_dibayar' => $row['jumlah_dibayar'],
                'sisa_tagihan' => $row['sisa_tagihan'],
                'tanggal_bayar_terakhir' => $row['tanggal_bayar_terakhir']
            ];
        }
        return array_values($organized_data); // Mengembalikan sebagai array indeks numerik
    }
}
