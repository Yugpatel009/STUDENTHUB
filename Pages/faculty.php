<?php
declare(strict_types=1);

/**
 * ============================================================================
 * CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY (CHARUSAT)
 * FACULTY OF TECHNOLOGY AND ENGINEERING (FTE)
 * SUBJECT: WEB DEVELOPMENT FRAMEWORKS (ITUE203)
 * ============================================================================
 * File: faculty.php
 * Description: Dedicated Faculty & Instructor Management Portal.
 *              Allows authorized Faculty (teachers) and Administrators to:
 *                1. Manage Course Data (Add, Edit, Delete course code, credits,
 *                   semester, syllabus, description).
 *                2. View and Edit Student Academic Records (update student CGPA,
 *                   semester, course enrollments, department).
 *                3. View departmental KPIs and real-time statistics.
 * Security: Guarded with requireRole(['teacher', 'admin']) and CSRF protection.
 * ============================================================================
 */

require_once __DIR__ . '/../StudentHUB_php/auth.php';
require_once __DIR__ . '/../StudentHUB_php/csrf.php';
require_once __DIR__ . '/../StudentHUB_php/db.php';

// RBAC Guard: Only Teachers and Administrators may enter this portal
$currentUser = requireRole(['teacher', 'admin']);
$csrfToken = generateCsrfToken();

$db = getDatabaseConnection();

// Fetch summary KPI statistics
$facultyId = (int)($currentUser['id'] ?? 0);
$isAdmin = ($currentUser['role'] ?? '') === 'admin';

// KPI 1: Assigned Courses Count
$coursesCountQuery = $isAdmin
    ? "SELECT COUNT(*) AS total_courses FROM courses"
    : "SELECT COUNT(*) AS total_courses FROM courses WHERE faculty_id = {$facultyId}";
$resCourses = $db->query($coursesCountQuery);
$totalCoursesCount = $resCourses ? (int)($resCourses->fetch_assoc()['total_courses'] ?? 0) : 0;

// KPI 2: Total Active Students
$resStudents = $db->query("SELECT COUNT(*) AS total_students, COALESCE(AVG(cgpa), 0) AS avg_cgpa FROM students WHERE is_deleted = 0");
$studentStats = $resStudents ? $resStudents->fetch_assoc() : ['total_students' => 0, 'avg_cgpa' => 0.0];
$totalActiveStudents = (int)($studentStats['total_students'] ?? 0);
$avgDeptCgpa = number_format((float)($studentStats['avg_cgpa'] ?? 0.0), 2);

// KPI 3: Distinct Departments / Programs
$resDepts = $db->query("SELECT COUNT(DISTINCT department) AS total_depts FROM courses");
$totalDepts = $resDepts ? (int)($resDepts->fetch_assoc()['total_depts'] ?? 1) : 1;

