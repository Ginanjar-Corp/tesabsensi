<?php
// project-root/classes/WhatsappQueueManager.php

class WhatsappQueueManager {
    private $pdo;
    private $table_name = "whatsapp_notification_queue";

    public function __construct(PDO $db) {
        $this->pdo = $db;
    }

    public function getNotificationsPaginated($limit, $offset, $status_filter = 'all', $search_term = '') {
        $sql = "SELECT * FROM " . $this->table_name;
        $conditions = [];
        $params = [];

        if ($status_filter !== 'all') {
            $conditions[] = "status = :status_filter";
            $params[':status_filter'] = $status_filter;
        }

        if (!empty($search_term)) {
            $conditions[] = "(recipient_phone_number LIKE :search_term OR template_name LIKE :search_term_template OR error_message LIKE :search_term_error)";
            $params[':search_term'] = "%" . $search_term . "%";
            $params[':search_term_template'] = "%" . $search_term . "%";
            $params[':search_term_error'] = "%" . $search_term . "%";
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function countNotifications($status_filter = 'all', $search_term = '') {
        $sql = "SELECT COUNT(*) FROM " . $this->table_name;
        $conditions = [];
        $params = [];

        if ($status_filter !== 'all') {
            $conditions[] = "status = :status_filter";
            $params[':status_filter'] = $status_filter;
        }

        if (!empty($search_term)) {
            $conditions[] = "(recipient_phone_number LIKE :search_term OR template_name LIKE :search_term_template OR error_message LIKE :search_term_error)";
            $params[':search_term'] = "%" . $search_term . "%";
            $params[':search_term_template'] = "%" . $search_term . "%";
            $params[':search_term_error'] = "%" . $search_term . "%";
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        return $stmt->fetchColumn(); // Perbaikan di sini
    }

    public function getNotificationById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function resendNotification($id) {
        try {
            $query = "UPDATE " . $this->table_name . " SET status = 'pending', processed_at = NULL, error_message = NULL, attempt_count = 0 WHERE id = :id";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error resending notification: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteNotification($id) {
        try {
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error deleting notification: " . $e->getMessage());
            return false;
        }
    }
}