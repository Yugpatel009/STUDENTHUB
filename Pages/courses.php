<?php
declare(strict_types=1);

/**
 * ============================================================================
 * CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY (CHARUSAT)
 * FACULTY OF TECHNOLOGY AND ENGINEERING (FTE)
 * SUBJECT: WEB DEVELOPMENT FRAMEWORKS (ITUE203)
 * ============================================================================
 * File: courses.php
 * Description: Dynamic, Database-Driven Courses Page.
 *              Fetches and displays active departmental courses from MySQL.
 *              Displays "Faculty Portal" and "Edit Course" buttons if user
 *              has role 'teacher' or 'admin'.
 * ============================================================================
 */

require_once __DIR__ . '/../StudentHUB_php/require_login.php';
require_once __DIR__ . '/../StudentHUB_php/db.php';
require_once __DIR__ . '/../StudentHUB_php/auth.php';

$currentUser = requireLogin();
$isFaculty = in_array($currentUser['role'] ?? '', ['teacher', 'admin'], true);
$isAdmin = ($currentUser['role'] ?? '') === 'admin';

$courses = [];
try {
    $db = getDatabaseConnection();
    $sql = "SELECT c.course_id, c.course_code, c.course_title, c.department,
                   c.faculty_id, c.credits, c.semester, c.description, c.thumbnail_url,
                   COALESCE(u.full_name, 'Department Faculty') AS faculty_name
            FROM courses c
            LEFT JOIN users u ON c.faculty_id = u.user_id
            ORDER BY c.semester ASC, c.course_code ASC";
    $result = $db->query($sql);
    if ($result) {
        $courses = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Throwable $e) {
    error_log('[StudentHub Courses Page Error] ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Hub - Courses</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/courses.css">
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
        <nav class="main-nav" aria-label="Course Navigation">
            <a href="dashboard.php">Dashboard</a>
            <a href="students.php">Students</a>
            <a href="courses.php" class="active">Courses</a>
            <?php if ($isAdmin): ?>
                <a href="admin.php" style="color: #ef4444; font-weight: 700;">Admin Center</a>
            <?php endif; ?>
            <?php if ($isFaculty): ?>
                <a href="faculty.php" style="color: var(--brand-primary); font-weight: 700;">Faculty Portal</a>
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
                <span style="font-size: 13px; color: var(--text-muted); margin-right: 8px;">
                    <?= htmlspecialchars($currentUser['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </span>
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
                <a href="dashboard.php">Dashboard</a>
                <a href="students.php">Students</a>
                <a href="courses.php" class="active">Courses</a>
            <?php if ($isAdmin): ?>
                <a href="admin.php" style="color: #ef4444; font-weight: 700;">Admin Center</a>
            <?php endif; ?>
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
                <a class="btn-logout" style="text-align: center; justify-content: center;" href="../StudentHUB_php/logout.php">Logout</a>
            </div>
        </div>
    </header>

    <main class="page-shell">
        <section class="page-heading">
            <div>
                <p class="eyebrow">Academics</p>
                <h1>Course Curriculum</h1>
                <p>Subjects, faculty in-charge, and curriculum syllabus for active semesters.</p>
            </div>
            <?php if ($isFaculty): ?>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <a href="faculty.php" class="btn btn-primary">🛠️ Manage in Faculty Portal</a>
                </div>
            <?php endif; ?>
        </section>

        <section class="course-grid">
            <?php if (empty($courses)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--text-muted); background: var(--bg-card); border-radius: 8px; border: 1px dashed var(--border-color);">
                    No courses currently scheduled in the database.
                </div>
            <?php else: ?>
                <?php foreach ($courses as $c): ?>
                    <?php
                    $thumb = !empty($c['thumbnail_url']) ? $c['thumbnail_url'] : '../img/web-development.jpg';
                    $sem = (int)($c['semester'] ?? 1);
                    $credits = (int)($c['credits'] ?? 4);
                    $code = htmlspecialchars($c['course_code'], ENT_QUOTES, 'UTF-8');
                    $title = htmlspecialchars($c['course_title'], ENT_QUOTES, 'UTF-8');
                    $desc = htmlspecialchars($c['description'] ?? 'Curriculum syllabus topics and practical modules.', ENT_QUOTES, 'UTF-8');
                    $facultyName = htmlspecialchars($c['faculty_name'] ?? 'Faculty Member', ENT_QUOTES, 'UTF-8');
                    ?>
                    <article class="course-card">
                        <div class="course-thumb">
                            <img src="<?= htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $title ?> Course" onerror="this.onerror=null; this.src='../img/web-development.jpg';">
                        </div>
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <span style="font-family: monospace; font-weight: 700; font-size: 13px; color: var(--brand-primary); background: var(--bg-subtle); padding: 2px 8px; border-radius: 4px; border: 1px solid var(--border-color);">
                                    <?= $code ?>
                                </span>
                                <span style="font-size: 12px; color: var(--text-muted); font-weight: 600;">
                                    Semester <?= $sem ?>
                                </span>
                            </div>
                            <h2><?= $title ?></h2>
                            <p><?= $desc ?></p>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 14px; flex-wrap: wrap; gap: 8px;">
                                <span>⚡ <?= $credits ?> Credits</span>
                                <span style="background: var(--bg-subtle); color: var(--text-secondary); font-size: 12px; font-weight: 600;">
                                    👨‍🏫 <?= $facultyName ?>
                                </span>
                            </div>
                            <?php if ($isFaculty): ?>
                                <div style="margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end;">
                                    <a href="faculty.php" style="font-size: 12px; color: var(--brand-primary); text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                        ✏️ Edit Course in Faculty Portal &rarr;
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>

    <footer class="site-footer">
        <p>&copy; 2026 Student Hub - CHARUSAT. All rights reserved.</p>
    </footer>
    <script src="../js/script.js"></script>
</body>
</html>
