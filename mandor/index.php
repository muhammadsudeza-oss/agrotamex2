<?php
// mandor/index.php
require_once '../config/koneksi.php';
require_once '../includes/header.php';

// Validate role
if ($_SESSION['role'] !== 'mandor') {
    header("Location: ../index.php");
    exit;
}

$mandor_id = $_SESSION['user_id'];
$error = "";

try {
    // 1. Fetch statistics
    // Pending reports from employees assigned under tasks supervised by this foreman
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending_count 
        FROM work_reports r
        JOIN assignments a ON r.id_assignment = a.id
        WHERE a.id_mandor = ? AND r.status = 'pending_mandor'
    ");
    $stmt->execute([$mandor_id]);
    $stats = $stmt->fetch();
    $pending_verifications = $stats['pending_count'] ?? 0;

    // Total active employees assigned to this foreman ever (distinct employees)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT id_karyawan) as emp_count 
        FROM assignments 
        WHERE id_mandor = ?
    ");
    $stmt->execute([$mandor_id]);
    $emp_stats = $stmt->fetch();
    $total_employees = $emp_stats['emp_count'] ?? 0;

    // 2. Fetch all reports under this foreman's supervision (recent ones first)
    $stmt = $pdo->prepare("
        SELECT r.*, a.tanggal, a.aktivitas, a.target_jumlah, a.unit, k.nama as nama_karyawan
        FROM work_reports r
        JOIN assignments a ON r.id_assignment = a.id
        JOIN karyawan k ON r.id_karyawan = k.id_karyawan
        WHERE a.id_mandor = ?
        ORDER BY r.status = 'pending_mandor' DESC, a.tanggal DESC, r.created_at DESC
        LIMIT 25
    ");
    $stmt->execute([$mandor_id]);
    $reports = $stmt->fetchAll();

} catch (\PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<div style="margin-bottom: 30px;">
    <h2>Dashboard Mandor</h2>
    <p style="color: var(--text-muted);">PT Agrotamex Sumindo Abadi - Pengawasan Kelompok Kerja Lapangan</p>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Stats Overview -->
<div class="grid-3" style="margin-bottom: 25px;">
    <div class="stat-card glass-panel">
        <div class="stat-icon">
            <i class="fa-solid fa-list-check"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?php echo $pending_verifications; ?></div>
            <div class="stat-label">Menunggu Verifikasi Anda</div>
        </div>
    </div>

    <div class="stat-card glass-panel">
        <div class="stat-icon" style="color: var(--primary-light); background: rgba(110, 192, 136, 0.1);">
            <i class="fa-solid fa-users-gear"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?php echo $total_employees; ?></div>
            <div class="stat-label">Karyawan Terdaftar Tugas</div>
        </div>
    </div>

    <div class="stat-card glass-panel">
        <div class="stat-icon" style="color: var(--gold); background: rgba(212, 175, 55, 0.1);">
            <i class="fa-solid fa-user-tie"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value" style="font-size: 1.1rem; padding-top: 10px; font-weight: 600;">MANDOR</div>
            <div class="stat-label">Peran Pengawas</div>
        </div>
    </div>
</div>

<!-- Supervised Employee Reports -->
<div class="card glass-panel">
    <h3 class="card-title">Daftar Laporan Pekerjaan Karyawan</h3>
    
    <?php if (empty($reports)): ?>
        <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
            <i class="fa-solid fa-folder-open" style="font-size: 3rem; margin-bottom: 15px; display: block;"></i>
            Belum ada laporan dari karyawan di bawah pengawasan Anda.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Karyawan</th>
                        <th>Aktivitas</th>
                        <th>Target</th>
                        <th>Realisasi</th>
                        <th>Bukti Foto</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $rep): ?>
                        <tr>
                            <td><?php echo date('d-m-Y', strtotime($rep['tanggal'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($rep['nama_karyawan']); ?></strong></td>
                            <td><?php echo htmlspecialchars($rep['aktivitas']); ?></td>
                            <td><?php echo (float)$rep['target_jumlah'] . ' ' . htmlspecialchars($rep['unit']); ?></td>
                            <td><?php echo (float)$rep['jumlah_realisasi'] . ' ' . htmlspecialchars($rep['unit']); ?></td>
                            <td>
                                <?php if (!empty($rep['foto_bukti'])): ?>
                                    <img src="../<?php echo htmlspecialchars($rep['foto_bukti']); ?>" 
                                         alt="Bukti Foto: <?php echo htmlspecialchars($rep['nama_karyawan']) . ' - ' . htmlspecialchars($rep['aktivitas']); ?>" 
                                         class="img-proof">
                                <?php else: ?>
                                    <span style="opacity: 0.5; font-style: italic;">No Photo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($rep['status'] === 'pending_mandor'): ?>
                                    <span class="badge badge-pending">Pending Mandor</span>
                                <?php elseif ($rep['status'] === 'verified_by_mandor'): ?>
                                    <span class="badge badge-verified">Terverifikasi Mandor</span>
                                <?php elseif ($rep['status'] === 'approved'): ?>
                                    <span class="badge badge-approved">Disetujui Manajer</span>
                                <?php elseif ($rep['status'] === 'rejected'): ?>
                                    <span class="badge badge-logout" style="background:#ffebee; color:#c62828; border:1px solid #ffcdd2;">Ditolak</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($rep['status'] === 'pending_mandor'): ?>
                                    <a href="verifikasi.php?report_id=<?php echo $rep['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fa-solid fa-user-check"></i> Verifikasi
                                    </a>
                                <?php else: ?>
                                    <a href="verifikasi.php?report_id=<?php echo $rep['id']; ?>" class="btn btn-secondary btn-sm">
                                        <i class="fa-solid fa-eye"></i> Detail
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
