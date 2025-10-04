<?php
// project-root/classes/User.php

class User {
    private $pdo;
    private $table_name = "users";

    public function __construct(PDO $db) {
        $this->pdo = $db;
    }

    /**
     * Mendapatkan semua data pengguna dengan nama peran.
     *
     * @return array Array asosiatif dari semua pengguna.
     */
    public function getAll() {
        $query = "SELECT u.id, u.username, u.email, u.role_id, r.role_name, u.related_id, u.is_active, u.created_at, u.updated_at
                  FROM " . $this->table_name . " u
                  JOIN roles r ON u.role_id = r.id
                  ORDER BY u.username ASC";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mendapatkan detail pengguna berdasarkan ID.
     *
     * @param int $id ID pengguna.
     * @return array|false Data pengguna jika ditemukan, false jika tidak.
     */
    public function findById($id) {
        $query = "SELECT u.id, u.username, u.email, u.role_id, r.role_name, u.related_id, u.is_active
                  FROM " . $this->table_name . " u
                  JOIN roles r ON u.role_id = r.id
                  WHERE u.id = :id LIMIT 0,1";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Mendapatkan detail pengguna berdasarkan username.
     * --- PERBAIKAN: Sertakan 'password', 'role_name', dan 'related_id' untuk proses login ---
     *
     * @param string $username Username pengguna.
     * @return array|false Data pengguna jika ditemukan, false jika tidak.
     */
    public function findByUsername($username) {
        $query = "SELECT u.id, u.username, u.email, u.password, u.role_id, r.role_name, u.related_id 
                  FROM " . $this->table_name . " u
                  JOIN roles r ON u.role_id = r.id
                  WHERE u.username = :username LIMIT 0,1";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Membuat pengguna baru.
     *
     * @param string $username
     * @param string $password Kata sandi plain text.
     * @param string $email
     * @param int $role_id
     * @param int|null $related_id ID terkait (guru_id atau siswa_id).
     * @param int $is_active
     * @return bool True jika berhasil, false jika gagal.
     */
    public function create($username, $password, $email, $role_id, $related_id = null, $is_active = 1) {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $query = "INSERT INTO " . $this->table_name . " (username, password, email, role_id, related_id, is_active)
                  VALUES (:username, :password, :email, :role_id, :related_id, :is_active)";
        
        $stmt = $this->pdo->prepare($query);
        
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':role_id', $role_id, PDO::PARAM_INT);
        $stmt->bindParam(':related_id', $related_id, PDO::PARAM_INT);
        $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Memperbarui data pengguna.
     *
     * @param int $id ID pengguna.
     * @param string $username
     * @param string|null $password Kata sandi plain text (opsional, jika ingin diubah).
     * @param string $email
     * @param int $role_id
     * @param int|null $related_id ID terkait (guru_id atau siswa_id).
     * @param int $is_active
     * @return bool True jika berhasil, false jika gagal.
     */
    public function update($id, $username, $password = null, $email, $role_id, $related_id = null, $is_active = 1) {
        $query = "UPDATE " . $this->table_name . " SET
                    username = :username,
                    email = :email,
                    role_id = :role_id,
                    related_id = :related_id,
                    is_active = :is_active,
                    updated_at = CURRENT_TIMESTAMP";
        
        if ($password !== null && $password !== '') {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $query .= ", password = :password";
        }
        
        $query .= " WHERE id = :id";
        
        $stmt = $this->pdo->prepare($query);
        
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':role_id', $role_id, PDO::PARAM_INT);
        $stmt->bindParam(':related_id', $related_id, PDO::PARAM_INT);
        $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        if ($password !== null && $password !== '') {
            $stmt->bindParam(':password', $hashed_password);
        }

        return $stmt->execute();
    }

    /**
     * Menghapus pengguna.
     *
     * @param int $id ID pengguna.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
    
    /**
     * Mendapatkan semua pengguna dengan filter peran.
     * @param array $roles Array nama peran untuk filter (misal: ['Admin', 'TataUsaha']).
     * @return array Array data pengguna.
     */
    public function getAllUsersByRole(array $roles) {
        if (empty($roles)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $query = "SELECT u.id, u.username, r.role_name, u.related_id 
                  FROM " . $this->table_name . " u
                  JOIN roles r ON u.role_id = r.id
                  WHERE r.role_name IN ($placeholders) ORDER BY u.username ASC";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($roles);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
