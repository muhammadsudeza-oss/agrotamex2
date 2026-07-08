<?php
// manajer/karyawan.php
require_once '../config/koneksi.php';
require_once '../includes/header.php';

// Validate role
if ($_SESSION['role'] !== 'manajer') {
    header("Location: ../index.php");
    exit;
}

$error = "";
$success = "";

// 1. Process Insert User (Add Mandor/Manajer)
if (isset($_POST['save_user'])) {
    $action_role = $_POST['action_role']; // 'mandor', 'manajer'
    $nama = trim($_POST['nama']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($nama) || empty($username) || empty($password)) {
        $error = "Nama, Username, dan Kata Sandi wajib diisi.";
    } elseif ($action_role === 'mandor' && empty($_POST['spesialisasi'])) {
        $error = "Spesialisasi Mandor wajib dipilih.";
    } elseif ($action_role !== 'mandor' && $action_role !== 'manajer') {
        $error = "Peran tidak valid. Hanya bisa menambahkan Mandor atau Manajer.";
    } else {
        try {
            // Check if username is already taken by other users in any table
            $exists = false;
            
            $stmt = $pdo->prepare("SELECT id_manajer FROM manajer WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) $exists = true;

            $stmt = $pdo->prepare("SELECT id_mandor FROM mandor WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) $exists = true;

            $stmt = $pdo->prepare("SELECT id_karyawan FROM karyawan WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) $exists = true;

            if ($exists) {
                $error = "Username sudah digunakan. Silakan gunakan username lain.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                if ($action_role === 'manajer') {
                    $stmt = $pdo->prepare("INSERT INTO manajer (nama, username, password) VALUES (?, ?, ?)");
                    $stmt->execute([$nama, $username, $hashed_password]);
                    $success = "Manajer baru berhasil ditambahkan.";
                } elseif ($action_role === 'mandor') {
                    $spesialisasi = $_POST['spesialisasi'];
                    $stmt = $pdo->prepare("INSERT INTO mandor (nama, username, password, spesialisasi) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$nama, $username, $hashed_password, $spesialisasi]);
                    $success = "Mandor baru berhasil ditambahkan.";
                }
            }
        } catch (\PDOException $e) {
            $error = "Gagal memproses data: " . $e->getMessage();
        }
    }
}

// 2. Process Delete User
if (isset($_GET['delete_role']) && isset($_GET['delete_id'])) {
    $del_role = $_GET['delete_role'];
    $del_id = (int)$_GET['delete_id'];
    
    try {
        if ($del_role === 'manajer') {
            if ($del_id === $_SESSION['user_id']) {
                $error = "Anda tidak dapat menghapus akun Manajer Anda sendiri yang sedang aktif.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM manajer WHERE id_manajer = ?");
                $stmt->execute([$del_id]);
                $success = "Manajer berhasil dihapus.";
            }
        } elseif ($del_role === 'mandor') {
            $stmt = $pdo->prepare("DELETE FROM mandor WHERE id_mandor = ?");
            $stmt->execute([$del_id]);
            $success = "Mandor berhasil dihapus.";
        } elseif ($del_role === 'karyawan') {
            $stmt = $pdo->prepare("DELETE FROM karyawan WHERE id_karyawan = ?");
            $stmt->execute([$del_id]);
            $success = "Karyawan berhasil dihapus.";
        }
    } catch (\PDOException $e) {
        $error = "Gagal menghapus pengguna (Pengguna ini mungkin memiliki data penugasan aktif di lapangan): " . $e->getMessage();
    }
}

// 3. Fetch User Lists
try {
    $stmt = $pdo->query("SELECT * FROM manajer ORDER BY nama ASC");
    $managers = $stmt->fetchAll();

    $stmt = $pdo->query("SELECT * FROM mandor ORDER BY nama ASC");
    $foremen = $stmt->fetchAll();

    $stmt = $pdo->query("SELECT * FROM karyawan ORDER BY nama ASC");
    $employees = $stmt->fetchAll();
} catch (\PDOException $e) {
    $error = "Error loading lists: " . $e->getMessage();
}
?>

<div style="margin-bottom: 30px;">
    <a href="index.php" style="color: var(--text-muted); font-size: 0.9rem;"><i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard</a>
    <h2 style="margin-top: 10px;">Manajemen Data Pengguna</h2>
    <p style="color: var(--text-muted);">Kelola data akun Manajer, Mandor Lapangan, dan Karyawan Lapangan</p>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error; ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo $success; ?></div>
<?php endif; ?>

<div class="grid-1-3" style="align-items: start;">
    <!-- Form Panel (Left) - Only Add Mode -->
    <div>
        <div class="card glass-panel">
            <h3 class="card-title">Tambah Pengguna Baru</h3>
            
            <form method="POST" action="karyawan.php">
                <div class="form-group">
                    <label for="action_role">Peran / Role</label>
                    <select name="action_role" id="action_role" class="form-control" required>
                        <option value="mandor">Mandor Lapangan</option>
                        <option value="manajer">Manajer / JEM</option>
                    </select>
                </div>

                <div class="form-group" id="spesialisasi_group">
                    <label for="spesialisasi">Spesialisasi Mandor</label>
                    <select name="spesialisasi" id="spesialisasi" class="form-control">
                        <option value="Pemanenan">Mandor Pemanenan</option>
                        <option value="Penyemprotan">Mandor Penyemprotan</option>
                        <option value="Pemupukan">Mandor Pemupukan</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="nama">Nama Lengkap</label>
                    <input type="text" name="nama" id="nama" class="form-control" placeholder="Contoh: Budi Santoso" required autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" name="username" id="username" class="form-control" placeholder="Gunakan huruf kecil & angka" required autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="password">Kata Sandi</label>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Masukkan kata sandi baru" required>
                </div>

                <button type="submit" name="save_user" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                    <i class="fa-solid fa-user-plus"></i> Daftarkan Pengguna
                </button>
            </form>
            
            <div style="background: rgba(46, 125, 50, 0.05); padding: 15px; border-radius: 8px; margin-top: 20px; font-size: 0.8rem; border: 1px solid var(--card-border);">
                <i class="fa-solid fa-circle-info" style="color: var(--primary); margin-right: 5px;"></i>
                <strong>Catatan:</strong> Sesuai kebijakan sistem, Karyawan Lapangan tidak dapat ditambahkan langsung oleh Manajer. Karyawan harus mendaftar secara mandiri melalui form registrasi di halaman login.
            </div>
        </div>
    </div>

    <!-- Lists Panel (Right) - Delete Only (No Edit) -->
    <div>
        <!-- Karyawan List -->
        <div class="card glass-panel" style="margin-bottom: 25px;">
            <h3 class="card-title">Karyawan Lapangan (<?php echo count($employees); ?>)</h3>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Username</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($emp['nama']); ?></strong></td>
                                <td><code><?php echo htmlspecialchars($emp['username']); ?></code></td>
                                <td>
                                    <?php echo $emp['status_aktif'] == 1 ? '<span class="badge badge-approved" style="font-size:0.7rem;">Aktif</span>' : '<span class="badge badge-pending" style="font-size:0.7rem; background:rgba(198,40,40,0.15); color:#f57f8a; border-color:var(--danger)">Non-aktif</span>'; ?>
                                </td>
                                <td>
                                    <a href="karyawan.php?delete_role=karyawan&delete_id=<?php echo $emp['id_karyawan']; ?>" class="btn btn-danger btn-sm" style="padding: 4px 12px;" onclick="return confirm('Apakah Anda yakin ingin menghapus akun karyawan ini? Semua data histori kerja bersangkutan juga akan ikut terhapus.')"><i class="fa-solid fa-trash-can"></i> Hapus</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mandor List -->
        <div class="card glass-panel" style="margin-bottom: 25px;">
            <h3 class="card-title">Mandor Lapangan (<?php echo count($foremen); ?>)</h3>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Username</th>
                            <th>Spesialisasi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($foremen as $mnd): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($mnd['nama']); ?></strong></td>
                                <td><code><?php echo htmlspecialchars($mnd['username']); ?></code></td>
                                <td><span class="badge badge-verified" style="font-size:0.7rem;"><?php echo htmlspecialchars($mnd['spesialisasi']); ?></span></td>
                                <td>
                                    <a href="karyawan.php?delete_role=mandor&delete_id=<?php echo $mnd['id_mandor']; ?>" class="btn btn-danger btn-sm" style="padding: 4px 12px;" onclick="return confirm('Apakah Anda yakin ingin menghapus akun mandor ini?')"><i class="fa-solid fa-trash-can"></i> Hapus</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Manajer List -->
        <div class="card glass-panel">
            <h3 class="card-title">Manajer (<?php echo count($managers); ?>)</h3>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Username</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($managers as $man): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($man['nama']); ?></strong></td>
                                <td><code><?php echo htmlspecialchars($man['username']); ?></code></td>
                                <td>
                                    <?php if ($man['id_manajer'] !== $_SESSION['user_id']): ?>
                                        <a href="karyawan.php?delete_role=manajer&delete_id=<?php echo $man['id_manajer']; ?>" class="btn btn-danger btn-sm" style="padding: 4px 12px;" onclick="return confirm('Apakah Anda yakin ingin menghapus akun manajer ini?')"><i class="fa-solid fa-trash-can"></i> Hapus</a>
                                    <?php else: ?>
                                        <span style="opacity:0.5; font-size:0.8rem; font-style:italic; color: var(--primary);">Akun Anda (Aktif)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('action_role').addEventListener('change', function() {
    const specGroup = document.getElementById('spesialisasi_group');
    const specSelect = document.getElementById('spesialisasi');
    if (this.value === 'mandor') {
        specGroup.style.display = 'block';
        specSelect.setAttribute('required', 'required');
    } else {
        specGroup.style.display = 'none';
        specSelect.removeAttribute('required');
    }
});
</script>
<?php require_once '../includes/footer.php'; ?>
