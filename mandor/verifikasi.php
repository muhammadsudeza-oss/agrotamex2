<?php
// mandor/verifikasi.php
require_once '../config/koneksi.php';
require_once '../includes/header.php';

// Validate role
if ($_SESSION['role'] !== 'mandor') {
    header("Location: ../index.php");
    exit;
}

$mandor_id = $_SESSION['user_id'];
$report_id = isset($_GET['report_id']) ? (int)$_GET['report_id'] : 0;
$error = "";
$success = "";
$report = null;
$pending_reports = [];

if ($report_id > 0) {
    // 1. Fetch details of a specific work report
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, a.tanggal, a.aktivitas, a.target_jumlah, a.unit, a.id_mandor, k.nama as nama_karyawan
            FROM work_reports r
            JOIN assignments a ON r.id_assignment = a.id
            JOIN karyawan k ON r.id_karyawan = k.id_karyawan
            WHERE r.id = ? AND a.id_mandor = ?
        ");
        $stmt->execute([$report_id, $mandor_id]);
        $report = $stmt->fetch();

        if (!$report) {
            header("Location: verifikasi.php");
            exit;
        }

    } catch (\PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
} else {
    // 2. No ID provided. Fetch all pending reports under this Foreman
    try {
        $stmt = $pdo->prepare("
            SELECT r.id, r.jumlah_realisasi, r.foto_bukti, r.created_at,
                   a.tanggal, a.aktivitas, a.target_jumlah, a.unit, k.nama as nama_karyawan
            FROM work_reports r
            JOIN assignments a ON r.id_assignment = a.id
            JOIN karyawan k ON r.id_karyawan = k.id_karyawan
            WHERE a.id_mandor = ? AND r.status = 'pending_mandor'
            ORDER BY a.tanggal DESC, r.created_at DESC
        ");
        $stmt->execute([$mandor_id]);
        $pending_reports = $stmt->fetchAll();
    } catch (\PDOException $e) {
        $error = "Gagal memuat laporan kerja: " . $e->getMessage();
    }
}

// Process Verification Form submission
if ($report_id > 0 && isset($_POST['verify_report']) && $report['status'] === 'pending_mandor') {
    $catatan_mandor = trim($_POST['catatan_mandor']);

    if (empty($catatan_mandor)) {
        $error = "Catatan pemeriksaan wajib diisi.";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE work_reports 
                SET status = 'verified_by_mandor', 
                    catatan_mandor = ?, 
                    tanggal_verifikasi_mandor = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$catatan_mandor, $report_id]);
            
            $success = "Pekerjaan berhasil diverifikasi dan dilaporkan ke Manajer.";
            
            // Refresh details
            $stmt = $pdo->prepare("
                SELECT r.*, a.tanggal, a.aktivitas, a.target_jumlah, a.unit, k.nama as nama_karyawan
                FROM work_reports r
                JOIN assignments a ON r.id_assignment = a.id
                JOIN karyawan k ON r.id_karyawan = k.id_karyawan
                WHERE r.id = ?
            ");
            $stmt->execute([$report_id]);
            $report = $stmt->fetch();
            
            // Redirect back to dashboard after 2 seconds
            echo "<script>setTimeout(() => { window.location.href = 'index.php'; }, 2000);</script>";
        } catch (\PDOException $e) {
            $error = "Gagal memverifikasi laporan: " . $e->getMessage();
        }
    }
}

