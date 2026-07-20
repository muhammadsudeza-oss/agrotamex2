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

<!-- Welcome Banner -->
<div class="card glass-panel" style="background: linear-gradient(135deg, #1e3d59 0%, #17b978 100%); border-left: 5px solid var(--primary-light); color: #fff; padding: 25px; margin-bottom: 25px; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; overflow: hidden; position: relative; box-shadow: 0 8px 32px rgba(23,185,120,0.15);">
    <div style="position: absolute; right: -20px; top: -30px; opacity: 0.12; font-size: 8rem; color: #fff; transform: rotate(15deg); pointer-events: none;">
        <i class="fa-solid fa-list-check"></i>
    </div>
    
    <div style="position: relative; z-index: 2;">
        <div style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; color: #e8fffa; font-weight: 700; margin-bottom: 5px;">Panel Pengawas Lapangan (Mandor)</div>
        <h2 style="margin: 0; font-size: 1.6rem; color: #fff; font-weight: 700;">Selamat Bekerja, Mandor <?php echo htmlspecialchars($_SESSION['nama'] ?? 'Mandor'); ?>! 📋</h2>
        <p style="margin: 10px 0 0 0; font-size: 0.88rem; color: #e8fffa; max-width: 600px; line-height: 1.5;">
            Pantau dan verifikasi hasil kerja harian karyawan bimbingan Anda. Pastikan keabsahan foto bukti fisik panen serta keselarasan lokasi koordinat GPS secara real-time.
        </p>
    </div>
    <div class="no-print" style="position: relative; z-index: 2; text-align: right; background: rgba(255,255,255,0.08); padding: 12px 18px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(5px);">
        <div style="font-size: 0.72rem; color: #e8fffa; text-transform: uppercase; font-weight: 600;">Hari Pengawasan</div>
        <div style="font-size: 1.1rem; font-weight: bold; margin-top: 3px; color: #fff;"><i class="fa-regular fa-calendar-check"></i> <?php echo date('d M Y'); ?></div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Stats Overview -->
<div class="grid-3" style="margin-bottom: 25px;">
    <!-- Verification Pending Card -->
    <a href="#supervised-reports-section" style="text-decoration: none; color: inherit;">
        <div class="card glass-panel" style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; border-left: 5px solid var(--gold); margin: 0; transition: transform 0.2s ease; cursor: pointer;">
            <div style="background: rgba(212, 175, 55, 0.1); padding: 12px; border-radius: 50%; display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; flex-shrink: 0; color: var(--gold); font-size: 1.3rem;">
                <i class="fa-solid fa-list-check"></i>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Verifikasi Pending</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--text-color); margin-top: 2px;">
                    <?php echo $pending_verifications; ?> <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-muted);">laporan</span>
                </div>
            </div>
        </div>
    </a>

    <!-- Total Employees Under Supervision Card -->
    <div class="card glass-panel" style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; border-left: 5px solid var(--primary-light); margin: 0;">
        <div style="background: rgba(110, 192, 136, 0.1); padding: 12px; border-radius: 50%; display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; flex-shrink: 0; color: var(--primary-light); font-size: 1.3rem;">
            <i class="fa-solid fa-users-gear"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Karyawan Bimbingan</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--text-color); margin-top: 2px;">
                <?php echo $total_employees; ?> <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-muted);">orang</span>
            </div>
        </div>
    </div>

    <!-- Role Card -->
    <div class="card glass-panel" style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; border-left: 5px solid #29b6f6; margin: 0;">
        <div style="background: rgba(41, 182, 246, 0.1); padding: 12px; border-radius: 50%; display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; flex-shrink: 0; color: #29b6f6; font-size: 1.3rem;">
            <i class="fa-solid fa-user-shield"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Grup Pengawas</div>
            <div style="font-size: 1.4rem; font-weight: 700; color: var(--text-color); margin-top: 2px;">
                MANDOR
            </div>
        </div>
    </div>
</div>

