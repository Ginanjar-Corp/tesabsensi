<?php
// project-root/admin/settings/download_backup.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../classes/Database.php';

// Hanya izinkan admin untuk mengunduh database
require_login();
require_role(['Admin']);

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Nonaktifkan foreign key checks untuk memastikan unduhan berjalan lancar
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');
    
    // Tentukan nama file
    $filename = 'db_backup_' . date('Y-m-d_H-i-s') . '.sql';

    // Set header HTTP untuk memaksa unduhan file
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Tulis header SQL dump
    echo "-- Database Backup\n";
    echo "-- Dibuat pada " . date('Y-m-d H:i:s') . "\n\n";

    // Ambil daftar semua tabel
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        // Ambil struktur tabel
        $stmt_create = $pdo->prepare("SHOW CREATE TABLE `$table`");
        $stmt_create->execute();
        $create_table = $stmt_create->fetch(PDO::FETCH_ASSOC);
        echo "DROP TABLE IF EXISTS `$table`;\n";
        echo $create_table['Create Table'] . ";\n\n";

        // Ambil data dari tabel
        $stmt_data = $pdo->prepare("SELECT * FROM `$table`");
        $stmt_data->execute();
        $rows = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

        if ($rows) {
            foreach ($rows as $row) {
                // Konversi nilai ke format SQL
                $values = array_map(function($value) use ($pdo) {
                    if ($value === null) {
                        return 'NULL';
                    }
                    return $pdo->quote($value);
                }, array_values($row));

                echo "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
            }
            echo "\n";
        }
    }
    
    // Aktifkan kembali foreign key checks
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');

} catch (PDOException $e) {
    die("Terjadi kesalahan saat membuat backup: " . $e->getMessage());
}

exit();

?>