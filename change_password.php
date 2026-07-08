<?php
// change_password.php
require_once 'config/koneksi.php';
require_once 'includes/header.php';

$error = "";
$success = "";

if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Semua field wajib diisi.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Konfirmasi kata sandi baru tidak cocok.";
    } elseif (strlen($new_password) < 6) {
        $error = "Kata sandi baru minimal harus 6 karakter.";
    } else {
        $user_id = $_SESSION['user_id'];
        $role = $_SESSION['role'];
        
        try {
            $user = null;
            $table = "";
            $id_column = "";

            if ($role === 'manajer') {
                $table = 'manajer';
                $id_column = 'id_manajer';
            } elseif ($role === 'mandor') {
                $table = 'mandor';
                $id_column = 'id_mandor';
            } elseif ($role === 'karyawan') {
                $table = 'karyawan';
                $id_column = 'id_karyawan';
            }

            // Fetch current password hash
            $stmt = $pdo->prepare("SELECT password FROM $table WHERE $id_column = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if ($user && password_verify($current_password, $user['password'])) {
                // Update to new password
                $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt_update = $pdo->prepare("UPDATE $table SET password = ? WHERE $id_column = ?");
                $stmt_update->execute([$new_hash, $user_id]);
                
                $success = "Kata sandi akun Anda berhasil diperbarui.";
            } else {
                $error = "Kata sandi saat ini yang Anda masukkan salah.";
            }
        } catch (\PDOException $e) {
            $error = "Gagal memproses data: " . $e->getMessage();
        }
    }
}
?>

<div style="margin-bottom: 30px;">
    <a href="index.php" style="color: var(--text-muted); font-size: 0.9rem;"><i class="fa-solid fa-arrow-left"></i> Kembali ke Halaman Utama</a>
    <h2 style="margin-top: 10px;">Ubah Kata Sandi Akun</h2>
    <p style="color: var(--text-muted);">Demi keamanan, perbarui kata sandi akun Anda secara berkala</p>
</div>

<div style="max-width: 500px; margin: 0 auto;">
    <div class="card glass-panel">
        <h3 class="card-title"><i class="fa-solid fa-shield-halved" style="color: var(--primary);"></i> Form Ubah Kata Sandi</h3>

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
            <div class="form-group">
                <label for="current_password">Kata Sandi Saat Ini</label>
                <input type="password" name="current_password" id="current_password" class="form-control" required autofocus>
            </div>

            <div class="form-group" style="border-top: 1px solid var(--card-border); padding-top: 15px; margin-top: 15px;">
                <label for="new_password">Kata Sandi Baru</label>
                <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Minimal 6 karakter" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Konfirmasi Kata Sandi Baru</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Masukkan kembali sandi baru" required>
            </div>

            <button type="submit" name="change_password" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                <i class="fa-solid fa-key"></i> Perbarui Kata Sandi
            </button>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
