<?php
// manajer/targets.php
require_once '../config/koneksi.php';
require_once '../includes/header.php';

// Validate role
if ($_SESSION['role'] !== 'manajer') {
    header("Location: ../index.php");
    exit;
}

$manajer_id = $_SESSION['user_id'];
$error = "";
$success = "";

// 1. Fetch Mandor & Karyawan for dropdowns
try {
    $stmt = $pdo->query("SELECT id_mandor, nama, spesialisasi FROM mandor ORDER BY nama ASC");
    $all_mandor = $stmt->fetchAll();

    $stmt = $pdo->query("SELECT id_karyawan, nama FROM karyawan WHERE status_aktif = 1 ORDER BY nama ASC");
    $all_karyawan = $stmt->fetchAll();
} catch (\PDOException $e) {
    $error = "Error fetching users: " . $e->getMessage();
}

// 2. Process Penugasan Form
if (isset($_POST['assign_target'])) {
    $tanggal = $_POST['tanggal'];
    $aktivitas = $_POST['aktivitas'];
    $target_jumlah = (float)$_POST['target_jumlah'];
    $unit = trim($_POST['unit']);
    $id_mandor = (int)$_POST['id_mandor'];
    $karyawans = isset($_POST['id_karyawan']) ? $_POST['id_karyawan'] : [];

    if (empty($tanggal) || empty($aktivitas) || $target_jumlah <= 0 || empty($unit) || empty($id_mandor) || empty($karyawans)) {
        $error = "Semua field harus diisi dan minimal pilih satu Karyawan.";
    } else {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO assignments (tanggal, aktivitas, target_jumlah, unit, id_mandor, id_karyawan, id_manajer) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($karyawans as $id_karyawan) {
                $stmt->execute([$tanggal, $aktivitas, $target_jumlah, $unit, $id_mandor, (int)$id_karyawan, $manajer_id]);
            }
            
            $pdo->commit();
            $success = "Penugasan harian berhasil dibuat dan didistribusikan ke Karyawan & Mandor.";
        } catch (\PDOException $e) {
            $pdo->rollBack();
            $error = "Gagal membuat penugasan: " . $e->getMessage();
        }
    }
}

// 3. Process Delete Assignment
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM assignments WHERE id = ?");
        $stmt->execute([$del_id]);
        $success = "Penugasan berhasil dihapus.";
    } catch (\PDOException $e) {
        $error = "Gagal menghapus penugasan (Tugas ini kemungkinan sudah dilaporkan/dikerjakan): " . $e->getMessage();
    }
}

