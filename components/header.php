<?php
// project-root/components/header.php
// Header HTML untuk semua halaman publik (sebelum login).
// Hanya menampilkan nama sekolah.

//awal ambil logo

$school_logo_path = '';
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $school_settings_model = new SchoolSettings($pdo);
    $settings = $school_settings_model->getSettings();
    if ($settings && !empty($settings['logo_sekolah_path'])) {
        $school_logo_path = htmlspecialchars($settings['logo_sekolah_path']);
    }
} catch (PDOException $e) {
    // Log error koneksi database, tapi jangan hentikan eksekusi
    error_log("Database connection error in header: " . $e->getMessage());
} catch (Exception $e) {
    // Log error umum, tapi jangan hentikan eksekusi
    error_log("Error fetching school settings in header: " . $e->getMessage());
}

// Tambahkan favicon jika logo sekolah tersedia
if (!empty($school_logo_path)) {
    echo '<link rel="icon" href="' . $school_logo_path . '" type="image/x-icon">';
    echo '<link rel="shortcut icon" href="' . $school_logo_path . '" type="image/x-icon">';
} else {
    // Fallback favicon jika tidak ada logo sekolah (opsional)
    // Anda bisa menunjuk ke ikon default atau tidak menampilkan apa-apa
    // echo '<link rel="icon" href="' . ROOT_URL . 'assets/images/default_favicon.ico" type="image/x-icon">';
}

//akhir ambil logo

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Inter dari Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome untuk ikon (tetap disertakan jika digunakan di bagian lain aplikasi) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6; /* Light gray background */
        }
    </style>
</head>
<body class="min-h-screen flex flex-col">
    <nav class="bg-white p-4 shadow-lg sticky top-0 z-30">
        <div class="container mx-auto flex justify-center items-center">
            <a href="<?php echo ROOT_URL; ?>" class="text-2xl font-extrabold text-blue-700 hover:text-blue-800 transition duration-300">
                <?php echo APP_NAME; ?>
            </a>
        </div>
    </nav>
    <!-- Tag <main> dan konten akan dimulai di file PHP utama (misal: index.php) -->
