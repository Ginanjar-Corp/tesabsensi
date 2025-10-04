<?php
// project-root/admin/manage_users.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Role.php';
require_once __DIR__ . '/../classes/Guru.php'; // Untuk dropdown terkait guru
require_once __DIR__ . '/../classes/Siswa.php'; // Untuk dropdown terkait siswa

// Pastikan pengguna sudah login dan memiliki peran Admin
require_login();
require_role('Admin'); // Hanya Admin yang bisa mengelola pengguna

$error_message = flash('error');
$success_message = flash('success');

$user_model = null;
$role_model = null;
$guru_model = null;
$siswa_model = null;
$pdo = null;

$all_users = [];
$all_roles = [];
$all_guru = [];
$all_siswa = [];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $user_model = new User($pdo);
    $role_model = new Role($pdo);
    $guru_model = new Guru($pdo);
    $siswa_model = new Siswa($pdo);

    $all_users = $user_model->getAll();
    $all_roles = $role_model->getAll();
    $all_guru = $guru_model->getAll();
    $all_siswa = $siswa_model->getFilteredSiswa('', 'Aktif');

    // Handle form submission for Add/Edit/Delete
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            $id = (int)($_POST['id'] ?? 0);

            // --- VALIDASI HANYA UNTUK TAMBAH DAN EDIT ---
            if ($action == 'add' || $action == 'edit') {
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? null;
                $email = trim($_POST['email'] ?? '');
                $role_id = (int)($_POST['role_id'] ?? 0);
                $related_id = (int)($_POST['related_id'] ?? 0);
                if ($related_id === 0) $related_id = null;
                $is_active = isset($_POST['is_active']) ? 1 : 0;

                // Basic validation for Add/Edit
                if (empty($username) || empty($email) || empty($role_id)) {
                    set_flash('error', 'Username, Email, dan Peran harus diisi.');
                    redirect(ROOT_URL . 'admin/manage_users.php');
                    exit();
                }

                // Check if username already exists for new user or different user on edit
                $existing_user_by_username = $user_model->findByUsername($username);
                if ($existing_user_by_username && ($action == 'add' || ($action == 'edit' && $existing_user_by_username['id'] != $id))) {
                    set_flash('error', 'Username sudah digunakan. Pilih username lain.');
                    redirect(ROOT_URL . 'admin/manage_users.php');
                    exit();
                }

                // Additional validation for 'add'
                if ($action == 'add' && empty($password)) {
                    set_flash('error', 'Password harus diisi untuk pengguna baru.');
                    redirect(ROOT_URL . 'admin/manage_users.php');
                    exit();
                }

                // --- LOGIKA TAMBAH/EDIT SETELAH VALIDASI ---
                if ($action == 'add') {
                    if ($user_model->create($username, $password, $email, $role_id, $related_id, $is_active)) {
                        set_flash('success', 'Pengguna berhasil ditambahkan.');
                    } else {
                        set_flash('error', 'Gagal menambahkan pengguna.');
                    }
                } elseif ($action == 'edit' && $id > 0) {
                    if ($user_model->update($id, $username, $password, $email, $role_id, $related_id, $is_active)) {
                        set_flash('success', 'Pengguna berhasil diperbarui.');
                    } else {
                        set_flash('error', 'Gagal memperbarui pengguna.');
                    }
                }
            } elseif ($action == 'delete' && $id > 0) {
                // --- LOGIKA HAPUS (tanpa validasi username, email, dll.) ---
                // Pencegahan: Jangan biarkan admin menghapus akunnya sendiri
                if ($id == $_SESSION['user_id']) {
                    set_flash('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
                    redirect(ROOT_URL . 'admin/manage_users.php');
                    exit();
                }
                if ($user_model->delete($id)) {
                    set_flash('success', 'Pengguna berhasil dihapus.');
                } else {
                    set_flash('error', 'Gagal menghapus pengguna.');
                }
            }

            redirect(ROOT_URL . 'admin/manage_users.php');
        }
    }
} catch (PDOException $e) {
    error_log("Manage Users Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
    redirect(ROOT_URL . 'admin/manage_users.php');
} catch (Exception $e) {
    error_log("Manage Users General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
    redirect(ROOT_URL . 'admin/manage_users.php');
}

// Sertakan header
require_once __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manajemen Pengguna</h1>

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

    <!-- Form Tambah/Edit Pengguna -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-semibold mb-4" id="form-title">Tambah Pengguna Baru</h2>
        <form id="userForm" action="<?php echo ROOT_URL; ?>admin/manage_users.php" method="POST">
            <input type="hidden" name="action" id="form-action" value="add">
            <input type="hidden" name="id" id="user-id" value="">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Username:</label>
                    <input type="text" id="username" name="username" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                <div>
                    <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password (isi untuk mengubah):</label>
                    <input type="password" id="password" name="password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="Biarkan kosong jika tidak diubah">
                </div>
                <div class="md:col-span-2">
                    <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email:</label>
                    <input type="email" id="email" name="email" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                <div>
                    <label for="role_id" class="block text-gray-700 text-sm font-bold mb-2">Peran (Role):</label>
                    <select id="role_id" name="role_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required onchange="toggleRelatedIdDropdown()">
                        <option value="">Pilih Peran</option>
                        <?php foreach ($all_roles as $role): ?>
                            <option value="<?php echo htmlspecialchars($role['id']); ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="related_id_container" class="hidden">
                    <label for="related_id" class="block text-gray-700 text-sm font-bold mb-2">Terhubung ke (Guru/Siswa):</label>
                    <select id="related_id" name="related_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="">Tidak Terhubung</option>
                        <optgroup label="Guru">
                            <?php foreach ($all_guru as $guru): ?>
                                <option value="<?php echo htmlspecialchars($guru['id']); ?>" data-role-name="Guru"><?php echo htmlspecialchars($guru['nama_lengkap']); ?> (NIP: <?php echo htmlspecialchars($guru['nip'] ?? '-'); ?>)</option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Siswa">
                            <?php foreach ($all_siswa as $siswa): ?>
                                <option value="<?php echo htmlspecialchars($siswa['id']); ?>" data-role-name="Siswa"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?> (NISN: <?php echo htmlspecialchars($siswa['nisn'] ?? '-'); ?>)</option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Status Aktif:</label>
                    <input type="checkbox" id="is_active" name="is_active" class="mr-2 leading-tight" checked>
                    <label for="is_active" class="text-sm text-gray-700">Aktif</label>
                </div>
            </div>

            <div class="flex items-center justify-between mt-6">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Simpan Pengguna
                </button>
                <button type="button" id="cancelEdit" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline hidden">
                    Batal Edit
                </button>
            </div>
        </form>
    </div>

    <!-- Daftar Pengguna -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold mb-4">Daftar Pengguna</h2>
        <?php if (empty($all_users)): ?>
            <p class="text-gray-600">Belum ada data pengguna.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                    <thead>
                        <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">No.</th>
                            <th class="py-3 px-6 text-left">Username</th>
                            <th class="py-3 px-6 text-left">Email</th>
                            <th class="py-3 px-6 text-left">Peran</th>
                            <th class="py-3 px-6 text-left">Terhubung ke</th>
                            <th class="py-3 px-6 text-center">Status</th>
                            <th class="py-3 px-6 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm">
                        <?php $no = 1; ?>
                        <?php foreach ($all_users as $user): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-6 whitespace-nowrap"><?php echo $no++; ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($user['username']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($user['role_name']); ?></td>
                                <td class="py-3 px-6">
                                    <?php
                                    $related_name = '-';
                                    if ($user['related_id']) {
                                        if ($user['role_name'] == 'Guru') {
                                            // Perbaikan: Menggunakan anonymous function
                                            $found_guru = array_filter($all_guru, function($g) use ($user) {
                                                return $g['id'] == $user['related_id'];
                                            });
                                            $related_name = !empty($found_guru) ? reset($found_guru)['nama_lengkap'] : 'Guru tidak ditemukan';
                                        } elseif ($user['role_name'] == 'Siswa') {
                                            // Perbaikan: Menggunakan anonymous function
                                            $found_siswa = array_filter($all_siswa, function($s) use ($user) {
                                                return $s['id'] == $user['related_id'];
                                            });
                                            $related_name = !empty($found_siswa) ? reset($found_siswa)['nama_lengkap'] : 'Siswa tidak ditemukan';
                                        }
                                    }
                                    echo htmlspecialchars($related_name);
                                    ?>
                                </td>
                                <td class="py-3 px-6 text-center">
                                    <span class="px-2 py-1 font-semibold leading-tight rounded-full <?php echo $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $user['is_active'] ? 'Aktif' : 'Tidak Aktif'; ?>
                                    </span>
                                </td>
                                <td class="py-3 px-6 text-center whitespace-nowrap">
                                    <button type="button" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)" class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-1 px-3 rounded text-xs mr-2">Edit</button>
                                    <form action="<?php echo ROOT_URL; ?>admin/manage_users.php" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pengguna ini?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                        <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-3 rounded text-xs" <?php echo ($user['id'] == $_SESSION['user_id']) ? 'disabled' : ''; ?>>Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    const allRoles = <?php echo json_encode($all_roles); ?>;
    const allGuru = <?php echo json_encode($all_guru); ?>;
    const allSiswa = <?php echo json_encode($all_siswa); ?>;

    function toggleRelatedIdDropdown() {
        const roleId = document.getElementById('role_id').value;
        const relatedIdContainer = document.getElementById('related_id_container');
        const relatedIdSelect = document.getElementById('related_id');

        // Clear existing options except the "Tidak Terhubung" one
        relatedIdSelect.innerHTML = '<option value="">Tidak Terhubung</option>';

        const selectedRole = allRoles.find(role => role.id == roleId);

        if (selectedRole && (selectedRole.role_name === 'Guru' || selectedRole.role_name === 'Siswa')) {
            relatedIdContainer.classList.remove('hidden');
            if (selectedRole.role_name === 'Guru') {
                const optgroupGuru = document.createElement('optgroup');
                optgroupGuru.label = 'Guru';
                allGuru.forEach(guru => {
                    const option = document.createElement('option');
                    option.value = guru.id;
                    option.textContent = `${guru.nama_lengkap} (NIP: ${guru.nip || '-'})`;
                    option.dataset.roleName = 'Guru';
                    optgroupGuru.appendChild(option);
                });
                relatedIdSelect.appendChild(optgroupGuru);
            } else if (selectedRole.role_name === 'Siswa') {
                const optgroupSiswa = document.createElement('optgroup');
                optgroupSiswa.label = 'Siswa';
                allSiswa.forEach(siswa => {
                    const option = document.createElement('option');
                    option.value = siswa.id;
                    option.textContent = `${siswa.nama_lengkap} (NISN: ${siswa.nisn || '-'})`;
                    option.dataset.roleName = 'Siswa';
                    optgroupSiswa.appendChild(option);
                });
                relatedIdSelect.appendChild(optgroupSiswa);
            }
        } else {
            relatedIdContainer.classList.add('hidden');
            relatedIdSelect.value = ''; // Reset selection
        }
    }

    function editUser(userData) {
        document.getElementById('form-title').textContent = 'Edit Data Pengguna';
        document.getElementById('form-action').value = 'edit';
        document.getElementById('user-id').value = userData.id;
        document.getElementById('username').value = userData.username;
        document.getElementById('email').value = userData.email;
        document.getElementById('role_id').value = userData.role_id;
        document.getElementById('is_active').checked = userData.is_active == 1;

        // Populate related_id dropdown based on selected role
        toggleRelatedIdDropdown();
        if (userData.related_id) {
            // Give a small delay to ensure options are populated before setting value
            setTimeout(() => {
                document.getElementById('related_id').value = userData.related_id;
            }, 50);
        } else {
            document.getElementById('related_id').value = '';
        }

        document.getElementById('cancelEdit').classList.remove('hidden');
        document.getElementById('password').placeholder = 'Biarkan kosong jika tidak diubah';
        window.scrollTo({ top: 0, behavior: 'smooth' }); // Scroll to top to see the form
    }

    document.getElementById('cancelEdit').addEventListener('click', function() {
        document.getElementById('form-title').textContent = 'Tambah Pengguna Baru';
        document.getElementById('form-action').value = 'add';
        document.getElementById('user-id').value = '';
        document.getElementById('userForm').reset(); // Reset form fields
        document.getElementById('is_active').checked = true; // Default to active for new
        document.getElementById('password').placeholder = ''; // Clear placeholder for new user
        document.getElementById('cancelEdit').classList.add('hidden');
        toggleRelatedIdDropdown(); // Reset related_id dropdown state
    });

    // Initial call to set correct state on page load
    document.addEventListener('DOMContentLoaded', toggleRelatedIdDropdown);
</script>

<?php
// Sertakan footer
require_once __DIR__ . '/../components/footer.php';
?>
