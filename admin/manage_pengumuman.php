<?php
// admin/manage_pengumuman.php (Updated)

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/Kelas.php';
require_once __DIR__ . '/../classes/Pengumuman.php';

require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$siswa_model = null;
$kelas_model = null;
$pengumuman_model = null;
$all_siswa = [];
$all_kelas = [];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $siswa_model = new Siswa($pdo);
    $kelas_model = new Kelas($pdo);
    $pengumuman_model = new Pengumuman($pdo);

    $all_siswa = $siswa_model->getAll();
    $all_kelas = $kelas_model->getAll();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $jenis_penerima = $_POST['jenis_penerima'] ?? null;
        $target_ids = $_POST['target_ids'] ?? null; // Perbarui ini untuk mengambil array
        $judul = trim($_POST['judul'] ?? '');
        $pesan = trim($_POST['pesan'] ?? '');

        if (empty($jenis_penerima) || empty($pesan)) {
            set_flash('error', 'Judul dan Isi Pengumuman harus diisi.');
        } else {
            // Perbarui ini untuk melewati array IDs
            $result = $pengumuman_model->kirimPengumuman($jenis_penerima, $judul, $pesan, $target_ids);
            if ($result['status'] === 'success' || $result['status'] === 'warning') {
                set_flash('success', $result['message']);
            } else {
                set_flash('error', $result['message']);
            }
        }
        header('Location: ' . ROOT_URL . 'admin/manage_pengumuman.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Pengumuman Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Pengumuman General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
}

require_once __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Kirim Pengumuman</h1>

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
        <form action="" method="POST" class="space-y-6">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Pilih Penerima:</label>
                <div class="mt-2 space-y-2">
                    <label class="inline-flex items-center">
                        <input type="radio" name="jenis_penerima" value="umum" class="form-radio text-indigo-600" checked onchange="toggleTargets()">
                        <span class="ml-2 text-gray-700">Umum (Semua Siswa)</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="radio" name="jenis_penerima" value="kelas" class="form-radio text-indigo-600" onchange="toggleTargets()">
                        <span class="ml-2 text-gray-700">Per Kelas</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="radio" name="jenis_penerima" value="perorangan" class="form-radio text-indigo-600" onchange="toggleTargets()">
                        <span class="ml-2 text-gray-700">Perorangan</span>
                    </label>
                </div>
            </div>

            <div id="target_kelas_div" class="hidden">
                <label for="target_kelas" class="block text-gray-700 text-sm font-bold mb-2">Pilih Kelas:</label>
                <select id="target_kelas" name="target_ids" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">-- Pilih Kelas --</option>
                    <?php foreach ($all_kelas as $kelas): ?>
                        <option value="<?php echo htmlspecialchars($kelas['id']); ?>"><?php echo htmlspecialchars($kelas['nama_kelas']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="target_siswa_div" class="hidden">
                <label for="target_siswa" class="block text-gray-700 text-sm font-bold mb-2">Pilih Siswa:</label>
                <select id="target_siswa" name="target_ids[]" multiple class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">-- Pilih Siswa --</option>
                    <?php foreach ($all_siswa as $siswa): ?>
                        <option value="<?php echo htmlspecialchars($siswa['id']); ?>"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?> (NISN: <?php echo htmlspecialchars($siswa['nisn'] ?? '-'); ?>)</option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Tekan `Ctrl` (Windows) atau `Cmd` (Mac) untuk memilih lebih dari satu siswa.</p>
            </div>
            
            <div>
                <label for="judul" class="block text-gray-700 text-sm font-bold mb-2">Judul Pengumuman:</label>
                <input type="text" id="judul" name="judul" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>

            <div>
                <label for="pesan" class="block text-gray-700 text-sm font-bold mb-2">Isi Pengumuman:</label>
                <textarea id="pesan" name="pesan" rows="6" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required></textarea>
            </div>

            <div class="flex items-center">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                    Kirim Pengumuman
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleTargets() {
        const jenisPenerima = document.querySelector('input[name="jenis_penerima"]:checked').value;
        const targetKelasDiv = document.getElementById('target_kelas_div');
        const targetSiswaDiv = document.getElementById('target_siswa_div');

        targetKelasDiv.classList.add('hidden');
        targetSiswaDiv.classList.add('hidden');
        
        // Atur ulang nama input untuk menghindari duplikasi
        document.getElementById('target_kelas').setAttribute('name', 'target_ids');
        document.getElementById('target_siswa').setAttribute('name', 'target_ids[]');

        if (jenisPenerima === 'kelas') {
            targetKelasDiv.classList.remove('hidden');
        } else if (jenisPenerima === 'perorangan') {
            targetSiswaDiv.classList.remove('hidden');
        }
    }

    // Set initial state
    toggleTargets();
</script>

<?php require_once __DIR__ . '/../components/footer.php'; ?>