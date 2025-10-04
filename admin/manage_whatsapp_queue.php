<?php
// project-root/admin/manage_whatsapp_queue.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/WhatsappQueueManager.php';
require_once __DIR__ . '/../classes/NotificationQueue.php';

// Pastikan pengguna sudah login dan memiliki peran Admin
require_login();
require_role('Admin');

$error_message = flash('error');
$success_message = flash('success');

$whatsapp_queue_manager = null;
$pdo = null;

$list_notifications = [];
$total_notifications = 0;
$limit = 20; // Jumlah notifikasi per halaman
$page = (int)($_GET['page'] ?? 1);
$offset = ($page - 1) * $limit;

$filter_status = $_GET['status'] ?? 'all';
$search_term = $_GET['search'] ?? '';

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $whatsapp_queue_manager = new WhatsappQueueManager($pdo);
    
    // --- PENANGANAN AKSI BARU ---
    if (isset($_GET['action']) && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        if ($_GET['action'] === 'resend') {
            if ($whatsapp_queue_manager->resendNotification($id)) {
                set_flash('success', 'Notifikasi berhasil diantrekan ulang untuk dikirim.');
            } else {
                set_flash('error', 'Gagal mengantrekan ulang notifikasi.');
            }
        } elseif ($_GET['action'] === 'delete') {
            if ($whatsapp_queue_manager->deleteNotification($id)) {
                set_flash('success', 'Notifikasi berhasil dihapus dari antrean.');
            } else {
                set_flash('error', 'Gagal menghapus notifikasi.');
            }
        }
        // Redirect untuk menghindari pengiriman ulang form
        header('Location: ' . ROOT_URL . 'admin/manage_whatsapp_queue.php?status=' . urlencode($filter_status) . '&page=' . $page . '&search=' . urlencode($search_term));
        exit;
    }
    // --- AKHIR PENANGANAN AKSI BARU ---
    
    $list_notifications = $whatsapp_queue_manager->getNotificationsPaginated($limit, $offset, $filter_status, $search_term);
    $total_notifications = $whatsapp_queue_manager->countNotifications($filter_status, $search_term);

} catch (Exception $e) {
    error_log("Error in manage_whatsapp_queue: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan: ' . $e->getMessage());
}

$total_pages = ceil($total_notifications / $limit);

require_once __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manajemen Antrean Notifikasi WhatsApp</h1>

    <?php if ($error_message): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
        </div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <form action="" method="GET" class="space-y-4 md:flex md:gap-4 items-end">
            <div class="flex-1">
                <label for="status" class="block text-sm font-medium text-gray-700">Filter Status</label>
                <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                    <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Semua</option>
                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="sent" <?php echo $filter_status === 'sent' ? 'selected' : ''; ?>>Terkirim</option>
                    <option value="failed" <?php echo $filter_status === 'failed' ? 'selected' : ''; ?>>Gagal</option>
                </select>
            </div>
            <div class="flex-1">
                <label for="search" class="block text-sm font-medium text-gray-700">Cari</label>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="No. HP, Template, Error" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
            </div>
            <div class="flex-shrink-0">
                <button type="submit" class="w-full md:w-auto px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Terapkan
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold mb-4">Daftar Notifikasi (Total: <?php echo $total_notifications; ?>)</h2>
        <?php if (empty($list_notifications)): ?>
            <p class="text-gray-600">Tidak ada notifikasi yang ditemukan.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto">
                    <thead>
                        <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">ID</th>
                            <th class="py-3 px-6 text-left">Penerima</th>
                            <th class="py-3 px-6 text-left">Template</th>
                            <th class="py-3 px-6 text-left">Status</th>
                            <th class="py-3 px-6 text-left">Pesan Error</th>
                            <th class="py-3 px-6 text-left">Percobaan</th>
                            <th class="py-3 px-6 text-left">Dibuat</th>
                            <th class="py-3 px-6 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm">
                        <?php foreach ($list_notifications as $notif): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars($notif['id']); ?></td>
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars($notif['recipient_phone_number']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($notif['template_name']); ?></td>
                                <td class="py-3 px-6 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full
                                        <?php echo ($notif['status'] === 'sent') ? 'bg-green-200 text-green-800' : ''; ?>
                                        <?php echo ($notif['status'] === 'pending') ? 'bg-yellow-200 text-yellow-800' : ''; ?>
                                        <?php echo ($notif['status'] === 'failed') ? 'bg-red-200 text-red-800' : ''; ?>">
                                        <?php echo htmlspecialchars(ucfirst($notif['status'])); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-6">
                                    <?php
                                        $error_message_display = $notif['error_message'] ?? '';
                                        echo htmlspecialchars(substr($error_message_display, 0, 40)) . (strlen($error_message_display) > 40 ? '...' : '');
                                    ?>
                                </td>
                                <td class="py-3 px-6 text-center"><?php echo htmlspecialchars($notif['attempt_count']); ?></td>
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo htmlspecialchars($notif['created_at']); ?></td>
                                <td class="py-3 px-6 text-center whitespace-nowrap">
                                    <?php if ($notif['status'] === 'failed'): ?>
                                        <a href="?action=resend&id=<?php echo htmlspecialchars($notif['id']); ?>&status=<?php echo urlencode($filter_status); ?>&page=<?php echo $page; ?>&search=<?php echo urlencode($search_term); ?>" class="text-blue-600 hover:text-blue-900 mr-3">Resend</a>
                                    <?php endif; ?>
                                    <a href="?action=delete&id=<?php echo htmlspecialchars($notif['id']); ?>&status=<?php echo urlencode($filter_status); ?>&page=<?php echo $page; ?>&search=<?php echo urlencode($search_term); ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Apakah Anda yakin ingin menghapus notifikasi ini? Tindakan ini tidak dapat dibatalkan.');">Hapus</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex justify-between items-center">
                <p class="text-sm text-gray-700">Menampilkan <?php echo count($list_notifications); ?> dari <?php echo $total_notifications; ?> notifikasi.</p>
                <div class="flex items-center space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search_term); ?>" class="px-3 py-1 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Previous</a>
                    <?php endif; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search_term); ?>" class="px-3 py-1 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div id="notification-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center p-4 z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-lg w-full p-6">
            <h3 class="text-xl font-bold mb-4">Detail Notifikasi</h3>
            <div class="space-y-2 text-sm">
                <p><strong>ID:</strong> <span id="modal-id"></span></p>
                <p><strong>Penerima:</strong> <span id="modal-recipient"></span></p>
                <p><strong>Template:</strong> <span id="modal-template"></span></p>
                <p><strong>Parameter:</strong></p>
                <pre class="bg-gray-100 p-2 rounded-md text-gray-700 overflow-x-auto"><code id="modal-params"></code></pre>
                <p><strong>Status:</strong> <span id="modal-status"></span></p>
                <p><strong>Dibuat:</strong> <span id="modal-created"></span></p>
                <p><strong>Diproses:</strong> <span id="modal-processed"></span></p>
                <p><strong>Pesan Error:</strong> <span id="modal-error"></span></p>
            </div>
            <div class="mt-4 flex justify-end">
                <button type="button" class="close-modal-btn px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const detailButtons = document.querySelectorAll('.detail-btn');
    const closeModalButtons = document.querySelectorAll('.close-modal-btn');
    const modal = document.getElementById('notification-modal');

    detailButtons.forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('modal-id').textContent = this.dataset.id;
            document.getElementById('modal-recipient').textContent = this.dataset.recipient;
            document.getElementById('modal-template').textContent = this.dataset.template;
            
            try {
                const params = JSON.parse(this.dataset.params);
                document.getElementById('modal-params').textContent = JSON.stringify(params, null, 2);
            } catch (e) {
                document.getElementById('modal-params').textContent = 'Invalid JSON: ' + this.dataset.params;
            }
            
            document.getElementById('modal-status').textContent = this.dataset.status;
            document.getElementById('modal-created').textContent = this.dataset.created;
            document.getElementById('modal-processed').textContent = this.dataset.processed;
            document.getElementById('modal-error').textContent = this.dataset.error;
            
            modal.classList.remove('hidden');
        });
    });

    closeModalButtons.forEach(button => {
        button.addEventListener('click', function() {
            modal.classList.add('hidden');
        });
    });

    // Close modal when clicking outside of it
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.classList.add('hidden');
        }
    });
});
</script>

<?php
// Sertakan footer
require_once __DIR__ . '/../components/footer.php';
?>