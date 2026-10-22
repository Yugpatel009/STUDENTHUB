<?php
declare(strict_types=1);

/**
 * ============================================================================
 * CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY (CHARUSAT)
 * FACULTY OF TECHNOLOGY AND ENGINEERING (FTE)
 * SUBJECT: WEB DEVELOPMENT FRAMEWORKS (ITUE203)
 * Practical 11: Student Management CRUD with Search and Filter
 * ============================================================================
 * File: students.php
 * Description: Interactive Student Management UI supporting real-time search,
 *              dynamic multi-filters, sortable columns, AJAX pagination,
 *              Create, Edit, and Soft-Delete modals.
 * ============================================================================
 */

require_once __DIR__ . '/../StudentHUB_php/require_login.php';
require_once __DIR__ . '/../StudentHUB_php/csrf.php';

$currentUser = requireLogin();
$csrfToken = generateCsrfToken();
$isFaculty = in_array($currentUser['role'] ?? '', ['teacher', 'admin'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Student Management (Practical 11)</title>
    <link rel="stylesheet" href="../css/style.css">
    <script>
        (function() {
            var saved = localStorage.getItem('studenthub_theme');
            var theme = saved || ((window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
            document.documentElement.style.colorScheme = theme;
        })();
    </script>
    <style>
        .container { max-width: 1250px; margin: 30px auto; padding: 0 20px; }
        .page-header-row { display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; }
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .kpi-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 18px 20px; box-shadow: var(--shadow-sm); transition: transform 0.2s, border-color 0.2s; }
        .kpi-card:hover { transform: translateY(-2px); border-color: var(--brand-primary); }
        .kpi-card .label { font-size: 12px; font-weight: 600; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; margin-bottom: 6px; }
        .kpi-card .value { font-size: 26px; font-weight: 700; color: var(--text-primary); }
        
        .filter-panel { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 18px 20px; margin-bottom: 20px; box-shadow: var(--shadow-sm); }
        .filter-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 12px; align-items: center; }
        @media (max-width: 900px) { .filter-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 600px) { .filter-grid { grid-template-columns: 1fr; } }
        
        .input-control, .select-control { width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-subtle); color: var(--text-primary); font-size: 14px; transition: border-color 0.2s, box-shadow 0.2s; }
        .input-control:focus, .select-control:focus { outline: none; border-color: var(--brand-primary); box-shadow: 0 0 0 3px var(--brand-glow); }
        .select-control option { background: var(--bg-card); color: var(--text-primary); }
        
        .table-wrap { overflow-x: auto; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; box-shadow: var(--shadow-sm); margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        th { background: var(--bg-subtle); padding: 14px 16px; font-weight: 600; color: var(--text-muted); border-bottom: 1px solid var(--border-color); text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em; white-space: nowrap; }
        td { padding: 14px 16px; border-bottom: 1px solid var(--border-color); vertical-align: middle; color: var(--text-primary); }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: var(--bg-subtle); }
        
        .badge { display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; }
        .badge-green { background: rgba(16, 185, 129, 0.15); color: #059669; }
        [data-theme="dark"] .badge-green { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .badge-blue { background: rgba(59, 130, 246, 0.15); color: #2563eb; }
        [data-theme="dark"] .badge-blue { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        .badge-orange { background: rgba(245, 158, 11, 0.15); color: #d97706; }
        [data-theme="dark"] .badge-orange { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
        .badge-red { background: rgba(239, 68, 68, 0.15); color: #dc2626; }
        [data-theme="dark"] .badge-red { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .badge-archived { background: var(--bg-subtle); color: var(--text-muted); border: 1px solid var(--border-color); }
        
        .code-pill { background: var(--bg-subtle); border: 1px solid var(--border-color); padding: 3px 6px; border-radius: 4px; font-family: monospace; font-size: 12px; color: var(--brand-primary); }
        .btn-action { padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 4px; }
        .btn-edit { background: #3b82f6; color: white; }
        .btn-edit:hover { background: #2563eb; }
        .btn-delete { background: #ef4444; color: white; }
        .btn-delete:hover { background: #dc2626; }
        .btn-restore { background: #10b981; color: white; }
        .btn-restore:hover { background: #059669; }
        
        .pagination-row { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; color: var(--text-muted); }
        .pagination-buttons { display: flex; gap: 6px; }
        .page-btn { padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); cursor: pointer; transition: all 0.2s; }
        .page-btn.active { background: var(--brand-primary); color: white; border-color: var(--brand-primary); }
        .page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
        
        /* Modal Styles */
        .modal-overlay { 
            position: fixed; 
            inset: 0; 
            background: var(--modal-backdrop, rgba(0, 0, 0, 0.6)); 
            display: none; 
            align-items: center; 
            justify-content: center; 
            z-index: 2000; 
            padding: 20px; 
            backdrop-filter: blur(4px); 
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s ease;
        }
        .modal-overlay.open, .modal-overlay.active { 
            display: flex !important; 
            opacity: 1 !important; 
            visibility: visible !important; 
        }
        .modal-card { 
            background: var(--bg-card); 
            border: 1px solid var(--border-color); 
            border-radius: 16px; 
            width: 100%; 
            max-width: 540px; 
            padding: 28px; 
            box-shadow: var(--shadow-xl); 
            position: relative; 
            animation: modalPop 0.25s cubic-bezier(0.16, 1, 0.3, 1); 
            color: var(--text-primary); 
            opacity: 1 !important;
            transform: scale(1) translateY(0) !important;
        }
        @keyframes modalPop { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h2 { margin: 0; font-size: 20px; color: var(--text-primary); }
        .modal-close { background: none; border: none; font-size: 22px; cursor: pointer; color: var(--text-muted); }
        .modal-close:hover { color: var(--text-primary); }
        .form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .toast { position: fixed; bottom: 24px; right: 24px; padding: 14px 20px; border-radius: 8px; color: white; font-size: 14px; font-weight: 500; display: none; z-index: 1100; box-shadow: 0 10px 25px rgba(0,0,0,0.4); }
        .toast-success { background: #059669; }
        .toast-error { background: #dc2626; }
    </style>
</head>
<body>
    <header class="site-header">
        <a class="brand" href="../index.html">
            <img src="../img/logo.png" alt="Student Hub Logo">
        </a>
        <nav class="main-nav" aria-label="Main Navigation">
            <a href="dashboard.php">Dashboard</a>
            <a href="students.php" class="active">Students</a>
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
        <div class="header-right">
            <div class="header-actions">
                <span style="font-size: 13px; color: var(--text-muted, #94a3b8); margin-right: 8px;">
                    <?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8') ?>
                </span>
                <a class="btn-logout" href="../StudentHUB_php/logout.php">Logout</a>
            </div>
            <button id="themeToggle" class="theme-toggle" aria-label="Toggle Theme" type="button">
                <svg class="sun-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                <svg class="moon-icon" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
            </button>
            <button id="hamburgerBtn" class="hamburger-btn" aria-label="Menu" type="button">
                <span></span><span></span><span></span>
            </button>
        </div>
        <div class="mobile-nav-drawer" id="mobileNavDrawer">
            <nav class="mobile-nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="students.php" class="active">Students</a>
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
                <a class="btn-logout" style="text-align:center; justify-content:center;" href="../StudentHUB_php/logout.php">Logout</a>
            </div>
        </div>
    </header>

    <main class="container">
        <!-- Top Heading & Actions -->
        <section class="page-header-row">
            <div>
                <p style="text-transform: uppercase; font-size: 12px; letter-spacing: 0.1em; color: #3b82f6; font-weight: 700; margin: 0 0 6px;">
                    Practical 11: MySQL Database CRUD
                </p>
                <h1 style="margin: 0; font-size: 28px;">Student Records Management</h1>
                <p style="margin: 6px 0 0; color: var(--text-muted, #94a3b8); font-size: 14px;">
                    Search, filter, paginate, and maintain verified student profiles with prepared statements.
                </p>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn btn-primary" id="openAddModalBtn" onclick="openAddModal()">➕ Add New Student</button>
            </div>
        </section>

        <!-- KPI Summary Cards -->
        <section class="kpi-grid">
            <div class="kpi-card">
                <div class="label">Total Active Students</div>
                <div class="value" id="kpiTotalActive">0</div>
            </div>
            <div class="kpi-card">
                <div class="label">Average CGPA</div>
                <div class="value" id="kpiAvgCgpa" style="color: #34d399;">0.00</div>
            </div>
            <div class="kpi-card">
                <div class="label">Distinct Courses</div>
                <div class="value" id="kpiCourses" style="color: #60a5fa;">0</div>
            </div>
            <div class="kpi-card">
                <div class="label">Archived (Soft-Deleted)</div>
                <div class="value" id="kpiDeleted" style="color: #f87171;">0</div>
            </div>
        </section>

        <!-- Search & Filter Controls -->
        <section class="filter-panel">
            <div class="filter-grid">
                <div>
                    <input type="text" id="searchInput" class="input-control" placeholder="🔍 Search by name, email, or enrollment no..." oninput="debounceFetch()">
                </div>
                <div>
                    <select id="courseFilter" class="select-control" onchange="fetchStudents(1)">
                        <option value="">All Courses</option>
                        <option value="B.Tech CE">B.Tech CE</option>
                        <option value="B.Tech IT">B.Tech IT</option>
                        <option value="B.Tech EC">B.Tech EC</option>
                        <option value="BCA">BCA</option>
                        <option value="MCA">MCA</option>
                    </select>
                </div>
                <div>
                    <select id="semesterFilter" class="select-control" onchange="fetchStudents(1)">
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
                    <select id="sortBy" class="select-control" onchange="fetchStudents(1)">
                        <option value="student_id">Sort: ID (Default)</option>
                        <option value="full_name">Sort: Name (A-Z)</option>
                        <option value="enrollment_no">Sort: Enrollment No</option>
                        <option value="cgpa">Sort: CGPA (High &rarr; Low)</option>
                        <option value="semester">Sort: Semester</option>
                    </select>
                </div>
                <div>
                    <button class="btn btn-secondary" style="padding: 10px 14px; font-size: 13px;" onclick="resetFilters()">Reset</button>
                </div>
            </div>
            <div style="margin-top: 12px; display: flex; justify-content: space-between; align-items: center; font-size: 13px;">
                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; color: var(--text-muted, #94a3b8);">
                    <input type="checkbox" id="showDeletedToggle" onchange="fetchStudents(1)">
                    Show archived (soft-deleted) students
                </label>
                <div style="color: var(--text-muted, #94a3b8);">
                    Showing per page:
                    <select id="limitSelect" style="background: none; border: 1px solid #334155; color: inherit; border-radius: 4px; padding: 2px 6px;" onchange="fetchStudents(1)">
                        <option value="10" selected>10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                    </select>
                </div>
            </div>
        </section>

        <!-- Student Data Table -->
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px;">#ID</th>
                        <th>Student Name</th>
                        <th>Enrollment No</th>
                        <th>Email Address</th>
                        <th>Course</th>
                        <th>Semester</th>
                        <th>CGPA</th>
                        <th style="text-align: right; width: 170px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="studentTableBody">
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted, #94a3b8);">
                            Loading student records...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination Bar -->
        <section class="pagination-row">
            <div style="font-size: 13px; color: var(--text-muted, #94a3b8);" id="paginationInfo">
                Showing 0 of 0 entries
            </div>
            <div class="pagination-buttons" id="paginationControls">
                <!-- Injected via JS -->
            </div>
        </section>
    </main>

    <!-- Modal: Add New Student -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-card">
            <div class="modal-header">
                <h2>Add New Student</h2>
                <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
            </div>
            <form id="addStudentForm" onsubmit="handleCreateStudent(event)">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group" style="margin-bottom: 12px;">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" class="input-control" placeholder="e.g. Yash Patel" required minlength="2" maxlength="100">
                </div>
                <div class="form-group" style="margin-bottom: 12px;">
                    <label>Email Address *</label>
                    <input type="email" name="email" class="input-control" placeholder="yash.patel@charusat.edu.in" required>
                </div>
                <div class="form-row-2" style="margin-bottom: 12px;">
                    <div class="form-group">
                        <label>Enrollment No *</label>
                        <input type="text" name="enrollment_no" class="input-control" placeholder="25DCE001" required minlength="3" maxlength="30">
                    </div>
                    <div class="form-group">
                        <label>Course *</label>
                        <select name="course_name" class="select-control" required>
                            <option value="">Select Course</option>
                            <option value="B.Tech CE">B.Tech CE</option>
                            <option value="B.Tech IT">B.Tech IT</option>
                            <option value="B.Tech EC">B.Tech EC</option>
                            <option value="BCA">BCA</option>
                            <option value="MCA">MCA</option>
                        </select>
                    </div>
                </div>
                <div class="form-row-2" style="margin-bottom: 16px;">
                    <div class="form-group">
                        <label>Semester (1-10) *</label>
                        <input type="number" name="semester" class="input-control" min="1" max="10" value="3" required>
                    </div>
                    <div class="form-group">
                        <label>CGPA (0.00 - 10.00) *</label>
                        <input type="number" step="0.01" name="cgpa" class="input-control" min="0" max="10" value="8.50" required>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addSubmitBtn">Create Student Record</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Edit Student -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-card">
            <div class="modal-header">
                <h2>Edit Student Record</h2>
                <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form id="editStudentForm" onsubmit="handleUpdateStudent(event)">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="student_id" id="editStudentId" value="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group" style="margin-bottom: 12px;">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" id="editFullName" class="input-control" required minlength="2" maxlength="100">
                </div>
                <div class="form-group" style="margin-bottom: 12px;">
                    <label>Email Address *</label>
                    <input type="email" name="email" id="editEmail" class="input-control" required>
                </div>
                <div class="form-row-2" style="margin-bottom: 12px;">
                    <div class="form-group">
                        <label>Enrollment No *</label>
                        <input type="text" name="enrollment_no" id="editEnrollment" class="input-control" required minlength="3" maxlength="30">
                    </div>
                    <div class="form-group">
                        <label>Course *</label>
                        <select name="course_name" id="editCourse" class="select-control" required>
                            <option value="B.Tech CE">B.Tech CE</option>
                            <option value="B.Tech IT">B.Tech IT</option>
                            <option value="B.Tech EC">B.Tech EC</option>
                            <option value="BCA">BCA</option>
                            <option value="MCA">MCA</option>
                        </select>
                    </div>
                </div>
                <div class="form-row-2" style="margin-bottom: 16px;">
                    <div class="form-group">
                        <label>Semester (1-10) *</label>
                        <input type="number" name="semester" id="editSemester" class="input-control" min="1" max="10" required>
                    </div>
                    <div class="form-group">
                        <label>CGPA (0.00 - 10.00) *</label>
                        <input type="number" step="0.01" name="cgpa" id="editCgpa" class="input-control" min="0" max="10" required>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="editSubmitBtn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Notification Toast -->
    <div id="toast" class="toast"></div>

    <footer class="site-footer" style="margin-top: 60px;">
        <p>&copy; 2026 Student Hub. CHARUSAT ITUE203 - Practical 11.</p>
    </footer>

    <script src="../js/script.js"></script>
    <script>
        const API_URL = '../StudentHUB_php/students_crud.php';
        let currentPage = 1;
        let debounceTimer = null;

        function showToast(message, isSuccess = true) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast ' + (isSuccess ? 'toast-success' : 'toast-error');
            toast.style.display = 'block';
            setTimeout(() => { toast.style.display = 'none'; }, 3500);
        }

        function openModal(id) {
            const el = document.getElementById(id);
            if (el) {
                el.classList.add('open');
                el.classList.add('active');
            }
        }

        function closeModal(id) {
            const el = document.getElementById(id);
            if (el) {
                el.classList.remove('open');
                el.classList.remove('active');
            }
        }

        function openAddModal() {
            const form = document.getElementById('addStudentForm');
            if (form) form.reset();
            openModal('addModal');
        }

        function debounceFetch() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => { fetchStudents(1); }, 300);
        }

        function resetFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('courseFilter').value = '';
            document.getElementById('semesterFilter').value = '';
            document.getElementById('sortBy').value = 'student_id';
            document.getElementById('showDeletedToggle').checked = false;
            fetchStudents(1);
        }

        function getCgpaBadge(cgpa) {
            const score = parseFloat(cgpa);
            if (score >= 9.0) return `<span class="badge badge-green">${score.toFixed(2)}</span>`;
            if (score >= 8.0) return `<span class="badge badge-blue">${score.toFixed(2)}</span>`;
            if (score >= 7.0) return `<span class="badge badge-orange">${score.toFixed(2)}</span>`;
            return `<span class="badge badge-red">${score.toFixed(2)}</span>`;
        }

        function fetchStudents(page = 1) {
            currentPage = page;
            const search = document.getElementById('searchInput').value.trim();
            const course = document.getElementById('courseFilter').value;
            const semester = document.getElementById('semesterFilter').value;
            const sort = document.getElementById('sortBy').value;
            const limit = document.getElementById('limitSelect').value;
            const showDeleted = document.getElementById('showDeletedToggle').checked ? '1' : '0';

            const params = new URLSearchParams({
                action: 'list',
                page: page,
                limit: limit,
                search: search,
                course: course,
                semester: semester,
                sort: sort,
                order: (sort === 'cgpa' ? 'DESC' : 'ASC'),
                show_deleted: showDeleted
            });

            fetch(`${API_URL}?${params.toString()}`)
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        showToast(res.message || 'Error fetching records', false);
                        return;
                    }

                    // Update KPI cards
                    if (res.data.kpi) {
                        document.getElementById('kpiTotalActive').textContent = res.data.kpi.total_active;
                        document.getElementById('kpiAvgCgpa').textContent = res.data.kpi.avg_cgpa.toFixed(2);
                        document.getElementById('kpiCourses').textContent = res.data.kpi.total_courses;
                        document.getElementById('kpiDeleted').textContent = res.data.kpi.total_deleted;
                    }

                    // Render Table Rows
                    const tbody = document.getElementById('studentTableBody');
                    const students = res.data.students || [];

                    if (students.length === 0) {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted, #94a3b8);">
                                    🔍 No student records matched the search & filter criteria.
                                </td>
                            </tr>`;
                    } else {
                        tbody.innerHTML = students.map(s => {
                            const isDel = s.is_deleted == 1;
                            const actionBtns = isDel ? `
                                <button class="btn-action btn-restore" onclick="restoreStudent(${s.student_id})">↩ Restore</button>
                                <button class="btn-action btn-delete" onclick="permanentDelete(${s.student_id})">✖ Delete</button>
                            ` : `
                                <button class="btn-action btn-edit" onclick="openEditModal(${s.student_id})">✏ Edit</button>
                                <button class="btn-action btn-delete" onclick="softDeleteStudent(${s.student_id}, '${escapeHtml(s.full_name)}')">🗑 Delete</button>
                            `;

                            return `
                                <tr style="${isDel ? 'opacity: 0.6; background: rgba(239, 68, 68, 0.05);' : ''}">
                                    <td style="color: var(--text-muted, #94a3b8); font-family: monospace;">#${s.student_id}</td>
                                    <td>
                                        <strong>${escapeHtml(s.full_name)}</strong>
                                        ${isDel ? '<span class="badge badge-archived" style="margin-left: 6px;">Archived</span>' : ''}
                                    </td>
                                    <td><span class="code-pill">${escapeHtml(s.enrollment_no)}</span></td>
                                    <td><a href="mailto:${escapeHtml(s.email)}" style="color: #3b82f6;">${escapeHtml(s.email)}</a></td>
                                    <td>${escapeHtml(s.course_name)}</td>
                                    <td style="text-align: center;">Sem ${s.semester}</td>
                                    <td>${getCgpaBadge(s.cgpa)}</td>
                                    <td style="text-align: right; white-space: nowrap;">${actionBtns}</td>
                                </tr>
                            `;
                        }).join('');
                    }

                    // Update Pagination
                    const pag = res.data.pagination;
                    document.getElementById('paginationInfo').textContent =
                        `Showing ${students.length > 0 ? (pag.page - 1) * pag.limit + 1 : 0} to ${Math.min(pag.page * pag.limit, pag.total)} of ${pag.total} students`;

                    renderPaginationControls(pag);
                })
                .catch(err => {
                    console.error(err);
                    showToast('Failed to load student records from server.', false);
                });
        }

        function renderPaginationControls(pag) {
            const container = document.getElementById('paginationControls');
            let html = '';

            html += `<button class="page-btn" ${!pag.has_prev ? 'disabled' : ''} onclick="fetchStudents(${pag.page - 1})">&larr; Prev</button>`;

            for (let i = 1; i <= pag.total_pages; i++) {
                if (i === 1 || i === pag.total_pages || (i >= pag.page - 1 && i <= pag.page + 1)) {
                    html += `<button class="page-btn ${i === pag.page ? 'active' : ''}" onclick="fetchStudents(${i})">${i}</button>`;
                } else if (i === pag.page - 2 || i === pag.page + 2) {
                    html += `<span style="padding: 6px 4px; color: var(--text-muted, #94a3b8);">...</span>`;
                }
            }

            html += `<button class="page-btn" ${!pag.has_next ? 'disabled' : ''} onclick="fetchStudents(${pag.page + 1})">Next &rarr;</button>`;
            container.innerHTML = html;
        }

        function handleCreateStudent(e) {
            e.preventDefault();
            const btn = document.getElementById('addSubmitBtn');
            btn.disabled = true;
            btn.textContent = 'Saving...';

            const form = document.getElementById('addStudentForm');
            const data = new FormData(form);

            fetch(API_URL, { method: 'POST', body: data })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        showToast(res.message, true);
                        closeModal('addModal');
                        form.reset();
                        fetchStudents(1);
                    } else {
                        showToast(res.message || (res.errors ? res.errors.join(', ') : 'Failed to create student'), false);
                    }
                })
                .catch(() => showToast('Network error creating student.', false))
                .finally(() => {
                    btn.disabled = false;
                    btn.textContent = 'Create Student Record';
                });
        }

        function openEditModal(id) {
            fetch(`${API_URL}?action=get&id=${id}`)
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        showToast(res.message || 'Error loading student', false);
                        return;
                    }
                    const s = res.data;
                    document.getElementById('editStudentId').value = s.student_id;
                    document.getElementById('editFullName').value = s.full_name;
                    document.getElementById('editEmail').value = s.email;
                    document.getElementById('editEnrollment').value = s.enrollment_no;
                    document.getElementById('editCourse').value = s.course_name;
                    document.getElementById('editSemester').value = s.semester;
                    document.getElementById('editCgpa').value = parseFloat(s.cgpa).toFixed(2);
                    openModal('editModal');
                })
                .catch(() => showToast('Failed to load student details.', false));
        }

        function handleUpdateStudent(e) {
            e.preventDefault();
            const btn = document.getElementById('editSubmitBtn');
            btn.disabled = true;
            btn.textContent = 'Updating...';

            const form = document.getElementById('editStudentForm');
            const data = new FormData(form);

            fetch(API_URL, { method: 'POST', body: data })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        showToast(res.message, true);
                        closeModal('editModal');
                        fetchStudents(currentPage);
                    } else {
                        showToast(res.message || (res.errors ? res.errors.join(', ') : 'Failed to update student'), false);
                    }
                })
                .catch(() => showToast('Network error updating student.', false))
                .finally(() => {
                    btn.disabled = false;
                    btn.textContent = 'Save Changes';
                });
        }

        function softDeleteStudent(id, name) {
            if (!confirm(`Are you sure you want to archive student #${id} (${name})?`)) return;

            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('student_id', id);
            fd.append('csrf_token', '<?= $csrfToken ?>');

            fetch(API_URL, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        showToast(res.message, true);
                        fetchStudents(currentPage);
                    } else {
                        showToast(res.message || 'Error deleting student', false);
                    }
                })
                .catch(() => showToast('Network error deleting student.', false));
        }

        function restoreStudent(id) {
            const fd = new FormData();
            fd.append('action', 'restore');
            fd.append('student_id', id);
            fd.append('csrf_token', '<?= $csrfToken ?>');

            fetch(API_URL, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        showToast(res.message, true);
                        fetchStudents(currentPage);
                    } else {
                        showToast(res.message, false);
                    }
                })
                .catch(() => showToast('Network error restoring student.', false));
        }

        function permanentDelete(id) {
            if (!confirm(`Permanently delete student #${id}? This cannot be undone.`)) return;

            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('permanent', '1');
            fd.append('student_id', id);
            fd.append('csrf_token', '<?= $csrfToken ?>');

            fetch(API_URL, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        showToast(res.message, true);
                        fetchStudents(currentPage);
                    } else {
                        showToast(res.message, false);
                    }
                })
                .catch(() => showToast('Network error deleting student.', false));
        }

        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        // Initial Load on page ready
        document.addEventListener('DOMContentLoaded', () => {
            fetchStudents(1);

            // Close modal when clicking on backdrop
            document.querySelectorAll('.modal-overlay').forEach(modal => {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        closeModal(modal.id);
                    }
                });
            });

            // Close modal on Escape key
            window.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    closeModal('addModal');
                    closeModal('editModal');
                }
            });
        });
    </script>
</body>
</html>
