        </main>
        <footer class="footer-bottom no-print">
            <p>&copy; <?php echo date('Y'); ?> PT Agrotamex Sumindo Abadi. Sistem Pemantauan Produktivitas Karyawan Perkebunan.</p>
        </footer>
    </div> <!-- End .main-wrapper -->

    <!-- Modal Detail Verifikasi Laporan Kerja (Exact Match with Screenshot Layout) -->
    <div id="reportDetailModal" class="modal-overlay no-print" style="display: none;">
        <div class="modal-container-lg">
            <div class="modal-header">
                <div>
                    <h3 class="modal-title" style="font-size: 1.3rem; font-weight: 700; color: #0f172a;">Verifikasi Laporan Kerja Karyawan</h3>
                    <p style="font-size: 0.82rem; color: #64748b; margin-top: 2px;">Periksa bukti fisik dan koordinat GPS hasil kerja kelompok mandor Anda</p>
                </div>
                <button type="button" class="btn-modal-close" onclick="closeReportDetailModal()">&times;</button>
            </div>
            
            <div class="modal-body" style="padding: 20px;">
                <div class="grid-2-modal">
                    <!-- Left Panel: Laporan Kerja Lapangan -->
                    <div class="glass-panel" style="padding: 20px; border-radius: 10px; background: #ffffff;">
                        <h4 style="font-size: 1.05rem; font-weight: 700; color: #1e293b; margin-bottom: 15px;">Laporan Kerja Lapangan</h4>
                        
                        <div style="margin-bottom: 14px;">
                            <span style="font-size: 0.78rem; color: #64748b; font-weight: 500;">Nama Karyawan</span>
                            <div id="modal_nama_karyawan" style="font-size: 1.1rem; font-weight: 700; color: #0f172a; margin-top: 2px;">-</div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                            <div>
                                <span style="font-size: 0.78rem; color: #64748b; font-weight: 500;">Tanggal Kerja</span>
                                <div id="modal_tanggal_kerja" style="font-size: 0.95rem; font-weight: 700; color: #1e293b; margin-top: 2px;">-</div>
                            </div>
                            <div>
                                <span style="font-size: 0.78rem; color: #64748b; font-weight: 500;">Aktivitas</span>
                                <div id="modal_aktivitas" style="font-size: 0.95rem; font-weight: 700; color: #1e293b; margin-top: 2px;">-</div>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                            <div>
                                <span style="font-size: 0.78rem; color: #64748b; font-weight: 500;">Target Penugasan</span>
                                <div id="modal_target" style="font-size: 1rem; font-weight: 700; color: #1e5235; margin-top: 2px;">-</div>
                            </div>
                            <div>
                                <span style="font-size: 0.78rem; color: #64748b; font-weight: 500;">Realisasi Dilaporkan</span>
                                <div id="modal_realisasi" style="font-size: 1rem; font-weight: 700; color: #2e7d32; margin-top: 2px;">-</div>
                            </div>
                        </div>

                        <div style="margin-bottom: 16px;">
                            <span style="font-size: 0.78rem; color: #64748b; font-weight: 500;">Catatan Karyawan</span>
                            <div id="modal_catatan_karyawan" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; font-size: 0.85rem; color: #334155; margin-top: 4px; font-style: italic;">
                                -
                            </div>
                        </div>

                        <div>
                            <span style="font-size: 0.78rem; color: #64748b; font-weight: 500; display: block; margin-bottom: 6px;">Foto Bukti Lapangan (Klik untuk memperbesar)</span>
                            <div id="modal_foto_container">
                                <img id="modal_foto_bukti" src="" alt="Bukti Lapangan" style="width: 100%; height: 200px; object-fit: cover; border-radius: 8px; border: 1px solid #cbd5e1; cursor: pointer;">
                            </div>
                        </div>
                    </div>

                    <!-- Right Panel: Status & Verifikasi -->
                    <div class="glass-panel" style="padding: 20px; border-radius: 10px; background: #ffffff; display: flex; flex-direction: column;">
                        <h4 style="font-size: 1.05rem; font-weight: 700; color: #1e293b; margin-bottom: 15px;">Status &amp; Verifikasi</h4>

                        <div style="margin-bottom: 18px; border-bottom: 1px solid #f1f5f9; padding-bottom: 14px;">
                            <span style="font-size: 0.78rem; color: #64748b; font-weight: 500;">Status Laporan Saat Ini</span>
                            <div id="modal_status_badge" style="margin-top: 4px; font-size: 1.05rem; font-weight: 700; color: #2e7d32;">-</div>
                            <div id="modal_status_desc" style="font-size: 0.8rem; color: #64748b; margin-top: 2px;">-</div>
                        </div>

                        <div style="margin-bottom: 18px; border-bottom: 1px solid #f1f5f9; padding-bottom: 14px;">
                            <span style="font-size: 0.78rem; color: #64748b; font-weight: 500;">Hasil Pemeriksaan Mandor</span>
                            <div id="modal_catatan_mandor" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; font-size: 0.85rem; color: #334155; margin-top: 4px; font-style: italic;">
                                -
                            </div>
                            <div id="modal_waktu_mandor" style="font-size: 0.78rem; color: #64748b; margin-top: 6px;">Diverifikasi pada: -</div>
                        </div>

                        <div style="margin-bottom: 18px;">
                            <span style="font-size: 0.78rem; color: #64748b; font-weight: 500;">Catatan Persetujuan Manajer</span>
                            <div id="modal_catatan_manajer" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; font-size: 0.85rem; color: #334155; margin-top: 4px; min-height: 38px;">
                                -
                            </div>
                            <div id="modal_waktu_manajer" style="font-size: 0.78rem; color: #64748b; margin-top: 6px;">Disetujui pada: -</div>
                        </div>

                        <div id="modal_bonus_box" style="margin-top: auto; padding: 14px; background: #f0fdf4; border: 1.5px solid #bbf7d0; border-radius: 8px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-size: 0.75rem; color: #166534; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <i class="fa-solid fa-calculator"></i> Rincian Bonus &amp; Sanksi:
                                </span>
                                <span id="modal_bonus_badge" class="badge" style="font-size: 0.7rem;">-</span>
                            </div>
                            <div id="modal_bonus_val" style="font-size: 1.2rem; font-weight: 700; color: #2e7d32; margin-top: 4px;">-</div>
                            
                            <!-- Detail Breakdown Box -->
                            <div id="modal_bonus_breakdown" style="margin-top: 10px; padding-top: 8px; border-top: 1px dashed rgba(0,0,0,0.12); font-size: 0.8rem; color: #334155; line-height: 1.5;">
                                -
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Main Script -->
    <?php
    $current_dir = basename(dirname($_SERVER['PHP_SELF']));
    $is_subfolder = ($current_dir === 'manajer' || $current_dir === 'mandor' || $current_dir === 'karyawan');
    $root_path = $is_subfolder ? '../' : './';
    ?>
    <script src="<?php echo $root_path; ?>assets/js/main.js?v=<?php echo time(); ?>"></script>
</body>
</html>
