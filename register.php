<?php
// register.php
require_once 'config/koneksi.php';

// Redirect if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    header("Location: index.php");
    exit;
}

$error = "";
$success = "";

if (isset($_POST['register'])) {
    $role = 'karyawan'; // Hardcoded as employees register themselves
    $nama = trim($_POST['nama']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($nama) || empty($username) || empty($password)) {
        $error = "Semua field wajib diisi.";
    } elseif ($password !== $confirm_password) {
        $error = "Konfirmasi kata sandi tidak cocok.";
    } elseif (strlen($password) < 6) {
        $error = "Kata sandi minimal harus 6 karakter.";
    } else {
        try {
            // Check if username already exists in either table
            $exists = false;
            
            $stmt1 = $pdo->prepare("SELECT id_manajer FROM manajer WHERE username = ?");
            $stmt1->execute([$username]);
            if ($stmt1->fetch()) $exists = true;

            $stmt2 = $pdo->prepare("SELECT id_mandor FROM mandor WHERE username = ?");
            $stmt2->execute([$username]);
            if ($stmt2->fetch()) $exists = true;

            $stmt3 = $pdo->prepare("SELECT id_karyawan FROM karyawan WHERE username = ?");
            $stmt3->execute([$username]);
            if ($stmt3->fetch()) $exists = true;

            if ($exists) {
                $error = "Username sudah digunakan. Silakan pilih username lain.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                
                $stmt = $pdo->prepare("INSERT INTO karyawan (username, password, nama, status_aktif) VALUES (?, ?, ?, 1)");
                $stmt->execute([$username, $hashed_password, $nama]);
                
                $success = "Registrasi berhasil! Silakan masuk ke dalam sistem.";
            }
        } catch (\PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran - Agrotamex Productivity</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom Style Sheet -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-wrapper">
        <div class="auth-card glass-panel" style="max-width: 520px;">
            <div class="auth-header">
                <i class="fa-solid fa-user-plus" style="font-size: 3rem; color: #6ec088; margin-bottom: 15px; filter: drop-shadow(0 0 10px rgba(110,192,136,0.3));"></i>
                <h2>Registrasi Akun Baru</h2>
                <p>Silakan buat akun Karyawan Lapangan</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <!-- Hardcoded employee role hidden field -->
                <input type="hidden" name="role" value="karyawan">

                <div class="form-group">
                    <label for="nama"><i class="fa-solid fa-signature"></i> Nama Lengkap</label>
                    <input type="text" name="nama" id="nama" class="form-control" placeholder="Contoh: Budi Santoso" required autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="username"><i class="fa-solid fa-user"></i> Username</label>
                    <input type="text" name="username" id="username" class="form-control" placeholder="Gunakan huruf kecil & angka" required autocomplete="off">
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label for="password"><i class="fa-solid fa-key"></i> Kata Sandi</label>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Min. 6 karakter" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password"><i class="fa-solid fa-shield-halved"></i> Konfirmasi</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Ulangi sandi" required>
                    </div>
                </div>

                <button type="submit" name="register" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                    <i class="fa-solid fa-user-check"></i> Daftar Sekarang
                </button>
            </form>

            <div style="margin-top: 25px; font-size: 0.85rem; color: var(--text-muted);">
                Sudah memiliki akun? <a href="login.php" style="color: var(--primary); font-weight: 600;">Masuk di Sini</a>
            </div>
        </div>
    </div>
</body>
</html>