// 4. Fetch Active Assignments List
$assignments_list = [];
try {
    $stmt = $pdo->query("
        SELECT a.id, a.tanggal, a.aktivitas, a.target_jumlah, a.unit, 
               k.nama as nama_karyawan, m.nama as nama_mandor, m.spesialisasi
        FROM assignments a
        JOIN karyawan k ON a.id_karyawan = k.id_karyawan
        JOIN mandor m ON a.id_mandor = m.id_mandor
        ORDER BY a.tanggal DESC, a.id DESC
    ");
    $assignments_list = $stmt->fetchAll();
} catch (\PDOException $e) {
    $error = "Gagal memuat histori penugasan: " . $e->getMessage();
}
?>

<div style="margin-bottom: 30px;">
    <a href="index.php" style="color: var(--text-muted); font-size: 0.9rem;"><i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard</a>
    <h2 style="margin-top: 10px;">Buat Penugasan & Target Baru</h2>
    <p style="color: var(--text-muted);">Tugaskan Karyawan di bawah pengawasan Mandor dengan target tertentu</p>
</div>

<div class="grid-1-3" style="align-items: start;">
    <!-- Guidelines (Left) -->
    <div>
        <div class="card glass-panel">
            <h3 class="card-title">Informasi Unit Standar</h3>
            <ul style="color: var(--text-muted); font-size: 0.85rem; padding-left: 15px; line-height: 1.6;">
                <li><strong>Pemanenan:</strong> Menggunakan unit <code style="color: var(--primary);">Ton</code> atau <code style="color: var(--primary);">Kg</code>.</li>
                <li><strong>Penyemprotan:</strong> Menggunakan unit <code style="color: var(--primary);">Hektar</code> atau <code style="color: var(--primary);">Liter</code>.</li>
                <li><strong>Pemupukan:</strong> Menggunakan unit <code style="color: var(--primary);">Karung</code> atau <code style="color: var(--primary);">Kg</code>.</li>
            </ul>
        </div>
    </div>

    <!-- Target Form (Right) -->
    <div>
        <div class="card glass-panel">
            <h3 class="card-title">Form Input Penugasan</h3>

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
                <div class="grid-2">
                    <div class="form-group">
                        <label for="tanggal">Tanggal Pelaksanaan</label>
                        <input type="date" name="tanggal" id="tanggal" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="aktivitas">Jenis Aktivitas Kerja</label>
                        <select name="aktivitas" id="aktivitas" class="form-control" required onchange="updateUnitPlaceholder()">
                            <option value="">-- Pilih Aktivitas --</option>
                            <option value="Pemanenan">Pemanenan</option>
                            <option value="Penyemprotan">Penyemprotan</option>
                            <option value="Pemupukan">Pemupukan</option>
                        </select>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label for="target_jumlah">Jumlah Target Kerja</label>
                        <input type="number" step="0.01" name="target_jumlah" id="target_jumlah" class="form-control" placeholder="Contoh: 1.5" required>
                    </div>

                    <div class="form-group">
                        <label for="unit">Satuan / Unit</label>
                        <input type="text" name="unit" id="unit" class="form-control" placeholder="Contoh: Ton" required>
                    </div>
                </div>

                <div class="form-group" style="border-top: 1px solid rgba(110,192,136,0.15); padding-top: 15px; margin-top: 15px;">
                    <label for="id_mandor">Mandor Lapangan Penanggung Jawab</label>
                    <select name="id_mandor" id="id_mandor" class="form-control" required>
                        <option value="">-- Pilih Mandor --</option>
                        <?php foreach ($all_mandor as $m): ?>
                            <option value="<?php echo $m['id_mandor']; ?>"><?php echo htmlspecialchars($m['nama']) . ' (' . htmlspecialchars($m['spesialisasi']) . ')'; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: var(--text-muted); display:block; margin-top: 5px;">Mandor bertanggung jawab mengawasi dan memverifikasi pekerjaan fisik di lapangan.</small>
                </div>

                <div class="form-group">
                    <label>Pilih Karyawan Lapangan (Pelaksana Tugas)</label>
                    <div style="background: rgba(0,0,0,0.25); border: 1px solid rgba(110,192,136,0.15); border-radius: 8px; padding: 15px; max-height: 200px; overflow-y: auto;">
                        <?php foreach ($all_karyawan as $k): ?>
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                <input type="checkbox" name="id_karyawan[]" id="karyawan_<?php echo $k['id_karyawan']; ?>" value="<?php echo $k['id_karyawan']; ?>" style="accent-color: var(--primary-light); width: 18px; height: 18px;">
                                <label for="karyawan_<?php echo $k['id_karyawan']; ?>" style="display:inline; margin: 0; color: #fff; cursor: pointer; font-size: 0.95rem;">
                                    <?php echo htmlspecialchars($k['nama']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <small style="color: var(--text-muted); display:block; margin-top: 5px;">Tugas di atas akan dibagikan kepada masing-masing Karyawan yang dicentang.</small>
                </div>

                <button type="submit" name="assign_target" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                    <i class="fa-solid fa-circle-plus"></i> Distribusikan Tugas
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Table of Created Assignments (Full Width) -->
<div class="card glass-panel" style="margin-top: 25px;">
    <h3 class="card-title"><i class="fa-solid fa-list-check" style="color: var(--primary);"></i> Daftar Penugasan Kerja & Pengawasan</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Tanggal Tugas</th>
                    <th>Pelaksana (Karyawan)</th>
                    <th>Pengawas (Mandor Lapangan)</th>
                    <th>Aktivitas Kerja</th>
                    <th>Target Jumlah</th>
                    <th class="no-print">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($assignments_list)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted);">Belum ada penugasan yang dibuat.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($assignments_list as $row): ?>
                        <tr>
                            <td><?php echo date('d-m-Y', strtotime($row['tanggal'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($row['nama_karyawan']); ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['nama_mandor']); ?></strong> 
                                <span style="font-size:0.75rem; color: var(--text-muted);"> (<?php echo htmlspecialchars($row['spesialisasi']); ?>)</span>
                            </td>
                            <td><span class="badge badge-verified" style="font-size:0.75rem;"><?php echo htmlspecialchars($row['aktivitas']); ?></span></td>
                            <td><strong style="color: var(--primary);"><?php echo (float)$row['target_jumlah'] . ' ' . htmlspecialchars($row['unit']); ?></strong></td>
                            <td class="no-print">
                                <a href="targets.php?delete_id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" style="padding: 4px 12px;" onclick="return confirm('Apakah Anda yakin ingin membatalkan/menghapus tugas ini?')"><i class="fa-solid fa-trash-can"></i> Hapus</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function updateUnitPlaceholder() {
        const activity = document.getElementById('aktivitas').value;
        const unitInput = document.getElementById('unit');
        
        if (activity === 'Pemanenan') {
            unitInput.value = 'Ton';
        } else if (activity === 'Penyemprotan') {
            unitInput.value = 'Hektar';
        } else if (activity === 'Pemupukan') {
            unitInput.value = 'Karung';
        } else {
            unitInput.value = '';
        }
    }
</script>

<?php require_once '../includes/footer.php'; ?>
