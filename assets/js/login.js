/* ============================================
   LCR DIGITAL ARCHIVING SYSTEM - LOGIN JS
   Municipality of San Julian, Eastern Samar
   ============================================ */

   $(document).ready(function () {

    // ── Toggle Password Visibility ──
    $('#togglePassword').on('click', function () {
        const passwordField = $('#password');
        const icon = $(this).find('i');
        const type = passwordField.attr('type') === 'password' ? 'text' : 'password';
        passwordField.attr('type', type);
        icon.toggleClass('fa-eye fa-eye-slash');
    });

    // ── Input focus effects ──
    $('.form-control').on('focus', function () {
        $(this).closest('.input-wrapper').find('.input-icon').css('color', '#8B0000');
    }).on('blur', function () {
        if (!$(this).val()) {
            $(this).closest('.input-wrapper').find('.input-icon').css('color', '#ccc');
        }
    });

    // ── Login Form Submission via AJAX ──
    $('#loginForm').on('submit', function (e) {
        e.preventDefault();

        const username = $('#username').val().trim();
        const password = $('#password').val().trim();
        const $btn     = $('#btnLogin');
        const $alert   = $('#alertMessage');

        // Basic front-end validation
        if (!username || !password) {
            showAlert('danger', '<i class="fas fa-exclamation-circle"></i> Please fill in all fields.');
            return;
        }

        // Show loading state
        $btn.addClass('loading').prop('disabled', true);
        $alert.removeClass('show alert-danger alert-success');

        // AJAX request to login.php
        $.ajax({
            url: 'login.php',
            type: 'POST',
            dataType: 'json',
            data: {
                username: username,
                password: password
            },
            success: function (response) {
                if (response.status === 'success') {
                    showAlert('success', '<i class="fas fa-check-circle"></i> Login successful! Redirecting...');
                    setTimeout(function () {
                        window.location.href = response.redirect;
                    }, 1000);
                } else {
                    showAlert('danger', '<i class="fas fa-exclamation-circle"></i> ' + response.message);
                    $btn.removeClass('loading').prop('disabled', false);
                    // Shake effect on error
                    $('.login-panel-right').addClass('shake');
                    setTimeout(() => $('.login-panel-right').removeClass('shake'), 500);
                }
            },
            error: function () {
                showAlert('danger', '<i class="fas fa-exclamation-circle"></i> Server error. Please try again.');
                $btn.removeClass('loading').prop('disabled', false);
            }
        });
    });

    // ── Show Alert Helper ──
    function showAlert(type, message) {
        const $alert = $('#alertMessage');
        $alert
            .removeClass('show alert-danger alert-success')
            .addClass('alert-' + type)
            .html(message);
        setTimeout(() => $alert.addClass('show'), 10);
    }

    // ── Enter key on username goes to password ──
    $('#username').on('keypress', function (e) {
        if (e.which === 13) {
            $('#password').focus();
        }
    });
});