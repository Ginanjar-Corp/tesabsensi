<?php
// Atur header untuk respons JSON
header('Content-Type: application/json');

// Sertakan file yang diperlukan
require_once '../path/to/Siswa.php'; // Sesuaikan path-nya
require_once '../path/to/Database.php'; // Sesuaikan path-nya

$response = [
    'success' => false,
    'message' => 'Terjadi kesalahan tidak diketahui.'
];

try {
    // Buat instance database dan siswa
    $database = new Database();
    $db = $database->getConnection();
    $siswa = new Siswa($db);

    // Dapatkan data dari permintaan AJAX
    // Gunakan $_GET karena seringnya AJAX request menggunakan GET
    $tipePenerima = isset($_GET['tipe_penerima']) ? $_GET['tipe_penerima'] : '';
    $kelasId = isset($_GET['kelas_id']) ? intval($_GET['kelas_id']) : null;

    $daftarSiswa = [];

    // Logika utama untuk memuat daftar siswa berdasarkan tipe
    if ($tipePenerima === 'Perorangan') {
        // Ambil semua siswa yang valid untuk pengumuman
        $daftarSiswa = $siswa->getAllForAnnouncement();
        $response['success'] = true;
        $response['message'] = 'Daftar siswa berhasil dimuat.';
        $response['data'] = $daftarSiswa;

    } else if ($tipePenerima === 'Kelas' && $kelasId) {
        // Ambil siswa berdasarkan kelas_id
        $daftarSiswa = $siswa->getStudentsByKelasId($kelasId);
        $response['success'] = true;
        $response['message'] = 'Daftar siswa berhasil dimuat.';
        $response['data'] = $daftarSiswa;

    } else {
        // Jika tipe penerima tidak valid atau kelas_id tidak ada
        $response['message'] = 'Tipe penerima tidak valid atau kelas belum dipilih.';
    }

} catch (PDOException $e) {
    // Tangani kesalahan database
    http_response_code(500);
    $response['message'] = 'Gagal memuat daftar siswa. Terjadi masalah database: ' . $e->getMessage();
} catch (Exception $e) {
    // Tangani kesalahan umum
    http_response_code(500);
    $response['message'] = 'Gagal memuat daftar siswa. Terjadi kesalahan: ' . $e->getMessage();
}

// Kirim respons dalam format JSON
echo json_encode($response);
?>
