<?php
// project-root/staff/absensi_gerbang.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/AbsensiGerbang.php';
require_once __DIR__ . '/../classes/Siswa.php'; // Diperlukan untuk cek siswa
require_once __DIR__ . '/../classes/Guru.php';   // Diperlukan untuk cek guru

// Pastikan pengguna sudah login dan memiliki peran Admin atau Tata Usaha
require_login();
require_role(['Admin', 'TataUsaha']);

$error_message = flash('error');
$success_message = flash('success');
$info_message = flash('info');

$absensi_gerbang_model = null;
$siswa_model = null;
$guru_model = null;
$pdo = null;

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $absensi_gerbang_model = new AbsensiGerbang($pdo);
    $siswa_model = new Siswa($pdo);
    $guru_model = new Guru($pdo);

    // Handle form submission (RFID/QR manual input) - This part remains for direct POST if JS fails or is disabled
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['is_ajax_request'])) { // Add a flag to distinguish AJAX
        $rfid_tag = trim($_POST['rfid_tag'] ?? '');
        $tipe_absensi = $_POST['tipe_absensi'] ?? '';
        $jenis_pengguna = $_POST['jenis_pengguna'] ?? '';
        $recorded_by_user_id = $_SESSION['user_id']; // User yang melakukan scan

        if (empty($rfid_tag) || empty($tipe_absensi) || empty($jenis_pengguna)) {
            set_flash('error', 'Semua bidang harus diisi untuk absensi manual.');
            redirect(ROOT_URL . 'staff/absensi_gerbang.php');
        }

        $user_info = null;
        $found_user_type = null;

        if ($jenis_pengguna === 'siswa') {
            $siswa = $siswa_model->findByRfidTag($rfid_tag);
            if ($siswa) {
                $user_info = $siswa;
                $found_user_type = 'siswa';
            }
        } 
        
        if ($jenis_pengguna === 'guru' || ($jenis_pengguna === 'siswa' && !$user_info)) {
            $guru = $guru_model->findByRfidTag($rfid_tag);
            if ($guru) {
                $user_info = $guru;
                $found_user_type = 'guru';
            }
        }

        if ($user_info) {
            require_once __DIR__ . '/../classes/AbsensiHarian.php';
            $absensi_harian_model = new AbsensiHarian($pdo);
            if ($found_user_type === 'siswa') {
                $absensi_result = $absensi_harian_model->recordSiswaAttendanceWithType($user_info['id'], $tipe_absensi);
            } elseif ($found_user_type === 'guru') {
                $absensi_result = $absensi_harian_model->recordGuruAttendanceWithType($user_info['id'], $tipe_absensi);
            }
            if ($absensi_result['status'] === 'success') {
                set_flash('success', $absensi_result['message']);
            } elseif ($absensi_result['status'] === 'warning') {
                set_flash('info', $absensi_result['message']);
            } else {
                set_flash('error', $absensi_result['message']);
            }
        } else {
            set_flash('error', 'RFID/QR Tag tidak terdaftar atau jenis pengguna tidak cocok.');
        }
        redirect(ROOT_URL . 'staff/absensi_gerbang.php');
    }

} catch (PDOException $e) {
    error_log("Absensi Gerbang Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Absensi Gerbang General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
}

// Sertakan header
require_once __DIR__ . '/../components/header_absensi.php';
?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Absensi Gerbang (RFID/QR Code)</h1>

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

    <?php if ($info_message): ?>
        <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($info_message); ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Panel Pemindaian QR Code Webcam -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold mb-4">Pemindaian QR Code (Webcam)</h2>
            <div class="mb-4">
                <label for="jenis_pengguna_qr" class="block text-gray-700 text-sm font-bold mb-2">Jenis Pengguna:</label>
                <select id="jenis_pengguna_qr" name="jenis_pengguna_qr" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="siswa">Siswa</option>
                    <option value="guru">Guru</option>
                </select>
            </div>
            <div class="mb-4">
                <label for="tipe_absensi_qr" class="block text-gray-700 text-sm font-bold mb-2">Tipe Absensi:</label>
                <select id="tipe_absensi_qr" name="tipe_absensi_qr" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="pulang">Pulang</option>
                </select>
            </div>
            <div class="mb-4">
                <label for="camera_select" class="block text-gray-700 text-sm font-bold mb-2">Pilih Kamera:</label>
                <select id="camera_select" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <!-- Options will be populated by JavaScript -->
                </select>
            </div>
            
            <div id="reader" class="w-full bg-gray-200 rounded-lg overflow-hidden flex justify-center items-center" style="height: 300px;">
                <p class="text-gray-500">Memuat pemindai...</p>
            </div>
            <div id="result" class="mt-4 p-3 bg-gray-50 rounded-lg text-gray-800 font-mono break-words hidden"></div>
            <div id="message_qr" class="mt-4 p-3 rounded-lg text-sm hidden"></div>

            <div class="mt-4 flex flex-col space-y-2">
                <button id="startScanBtn" class="bg-indigo-500 hover:bg-indigo-600 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline" disabled>
                    Mulai Pindai
                </button>
                <button id="stopScanBtn" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline hidden">
                    Berhenti Pindai
                </button>
            </div>
        </div>

        <!-- Panel Input Manual RFID/QR Code -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold mb-4">Input Manual RFID/QR Code</h2>
            <div id="message_manual" class="hidden mb-4 p-3 rounded-md text-sm"></div>
            <form id="manualAbsensiForm" action="" method="POST">
                <div class="mb-4">
                    <label for="rfid_tag_manual" class="block text-gray-700 text-sm font-bold mb-2">RFID/QR Tag:</label>
                    <input type="text" id="rfid_tag_manual" name="rfid_tag" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="Masukkan atau tempel tag" required autofocus>
                </div>
                <div class="mb-4">
                    <label for="jenis_pengguna_manual" class="block text-gray-700 text-sm font-bold mb-2">Jenis Pengguna:</label>
                    <select id="jenis_pengguna_manual" name="jenis_pengguna" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        <option value="siswa">Siswa</option>
                        <option value="guru">Guru</option>
                    </select>
                </div>
                <div class="mb-6">
                    <label for="tipe_absensi_manual" class="block text-gray-700 text-sm font-bold mb-2">Tipe Absensi:</label>
                    <select id="tipe_absensi_manual" name="tipe_absensi" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        <option value="pulang">Pulang</option>
                    </select>
                </div>
                <div class="flex justify-end">
                    <button type="submit" id="submitManualBtn" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                        Catat Absensi Manual
                    </button>
                </div>
                <div id="manualResult" class="mt-6 p-4 bg-gray-50 rounded-lg border border-gray-200 hidden">
                    <h3 class="text-xl font-semibold mb-3 text-gray-800">Detail Absensi:</h3>
                    <p class="text-gray-700 mb-2">Nama: <span id="manual-result-nama" class="font-medium"></span></p>
                    <p class="text-gray-700 mb-2">Tipe Pengguna: <span id="manual-result-type" class="font-medium capitalize"></span></p>
                    <p class="text-gray-700 mb-2">Status: <span id="manual-result-status" class="font-medium"></span></p>
                    <p class="text-gray-700 mb-2">Waktu: <span id="manual-result-waktu" class="font-medium"></span></p>
                    <p class="text-gray-700 mb-2 hidden" id="manual-result-kelas-container">Kelas: <span id="manual-result-kelas" class="font-medium"></span></p>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- html5-qrcode library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const html5QrCode = new Html5Qrcode("reader");
    const resultDiv = document.getElementById('result'); // For QR scanner result
    const messageQrDiv = document.getElementById('message_qr'); // For QR scanner messages
    const startScanBtn = document.getElementById('startScanBtn');
    const stopScanBtn = document.getElementById('stopScanBtn');
    const jenisPenggunaQrSelect = document.getElementById('jenis_pengguna_qr');
    const tipeAbsensiQrSelect = document.getElementById('tipe_absensi_qr');
    const cameraSelect = document.getElementById('camera_select');
    const readerDiv = document.getElementById('reader');

    const manualAbsensiForm = document.getElementById('manualAbsensiForm');
    const rfidTagInputManual = document.getElementById('rfid_tag_manual');
    const jenisPenggunaManualSelect = document.getElementById('jenis_pengguna_manual');
    const tipeAbsensiManualSelect = document.getElementById('tipe_absensi_manual');
    const submitManualBtn = document.getElementById('submitManualBtn');
    const messageManualDiv = document.getElementById('message_manual'); // For manual input messages
    const manualResultDiv = document.getElementById('manualResult'); // For manual input result

    let isScanning = false;
    let selectedCameraId = null;

    // Function to display messages in the QR scanner panel
    function showQrMessage(type, message) {
        messageQrDiv.textContent = message;
        messageQrDiv.classList.remove('hidden', 'bg-green-100', 'border-green-400', 'text-green-700', 'bg-red-100', 'border-red-400', 'text-red-700', 'bg-blue-100', 'border-blue-400', 'text-blue-700');
        if (type === 'success') {
            messageQrDiv.classList.add('bg-green-100', 'border-green-400', 'text-green-700');
        } else if (type === 'error') {
            messageQrDiv.classList.add('bg-red-100', 'border-red-400', 'text-red-700');
        } else if (type === 'info') {
            messageQrDiv.classList.add('bg-blue-100', 'border-blue-400', 'text-blue-700');
        }
        messageQrDiv.classList.remove('hidden');
    }

    // Function to clear QR scanner messages and results
    function clearQrDisplay() {
        messageQrDiv.classList.add('hidden');
        resultDiv.classList.add('hidden');
        resultDiv.textContent = '';
    }

    // Function to display messages in the manual input panel
    function showManualMessage(type, message) {
        messageManualDiv.textContent = message;
        messageManualDiv.classList.remove('hidden', 'bg-green-100', 'border-green-400', 'text-green-700', 'bg-red-100', 'border-red-400', 'text-red-700', 'bg-blue-100', 'border-blue-400', 'text-blue-700');
        if (type === 'success') {
            messageManualDiv.classList.add('bg-green-100', 'border-green-400', 'text-green-700');
        } else if (type === 'error') {
            messageManualDiv.classList.add('bg-red-100', 'border-red-400', 'text-red-700');
        } else if (type === 'info') {
            messageManualDiv.classList.add('bg-blue-100', 'border-blue-400', 'text-blue-700');
        }
        messageManualDiv.classList.remove('hidden');
    }

    // Function to clear manual input messages and results
    function clearManualDisplay() {
        messageManualDiv.classList.add('hidden');
        manualResultDiv.classList.add('hidden');
        // Clear inner spans if needed, but they are updated dynamically
    }

    // Populate camera options and attempt to start scanner
    Html5Qrcode.getCameras().then(cameras => {
        if (cameras && cameras.length > 0) {
            cameraSelect.innerHTML = ''; // Clear existing options
            cameras.forEach(camera => {
                const option = document.createElement('option');
                option.value = camera.id;
                // Prioritize 'environment' (back camera) or 'user' (front camera)
                if (camera.label.toLowerCase().includes('back') || camera.label.toLowerCase().includes('environment')) {
                    option.textContent = camera.label || `Kamera Belakang (${camera.id})`;
                } else if (camera.label.toLowerCase().includes('front') || camera.label.toLowerCase().includes('user')) {
                    option.textContent = camera.label || `Kamera Depan (${camera.id})`;
                } else {
                    option.textContent = camera.label || `Kamera ${camera.id}`;
                }
                cameraSelect.appendChild(option);
            });

            // Set default selected camera: prefer back camera if available
            const frontCamera = cameras.find(camera => camera.label.toLowerCase().includes('front') || camera.label.toLowerCase().includes('environment'));
            selectedCameraId = frontCamera ? frontCamera.id : cameras[0].id; // Fallback to first camera
            cameraSelect.value = selectedCameraId; // Update dropdown selection

            readerDiv.innerHTML = '<p class="text-gray-500">Kamera terdeteksi. Tekan "Mulai Pindai" atau tunggu untuk memulai otomatis.</p>';
            startScanBtn.disabled = false; // Enable start button

            // Attempt to start scanner automatically after a short delay
            setTimeout(() => {
                if (selectedCameraId && !isScanning) {
                    startQrCodeScanner(selectedCameraId);
                }
            }, 1000); // 1 second delay
            
        } else {
            readerDiv.innerHTML = '<p class="text-red-500">Tidak ada kamera yang ditemukan.</p>';
            startScanBtn.disabled = true;
            cameraSelect.disabled = true;
            showQrMessage('error', 'Tidak ada kamera yang terdeteksi pada perangkat ini. Pastikan perangkat memiliki kamera dan terhubung.');
        }
    }).catch(err => {
        console.error("Error getting cameras: ", err);
        // Display a more user-friendly error message on the page
        showQrMessage('error', 'Gagal mengakses kamera. Pastikan izin diberikan dan situs diakses melalui HTTPS (jika bukan localhost). Detail: ' + err.message);
        readerDiv.innerHTML = '<p class="text-red-500">Gagal mengakses kamera. Pastikan izin diberikan dan situs diakses melalui HTTPS (jika bukan localhost).</p>';
        startScanBtn.disabled = true;
        cameraSelect.disabled = true;
    });

    // Event listener for camera selection change
    cameraSelect.addEventListener('change', (event) => {
        selectedCameraId = event.target.value;
        if (isScanning) {
            // Restart scan with new camera if already scanning
            stopQrCodeScanner().then(() => {
                startQrCodeScanner(selectedCameraId);
            });
        }
    });

    const qrCodeSuccessCallback = async (decodedText, decodedResult) => {
        if (!isScanning) return; // Prevent processing if scanner is stopped

        // Stop scanning immediately after a successful scan to prevent multiple reads
        await stopQrCodeScanner(); 
        
        resultDiv.textContent = `QR Code Terdeteksi: ${decodedText}`;
        resultDiv.classList.remove('hidden');
        showQrMessage('info', 'Memproses absensi...');

        const jenisPengguna = jenisPenggunaQrSelect.value;
        const tipeAbsensi = tipeAbsensiQrSelect.value;

        // Send data to backend PHP
        try {
            const response = await fetch('<?php echo ROOT_URL; ?>api/absensi_gerbang_process.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `rfid_tag=${encodeURIComponent(decodedText)}&jenis_pengguna=${encodeURIComponent(jenisPengguna)}&tipe_absensi=${encodeURIComponent(tipeAbsensi)}&is_ajax_request=1`
            });
            const data = await response.json();

            if (data.status === 'success') {
                showQrMessage('success', data.message);
                resultDiv.textContent += `\nNama: ${data.nama}, Waktu: ${data.waktu}, Tipe: ${data.user_type}, Status: (data.type === 'pulang' ? 'Pulang' : 'Sudah Absen')}`;
                if (data.user_type === 'siswa' && data.kelas) {
                    resultDiv.textContent += `, Kelas: ${data.kelas}`;
                }
            } else if (data.status === 'info') {
                showQrMessage('info', data.message);
                resultDiv.textContent += `\nNama: ${data.nama}, Waktu: ${data.waktu}, Tipe: ${data.user_type}, Status:  (data.type === 'pulang' ? 'Pulang' : 'Sudah Absen')}`;
                if (data.user_type === 'siswa' && data.kelas) {
                    resultDiv.textContent += `, Kelas: ${data.kelas}`;
                }
            } else {
                showQrMessage('error', data.message);
            }
        } catch (error) {
            console.error('Error sending QR data to backend:', error);
            showQrMessage('error', 'Terjadi kesalahan saat mengirim data ke server.');
        } finally {
            // Re-enable scan button after processing
            startScanBtn.classList.remove('hidden');
            stopScanBtn.classList.add('hidden');
            isScanning = false;
            
            // --- MODIFIKASI: Auto Refresh/Reload Halaman ---
            // Halaman akan di-reload setelah 2 detik untuk memberi waktu pengguna melihat pesan.
            setTimeout(() => {
                location.reload(); 
            }, 2000); 
            // --- AKHIR MODIFIKASI ---
        }
    };

    const qrCodeErrorCallback = (errorMessage) => {
        // console.warn(`QR Code Scan Error: ${errorMessage}`); // Log errors for debugging
        // You might want to show a message only if the error is persistent or critical
        // showQrMessage('error', 'Gagal memindai QR Code. Pastikan QR Code jelas.');
    };

    async function startQrCodeScanner(cameraId) {
        if (!cameraId) {
            showQrMessage('error', 'Pilih kamera terlebih dahulu.');
            return;
        }
        clearQrDisplay();
        readerDiv.innerHTML = ''; // Clear loading text
        try {
            await html5QrCode.start(
                cameraId, 
                { fps: 10, qrbox: { width: 500, height: 500 } }, 
                qrCodeSuccessCallback, 
                qrCodeErrorCallback
            );
            isScanning = true;
            startScanBtn.classList.add('hidden');
            stopScanBtn.classList.remove('hidden');
            showQrMessage('info', 'Pemindai aktif. Arahkan kamera ke QR Code.');
        } catch (err) {
            console.error("Error starting QR Code scanner: ", err);
            showQrMessage('error', 'Gagal memulai pemindai. Pastikan kamera tidak digunakan aplikasi lain dan izin diberikan. Error: ' + err);
            startScanBtn.classList.remove('hidden');
            stopScanBtn.classList.add('hidden'); // Ensure stop button is hidden on failure
            isScanning = false;
        }
    }

    async function stopQrCodeScanner() {
        if (isScanning) {
            try {
                await html5QrCode.stop();
                isScanning = false;
                startScanBtn.classList.remove('hidden');
                stopScanBtn.classList.add('hidden');
                showQrMessage('info', 'Pemindai dihentikan.');
                readerDiv.innerHTML = '<p class="text-gray-500">Pemindai berhenti. Tekan "Mulai Pindai" untuk melanjutkan.</p>';
            } catch (err) {
                console.error("Error stopping QR Code scanner: ", err);
                showQrMessage('error', 'Gagal menghentikan pemindai.');
            }
        }
    }

    startScanBtn.addEventListener('click', () => startQrCodeScanner(selectedCameraId));
    stopScanBtn.addEventListener('click', stopQrCodeScanner);

    // --- Manual RFID/QR Input Logic (using AJAX for automatic processing) ---
    async function processManualAbsensi() {
        const rfidTag = rfidTagInputManual.value.trim();
        const jenisPengguna = jenisPenggunaManualSelect.value;
        const tipeAbsensi = tipeAbsensiManualSelect.value;

        if (!rfidTag) {
            showManualMessage('error', 'RFID/QR Tag tidak boleh kosong.');
            return;
        }

        submitManualBtn.textContent = 'Memproses...';
        submitManualBtn.disabled = true;
        submitManualBtn.classList.add('opacity-50', 'cursor-not-allowed');
        clearManualDisplay();
        manualResultDiv.classList.add('hidden');

        try {
            const response = await fetch('<?php echo ROOT_URL; ?>api/absensi_gerbang_process.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `rfid_tag=${encodeURIComponent(rfidTag)}&jenis_pengguna=${encodeURIComponent(jenisPengguna)}&tipe_absensi=${encodeURIComponent(tipeAbsensi)}&is_ajax_request=1`
            });
            const data = await response.json();

            if (data.status === 'success') {
                showManualMessage('success', data.message);
                displayManualResult(data);
            } else if (data.status === 'info') {
                showManualMessage('info', data.message);
                displayManualResult(data);
            } else {
                showManualMessage('error', data.message);
                manualResultDiv.classList.add('hidden');
            }
        } catch (error) {
            console.error('Error sending RFID data to backend:', error);
            showManualMessage('error', 'Terjadi kesalahan saat mengirim data ke server.');
            manualResultDiv.classList.add('hidden');
        } finally {
            submitManualBtn.textContent = 'Catat Absensi Manual';
            submitManualBtn.disabled = false;
            submitManualBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            rfidTagInputManual.value = ''; // Clear input after processing
            rfidTagInputManual.focus(); // Keep focus for next scan
        }
    }

    function displayManualResult(data) {
        document.getElementById('manual-result-nama').textContent = data.nama;
        document.getElementById('manual-result-type').textContent = data.user_type;
        document.getElementById('manual-result-status').textContent =  (data.type === 'pulang' ? 'Pulang' : 'Sudah Absen');
        document.getElementById('manual-result-waktu').textContent = data.waktu;

        const manualKelasContainer = document.getElementById('manual-result-kelas-container');
        if (data.user_type === 'siswa' && data.kelas) {
            document.getElementById('manual-result-kelas').textContent = data.kelas;
            manualKelasContainer.classList.remove('hidden');
        } else {
            manualKelasContainer.classList.add('hidden');
        }
        manualResultDiv.classList.remove('hidden');
    }

    // Trigger AJAX submission on form submit (for button click)
    manualAbsensiForm.addEventListener('submit', function(e) {
        e.preventDefault();
        processManualAbsensi();
    });

    // Trigger AJAX submission on Enter key press in RFID input field
    rfidTagInputManual.addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault(); // Prevent default form submission
            processManualAbsensi();
        }
    });

    // Ensure RFID input is focused on page load for immediate scanning
    rfidTagInputManual.focus();
});
</script>

<?php
// Sertakan footer
require_once __DIR__ . '/../components/footer.php';
?>
