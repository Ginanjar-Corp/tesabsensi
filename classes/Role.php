<?php
// project-root/classes/Role.php

class Role {
    private $pdo;
    private $table_name = "roles";

    public function __construct(PDO $db) {
        $this->pdo = $db;
    }

    /**
     * Mendapatkan semua peran.
     *
     * @return array Array asosiatif dari semua peran.
     */
    public function getAll() {
        $query = "SELECT id, role_name FROM " . $this->table_name;
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mendapatkan ID peran berdasarkan nama peran.
     *
     * @param string $role_name Nama peran.
     * @return int|false ID peran jika ditemukan, false jika tidak.
     */
    public function getIdByName($role_name) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE role_name = :role_name LIMIT 0,1";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':role_name', $role_name);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : false;
    }
}
