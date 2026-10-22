<?php
declare(strict_types=1);

require_once __DIR__ . '/../StudentHUB_php/csrf.php';
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Hub - Registration</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/register.css">
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

        <!-- Centered Floating Pill Navigation -->
        <nav class="main-nav" aria-label="Register Navigation">
            <a href="../index.html">Home</a>
            <a href="contact.html">Contact</a>
        </nav>

        <!-- Right Corner Actions & Controls -->
        <div class="header-right">
            <div class="header-actions">
                <a class="btn-signup" href="login.html">Login</a>
            </div>

            <button id="themeToggle" class="theme-toggle" aria-label="Toggle Light/Dark Theme" type="button">
                <svg class="sun-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                <svg class="moon-icon" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
            </button>

            <button id="hamburgerBtn" class="hamburger-btn" aria-label="Toggle navigation menu" aria-expanded="false" type="button">
                <span></span><span></span><span></span>
            </button>
        </div>

        <!-- Mobile Drawer Navigation -->
        <div class="mobile-nav-drawer" id="mobileNavDrawer">
            <nav class="mobile-nav-links">
                <a href="../index.html">Home</a>
                <a href="contact.html">Contact</a>
            </nav>
            <div class="mobile-drawer-actions">
                <a class="btn-signup" style="text-align:center;justify-content:center;" href="login.html">Login</a>
            </div>
        </div>
    </header>

    <main class="auth-page">
        <section class="auth-card">
            <div class="auth-form">
                <p class="eyebrow">Join Portal</p>
                <h1>Sign Up</h1>
                <p>Create your Student Hub account.</p>

                <!-- Feedback Banner -->
                <div id="formAlert" class="form-alert" role="alert" aria-live="polite" style="display:none;"></div>

                <form id="registerForm" action="../StudentHUB_php/register.php" method="post" novalidate>
                    <input type="hidden" id="csrf_token" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <!-- Full Name -->
                    <div class="form-group">
                        <label for="student-name">Full Name</label>
                        <input type="text" id="student-name" name="name"
                               placeholder="e.g. Aarav Sharma"
                               autocomplete="name"
                               minlength="2" maxlength="100" required>
                        <small class="field-error" id="name-error"></small>
                    </div>

                    <!-- Email -->
                    <div class="form-group">
                        <label for="student-email">Email Address</label>
                        <input type="email" id="student-email" name="email"
                               placeholder="student@charusat.edu.in"
                               autocomplete="email" required>
                        <small class="field-error" id="email-error"></small>
                    </div>

                    <!-- Password -->
                    <div class="form-group">
                        <label for="student-password">Password</label>
                        <div class="input-icon-wrap">
                            <input type="password" id="student-password" name="password"
                                   placeholder="Min 8 chars, 1 uppercase, 1 number"
                                   autocomplete="new-password"
                                   minlength="8" required>
                            <button type="button" class="toggle-pwd" aria-label="Show password" data-target="student-password">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <!-- Strength bar -->
                        <div class="strength-bar" aria-hidden="true">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <small class="password-msg" id="password-strength">Use at least 8 characters</small>
                    </div>

                    <!-- Confirm Password -->
                    <div class="form-group">
                        <label for="confirm-password">Confirm Password</label>
                        <div class="input-icon-wrap">
                            <input type="password" id="confirm-password" name="confirm-password"
                                   placeholder="Re-enter your password"
                                   autocomplete="new-password" required>
                            <button type="button" class="toggle-pwd" aria-label="Show confirm password" data-target="confirm-password">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <small class="password-msg" id="confirm-password-msg"></small>
                    </div>

                    <!-- Role -->
                    <div class="form-group">
                        <label for="login-role">Role</label>
                        <select id="login-role" name="role" required>
                            <option value="">Select your role</option>
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="admin">Admin</option>
                        </select>
                        <small class="field-error" id="role-error"></small>
                    </div>

                    <!-- Course (shown only for Student role) -->
                    <div class="form-group" id="course-group" style="display:none;" aria-hidden="true">
                        <label for="student-course">Course</label>
                        <select id="student-course" name="course">
                            <option value="">Select your course</option>
                            <option value="bca">BCA</option>
                            <option value="bsc-it">BSc IT</option>
                            <option value="mca">MCA</option>
                        </select>
                        <small class="field-error" id="course-error"></small>
                    </div>

                    <!-- Terms -->
                    <label class="inline-check">
                        <input type="checkbox" name="terms" id="terms" required>
                        I accept the <a href="../index.html" target="_blank">portal terms</a>
                    </label>
                    <small class="field-error" id="terms-error"></small>

                    <button class="btn btn-primary full" id="submitBtn" type="submit">
                        <span id="btnText">Create Account</span>
                        <span id="btnSpinner" style="display:none;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin 1s linear infinite;vertical-align:middle;margin-left:6px;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                            Registering…
                        </span>
                    </button>
                </form>

                <p class="form-note">Already registered? <a href="login.html">Login here</a></p>
            </div>

            <div class="auth-media">
                <img class="signup-image" src="../img/login-illustration-theme.png" alt="Student using laptop illustration">
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <p>&copy; 2026 Student Hub. All rights reserved.</p>
    </footer>

    <script src="../js/script.js"></script>
    <script>
    // ── Password visibility toggles ────────────────────────────────────────
    document.querySelectorAll('.toggle-pwd').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.target);
            input.type = input.type === 'password' ? 'text' : 'password';
            btn.setAttribute('aria-label', input.type === 'password' ? 'Show password' : 'Hide password');
        });
    });

    // ── Live password strength ─────────────────────────────────────────────
    const pwdInput   = document.getElementById('student-password');
    const fill       = document.getElementById('strengthFill');
    const pwdMsg     = document.getElementById('password-strength');

    function checkStrength(pwd) {
        let score = 0;
        if (pwd.length >= 8)               score++;
        if (/[A-Z]/.test(pwd))             score++;
        if (/[0-9]/.test(pwd))             score++;
        if (/[^A-Za-z0-9]/.test(pwd))     score++;
        return score;
    }

    const strengthLabels = ['Too short', 'Weak', 'Fair', 'Strong', 'Very strong'];
    const strengthColors = ['#ef4444','#f97316','#eab308','#22c55e','#10b981'];

    pwdInput.addEventListener('input', () => {
        const s = checkStrength(pwdInput.value);
        fill.style.width = (s * 25) + '%';
        fill.style.background = strengthColors[s] || '#ef4444';
        pwdMsg.textContent  = pwdInput.value.length === 0 ? 'Use at least 8 characters' : strengthLabels[s];
        pwdMsg.className    = 'password-msg ' + (s >= 3 ? 'success' : s >= 2 ? '' : 'error');
    });

    // ── Live confirm-password match ────────────────────────────────────────
    const confirmInput = document.getElementById('confirm-password');
    const confirmMsg   = document.getElementById('confirm-password-msg');

    function checkConfirm() {
        if (confirmInput.value === '') { confirmMsg.textContent = ''; return; }
        if (confirmInput.value === pwdInput.value) {
            confirmMsg.textContent = '✓ Passwords match';
            confirmMsg.className   = 'password-msg success';
        } else {
            confirmMsg.textContent = '✗ Passwords do not match';
            confirmMsg.className   = 'password-msg error';
        }
    }
    confirmInput.addEventListener('input', checkConfirm);
    pwdInput.addEventListener('input', checkConfirm);

    // ── Course group toggle ────────────────────────────────────────────────
    const roleSelect  = document.getElementById('login-role');
    const courseGroup = document.getElementById('course-group');

    roleSelect.addEventListener('change', () => {
        const isStudent = roleSelect.value === 'student';
        courseGroup.style.display = isStudent ? '' : 'none';
        courseGroup.setAttribute('aria-hidden', String(!isStudent));
        document.getElementById('student-course').required = isStudent;
    });

    // ── Client-side validation (mirrors server rules exactly) ─────────────
    function clearErrors() {
        document.querySelectorAll('.field-error').forEach(el => el.textContent = '');
        document.querySelectorAll('.form-group input, .form-group select').forEach(el => el.classList.remove('input-error'));
    }

    function setError(fieldId, errorId, msg) {
        const input = document.getElementById(fieldId);
        const err   = document.getElementById(errorId);
        if (input) input.classList.add('input-error');
        if (err)   err.textContent = msg;
    }

    function validateForm() {
        clearErrors();
        let valid = true;
        const name    = document.getElementById('student-name').value.trim();
        const email   = document.getElementById('student-email').value.trim();
        const pwd     = pwdInput.value;
        const confirm = confirmInput.value;
        const role    = roleSelect.value;
        const course  = document.getElementById('student-course').value;
        const terms   = document.getElementById('terms').checked;

        if (name.length < 2) {
            setError('student-name', 'name-error', 'Full name must be at least 2 characters long.');
            valid = false;
        }

        const emailRx = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRx.test(email)) {
            setError('student-email', 'email-error', 'Please enter a valid email address.');
            valid = false;
        }

        if (pwd.length < 8) {
            setError('student-password', 'password-strength', 'Password must be at least 8 characters.');
            valid = false;
        } else if (!/[A-Z]/.test(pwd)) {
            setError('student-password', 'password-strength', 'Must contain at least one uppercase letter.');
            valid = false;
        } else if (!/[0-9]/.test(pwd)) {
            setError('student-password', 'password-strength', 'Must contain at least one number.');
            valid = false;
        }

        if (pwd !== confirm) {
            setError('confirm-password', 'confirm-password-msg', 'Passwords do not match.');
            valid = false;
        }

        if (!role) {
            setError('login-role', 'role-error', 'Please select a role.');
            valid = false;
        }

        if (role === 'student' && !course) {
            setError('student-course', 'course-error', 'Please select your course.');
            valid = false;
        }

        if (!terms) {
            document.getElementById('terms-error').textContent = 'You must accept the portal terms.';
            valid = false;
        }

        return valid;
    }

    // ── Form submission (AJAX with graceful fallback) ──────────────────────
    const form      = document.getElementById('registerForm');
    const alert_box = document.getElementById('formAlert');
    const submitBtn = document.getElementById('submitBtn');
    const btnText   = document.getElementById('btnText');
    const spinner   = document.getElementById('btnSpinner');

    function showAlert(msg, errors, success) {
        alert_box.style.display = 'block';
        alert_box.className = 'form-alert ' + (success ? 'form-alert--success' : 'form-alert--error');
        let html = '<strong>' + (success ? '✅ ' : '❌ ') + msg + '</strong>';
        if (errors && errors.length) {
            html += '<ul>' + errors.map(e => `<li>${e}</li>`).join('') + '</ul>';
        }
        alert_box.innerHTML = html;
        alert_box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    form.addEventListener('submit', e => {
        if (!validateForm()) { e.preventDefault(); return; }

        e.preventDefault();
        btnText.style.display  = 'none';
        spinner.style.display  = 'inline';
        submitBtn.disabled     = true;
        alert_box.style.display = 'none';

        fetch(form.action, {
            method: 'POST',
            body:   new FormData(form),
            headers: { 'Accept': 'application/json' }
        })
        .then(async res => {
            const data = await res.json().catch(() => ({}));

            if (data.success) {
                showAlert(data.message, [], true);
                form.reset();
                setTimeout(() => {
                    if (data.redirect) window.location.href = data.redirect;
                }, 2000);
            } else {
                showAlert(data.message || 'Registration failed.', data.errors || [], false);
            }
        })
        .catch(() => {
            form.submit();
        })
        .finally(() => {
            btnText.style.display = 'inline';
            spinner.style.display = 'none';
            submitBtn.disabled    = false;
        });
    });
    </script>
</body>

</html>