// Process Rejection Form submission
if ($report_id > 0 && isset($_POST['reject_report']) && $report['status'] === 'pending_mandor') {
    $catatan_mandor = trim($_POST['catatan_mandor']);
    $reject_type = isset($_POST['reject_type']) ? trim($_POST['reject_type']) : 'regular';

    if (empty($catatan_mandor)) {
        $error = "Catatan alasan penolakan wajib diisi.";
    } else {
        try {
            $new_status = ($reject_type === 'manipulation') ? 'pending_manajer_tolak' : 'rejected';

            $stmt = $pdo->prepare("
                UPDATE work_reports 
                SET status = ?, 
                    catatan_mandor = ?, 
                    tanggal_verifikasi_mandor = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$new_status, $catatan_mandor, $report_id]);
            
            if ($new_status === 'pending_manajer_tolak') {
                $success = "Laporan manipulasi berhasil dikirim. Menunggu keputusan & konfirmasi Manajer JEM.";
            } else {
                $success = "Laporan ditolak. Laporan telah dikembalikan ke Karyawan untuk dikirim ulang.";
            }
            
            // Refresh details
            $stmt = $pdo->prepare("
                SELECT r.*, a.tanggal, a.aktivitas, a.target_jumlah, a.unit, k.nama as nama_karyawan
                FROM work_reports r
                JOIN assignments a ON r.id_assignment = a.id
                JOIN karyawan k ON r.id_karyawan = k.id_karyawan
                WHERE r.id = ?
            ");
            $stmt->execute([$report_id]);
            $report = $stmt->fetch();
            
            // Redirect back to dashboard after 2 seconds
            echo "<script>setTimeout(() => { window.location.href = 'index.php'; }, 2000);</script>";
        } catch (\PDOException $e) {
            $error = "Gagal menolak laporan: " . $e->getMessage();
        }
    }
}
?>

<div style="margin-bottom: 30px;">
    <a href="index.php" style="color: var(--text-muted); font-size: 0.9rem;"><i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard</a>
    <h2 style="margin-top: 10px;">Verifikasi Laporan Kerja Karyawan</h2>
    <p style="color: var(--text-muted);">Periksa bukti fisik dan koordinat GPS hasil kerja kelompok mandor Anda</p>
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

