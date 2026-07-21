<?php
// mandor/index.php
// DASHBOARD MANDOR: Panel Pengawasan Lapangan dengan Tampilan Rapi & Modern.
require_once '../config/koneksi.php';
require_once '../includes/header.php';

// Validate role
if ($_SESSION['role'] !== 'mandor') {
    header("Location: ../index.php");
    exit;
}

$mandor_id = $_SESSION['user_id'];
$nama_mandor = $_SESSION['nama'] ?? 'Mandor';
$error = "";

try {
    // 1. Fetch statistics
    // Pending reports waiting for foreman verification
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending_count 
        FROM work_reports r
        JOIN assignments a ON r.id_assignment = a.id
        WHERE a.id_mandor = ? AND r.status = 'pending_mandor'
    ");
    $stmt->execute([$mandor_id]);
    $stats = $stmt->fetch();
    $pending_verifications = $stats['pending_count'] ?? 0;

    // Verified today count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as verified_today 
        FROM work_reports r
        JOIN assignments a ON r.id_assignment = a.id
        WHERE a.id_mandor = ? AND r.tanggal_verifikasi_mandor >= CURDATE()
    ");
    $stmt->execute([$mandor_id]);
    $v_stats = $stmt->fetch();
    $verified_today = $v_stats['verified_today'] ?? 0;

    // Total distinct active employees under this foreman
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
        SELECT r.*, a.tanggal, a.aktivitas, a.target_jumlah, a.unit, k.nama as nama_karyawan, m.nama as nama_mandor
        FROM work_reports r
        JOIN assignments a ON r.id_assignment = a.id
        JOIN karyawan k ON r.id_karyawan = k.id_karyawan
        JOIN mandor m ON a.id_mandor = m.id_mandor
        WHERE a.id_mandor = ?
        ORDER BY (r.status = 'pending_mandor') DESC, a.tanggal DESC, r.created_at DESC
        LIMIT 30
    ");
    $stmt->execute([$mandor_id]);
    $reports = $stmt->fetchAll();

} catch (\PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

$status_labels = [
    'pending_mandor'        => 'Menunggu Mandor',
    'verified_by_mandor'    => 'Terverifikasi Mandor',
    'approved'              => 'Disetujui Manajer',
    'rejected'              => 'Ditolak (Sanksi 10%)',
    'pending_manajer_tolak' => 'Tinjauan Sanksi 10%',
];
$status_colors = [
    'pending_mandor'        => '#e0a800',
    'verified_by_mandor'    => '#0d6efd',
    'approved'              => '#2e7d32',
    'rejected'              => '#c62828',
    'pending_manajer_tolak' => '#e65100',
];
?>

<!-- Welcome Banner (Dark Theme Accent) -->
<div class="card glass-panel" style="background: linear-gradient(135deg, #1c1f26 0%, #2a303c 100%); border-left: 5px solid var(--primary-light); color: #fff; padding: 22px 25px; margin-bottom: 25px; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; position: relative; overflow: hidden; box-shadow: 0 8px 25px rgba(0,0,0,0.15);">
    <div style="position: absolute; right: -15px; top: -25px; opacity: 0.08; font-size: 8rem; color: #fff; pointer-events: none;">
        <i class="fa-solid fa-list-check"></i>
    </div>
    
    <div style="position: relative; z-index: 2;">
        <div style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 1px; color: var(--gold); font-weight: 700; margin-bottom: 4px;">
            <i class="fa-solid fa-clipboard-check"></i> Panel Pengawasan Mandor Lapangan
        </div>
        <h2 style="margin: 0; font-size: 1.5rem; color: #ffffff; font-weight: 700;">Selamat Datang, Mandor <?php echo htmlspecialchars($nama_mandor); ?>!</h2>
        <p style="margin: 6px 0 0 0; font-size: 0.85rem; color: #cbd5e1; max-width: 650px; line-height: 1.5;">
            Periksa keabsahan foto bukti fisik sawit dan koordinat lokasi GPS pelaporan harian karyawan bimbingan Anda.
        </p>
    </div>

    <div class="no-print" style="position: relative; z-index: 2; display: flex; gap: 10px;">
        <a href="laporan.php" class="btn btn-gold" style="padding: 9px 16px; font-size: 0.82rem; border-radius: 8px; font-weight: 600; display: flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-chart-column"></i> Histori Pengawasan
        </a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Grid statistik 4 Kolom Rapi -->
<div class="grid-4" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px;">
    <!-- 1. Pending Verifikasi -->
    <a href="#supervised-reports-section" style="text-decoration: none; color: inherit;">
        <div class="card glass-panel" style="padding: 16px; border-top: 3.5px solid #e0a800; margin: 0; transition: transform 0.2s ease; cursor: pointer;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.72rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Pending Verifikasi</div>
                    <div style="font-size: 1.6rem; font-weight: 700; color: #e0a800; margin-top: 4px;">
                        <?php echo $pending_verifications; ?> <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-muted);">laporan</span>
                    </div>
                </div>
                <div style="background: rgba(224, 168, 0, 0.12); width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #e0a800; font-size: 1.3rem;">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
            </div>
        </div>
    </a>

    <!-- 2. Diverifikasi Hari Ini -->
    <div class="card glass-panel" style="padding: 16px; border-top: 3.5px solid #0d6efd; margin: 0;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <div style="font-size: 0.72rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Diverifikasi Hari Ini</div>
                <div style="font-size: 1.6rem; font-weight: 700; color: #0d6efd; margin-top: 4px;">
                    <?php echo $verified_today; ?> <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-muted);">laporan</span>
                </div>
            </div>
            <div style="background: rgba(13, 110, 253, 0.12); width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #0d6efd; font-size: 1.3rem;">
                <i class="fa-solid fa-user-check"></i>
            </div>
        </div>
    </div>

    <!-- 3. Karyawan Bimbingan -->
    <div class="card glass-panel" style="padding: 16px; border-top: 3.5px solid var(--primary-light); margin: 0;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <div style="font-size: 0.72rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Karyawan Bimbingan</div>
                <div style="font-size: 1.6rem; font-weight: 700; color: var(--primary-light); margin-top: 4px;">
                    <?php echo $total_employees; ?> <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-muted);">orang</span>
                </div>
            </div>
            <div style="background: rgba(46, 125, 50, 0.12); width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--primary-light); font-size: 1.3rem;">
                <i class="fa-solid fa-users-gear"></i>
            </div>
        </div>
    </div>

    <!-- 4. Tanggal Hari Ini -->
    <div class="card glass-panel" style="padding: 16px; border-top: 3.5px solid var(--gold); margin: 0;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <div style="font-size: 0.72rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Tanggal Pengawasan</div>
                <div style="font-size: 1.1rem; font-weight: 700; color: var(--text-color); margin-top: 8px;">
                    <?php echo date('d F Y'); ?>
                </div>
            </div>
            <div style="background: rgba(212, 175, 55, 0.12); width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--gold); font-size: 1.3rem;">
                <i class="fa-regular fa-calendar-check"></i>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Laporan Pekerjaan Karyawan -->
