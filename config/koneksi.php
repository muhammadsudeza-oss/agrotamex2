<?php
// config/koneksi.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$db   = 'db_agrotamex'; // Nama database Anda
$user = 'root';        // Username MySQL default XAMPP
$pass = '';            // Password MySQL default XAMPP (kosong)
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Jika database belum dibuat/diimpor, coba hubungkan ke server saja 
    // agar script setup.php dapat dijalankan untuk membuat database otomatis
    try {
        $pdo = new PDO("mysql:host=$host;charset=$charset", $user, $pass, $options);
        $current_script = basename($_SERVER['PHP_SELF']);
        if ($current_script !== 'setup.php') {
            $redirect_url = '/agrotamex1/config/setup.php';
            header("Location: $redirect_url");
            exit;
        }
    } catch (\PDOException $e_server) {
        die("Koneksi ke database gagal: " . $e_server->getMessage());
    }
}
?>
