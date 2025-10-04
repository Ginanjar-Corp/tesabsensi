<?php
// project-root/classes/DetailPembayaranTransaksi.php

class DetailPembayaranTransaksi {
    private $pdo;
    private $table_name = "detail_pembayaran_transaksi";
    private $table_tagihan_siswa = "tagihan_siswa"; // Diperlukan untuk update sisa tagihan
    private $table_users = "users"; // Untuk mendapatkan nama petugas

    public $id;
    public $tagihan_siswa_id;
    public $tanggal_bayar;
    public $jumlah_bayar;
    public $metode_pembayaran; // Contoh: 'Tunai', 'Transfer Bank', 'Debit Card'
    public $petugas_id; // ID pengguna (admin/tata usaha) yang mencatat pembayaran
    public $bukti_pembayaran_path; // Path ke file bukti pembayaran
    public $catatan;
    public $status_pembayaran_detail; // BARU: 'Lunas', 'Dibatalkan'
    public $created_at;
    public $updated_at;

    public function __construct(PDO $db) {
        $this->pdo = $db;
    }

    /**
     * Mengambil semua detail pembayaran dengan filter opsional.
     *
     * @param array $filters Array filter (tagihan_siswa_id, petugas_id, status_pembayaran_detail)
     * @return array Array asosiatif dari detail pembayaran.
     */
    public function getAll(array $filters = []) {
        $query = "SELECT dpt.id, dpt.tagihan_siswa_id, dpt.tanggal_bayar, dpt.jumlah_bayar,
                         dpt.metode_pembayaran, dpt.petugas_id, dpt.bukti_pembayaran_path,
                         dpt.catatan, dpt.status_pembayaran_detail, dpt.created_at, dpt.updated_at,
                         u.username AS nama_petugas
                  FROM " . $this->table_name . " dpt
                  LEFT JOIN " . $this->table_users . " u ON dpt.petugas_id = u.id
                  WHERE 1=1";
        
        $params = [];

        if (isset($filters['tagihan_siswa_id']) && $filters['tagihan_siswa_id']) {
            $query .= " AND dpt.tagihan_siswa_id = :tagihan_siswa_id";
            $params[':tagihan_siswa_id'] = $filters['tagihan_siswa_id'];
        }
        if (isset($filters['petugas_id']) && $filters['petugas_id']) {
            $query .= " AND dpt.petugas_id = :petugas_id";
            $params[':petugas_id'] = $filters['petugas_id'];
        }
        if (isset($filters['status_pembayaran_detail']) && $filters['status_pembayaran_detail'] !== 'all') {
            $query .= " AND dpt.status_pembayaran_detail = :status_pembayaran_detail";
            $params[':status_pembayaran_detail'] = $filters['status_pembayaran_detail'];
        }

        $query .= " ORDER BY dpt.tanggal_bayar DESC, dpt.created_at DESC";

        $stmt = $this->pdo->prepare($query);
        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mendapatkan detail pembayaran berdasarkan ID tagihan siswa.
     * Digunakan untuk menampilkan riwayat pembayaran untuk tagihan tertentu.
     *
     * @param int $tagihan_siswa_id ID tagihan siswa.
     * @return array Array asosiatif dari detail pembayaran.
     */
    public function getByTagihanSiswaId($tagihan_siswa_id) {
        $query = "SELECT dpt.id, dpt.tanggal_bayar, dpt.jumlah_bayar, dpt.metode_pembayaran,
                         dpt.bukti_pembayaran_path, dpt.catatan, dpt.status_pembayaran_detail,
                         u.username AS nama_petugas
                  FROM " . $this->table_name . " dpt
                  LEFT JOIN " . $this->table_users . " u ON dpt.petugas_id = u.id
                  WHERE dpt.tagihan_siswa_id = :tagihan_siswa_id
                  ORDER BY dpt.tanggal_bayar ASC, dpt.created_at ASC";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':tagihan_siswa_id', $tagihan_siswa_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mendapatkan satu detail pembayaran berdasarkan ID.
     *
     * @param int $id ID detail pembayaran.
     * @return array|false Data detail pembayaran jika ditemukan, false jika tidak.
     */
    public function getById($id) {
        $query = "SELECT dpt.id, dpt.tagihan_siswa_id, dpt.tanggal_bayar, dpt.jumlah_bayar,
                         dpt.metode_pembayaran, dpt.petugas_id, dpt.bukti_pembayaran_path,
                         dpt.catatan, dpt.status_pembayaran_detail, dpt.created_at, dpt.updated_at,
                         u.username AS nama_petugas
                  FROM " . $this->table_name . " dpt
                  LEFT JOIN " . $this->table_users . " u ON dpt.petugas_id = u.id
                  WHERE dpt.id = :id LIMIT 0,1";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Menghitung total pembayaran yang SAH (bukan 'Dibatalkan') untuk sebuah tagihan.
     *
     * @param int $tagihan_siswa_id ID tagihan siswa.
     * @return float Total jumlah yang dibayar.
     */
    public function getTotalPaidForTagihan($tagihan_siswa_id) {
        $query = "SELECT SUM(jumlah_bayar) AS total_paid
                  FROM " . $this->table_name . "
                  WHERE tagihan_siswa_id = :tagihan_siswa_id AND status_pembayaran_detail = 'Lunas'";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':tagihan_siswa_id', $tagihan_siswa_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float) ($result['total_paid'] ?? 0);
    }

    /**
     * Menambahkan detail pembayaran baru.
     * Otomatis mengupdate sisa tagihan dan status tagihan di tabel tagihan_siswa.
     *
     * @return bool True jika berhasil, false jika gagal.
     */
    public function create() {
        $this->pdo->beginTransaction();
        try {
            $query = "INSERT INTO " . $this->table_name . " (
                        tagihan_siswa_id, tanggal_bayar, jumlah_bayar, metode_pembayaran,
                        petugas_id, bukti_pembayaran_path, catatan, status_pembayaran_detail
                    ) VALUES (
                        :tagihan_siswa_id, :tanggal_bayar, :jumlah_bayar, :metode_pembayaran,
                        :petugas_id, :bukti_pembayaran_path, :catatan, :status_pembayaran_detail
                    )";
            $stmt = $this->pdo->prepare($query);

            $this->tagihan_siswa_id = htmlspecialchars(strip_tags($this->tagihan_siswa_id));
            $this->tanggal_bayar = htmlspecialchars(strip_tags($this->tanggal_bayar));
            $this->jumlah_bayar = htmlspecialchars(strip_tags($this->jumlah_bayar));
            $this->metode_pembayaran = htmlspecialchars(strip_tags($this->metode_pembayaran));
            $this->petugas_id = htmlspecialchars(strip_tags($this->petugas_id));
            $this->bukti_pembayaran_path = htmlspecialchars(strip_tags($this->bukti_pembayaran_path));
            $this->catatan = htmlspecialchars(strip_tags($this->catatan));
            // Default status saat membuat adalah 'Lunas' (untuk transaksi detail)
            $this->status_pembayaran_detail = 'Lunas'; 

            $stmt->bindParam(':tagihan_siswa_id', $this->tagihan_siswa_id, PDO::PARAM_INT);
            $stmt->bindParam(':tanggal_bayar', $this->tanggal_bayar);
            $stmt->bindParam(':jumlah_bayar', $this->jumlah_bayar);
            $stmt->bindParam(':metode_pembayaran', $this->metode_pembayaran);
            $stmt->bindParam(':petugas_id', $this->petugas_id, PDO::PARAM_INT);
            $stmt->bindParam(':bukti_pembayaran_path', $this->bukti_pembayaran_path);
            $stmt->bindParam(':catatan', $this->catatan);
            $stmt->bindParam(':status_pembayaran_detail', $this->status_pembayaran_detail);

            if ($stmt->execute()) {
                // Setelah berhasil membuat detail pembayaran, update sisa tagihan di tabel tagihan_siswa
                $tagihan_siswa_model = new TagihanSiswa($this->pdo); // Inisialisasi model TagihanSiswa
                $total_dibayar_terkini = $this->getTotalPaidForTagihan($this->tagihan_siswa_id);
                if ($tagihan_siswa_model->updateSisaTagihanAndStatus($this->tagihan_siswa_id, $total_dibayar_terkini)) {
                    $this->pdo->commit();
                    return true;
                } else {
                    throw new Exception("Gagal memperbarui sisa tagihan di tabel tagihan_siswa.");
                }
            }
            throw new Exception("Gagal membuat detail pembayaran.");
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error creating payment detail: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Memperbarui detail pembayaran (misal: status_pembayaran_detail).
     * Setelah update, hitung ulang dan perbarui sisa tagihan di tabel tagihan_siswa.
     *
     * @param int $id ID detail pembayaran.
     * @param array $data Data yang akan diperbarui.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function update($id, array $data) {
        $this->pdo->beginTransaction();
        try {
            $set_parts = [];
            $params = [':id' => $id];

            foreach ($data as $key => $value) {
                if (in_array($key, ['tanggal_bayar', 'jumlah_bayar', 'metode_pembayaran', 'petugas_id', 'bukti_pembayaran_path', 'catatan', 'status_pembayaran_detail'])) {
                    $set_parts[] = "$key = :$key";
                    $params[":$key"] = htmlspecialchars(strip_tags($value));
                }
            }
            $set_parts[] = "updated_at = CURRENT_TIMESTAMP";

            if (empty($set_parts)) {
                return false; // Tidak ada data yang valid untuk diperbarui
            }

            $query = "UPDATE " . $this->table_name . " SET " . implode(', ', $set_parts) . " WHERE id = :id";
            $stmt = $this->pdo->prepare($query);

            if ($stmt->execute($params)) {
                // Ambil tagihan_siswa_id yang terkait dengan detail pembayaran ini
                $current_detail = $this->getById($id);
                if ($current_detail && $current_detail['tagihan_siswa_id']) {
                    $tagihan_siswa_id = $current_detail['tagihan_siswa_id'];
                    $tagihan_siswa_model = new TagihanSiswa($this->pdo);
                    $total_dibayar_terkini = $this->getTotalPaidForTagihan($tagihan_siswa_id);
                    if ($tagihan_siswa_model->updateSisaTagihanAndStatus($tagihan_siswa_id, $total_dibayar_terkini)) {
                        $this->pdo->commit();
                        return true;
                    } else {
                        throw new Exception("Gagal memperbarui sisa tagihan di tabel tagihan_siswa setelah update detail pembayaran.");
                    }
                }
                $this->pdo->commit(); // Commit jika tidak ada tagihan_siswa_id yang terkait
                return true;
            }
            throw new Exception("Gagal memperbarui detail pembayaran.");
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error updating payment detail: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Membatalkan sebuah transaksi pembayaran.
     * Ini akan mengubah status_pembayaran_detail menjadi 'Dibatalkan' dan
     * memperbarui sisa tagihan pada tagihan_siswa yang terkait.
     *
     * @param int $id ID detail pembayaran yang akan dibatalkan.
     * @return array Hasil operasi (status, message).
     */
    public function cancelPayment($id) {
        $this->pdo->beginTransaction();
        try {
            $current_detail = $this->getById($id);
            if (!$current_detail) {
                throw new Exception("Detail pembayaran tidak ditemukan.");
            }
            if ($current_detail['status_pembayaran_detail'] === 'Dibatalkan') {
                $this->pdo->rollBack();
                return ['status' => 'error', 'message' => 'Transaksi sudah dibatalkan sebelumnya.'];
            }

            // Update status detail pembayaran menjadi 'Dibatalkan'
            $query_update_detail = "UPDATE " . $this->table_name . " SET status_pembayaran_detail = 'Dibatalkan', updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $stmt_update_detail = $this->pdo->prepare($query_update_detail);
            $stmt_update_detail->bindParam(':id', $id, PDO::PARAM_INT);
            if (!$stmt_update_detail->execute()) {
                throw new Exception("Gagal memperbarui status detail pembayaran.");
            }

            // Hitung ulang total pembayaran sah untuk tagihan ini
            $tagihan_siswa_id = $current_detail['tagihan_siswa_id'];
            $tagihan_siswa_model = new TagihanSiswa($this->pdo);
            $total_dibayar_terkini = $this->getTotalPaidForTagihan($tagihan_siswa_id);

            // Update sisa tagihan dan status tagihan di tabel tagihan_siswa
            if ($tagihan_siswa_model->updateSisaTagihanAndStatus($tagihan_siswa_id, $total_dibayar_terkini)) {
                $this->pdo->commit();
                return ['status' => 'success', 'message' => 'Transaksi pembayaran berhasil dibatalkan.'];
            } else {
                throw new Exception("Gagal memperbarui sisa tagihan di tabel tagihan_siswa setelah pembatalan.");
            }
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error cancelling payment: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Terjadi kesalahan saat membatalkan transaksi: ' . $e->getMessage()];
        }
    }
}
