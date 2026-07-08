<?php
// config/setup.php
require_once 'koneksi.php';

$message = "";
$status = "";

if (isset($_POST['setup'])) {
    try {
        // 1. Create Database if not exists
        $pdo->exec("CREATE DATABASE IF NOT EXISTS db_agrotamex CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE db_agrotamex");

        // 2. Drop existing tables to ensure clean setup
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $pdo->exec("DROP TABLE IF EXISTS work_reports;");
        $pdo->exec("DROP TABLE IF EXISTS assignments;");
        $pdo->exec("DROP TABLE IF EXISTS karyawan;");
        $pdo->exec("DROP TABLE IF EXISTS mandor;");
        $pdo->exec("DROP TABLE IF EXISTS manajer;");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        // 3. Create Tables
        
        // Table Manajer
        $pdo->exec("CREATE TABLE manajer (
            id_manajer INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            nama VARCHAR(100) NOT NULL
        ) ENGINE=InnoDB;");

        // Table Mandor
        $pdo->exec("CREATE TABLE mandor (
            id_mandor INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            nama VARCHAR(100) NOT NULL,
            spesialisasi ENUM('Pemanenan', 'Penyemprotan', 'Pemupukan') NOT NULL
        ) ENGINE=InnoDB;");

        // Table Karyawan
        $pdo->exec("CREATE TABLE karyawan (
            id_karyawan INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            nama VARCHAR(100) NOT NULL,
            status_aktif TINYINT DEFAULT 1
        ) ENGINE=InnoDB;");

        // Table Assignments
        $pdo->exec("CREATE TABLE assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NOT NULL,
            aktivitas VARCHAR(50) NOT NULL,
            target_jumlah DECIMAL(10,2) NOT NULL,
            unit VARCHAR(20) NOT NULL,
            id_mandor INT NOT NULL,
            id_karyawan INT NOT NULL,
            id_manajer INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_mandor) REFERENCES mandor(id_mandor) ON DELETE CASCADE,
            FOREIGN KEY (id_karyawan) REFERENCES karyawan(id_karyawan) ON DELETE CASCADE,
            FOREIGN KEY (id_manajer) REFERENCES manajer(id_manajer) ON DELETE CASCADE
        ) ENGINE=InnoDB;");

        // Table Work Reports
        $pdo->exec("CREATE TABLE work_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_assignment INT NOT NULL,
            id_karyawan INT NOT NULL,
            jumlah_realisasi DECIMAL(10,2) NOT NULL,
            foto_bukti VARCHAR(255) NULL,
            catatan_karyawan TEXT NULL,
            status ENUM('pending_mandor', 'verified_by_mandor', 'approved', 'rejected', 'pending_manajer_tolak') DEFAULT 'pending_mandor',
            catatan_mandor TEXT NULL,
            tanggal_verifikasi_mandor DATETIME NULL,
            catatan_manajer TEXT NULL,
            tanggal_verifikasi_manajer DATETIME NULL,
            bonus_diterima DECIMAL(15,2) DEFAULT 0.00,
            potongan_penalti DECIMAL(5,2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_assignment) REFERENCES assignments(id) ON DELETE CASCADE,
            FOREIGN KEY (id_karyawan) REFERENCES karyawan(id_karyawan) ON DELETE CASCADE
        ) ENGINE=InnoDB;");

        // 4. Seed Dummy Data
        $pass_hash = password_hash('password123', PASSWORD_BCRYPT);

        // Insert Manajer
        $stmt = $pdo->prepare("INSERT INTO manajer (username, password, nama) VALUES (?, ?, ?)");
        $stmt->execute(['manajer', $pass_hash, 'Junior Estate Manager (JEM)']);

        // Insert Mandor (spesialisasi: Pemanenan, Penyemprotan, Pemupukan)
        $stmt = $pdo->prepare("INSERT INTO mandor (username, password, nama, spesialisasi) VALUES (?, ?, ?, ?)");
        $stmt->execute(['mandor1', $pass_hash, 'Mandor Budi', 'Pemanenan']);
        $stmt->execute(['mandor2', $pass_hash, 'Mandor Slamet', 'Pemupukan']);
        $stmt->execute(['mandor3', $pass_hash, 'Mandor Roni', 'Penyemprotan']);

        // Insert Karyawan
        $stmt = $pdo->prepare("INSERT INTO karyawan (username, password, nama, status_aktif) VALUES (?, ?, ?, ?)");
        $stmt->execute(['karyawan1', $pass_hash, 'Agung B.', 1]);
        $stmt->execute(['karyawan2', $pass_hash, 'Endra P.', 1]);
        $stmt->execute(['karyawan3', $pass_hash, 'Feri I.', 1]);
        $stmt->execute(['karyawan4', $pass_hash, 'Herman', 1]);
        $stmt->execute(['karyawan5', $pass_hash, 'Ismail', 1]);

        // Insert Sample Assignments
        // Yesterday's targets
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $today = date('Y-m-d');
        
        // Penugasan Kemarin
        $stmt = $pdo->prepare("INSERT INTO assignments (tanggal, aktivitas, target_jumlah, unit, id_mandor, id_karyawan, id_manajer) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        // Mandor 1 (Pemanenan) supervises Karyawan 1 & 2 for Pemanenan (yesterday)
        $stmt->execute([$yesterday, 'Pemanenan', 1.50, 'Ton', 1, 1, 1]); // ID assignment 1
        $stmt->execute([$yesterday, 'Pemanenan', 1.50, 'Ton', 1, 2, 1]); // ID assignment 2
        
        // Mandor 2 (Pemupukan) supervises Karyawan 3 for Pemupukan (yesterday)
        $stmt->execute([$yesterday, 'Pemupukan', 10.00, 'Karung', 2, 3, 1]); // ID assignment 3

        // Penugasan Hari Ini
        $stmt->execute([$today, 'Pemanenan', 1.50, 'Ton', 1, 1, 1]); // ID assignment 4
        $stmt->execute([$today, 'Penyemprotan', 2.00, 'Hektar', 3, 4, 1]); // ID assignment 5 (Mandor 3 = Penyemprotan)
        $stmt->execute([$today, 'Pemupukan', 12.00, 'Karung', 2, 5, 1]); // ID assignment 6

        // Insert Sample Work Reports for Yesterday
        // Report 1: Karyawan 1 (Panen yesterday) - Fully approved
        $stmt = $pdo->prepare("INSERT INTO work_reports (id_assignment, id_karyawan, jumlah_realisasi, foto_bukti, catatan_karyawan, status, catatan_mandor, tanggal_verifikasi_mandor, catatan_manajer, tanggal_verifikasi_manajer) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            1, 1, 1.65, 'assets/uploads/dummy_panen1.jpg', 'Kemarin cuaca cerah, hasil panen melimpah.', 'approved', 
            'Pekerjaan bagus, buah sawit bersih dan matang.', date('Y-m-d H:i:s', strtotime('-1 day 4 hours')),
            'Disetujui. Pertahankan performa kerja!', date('Y-m-d H:i:s', strtotime('-1 day 5 hours'))
        ]);

        // Report 2: Karyawan 2 (Panen yesterday) - Verified by Mandor, pending Manager
        $stmt->execute([
            2, 2, 1.40, 'assets/uploads/dummy_panen2.jpg', 'Target kurang sedikit karena mobil traksi terlambat.', 'verified_by_mandor',
            'Fisik buah sudah sesuai takaran timbangan.', date('Y-m-d H:i:s', strtotime('-1 day 3 hours')),
            null, null
        ]);

        // Report 3: Karyawan 3 (Pemupukan yesterday) - Still pending Mandor
        $stmt->execute([
            3, 3, 10.00, 'assets/uploads/dummy_pemupukan1.jpg', 'Pemupukan Blok B selesai tepat waktu.', 'pending_mandor',
            null, null, null, null
        ]);

        // Create uploads directory if not exists
        if (!file_exists('../assets/uploads')) {
            mkdir('../assets/uploads', 0777, true);
        }
        
        // Copy dummy images to assets/uploads
        // We will create simple placeholder text images or write simple scripts to generate them
        file_put_contents('../assets/uploads/dummy_panen1.jpg', 'dummy panen content');
        file_put_contents('../assets/uploads/dummy_panen2.jpg', 'dummy panen content');
        file_put_contents('../assets/uploads/dummy_pemupukan1.jpg', 'dummy pupuk content');

        $status = "success";
        $message = "Database 'db_agrotamex' dan data dummy berhasil diinisialisasi!";
    } catch (\PDOException $e) {
        $status = "danger";
        $message = "Gagal melakukan inisialisasi database: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Database - Agrotamex</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0b150f;
            --primary: #24693d;
            --primary-light: #8bd9a2;
            --gold: #cba135;
            --text-color: #e0eee3;
            --card-bg: rgba(18, 41, 27, 0.45);
            --card-border: rgba(139, 217, 162, 0.15);
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: radial-gradient(circle at top right, #12291b, #070d0a);
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            width: 100%;
            background: var(--card-bg);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            text-align: center;
        }
        h1 {
            color: var(--primary-light);
            font-size: 2rem;
            margin-bottom: 10px;
            text-shadow: 0 0 10px rgba(139,217,162,0.3);
        }
        p {
            font-weight: 300;
            color: #b2c9b7;
            margin-bottom: 30px;
        }
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 0.95rem;
            text-align: left;
        }
        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid #28a745;
            color: #72db8c;
        }
        .alert-danger {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid #dc3545;
            color: #f57f8a;
        }
        .btn-setup {
            background: linear-gradient(135deg, var(--primary), #1b4d2c);
            color: #fff;
            border: 1px solid var(--primary-light);
            padding: 14px 28px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(36, 105, 61, 0.4);
            text-decoration: none;
            display: inline-block;
        }
        .btn-setup:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 217, 162, 0.4);
            background: linear-gradient(135deg, #2a7a47, var(--primary));
        }
        .credentials {
            margin-top: 30px;
            padding: 20px;
            background: rgba(0, 0, 0, 0.25);
            border-radius: 10px;
            border: 1px solid rgba(139, 217, 162, 0.1);
            text-align: left;
        }
        .credentials h3 {
            margin-top: 0;
            color: var(--gold);
            font-size: 1.1rem;
        }
        .credentials ul {
            padding-left: 20px;
            margin-bottom: 0;
            color: #b2c9b7;
            font-size: 0.9rem;
        }
        .credentials li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Inisialisasi Database Agrotamex</h1>
        <p>PT Agrotamex Sumindo Abadi - Sistem Informasi Pemantauan Produktivitas Karyawan</p>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $status; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <button type="submit" name="setup" class="btn-setup">Inisialisasi & Isi Data Contoh</button>
        </form>

        <?php if ($status === "success"): ?>
            <div style="margin-top: 25px;">
                <a href="../login.php" class="btn-setup" style="background: linear-gradient(135deg, var(--gold), #9b7a24); border-color: var(--gold); box-shadow: 0 4px 15px rgba(203, 161, 53, 0.4);">Ke Halaman Login</a>
            </div>
        <?php endif; ?>

        <div class="credentials">
            <h3>Akun Demo default (Password: <code>password123</code>):</h3>
            <ul>
                <li><strong>Manajer:</strong> Username: <code>manajer</code></li>
                <li><strong>Mandor:</strong> Username: <code>mandor1</code>, <code>mandor2</code>, <code>mandor3</code></li>
                <li><strong>Karyawan:</strong> Username: <code>karyawan1</code>, <code>karyawan2</code>, <code>karyawan3</code>, <code>karyawan4</code>, <code>karyawan5</code></li>
            </ul>
        </div>
    </div>
</body>
</html>
