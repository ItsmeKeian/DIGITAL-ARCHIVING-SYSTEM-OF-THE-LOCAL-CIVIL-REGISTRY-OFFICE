<?php // includes/footer.php ?>
    </main>
    <!-- end page-content -->

</div>
<!-- end main-wrapper -->

<!-- Overlay for mobile sidebar -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Main JS -->
<script src="assets/js/main.js"></script>
<?php if (isset($extra_js)): ?>
    <script src="assets/js/<?= $extra_js ?>"></script>
<?php endif; ?>

</body>
</html>