<!-- Supervised Employee Reports -->
<div id="supervised-reports-section" class="card glass-panel" style="padding: 20px;">
    <h3 class="card-title" style="margin-bottom: 15px;"><i class="fa-solid fa-user-check" style="color: var(--primary);"></i> Daftar Laporan Pekerjaan Karyawan</h3>
    
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
                        <th>Hasil</th>
                        <th>Catatan & Koordinat GPS</th>
                        <th>Bukti Foto</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $rep): 
                        // Override realization display if report has a penalty
                        $realisasi_display = $rep['jumlah_realisasi'];
                        if ($rep['potongan_penalti'] > 0) {
                            $realisasi_display = 0;
                        }
                    ?>
                        <tr>
                            <td><?php echo date('d-m-Y', strtotime($rep['tanggal'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($rep['nama_karyawan']); ?></strong></td>
                            <td><?php echo htmlspecialchars($rep['aktivitas']); ?></td>
                            <td><?php echo (float)$rep['target_jumlah'] . ' ' . htmlspecialchars($rep['unit']); ?></td>
                            <td><?php echo (float)$realisasi_display . ' ' . htmlspecialchars($rep['unit']); ?></td>
                            <td>
                                <?php 
                                $lat = '';
                                $lng = '';
                                $clean_notes = $rep['catatan_karyawan'];
                                if (preg_match('/\[Lokasi GPS(?:\s*Terkunci)?:\s*Lat\s*([eE\d\.-]+)\s*\|\s*Lng\s*([eE\d\.-]+)\](.*)/is', $rep['catatan_karyawan'], $matches)) {
                                    $lat = trim($matches[1]);
                                    $lng = trim($matches[2]);
                                    $clean_notes = trim($matches[3]);
                                }
                                ?>
                                <div class="gps-container-cell" data-lat="<?php echo $lat; ?>" data-lng="<?php echo $lng; ?>" style="font-size: 0.85rem; max-width: 250px; word-wrap: break-word;">
                                    <?php if (!empty($clean_notes)): ?>
                                        <strong>Catatan:</strong> <?php echo nl2br(htmlspecialchars($clean_notes)); ?><br>
                                    <?php endif; ?>
                                    <?php if (!empty($lat) && !empty($lng)): ?>
                                        <span style="color:var(--text-muted); font-size: 0.75rem; display:block; margin-bottom: 2px;">
                                            <i class="fa-solid fa-location-crosshairs"></i> Lat: <?php echo htmlspecialchars($lat); ?>, Lng: <?php echo htmlspecialchars($lng); ?>
                                        </span>
                                        <span class="address-text" style="color: #728c7f; font-size: 0.75rem; font-weight: 600; display:block;"><i class="fa-solid fa-spinner fa-spin"></i> Mencari nama tempat...</span>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted); font-size:0.75rem; font-style:italic;">GPS tidak terdeteksi</span>
                                    <?php endif; ?>
                                </div>
                            </td>
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

<script>
// Asynchronously resolve GPS coordinates to place names for table rows
document.addEventListener('DOMContentLoaded', () => {
    const gpsContainers = document.querySelectorAll('.gps-container-cell');
    gpsContainers.forEach((container, idx) => {
        const lat = container.getAttribute('data-lat');
        const lng = container.getAttribute('data-lng');
        const addressSpan = container.querySelector('.address-text');
        
        if (!lat || !lng || !addressSpan) return;
        
        // Timeout delay of 1 second per row to respect Nominatim rate limiting policy (1 req/sec)
        setTimeout(() => {
            fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`)
                .then(res => res.json())
                .then(data => {
                    if (data && data.address) {
                        let parts = [];
                        if (data.address.village) parts.push(data.address.village);
                        else if (data.address.suburb) parts.push(data.address.suburb);
                        else if (data.address.town) parts.push(data.address.town);
                        
                        if (data.address.city_district) parts.push(data.address.city_district);
                        else if (data.address.district) parts.push(data.address.district);
                        
                        if (data.address.city) parts.push(data.address.city);
                        else if (data.address.county) parts.push(data.address.county);
                        
                        if (data.address.state) parts.push(data.address.state);
                        
                        if (parts.length > 0) {
                            addressSpan.innerHTML = `<i class="fa-solid fa-location-dot" style="color:var(--primary-light);"></i> ${parts.join(', ')}`;
                        } else {
                            addressSpan.innerHTML = `<i class="fa-solid fa-location-dot" style="color:var(--primary-light);"></i> ${data.display_name}`;
                        }
                    } else {
                        addressSpan.innerHTML = `<i class="fa-solid fa-location-dot" style="color:var(--primary-light);"></i> GPS: ${parseFloat(lat).toFixed(5)}, ${parseFloat(lng).toFixed(5)}`;
                    }
                })
                .catch(err => {
                    addressSpan.innerHTML = `<i class="fa-solid fa-location-dot" style="color:var(--primary-light);"></i> GPS: ${parseFloat(lat).toFixed(5)}, ${parseFloat(lng).toFixed(5)}`;
                });
        }, idx * 1000);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
