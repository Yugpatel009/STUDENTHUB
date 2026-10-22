<?php
declare(strict_types=1);

/**
 * ============================================================================
 * StudentHub - Server-Rendered Login Page (Practical 10)
 * Features:
 *   - Automatic redirection to dashboard if already authenticated
 *   - Pre-embedded CSRF token
 *   - Server-side flash alerts for session timeout and logout
 * ============================================================================
 */

require_once __DIR__ . '/../StudentHUB_php/auth.php';
require_once __DIR__ . '/../StudentHUB_php/csrf.php';

initSession();

// If already logged in, redirect straight to dashboard
if (getCurrentUser() !== null) {
    header('Location: dashboard.php');
    exit;
}

$csrfToken = generateCsrfToken();
$timeoutAlert = isset($_GET['timeout']) && $_GET['timeout'] === '1';
$logoutAlert  = isset($_GET['logged_out']) && $_GET['logged_out'] === '1';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Hub - Login</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/login.css">
    <script>
        (function () {
            var saved = localStorage.getItem('studenthub_theme');
            var theme = saved || ((window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
            document.documentElement.style.colorScheme = theme;
        })();
    </script>
</head>

<body>
    <header class="site-header">
        <a class="brand" href="../index.html">
            <img src="../img/logo.png" alt="Student Hub Logo">
        </a>

        <nav class="main-nav" aria-label="Login Navigation">
            <a href="../index.html">Home</a>
            <a href="contact.html">Contact</a>
        </nav>

        <div class="header-right">
            <div class="header-actions">
                <a class="btn-signup" href="register.php">Sign Up</a>
            </div>

            <button id="themeToggle" class="theme-toggle" aria-label="Toggle Light/Dark Theme" type="button">
                <svg class="sun-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                <svg class="moon-icon" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
            </button>

            <button id="hamburgerBtn" class="hamburger-btn" aria-label="Toggle navigation menu" aria-expanded="false" type="button">
                <span></span><span></span><span></span>
            </button>
        </div>

        <div class="mobile-nav-drawer" id="mobileNavDrawer">
            <nav class="mobile-nav-links">
                <a href="../index.html">Home</a>
                <a href="contact.html">Contact</a>
            </nav>
            <div class="mobile-drawer-actions">
                <a class="btn-signup" style="text-align: center; justify-content: center;" href="register.php">Sign Up</a>
            </div>
        </div>
    </header>

    <main class="auth-page">
        <section class="auth-card">
            <div class="auth-media">
                <img class="login-image" src="../img/login-illustration-theme.png" alt="Student using laptop illustration">
            </div>
            <div class="auth-form">
                <p class="eyebrow">Welcome Back</p>
                <h1>Login</h1>
                <p>Enter your account details to open the student dashboard.</p>

                <!-- Feedback Banner for Timeout / Logout -->
                <div id="loginAlert" class="form-alert" role="alert" style="<?= ($timeoutAlert || $logoutAlert) ? 'display: block;' : 'display: none;' ?> padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; line-height: 1.5; <?= $timeoutAlert ? 'background: #78350f; border: 1px solid #d97706; color: #fde68a;' : 'background: #064e3b; border: 1px solid #059669; color: #34d399;' ?>">
                    <?php if ($timeoutAlert): ?>
                        ⏱️ <strong>Session Expired:</strong> You were inactive for more than 30 minutes. Please log in again to continue.
                    <?php elseif ($logoutAlert): ?>
                        ✅ <strong>Logged Out:</strong> You have been safely signed out and your session was terminated.
                    <?php endif; ?>
                </div>

                <form id="loginForm" action="../StudentHUB_php/login.php" method="post">
                    <input type="hidden" id="csrf_token" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="form-group">
                        <label for="login-email">Email Address</label>
                        <input type="email" id="login-email" name="email" placeholder="e.g. user@charusat.edu.in" autocomplete="email" required>
                    </div>

                    <div class="form-group">
                        <label for="login-password">Password</label>
                        <div class="input-icon-wrap" style="position: relative; display: flex; align-items: center;">
                            <input type="password" id="login-password" name="password" placeholder="Enter your password" autocomplete="current-password" required style="width: 100%; padding-right: 40px;">
                            <button type="button" id="togglePasswordBtn" aria-label="Show password" style="position: absolute; right: 10px; background: none; border: none; cursor: pointer; color: #94a3b8; display: flex; align-items: center;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="login-role">Role</label>
                        <select id="login-role" name="role" required>
                            <option value="">Select role</option>
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <div class="form-row" style="display: flex; justify-content: space-between; align-items: center; margin: 12px 0 20px;">
                        <label class="inline-check" style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; color: var(--text-secondary, #94a3b8);">
                            <input type="checkbox" id="remember_me" name="remember_me" value="1">
                            Remember Me (30 Days)
                        </label>
                        <a href="contact.html" style="font-size: 13px; color: var(--primary, #3b82f6); text-decoration: none;">Need help?</a>
                    </div>

                    <button class="btn btn-primary full" id="submitBtn" type="submit">
                        <span id="btnText">Login</span>
                        <span id="btnSpinner" style="display: none;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite; vertical-align: middle; margin-right: 6px;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                            Authenticating...
                        </span>
                    </button>
                </form>

                <p class="form-note">New student? <a href="register.php">Create an account</a></p>
            </div>
        </section>
    </main>
    <footer class="site-footer">
        <p>&copy; 2026 Student Hub. All rights reserved.</p>
    </footer>
    <script src="../js/script.js"></script>
    <script>
        // Password Show/Hide Toggle
        const toggleBtn = document.getElementById('togglePasswordBtn');
        const pwdInput = document.getElementById('login-password');
        if (toggleBtn && pwdInput) {
            toggleBtn.addEventListener('click', () => {
                const isPassword = pwdInput.type === 'password';
                pwdInput.type = isPassword ? 'text' : 'password';
                toggleBtn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            });
        }

        // AJAX Submission
        const loginForm = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const btnSpinner = document.getElementById('btnSpinner');
        const loginAlert = document.getElementById('loginAlert');

        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                e.preventDefault();
                btnText.style.display = 'none';
                btnSpinner.style.display = 'inline';
                submitBtn.disabled = true;

                const formData = new FormData(loginForm);

                fetch(loginForm.action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                })
                .then(async res => {
                    const data = await res.json().catch(() => ({}));
                    if (res.ok && data.success) {
                        loginAlert.style.display = 'block';
                        loginAlert.style.background = '#064e3b';
                        loginAlert.style.border = '1px solid #059669';
                        loginAlert.style.color = '#34d399';
                        loginAlert.innerHTML = '✅ <strong>Success:</strong> ' + data.message;
                        window.location.href = data.redirect || 'dashboard.php';
                    } else {
                        loginAlert.style.display = 'block';
                        loginAlert.style.background = '#7f1d1d';
                        loginAlert.style.border = '1px solid #b91c1c';
                        loginAlert.style.color = '#fca5a5';
                        loginAlert.innerHTML = '❌ <strong>Authentication Failed:</strong> ' + (data.message || 'Invalid credentials.');
                    }
                })
                .catch(() => {
                    loginForm.submit();
                })
                .finally(() => {
                    btnText.style.display = 'inline';
                    btnSpinner.style.display = 'none';
                    submitBtn.disabled = false;
                });
            });
        }
    </script>
</body>
</html>
