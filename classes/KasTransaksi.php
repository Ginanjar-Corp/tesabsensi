<?php
// project-root/classes/KasTransaksi.php

class KasTransaksi {
    private $pdo;
    private $table_name = "kas_transaksi"; // Tabel utama untuk kas manual
    private $table_kategori = "kas_kategori";
    private $table_unit = "kas_unit";
    private $table_users = "users";
    // Tabel baru yang diperlukan untuk ringkasan komprehensif
    private $table_jenis_pembayaran = "jenis_pembayaran";
    private $table_tagihan_siswa = "tagihan_siswa";
    private $table_detail_pembayaran_transaksi = "detail_pembayaran_transaksi";

    public function __construct(PDO $db) {
        $this->pdo = $db;
    }

    /**
     * Menambahkan transaksi kas baru.
     *
     * @param string $tanggal_transaksi Tanggal transaksi (YYYY-MM-DD).
     * @param string $deskripsi Deskripsi transaksi.
     * @param float $jumlah Jumlah uang.
     * @param string $tipe_transaksi Tipe transaksi ('masuk' atau 'keluar').
     * @param int $kategori_id ID kategori.
     * @param int $unit_id ID unit.
     * @param int|null $recorded_by_user_id ID user yang mencatat.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function create($tanggal_transaksi, $deskripsi, $jumlah, $tipe_transaksi, $kategori_id, $unit_id, $recorded_by_user_id = null) {
        $query = "INSERT INTO " . $this->table_name . " (tanggal_transaksi, deskripsi, jumlah, tipe_transaksi, kategori_id, unit_id, recorded_by_user_id) VALUES (:tanggal_transaksi, :deskripsi, :jumlah, :tipe_transaksi, :kategori_id, :unit_id, :recorded_by_user_id)";
        $stmt = $this->pdo->prepare($query);

        $stmt->bindParam(':tanggal_transaksi', $tanggal_transaksi);
        $stmt->bindParam(':deskripsi', $deskripsi);
        $stmt->bindParam(':jumlah', $jumlah);
        $stmt->bindParam(':tipe_transaksi', $tipe_transaksi);
        $stmt->bindParam(':kategori_id', $kategori_id, PDO::PARAM_INT);
        $stmt->bindParam(':unit_id', $unit_id, PDO::PARAM_INT);
        $stmt->bindParam(':recorded_by_user_id', $recorded_by_user_id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Memperbarui transaksi kas.
     *
     * @param int $id ID transaksi.
     * @param string $tanggal_transaksi Tanggal transaksi (YYYY-MM-DD).
     * @param string $deskripsi Deskripsi transaksi.
     * @param float $jumlah Jumlah uang.
     * @param string $tipe_transaksi Tipe transaksi ('masuk' atau 'keluar').
     * @param int $kategori_id ID kategori.
     * @param int $unit_id ID unit.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function update($id, $tanggal_transaksi, $deskripsi, $jumlah, $tipe_transaksi, $kategori_id, $unit_id) {
        $query = "UPDATE " . $this->table_name . " SET
                    tanggal_transaksi = :tanggal_transaksi,
                    deskripsi = :deskripsi,
                    jumlah = :jumlah,
                    tipe_transaksi = :tipe_transaksi,
                    kategori_id = :kategori_id,
                    unit_id = :unit_id,
                    updated_at = CURRENT_TIMESTAMP
                  WHERE id = :id";
        $stmt = $this->pdo->prepare($query);

        $stmt->bindParam(':tanggal_transaksi', $tanggal_transaksi);
        $stmt->bindParam(':deskripsi', $deskripsi);
        $stmt->bindParam(':jumlah', $jumlah);
        $stmt->bindParam(':tipe_transaksi', $tipe_transaksi);
        $stmt->bindParam(':kategori_id', $kategori_id, PDO::PARAM_INT);
        $stmt->bindParam(':unit_id', $unit_id, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Menghapus transaksi kas.
     *
     * @param int $id ID transaksi.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Mengambil transaksi kas dengan filter dan pagination.
     *
     * @param array $filters Array filter (search, kategori_id, unit_id, tipe_transaksi, start_date, end_date).
     * @param int $limit Batas jumlah hasil.
     * @param int $offset Offset untuk pagination.
     * @return array Daftar transaksi kas.
     */
    public function getTransactions(array $filters = [], int $limit = 10, int $offset = 0) {
        $query = "
            SELECT
                kt.id,
                kt.tanggal_transaksi,
                kt.deskripsi,
                kt.jumlah,
                kt.tipe_transaksi,
                kk.nama_kategori,
                ku.nama_unit,
                u.username AS recorded_by_user,
                kt.created_at,
                kt.updated_at
            FROM
                " . $this->table_name . " kt
            JOIN
                " . $this->table_kategori . " kk ON kt.kategori_id = kk.id
            JOIN
                " . $this->table_unit . " ku ON kt.unit_id = ku.id
            LEFT JOIN
                " . $this->table_users . " u ON kt.recorded_by_user_id = u.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['search'])) {
            $query .= " AND (kt.deskripsi LIKE :search OR kk.nama_kategori LIKE :search OR ku.nama_unit LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['kategori_id'])) {
            $query .= " AND kt.kategori_id = :kategori_id";
            $params[':kategori_id'] = $filters['kategori_id'];
        }
        if (!empty($filters['unit_id'])) {
            $query .= " AND kt.unit_id = :unit_id";
            $params[':unit_id'] = $filters['unit_id'];
        }
        if (!empty($filters['tipe_transaksi'])) {
            $query .= " AND kt.tipe_transaksi = :tipe_transaksi";
            $params[':tipe_transaksi'] = $filters['tipe_transaksi'];
        }
        if (!empty($filters['start_date'])) {
            $query .= " AND kt.tanggal_transaksi >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $query .= " AND kt.tanggal_transaksi <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }

        $query .= " ORDER BY kt.tanggal_transaksi DESC, kt.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Menghitung total transaksi kas dengan filter.
     *
     * @param array $filters Array filter (search, kategori_id, unit_id, tipe_transaksi, start_date, end_date).
     * @return int Total jumlah transaksi.
     */
    public function countTransactions(array $filters = []): int {
        $query = "
            SELECT COUNT(kt.id)
            FROM
                " . $this->table_name . " kt
            JOIN
                " . $this->table_kategori . " kk ON kt.kategori_id = kk.id
            JOIN
                " . $this->table_unit . " ku ON kt.unit_id = ku.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['search'])) {
            $query .= " AND (kt.deskripsi LIKE :search OR kk.nama_kategori LIKE :search OR ku.nama_unit LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['kategori_id'])) {
            $query .= " AND kt.kategori_id = :kategori_id";
            $params[':kategori_id'] = $filters['kategori_id'];
        }
        if (!empty($filters['unit_id'])) {
            $query .= " AND kt.unit_id = :unit_id";
            $params[':unit_id'] = $filters['unit_id'];
        }
        if (!empty($filters['tipe_transaksi'])) {
            $query .= " AND kt.tipe_transaksi = :tipe_transaksi";
            $params[':tipe_transaksi'] = $filters['tipe_transaksi'];
        }
        if (!empty($filters['start_date'])) {
            $query .= " AND kt.tanggal_transaksi >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $query .= " AND kt.tanggal_transaksi <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }

        $stmt = $this->pdo->prepare($query);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Mengambil satu transaksi berdasarkan ID.
     *
     * @param int $id ID transaksi.
     * @return array|false Data transaksi jika ditemukan, false jika tidak.
     */
    public function getById($id) {
        $query = "
            SELECT
                kt.id,
                kt.tanggal_transaksi,
                kt.deskripsi,
                kt.jumlah,
                kt.tipe_transaksi,
                kk.id AS kategori_id,
                kk.nama_kategori,
                ku.id AS unit_id,
                ku.nama_unit,
                u.username AS recorded_by_user,
                kt.created_at,
                kt.updated_at
            FROM
                " . $this->table_name . " kt
            JOIN
                " . $this->table_kategori . " kk ON kt.kategori_id = kk.id
            JOIN
                " . $this->table_unit . " ku ON kt.unit_id = ku.id
            LEFT JOIN
                " . $this->table_users . " u ON kt.recorded_by_user_id = u.id
            WHERE kt.id = :id LIMIT 0,1
        ";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Menghitung total kas masuk global.
     *
     * @return float Total kas masuk.
     */
    public function getTotalKasMasukGlobal(): float {
        // Hitung dari kas_transaksi (tipe 'masuk')
        $query_kas = "SELECT COALESCE(SUM(jumlah), 0) FROM " . $this->table_name . " WHERE tipe_transaksi = 'masuk'";
        $stmt_kas = $this->pdo->prepare($query_kas);
        $stmt_kas->execute();
        $total_kas_masuk = (float) $stmt_kas->fetchColumn();

        // Hitung dari detail_pembayaran_transaksi (status 'Lunas')
        $query_pembayaran = "
            SELECT COALESCE(SUM(dpt.jumlah_bayar), 0)
            FROM " . $this->table_detail_pembayaran_transaksi . " dpt
            WHERE dpt.status_pembayaran_detail = 'Lunas'
        ";
        $stmt_pembayaran = $this->pdo->prepare($query_pembayaran);
        $stmt_pembayaran->execute();
        $total_pembayaran_masuk = (float) $stmt_pembayaran->fetchColumn();

        return $total_kas_masuk + $total_pembayaran_masuk;
    }

    /**
     * Menghitung total kas keluar global.
     *
     * @return float Total kas keluar.
     */
    public function getTotalKasKeluarGlobal(): float {
        $query = "SELECT COALESCE(SUM(jumlah), 0) FROM " . $this->table_name . " WHERE tipe_transaksi = 'keluar'";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        return (float) $stmt->fetchColumn();
    }

    /**
     * Mengambil ringkasan kas masuk, keluar, dan saldo per unit secara komprehensif.
     * Ini menggabungkan transaksi kas manual dan pembayaran tagihan siswa.
     *
     * @return array Array asosiatif dengan ringkasan per unit.
     */
    public function getUnitCashSummary(): array {
        $query = "
            SELECT
                unit_id,
                nama_unit,
                SUM(total_masuk) AS total_masuk,
                SUM(total_keluar) AS total_keluar
            FROM
                (
                    -- Transaksi dari tabel kas_transaksi
                    SELECT
                        ku.id AS unit_id,
                        ku.nama_unit,
                        COALESCE(SUM(CASE WHEN kt.tipe_transaksi = 'masuk' THEN kt.jumlah ELSE 0 END), 0) AS total_masuk,
                        COALESCE(SUM(CASE WHEN kt.tipe_transaksi = 'keluar' THEN kt.jumlah ELSE 0 END), 0) AS total_keluar
                    FROM
                        " . $this->table_unit . " ku
                    LEFT JOIN
                        " . $this->table_name . " kt ON ku.id = kt.unit_id
                    GROUP BY
                        ku.id, ku.nama_unit

                    UNION ALL

                    -- Pembayaran dari tabel detail_pembayaran_transaksi (status 'Lunas')
                    SELECT
                        ku.id AS unit_id,
                        ku.nama_unit,
                        COALESCE(SUM(dpt.jumlah_bayar), 0) AS total_masuk,
                        0 AS total_keluar -- Pembayaran tagihan selalu merupakan pemasukan
                    FROM
                        " . $this->table_unit . " ku
                    JOIN
                        " . $this->table_jenis_pembayaran . " jp ON ku.id = jp.unit_id
                    JOIN
                        " . $this->table_tagihan_siswa . " ts ON jp.id = ts.jenis_pembayaran_id
                    JOIN
                        " . $this->table_detail_pembayaran_transaksi . " dpt ON ts.id = dpt.tagihan_siswa_id
                    WHERE
                        dpt.status_pembayaran_detail = 'Lunas'
                    GROUP BY
                        ku.id, ku.nama_unit
                ) AS combined_cash_flow
            GROUP BY
                unit_id, nama_unit
            ORDER BY
                nama_unit ASC
        ";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