<?php if ($report_id === 0): ?>
    <!-- 1. Selection screen (list of pending reports) -->
    <div style="max-width: 850px; margin: 0 auto;">
        <div class="card glass-panel">
            <h3 class="card-title"><i class="fa-solid fa-stamp" style="color: var(--primary);"></i> Daftar Pekerjaan Menunggu Verifikasi Anda</h3>
            
            <?php if (empty($pending_reports)): ?>
                <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                    <i class="fa-solid fa-circle-check" style="font-size: 3rem; color: var(--success); margin-bottom: 15px; display: block;"></i>
                    Semua laporan pekerjaan karyawan kelompok Anda telah terverifikasi.
                </div>
            <?php else: ?>
                <p style="color: var(--text-muted); margin-bottom: 15px; font-size: 0.9rem;">Berikut adalah daftar laporan masuk yang memerlukan pemeriksaan dan tanda tangan verifikasi Anda:</p>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tanggal Kerja</th>
                                <th>Pelaksana (Karyawan)</th>
                                <th>Aktivitas Kerja</th>
                                <th>Pencapaian Realisasi</th>
                                <th>Foto Bukti</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_reports as $item): ?>
                                <tr>
                                    <td><?php echo date('d-m-Y', strtotime($item['tanggal'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($item['nama_karyawan']); ?></strong></td>
                                    <td><span class="badge badge-verified" style="font-size:0.75rem;"><?php echo htmlspecialchars($item['aktivitas']); ?></span></td>
                                    <td>
                                        <strong style="color: var(--primary);"><?php echo (float)$item['jumlah_realisasi'] . ' ' . htmlspecialchars($item['unit']); ?></strong>
                                        <span style="font-size:0.75rem; color:var(--text-muted);">/ <?php echo (float)$item['target_jumlah'] . ' ' . htmlspecialchars($item['unit']); ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($item['foto_bukti'])): ?>
                                            <img src="../<?php echo htmlspecialchars($item['foto_bukti']); ?>" class="img-proof" onclick="openModal('../<?php echo htmlspecialchars($item['foto_bukti']); ?>', 'Bukti Foto: <?php echo htmlspecialchars($item['nama_karyawan']) . ' - ' . htmlspecialchars($item['aktivitas']); ?>')" />
                                        <?php else: ?>
                                            <span style="opacity: 0.5; font-style: italic;">Tidak ada foto</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="verifikasi.php?report_id=<?php echo $item['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fa-solid fa-stamp"></i> Periksa & Verifikasi
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
    <div class="grid-2">
        <!-- Left Column: Report Details & Photo -->
        <div>
            <div class="card glass-panel">
                <h3 class="card-title">Laporan Kerja Lapangan</h3>
                
                <div style="margin-bottom: 20px;">
                    <label style="color: var(--text-muted); font-size: 0.85rem; display:block;">Nama Karyawan</label>
                    <span style="font-size: 1.1rem; font-weight:600;"><?php echo htmlspecialchars($report['nama_karyawan']); ?></span>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                    <div>
                        <label style="color: var(--text-muted); font-size: 0.85rem; display:block;">Tanggal Kerja</label>
                        <span style="font-weight: 500;"><?php echo date('d F Y', strtotime($report['tanggal'])); ?></span>
                    </div>
                    <div>
                        <label style="color: var(--text-muted); font-size: 0.85rem; display:block;">Aktivitas</label>
                        <span style="font-weight: 500;"><?php echo htmlspecialchars($report['aktivitas']); ?></span>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                    <div>
                        <label style="color: var(--text-muted); font-size: 0.85rem; display:block;">Target Penugasan</label>
                        <span style="font-weight: 600; color: var(--primary);"><?php echo (float)$report['target_jumlah'] . ' ' . htmlspecialchars($report['unit']); ?></span>
                    </div>
                    <div>
                        <label style="color: var(--text-muted); font-size: 0.85rem; display:block;">Realisasi Dilaporkan</label>
                        <span style="font-weight: 600; color: var(--primary-light);"><?php echo (float)$report['jumlah_realisasi'] . ' ' . htmlspecialchars($report['unit']); ?></span>
                    </div>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="color: var(--text-muted); font-size: 0.85rem; display:block;">Catatan Karyawan</label>
                    <p style="background: rgba(0,0,0,0.03); border: 1px solid var(--card-border); padding: 10px; border-radius: 6px; font-size: 0.9rem; font-style: italic;">
                        <?php echo !empty($report['catatan_karyawan']) ? htmlspecialchars($report['catatan_karyawan']) : '- Tidak ada catatan -'; ?>
                    </p>
                </div>

                <div>
                    <label style="color: var(--text-muted); font-size: 0.85rem; display:block; margin-bottom: 8px;">Foto Bukti Lapangan (Klik untuk memperbesar)</label>
                    <?php if (!empty($report['foto_bukti'])): ?>
                        <img src="../<?php echo htmlspecialchars($report['foto_bukti']); ?>" 
                             alt="Bukti Foto: <?php echo htmlspecialchars($report['nama_karyawan']) . ' - ' . htmlspecialchars($report['aktivitas']); ?>" 
                             class="img-proof" style="width: 100%; height: auto; max-height: 250px; border-radius: 8px; border: 1px solid var(--card-border);"
                             onclick="openModal('../<?php echo htmlspecialchars($report['foto_bukti']); ?>', 'Bukti Foto: <?php echo htmlspecialchars($report['nama_karyawan']) . ' - ' . htmlspecialchars($report['aktivitas']); ?>')">
                    <?php else: ?>
                        <span style="opacity: 0.5; font-style: italic;">Tidak ada foto bukti diunggah.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Verification Form / Info -->
        <div>
            <div class="card glass-panel">
                <h3 class="card-title">Status & Verifikasi</h3>

                <!-- Verification Form for Pending Mandor -->
                <?php if ($report['status'] === 'pending_mandor'): ?>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="catatan_mandor">Catatan Verifikasi Mandor (Evaluasi kerja / Alasan penolakan)</label>
                            <textarea name="catatan_mandor" id="catatan_mandor" rows="4" class="form-control" placeholder="Tulis catatan verifikasi Anda..." required></textarea>
                        </div>
                        
                        <div class="form-group" style="border: 1px solid var(--card-border); padding: 12px; border-radius: 6px; background: rgba(0,0,0,0.01); margin-bottom: 20px;">
                            <label style="display:block; font-weight:700; margin-bottom: 8px;"><i class="fa-solid fa-list-check" style="color:var(--primary-light);"></i> Kategori Tindakan Penolakan:</label>
                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                <label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                    <input type="radio" name="reject_type" value="regular" checked>
                                    <span><strong>Penolakan Biasa</strong> (Foto blur / salah ketik, kirim ulang tanpa sanksi)</span>
                                </label>
                                <label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                    <input type="radio" name="reject_type" value="manipulation">
                                    <span style="color: var(--danger);"><strong>Indikasi Manipulasi Data</strong> (Sanksi Potong Bonus 10% - Butuh Review JEM)</span>
                                </label>
                            </div>
                        </div>

                        <button type="submit" name="verify_report" class="btn btn-primary" style="width: 100%; margin-bottom: 10px;">
                            <i class="fa-solid fa-square-check"></i> Verifikasi & Kirim Ke Manajer
                        </button>
                        
                        <button type="submit" name="reject_report" class="btn btn-danger" style="width: 100%;" onclick="return confirm('Apakah Anda yakin ingin menolak laporan ini?')">
                            <i class="fa-solid fa-ban"></i> Tolak Pekerjaan
                        </button>
                    </form>
                <?php else: ?>
                    <!-- Readonly verification state -->
                    <div style="margin-top: 10px;">
                        <div style="margin-bottom: 20px;">
                            <label style="color: var(--text-muted); font-size: 0.85rem; display:block;">Status Laporan Saat Ini</label>
                            <?php if ($report['status'] === 'verified_by_mandor'): ?>
                                <span class="badge badge-verified" style="font-size: 0.9rem; padding: 6px 12px; margin-top: 5px;">Terverifikasi Mandor</span>
                                <small style="display:block; color: var(--text-muted); margin-top: 5px;">Menunggu verifikasi akhir & persetujuan Manajer.</small>
                            <?php elseif ($report['status'] === 'approved'): ?>
                                <span class="badge badge-approved" style="font-size: 0.9rem; padding: 6px 12px; margin-top: 5px;">Disetujui Manajer</span>
                                <small style="display:block; color: var(--text-muted); margin-top: 5px;">Pekerjaan ini sudah final disetujui Manajer.</small>
                            <?php elseif ($report['status'] === 'rejected'): ?>
                                <span class="badge badge-logout" style="font-size: 0.9rem; padding: 6px 12px; margin-top: 5px; background:#ffebee; color:#c62828; border:1px solid #ffcdd2;">Ditolak (Rejected)</span>
                                <small style="display:block; color: var(--text-muted); margin-top: 5px;">Laporan telah dikembalikan untuk dilaporkan ulang.</small>
                            <?php elseif ($report['status'] === 'pending_manajer_tolak'): ?>
                                <span class="badge badge-pending" style="font-size: 0.9rem; padding: 6px 12px; margin-top: 5px; background:#fff3e0; color:#e65100; border:1px solid #ffe0b2;">Menunggu Konfirmasi JEM</span>
                                <small style="display:block; color: var(--text-muted); margin-top: 5px;">Indikasi manipulasi dilaporkan. Menunggu keputusan sanksi dari JEM.</small>
                            <?php endif; ?>
                        </div>

                        <div style="border-top: 1px solid var(--card-border); padding-top: 15px; margin-bottom: 20px;">
                            <label style="color: var(--text-muted); font-size: 0.85rem; display:block;">Hasil Pemeriksaan Mandor</label>
                            <p style="background: rgba(0,0,0,0.03); border: 1px solid var(--card-border); padding: 10px; border-radius: 6px; font-size: 0.9rem; margin-top: 5px; font-style: italic;">
                                <?php echo htmlspecialchars($report['catatan_mandor']); ?>
                            </p>
                            <small style="color: var(--text-muted);">
                                Diverifikasi pada: <?php echo date('d-m-Y H:i', strtotime($report['tanggal_verifikasi_mandor'])); ?>
                            </small>
                        </div>

                        <?php if ($report['status'] === 'approved'): ?>
                            <div style="border-top: 1px solid var(--card-border); padding-top: 15px;">
                                <label style="color: var(--text-muted); font-size: 0.85rem; display:block;">Catatan Persetujuan Manajer</label>
                                <p style="background: var(--gold-light); border: 1px solid var(--card-border); padding: 10px; border-radius: 6px; font-size: 0.9rem; margin-top: 5px; font-style: italic;">
                                    <?php echo htmlspecialchars($report['catatan_manajer']); ?>
                                </p>
                                <small style="color: var(--text-muted);">
                                    Disetujui pada: <?php echo date('d-m-Y H:i', strtotime($report['tanggal_verifikasi_manajer'])); ?>
                                </small>
                            </div>
                        <?php endif; ?>
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
