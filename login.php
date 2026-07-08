<?php
// login.php
require_once 'config/koneksi.php';

// Redirect if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    header("Location: index.php");
    exit;
}

$error = "";

if (isset($_POST['login'])) {
    $role = $_POST['role'];
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($role) || empty($username) || empty($password)) {
        $error = "Semua field harus diisi.";
    } else {
        try {
            $user = null;
            $id_column = "";
            
            if ($role === 'manajer') {
                $stmt = $pdo->prepare("SELECT * FROM manajer WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                $id_column = 'id_manajer';
            } elseif ($role === 'mandor') {
                $stmt = $pdo->prepare("SELECT * FROM mandor WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                $id_column = 'id_mandor';
            } elseif ($role === 'karyawan') {
                $stmt = $pdo->prepare("SELECT * FROM karyawan WHERE username = ? AND status_aktif = 1");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                $id_column = 'id_karyawan';
            }

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user[$id_column];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['role'] = $role;

                header("Location: index.php");
                exit;
            } else {
                $error = "Username atau password salah (atau akun tidak aktif).";
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
    <title>Masuk - Agrotamex Productivity</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom Style Sheet -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-wrapper">
        <div class="auth-card glass-panel">
            <div class="auth-header">
                <i class="fa-solid fa-seedling" style="font-size: 3rem; color: #6ec088; margin-bottom: 15px; filter: drop-shadow(0 0 10px rgba(110,192,136,0.3));"></i>
                <h2>PT AGROTAMEX</h2>
                <p>Pemantauan Produktivitas Karyawan</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="role"><i class="fa-solid fa-user-shield"></i> Peran / Jabatan</label>
                    <select name="role" id="role" class="form-control" required>
                        <option value="">-- Pilih Peran --</option>
                        <option value="manajer">Manajer / Junior Estate Manager</option>
                        <option value="mandor">Mandor Lapangan</option>
                        <option value="karyawan">Karyawan Lapangan</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="username"><i class="fa-solid fa-user"></i> Username</label>
                    <input type="text" name="username" id="username" class="form-control" placeholder="Masukkan username" required autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="password"><i class="fa-solid fa-key"></i> Kata Sandi</label>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Masukkan kata sandi" required>
                </div>

                <button type="submit" name="login" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                    <i class="fa-solid fa-right-to-bracket"></i> Masuk Sistem
                </button>
            </form>

            <div style="margin-top: 25px; font-size: 0.85rem; color: #9bbca5;">
                Belum punya akun? <a href="register.php" style="color: #d4af37; font-weight: 600;">Registrasi di Sini</a>
            </div>
            
            <div style="margin-top: 15px; font-size: 0.8rem;">
                <a href="config/setup.php" style="opacity: 0.7; color: #a5d6a7;"><i class="fa-solid fa-database"></i> Setup/Reset Database</a>
            </div>
        </div>
    </div>
</body>
</html>
