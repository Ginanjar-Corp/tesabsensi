<?php
// project-root/admin/daftar_halaqoh.php

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Halaqoh.php';

require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$halaqoh_list = [];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $halaqoh_model = new Halaqoh($pdo);

    $halaqoh_list = $halaqoh_model->getAllWithGuru(); // Anda mungkin perlu membuat metode ini

} catch (Exception $e) {
    $error_message = "Terjadi kesalahan: " . $e->getMessage();
}

require_once __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-white shadow-lg rounded-xl p-6">
        <h1 class="text-2xl font-bold mb-4">Daftar Semua Halaqoh</h1>
        
        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (empty($halaqoh_list)): ?>
            <p class="text-gray-500 italic">Tidak ada halaqoh yang tercatat.</p>
        <?php else: ?>
            <ul class="space-y-4">
                <?php foreach ($halaqoh_list as $halaqoh): ?>
                    <li>
                        <a href="<?php echo ROOT_URL; ?>admin/catat_absensi_halaqoh.php?halaqoh_id=<?php echo htmlspecialchars($halaqoh['id']); ?>" class="flex items-center p-4 bg-purple-500 hover:bg-purple-600 text-white rounded-lg transition duration-300 shadow-md">
                            <i class="fas fa-clipboard-list mr-4 text-xl"></i>
                            <span class="font-medium text-lg"><?php echo htmlspecialchars($halaqoh['nama_halaqoh']); ?> (Guru: <?php echo htmlspecialchars($halaqoh['nama_guru']); ?>)</span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../components/footer.php';
?>