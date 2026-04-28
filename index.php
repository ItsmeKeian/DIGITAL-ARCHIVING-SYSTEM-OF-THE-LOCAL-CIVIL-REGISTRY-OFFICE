<?php
/* ============================================
   LCR DIGITAL ARCHIVING SYSTEM - LOGIN PAGE
   Municipality of San Julian, Eastern Samar
   ============================================ */

require_once 'authentication/session.php';

// Redirect to dashboard if already logged in
redirectIfLoggedIn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Digital Archiving System - Local Civil Registry Office of San Julian, Eastern Samar" />
    <title>LCR Digital Archiving System | San Julian, Eastern Samar</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/images/logo.png" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/login.css" />

    <style>
        /* Shake animation for wrong password */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%       { transform: translateX(-8px); }
            40%       { transform: translateX(8px); }
            60%       { transform: translateX(-5px); }
            80%       { transform: translateX(5px); }
        }
        .shake { animation: shake 0.5s ease; }
    </style>
</head>
<body>

    <div class="login-wrapper">

        <!-- ── LEFT PANEL ── -->
        <div class="login-panel-left">
            <div class="logo-container">
                <div class="logo-ring">
                    <img src="assets/images/logo.png" alt="Municipality of San Julian Logo" />
                </div>
                <div class="panel-title">
                    Municipality of<br />San Julian
                </div>
                <div class="panel-subtitle">Eastern Samar, Philippines</div>
                <div class="gold-divider"></div>
                <div class="panel-subtitle" style="margin-top:14px; font-size:0.72rem;">
                    Local Civil Registry Office<br />
                    Digital Archiving System
                </div>
            </div>
        </div>

        <!-- ── RIGHT PANEL (FORM) ── -->
        <div class="login-panel-right">

            <h1 class="login-heading">Welcome Back</h1>
            <p class="login-subheading">Sign in to access the Civil Registry records.</p>

            <!-- Alert Message -->
            <div class="alert" id="alertMessage" role="alert"></div>

            <!-- Login Form -->
            <form id="loginForm" novalidate>

                <!-- Username -->
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <div class="input-wrapper">
                        <input
                            type="text"
                            id="username"
                            name="username"
                            class="form-control"
                            placeholder="Enter your username"
                            autocomplete="username"
                            required
                        />
                        <i class="fas fa-user input-icon"></i>
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        />
                        <i class="fas fa-lock input-icon"></i>
                        <button type="button" class="toggle-password" id="togglePassword" title="Show/Hide Password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-login" id="btnLogin">
                    <span class="spinner"></span>
                    <span class="btn-text">SIGN IN</span>
                </button>

            </form>

            <!-- Footer -->
            <div class="login-footer">
                <p>&copy; <?= date('Y') ?> <span>Local Civil Registry Office</span></p>
                <p>Municipality of San Julian, Eastern Samar</p>
                <p style="margin-top:6px; font-size:0.7rem;">For account issues, contact the System Administrator.</p>
            </div>

        </div>
        <!-- end right panel -->

    </div>
    <!-- end login-wrapper -->

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Custom JS -->
    <script src="assets/js/login.js"></script>

</body>
</html>