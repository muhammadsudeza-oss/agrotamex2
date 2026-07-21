// assets/js/main.js

document.addEventListener('DOMContentLoaded', () => {
    // 0. Sidebar Toggle (Hamburger menu)
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            document.body.classList.toggle('sidebar-collapsed');
        });
    }

    // 1. Alert auto fade out
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.remove();
            }, 500);
        }, 4000);
    });

    // 2. Image Proof Modal Preview
    const images = document.querySelectorAll('.img-proof');
    if (images.length > 0) {
        // Create modal element dynamically if not exists
        let modal = document.getElementById('imagePreviewModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'imagePreviewModal';
            modal.className = 'modal';
            modal.innerHTML = `
                <span class="modal-close" id="closeModal">&times;</span>
                <img class="modal-content" id="modalImg">
                <div id="modalCaption" class="modal-caption"></div>
            `;
            document.body.appendChild(modal);
        }

        const modalImg = document.getElementById('modalImg');
        const captionText = document.getElementById('modalCaption');
        const closeModal = document.getElementById('closeModal');

        images.forEach(img => {
            img.addEventListener('click', function() {
                modal.style.display = "block";
                modalImg.src = this.src;
                captionText.innerHTML = this.alt || "Bukti Pekerjaan";
            });
        });

        // Close modal
        closeModal.addEventListener('click', () => {
            modal.style.display = "none";
        });

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = "none";
            }
        });
    }

    // 3. File upload name display
    const fileInput = document.querySelector('input[type="file"]');
    if (fileInput) {
        const fileLabel = document.querySelector('.file-upload-btn');
        if (fileLabel) {
            const originalText = fileLabel.innerHTML;
            fileInput.addEventListener('change', (e) => {
                if (fileInput.files.length > 0) {
                    fileLabel.innerHTML = `<i class="fas fa-file-image"></i> Terpilih: <strong>${fileInput.files[0].name}</strong>`;
                    fileLabel.style.borderColor = '#cba135';
                    fileLabel.style.color = '#cba135';
                } else {
                    fileLabel.innerHTML = originalText;
                    fileLabel.style.borderColor = '';
                    fileLabel.style.color = '';
                }
            });
        }
    }
});

// Helper function to create standard productivity charts (Satu data realisasi berupa kurva garis dengan area gradient)
function initProductivityChart(elementId, labels, dataTarget, dataActual) {
    const ctx = document.getElementById(elementId);
    if (!ctx) return;

    // Create gradient fill
    const canvasCtx = ctx.getContext('2d');
    const gradient = canvasCtx.createLinearGradient(0, 0, 0, 250);
    gradient.addColorStop(0, 'rgba(46, 125, 50, 0.35)');
    gradient.addColorStop(1, 'rgba(46, 125, 50, 0.02)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Realisasi Hasil Kerja',
                    data: dataActual,
                    borderColor: '#1e5235',
                    borderWidth: 3,
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.4, // Membuat garis melengkung halus (smooth curve)
                    pointBackgroundColor: '#2e7d32',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointHoverBorderWidth: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        color: '#5c7567',
                        font: {
                            family: 'Poppins',
                            size: 11
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#5c7567',
                        font: {
                            family: 'Poppins',
                            size: 11
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        color: '#1a2c22',
                        font: {
                            family: 'Poppins',
                            weight: '600'
                        }
                    }
                }
            }
        }
    });
}

// Helper setters for modal populating
function setElText(id, text) {
    const el = document.getElementById(id);
    if (el) el.innerText = text;
}
function setElHtml(id, html) {
    const el = document.getElementById(id);
    if (el) el.innerHTML = html;
}
function setElStyle(id, prop, val) {
    const el = document.getElementById(id);
    if (el) el.style[prop] = val;
}

