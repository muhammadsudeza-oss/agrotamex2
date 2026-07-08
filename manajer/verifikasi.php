<?php
// manajer/verifikasi.php
require_once '../config/koneksi.php';
require_once '../includes/header.php';

// Validate role
if ($_SESSION['role'] !== 'manajer') {
    header("Location: ../index.php");
    exit;
}

$manajer_id = $_SESSION['user_id'];
$report_id = isset($_GET['report_id']) ? (int)$_GET['report_id'] : 0;
$error = "";
$success = "";
$report = null;
$pending_list = [];
$manipulation_list = [];

// Helper function to calculate bonus
function calculateBonus($activity, $actual, $target) {
    if ($actual <= $target) {
        return 0.00;
    }
    $excess = $actual - $target;
    switch ($activity) {
        case 'Pemanenan':
            return $excess * 50000;   // Rp 50.000 / Ton lebih
        case 'Penyemprotan':
            return $excess * 100000;  // Rp 100.000 / Hektar lebih
        case 'Pemupukan':
            return $excess * 5000;    // Rp 5.000 / Karung lebih
        default:
            return 0.00;
    }
}

try {
    // 1. Fetch work report details
    $stmt = $pdo->prepare("
        SELECT r.*, a.tanggal, a.aktivitas, a.target_jumlah, a.unit, k.nama as nama_karyawan, m.nama as nama_mandor
        FROM work_reports r
        JOIN assignments a ON r.id_assignment = a.id
        JOIN karyawan k ON r.id_karyawan = k.id_karyawan
        JOIN mandor m ON a.id_mandor = m.id_mandor
        WHERE r.id = ?
    ");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch();

    if (!$report) {
        // If no report_id, fetch verified pending list
        $stmt_pending = $pdo->prepare("
            SELECT r.*, a.tanggal, a.aktivitas, a.target_jumlah, a.unit, k.nama as nama_karyawan, m.nama as nama_mandor
            FROM work_reports r
            JOIN assignments a ON r.id_assignment = a.id
            JOIN karyawan k ON r.id_karyawan = k.id_karyawan
            JOIN mandor m ON a.id_mandor = m.id_mandor
            WHERE r.status = 'verified_by_mandor'
            ORDER BY a.tanggal DESC, r.created_at DESC
        ");
        $stmt_pending->execute();
        $pending_list = $stmt_pending->fetchAll();

        // Fetch manipulation cases waiting JEM confirmation
        $stmt_manipulation = $pdo->prepare("
            SELECT r.*, a.tanggal, a.aktivitas, a.target_jumlah, a.unit, k.nama as nama_karyawan, m.nama as nama_mandor
            FROM work_reports r
            JOIN assignments a ON r.id_assignment = a.id
            JOIN karyawan k ON r.id_karyawan = k.id_karyawan
            JOIN mandor m ON a.id_mandor = m.id_mandor
            WHERE r.status = 'pending_manajer_tolak'
            ORDER BY a.tanggal DESC, r.created_at DESC
        ");
        $stmt_manipulation->execute();
        $manipulation_list = $stmt_manipulation->fetchAll();
    }

} catch (\PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

// 2. Process Final Approval Form
if (isset($_POST['approve_report']) && $report && $report['status'] === 'verified_by_mandor') {
    $catatan_manajer = trim($_POST['catatan_manajer']);

    // Calculate final bonus if realization exceeds target
    $bonus = 0.00;
    if ($report['jumlah_realisasi'] > $report['target_jumlah']) {
        $bonus = calculateBonus($report['aktivitas'], (float)$report['jumlah_realisasi'], (float)$report['target_jumlah']);
        // Apply penalty percentage discount if any
        if ($report['potongan_penalti'] > 0) {
            $bonus = $bonus * (1 - ($report['potongan_penalti'] / 100));
        }
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE work_reports 
            SET status = 'approved', 
                catatan_manajer = ?, 
                tanggal_verifikasi_manajer = NOW(),
                bonus_diterima = ?
            WHERE id = ?
        ");
        $stmt->execute([$catatan_manajer, $bonus, $report_id]);
        
        $success = "Pekerjaan karyawan berhasil diberikan persetujuan (Approve) final.";
        if ($bonus > 0) {
            $success .= " Bonus Rp " . number_format($bonus, 0, ',', '.') . " dialokasikan ke Karyawan.";
            if ($report['potongan_penalti'] > 0) {
                $success .= " (Denda potongan penalti " . (float)$report['potongan_penalti'] . "% telah diterapkan).";
            }
        }
        
        // Refresh details
        $stmt = $pdo->prepare("
            SELECT r.*, a.tanggal, a.aktivitas, a.target_jumlah, a.unit, k.nama as nama_karyawan, m.nama as nama_mandor
            FROM work_reports r
            JOIN assignments a ON r.id_assignment = a.id
            JOIN karyawan k ON r.id_karyawan = k.id_karyawan
            JOIN mandor m ON a.id_mandor = m.id_mandor
            WHERE r.id = ?
        ");
        $stmt->execute([$report_id]);
        $report = $stmt->fetch();

        echo "<script>setTimeout(() => { window.location.href = 'index.php'; }, 2000);</script>";
    } catch (\PDOException $e) {
        $error = "Gagal memproses persetujuan: " . $e->getMessage();
    }
}

// 3. Process Rejection Form (Regular Rejection)
if (isset($_POST['reject_report']) && $report && $report['status'] === 'verified_by_mandor') {
    $catatan_manajer = trim($_POST['catatan_manajer']);

    if (empty($catatan_manajer)) {
        $error = "Gagal menolak: Wajib menuliskan Catatan Alasan Penolakan agar Karyawan tahu penyebabnya.";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE work_reports 
                SET status = 'rejected', 
                    catatan_manajer = ?, 
                    tanggal_verifikasi_manajer = NOW(),
                    bonus_diterima = 0.00,
                    potongan_penalti = 0.00
                WHERE id = ?
            ");
            $stmt->execute([$catatan_manajer, $report_id]);
            
            $success = "Laporan kerja telah Ditolak. Tugas telah dikembalikan ke Karyawan untuk dilaporkan ulang.";
            
            // Refresh details
            $stmt = $pdo->prepare("
                SELECT r.*, a.tanggal, a.aktivitas, a.target_jumlah, a.unit, k.nama as nama_karyawan, m.nama as nama_mandor
                FROM work_reports r
                JOIN assignments a ON r.id_assignment = a.id
                JOIN karyawan k ON r.id_karyawan = k.id_karyawan
                JOIN mandor m ON a.id_mandor = m.id_mandor
                WHERE r.id = ?
            ");
            $stmt->execute([$report_id]);
            $report = $stmt->fetch();

            echo "<script>setTimeout(() => { window.location.href = 'index.php'; }, 2000);</script>";
        } catch (\PDOException $e) {
            $error = "Gagal memproses penolakan: " . $e->getMessage();
        }
    }
}

// 4. Process Confirmation of Rejection Penalty (Confirmed Manipulation Case)
if (isset($_POST['confirm_manipulation_penalty']) && $report && $report['status'] === 'pending_manajer_tolak') {
    $catatan_manajer = trim($_POST['catatan_manajer']);
    
    if (empty($catatan_manajer)) {
        $error = "Gagal memproses: Wajib menuliskan catatan alasan/keputusan sanksi denda.";
    } else {
        try {
            // Calculate sum of all previously approved bonuses for this employee
            $stmt_sum = $pdo->prepare("
                SELECT SUM(bonus_diterima) as total_bonus 
                FROM work_reports 
                WHERE id_karyawan = ? AND status = 'approved'
            ");
            $stmt_sum->execute([$report['id_karyawan']]);
            $sum_res = $stmt_sum->fetch();
            $total_past_bonus = (float)($sum_res['total_bonus'] ?? 0);
            
            // Calculate 10% penalty denda
            $penalty = $total_past_bonus * 0.10;
            $negative_bonus = -$penalty;

            $stmt = $pdo->prepare("
                UPDATE work_reports 
                SET status = 'rejected', 
                    potongan_penalti = 10.00,
                    catatan_manajer = ?, 
                    tanggal_verifikasi_manajer = NOW(),
                    bonus_diterima = ?
                WHERE id = ?
            ");
            $stmt->execute([$catatan_manajer, $negative_bonus, $report_id]);
            
            $success = "Kasus manipulasi dikonfirmasi. Laporan ditolak permanen. Denda potongan 10% sebesar Rp " . number_format($penalty, 0, ',', '.') . " telah dipotong dari akumulasi bonus Karyawan.";
            
            // Refresh details
            $stmt = $pdo->prepare("
                SELECT r.*, a.tanggal, a.aktivitas, a.target_jumlah, a.unit, k.nama as nama_karyawan, m.nama as nama_mandor
                FROM work_reports r
                JOIN assignments a ON r.id_assignment = a.id
                JOIN karyawan k ON r.id_karyawan = k.id_karyawan
                JOIN mandor m ON a.id_mandor = m.id_mandor
                WHERE r.id = ?
            ");
            $stmt->execute([$report_id]);
            $report = $stmt->fetch();

            echo "<script>setTimeout(() => { window.location.href = 'index.php'; }, 2000);</script>";
        } catch (\PDOException $e) {
            $error = "Gagal memproses sanksi denda: " . $e->getMessage();
        }
    }
}

// 5. Process Cancellation of Rejection Penalty (Manipulation Case)
if (isset($_POST['cancel_manipulation_penalty']) && $report && $report['status'] === 'pending_manajer_tolak') {
    $catatan_manajer = trim($_POST['catatan_manajer']);
    
    if (empty($catatan_manajer)) {
        $error = "Gagal memproses: Wajib menuliskan catatan evaluasi pembatalan sanksi.";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE work_reports 
                SET status = 'rejected', 
                    potongan_penalti = 0.00,
                    catatan_manajer = ?, 
                    tanggal_verifikasi_manajer = NOW(),
                    bonus_diterima = 0.00
                WHERE id = ?
            ");
            $stmt->execute([$catatan_manajer, $report_id]);
            
            $success = "Sanksi dibatalkan. Laporan ditolak sebagai penolakan biasa tanpa denda potongan (bisa kirim ulang).";
            
            // Refresh details
            $stmt = $pdo->prepare("
                SELECT r.*, a.tanggal, a.aktivitas, a.target_jumlah, a.unit, k.nama as nama_karyawan, m.nama as nama_mandor
                FROM work_reports r
                JOIN assignments a ON r.id_assignment = a.id
                JOIN karyawan k ON r.id_karyawan = k.id_karyawan
                JOIN mandor m ON a.id_mandor = m.id_mandor
                WHERE r.id = ?
            ");
            $stmt->execute([$report_id]);
            $report = $stmt->fetch();

            echo "<script>setTimeout(() => { window.location.href = 'index.php'; }, 2000);</script>";
        } catch (\PDOException $e) {
            $error = "Gagal membatalkan sanksi: " . $e->getMessage();
        }
    }
}
?>

<div style="margin-bottom: 30px;">
    <a href="index.php" style="color: var(--text-muted); font-size: 0.9rem;"><i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard</a>
    <h2 style="margin-top: 10px;">Review & Approval Akhir Manajer</h2>
    <p style="color: var(--text-muted);">Persetujuan akhir terhadap laporan pekerjaan yang telah diverifikasi Mandor</p>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger" style="max-width: 800px; margin: 0 auto 20px auto;">
        <i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success" style="max-width: 800px; margin: 0 auto 20px auto;">
        <i class="fa-solid fa-circle-check"></i> <?php echo $success; ?>
    </div>
<?php endif; ?>

<?php if (!$report): ?>
    <!-- 1. Selection screen lists -->
    <div style="max-width: 850px; margin: 0 auto; display: flex; flex-direction: column; gap: 30px;">
        
        <!-- Case list of Rejections with Manipulation Indication -->
        <div class="card glass-panel" style="border: 1px solid rgba(230,81,0,0.2); background: rgba(230,81,0,0.01);">
            <h3 class="card-title" style="color: #e65100;"><i class="fa-solid fa-triangle-exclamation"></i> Kasus Indikasi Manipulasi Menunggu Konfirmasi JEM</h3>
            <?php if (empty($manipulation_list)): ?>
                <div style="text-align: center; padding: 30px 20px; color: var(--text-muted); font-size:0.9rem;">
                    <i class="fa-solid fa-circle-check" style="font-size: 2rem; color: var(--success); margin-bottom: 10px; display: block;"></i>
                    Tidak ada laporan terindikasi manipulasi data yang menunggu konfirmasi Anda.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Karyawan</th>
                                <th>Aktivitas</th>
                                <th>Pemeriksa (Mandor)</th>
                                <th>Catatan Kejanggalan Mandor</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($manipulation_list as $m): ?>
                                <tr style="background: rgba(230,81,0,0.01);">
                                    <td><?php echo date('d-m-Y', strtotime($m['tanggal'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($m['nama_karyawan']); ?></strong></td>
                                    <td><span class="badge" style="font-size:0.75rem; background:#ffebee; color:#c62828; border:1px solid #ffcdd2; padding: 4px 8px; border-radius: 4px; font-weight:600; display:inline-block;"><?php echo htmlspecialchars($m['aktivitas']); ?></span></td>
                                    <td><strong><?php echo htmlspecialchars($m['nama_mandor']); ?></strong></td>
                                    <td style="font-size: 0.85rem; font-style:italic; color: #d84315;"><?php echo htmlspecialchars($m['catatan_mandor']); ?></td>
                                    <td>
                                        <a href="verifikasi.php?report_id=<?php echo $m['id']; ?>" class="btn btn-sm" style="background:#e65100; color:#fff; border:none; padding:6px 12px; display:inline-block; border-radius:6px; font-weight:600; text-align:center;">
                                            <i class="fa-solid fa-gavel"></i> Tinjau Kasus
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Normal pending approvals list -->
        <div class="card glass-panel">
            <h3 class="card-title"><i class="fa-solid fa-list-check" style="color:var(--primary);"></i> Daftar Antrean Persetujuan Final</h3>
            <?php if (empty($pending_list)): ?>
                <div style="text-align: center; padding: 45px 20px; color: var(--text-muted);">
                    <i class="fa-solid fa-circle-check" style="font-size: 3rem; color: var(--primary-light); margin-bottom: 15px; display: block;"></i>
                    Tidak ada laporan yang menunggu persetujuan Anda saat ini.
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
                                <th>Verifikasi Mandor</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_list as $p): ?>
                                <tr>
                                    <td><?php echo date('d-m-Y', strtotime($p['tanggal'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($p['nama_karyawan']); ?></strong></td>
                                    <td><span class="badge badge-verified" style="font-size:0.75rem;"><?php echo htmlspecialchars($p['aktivitas']); ?></span></td>
                                    <td><?php echo (float)$p['target_jumlah'] . ' ' . htmlspecialchars($p['unit']); ?></td>
                                    <td><strong style="color:var(--primary);"><?php echo (float)$p['jumlah_realisasi'] . ' ' . htmlspecialchars($p['unit']); ?></strong></td>
                                    <td><span style="font-style:italic; font-size:0.85rem; color:var(--text-muted);"><?php echo htmlspecialchars($p['nama_mandor']); ?></span></td>
                                    <td>
                                        <a href="verifikasi.php?report_id=<?php echo $p['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fa-solid fa-magnifying-glass-chart"></i> Review & Proses
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
<?php else: ?>
    <!-- 2. Detailed Verification View -->
    <div class="grid-2" style="max-width: 850px; margin:0 auto;">
        <!-- Left: Details & Photos -->
        <div>
            <div class="card glass-panel">
                <h3 class="card-title">Rincian Laporan Lapangan</h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                    <div>
                        <label style="color: var(--text-muted); font-size: 0.85rem; display:block;">Nama Karyawan</label>
                        <span style="font-size: 1.05rem; font-weight:600;"><?php echo htmlspecialchars($report['nama_karyawan']); ?></span>
                    </div>
                    <div>
                        <label style="color: var(--text-muted); font-size: 0.85rem; display:block;">Mandor Verifikator</label>
                        <span style="font-size: 1.05rem; font-weight:600; color: var(--primary-light);"><?php echo htmlspecialchars($report['nama_mandor']); ?></span>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                    <div>
                        <label style="color: var(--text-muted); font-size: 0.85rem; display:block;">Aktivitas</label>
                        <span style="font-weight: 500;"><?php echo htmlspecialchars($report['aktivitas']); ?></span>
                    </div>
                    <div>
                        <label style="color: var(--text-muted); font-size: 0.85rem; display:block;">Tanggal Tugas</label>
                        <span style="font-weight: 500;"><?php echo date('d F Y', strtotime($report['tanggal'])); ?></span>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                    <div>
                        <label style="color: var(--text-muted); font-size: 0.85rem; display:block;">Target</label>
                        <span style="font-weight: 600; color: var(--gold);"><?php echo (float)$report['target_jumlah'] . ' ' . htmlspecialchars($report['unit']); ?></span>
                    </div>
                    <div>
                        <label style="color: var(--text-muted); font-size: 0.85rem; display:block;">Realisasi Hasil</label>
                        <span style="font-weight: 600; color: var(--primary);"><?php echo (float)$report['jumlah_realisasi'] . ' ' . htmlspecialchars($report['unit']); ?></span>
                    </div>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="color: var(--text-muted); font-size: 0.85rem; display:block;">Komentar Mandor Lapangan</label>
                    <p style="background: rgba(46,125,50,0.02); border: 1px solid var(--card-border); padding: 10px; border-radius: 6px; font-size: 0.9rem; font-style: italic;">
                        <?php echo htmlspecialchars($report['catatan_mandor']); ?>
                    </p>
                    <small style="color: var(--text-muted);">
                        Diverifikasi Mandor: <?php echo date('d-m-Y H:i', strtotime($report['tanggal_verifikasi_mandor'])); ?>
                    </small>
                </div>

                <!-- Previous penalty indicator -->
                <?php if ($report['potongan_penalti'] > 0): ?>
                    <div style="background: rgba(198,40,40,0.05); border: 1px solid rgba(198,40,40,0.2); padding: 12px; border-radius: 6px; margin-bottom: 20px;">
                        <span style="font-weight: 600; color: #c62828;"><i class="fa-solid fa-hand-holding-dollar"></i> Sanksi Denda Aktif:</span>
                        <strong style="color: #c62828; float: right;">Potong Bonus <?php echo (float)$report['potongan_penalti']; ?>%</strong>
                        <small style="display:block; color:var(--text-muted); font-size:0.75rem; margin-top:4px;">Karyawan terbukti melakukan percobaan manipulasi data pada tugas ini sebelumnya.</small>
                    </div>
                <?php endif; ?>

                <!-- Estimated bonus summary in details -->
                <?php 
                $bonus_est = calculateBonus($report['aktivitas'], (float)$report['jumlah_realisasi'], (float)$report['target_jumlah']);
                if ($report['potongan_penalti'] > 0 && $bonus_est > 0) {
                    // Apply penalty cut for visual estimate
                    $bonus_est_discounted = $bonus_est * (1 - ($report['potongan_penalti'] / 100));
                } else {
                    $bonus_est_discounted = $bonus_est;
                }

                if ($bonus_est > 0 && $report['status'] === 'verified_by_mandor'):
                ?>
                    <div style="background: rgba(46,125,50,0.05); border: 1px solid var(--primary-light); padding: 12px; border-radius: 6px; margin-bottom: 20px;">
                        <span style="font-weight: 600; color: var(--success);"><i class="fa-solid fa-gift"></i> Estimasi Bonus Ketercapaian Target:</span>
                        <?php if ($report['potongan_penalti'] > 0): ?>
                            <strong style="color: var(--primary); float: right;">Rp <?php echo number_format($bonus_est_discounted, 0, ',', '.'); ?> <span style="font-size:0.75rem; text-decoration: line-through; color: var(--text-muted);">Rp <?php echo number_format($bonus_est, 0, ',', '.'); ?></span></strong>
                        <?php else: ?>
                            <strong style="color: var(--primary); float: right;">Rp <?php echo number_format($bonus_est, 0, ',', '.'); ?></strong>
                        <?php endif; ?>
                        <small style="display:block; color:var(--text-muted); font-size:0.75rem; margin-top:4px;">Kelebihan hasil sebesar <?php echo (float)($report['jumlah_realisasi'] - $report['target_jumlah']) . ' ' . htmlspecialchars($report['unit']); ?>.</small>
                    </div>
                <?php endif; ?>

                <div>
                    <label style="color: var(--text-muted); font-size: 0.85rem; display:block; margin-bottom: 8px;">Foto Bukti Lapangan (Klik untuk memperbesar)</label>
                    <?php if (!empty($report['foto_bukti'])): ?>
                        <img src="../<?php echo htmlspecialchars($report['foto_bukti']); ?>" 
                             alt="Bukti Foto: <?php echo htmlspecialchars($report['nama_karyawan']) . ' - ' . htmlspecialchars($report['aktivitas']); ?>" 
                             class="img-proof" style="width: 100%; height: auto; max-height: 250px; border-radius: 8px; border: 1px solid var(--card-border);"
                             onclick="openModal('../<?php echo htmlspecialchars($report['foto_bukti']); ?>', 'Bukti Foto: <?php echo htmlspecialchars($report['nama_karyawan']) . ' - ' . htmlspecialchars($report['aktivitas']); ?>')">
                    <?php else: ?>
                        <span style="opacity: 0.5; font-style: italic;">Tidak ada foto bukti.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Action Form -->
        <div>
            <div class="card glass-panel">
                <h3 class="card-title">Keputusan Manajer JEM</h3>

                <?php if ($report['status'] === 'verified_by_mandor'): ?>
                    <!-- Form approval normal JEM -->
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="catatan_manajer">Catatan Evaluasi Manajer (Wajib diisi jika menolak)</label>
                            <textarea name="catatan_manajer" id="catatan_manajer" rows="4" class="form-control" placeholder="Tulis catatan persetujuan atau alasan penolakan kerja..."></textarea>
                        </div>

                        <button type="submit" name="approve_report" class="btn btn-primary" style="width: 100%; margin-bottom: 12px; padding: 12px;">
                            <i class="fa-solid fa-stamp"></i> Setujui & Berikan Persetujuan
                        </button>
                        
                        <button type="submit" name="reject_report" class="btn btn-danger" style="width: 100%; padding: 12px;" onclick="return confirm('Apakah Anda yakin ingin menolak laporan pekerjaan ini?')">
                            <i class="fa-solid fa-ban"></i> Tolak Pekerjaan (Laporkan Ulang)
                        </button>
                    </form>
                <?php elseif ($report['status'] === 'pending_manajer_tolak'): ?>
                    <!-- Confirmation form for data manipulation reported by Foreman -->
                    <div style="background: rgba(230,81,0,0.04); border: 1px solid rgba(230,81,0,0.2); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <h4 style="color: #e65100; margin-top: 0; margin-bottom: 8px;"><i class="fa-solid fa-triangle-exclamation"></i> Laporan Kasus Indikasi Manipulasi Data</h4>
                        <p style="font-size:0.85rem; color:var(--text-muted); margin:0; line-height:1.4;">Mandor melaporkan kejanggalan manipulasi bukti kerja pada tugas ini. Harap tinjau bukti foto & koordinat untuk memvalidasi sanksi denda.</p>
                    </div>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="catatan_manajer">Catatan / Alasan Keputusan Sanksi (Wajib Diisi)</label>
                            <textarea name="catatan_manajer" id="catatan_manajer" rows="4" class="form-control" placeholder="Tulis alasan keputusan sanksi denda atau pembatalan denda..." required></textarea>
                        </div>

                        <button type="submit" name="confirm_manipulation_penalty" class="btn btn-danger" style="width: 100%; margin-bottom: 12px; padding: 12px;" onclick="return confirm('Apakah Anda yakin ingin mengonfirmasi kasus manipulasi ini? Laporan akan ditolak permanen (tidak bisa kirim ulang) dan bonus yang pernah didapat karyawan otomatis dipotong 10%!')">
                            <i class="fa-solid fa-gavel"></i> Konfirmasi Sanksi (Potong Akumulasi Bonus 10% & Tolak Permanen)
                        </button>
                        
                        <button type="submit" name="cancel_manipulation_penalty" class="btn btn-secondary" style="width: 100%; padding: 12px;" onclick="return confirm('Apakah Anda yakin ingin membatalkan sanksi denda manipulasi? Laporan akan ditolak biasa tanpa denda (bisa dilaporkan ulang).')">
                            <i class="fa-solid fa-xmark"></i> Batalkan Sanksi (Tolak Biasa)
                        </button>
                    </form>
                <?php else: ?>
                    <!-- Readonly final state -->
                    <div style="margin-top: 10px;">
                        <div style="margin-bottom: 20px;">
                            <label style="color: var(--text-muted); font-size: 0.85rem; display:block;">Status Laporan Akhir</label>
                            <?php if ($report['status'] === 'approved'): ?>
                                <span class="badge badge-approved" style="font-size: 0.95rem; padding: 6px 12px; margin-top: 5px;">Disetujui Final</span>
                                <?php if ($report['bonus_diterima'] > 0): ?>
                                    <div style="background: rgba(46,125,50,0.05); border: 1px solid var(--primary-light); padding: 12px; border-radius: 6px; margin-top: 15px;">
                                        <span style="font-weight: 600; color: var(--success);"><i class="fa-solid fa-gift"></i> Bonus Kinerja Diterima Karyawan:</span>
                                        <strong style="color: var(--primary); display:block; font-size:1.3rem; margin-top:5px;">Rp <?php echo number_format($report['bonus_diterima'], 0, ',', '.'); ?></strong>
                                        <?php if ($report['potongan_penalti'] > 0): ?>
                                            <small style="color: #c62828; font-weight:600; display:block; margin-top:4px;">(Sudah dipotong denda sanksi <?php echo (float)$report['potongan_penalti']; ?>% karena manipulasi sebelumnya)</small>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($report['status'] === 'rejected'): ?>
                                <?php if ($report['potongan_penalti'] > 0): ?>
                                    <span class="badge badge-logout" style="font-size: 0.95rem; padding: 6px 12px; margin-top: 5px; background:#ffebee; color:#c62828; border:1px solid #ffcdd2;">Ditolak Permanen (Sanksi Manipulasi)</span>
                                    <div style="background: rgba(198,40,40,0.05); border: 1px solid rgba(198,40,40,0.2); padding: 10px; border-radius: 6px; margin-top: 10px; font-size:0.85rem; color:#c62828; font-weight:600;">
                                        <i class="fa-solid fa-triangle-exclamation"></i> Sanksi Denda 10% Diterapkan JEM. Akumulasi Bonus Dipotong: Rp <?php echo number_format(abs($report['bonus_diterima']), 0, ',', '.'); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="badge badge-logout" style="font-size: 0.95rem; padding: 6px 12px; margin-top: 5px; background:#ffebee; color:#c62828; border:1px solid #ffcdd2;">Ditolak (Bisa Kirim Ulang)</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div style="border-top: 1px solid var(--card-border); padding-top: 15px; margin-top: 20px;">
                            <label style="color: var(--text-muted); font-size: 0.85rem; display:block;">Catatan Manajer JEM</label>
                            <p style="background: rgba(0,0,0,0.02); border: 1px solid var(--card-border); padding: 10px; border-radius: 6px; font-size: 0.9rem; margin-top: 5px; font-style: italic;">
                                <?php echo !empty($report['catatan_manajer']) ? htmlspecialchars($report['catatan_manajer']) : '- Tidak ada catatan -'; ?>
                            </p>
                            <small style="color: var(--text-muted);">
                                Diproses pada: <?php echo date('d-m-Y H:i', strtotime($report['tanggal_verifikasi_manajer'])); ?>
                            </small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Modal for Image Preview Lightbox -->
<div id="imageModal" class="modal" onclick="closeModal()">
    <span class="modal-close" onclick="closeModal()">&times;</span>
    <img class="modal-content" id="imgModalTarget">
    <div id="imgModalCaption" class="modal-caption"></div>
</div>

<script>
function openModal(src, caption) {
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('imgModalTarget');
    const captionText = document.getElementById('imgModalCaption');
    
    modal.style.display = "block";
    modalImg.src = src;
    captionText.innerHTML = caption;
}

function closeModal() {
    document.getElementById('imageModal').style.display = "none";
}
</script>

<?php require_once '../includes/footer.php'; ?>
