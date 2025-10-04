<?php
// project-root/classes/NotificationQueue.php

class NotificationQueue {
    private $pdo;
    private $table_name = "whatsapp_notification_queue";

    public function __construct(PDO $db) {
        $this->pdo = $db;
    }

    /**
     * Menambahkan tugas notifikasi ke antrean.
     * @param string $recipientPhoneNumber Nomor telepon penerima (format internasional, misal: "6281234567890" tanpa '+').
     * @param string $templateName Nama template pesan (digunakan sebagai identifikasi internal).
     * @param array $templateParameters Array parameter untuk mengisi pesan.
     * @return array Status dan pesan.
     */
    public function addNotificationToQueue($recipientPhoneNumber, $templateName, $templateParameters = []) {
        try {
            // Pastikan nomor telepon bersih dari '+'
            $recipientPhoneNumber = ltrim($recipientPhoneNumber, '+');
            $templateParametersJson = json_encode($templateParameters);
            
            $query = "INSERT INTO " . $this->table_name . " (recipient_phone_number, template_name, template_parameters_json) VALUES (:phone, :template, :params)";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':phone', $recipientPhoneNumber);
            $stmt->bindParam(':template', $templateName);
            $stmt->bindParam(':params', $templateParametersJson);
            $stmt->execute();
            return ['status' => 'success', 'message' => 'Notifikasi berhasil ditambahkan ke antrean.'];
        } catch (PDOException $e) {
            error_log("Error adding notification to queue: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Gagal menambahkan notifikasi ke antrean: ' . $e->getMessage()];
        }
    }

    /**
     * Mengambil sejumlah tugas notifikasi yang statusnya 'pending'.
     * @param int $limit Jumlah tugas yang akan diambil.
     * @return array Daftar tugas.
     */
    public function getPendingNotifications($limit = 10) {
        // Menggunakan FOR UPDATE untuk mengunci baris yang diambil agar tidak diproses oleh worker lain secara bersamaan
        $query = "SELECT * FROM " . $this->table_name . " WHERE status = 'pending' ORDER BY created_at ASC LIMIT :limit FOR UPDATE";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Memperbarui status tugas notifikasi.
     * @param int $id ID tugas.
     * @param string $status Status baru ('sent' atau 'failed').
     * @param string|null $errorMessage Pesan error jika status 'failed'.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function updateNotificationStatus($id, $status, $errorMessage = null) {
        try {
            $query = "UPDATE " . $this->table_name . " SET status = :status, processed_at = NOW(), error_message = :error_message, attempt_count = attempt_count + 1 WHERE id = :id";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':error_message', $errorMessage);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating notification status for ID {$id}: " . $e->getMessage());
            return false;
        }
    }
}
