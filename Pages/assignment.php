<?php
declare(strict_types=1);

/**
 * ============================================================================
 * CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY (CHARUSAT)
 * FACULTY OF TECHNOLOGY AND ENGINEERING (FTE)
 * SUBJECT: WEB DEVELOPMENT FRAMEWORKS (ITUE203)
 * Practical 13: RESTful PHP JSON API and AJAX/Fetch-Based CRUD
 * ============================================================================
 * File: assignment.php
 * Description: Fully asynchronous, RESTful AJAX-driven assignments module:
 *              - Zero whole-page reloads across all user interactions
 *              - REST API integration via native browser fetch() API
 *              - Live debounced keyword search and course multi-filtering
 *              - In-place DOM updates for Create, Update, Delete, and Submit
 *              - Dual-role experience (Faculty CRUD vs Student Submission)
 *              - 100% Light and Dark theme compatibility
 * ============================================================================
 */

require_once __DIR__ . '/../StudentHUB_php/require_login.php';
require_once __DIR__ . '/../StudentHUB_php/csrf.php';
require_once __DIR__ . '/../StudentHUB_php/db.php';

$currentUser = requireLogin();
$csrfToken = generateCsrfToken();
$isFaculty = in_array($currentUser['role'] ?? '', ['teacher', 'admin'], true);
$isAdmin = ($currentUser['role'] ?? '') === 'admin';

$db = getDatabaseConnection();

