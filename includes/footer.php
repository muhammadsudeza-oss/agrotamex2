    </main>
    <footer class="no-print">
        <p>&copy; <?php echo date('Y'); ?> PT Agrotamex Sumindo Abadi. Sistem Informasi Pemantauan Produktivitas Karyawan.</p>
    </footer>
    
    <!-- Custom Main Script -->
    <?php
    $current_dir = basename(dirname($_SERVER['PHP_SELF']));
    $is_subfolder = ($current_dir === 'manajer' || $current_dir === 'mandor' || $current_dir === 'karyawan');
    $root_path = $is_subfolder ? '../' : './';
    ?>
    <script src="<?php echo $root_path; ?>assets/js/main.js"></script>
</body>
</html>
