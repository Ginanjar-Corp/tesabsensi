<?php
class Database {
    private static $instance = null;
    private $pdo;

    // Konstruktor privat untuk mencegah instansiasi langsung
    private function __construct() {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Mode error untuk melempar PDOExceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Mengambil hasil sebagai array asosiatif
            PDO::ATTR_EMULATE_PREPARES   => false,                  // Menonaktifkan emulasi prepared statements (lebih aman)
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE " . DB_COLLATE // Set charset dan collation
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log error secara detail (jangan tampilkan ke pengguna di lingkungan produksi)
            error_log("Koneksi database gagal: " . $e->getMessage());
            // Berhenti eksekusi dan tampilkan pesan umum kepada pengguna
            die("Terjadi masalah pada koneksi database. Silakan coba lagi nanti.");
        }
    }

    // Metode statis untuk mendapatkan satu-satunya instance kelas Database
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    // Metode untuk mendapatkan objek PDO
    public function getConnection() {
        return $this->pdo;
    }

    // Metode untuk mencegah kloning objek
    private function __clone() {}

    // Metode untuk mencegah unserialisasi objek
    public function __wakeup() {
        throw new Exception("Cannot unserialize a singleton.");
    }
}
?>