// Get distinct courses for filter dropdowns
$resCourseTitles = $db->query("SELECT DISTINCT course_title FROM courses ORDER BY course_title ASC");
$allCoursesList = [];
if ($resCourseTitles) {
    while ($row = $resCourseTitles->fetch_assoc()) {
        $allCoursesList[] = $row['course_title'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Portal - Student Hub</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/faculty.css">
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
        <nav class="main-nav" aria-label="Faculty Navigation">
            <a href="dashboard.php">Dashboard</a>
            <a href="students.php">Students</a>
            <a href="courses.php">Courses</a>
            <?php if ($isAdmin): ?>
                <a href="admin.php" style="color: #ef4444; font-weight: 700;">Admin Center</a>
            <?php endif; ?>
            <a href="faculty.php" class="active">Faculty Portal</a>
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
                    <?= htmlspecialchars($currentUser['name'] ?? 'Faculty Member', ENT_QUOTES, 'UTF-8') ?>
                </span>
                <a class="btn-logout" id="logoutLink" href="../StudentHUB_php/logout.php">Logout</a>
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
                <a href="dashboard.php">Dashboard</a>
                <a href="students.php">Students</a>
                <a href="courses.php">Courses</a>
                <?php if ($isAdmin): ?>
                <a href="admin.php" style="color: #ef4444; font-weight: 700;">Admin Center</a>
            <?php endif; ?>
            <a href="faculty.php" class="active">Faculty Portal</a>
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

    <main class="faculty-container">
        <!-- Faculty Hero Header -->
        <section class="faculty-hero">
            <div class="faculty-profile-info">
                <div class="faculty-avatar">
                    <?php
                    $names = explode(' ', trim($currentUser['name'] ?? 'Faculty Member'));
                    $initials = '';
                    foreach ($names as $n) {
                        if (!empty($n)) {
                            $initials .= strtoupper($n[0]);
                        }
                    }
                    echo htmlspecialchars(substr($initials, 0, 2), ENT_QUOTES, 'UTF-8');
                    ?>
                </div>
                <div class="faculty-meta">
                    <h1><?= htmlspecialchars($currentUser['name'] ?? 'Faculty Member', ENT_QUOTES, 'UTF-8') ?></h1>
                    <div class="faculty-subtext">
                        <span class="role-badge"><?= htmlspecialchars(ucfirst($currentUser['role'] ?? 'teacher'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span>📧 <?= htmlspecialchars($currentUser['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                        <span>🏢 Department of Computer Engineering</span>
                    </div>
                </div>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <button class="btn btn-primary" onclick="openAddCourseModal()">➕ Add New Course</button>
            </div>
        </section>

        <!-- KPI Metric Summary -->
        <section class="faculty-kpis">
            <div class="kpi-card">
                <div class="kpi-label">Courses Managed</div>
                <div class="kpi-value" id="kpiCourseCount"><?= $totalCoursesCount ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Total Enrolled Students</div>
                <div class="kpi-value" id="kpiStudentCount" style="color: #60a5fa;"><?= $totalActiveStudents ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Dept. Average CGPA</div>
                <div class="kpi-value" id="kpiAvgCgpa" style="color: #34d399;"><?= $avgDeptCgpa ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Active Academic Programs</div>
                <div class="kpi-value" style="color: #fbbf24;"><?= $totalDepts ?></div>
            </div>
        </section>

        <!-- Navigation Tabs -->
        <nav class="faculty-tabs" aria-label="Faculty Portal Modules">
            <button class="tab-btn active" id="tabBtnCourses" onclick="switchTab('courses')">
                📚 Course Data Management
            </button>
            <button class="tab-btn" id="tabBtnStudents" onclick="switchTab('students')">
                🎓 Student Academic Performance
            </button>
        </nav>

        <!-- TAB 1: COURSE DATA MANAGEMENT -->
        <section id="tabContentCourses" class="tab-content active">
            <div class="section-bar">
                <div>
                    <h2 class="section-title">Department Courses & Syllabus</h2>
                    <p class="section-desc">Create, edit course titles, credits, semester allotments, and syllabus details.</p>
                </div>
                <div>
                    <button class="btn btn-primary" onclick="openAddCourseModal()">➕ Add Course</button>
                </div>
            </div>

            <!-- Course Search & Filters -->
            <div class="faculty-filter-bar">
                <div class="filter-grow">
                    <input type="text" id="courseSearchInput" class="input-faculty" style="width: 100%; box-sizing: border-box;" placeholder="🔍 Search courses by code, title, or syllabus..." oninput="debounceCourseFetch()">
                </div>
                <div>
                    <select id="courseSemFilter" class="select-faculty" onchange="fetchCourses()">
                        <option value="">All Semesters</option>
                        <option value="1">Semester 1</option>
                        <option value="2">Semester 2</option>
                        <option value="3">Semester 3</option>
                        <option value="4">Semester 4</option>
                        <option value="5">Semester 5</option>
                        <option value="6">Semester 6</option>
                        <option value="7">Semester 7</option>
                        <option value="8">Semester 8</option>
                    </select>
                </div>
                <div>
                    <button class="btn btn-secondary" onclick="resetCourseFilters()" style="padding: 9px 14px; font-size: 13px;">Reset</button>
                </div>
            </div>

            <!-- Courses Grid Container -->
            <div class="courses-faculty-grid" id="facultyCoursesGrid">
                <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--text-muted);">
                    Loading courses...
                </div>
            </div>
        </section>

        <!-- TAB 2: STUDENT ACADEMIC PERFORMANCE -->
        <section id="tabContentStudents" class="tab-content">
            <div class="section-bar">
                <div>
                    <h2 class="section-title">Student Academic Performance & Course Allotment</h2>
                    <p class="section-desc">Review student enrollments and edit academic CGPA, semester, and course assignments.</p>
                </div>
            </div>

            <!-- Student Filter Bar -->
            <div class="faculty-filter-bar">
                <div class="filter-grow">
                    <input type="text" id="studentSearchInput" class="input-faculty" style="width: 100%; box-sizing: border-box;" placeholder="🔍 Search by student name, enrollment number, or email..." oninput="debounceStudentFetch()">
                </div>
                <div>
                    <select id="studentCourseFilter" class="select-faculty" onchange="fetchStudents(1)">
                        <option value="">All Courses</option>
                        <?php foreach ($allCoursesList as $ct): ?>
                            <option value="<?= htmlspecialchars($ct, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($ct, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                        <option value="B.Tech CE">B.Tech CE</option>
                        <option value="B.Tech IT">B.Tech IT</option>
                        <option value="BCA">BCA</option>
                        <option value="MCA">MCA</option>
                    </select>
                </div>
                <div>
                    <select id="studentSemFilter" class="select-faculty" onchange="fetchStudents(1)">
                        <option value="">All Semesters</option>
                        <option value="1">Semester 1</option>
                        <option value="2">Semester 2</option>
                        <option value="3">Semester 3</option>
                        <option value="4">Semester 4</option>
                        <option value="5">Semester 5</option>
                        <option value="6">Semester 6</option>
                        <option value="7">Semester 7</option>
                        <option value="8">Semester 8</option>
                    </select>
                </div>
                <div>
                    <button class="btn btn-secondary" onclick="resetStudentFilters()" style="padding: 9px 14px; font-size: 13px;">Reset</button>
                </div>
            </div>

            <!-- Student Table -->
            <div class="table-container">
                <table class="faculty-table">
                    <thead>
                        <tr>
                            <th>Enrollment No</th>
                            <th>Student Name</th>
                            <th>Email Address</th>
                            <th>Enrolled Course</th>
                            <th>Semester</th>
                            <th>CGPA</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="studentTableBody">
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                Loading student performance records...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Student Pagination -->
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; color: var(--text-muted); font-size: 13px;">
                <span id="studentPaginationInfo">Showing 0 of 0 records</span>
                <div style="display: flex; gap: 6px;" id="studentPaginationButtons">
                    <!-- Dynamic Page Buttons -->
                </div>
            </div>
        </section>
    </main>

    <!-- ====================================================================== -->
    <!-- MODAL 1: ADD / EDIT COURSE -->
    <!-- ====================================================================== -->
    <div class="faculty-modal-overlay" id="courseModal">
        <div class="faculty-modal-card">
            <div class="modal-header-row">
                <h2 id="courseModalTitle">Add New Course</h2>
                <button class="modal-close-btn" onclick="closeCourseModal()">&times;</button>
            </div>
            <form id="courseForm" onsubmit="handleCourseSubmit(event)">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" id="courseFormAction" value="create">
                <input type="hidden" name="course_id" id="courseFormId" value="">

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label" for="courseCodeInput">Course Code *</label>
                        <input type="text" id="courseCodeInput" name="course_code" class="form-control-input" placeholder="e.g. IT203" required maxlength="20">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="courseCreditsInput">Credits (1 - 8) *</label>
                        <input type="number" id="courseCreditsInput" name="credits" class="form-control-input" min="1" max="8" value="4" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="courseTitleInput">Course Title *</label>
                    <input type="text" id="courseTitleInput" name="course_title" class="form-control-input" placeholder="e.g. Web Development Frameworks" required maxlength="150">
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label" for="courseDeptInput">Department *</label>
                        <select id="courseDeptInput" name="department" class="form-control-select">
                            <option value="Computer Engineering">Computer Engineering</option>
                            <option value="Information Technology">Information Technology</option>
                            <option value="Electronics & Communication">Electronics & Communication</option>
                            <option value="Computer Applications (BCA/MCA)">Computer Applications (BCA/MCA)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="courseSemInput">Semester (1 - 10) *</label>
                        <input type="number" id="courseSemInput" name="semester" class="form-control-input" min="1" max="10" value="3" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="courseDescInput">Course Syllabus & Description</label>
                    <textarea id="courseDescInput" name="description" class="form-control-textarea" rows="3" placeholder="Overview, learning outcomes, syllabus topics..."></textarea>
                </div>

                <div id="courseModalErrors" style="color: #ef4444; font-size: 13px; margin-bottom: 12px; display: none;"></div>

                <div class="modal-footer-row">
                    <button type="button" class="btn btn-secondary" onclick="closeCourseModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="courseSubmitBtn">Save Course</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ====================================================================== -->
    <!-- MODAL 2: EDIT STUDENT ACADEMIC DATA -->
    <!-- ====================================================================== -->
    <div class="faculty-modal-overlay" id="studentAcademicModal">
        <div class="faculty-modal-card">
            <div class="modal-header-row">
                <h2>Edit Student Academic Performance</h2>
                <button class="modal-close-btn" onclick="closeStudentModal()">&times;</button>
            </div>
            <form id="studentAcademicForm" onsubmit="handleStudentAcademicSubmit(event)">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="student_id" id="editStudentId" value="">

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Student Name</label>
                        <input type="text" id="editStudentName" name="full_name" class="form-control-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Enrollment No</label>
                        <input type="text" id="editStudentEnrollment" name="enrollment_no" class="form-control-input" required style="font-family: monospace;">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" id="editStudentEmail" name="email" class="form-control-input" required>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Course / Program *</label>
                        <select id="editStudentCourse" name="course_name" class="form-control-select" required>
                            <?php foreach ($allCoursesList as $ct): ?>
                                <option value="<?= htmlspecialchars($ct, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($ct, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                            <option value="B.Tech CE">B.Tech CE</option>
                            <option value="B.Tech IT">B.Tech IT</option>
                            <option value="BCA">BCA</option>
                            <option value="MCA">MCA</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Department *</label>
                        <select id="editStudentDept" name="department" class="form-control-select" required>
                            <option value="Computer Engineering">Computer Engineering</option>
                            <option value="Information Technology">Information Technology</option>
                            <option value="Electronics & Communication">Electronics & Communication</option>
                            <option value="Computer Applications">Computer Applications</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Semester (1 - 8) *</label>
                        <input type="number" id="editStudentSemester" name="semester" class="form-control-input" min="1" max="8" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">CGPA (0.00 - 10.00) *</label>
                        <input type="number" id="editStudentCgpa" name="cgpa" class="form-control-input" min="0.00" max="10.00" step="0.01" required>
                    </div>
                </div>

                <div id="studentModalErrors" style="color: #ef4444; font-size: 13px; margin-bottom: 12px; display: none;"></div>

                <div class="modal-footer-row">
                    <button type="button" class="btn btn-secondary" onclick="closeStudentModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="studentSubmitBtn">Update Academic Record</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Floating Feedback Toast -->
    <div id="facultyToast" class="faculty-toast"></div>

    <footer class="site-footer">
        <p>&copy; 2026 Student Hub - CHARUSAT. All rights reserved.</p>
    </footer>

    <script src="../js/script.js"></script>
    <script>
        const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
        let cachedCourses = [];
        let courseDebounceTimer = null;
        let studentDebounceTimer = null;
        let currentStudentPage = 1;

        // Toast Helper
        function showToast(msg, isSuccess = true) {
            const toast = document.getElementById('facultyToast');
            toast.textContent = msg;
            toast.className = 'faculty-toast ' + (isSuccess ? 'toast-success' : 'toast-error');
            toast.style.display = 'block';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3500);
        }

        // Tab Switching
        function switchTab(tab) {
            const btnCourses = document.getElementById('tabBtnCourses');
            const btnStudents = document.getElementById('tabBtnStudents');
            const tabCourses = document.getElementById('tabContentCourses');
            const tabStudents = document.getElementById('tabContentStudents');

            if (tab === 'courses') {
                btnCourses.classList.add('active');
                btnStudents.classList.remove('active');
                tabCourses.classList.add('active');
                tabStudents.classList.remove('active');
            } else {
                btnStudents.classList.add('active');
                btnCourses.classList.remove('active');
                tabStudents.classList.add('active');
                tabCourses.classList.remove('active');
                fetchStudents(1);
            }
        }

        // ====================================================================
        // COURSES MODULE
        // ====================================================================
        function debounceCourseFetch() {
            clearTimeout(courseDebounceTimer);
            courseDebounceTimer = setTimeout(fetchCourses, 300);
        }

        function resetCourseFilters() {
            document.getElementById('courseSearchInput').value = '';
            document.getElementById('courseSemFilter').value = '';
            fetchCourses();
        }

        async function fetchCourses() {
            const search = document.getElementById('courseSearchInput').value.trim();
            const semester = document.getElementById('courseSemFilter').value;

            let url = `../StudentHUB_php/courses_crud.php?action=list&search=${encodeURIComponent(search)}`;
            if (semester) {
                url += `&semester=${encodeURIComponent(semester)}`;
            }

            try {
                const res = await fetch(url);
                const result = await res.json();
                if (result.success && result.data && Array.isArray(result.data.courses)) {
                    cachedCourses = result.data.courses;
                    renderCourses(cachedCourses);
                    document.getElementById('kpiCourseCount').textContent = cachedCourses.length;
                } else {
                    renderCourses([]);
                }
            } catch (err) {
                console.error('Failed to fetch courses:', err);
                document.getElementById('facultyCoursesGrid').innerHTML = `
                    <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #ef4444;">
                        ⚠️ Failed to load courses. Please refresh the page.
                    </div>
                `;
            }
        }

        function renderCourses(courses) {
            const container = document.getElementById('facultyCoursesGrid');
            if (!courses.length) {
                container.innerHTML = `
                    <div style="grid-column: 1/-1; text-align: center; padding: 50px 20px; background: var(--bg-card); border: 1px dashed var(--border-color); border-radius: 12px;">
                        <p style="font-size: 16px; color: var(--text-muted); margin: 0 0 10px;">No courses found matching your search criteria.</p>
                        <button class="btn btn-primary" onclick="openAddCourseModal()">➕ Add New Course</button>
                    </div>
                `;
                return;
            }

            container.innerHTML = courses.map(course => {
                const desc = course.description || 'No detailed syllabus added yet.';
                return `
                    <article class="faculty-course-card" id="course-card-${course.course_id}">
                        <div class="faculty-course-header">
                            <span class="faculty-course-code">${escapeHtml(course.course_code)}</span>
                            <span class="faculty-course-sem">Sem ${course.semester}</span>
                        </div>
                        <div class="faculty-course-body">
                            <h3 class="faculty-course-title">${escapeHtml(course.course_title)}</h3>
                            <div class="faculty-course-dept">
                                🏢 ${escapeHtml(course.department || 'Computer Engineering')}
                            </div>
                            <p class="faculty-course-desc">${escapeHtml(desc)}</p>
                            <div class="faculty-course-meta">
                                <span>⚡ <strong>${course.credits}</strong> Credits</span>
                                <span>👥 <strong>${course.enrolled_count || 0}</strong> Enrolled</span>
                            </div>
                        </div>
                        <div class="faculty-course-footer">
                            <button class="btn-faculty-edit" onclick="openEditCourseModal(${course.course_id})">
                                ✏️ Edit Course
                            </button>
                            <button class="btn-faculty-delete" onclick="handleDeleteCourse(${course.course_id}, '${escapeHtml(course.course_title)}')">
                                🗑️ Delete
                            </button>
                        </div>
                    </article>
                `;
            }).join('');
        }

        function openAddCourseModal() {
            document.getElementById('courseModalTitle').textContent = 'Add New Course';
            document.getElementById('courseFormAction').value = 'create';
            document.getElementById('courseFormId').value = '';
            document.getElementById('courseCodeInput').value = '';
            document.getElementById('courseCodeInput').readOnly = false;
            document.getElementById('courseTitleInput').value = '';
            document.getElementById('courseDeptInput').value = 'Computer Engineering';
            document.getElementById('courseCreditsInput').value = 4;
            document.getElementById('courseSemInput').value = 3;
            document.getElementById('courseDescInput').value = '';
            document.getElementById('courseModalErrors').style.display = 'none';
            document.getElementById('courseSubmitBtn').textContent = 'Create Course';
            document.getElementById('courseModal').classList.add('open');
        }

        function openEditCourseModal(courseId) {
            const course = cachedCourses.find(c => Number(c.course_id) === Number(courseId));
            if (!course) return;

            document.getElementById('courseModalTitle').textContent = `Edit Course: ${course.course_code}`;
            document.getElementById('courseFormAction').value = 'update';
            document.getElementById('courseFormId').value = course.course_id;
            document.getElementById('courseCodeInput').value = course.course_code;
            document.getElementById('courseTitleInput').value = course.course_title;
            document.getElementById('courseDeptInput').value = course.department || 'Computer Engineering';
            document.getElementById('courseCreditsInput').value = course.credits;
            document.getElementById('courseSemInput').value = course.semester;
            document.getElementById('courseDescInput').value = course.description || '';
            document.getElementById('courseModalErrors').style.display = 'none';
            document.getElementById('courseSubmitBtn').textContent = 'Update Course';
            document.getElementById('courseModal').classList.add('open');
        }

        function closeCourseModal() {
            document.getElementById('courseModal').classList.remove('open');
        }

        async function handleCourseSubmit(e) {
            e.preventDefault();
            const btn = document.getElementById('courseSubmitBtn');
            const errBox = document.getElementById('courseModalErrors');
            errBox.style.display = 'none';
            btn.disabled = true;

            const form = document.getElementById('courseForm');
            const formData = new FormData(form);

            try {
                const res = await fetch('../StudentHUB_php/courses_crud.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const result = await res.json();
                if (result.success) {
                    showToast(result.message, true);
                    closeCourseModal();
                    fetchCourses();
                } else {
                    const errors = result.errors && result.errors.length ? result.errors.join('<br>') : result.message;
                    errBox.innerHTML = errors;
                    errBox.style.display = 'block';
                }
            } catch (err) {
                errBox.textContent = 'A network error occurred. Please try again.';
                errBox.style.display = 'block';
            } finally {
                btn.disabled = false;
            }
        }

        async function handleDeleteCourse(courseId, title) {
            if (!confirm(`Are you sure you want to delete course "${title}"? This cannot be undone.`)) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('course_id', courseId);
            formData.append('csrf_token', CSRF_TOKEN);

            try {
                const res = await fetch('../StudentHUB_php/courses_crud.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const result = await res.json();
                if (result.success) {
                    showToast(result.message, true);
                    fetchCourses();
                } else {
                    showToast(result.message || 'Failed to delete course.', false);
                }
            } catch (err) {
                showToast('Failed to delete course due to network error.', false);
            }
        }

        // ====================================================================
        // STUDENTS ACADEMIC PERFORMANCE MODULE
        // ====================================================================
        function debounceStudentFetch() {
            clearTimeout(studentDebounceTimer);
            studentDebounceTimer = setTimeout(() => fetchStudents(1), 300);
        }

        function resetStudentFilters() {
            document.getElementById('studentSearchInput').value = '';
            document.getElementById('studentCourseFilter').value = '';
            document.getElementById('studentSemFilter').value = '';
            fetchStudents(1);
        }

        async function fetchStudents(page = 1) {
            currentStudentPage = page;
            const search = document.getElementById('studentSearchInput').value.trim();
            const course = document.getElementById('studentCourseFilter').value;
            const semester = document.getElementById('studentSemFilter').value;

            let url = `../StudentHUB_php/students_crud.php?action=list&page=${page}&limit=10&search=${encodeURIComponent(search)}`;
            if (course) url += `&course=${encodeURIComponent(course)}`;
            if (semester) url += `&semester=${encodeURIComponent(semester)}`;

            try {
                const res = await fetch(url);
                const result = await res.json();
                if (result.success && result.data) {
                    renderStudents(result.data.students);
                    renderStudentPagination(result.data.pagination);
                    if (result.data.kpi) {
                        document.getElementById('kpiStudentCount').textContent = result.data.kpi.total_active || 0;
                        document.getElementById('kpiAvgCgpa').textContent = Number(result.data.kpi.avg_cgpa || 0).toFixed(2);
                    }
                } else {
                    renderStudents([]);
                }
            } catch (err) {
                console.error('Failed to fetch students:', err);
                document.getElementById('studentTableBody').innerHTML = `
                    <tr><td colspan="7" style="text-align: center; color: #ef4444; padding: 20px;">⚠️ Error loading students.</td></tr>
                `;
            }
        }

        function renderStudents(students) {
            const tbody = document.getElementById('studentTableBody');
            if (!students.length) {
                tbody.innerHTML = `
                    <tr><td colspan="7" style="text-align: center; padding: 30px; color: var(--text-muted);">No student records found matching the filter criteria.</td></tr>
                `;
                return;
            }

            tbody.innerHTML = students.map(s => {
                const cgpaVal = parseFloat(s.cgpa) || 0.0;
                let cgpaClass = 'cgpa-good';
                if (cgpaVal >= 9.0) cgpaClass = 'cgpa-excellent';
                else if (cgpaVal >= 7.5) cgpaClass = 'cgpa-good';
                else if (cgpaVal >= 6.0) cgpaClass = 'cgpa-average';
                else cgpaClass = 'cgpa-danger';

                return `
                    <tr id="student-row-${s.student_id}">
                        <td><span style="font-family: monospace; font-weight: 700; color: var(--brand-primary);">${escapeHtml(s.enrollment_no)}</span></td>
                        <td><strong>${escapeHtml(s.full_name)}</strong></td>
                        <td style="color: var(--text-secondary);">${escapeHtml(s.email)}</td>
                        <td><span style="background: var(--bg-subtle); padding: 3px 8px; border-radius: 4px; font-size: 12px; border: 1px solid var(--border-color);">${escapeHtml(s.course_name)}</span></td>
                        <td>Sem ${s.semester}</td>
                        <td><span class="cgpa-pill ${cgpaClass}">${cgpaVal.toFixed(2)}</span></td>
                        <td style="text-align: right;">
                            <button class="btn-faculty-edit" onclick="openEditStudentModal(${s.student_id})">
                                ✏️ Edit Academic Data
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function renderStudentPagination(p) {
            if (!p) return;
            const info = document.getElementById('studentPaginationInfo');
            const btns = document.getElementById('studentPaginationButtons');

            const start = p.total_records === 0 ? 0 : (p.current_page - 1) * p.limit + 1;
            const end = Math.min(p.current_page * p.limit, p.total_records);
            info.textContent = `Showing ${start} to ${end} of ${p.total_records} students`;

            let html = '';
            if (p.current_page > 1) {
                html += `<button class="btn btn-secondary" style="padding: 4px 10px; font-size: 12px;" onclick="fetchStudents(${p.current_page - 1})">&laquo; Prev</button>`;
            }
            for (let i = 1; i <= p.total_pages; i++) {
                if (i === 1 || i === p.total_pages || (i >= p.current_page - 1 && i <= p.current_page + 1)) {
                    const activeStyle = i === p.current_page ? 'background: var(--brand-primary); color: white;' : '';
                    html += `<button class="btn btn-secondary" style="padding: 4px 10px; font-size: 12px; ${activeStyle}" onclick="fetchStudents(${i})">${i}</button>`;
                } else if (i === p.current_page - 2 || i === p.current_page + 2) {
                    html += `<span style="padding: 4px 6px;">...</span>`;
                }
            }
            if (p.current_page < p.total_pages) {
                html += `<button class="btn btn-secondary" style="padding: 4px 10px; font-size: 12px;" onclick="fetchStudents(${p.current_page + 1})">Next &raquo;</button>`;
            }
            btns.innerHTML = html;
        }

        async function openEditStudentModal(studentId) {
            try {
                const res = await fetch(`../StudentHUB_php/students_crud.php?action=get&id=${studentId}`);
                const result = await res.json();
                if (!result.success || !result.data) {
                    showToast('Failed to fetch student details.', false);
                    return;
                }
                const s = result.data;
                document.getElementById('editStudentId').value = s.student_id;
                document.getElementById('editStudentName').value = s.full_name;
                document.getElementById('editStudentEnrollment').value = s.enrollment_no;
                document.getElementById('editStudentEmail').value = s.email;
                document.getElementById('editStudentCourse').value = s.course_name;
                document.getElementById('editStudentDept').value = s.department || 'Computer Engineering';
                document.getElementById('editStudentSemester').value = s.semester;
                document.getElementById('editStudentCgpa').value = parseFloat(s.cgpa).toFixed(2);
                document.getElementById('studentModalErrors').style.display = 'none';

                document.getElementById('studentAcademicModal').classList.add('open');
            } catch (err) {
                showToast('Network error loading student details.', false);
            }
        }

        function closeStudentModal() {
            document.getElementById('studentAcademicModal').classList.remove('open');
        }

        async function handleStudentAcademicSubmit(e) {
            e.preventDefault();
            const btn = document.getElementById('studentSubmitBtn');
            const errBox = document.getElementById('studentModalErrors');
            errBox.style.display = 'none';
            btn.disabled = true;

            const form = document.getElementById('studentAcademicForm');
            const formData = new FormData(form);

            try {
                const res = await fetch('../StudentHUB_php/students_crud.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const result = await res.json();
                if (result.success) {
                    showToast(result.message, true);
                    closeStudentModal();
                    fetchStudents(currentStudentPage);
                } else {
                    const errors = result.errors && result.errors.length ? result.errors.join('<br>') : result.message;
                    errBox.innerHTML = errors;
                    errBox.style.display = 'block';
                }
            } catch (err) {
                errBox.textContent = 'A network error occurred while updating academic data.';
                errBox.style.display = 'block';
            } finally {
                btn.disabled = false;
            }
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // Close modals with Escape key
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeCourseModal();
                closeStudentModal();
            }
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', () => {
            fetchCourses();
        });
    </script>
</body>
</html>
