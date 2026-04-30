/* ============================================
   LCR DIGITAL ARCHIVING SYSTEM - MAIN JS
   Municipality of San Julian, Eastern Samar
   ============================================ */

   $(document).ready(function () {

    // ── Sidebar Desktop Toggle ──
    $('#sidebarToggle').on('click', function () {
        $('#sidebar').toggleClass('collapsed');
        $('#mainWrapper').toggleClass('collapsed');
        const isCollapsed = $('#sidebar').hasClass('collapsed');
        localStorage.setItem('sidebar_collapsed', isCollapsed);
    });

    // Restore sidebar state
    if (localStorage.getItem('sidebar_collapsed') === 'true') {
        $('#sidebar').addClass('collapsed');
        $('#mainWrapper').addClass('collapsed');
    }

    // ── Sidebar Mobile Toggle ──
    $('#mobileSidebarToggle').on('click', function () {
        $('#sidebar').addClass('mobile-open');
        $('#sidebarOverlay').addClass('active');
    });

    $('#sidebarOverlay').on('click', function () {
        $('#sidebar').removeClass('mobile-open');
        $('#sidebarOverlay').removeClass('active');
    });

    // ── Active nav link tooltip (collapsed sidebar) ──
    $('.nav-link').each(function () {
        const text = $(this).find('.nav-text').text().trim();
        $(this).attr('data-tooltip', text);
    });

    // ── Auto-dismiss alerts ──
    setTimeout(function () {
        $('.alert-auto-dismiss').fadeOut(400);
    }, 4000);

    // ── Confirm delete dialogs ──
    $(document).on('click', '.btn-delete', function (e) {
        if (!confirm('Are you sure you want to delete this record? This action cannot be undone.')) {
            e.preventDefault();
        }
    });

    // ── Confirm logout ──
    $('a[href="logout.php"]').on('click', function (e) {
        if (!confirm('Are you sure you want to logout?')) {
            e.preventDefault();
        }
    });
});