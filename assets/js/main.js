/* ============================================
   LCR DIGITAL ARCHIVING SYSTEM - MAIN JS
   Municipality of San Julian, Eastern Samar
   ============================================ */

   $(document).ready(function () {

    // ── Restore sidebar state on page load ──
    if (localStorage.getItem('sidebar_collapsed') === 'true') {
        $('#sidebar').addClass('collapsed');
        $('#mainWrapper').addClass('collapsed');
    }

    // ── Sidebar inner toggle (hamburger inside sidebar header) ──
    $(document).on('click', '#sidebarToggle', function (e) {
        e.stopPropagation();
        if (window.innerWidth > 900) {
            $('#sidebar').toggleClass('collapsed');
            $('#mainWrapper').toggleClass('collapsed');
            localStorage.setItem('sidebar_collapsed', $('#sidebar').hasClass('collapsed'));
        }
    });

    // ── Topbar hamburger — handles BOTH desktop and mobile ──
    $(document).on('click', '#mobileSidebarToggle', function (e) {
        e.stopPropagation();
        if (window.innerWidth <= 900) {
            // Mobile — slide in/out with overlay
            $('#sidebar').toggleClass('mobile-open');
            $('#sidebarOverlay').toggleClass('active');
        } else {
            // Desktop — collapse/expand with localStorage
            $('#sidebar').toggleClass('collapsed');
            $('#mainWrapper').toggleClass('collapsed');
            localStorage.setItem('sidebar_collapsed', $('#sidebar').hasClass('collapsed'));
        }
    });

    // ── Close mobile sidebar when overlay is clicked ──
    $(document).on('click', '#sidebarOverlay', function () {
        $('#sidebar').removeClass('mobile-open');
        $('#sidebarOverlay').removeClass('active');
    });

    // ── Confirm logout ──
    $(document).on('click', 'a[href="logout.php"]', function (e) {
        if (!confirm('Are you sure you want to logout?')) {
            e.preventDefault();
        }
    });

    // ── Auto-dismiss alerts ──
    setTimeout(function () {
        $('.alert-auto-dismiss').fadeOut(400);
    }, 4000);

});