<?php
// project-root/api/export_pembayaran_pdf.php

// Memuat file konfigurasi dan kelas yang diperlukan
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Siswa.php';
require_once __DIR__ . '/../classes/TagihanSiswa.php';
require_once __DIR__ . '/../classes/DetailPembayaranTransaksi.php';
require_once __DIR__ . '/../classes/TahunAjaran.php';
require_once __DIR__ . '/../classes/SchoolSettings.php';

// Memuat library FPDF
// Pastikan FPDF terinstal di vendor/fpdf/fpdf.php
require_once __DIR__ . '/../vendor/fpdf/fpdf.php'; 

// Memastikan pengguna sudah login dan memiliki peran yang sesuai
require_login();
require_role(['Admin', 'TataUsaha']);

class PDF extends FPDF
{
    private $school_logo_path = '';
    private $school_name = '';
    private $school_address = '';
    private $report_title = '';
    private $report_subtitle = '';

    function setHeaderInfo($logo_path, $name, $address, $title, $subtitle) {
        $this->school_logo_path = $logo_path;
        $this->school_name = $name;
        $this->school_address = $address;
        $this->report_title = $title;
        $this->report_subtitle = $subtitle;
    }

    // Page header
    function Header()
    {
        // Logo
        if (!empty($this->school_logo_path) && file_exists(__DIR__ . '/../' . $this->school_logo_path)) {
            $this->Image(__DIR__ . '/../' . $this->school_logo_path, 10, 8, 20); // Path relatif dari root proyek
        } else {
            // Placeholder jika logo tidak ditemukan
            // $this->Rect(10, 8, 20, 20, 'D'); // Gambar kotak sebagai placeholder
            // $this->SetFont('Arial', '', 8);
            // $this->Text(12, 18, 'LOGO');
        }

        // School Name
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 7, $this->school_name, 0, 1, 'C');
        // School Address
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, $this->school_address, 0, 1, 'C');
        
        $this->Ln(5); // Line break

        // Report Title
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 8, $this->report_title, 0, 1, 'C');
        // Report Subtitle
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, $this->report_subtitle, 0, 1, 'C');

        // Line break
        $this->Ln(5);
        // Garis pemisah header
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
    }

    // Page footer
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    // Colored table
    function FancyTable($header, $data)
    {
        // Colors, line width and bold font
        $this->SetFillColor(46, 117, 182); // Blue-ish
        $this->SetTextColor(255);
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(.3);
        $this->SetFont('', 'B', 8);

        // Header
        $w = [10, 30, 40, 40, 20, 30, 20]; // Lebar kolom (No, Tanggal, Siswa, Pembayaran, Periode, Jumlah, Status)
        for ($i = 0; $i < count($header); $i++)
            $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
        $this->Ln();

        // Color and font restoration
        $this->SetFillColor(224, 235, 255);
        $this->SetTextColor(0);
        $this->SetFont('', '', 8);

        // Data
        $fill = false;
        $no = 1;
        foreach ($data as $row) {
            $this->Cell($w[0], 6, $no++, 'LR', 0, 'C', $fill);
            $this->Cell($w[1], 6, format_date_indonesian($row['tanggal_bayar']), 'LR', 0, 'L', $fill);
            $this->Cell($w[2], 6, $row['nama_siswa'], 'LR', 0, 'L', $fill);
            $this->Cell($w[3], 6, $row['nama_pembayaran'], 'LR', 0, 'L', $fill);
            $this->Cell($w[4], 6, $row['periode_tagihan'] ?? '-', 'LR', 0, 'L', $fill);
            $this->Cell($w[5], 6, number_format($row['jumlah_bayar'], 2, ',', '.'), 'LR', 0, 'R', $fill);
            $this->Cell($w[6], 6, $row['status_pembayaran_detail'], 'LR', 0, 'C', $fill);
            $this->Ln();
            $fill = !$fill;
        }
        // Closing line
        $this->Cell(array_sum($w), 0, '', 'T');
    }
}

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $siswa_model = new Siswa($pdo);
    $tagihan_siswa_model = new TagihanSiswa($pdo);
    $detail_pembayaran_transaksi_model = new DetailPembayaranTransaksi($pdo);
    $tahun_ajaran_model = new TahunAjaran($pdo);
    $school_settings_model = new SchoolSettings($pdo);

    $tahun_ajaran_id = $_GET['tahun_ajaran_id'] ?? null;
    $siswa_id = $_GET['siswa_id'] ?? null;

    if (!$tahun_ajaran_id) {
        set_flash('error', 'Tahun Ajaran harus dipilih untuk laporan PDF.');
        redirect(ROOT_URL . 'admin/catat_pembayaran.php'); // Redirect to previous page or a default
    }

    $tahun_ajaran_info = $tahun_ajaran_model->getById($tahun_ajaran_id);
    if (!$tahun_ajaran_info) {
        set_flash('error', 'Tahun Ajaran tidak ditemukan.');
        redirect(ROOT_URL . 'admin/catat_pembayaran.php');
    }

    $filters = ['tahun_ajaran_id' => $tahun_ajaran_id];
    $report_title = 'Laporan Pembayaran Siswa';
    $report_subtitle = 'Tahun Ajaran: ' . htmlspecialchars($tahun_ajaran_info['tahun_ajaran']);

    if ($siswa_id) {
        $siswa_info = $siswa_model->findById($siswa_id);
        if ($siswa_info) {
            $filters['siswa_id'] = $siswa_id;
            $report_title .= ' (Individual)';
            $report_subtitle .= ' | Siswa: ' . htmlspecialchars($siswa_info['nama_lengkap']);
        }
    }

    // Mendapatkan semua detail pembayaran untuk filter yang diberikan
    $all_payments = $detail_pembayaran_transaksi_model->getAllWithTagihanAndSiswa($filters);

    if (empty($all_payments)) {
        set_flash('info', 'Tidak ada data pembayaran untuk kriteria yang dipilih.');
        redirect(ROOT_URL . 'admin/catat_pembayaran.php?siswa_id=' . ($siswa_id ?? ''));
    }

    // Fetch school settings for PDF header
    $school_settings = $school_settings_model->getSettings();
    $school_logo = $school_settings['logo_sekolah_path'] ?? '';
    $school_name = $school_settings['nama_sekolah'] ?? APP_NAME;
    $school_address = $school_settings['alamat_sekolah'] ?? 'Alamat Sekolah';

    $pdf = new PDF();
    $pdf->AliasNbPages(); // Untuk {nb} di footer
    $pdf->AddPage();
    $pdf->SetMargins(10, 10, 10); // Left, Top, Right
    
    // Set header info
    $pdf->setHeaderInfo($school_logo, $school_name, $school_address, $report_title, $report_subtitle);

    // Table header
    $header = ['No.', 'Tanggal Bayar', 'Nama Siswa', 'Jenis Pembayaran', 'Periode', 'Jumlah Bayar', 'Status'];
    $pdf->FancyTable($header, $all_payments);

    // Total Summary
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 10, 'Ringkasan Pembayaran:', 0, 1);
    
    $total_lunas = 0;
    $total_dibatalkan = 0;
    foreach ($all_payments as $payment) {
        if ($payment['status_pembayaran_detail'] === 'Lunas') {
            $total_lunas += $payment['jumlah_bayar'];
        } elseif ($payment['status_pembayaran_detail'] === 'Dibatalkan') {
            $total_dibatalkan += $payment['jumlah_bayar'];
        }
    }

    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 7, 'Total Pembayaran Diterima: Rp ' . number_format($total_lunas, 2, ',', '.'), 0, 1);
    $pdf->Cell(0, 7, 'Total Pembayaran Dibatalkan: Rp ' . number_format($total_dibatalkan, 2, ',', '.'), 0, 1);

    // Output the PDF
    $pdf_filename = 'Laporan_Pembayaran_' . ($siswa_info['nama_lengkap'] ?? 'Semua') . '_' . $tahun_ajaran_info['tahun_ajaran'] . '.pdf';
    $pdf->Output('D', $pdf_filename); // 'D' untuk download

} catch (PDOException $e) {
    error_log("Export Pembayaran PDF Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan database saat membuat laporan PDF: ' . $e->getMessage());
    redirect(ROOT_URL . 'admin/catat_pembayaran.php?siswa_id=' . ($siswa_id ?? ''));
} catch (Exception $e) {
    error_log("Export Pembayaran PDF General Error: " . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan sistem saat membuat laporan PDF: ' . $e->getMessage());
    redirect(ROOT_URL . 'admin/catat_pembayaran.php?siswa_id=' . ($siswa_id ?? ''));
}
?>