// Fetch active courses for modal dropdowns and filters
$coursesRes = $db->query("SELECT course_id, course_code, course_title FROM courses ORDER BY semester ASC, course_code ASC");
$coursesList = $coursesRes ? $coursesRes->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Assignments (Practical 13: REST API & AJAX Fetch)</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/assignment.css">
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
    <!-- Site Header -->
    <header class="site-header">
        <a class="brand" href="../index.html">
            <img src="../img/logo.png" alt="Student Hub Logo">
        </a>

        <!-- Centered Floating Pill Navigation -->
        <nav class="main-nav" aria-label="Assignment Navigation">
            <a href="dashboard.php">Dashboard</a>
            <a href="students.php">Students</a>
            <a href="courses.php">Courses</a>
            <a href="events.php">Campus Events</a>
            <?php if ($isFaculty): ?>
                <a href="faculty.php">Faculty Portal</a>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
                <a href="admin.php" style="color: #ef4444; font-weight: 700;">Admin Center</a>
            <?php endif; ?>
            <a href="assignment.php" class="active">Assignments</a>
            <a href="attendance.php">Attendance</a>
            <a href="result.php">Results</a>
            <a href="profile.php">Profile</a>
        </nav>

        <!-- Right Header Actions -->
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
                <span></span><span></span><span></span>
            </button>
        </div>

        <!-- Mobile Drawer Navigation -->
        <div class="mobile-nav-drawer" id="mobileNavDrawer">
            <nav class="mobile-nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="students.php">Students</a>
                <a href="courses.php">Courses</a>
                <a href="events.php">Campus Events</a>
                <?php if ($isFaculty): ?>
                    <a href="faculty.php">Faculty Portal</a>
                <?php endif; ?>
                <?php if ($isAdmin): ?>
                <a href="admin.php" style="color: #ef4444; font-weight: 700;">Admin Center</a>
            <?php endif; ?>
            <a href="assignment.php" class="active">Assignments</a>
                <a href="attendance.php">Attendance</a>
                <a href="result.php">Results</a>
                <a href="profile.php">Profile</a>
            </nav>
            <div class="mobile-drawer-actions">
                <a class="btn-logout" style="text-align: center; justify-content: center;" href="../StudentHUB_php/logout.php">Logout</a>
            </div>
        </div>
    </header>

    <main class="assignment-container">
        <!-- Page Heading & Actions -->
        <section class="page-header-row">
            <div>
                <p style="text-transform: uppercase; font-size: 12px; letter-spacing: 0.1em; color: var(--brand-primary); font-weight: 700; margin: 0 0 6px;">
                    Practical 13: RESTful PHP JSON API & Asynchronous Fetch CRUD
                </p>
                <h1 style="margin: 0; font-size: 28px; color: var(--text-primary);">Course Assignments & Tasks</h1>
                <p style="margin: 6px 0 0; color: var(--text-muted); font-size: 14px;">
                    Track deadlines, submit assignments, and manage coursework asynchronously without page reloads.
                </p>
            </div>
            <?php if ($isFaculty): ?>
                <div>
                    <button class="btn btn-primary" onclick="openAddAssignmentModal()">➕ Create Assignment</button>
                </div>
            <?php endif; ?>
        </section>

        <!-- KPI Metric Summary Tiles -->
        <section class="asgn-kpi-grid">
            <div class="asgn-kpi-card">
                <div class="asgn-kpi-label">Total Assignments</div>
                <div class="asgn-kpi-value" id="kpiTotalAsgn">0</div>
            </div>
            <div class="asgn-kpi-card">
                <div class="asgn-kpi-label">Active / Due</div>
                <div class="asgn-kpi-value" id="kpiActiveAsgn" style="color: #10b981;">0</div>
            </div>
            <div class="asgn-kpi-card">
                <div class="asgn-kpi-label">Total Submissions</div>
                <div class="asgn-kpi-value" id="kpiTotalSubs" style="color: #3b82f6;">0</div>
            </div>
            <div class="asgn-kpi-card">
                <div class="asgn-kpi-label">API Status</div>
                <div class="asgn-kpi-value" style="color: #fbbf24; font-size: 20px;">REST JSON (200 OK)</div>
            </div>
        </section>

        <!-- Search & Filter Toolbar -->
        <section class="asgn-filter-panel">
            <div class="asgn-filter-grid">
                <div>
                    <input type="text" id="asgnSearchInput" class="input-control" placeholder="🔍 Search assignments by title, course, or topic..." oninput="debounceFetchAssignments()">
                </div>
                <div>
                    <select id="asgnCourseFilter" class="select-control" onchange="fetchAssignments(1)">
                        <option value="">All Courses</option>
                        <?php foreach ($coursesList as $c): ?>
                            <option value="<?= (int)$c['course_id'] ?>">
                                <?= htmlspecialchars($c['course_code'] . ' - ' . $c['course_title'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <select id="asgnStatusFilter" class="select-control" onchange="fetchAssignments(1)">
                        <option value="all">All Statuses</option>
                        <option value="active">Active Only</option>
                        <option value="closed">Closed</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>
                <div>
                    <button class="btn btn-secondary" onclick="resetAssignmentFilters()" style="padding: 10px 16px; font-size: 13px;">Reset</button>
                </div>
            </div>
        </section>

        <!-- Dynamic Assignments Table -->
        <section class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Assignment Title</th>
                        <th>Due Date</th>
                        <th>Max Marks</th>
                        <th><?= $isFaculty ? 'Submissions' : 'Your Status' ?></th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody id="assignmentsTableBody">
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                            Loading assignments via REST API...
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- Pagination Controls -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; color: var(--text-muted); font-size: 13px; margin-bottom: 40px;">
            <span id="asgnPaginationInfo">Showing 0 assignments</span>
            <div style="display: flex; gap: 6px;" id="asgnPaginationButtons"></div>
        </div>
    </main>

    <!-- ====================================================================== -->
    <!-- MODAL 1: ADD / EDIT ASSIGNMENT (FACULTY & ADMIN) -->
    <!-- ====================================================================== -->
    <div class="modal-overlay" id="assignmentModal">
        <div class="modal-card">
            <div class="modal-header">
                <h2 id="asgnModalTitle">Create Course Assignment</h2>
                <button class="modal-close" onclick="closeAssignmentModal()">&times;</button>
            </div>
            <form id="assignmentForm" onsubmit="handleAssignmentSubmit(event)">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="assignment_id" id="asgnFormId" value="">
                <input type="hidden" name="_method" id="asgnFormMethod" value="POST">

                <div class="form-group">
                    <label class="form-label" for="asgnCourseSelect">Academic Course *</label>
                    <select id="asgnCourseSelect" name="course_id" class="select-control" required>
                        <option value="">Select Course...</option>
                        <?php foreach ($coursesList as $c): ?>
                            <option value="<?= (int)$c['course_id'] ?>">
                                <?= htmlspecialchars($c['course_code'] . ' - ' . $c['course_title'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="asgnTitleInput">Assignment Title *</label>
                    <input type="text" id="asgnTitleInput" name="title" class="input-control" placeholder="e.g. Responsive StudentHub Redesign" required maxlength="150">
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label" for="asgnDueDateInput">Due Date & Time *</label>
                        <input type="datetime-local" id="asgnDueDateInput" name="due_date" class="input-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="asgnMaxMarksInput">Max Marks *</label>
                        <input type="number" id="asgnMaxMarksInput" name="max_marks" class="input-control" min="1" max="1000" value="50" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="asgnStatusInput">Status *</label>
                    <select id="asgnStatusInput" name="status" class="select-control" required>
                        <option value="active">Active (Accepting Submissions)</option>
                        <option value="closed">Closed</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="asgnDescInput">Instructions / Problem Statement</label>
                    <textarea id="asgnDescInput" name="description" class="input-control" rows="3" style="resize: vertical;" placeholder="Assignment specifications, deliverables, and submission guidelines..."></textarea>
                </div>

                <div id="asgnModalErrors" style="color: #ef4444; font-size: 13px; margin-bottom: 14px; display: none;"></div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; padding-top: 14px; border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" onclick="closeAssignmentModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="asgnSubmitBtn">Save Assignment</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ====================================================================== -->
    <!-- MODAL 2: STUDENT SUBMISSION MODAL -->
    <!-- ====================================================================== -->
    <div class="modal-overlay" id="submissionModal">
        <div class="modal-card">
            <div class="modal-header">
                <h2>Submit Assignment Work</h2>
                <button class="modal-close" onclick="closeSubmissionModal()">&times;</button>
            </div>
            <form id="submissionForm" onsubmit="handleStudentWorkSubmit(event)">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="submit">
                <input type="hidden" name="assignment_id" id="subAssignmentId" value="">

                <div style="background: var(--bg-subtle); padding: 14px; border-radius: 8px; margin-bottom: 16px; border: 1px solid var(--border-color);">
                    <strong id="subModalAsgnTitle" style="display: block; font-size: 16px; margin-bottom: 4px; color: var(--text-primary);"></strong>
                    <span id="subModalCourseTitle" style="font-size: 13px; color: var(--text-secondary);"></span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="subTextInput">Submission Text / Solution Link *</label>
                    <textarea id="subTextInput" name="submission_text" class="input-control" rows="4" style="resize: vertical;" placeholder="Provide GitHub repository URL, deployment link, or solution summary (min 5 characters)..." required></textarea>
                </div>

                <div id="subModalErrors" style="color: #ef4444; font-size: 13px; margin-bottom: 14px; display: none;"></div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; padding-top: 14px; border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" onclick="closeSubmissionModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="subBtnSubmit">Submit Solution &rarr;</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Floating Feedback Toast -->
    <div id="asgnToast" class="toast-popup"></div>

    <footer class="site-footer">
        <p>&copy; 2026 Student Hub - CHARUSAT. All rights reserved.</p>
    </footer>

    <script src="../js/script.js"></script>
    <script>
        const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
        const IS_FACULTY = <?= json_encode($isFaculty) ?>;
        let cachedAssignments = [];
        let asgnDebounceTimer = null;
        let currentAsgnPage = 1;

        function showToast(msg, isSuccess = true) {
            const toast = document.getElementById('asgnToast');
            toast.textContent = msg;
            toast.className = 'toast-popup ' + (isSuccess ? 'toast-success' : 'toast-error');
            toast.style.display = 'block';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3500);
        }

        function debounceFetchAssignments() {
            clearTimeout(asgnDebounceTimer);
            asgnDebounceTimer = setTimeout(() => fetchAssignments(1), 300);
        }

        function resetAssignmentFilters() {
            document.getElementById('asgnSearchInput').value = '';
            document.getElementById('asgnCourseFilter').value = '';
            document.getElementById('asgnStatusFilter').value = 'all';
            fetchAssignments(1);
        }

        async function fetchAssignments(page = 1) {
            currentAsgnPage = page;
            const search = document.getElementById('asgnSearchInput').value.trim();
            const courseId = document.getElementById('asgnCourseFilter').value;
            const status = document.getElementById('asgnStatusFilter').value;

            let url = `../StudentHUB_php/api_assignments.php?page=${page}&limit=8&search=${encodeURIComponent(search)}`;
            if (courseId) url += `&course_id=${encodeURIComponent(courseId)}`;
            if (status !== 'all') url += `&status=${encodeURIComponent(status)}`;

            try {
                const res = await fetch(url, {
                    headers: { 'Accept': 'application/json' }
                });
                const result = await res.json();
                if (result.success && result.data) {
                    cachedAssignments = result.data.assignments || [];
                    renderAssignments(cachedAssignments);
                    renderPagination(result.data.pagination);
                    if (result.data.kpi) {
                        document.getElementById('kpiTotalAsgn').textContent = result.data.kpi.total_assignments || 0;
                        document.getElementById('kpiActiveAsgn').textContent = result.data.kpi.active_assignments || 0;
                        document.getElementById('kpiTotalSubs').textContent = result.data.kpi.total_submissions || 0;
                    }
                } else {
                    renderAssignments([]);
                }
            } catch (err) {
                console.error('Fetch error:', err);
                document.getElementById('assignmentsTableBody').innerHTML = `
                    <tr><td colspan="6" style="text-align: center; color: #ef4444; padding: 30px;">⚠️ Failed to load assignments via REST API.</td></tr>
                `;
            }
        }

        function renderAssignments(list) {
            const tbody = document.getElementById('assignmentsTableBody');
            if (!list.length) {
                tbody.innerHTML = `
                    <tr><td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">No assignments found matching the filter criteria.</td></tr>
                `;
                return;
            }

            tbody.innerHTML = list.map(asgn => {
                const due = new Date(asgn.due_date).toLocaleDateString('en-US', {
                    month: 'short', day: 'numeric', year: 'numeric'
                });

                let statusBadge = '';
                if (IS_FACULTY) {
                    const count = parseInt(asgn.submissions_count) || 0;
                    statusBadge = `<span class="badge ${count > 0 ? 'badge-submitted' : 'badge-pending'}">${count} Submissions</span>`;
                } else {
                    const st = asgn.student_status || 'Pending';
                    const badgeClass = st === 'Graded' ? 'badge-graded' : (st === 'Submitted' ? 'badge-submitted' : 'badge-pending');
                    statusBadge = `<span class="badge ${badgeClass}">${st}</span>`;
                }

                return `
                    <tr id="asgn-row-${asgn.assignment_id}">
                        <td><span class="badge-course">${escapeHtml(asgn.course_code)}</span></td>
                        <td>
                            <strong>${escapeHtml(asgn.title)}</strong>
                            <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">${escapeHtml(asgn.course_title)}</div>
                        </td>
                        <td>${due}</td>
                        <td><strong>${asgn.max_marks}</strong> pts</td>
                        <td>${statusBadge}</td>
                        <td style="text-align: right; white-space: nowrap;">
                            ${IS_FACULTY ? `
                                <button class="btn-action btn-edit" onclick="openEditAssignmentModal(${asgn.assignment_id})">✏️ Edit</button>
                                <button class="btn-action btn-delete" onclick="handleDeleteAssignment(${asgn.assignment_id}, '${escapeHtml(asgn.title)}')">🗑️ Delete</button>
                            ` : `
                                <button class="btn-action btn-submit" onclick="openSubmitModal(${asgn.assignment_id}, '${escapeHtml(asgn.title)}', '${escapeHtml(asgn.course_title)}')">
                                    ${asgn.student_status === 'Submitted' || asgn.student_status === 'Graded' ? 'View / Resubmit' : 'Submit &rarr;'}
                                </button>
                            `}
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function renderPagination(p) {
            if (!p) return;
            const info = document.getElementById('asgnPaginationInfo');
            const btns = document.getElementById('asgnPaginationButtons');

            const start = p.total_records === 0 ? 0 : (p.current_page - 1) * p.limit + 1;
            const end = Math.min(p.current_page * p.limit, p.total_records);
            info.textContent = `Showing ${start} to ${end} of ${p.total_records} assignments`;

            let html = '';
            if (p.current_page > 1) {
                html += `<button class="btn btn-secondary" style="padding: 4px 10px; font-size: 12px;" onclick="fetchAssignments(${p.current_page - 1})">&laquo; Prev</button>`;
            }
            for (let i = 1; i <= p.total_pages; i++) {
                if (i === 1 || i === p.total_pages || (i >= p.current_page - 1 && i <= p.current_page + 1)) {
                    const activeStyle = i === p.current_page ? 'background: var(--brand-primary); color: white;' : '';
                    html += `<button class="btn btn-secondary" style="padding: 4px 10px; font-size: 12px; ${activeStyle}" onclick="fetchAssignments(${i})">${i}</button>`;
                } else if (i === p.current_page - 2 || i === p.current_page + 2) {
                    html += `<span style="padding: 4px 6px;">...</span>`;
                }
            }
            if (p.current_page < p.total_pages) {
                html += `<button class="btn btn-secondary" style="padding: 4px 10px; font-size: 12px;" onclick="fetchAssignments(${p.current_page + 1})">Next &raquo;</button>`;
            }
            btns.innerHTML = html;
        }

        // ====================================================================
        // ASYNC CRUD MODAL HANDLERS (ZERO PAGE RELOAD)
        // ====================================================================
        function openAddAssignmentModal() {
            document.getElementById('asgnModalTitle').textContent = 'Create Course Assignment';
            document.getElementById('asgnFormMethod').value = 'POST';
            document.getElementById('asgnFormId').value = '';
            document.getElementById('asgnCourseSelect').value = '';
            document.getElementById('asgnTitleInput').value = '';
            document.getElementById('asgnMaxMarksInput').value = 50;
            document.getElementById('asgnStatusInput').value = 'active';
            document.getElementById('asgnDescInput').value = '';
            document.getElementById('asgnModalErrors').style.display = 'none';

            // Due date: 7 days from now
            const nextWeek = new Date();
            nextWeek.setDate(nextWeek.getDate() + 7);
            nextWeek.setHours(23, 59, 0, 0);
            document.getElementById('asgnDueDateInput').value = nextWeek.toISOString().slice(0, 16);

            document.getElementById('asgnSubmitBtn').textContent = 'Create Assignment';
            document.getElementById('assignmentModal').classList.add('open');
        }

        function openEditAssignmentModal(id) {
            const asgn = cachedAssignments.find(a => Number(a.assignment_id) === Number(id));
            if (!asgn) return;

            document.getElementById('asgnModalTitle').textContent = `Edit Assignment: ${asgn.title}`;
            document.getElementById('asgnFormMethod').value = 'PUT';
            document.getElementById('asgnFormId').value = asgn.assignment_id;
            document.getElementById('asgnCourseSelect').value = asgn.course_id;
            document.getElementById('asgnTitleInput').value = asgn.title;
            document.getElementById('asgnMaxMarksInput').value = asgn.max_marks;
            document.getElementById('asgnStatusInput').value = asgn.status;
            document.getElementById('asgnDescInput').value = asgn.description || '';
            document.getElementById('asgnModalErrors').style.display = 'none';

            const d = new Date(asgn.due_date);
            document.getElementById('asgnDueDateInput').value = d.toISOString().slice(0, 16);

            document.getElementById('asgnSubmitBtn').textContent = 'Update Assignment';
            document.getElementById('assignmentModal').classList.add('open');
        }

        function closeAssignmentModal() {
            document.getElementById('assignmentModal').classList.remove('open');
        }

        async function handleAssignmentSubmit(e) {
            e.preventDefault();
            const btn = document.getElementById('asgnSubmitBtn');
            const errBox = document.getElementById('asgnModalErrors');
            errBox.style.display = 'none';
            btn.disabled = true;

            const form = document.getElementById('assignmentForm');
            const formData = new FormData(form);

            try {
                const res = await fetch('../StudentHUB_php/api_assignments.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                });
                const result = await res.json();
                if (result.success) {
                    showToast(result.message, true);
                    closeAssignmentModal();
                    fetchAssignments(currentAsgnPage);
                } else {
                    const errs = result.errors && result.errors.length ? result.errors.join('<br>') : result.message;
                    errBox.innerHTML = errs;
                    errBox.style.display = 'block';
                }
            } catch (err) {
                errBox.textContent = 'REST API network error. Please try again.';
                errBox.style.display = 'block';
            } finally {
                btn.disabled = false;
            }
        }

        async function handleDeleteAssignment(id, title) {
            if (!confirm(`Are you sure you want to delete assignment "${title}"? This cannot be undone.`)) {
                return;
            }

            const formData = new FormData();
            formData.append('_method', 'DELETE');
            formData.append('assignment_id', id);
            formData.append('csrf_token', CSRF_TOKEN);

            try {
                const res = await fetch('../StudentHUB_php/api_assignments.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                });
                const result = await res.json();
                if (result.success) {
                    showToast(result.message, true);
                    fetchAssignments(currentAsgnPage);
                } else {
                    showToast(result.message || 'Failed to delete assignment.', false);
                }
            } catch (err) {
                showToast('Network error while deleting assignment.', false);
            }
        }

        // ====================================================================
        // STUDENT SUBMISSION (ZERO PAGE RELOAD)
        // ====================================================================
        function openSubmitModal(id, title, courseTitle) {
            document.getElementById('subAssignmentId').value = id;
            document.getElementById('subModalAsgnTitle').textContent = title;
            document.getElementById('subModalCourseTitle').textContent = courseTitle;
            document.getElementById('subTextInput').value = '';
            document.getElementById('subModalErrors').style.display = 'none';
            document.getElementById('submissionModal').classList.add('open');
        }

        function closeSubmissionModal() {
            document.getElementById('submissionModal').classList.remove('open');
        }

        async function handleStudentWorkSubmit(e) {
            e.preventDefault();
            const btn = document.getElementById('subBtnSubmit');
            const errBox = document.getElementById('subModalErrors');
            errBox.style.display = 'none';
            btn.disabled = true;

            const form = document.getElementById('submissionForm');
            const formData = new FormData(form);

            try {
                const res = await fetch('../StudentHUB_php/api_assignments.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                });
                const result = await res.json();
                if (result.success) {
                    showToast(result.message, true);
                    closeSubmissionModal();
                    fetchAssignments(currentAsgnPage);
                } else {
                    const errs = result.errors && result.errors.length ? result.errors.join('<br>') : result.message;
                    errBox.innerHTML = errs;
                    errBox.style.display = 'block';
                }
            } catch (err) {
                errBox.textContent = 'REST API network error during submission.';
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

        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeAssignmentModal();
                closeSubmissionModal();
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            fetchAssignments(1);
        });
    </script>
</body>
</html>
