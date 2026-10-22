<?php
require_once __DIR__ . '/../StudentHUB_php/require_login.php';
$currentUser = requireLogin();
$isFaculty = in_array($currentUser['role'] ?? '', ['teacher', 'admin'], true);
$isAdmin = ($currentUser['role'] ?? '') === 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Hub - Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <script>
        (function() {
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
        <nav class="main-nav" aria-label="Dashboard Navigation">
            <a href="dashboard.php" class="active">Dashboard</a>
            <?php if ($isAdmin): ?>
                <a href="admin.php" style="color: #ef4444; font-weight: 700;">Admin Center</a>
                <a href="deployment.php" style="color: #2563eb; font-weight: 600;">Diagnostics & Deploy</a>
            <?php endif; ?>
            <a href="students.php">Students</a>
            <a href="courses.php">Courses</a>
            <?php if ($isFaculty): ?>
                <a href="faculty.php">Faculty Portal</a>
            <?php endif; ?>
            <a href="events.php">Campus Events</a>
            <a href="assignment.php">Assignments</a>
            <a href="attendance.php">Attendance</a>
            <a href="result.php">Results</a>
            <a href="profile.php">Profile</a>
        </nav>

        <!-- Right Corner Actions & Controls -->
        <div class="header-right">
            <div class="header-actions">
                <a class="btn-logout" id="logoutLink" href="../StudentHUB_php/logout.php">Logout</a>
            </div>

            <button id="themeToggle" class="theme-toggle" aria-label="Toggle Light/Dark Theme" type="button">
                <svg class="sun-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                <svg class="moon-icon" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
            </button>

            <button id="hamburgerBtn" class="hamburger-btn" aria-label="Toggle navigation menu" aria-expanded="false" type="button">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>

        <!-- Mobile Drawer Navigation -->
        <div class="mobile-nav-drawer" id="mobileNavDrawer">
            <nav class="mobile-nav-links">
                <a href="dashboard.php" class="active">Dashboard</a>
            <?php if ($isAdmin): ?>
                <a href="admin.php" style="color: #ef4444; font-weight: 700;">Admin Center</a>
                <a href="deployment.php" style="color: #2563eb; font-weight: 600;">Diagnostics & Deploy</a>
            <?php endif; ?>
                <a href="students.php">Students</a>
                <a href="courses.php">Courses</a>
                <?php if ($isFaculty): ?>
                    <a href="faculty.php">Faculty Portal</a>
                <?php endif; ?>
                <a href="events.php">Campus Events</a>
                <a href="assignment.php">Assignments</a>
                <a href="attendance.php">Attendance</a>
                <a href="result.php">Results</a>
                <a href="profile.php">Profile</a>
            </nav>
            <div class="mobile-drawer-actions">
                <a class="btn-logout" style="text-align: center; justify-content: center;" id="mobileLogoutLink" href="../StudentHUB_php/logout.php">Logout</a>
            </div>
        </div>
    </header>

    <main class="page-shell">
        <section class="page-heading">
            <div>
                <p class="eyebrow">Overview</p>
                <h1>Student Dashboard</h1>
                <p>
                    Welcome back, <strong><?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; background: #3b82f6; color: #fff; margin-left: 6px;">
                        <?= htmlspecialchars($currentUser['role'] ?? 'Student', ENT_QUOTES, 'UTF-8') ?>
                    </span>.
                    Here is your academic summary.
                </p>
                <?php if (isset($_GET['unauthorized'])): ?>
                    <div style="background: #7f1d1d; border: 1px solid #b91c1c; color: #fca5a5; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-top: 10px;">
                        ⚠️ <strong>Access Restricted:</strong> You do not have the required role permissions to access that module.
                    </div>
                <?php endif; ?>
            </div>
            <a class="btn btn-primary" href="profile.php">View Profile</a>
        </section>

        <section class="stats-grid">
            <article class="stat-card">
                <span>Courses</span>
                <strong>6</strong>
                <p>Active this semester</p>
            </article>
            <article class="stat-card">
                <span>Attendance</span>
                <strong>86%</strong>
                <p>Overall percentage</p>
            </article>
            <article class="stat-card">
                <span>Assignments</span>
                <strong>3</strong>
                <p>Pending submissions</p>
            </article>
            <article class="stat-card">
                <span>Result</span>
                <strong>A</strong>
                <p>Current grade average</p>
            </article>
        </section>

        <section class="content-grid">
            <article class="panel">
                <h2>Today Schedule</h2>
                <ul class="timeline">
                    <li><span>09:00</span> Web Development</li>
                    <li><span>11:00</span> Database Management</li>
                    <li><span>02:00</span> Computer Networks</li>
                </ul>
            </article>

            <article class="panel">
                <h2>Portal Modules</h2>
                <div class="module-list">
                    <?php if ($isFaculty): ?>
                        <a href="faculty.php" style="border-left: 3px solid var(--brand-primary); font-weight: 700; color: var(--brand-primary);">🛠️ Faculty Portal (Course & Academic Editor)</a>
                    <?php endif; ?>
                    <?php if ($isAdmin): ?>
                        <a href="admin.php" style="border-left: 3px solid #ef4444; font-weight: 700; color: #ef4444;">🛡️ Admin Center (Telemetry, RBAC & Audit Trail)</a>
                    <?php endif; ?>
                    <a href="courses.php">Course Info</a>
                    <a href="events.php">Campus Events & Workshops</a>
                    <a href="assignment.php">Assignment Details</a>
                    <a href="attendance.php">Attendance Info</a>
                    <a href="result.php">Results</a>
                    <a href="contact.html">Contact Support</a>
                </div>
            </article>
        </section>
    </main>

    <footer class="site-footer">
        <p>&copy; 2026 Student Hub. All rights reserved.</p>
    </footer>
    <script src="../js/script.js"></script>
</body>
</html>
