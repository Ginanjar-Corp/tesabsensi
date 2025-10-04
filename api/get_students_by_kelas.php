<?php
// project-root/api/get_students_by_kelas.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Siswa.php';

$response = ['success' => false, 'data' => [], 'message' => 'Terjadi kesalahan tidak dikenal.'];

try {
    // Pastikan kelas_id ada dan valid
    if (!isset($_GET['kelas_id']) || !is_numeric($_GET['kelas_id'])) {
        $response['message'] = 'Parameter kelas_id tidak valid.';
        echo json_encode($response);
        exit;
    }

    $kelas_id = (int)$_GET['kelas_id'];

    // Coba inisialisasi database dan model
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $siswa_model = new Siswa($pdo);

    // Gunakan metode untuk mendapatkan siswa berdasarkan kelas
    $students = $siswa_model->getStudentsByKelasId($kelas_id);
    
    $response['success'] = true;
    $response['data'] = $students;
    $response['message'] = 'Daftar siswa berhasil diambil.';

} catch (PDOException $e) {
    // Tangani kesalahan database
    error_log("API Error (get_students_by_kelas) PDO: " . $e->getMessage());
    $response['message'] = 'Terjadi kesalahan database saat mengambil siswa.';
} catch (Exception $e) {
    // Tangani kesalahan umum
    error_log("API Error (get_students_by_kelas) General: " . $e->getMessage());
    $response['message'] = 'Terjadi kesalahan sistem saat mengambil siswa.';
}

echo json_encode($response);
?>
