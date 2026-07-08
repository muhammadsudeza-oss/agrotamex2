// assets/js/main.js

document.addEventListener('DOMContentLoaded', () => {
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
