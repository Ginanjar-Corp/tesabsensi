<?php
// project-root/admin/edit_tagihan.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/Kelas.php';
require_once __DIR__ . '/../classes/JenisPembayaran.php';
require_once __DIR__ . '/../classes/TahunAjaran.php';
require_once __DIR__ . '/../classes/TagihanSiswa.php';
require_once __DIR__ . '/../classes/DetailPembayaranTransaksi.php'; // Untuk getTotalPaidForTagihan

// Memastikan pengguna sudah login dan memiliki peran Admin atau Tata Usaha
require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');

$siswa_model = null;
$kelas_model = null;
$jenis_pembayaran_model = null;
$tahun_ajaran_model = null;
$tagihan_siswa_model = null;
$detail_pembayaran_transaksi_model = null;

$tagihan_to_edit = null;
$siswa_detail = null;
$all_jenis_pembayaran = [];
$all_tahun_ajaran = [];
$all_kelas = [];

// Mendapatkan ID tagihan dari URL
$tagihan_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$siswa_id_from_url = filter_input(INPUT_GET, 'siswa_id', FILTER_VALIDATE_INT); // Untuk redirect kembali ke siswa yang benar

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    $siswa_model = new Siswa($pdo);
    $kelas_model = new Kelas($pdo);
    $jenis_pembayaran_model = new JenisPembayaran($pdo);
    $tahun_ajaran_model = new TahunAjaran($pdo);
    $tagihan_siswa_model = new TagihanSiswa($pdo);
    $detail_pembayaran_transaksi_model = new DetailPembayaranTransaksi($pdo);

    $all_jenis_pembayaran = $jenis_pembayaran_model->getAll();
    $all_tahun_ajaran = $tahun_ajaran_model->getAll();
    $all_kelas = $kelas_model->getAll();

    // Handle form submission for update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_tagihan') {
        $tagihan_id = filter_input(INPUT_POST, 'tagihan_id', FILTER_VALIDATE_INT);
        $siswa_id = filter_input(INPUT_POST, 'siswa_id', FILTER_VALIDATE_INT);
        $jenis_pembayaran_id = filter_input(INPUT_POST, 'jenis_pembayaran_id', FILTER_VALIDATE_INT);
        $tahun_ajaran_id = filter_input(INPUT_POST, 'tahun_ajaran_id', FILTER_VALIDATE_INT);
        $kelas_id_saat_tagih = filter_input(INPUT_POST, 'kelas_id_saat_tagih', FILTER_VALIDATE_INT);
        $jumlah_tagihan_baru = (float)filter_input(INPUT_POST, 'jumlah_tagihan', FILTER_VALIDATE_FLOAT);
        $periode_tagihan = filter_input(INPUT_POST, 'periode_tagihan', FILTER_SANITIZE_STRING);
        $tanggal_jatuh_tempo = filter_input(INPUT_POST, 'tanggal_jatuh_tempo', FILTER_SANITIZE_STRING);

        // Ambil data tagihan lama untuk membandingkan jumlah tagihan
        $old_tagihan_data = $tagihan_siswa_model->getById($tagihan_id);

        if ($old_tagihan_data) {
            $total_dibayar_sebelumnya = $detail_pembayaran_transaksi_model->getTotalPaidForTagihan($tagihan_id);
            $sisa_tagihan_baru = $jumlah_tagihan_baru - $total_dibayar_sebelumnya;

            $status_tagihan_baru = 'Belum Lunas';
            if ($sisa_tagihan_baru <= 0) {
                $status_tagihan_baru = 'Lunas';
            } elseif ($total_dibayar_sebelumnya > 0 && $sisa_tagihan_baru > 0) {
                $status_tagihan_baru = 'Sebagian Lunas';
            }

            // Set properti model
            $tagihan_siswa_model->id = $tagihan_id;
            $tagihan_siswa_model->siswa_id = $siswa_id;
            $tagihan_siswa_model->jenis_pembayaran_id = $jenis_pembayaran_id;
            $tagihan_siswa_model->tahun_ajaran_id = $tahun_ajaran_id;
            $tagihan_siswa_model->kelas_id_saat_tagih = $kelas_id_saat_tagih;
            $tagihan_siswa_model->jumlah_tagihan = $jumlah_tagihan_baru;
            $tagihan_siswa_model->sisa_tagihan = $sisa_tagihan_baru; // Update sisa tagihan
            $tagihan_siswa_model->periode_tagihan = $periode_tagihan;
            $tagihan_siswa_model->tanggal_jatuh_tempo = $tanggal_jatuh_tempo;
            $tagihan_siswa_model->status_tagihan = $status_tagihan_baru; // Update status tagihan

            if ($tagihan_siswa_model->update()) {
                set_flash('success', 'Tagihan berhasil diperbarui.');
                redirect(ROOT_URL . 'admin/catat_pembayaran.php?siswa_id=' . $siswa_id . '&tagihan_id=' . $tagihan_id);
            } else {
                set_flash('error', 'Gagal memperbarui tagihan.');
            }
        } else {
            set_flash('error', 'Tagihan tidak ditemukan untuk diperbarui.');
        }
    } 
    // Handle delete action
    else if (isset($_GET['action']) && $_GET['action'] === 'delete_tagihan' && $tagihan_id) {
        $delete_result = $tagihan_siswa_model->delete($tagihan_id);
        if ($delete_result['status'] === 'success') {
            set_flash('success', $delete_result['message']);
            redirect(ROOT_URL . 'admin/catat_pembayaran.php?siswa_id=' . $siswa_id_from_url); // Kembali ke daftar tagihan siswa
        } else {
            set_flash('error', $delete_result['message']);
            redirect(ROOT_URL . 'admin/catat_pembayaran.php?siswa_id=' . $siswa_id_from_url . '&tagihan_id=' . $tagihan_id); // Tetap di halaman edit jika gagal
        }
    }

    // Load tagihan data for the form
    if ($tagihan_id) {
        $tagihan_to_edit = $tagihan_siswa_model->getById($tagihan_id);
        if ($tagihan_to_edit) {
            $siswa_detail = $siswa_model->findById($tagihan_to_edit['siswa_id']);
            if (!$siswa_detail) {
                set_flash('error', 'Detail siswa tidak ditemukan.');
                redirect(ROOT_URL . 'admin/catat_pembayaran.php'); // Redirect jika siswa tidak ditemukan
            }
        } else {
            set_flash('error', 'Tagihan tidak ditemukan.');
            redirect(ROOT_URL . 'admin/catat_pembayaran.php'); // Redirect jika tagihan tidak ditemukan
        }
    } else {
        set_flash('error', 'ID Tagihan tidak valid.');
        redirect(ROOT_URL . 'admin/catat_pembayaran.php');
    }

} catch (PDOException $e) {
    error_log("Edit Tagihan Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
    redirect(ROOT_URL . 'admin/catat_pembayaran.php');
} catch (Exception $e) {
    error_log("Edit Tagihan General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
    redirect(ROOT_URL . 'admin/catat_pembayaran.php');
}

require_once __DIR__ . '/../components/header_admin.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Edit Detail Tagihan Siswa</h1>

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

    <?php if ($tagihan_to_edit && $siswa_detail): ?>
        <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Tagihan untuk: <?php echo htmlspecialchars($siswa_detail['nama_lengkap']); ?> (NISN: <?php echo htmlspecialchars($siswa_detail['nisn']); ?>)</h2>
            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_tagihan">
                <input type="hidden" name="tagihan_id" value="<?php echo htmlspecialchars($tagihan_to_edit['tagihan_id']); ?>">
                <input type="hidden" name="siswa_id" value="<?php echo htmlspecialchars($tagihan_to_edit['siswa_id']); ?>">

                <div>
                    <label for="jenis_pembayaran_id" class="block text-gray-700 text-sm font-bold mb-2">Jenis Pembayaran:</label>
                    <select id="jenis_pembayaran_id" name="jenis_pembayaran_id"
                            class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500" required>
                        <?php foreach ($all_jenis_pembayaran as $jp): ?>
                            <option value="<?php echo htmlspecialchars($jp['id']); ?>"
                                    <?php echo ($tagihan_to_edit['jenis_pembayaran_id'] == $jp['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($jp['nama_pembayaran']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="tahun_ajaran_id" class="block text-gray-700 text-sm font-bold mb-2">Tahun Ajaran:</label>
                    <select id="tahun_ajaran_id" name="tahun_ajaran_id"
                            class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500" required>
                        <?php foreach ($all_tahun_ajaran as $ta): ?>
                            <option value="<?php echo htmlspecialchars($ta['id']); ?>"
                                    <?php echo ($tagihan_to_edit['tahun_ajaran_id'] == $ta['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ta['tahun_ajaran']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="kelas_id_saat_tagih" class="block text-gray-700 text-sm font-bold mb-2">Kelas Saat Tagih:</label>
                    <select id="kelas_id_saat_tagih" name="kelas_id_saat_tagih"
                            class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500" required>
                        <option value="">Pilih Kelas</option>
                        <?php foreach ($all_kelas as $kelas): ?>
                            <option value="<?php echo htmlspecialchars($kelas['id']); ?>"
                                    <?php echo ($tagihan_to_edit['kelas_id_saat_tagih'] == $kelas['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="jumlah_tagihan" class="block text-gray-700 text-sm font-bold mb-2">Jumlah Tagihan (Rp):</label>
                    <input type="number" step="0.01" id="jumlah_tagihan" name="jumlah_tagihan"
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500"
                           value="<?php echo htmlspecialchars($tagihan_to_edit['jumlah_tagihan']); ?>" required>
                </div>

                <div>
                    <label for="periode_tagihan" class="block text-gray-700 text-sm font-bold mb-2">Periode Tagihan (cth: Juli 2024):</label>
                    <input type="text" id="periode_tagihan" name="periode_tagihan"
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500"
                           value="<?php echo htmlspecialchars($tagihan_to_edit['periode_tagihan'] ?? ''); ?>">
                </div>

                <div>
                    <label for="tanggal_jatuh_tempo" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Jatuh Tempo:</label>
                    <input type="date" id="tanggal_jatuh_tempo" name="tanggal_jatuh_tempo"
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500"
                           value="<?php echo htmlspecialchars($tagihan_to_edit['tanggal_jatuh_tempo'] ?? ''); ?>">
                </div>

                <div class="flex items-center space-x-4">
                    <button type="submit"
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                        Perbarui Tagihan
                    </button>
                    <a href="<?php echo ROOT_URL; ?>admin/catat_pembayaran.php?siswa_id=<?php echo htmlspecialchars($tagihan_to_edit['siswa_id']); ?>&tagihan_id=<?php echo htmlspecialchars($tagihan_to_edit['tagihan_id']); ?>"
                       class="inline-block bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                        Batal
                    </a>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
