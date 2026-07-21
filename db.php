<?php
// db.php - Database Connection & Compatibility Helper
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'db_agrotamex';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Koneksi MySQLi gagal: " . $conn->connect_error);
}

// Auto-create 'monitoring' table if not exists
$create_table_sql = "
CREATE TABLE IF NOT EXISTS monitoring (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL,
    kegiatan VARCHAR(100) NOT NULL,
    output DECIMAL(10,2) NOT NULL DEFAULT 0,
    target DECIMAL(10,2) NOT NULL DEFAULT 0,
    satuan VARCHAR(50) NOT NULL,
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
$conn->query($create_table_sql);
?>
