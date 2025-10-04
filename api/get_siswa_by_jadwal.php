<?php
// project-root/api/get_siswa_by_jadwal.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Siswa.php';

$response = [];

try {
    // Pastikan parameter jadwal_id ada dan merupakan angka
    if (!isset($_GET['jadwal_id']) || !is_numeric($_GET['jadwal_id'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid jadwal_id provided.']);
        exit;
    }

    $jadwal_id = (int)$_GET['jadwal_id'];
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    

    // Query untuk mengambil siswa_id dari tabel jadwal_siswa berdasarkan jadwal_id
    $sql = "SELECT siswa_id FROM jadwal_siswa WHERE jadwal_id = :jadwal_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':jadwal_id', $jadwal_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $siswa_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // Mengirimkan array siswa_id dalam format JSON
    echo json_encode($siswa_ids);

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    error_log("Database error in get_siswa_by_jadwal.php: " . $e->getMessage());
    echo json_encode(['error' => 'A database error occurred.']);
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    error_log("General error in get_siswa_by_jadwal.php: " . $e->getMessage());
    echo json_encode(['error' => 'An unexpected error occurred.']);
} catch (Exception $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}