// Function to populate and display Report Detail Modal
function openReportDetailModal(d) {
    if (d && typeof d === 'object' && d.nodeType === 1 && d.hasAttribute('data-detail')) {
        try {
            d = JSON.parse(d.getAttribute('data-detail'));
        } catch(e) {
            console.error("Error parsing data-detail:", e);
            return;
        }
    }
    if (typeof d === 'string') {
        try {
            d = JSON.parse(d);
        } catch(e) {
            console.error("Error parsing JSON string:", e);
            return;
        }
    }
    if (!d) return;

    // Populate Left Panel (Laporan Kerja Lapangan)
    setElText('modal_nama_karyawan', d.nama_karyawan || '-');
    setElText('modal_tanggal_kerja', d.tanggal_kerja || '-');
    setElText('modal_aktivitas', d.aktivitas || '-');
    setElText('modal_target', (d.target_jumlah || '0') + ' ' + (d.unit || ''));
    setElText('modal_realisasi', (d.jumlah_realisasi || '0') + ' ' + (d.unit || ''));
    setElText('modal_catatan_karyawan', d.catatan_karyawan || 'Tidak ada catatan karyawan.');

    const fotoImg = document.getElementById('modal_foto_bukti');
    if (fotoImg) {
        if (d.foto_bukti) {
            fotoImg.src = d.foto_bukti;
            fotoImg.style.display = 'block';
            fotoImg.onclick = function() {
                window.open(d.foto_bukti, '_blank');
            };
        } else {
            fotoImg.style.display = 'none';
        }
    }

    // Populate Right Panel (Status & Verifikasi)
    let statusText = d.status_label || d.status || '-';
    let statusDescText = '';
    let statusColor = '#2e7d32';

    if (d.status === 'approved') {
        statusText = 'Disetujui Manajer';
        statusDescText = 'Pekerjaan ini sudah final disetujui Manajer.';
        statusColor = '#2e7d32';
    } else if (d.status === 'verified_by_mandor') {
        statusText = 'Terverifikasi Mandor';
        statusDescText = 'Hasil kerja telah diverifikasi Mandor, menunggu persetujuan Manajer.';
        statusColor = '#0d6efd';
    } else if (d.status === 'pending_mandor') {
        statusText = 'Menunggu Mandor';
        statusDescText = 'Laporan baru dikirim, menunggu pemeriksaan Mandor Lapangan.';
        statusColor = '#e0a800';
    } else if (d.status === 'pending_manajer_tolak') {
        statusText = 'Tinjauan Sanksi Denda 10%';
        statusDescText = 'Ditolak Mandor (terdeteksi manipulasi), dalam tinjauan sanksi Manajer.';
        statusColor = '#e65100';
    } else if (d.status === 'rejected') {
        statusText = 'Ditolak (Kena Sanksi 10%)';
        statusDescText = 'Laporan ditolak secara final dan dikenakan denda sanksi 10%.';
        statusColor = '#c62828';
    }

    setElText('modal_status_badge', statusText);
    setElStyle('modal_status_badge', 'color', statusColor);
    setElText('modal_status_desc', statusDescText);

    setElText('modal_catatan_mandor', d.catatan_mandor || 'Belum ada catatan mandor.');
    setElText('modal_waktu_mandor', 'Diverifikasi pada: ' + (d.waktu_mandor || '-'));

    setElText('modal_catatan_manajer', d.catatan_manajer || 'Belum ada catatan manajer.');
    setElText('modal_waktu_manajer', 'Disetujui pada: ' + (d.waktu_manajer || '-'));

    // Insentif / Sanksi Box
    const bonusBox = document.getElementById('modal_bonus_box');
    const bonusNum = Number(d.bonus_diterima || 0);
    const targetNum = Number(d.target_jumlah || 0);
    const realisasiNum = Number(d.jumlah_realisasi || 0);
    const unitStr = d.unit || '';

    if (bonusBox) {
        if (bonusNum > 0) {
            bonusBox.style.background = '#f0fdf4';
            bonusBox.style.borderColor = '#bbf7d0';
            setElText('modal_bonus_val', '+Rp ' + bonusNum.toLocaleString('id-ID'));
            setElStyle('modal_bonus_val', 'color', '#2e7d32');
            
            setElText('modal_bonus_badge', 'Bonus Kinerja');
            setElStyle('modal_bonus_badge', 'background', '#dcfce7');
            setElStyle('modal_bonus_badge', 'color', '#15803d');

            const surplus = (realisasiNum - targetNum).toFixed(2);
            setElHtml('modal_bonus_breakdown', `
                <div><strong>Ketentuan:</strong> Realisasi (${realisasiNum} ${unitStr}) melampaui Target Dasar (${targetNum} ${unitStr}).</div>
                <div><strong>Surplus Hasil:</strong> +${surplus} ${unitStr} (Surplus Produktif).</div>
                <div><strong>Skema Insentif:</strong> Nominal bonus dihitung dari kelebihan hasil kerja fisik.</div>
            `);
        } else if (bonusNum < 0 || d.status === 'rejected' || d.status === 'pending_manajer_tolak') {
            bonusBox.style.background = '#fef2f2';
            bonusBox.style.borderColor = '#fecaca';
            setElText('modal_bonus_val', '-Rp ' + Math.abs(bonusNum).toLocaleString('id-ID'));
            setElStyle('modal_bonus_val', 'color', '#c62828');

            setElText('modal_bonus_badge', 'Sanksi Denda 10%');
            setElStyle('modal_bonus_badge', 'background', '#fee2e2');
            setElStyle('modal_bonus_badge', 'color', '#b91c1c');

            setElHtml('modal_bonus_breakdown', `
                <div><strong>Ketentuan:</strong> Terdeteksi pelanggaran / manipulasi data laporan kerja.</div>
                <div><strong>Dampak Capaian:</strong> Hasil realisasi dianulir menjadi <strong>0 ${unitStr} (0%)</strong>.</div>
                <div><strong>Denda Penalti:</strong> Pemotongan otomatis 10% sebesar <strong>-Rp ${Math.abs(bonusNum).toLocaleString('id-ID')}</strong> dari akumulasi insentif.</div>
            `);
        } else {
            bonusBox.style.background = '#f8fafc';
            bonusBox.style.borderColor = '#e2e8f0';
            setElText('modal_bonus_val', 'Rp 0 (Target Terpenuhi)');
            setElStyle('modal_bonus_val', 'color', '#64748b');

            setElText('modal_bonus_badge', 'Standar');
            setElStyle('modal_bonus_badge', 'background', '#f1f5f9');
            setElStyle('modal_bonus_badge', 'color', '#475569');

            setElHtml('modal_bonus_breakdown', `
                <div><strong>Ketentuan:</strong> Realisasi (${realisasiNum} ${unitStr}) sesuai dengan Target Dasar (${targetNum} ${unitStr}).</div>
                <div><strong>Keterangan:</strong> Pekerjaan tuntas 100% tanpa kelebihan bonus surplus atau denda sanksi.</div>
            `);
        }
    }

    const modal = document.getElementById('reportDetailModal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closeReportDetailModal() {
    const modal = document.getElementById('reportDetailModal');
    if (modal) {
        modal.style.display = 'none';
    }
}
