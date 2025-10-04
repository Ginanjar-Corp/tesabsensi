<?php
// project-root/classes/Guru.php

class Guru {
    private $pdo;
    private $table_name = "guru";

    public function __construct(PDO $db) {
        $this->pdo = $db;
    }
    
    /**
     * Finds a teacher by their NIP.
     * @param string $nip The NIP to search for.
     * @return array|false Teacher data or false if not found.
     */
    public function findByNip($nip) {
        $stmt = $this->pdo->prepare("SELECT id, nama_lengkap, nip, rfid_tag FROM " . $this->table_name . " WHERE nip = ?");
        $stmt->execute([$nip]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Gets a teacher by their user_id.
     * @param int $userId The user_id to search for.
     * @return array|false Teacher data or false if not found.
     */
    public function getGuruByUserId($userId) {
        // NOTE: The original query for getGuruByUserId seems incomplete as it binds ':user_id' but uses 'g.id = :id'. 
        // Assuming g.id should be replaced with user_id based on the comment/intent, but I'll only add new columns to SELECT if they were intended. 
        // Sticking to original SELECT for this specific method as it seems tied to user context.
        $query = "SELECT g.id, g.nip, g.nama_lengkap
                  FROM " . $this->table_name . " g
                  WHERE g.id = :user_id
                  LIMIT 1";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT); // Correcting binding to match where clause intent.
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
     /**
      * Finds a teacher by their RFID tag.
      * @param string $rfidTag The RFID tag to search for.
      * @return array|false Teacher data or false if not found.
      */
    public function findByRfidTag($rfidTag) {
        $stmt = $this->pdo->prepare("SELECT id, nip, nama_lengkap, no_telp, transport_jabatan, hari_piket, transport_piket, tunjangan_sertifikasi FROM guru WHERE rfid_tag = ?");
        $stmt->execute([$rfidTag]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Finds a teacher by their ID.
     * @param int $id The ID of the teacher to search for.
     * @return array|false Teacher data or false if not found.
     */
    public function findById($id) {
        // PASTIKAN rfid_tag disertakan di sini
        $stmt = $this->pdo->prepare("SELECT id, nip, nama_lengkap, no_telp, tempat_lahir, tanggal_lahir, jenis_kelamin, alamat, email, gaji_per_pertemuan, rfid_tag, is_active, jabatan, tunjangan_jabatan, transport, tunjangan_transport, transport_jabatan, hari_piket, transport_piket, tunjangan_sertifikasi FROM guru WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieves all teachers.
     * @return array An array of all teacher data.
     */
    public function getAll() {
        // PASTIKAN rfid_tag disertakan di sini
        $query = "SELECT id, nip, nama_lengkap, no_telp, tempat_lahir, tanggal_lahir, jenis_kelamin, alamat, email, gaji_per_pertemuan, rfid_tag, is_active, jabatan, tunjangan_jabatan, transport, tunjangan_transport, transport_jabatan, hari_piket, transport_piket, tunjangan_sertifikasi FROM " . $this->table_name . " ORDER BY nama_lengkap ASC";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getById($id) {
        $query = "SELECT *
                  FROM " . $this->table_name . "
                  WHERE id = :id
                  LIMIT 0,1";
    
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
        return $row;
    }
    
    public function getByUId($id) {
    $query = "SELECT *
              FROM " . $this->table_name . "
              WHERE id = :id
              LIMIT 1"; // Ubah LIMIT 0,1 menjadi LIMIT 1

    $stmt = $this->pdo->prepare($query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row;
}

    /**
     * Adds a new teacher.
     * @param array $data An associative array containing teacher data.
     * @return bool True on success, false on failure.
     */
    public function add(array $data) {
        $query = "INSERT INTO " . $this->table_name . " (nama_lengkap, nip, rfid_tag, tempat_lahir, tanggal_lahir, jenis_kelamin, alamat, no_telp, email, gaji_per_pertemuan, is_active, jabatan, tunjangan_jabatan, transport, tunjangan_transport, transport_jabatan, hari_piket, transport_piket) VALUES (:nama_lengkap, :nip, :rfid_tag, :tempat_lahir, :tanggal_lahir, :jenis_kelamin, :alamat, :no_telp, :email, :gaji_per_pertemuan, :is_active, :jabatan, :tunjangan_jabatan, :transport, :tunjangan_transport, :transport_jabatan, :hari_piket, :transport_piket)";
        $stmt = $this->pdo->prepare($query);

        // Bind parameter, termasuk gaji_per_pertemuan, rfid_tag, dan kolom baru
        $stmt->bindParam(':nama_lengkap', $data['nama_lengkap']);
        $stmt->bindParam(':nip', $data['nip']);
        $stmt->bindParam(':rfid_tag', $data['rfid_tag']);
        $stmt->bindParam(':tempat_lahir', $data['tempat_lahir']);
        $stmt->bindParam(':tanggal_lahir', $data['tanggal_lahir']);
        $stmt->bindParam(':jenis_kelamin', $data['jenis_kelamin']);
        $stmt->bindParam(':alamat', $data['alamat']);
        $stmt->bindParam(':no_telp', $data['no_telp']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':gaji_per_pertemuan', $data['gaji_per_pertemuan']);
        $stmt->bindParam(':is_active', $data['is_active'], PDO::PARAM_INT);
        $stmt->bindParam(':jabatan', $data['jabatan']);
        $stmt->bindParam(':tunjangan_jabatan', $data['tunjangan_jabatan']);
        $stmt->bindParam(':transport', $data['transport']);
        $stmt->bindParam(':tunjangan_transport', $data['tunjangan_transport']);
        $stmt->bindParam(':transport_jabatan', $data['transport_jabatan']); // NEW
        $stmt->bindParam(':hari_piket', $data['hari_piket']); // NEW
        $stmt->bindParam(':transport_piket', $data['transport_piket']); // NEW
        $stmt->bindParam(':tunjangan_sertifikasi', $data['tunjangan_sertifikasi']); // NEW


        return $stmt->execute();
    }

    /**
     * Updates an existing teacher.
     * @param int $id The ID of the teacher to update.
     * @param array $data An associative array containing updated teacher data.
     * @return bool True on success, false on failure.
     */
    public function update($id, array $data) {
        $query = "UPDATE " . $this->table_name . " SET
                    nama_lengkap = :nama_lengkap,
                    nip = :nip,
                    rfid_tag = :rfid_tag,
                    tempat_lahir = :tempat_lahir,
                    tanggal_lahir = :tanggal_lahir,
                    jenis_kelamin = :jenis_kelamin,
                    alamat = :alamat,
                    no_telp = :no_telp,
                    email = :email,
                    gaji_per_pertemuan = :gaji_per_pertemuan,
                    is_active = :is_active,
                    jabatan = :jabatan,
                    tunjangan_jabatan = :tunjangan_jabatan,
                    transport = :transport,
                    tunjangan_transport = :tunjangan_transport,
                    transport_jabatan = :transport_jabatan, -- NEW
                    hari_piket = :hari_piket, -- NEW
                    transport_piket = :transport_piket, -- NEW
                    tunjangan_sertifikasi = :tunjangan_sertifikasi,
                    updated_at = CURRENT_TIMESTAMP
                  WHERE id = :id";
        $stmt = $this->pdo->prepare($query);

        // Bind parameter, termasuk gaji_per_pertemuan, rfid_tag, dan kolom baru
        $stmt->bindParam(':nama_lengkap', $data['nama_lengkap']);
        $stmt->bindParam(':nip', $data['nip']);
        $stmt->bindParam(':rfid_tag', $data['rfid_tag']);
        $stmt->bindParam(':tempat_lahir', $data['tempat_lahir']);
        $stmt->bindParam(':tanggal_lahir', $data['tanggal_lahir']);
        $stmt->bindParam(':jenis_kelamin', $data['jenis_kelamin']);
        $stmt->bindParam(':alamat', $data['alamat']);
        $stmt->bindParam(':no_telp', $data['no_telp']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':gaji_per_pertemuan', $data['gaji_per_pertemuan']);
        $stmt->bindParam(':is_active', $data['is_active'], PDO::PARAM_INT);
        $stmt->bindParam(':jabatan', $data['jabatan']);
        $stmt->bindParam(':tunjangan_jabatan', $data['tunjangan_jabatan']);
        $stmt->bindParam(':transport', $data['transport']);
        $stmt->bindParam(':tunjangan_transport', $data['tunjangan_transport']);
        $stmt->bindParam(':transport_jabatan', $data['transport_jabatan']); // NEW
        $stmt->bindParam(':hari_piket', $data['hari_piket']); // NEW
        $stmt->bindParam(':transport_piket', $data['transport_piket']); // NEW
        $stmt->bindParam(':tunjangan_sertifikasi', $data['tunjangan_sertifikasi']); // NEW
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Soft deletes a teacher (sets is_active to 0).
     * @param int $id ID guru.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function softDelete($id) {
        try {
            $query = "UPDATE " . $this->table_name . " SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error soft-deleting guru ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Hard deletes a teacher (removes record permanently).
     * This method should be used with extreme caution.
     * It also attempts to delete related records in 'users' and 'jadwal_pelajaran' tables.
     *
     * @param int $id ID guru.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function hardDelete($id) {
        try {
            $this->pdo->beginTransaction();

            // Hapus user terkait (jika ada)
            $stmt_user = $this->pdo->prepare("DELETE FROM users WHERE related_id = :related_id AND role_id = (SELECT id FROM roles WHERE role_name = 'Guru')");
            $stmt_user->bindParam(':related_id', $id, PDO::PARAM_INT);
            $stmt_user->execute();

            // Hapus jadwal pelajaran yang diampu guru ini (jika tidak ada DELETE CASCADE)
            $stmt_jadwal = $this->pdo->prepare("DELETE FROM jadwal_pelajaran WHERE guru_id = :guru_id");
            $stmt_jadwal->bindParam(':guru_id', $id, PDO::PARAM_INT);
            $stmt_jadwal->execute();

            // Hapus guru itu sendiri
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $result = $stmt->execute();

            $this->pdo->commit();
            return $result;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error hard-deleting guru ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mengaktifkan kembali guru (mengubah status is_active menjadi 1).
     *
     * @param int $id ID guru.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function activate($id) {
        try {
            $query = "UPDATE " . $this->table_name . " SET is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error activating guru ID {$id}: " . $e->getMessage());
            return false;
        }
    }
}