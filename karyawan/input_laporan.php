<?php
// karyawan/input_laporan.php
require_once '../config/koneksi.php';
require_once '../includes/header.php';

// Validate role
if ($_SESSION['role'] !== 'karyawan') {
    header("Location: ../index.php");
    exit;
}

$karyawan_id = $_SESSION['user_id'];
$assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;
$error = "";
$success = "";
$assignment = null;
$pending_assignments = [];

if ($assignment_id > 0) {
    // 1. Validate specific assignment details
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, m.nama as nama_mandor 
            FROM assignments a
            JOIN mandor m ON a.id_mandor = m.id_mandor
            WHERE a.id = ? AND a.id_karyawan = ?
        ");
        $stmt->execute([$assignment_id, $karyawan_id]);
        $assignment = $stmt->fetch();

        if (!$assignment) {
            header("Location: input_laporan.php");
            exit;
        }

        // Check if report already submitted (allow if status is rejected AND potongan_penalti = 0)
        $stmt = $pdo->prepare("SELECT id, status, potongan_penalti FROM work_reports WHERE id_assignment = ? AND id_karyawan = ?");
        $stmt->execute([$assignment_id, $karyawan_id]);
        $existing_rep = $stmt->fetch();
        if ($existing_rep) {
            if ($existing_rep['status'] !== 'rejected' || $existing_rep['potongan_penalti'] > 0) {
                header("Location: index.php");
                exit;
            }
        }

    } catch (\PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
} else {
    // No assignment ID provided. Fetch pending assignments for this employee
    try {
        $stmt = $pdo->prepare("
            SELECT a.id, a.tanggal, a.aktivitas, a.target_jumlah, a.unit, m.nama as nama_mandor, r.status as report_status, r.potongan_penalti
            FROM assignments a
            JOIN mandor m ON a.id_mandor = m.id_mandor
            LEFT JOIN work_reports r ON a.id = r.id_assignment
            WHERE a.id_karyawan = ? AND (r.id IS NULL OR (r.status = 'rejected' AND r.potongan_penalti = 0))
            ORDER BY a.tanggal DESC
        ");
        $stmt->execute([$karyawan_id]);
        $pending_assignments = $stmt->fetchAll();
    } catch (\PDOException $e) {
        $error = "Gagal memuat daftar penugasan: " . $e->getMessage();
    }
}

// 2. Process Form Submission (Base64 Camera Photo Upload + GPS Coordinates)
if (isset($_POST['submit_report'])) {
    $jumlah_realisasi = (float)$_POST['jumlah_realisasi'];
    $catatan_karyawan = trim($_POST['catatan_karyawan']);
    $photo_base64 = $_POST['photo_base64'];
    $lat = isset($_POST['lat']) ? trim($_POST['lat']) : '';
    $lng = isset($_POST['lng']) ? trim($_POST['lng']) : '';
    
    $file_saved = false;
    $db_photo_path = "";

    if (empty($jumlah_realisasi) || $jumlah_realisasi <= 0) {
        $error = "Jumlah realisasi harus lebih dari 0.";
    } elseif (empty($photo_base64)) {
        $error = "Wajib mengambil foto bukti pekerjaan menggunakan kamera.";
    } elseif (empty($lat) || empty($lng)) {
        $error = "Gagal mengirim data. Koordinat lokasi GPS tidak terdeteksi.";
    } else {
        // Decode Base64 image
        if (preg_match('/^data:image\/(\w+);base64,/', $photo_base64, $type)) {
            $photo_base64 = substr($photo_base64, strpos($photo_base64, ',') + 1);
        }
        $image_data = base64_decode($photo_base64);
        
        if ($image_data === false) {
            $error = "Data gambar rusak atau tidak valid.";
        } else {
            $upload_dir = "../assets/uploads/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique name
            $new_filename = "report_camera_" . $assignment_id . "_" . time() . ".jpg";
            $target_file = $upload_dir . $new_filename;
            
            if (file_put_contents($target_file, $image_data)) {
                $file_saved = true;
                $db_photo_path = "assets/uploads/" . $new_filename;
            } else {
                $error = "Gagal menulis file gambar ke server.";
            }
        }
    }

    if ($file_saved && empty($error)) {
        try {
            // Append GPS location coordinates to employee notes for double verification
            $gps_verification_notes = "[Lokasi GPS Terkunci: Lat " . $lat . " | Lng " . $lng . "] " . $catatan_karyawan;
            
            // Check if we need to update or insert
            $stmt = $pdo->prepare("SELECT id FROM work_reports WHERE id_assignment = ? AND id_karyawan = ?");
            $stmt->execute([$assignment_id, $karyawan_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update rejected report and reset fields
                $stmt = $pdo->prepare("
                    UPDATE work_reports 
                    SET jumlah_realisasi = ?, 
                        foto_bukti = ?, 
                        catatan_karyawan = ?, 
                        status = 'pending_mandor',
                        catatan_mandor = NULL,
                        tanggal_verifikasi_mandor = NULL,
                        catatan_manajer = NULL,
                        tanggal_verifikasi_manajer = NULL,
                        bonus_diterima = 0.00
                    WHERE id = ?
                ");
                $stmt->execute([$jumlah_realisasi, $db_photo_path, $gps_verification_notes, $existing['id']]);
            } else {
                // Insert new report
                $stmt = $pdo->prepare("
                    INSERT INTO work_reports (id_assignment, id_karyawan, jumlah_realisasi, foto_bukti, catatan_karyawan, status) 
                    VALUES (?, ?, ?, ?, ?, 'pending_mandor')
                ");
                $stmt->execute([$assignment_id, $karyawan_id, $jumlah_realisasi, $db_photo_path, $gps_verification_notes]);
            }
            
            $success = "Laporan pekerjaan ber-watermark GPS berhasil dikirim ke Mandor.";
            
            // Redirect to dashboard after 2 seconds
            echo "<script>setTimeout(() => { window.location.href = 'index.php'; }, 2000);</script>";
        } catch (\PDOException $e) {
            $error = "Gagal menyimpan laporan ke database: " . $e->getMessage();
        }
    }
}
?>

<div style="margin-bottom: 30px;">
    <a href="index.php" style="color: var(--text-muted); font-size: 0.9rem;"><i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard</a>
    <h2 style="margin-top: 10px;">Input Laporan Hasil Kerja</h2>
    <p style="color: var(--text-muted);">Ambil foto bukti fisik langsung di lapangan dengan validasi kamera anti-manipulasi & GPS</p>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger" style="max-width: 650px; margin: 0 auto 20px auto;">
        <i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success" style="max-width: 650px; margin: 0 auto 20px auto;">
        <i class="fa-solid fa-circle-check"></i> <?php echo $success; ?>
    </div>
<?php endif; ?>

<?php if ($assignment_id === 0): ?>
    <!-- 1. Selection screen (list of pending targets) -->
    <div style="max-width: 800px; margin: 0 auto;">
        <div class="card glass-panel">
            <h3 class="card-title"><i class="fa-solid fa-list-check" style="color: var(--primary);"></i> Pilih Penugasan Yang Akan Dilaporkan</h3>
            
            <?php if (empty($pending_assignments)): ?>
                <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                    <i class="fa-solid fa-clipboard-check" style="font-size: 3rem; color: var(--primary-light); margin-bottom: 15px; display: block;"></i>
                    Semua penugasan kerja Anda telah dilaporkan atau belum ada penugasan baru saat ini.
                </div>
            <?php else: ?>
                <p style="color: var(--text-muted); margin-bottom: 15px; font-size: 0.9rem;">Berikut adalah daftar penugasan aktif Anda yang belum dilaporkan. Silakan pilih tugas untuk membuka kamera:</p>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tanggal Tugas</th>
                                <th>Aktivitas Kerja</th>
                                <th>Target Kerja</th>
                                <th>Mandor Pengawas</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_assignments as $item): ?>
                                <tr>
                                    <td><?php echo date('d-m-Y', strtotime($item['tanggal'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['aktivitas']); ?></strong>
                                        <?php if ($item['report_status'] === 'rejected'): ?>
                                            <span class="badge badge-logout" style="font-size:0.7rem; background:#ffebee; color:#c62828; padding:2px 6px; border:1px solid #ffcdd2; margin-left:8px;">Ditolak</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong style="color: var(--primary);"><?php echo (float)$item['target_jumlah'] . ' ' . htmlspecialchars($item['unit']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($item['nama_mandor']); ?></td>
                                    <td>
                                        <a href="input_laporan.php?assignment_id=<?php echo $item['id']; ?>" class="btn <?php echo $item['report_status'] === 'rejected' ? 'btn-danger' : 'btn-primary'; ?> btn-sm">
                                            <i class="fa-solid <?php echo $item['report_status'] === 'rejected' ? 'fa-rotate-left' : 'fa-camera'; ?>"></i> <?php echo $item['report_status'] === 'rejected' ? 'Laporkan Ulang' : 'Mulai Laporkan'; ?>
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
    <!-- 2. Camera Input View -->
    <div style="max-width: 650px; margin: 0 auto;">
        <div class="card glass-panel">
            <h3 class="card-title">Informasi Penugasan</h3>
            <div style="background: var(--gold-light); padding: 15px; border-radius: 8px; margin-bottom: 25px; font-size: 0.9rem; border: 1px solid var(--card-border);">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span>Aktivitas Kerja:</span>
                    <strong style="color: var(--primary);"><?php echo htmlspecialchars($assignment['aktivitas']); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span>Target Harian:</span>
                    <strong style="color: var(--primary-light);"><?php echo (float)$assignment['target_jumlah'] . ' ' . htmlspecialchars($assignment['unit']); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span>Mandor Pengawas:</span>
                    <strong style="color: var(--primary);"><?php echo htmlspecialchars($assignment['nama_mandor']); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Tanggal Tugas:</span>
                    <strong style="color: var(--primary);"><?php echo date('d F Y', strtotime($assignment['tanggal'])); ?></strong>
                </div>
            </div>

            <!-- Form Laporan -->
            <form method="POST" action="" onsubmit="return validateForm()">
                <!-- Hidden Fields for Base64 image and GPS coordinates -->
                <input type="hidden" name="photo_base64" id="photo_base64" required>
                <input type="hidden" name="lat" id="lat">
                <input type="hidden" name="lng" id="lng">

                <div class="form-group">
                    <label for="jumlah_realisasi">Hasil Realisasi Lapangan (dalam <?php echo htmlspecialchars($assignment['unit']); ?>)</label>
                    <input type="number" step="0.01" name="jumlah_realisasi" id="jumlah_realisasi" class="form-control" placeholder="Contoh: 1.65" required autofocus>
                </div>

                <!-- Kamera Panel Container -->
                <div class="form-group" style="border: 1px solid var(--card-border); padding: 15px; border-radius: 8px; background: rgba(46,125,50,0.02); text-align: center;">
                    <label style="display:block; margin-bottom: 12px; font-weight:700; color: var(--primary); text-align: left;">
                        <i class="fa-solid fa-camera"></i> Bukti Foto Lapangan (Kamera Langsung & GPS Watermark)
                    </label>

                    <!-- Camera Stream View -->
                    <div style="position: relative; width: 100%; max-width: 420px; margin: 0 auto; overflow: hidden; border-radius: 8px; border: 2px solid var(--card-border); background: #000; aspect-ratio: 4/3;">
                        <!-- Camera Placeholder Overlay -->
                        <div id="camera-placeholder" style="position: absolute; top:0; left:0; width:100%; height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; padding: 20px; background: rgba(0,0,0,0.85); color: #fff; z-index: 5;">
                            <i class="fa-solid fa-camera-slash" style="font-size: 2.5rem; margin-bottom: 12px; color: rgba(255,255,255,0.6);"></i>
                            <span style="font-size: 0.9rem; text-align:center; font-weight:500;">Kamera Belum Aktif</span>
                            <button type="button" class="btn btn-gold btn-sm" id="btnStartCamera" onclick="startCamera()" style="margin-top: 15px; padding: 8px 16px;">
                                <i class="fa-solid fa-video"></i> Aktifkan Kamera
                            </button>
                        </div>

                        <video id="video" autoplay playsinline style="width: 100%; height: 100%; object-fit: cover;"></video>
                        
                        <!-- Captured Preview image -->
                        <img id="result-image" style="display:none; width: 100%; height: 100%; object-fit: cover;" />
                    </div>

                    <!-- Canvas for watermark overlay (hidden) -->
                    <canvas id="canvas" style="display:none;"></canvas>

                    <!-- Camera controls buttons -->
                    <div style="margin-top: 15px; max-width: 420px; margin-left: auto; margin-right: auto;">
                        <button type="button" class="btn btn-primary" id="btnCapture" onclick="takePhoto()" disabled style="width: 100%; padding: 12px;">
                            <i class="fa-solid fa-camera-retro"></i> 📸 AMBIL FOTO SEKARANG
                        </button>
                        
                        <button type="button" class="btn btn-secondary" id="btnRetake" onclick="retakePhoto()" style="display:none; width: 100%; padding: 12px;">
                            <i class="fa-solid fa-rotate-left"></i> 🔄 ULANGI AMBIL FOTO
                        </button>
                    </div>

                    <!-- Geolocation status messages -->
                    <div id="gpsStatus" style="font-size: 0.8rem; color: #728c7f; font-weight: 600; margin-top: 10px;">
                        <i class="fa-solid fa-location-crosshairs fa-spin"></i> Mencari Lokasi GPS...
                    </div>
                    <div id="errorMsg" style="font-size: 0.8rem; color: var(--danger); font-weight: 600; margin-top: 5px;"></div>
                </div>

                <div class="form-group">
                    <label for="catatan_karyawan">Catatan Tambahan Pekerjaan (Opsional)</label>
                    <textarea name="catatan_karyawan" id="catatan_karyawan" rows="3" class="form-control" placeholder="Tulis catatan kondisi lapangan, kendala, dll..."></textarea>
                </div>

                <button type="submit" name="submit_report" id="btnSubmitForm" class="btn btn-primary" style="width: 100%; margin-top: 15px; padding: 12px;" disabled>
                    <i class="fa-solid fa-paper-plane"></i> Kirim Laporan ke Mandor
                </button>
            </form>
        </div>
    </div>

    <script>
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const resultImage = document.getElementById('result-image');
    const btnCapture = document.getElementById('btnCapture');
    const btnRetake = document.getElementById('btnRetake');
    const btnSubmitForm = document.getElementById('btnSubmitForm');
    const gpsStatus = document.getElementById('gpsStatus');
    const errorMsg = document.getElementById('errorMsg');

    const latInput = document.getElementById('lat');
    const lngInput = document.getElementById('lng');
    const photoBase64Input = document.getElementById('photo_base64');

    let currentLat = null;
    let currentLng = null;
    let currentAddress = "Mencari nama lokasi...";
    let stream = null;

    // 1. Start rear camera
    async function startCamera() {
        try {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
            stream = await navigator.mediaDevices.getUserMedia({ 
                video: { facingMode: "environment" }, // Force rear camera
                audio: false 
            });
            video.srcObject = stream;
            video.style.display = 'block';
            resultImage.style.display = 'none';

            // Hide camera placeholder overlay
            const placeholder = document.getElementById('camera-placeholder');
            if (placeholder) {
                placeholder.style.display = 'none';
            }

            // Enable the capture button
            btnCapture.removeAttribute('disabled');
        } catch (err) {
            errorMsg.innerHTML = "<i class='fa-solid fa-triangle-exclamation'></i> Gagal mengakses kamera belakang. Pastikan izin kamera telah diberikan!";
        }
    }

    // 2. Lock GPS location in real time
    function getGPS() {
        if (!navigator.geolocation) {
            errorMsg.innerHTML = "<i class='fa-solid fa-triangle-exclamation'></i> Browser Anda tidak mendukung GPS.";
            return;
        }
        
        navigator.geolocation.getCurrentPosition(
            (position) => {
                currentLat = position.coords.latitude;
                currentLng = position.coords.longitude;
                
                // Update hidden inputs
                latInput.value = currentLat;
                lngInput.value = currentLng;
                
                gpsStatus.innerHTML = `<i class="fa-solid fa-location-dot" style="color: var(--success);"></i> Lokasi Terkunci: <strong>${currentLat.toFixed(5)}, ${currentLng.toFixed(5)}</strong>`;
                gpsStatus.style.color = "var(--success)";
                
                // Fetch dynamic address name from GPS coordinates via Nominatim API
                fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${currentLat}&lon=${currentLng}`)
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
                                currentAddress = parts.join(', ');
                            } else {
                                currentAddress = data.display_name || `Lat ${currentLat.toFixed(5)}, Lng ${currentLng.toFixed(5)}`;
                            }
                        } else {
                            currentAddress = `Lat ${currentLat.toFixed(5)}, Lng ${currentLng.toFixed(5)}`;
                        }
                        gpsStatus.innerHTML = `<i class="fa-solid fa-location-dot" style="color: var(--success);"></i> Lokasi Terkunci: <strong>${currentLat.toFixed(5)}, ${currentLng.toFixed(5)}</strong><br><span style="font-size:0.75rem; color:#728c7f; font-weight:normal; display:block; margin-top:3px;">${currentAddress}</span>`;
                    })
                    .catch(err => {
                        currentAddress = `Lat ${currentLat.toFixed(5)}, Lng ${currentLng.toFixed(5)}`;
                    });
                
                // Check if photo is already taken to enable submit
                if (photoBase64Input.value !== "") {
                    btnSubmitForm.removeAttribute('disabled');
                }
            },
            (error) => {
                errorMsg.innerHTML = "<i class='fa-solid fa-triangle-exclamation'></i> Error GPS: " + error.message + ". GPS Wajib Aktif & Akurat!";
                gpsStatus.innerHTML = "<span style='color:var(--danger);'>Lokasi Tidak Terkunci</span>";
                btnCapture.setAttribute('disabled', 'disabled'); // Disable capture until GPS works
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        );
    }

    // Initialize location capture on window load (camera waits for click)
    window.addEventListener('load', () => {
        getGPS();
    });

    // 3. Take photo and draw anti-manipulation watermark
    function takePhoto() {
        if (currentLat === null || currentLng === null) {
            alert("Mohon tunggu sampai lokasi GPS terkunci secara akurat!");
            return;
        }

        const context = canvas.getContext('2d');
        
        // Set canvas dimensions based on video source stream size
        canvas.width = video.videoWidth || 640;
        canvas.height = video.videoHeight || 480;
        
        // Draw the current video frame on canvas
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        // --- WATERMARK ANTI-MANIPULASI ---
        const now = new Date();
        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        const timestampStr = days[now.getDay()] + ", " + now.getDate() + " " + months[now.getMonth()] + " " + now.getFullYear() + " " + now.toTimeString().split(' ')[0] + " WIB";
        
        // Set dynamic text properties
        context.font = "bold 16px Arial";
        context.fillStyle = "#ffffff";
        context.strokeStyle = "#000000";
        context.lineWidth = 3;
        
        // Watermark lines
        const textTime = "Waktu: " + timestampStr;
        const textGPS = "GPS: Lat " + currentLat.toFixed(6) + " | Lng " + currentLng.toFixed(6);
        const textOrigin = "Lokasi: " + currentAddress;
        
        // Position watermark overlay lines at the bottom of the image
        const yOrigin = canvas.height - 70;
        const yTime = canvas.height - 45;
        const yGPS = canvas.height - 20;
        
        // Burn watermark into canvas
        context.strokeText(textOrigin, 20, yOrigin);
        context.fillText(textOrigin, 20, yOrigin);
        
        context.strokeText(textTime, 20, yTime);
        context.fillText(textTime, 20, yTime);
        
        context.strokeText(textGPS, 20, yGPS);
        context.fillText(textGPS, 20, yGPS);
        
        // Render captured base64 image data
        const capturedDataURL = canvas.toDataURL('image/jpeg', 0.85);
        
        // Store in hidden input form
        photoBase64Input.value = capturedDataURL;
        
        // Display result image preview
        resultImage.src = capturedDataURL;
        resultImage.style.display = 'block';
        video.style.display = 'none';
        
        // Toggle control buttons
        btnCapture.style.display = 'none';
        btnRetake.style.display = 'block';
        
        // Enable Form submission button
        btnSubmitForm.removeAttribute('disabled');
        
        // Stop camera tracks to save battery life
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
    }

    // 4. Retake Photo logic
    function retakePhoto() {
        photoBase64Input.value = "";
        btnSubmitForm.setAttribute('disabled', 'disabled');
        
        btnCapture.style.display = 'block';
        btnRetake.style.display = 'none';
        
        startCamera();
    }

    // 5. Client-side Form Validation check
    function validateForm() {
        const photo = photoBase64Input.value;
        const lat = latInput.value;
        const lng = lngInput.value;
        
        if (photo === "") {
            alert("Silakan ambil foto bukti fisik pekerjaan terlebih dahulu!");
            return false;
        }
        if (lat === "" || lng === "") {
            alert("Gagal mengirim laporan. Validasi lokasi GPS wajib terdeteksi!");
            return false;
        }
        return true;
    }
    </script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