<div id="supervised-reports-section" class="card glass-panel" style="padding: 22px;">
    <div class="card-title" style="margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center;">
        <span><i class="fa-solid fa-list-check" style="color: var(--primary);"></i> Daftar Pelaporan Kerja Karyawan Lapangan</span>
        <span class="badge" style="background: rgba(46,125,50,0.1); color: var(--primary); font-size: 0.8rem;">Total: <?php echo count($reports); ?> Laporan</span>
    </div>
    
    <?php if (empty($reports)): ?>
        <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
            <i class="fa-solid fa-folder-open" style="font-size: 3rem; margin-bottom: 12px; display: block; color: var(--text-muted);"></i>
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
                        <th>Catatan &amp; Lokasi GPS</th>
                        <th>Bukti Foto</th>
                        <th>Status Alur</th>
                        <th class="no-print" style="text-align: center;">Aksi &amp; Verifikasi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $rep): 
                        $realisasi_display = (float)$rep['jumlah_realisasi'];
                        if ((float)$rep['potongan_penalti'] > 0) {
                            $realisasi_display = 0;
                        }

                        $lat = '';
                        $lng = '';
                        $clean_notes = $rep['catatan_karyawan'];
                        if (preg_match('/\[Lokasi GPS(?:\s*Terkunci)?:\s*Lat\s*([eE\d\.-]+)\s*\|\s*Lng\s*([eE\d\.-]+)\](.*)/is', $rep['catatan_karyawan'], $matches)) {
                            $lat = trim($matches[1]);
                            $lng = trim($matches[2]);
                            $clean_notes = trim($matches[3]);
                        }

                        $detail_json = htmlspecialchars(json_encode([
                            'nama_karyawan'    => $rep['nama_karyawan'],
                            'tanggal_kerja'    => date('d F Y', strtotime($rep['tanggal'])),
                            'aktivitas'        => $rep['aktivitas'],
                            'target_jumlah'    => (float)$rep['target_jumlah'],
                            'unit'             => $rep['unit'],
                            'jumlah_realisasi' => (float)$rep['jumlah_realisasi'],
                            'catatan_karyawan' => $rep['catatan_karyawan'] ?? '',
                            'foto_bukti'       => !empty($rep['foto_bukti']) ? '../' . $rep['foto_bukti'] : '',
                            'status'           => $rep['status'],
                            'status_label'     => $status_labels[$rep['status']] ?? $rep['status'],
                            'catatan_mandor'   => $rep['catatan_mandor'] ?? '',
                            'waktu_mandor'     => $rep['tanggal_verifikasi_mandor'] ? date('d-m-Y H:i', strtotime($rep['tanggal_verifikasi_mandor'])) : '',
                            'catatan_manajer'  => $rep['catatan_manajer'] ?? '',
                            'waktu_manajer'    => $rep['tanggal_verifikasi_manajer'] ? date('d-m-Y H:i', strtotime($rep['tanggal_verifikasi_manajer'])) : '',
                            'bonus_diterima'   => (float)($rep['bonus_diterima'] ?? 0),
                        ]), ENT_QUOTES, 'UTF-8');
                    ?>
                        <tr>
                            <td><?php echo date('d-m-Y', strtotime($rep['tanggal'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($rep['nama_karyawan']); ?></strong></td>
                            <td><?php echo htmlspecialchars($rep['aktivitas']); ?></td>
                            <td><?php echo (float)$rep['target_jumlah'] . ' ' . htmlspecialchars($rep['unit']); ?></td>
                            <td><strong><?php echo (float)$realisasi_display . ' ' . htmlspecialchars($rep['unit']); ?></strong></td>
                            <td>
                                <div class="gps-container-cell" data-lat="<?php echo $lat; ?>" data-lng="<?php echo $lng; ?>" style="font-size: 0.83rem; max-width: 240px; word-wrap: break-word;">
                                    <?php if (!empty($clean_notes)): ?>
                                        <span style="font-style: italic; color: var(--text-color); display:block; margin-bottom: 2px;">"<?php echo htmlspecialchars($clean_notes); ?>"</span>
                                    <?php endif; ?>
                                    <?php if (!empty($lat) && !empty($lng)): ?>
                                        <span class="address-text" style="color: #728c7f; font-size: 0.74rem; font-weight: 600; display:block;"><i class="fa-solid fa-spinner fa-spin"></i> Mencari alamat...</span>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted); font-size:0.75rem; font-style:italic;">GPS tidak terdeteksi</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($rep['foto_bukti'])): ?>
                                    <img src="../<?php echo htmlspecialchars($rep['foto_bukti']); ?>" 
                                         alt="Bukti Foto: <?php echo htmlspecialchars($rep['nama_karyawan']); ?>" 
                                         class="img-proof" 
                                         onclick="openModal('../<?php echo htmlspecialchars($rep['foto_bukti']); ?>', 'Bukti Foto: <?php echo htmlspecialchars($rep['nama_karyawan']); ?>')"
                                         style="width: 50px; height: 38px; object-fit: cover; border-radius: 6px; cursor: pointer; border: 1px solid #cbd5e1;">
                                <?php else: ?>
                                    <span style="color:var(--text-muted); font-size:0.78rem; font-style:italic;">Tidak ada</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge" style="background: <?php echo $status_colors[$rep['status']]; ?>1a; color: <?php echo $status_colors[$rep['status']]; ?>; border: 1px solid <?php echo $status_colors[$rep['status']]; ?>44; font-size: 0.76rem;">
                                    <?php echo $status_labels[$rep['status']] ?? $rep['status']; ?>
                                </span>
                            </td>
                            <td class="no-print" style="text-align: center;">
                                <div style="display: flex; gap: 4px; justify-content: center; align-items: center;">
                                    <?php if ($rep['status'] === 'pending_mandor'): ?>
                                        <a href="verifikasi.php?report_id=<?php echo $rep['id']; ?>" class="btn btn-primary btn-sm" style="padding: 4px 10px; font-size: 0.76rem; border-radius: 6px;">
                                            <i class="fa-solid fa-user-check"></i> Periksa
                                        </a>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick='openReportDetailModal(<?php echo $detail_json; ?>)' style="padding: 4px 10px; font-size: 0.76rem; border-radius: 6px;">
                                        <i class="fa-solid fa-eye" style="color: var(--primary-light);"></i> Detail
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const gpsContainers = document.querySelectorAll('.gps-container-cell');
    gpsContainers.forEach((container, idx) => {
        const lat = container.getAttribute('data-lat');
        const lng = container.getAttribute('data-lng');
        const addressSpan = container.querySelector('.address-text');
        
        if (!lat || !lng || !addressSpan) return;
        
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
                        
                        if (parts.length > 0) {
                            addressSpan.innerHTML = `<i class="fa-solid fa-location-dot" style="color:var(--primary-light);"></i> ${parts.join(', ')}`;
                        } else {
                            addressSpan.innerHTML = `<i class="fa-solid fa-location-dot" style="color:var(--primary-light);"></i> ${data.display_name}`;
                        }
                    } else {
                        addressSpan.innerHTML = `<i class="fa-solid fa-location-dot" style="color:var(--primary-light);"></i> GPS: ${parseFloat(lat).toFixed(4)}, ${parseFloat(lng).toFixed(4)}`;
                    }
                })
                .catch(err => {
                    addressSpan.innerHTML = `<i class="fa-solid fa-location-dot" style="color:var(--primary-light);"></i> GPS: ${parseFloat(lat).toFixed(4)}, ${parseFloat(lng).toFixed(4)}`;
                });
        }, idx * 1000);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
