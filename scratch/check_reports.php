<?php
require_once '../config/koneksi.php';
$stmt = $pdo->query("SELECT id, id_karyawan, foto_bukti, status FROM work_reports");
while ($row = $stmt->fetch()) {
    echo "ID: " . $row['id'] . " | Karyawan: " . $row['id_karyawan'] . " | Status: " . $row['status'] . " | Foto: " . $row['foto_bukti'] . "\n";
}
