<?php
declare(strict_types=1);

/**
 * ============================================================================
 * CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY (CHARUSAT)
 * FACULTY OF TECHNOLOGY AND ENGINEERING (FTE)
 * SUBJECT: WEB DEVELOPMENT FRAMEWORKS (ITUE203)
 * Practical 14: Admin Dashboard with System Analytics & Audit Log History
 * ============================================================================
 * File: admin.php
 * Description: Dedicated Administrator Command Center:
 *              - Strictly guarded with requireRole('admin') (HTTP 403 for others)
 *              - Real-time system analytics and health KPI telemetry
 *              - User account directory & RBAC role reassignment
 *              - Searchable security audit trail with severity filtering
 *              - Retention governance & automated audit purge
 *              - 100% Light and Dark theme compatibility
 * ============================================================================
 */

require_once __DIR__ . '/../StudentHUB_php/auth.php';
require_once __DIR__ . '/../StudentHUB_php/csrf.php';
require_once __DIR__ . '/../StudentHUB_php/db.php';
require_once __DIR__ . '/../StudentHUB_php/audit.php';

// Strict Role Guard: Only 'admin' role may enter
$currentUser = requireRole('admin');
$csrfToken = generateCsrfToken();

// Initial server-side analytics
$analytics = getSystemAnalytics();
$users = $analytics['users'] ?? [];
$students = $analytics['students'] ?? [];
$content = $analytics['content'] ?? [];
$system = $analytics['system'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Admin Command Center (Practical 14)</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
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
        <nav class="main-nav" aria-label="Admin Navigation">
            <a href="dashboard.php">Dashboard</a>
            <a href="admin.php" class="active" style="color: #ef4444; font-weight: 700;">Admin Center</a>
            <a href="deployment.php" style="color: #2563eb; font-weight: 600;">Diagnostics & Deploy (P15)</a>
            <a href="students.php">Students</a>
            <a href="courses.php">Courses</a>
            <a href="faculty.php">Faculty Portal</a>
            <a href="events.php">Events</a>
            <a href="assignment.php">Assignments</a>
            <a href="attendance.php">Attendance</a>
            <a href="result.php">Results</a>
            <a href="profile.php">Profile</a>
        </nav>

        <!-- Right Header Actions -->
        <div class="header-right">
            <div class="header-actions">
                <span style="font-size: 13px; color: var(--text-muted); margin-right: 8px;">
                    🛡️ <?= htmlspecialchars($currentUser['name'] ?? 'Admin Officer', ENT_QUOTES, 'UTF-8') ?>
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
                <a href="admin.php" class="active">Admin Center</a>
                <a href="deployment.php" style="color: #2563eb; font-weight: 600;">Diagnostics & Deploy (P15)</a>
                <a href="students.php">Students</a>
                <a href="courses.php">Courses</a>
                <a href="faculty.php">Faculty Portal</a>
                <a href="events.php">Events</a>
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

    <main class="admin-container">
        <!-- Admin Profile Banner -->
        <section class="admin-hero">
            <div class="admin-profile-info">
                <div class="admin-avatar">ADM</div>
                <div class="admin-meta">
                    <h1>System Administrator Command Center</h1>
                    <div class="admin-subtext">
                        <span class="role-admin-badge">Super Admin</span>
                        <span>📧 <?= htmlspecialchars($currentUser['email'] ?? 'admin@charusat.edu.in', ENT_QUOTES, 'UTF-8') ?></span>
                        <span>🖥️ PHP <?= htmlspecialchars($system['php_version'] ?? PHP_VERSION, ENT_QUOTES, 'UTF-8') ?></span>
                        <span>🗄️ MySQL <?= htmlspecialchars(substr($system['db_version'] ?? '', 0, 15), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="deployment.php" class="btn btn-primary" style="background: #2563eb; color: #fff; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                    🚀 Diagnostics & Deploy (P15)
                </a>
                <button class="btn btn-secondary" onclick="refreshAnalytics()">🔄 Refresh Telemetry</button>
            </div>
        </section>

        <!-- KPI Metrics Grid -->
        <section class="admin-kpis">
            <div class="admin-kpi-card">
                <div class="admin-kpi-label">Registered Accounts</div>
                <div class="admin-kpi-value" id="kpiTotalUsers"><?= (int)($users['total_users'] ?? 0) ?></div>
            </div>
            <div class="admin-kpi-card">
                <div class="admin-kpi-label">Active Students</div>
                <div class="admin-kpi-value" id="kpiActiveStudents" style="color: #10b981;"><?= (int)($students['total_students'] ?? 0) ?></div>
            </div>
            <div class="admin-kpi-card">
                <div class="admin-kpi-label">Faculty Accounts</div>
                <div class="admin-kpi-value" id="kpiTeachers" style="color: #3b82f6;"><?= (int)($users['teacher_count'] ?? 0) ?></div>
            </div>
            <div class="admin-kpi-card">
                <div class="admin-kpi-label">Campus Events</div>
                <div class="admin-kpi-value" id="kpiEvents" style="color: #a855f7;"><?= (int)($content['total_events'] ?? 0) ?></div>
            </div>
            <div class="admin-kpi-card">
                <div class="admin-kpi-label">Database Storage</div>
                <div class="admin-kpi-value" id="kpiDbSize" style="color: #fbbf24;"><?= number_format((float)($system['db_size_mb'] ?? 0.0), 2) ?> MB</div>
            </div>
        </section>

        <!-- Admin Module Tabs -->
        <nav class="admin-tabs" aria-label="Admin Modules">
            <button class="admin-tab-btn active" id="tabBtnAnalytics" onclick="switchAdminTab('analytics')">
                📊 System Telemetry & Health
            </button>
            <button class="admin-tab-btn" id="tabBtnUsers" onclick="switchAdminTab('users')">
                👥 User Management & RBAC
            </button>
            <button class="admin-tab-btn" id="tabBtnAudit" onclick="switchAdminTab('audit')">
                📜 Security Audit Trail
            </button>
        </nav>

        <!-- ================================================================== -->
        <!-- TAB 1: SYSTEM TELEMETRY & MODULE HEALTH -->
        <!-- ================================================================== -->
        <section id="tabContentAnalytics" class="admin-tab-content active">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 24px;">
                <!-- User Breakdown Card -->
                <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 22px; box-shadow: var(--shadow-sm);">
                    <h3 style="margin: 0 0 14px; font-size: 16px; color: var(--text-primary);">👤 User Distribution by Role</h3>
                    <div style="display: flex; flex-direction: column; gap: 10px; font-size: 14px;">
                        <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--border-color);">
                            <span>Students</span>
                            <strong><span class="badge-role-student"><?= (int)($users['student_count'] ?? 0) ?></span></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--border-color);">
                            <span>Faculty / Teachers</span>
                            <strong><span class="badge-role-teacher"><?= (int)($users['teacher_count'] ?? 0) ?></span></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--border-color);">
                            <span>System Administrators</span>
                            <strong><span class="badge-role-admin"><?= (int)($users['admin_count'] ?? 0) ?></span></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 6px 0;">
                            <span>Active in Last 7 Days</span>
                            <strong style="color: #10b981;"><?= (int)($users['active_7days'] ?? 0) ?> users</strong>
                        </div>
                    </div>
                </div>

                <!-- Database Records Card -->
                <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 22px; box-shadow: var(--shadow-sm);">
                    <h3 style="margin: 0 0 14px; font-size: 16px; color: var(--text-primary);">🗄️ Database Tables Telemetry</h3>
                    <div style="display: flex; flex-direction: column; gap: 10px; font-size: 14px;">
                        <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--border-color);">
                            <span>Academic Courses</span>
                            <strong><?= (int)($content['total_courses'] ?? 0) ?> courses</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--border-color);">
                            <span>Campus Events</span>
                            <strong><?= (int)($content['total_events'] ?? 0) ?> events</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--border-color);">
                            <span>Assignments Published</span>
                            <strong><?= (int)($content['total_assignments'] ?? 0) ?> assignments</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 6px 0;">
                            <span>Audit Log Records</span>
                            <strong><?= (int)($content['total_logs'] ?? 0) ?> entries</strong>
                        </div>
                    </div>
                </div>

                <!-- Security & Audit Severity Card -->
                <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 22px; box-shadow: var(--shadow-sm);">
                    <h3 style="margin: 0 0 14px; font-size: 16px; color: var(--text-primary);">🛡️ Security Log Telemetry</h3>
                    <div style="display: flex; flex-direction: column; gap: 10px; font-size: 14px;">
                        <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--border-color);">
                            <span>Info Events</span>
                            <span class="badge-sev-info"><?= (int)($analytics['audit']['info_logs'] ?? 0) ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--border-color);">
                            <span>Warnings</span>
                            <span class="badge-sev-warning"><?= (int)($analytics['audit']['warning_logs'] ?? 0) ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--border-color);">
                            <span>Danger / Auth Failures</span>
                            <span class="badge-sev-danger"><?= (int)($analytics['audit']['danger_logs'] ?? 0) ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 6px 0;">
                            <span>System Status</span>
                            <span style="color: #10b981; font-weight: 700;">🟢 Healthy (Operational)</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================================================================== -->
        <!-- TAB 2: USER MANAGEMENT & RBAC ROLE ASSIGNMENT -->
        <!-- ================================================================== -->
        <section id="tabContentUsers" class="admin-tab-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2 style="margin: 0; font-size: 20px; color: var(--text-primary);">User Accounts & RBAC Roles</h2>
                    <p style="margin: 4px 0 0; font-size: 13px; color: var(--text-muted);">Manage registered user credentials, assign roles, and audit access permissions.</p>
                </div>
            </div>

            <div class="admin-filter-bar">
                <div style="flex: 1; min-width: 240px;">
                    <input type="text" id="userSearchInput" class="input-admin" style="width: 100%;" placeholder="🔍 Search users by name or email..." oninput="debounceFetchUsers()">
                </div>
                <div>
                    <select id="userRoleFilter" class="select-admin" onchange="fetchUsers(1)">
                        <option value="all">All Roles</option>
                        <option value="student">Students</option>
                        <option value="teacher">Teachers / Faculty</option>
                        <option value="admin">Administrators</option>
                    </select>
                </div>
                <div>
                    <button class="btn btn-secondary" onclick="resetUserFilters()" style="padding: 9px 14px; font-size: 13px;">Reset</button>
                </div>
            </div>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Full Name</th>
                            <th>Email Address</th>
                            <th>Role</th>
                            <th>Last Active</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <tr><td colspan="6" style="text-align: center; padding: 30px; color: var(--text-muted);">Loading user records...</td></tr>
                    </tbody>
                </table>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; color: var(--text-muted); font-size: 13px; margin-bottom: 40px;">
                <span id="userPaginationInfo">Showing 0 users</span>
                <div style="display: flex; gap: 6px;" id="userPaginationButtons"></div>
            </div>
        </section>

        <!-- ================================================================== -->
        <!-- TAB 3: SECURITY AUDIT TRAIL -->
        <!-- ================================================================== -->
        <section id="tabContentAudit" class="admin-tab-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2 style="margin: 0; font-size: 20px; color: var(--text-primary);">Security & System Audit Trail</h2>
                    <p style="margin: 4px 0 0; font-size: 13px; color: var(--text-muted);">Immutable audit logs capturing user authentication, role changes, and administrative actions.</p>
                </div>
                <div>
                    <button class="btn btn-secondary" onclick="openPurgeModal()" style="font-size: 13px; color: #ef4444; border-color: rgba(239, 68, 68, 0.4);">🗑️ Purge Old Logs</button>
                </div>
            </div>

            <div class="admin-filter-bar">
                <div style="flex: 1; min-width: 240px;">
                    <input type="text" id="auditSearchInput" class="input-admin" style="width: 100%;" placeholder="🔍 Search audit logs by action, IP, or details..." oninput="debounceFetchAudit()">
                </div>
                <div>
                    <select id="auditSeverityFilter" class="select-admin" onchange="fetchAuditLogs(1)">
                        <option value="all">All Severities</option>
                        <option value="info">Info</option>
                        <option value="warning">Warning</option>
                        <option value="danger">Danger</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
                <div>
                    <button class="btn btn-secondary" onclick="resetAuditFilters()" style="padding: 9px 14px; font-size: 13px;">Reset</button>
                </div>
            </div>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Log ID</th>
                            <th>Timestamp</th>
                            <th>Action</th>
                            <th>Severity</th>
                            <th>User</th>
                            <th>IP Address</th>
                            <th>Context Details</th>
                        </tr>
                    </thead>
                    <tbody id="auditTableBody">
                        <tr><td colspan="7" style="text-align: center; padding: 30px; color: var(--text-muted);">Loading audit logs...</td></tr>
                    </tbody>
                </table>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; color: var(--text-muted); font-size: 13px; margin-bottom: 40px;">
                <span id="auditPaginationInfo">Showing 0 logs</span>
                <div style="display: flex; gap: 6px;" id="auditPaginationButtons"></div>
            </div>
        </section>
    </main>

    <!-- ====================================================================== -->
    <!-- MODAL 1: CHANGE USER ROLE (RBAC) -->
    <!-- ====================================================================== -->
    <div class="admin-modal-overlay" id="roleModal">
        <div class="admin-modal-card">
            <h2 style="margin: 0 0 16px; font-size: 18px; color: var(--text-primary);">Update User Role (RBAC)</h2>
            <form id="roleForm" onsubmit="handleRoleSubmit(event)">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="update_role">
                <input type="hidden" name="user_id" id="roleUserId" value="">

                <div style="background: var(--bg-subtle); padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; border: 1px solid var(--border-color);">
                    <div id="roleModalUserName" style="font-weight: 700; color: var(--text-primary);"></div>
                    <div id="roleModalUserEmail" style="font-size: 13px; color: var(--text-muted); margin-top: 2px;"></div>
                </div>

                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">
                        Select New Permission Role *
                    </label>
                    <select id="roleSelect" name="new_role" class="select-admin" style="width: 100%;" required>
                        <option value="student">Student (Standard Portal Access)</option>
                        <option value="teacher">Teacher / Faculty (Course & Grade Editor)</option>
                        <option value="admin">Administrator (Super Admin Control)</option>
                    </select>
                </div>

                <div id="roleModalErrors" style="color: #ef4444; font-size: 13px; margin-bottom: 14px; display: none;"></div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; padding-top: 14px; border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" onclick="closeRoleModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="roleSubmitBtn">Save Role Change</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ====================================================================== -->
    <!-- MODAL 2: PURGE AUDIT LOGS RETENTION -->
    <!-- ====================================================================== -->
    <div class="admin-modal-overlay" id="purgeModal">
        <div class="admin-modal-card">
            <h2 style="margin: 0 0 12px; font-size: 18px; color: #ef4444;">Purge Audit Trail History</h2>
            <p style="font-size: 13px; color: var(--text-muted); line-height: 1.5; margin: 0 0 16px;">
                Permanently prune old audit logs under university retention policies. This action is irreversible.
            </p>
            <form id="purgeForm" onsubmit="handlePurgeSubmit(event)">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="clear_audit_logs">

                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">
                        Retention Window
                    </label>
                    <select id="purgeDays" name="days" class="select-admin" style="width: 100%;">
                        <option value="30">Delete logs older than 30 days</option>
                        <option value="60">Delete logs older than 60 days</option>
                        <option value="90">Delete logs older than 90 days</option>
                    </select>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; padding-top: 14px; border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" onclick="closePurgeModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger" style="background: #ef4444; color: white;" id="purgeSubmitBtn">Execute Purge</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Floating Feedback Toast -->
    <div id="adminToast" class="admin-toast"></div>

    <footer class="site-footer">
        <p>&copy; 2026 Student Hub - CHARUSAT. All rights reserved.</p>
    </footer>

    <script src="../js/script.js"></script>
    <script>
        const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
        let cachedUsers = [];
        let userDebounceTimer = null;
        let auditDebounceTimer = null;
        let currentUserPage = 1;
        let currentAuditPage = 1;

        function showToast(msg, isSuccess = true) {
            const toast = document.getElementById('adminToast');
            toast.textContent = msg;
            toast.className = 'admin-toast ' + (isSuccess ? 'toast-success' : 'toast-error');
            toast.style.display = 'block';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3500);
        }

        function switchAdminTab(tab) {
            const tabs = ['analytics', 'users', 'audit'];
            tabs.forEach(t => {
                const btn = document.getElementById('tabBtn' + t.charAt(0).toUpperCase() + t.slice(1));
                const content = document.getElementById('tabContent' + t.charAt(0).toUpperCase() + t.slice(1));
                if (t === tab) {
                    btn.classList.add('active');
                    content.classList.add('active');
                } else {
                    btn.classList.remove('active');
                    content.classList.remove('active');
                }
            });

            if (tab === 'users') fetchUsers(1);
            if (tab === 'audit') fetchAuditLogs(1);
        }

        async function refreshAnalytics() {
            try {
                const res = await fetch('../StudentHUB_php/admin_crud.php?action=analytics');
                const result = await res.json();
                if (result.success && result.data) {
                    const d = result.data;
                    document.getElementById('kpiTotalUsers').textContent = d.users.total_users || 0;
                    document.getElementById('kpiActiveStudents').textContent = d.students.total_students || 0;
                    document.getElementById('kpiTeachers').textContent = d.users.teacher_count || 0;
                    document.getElementById('kpiEvents').textContent = d.content.total_events || 0;
                    document.getElementById('kpiDbSize').textContent = Number(d.system.db_size_mb || 0).toFixed(2) + ' MB';
                    showToast('System telemetry refreshed.', true);
                }
            } catch (e) {
                showToast('Failed to refresh telemetry.', false);
            }
        }

        // ====================================================================
        // USERS & RBAC MODULE
        // ====================================================================
        function debounceFetchUsers() {
            clearTimeout(userDebounceTimer);
            userDebounceTimer = setTimeout(() => fetchUsers(1), 300);
        }

        function resetUserFilters() {
            document.getElementById('userSearchInput').value = '';
            document.getElementById('userRoleFilter').value = 'all';
            fetchUsers(1);
        }

        async function fetchUsers(page = 1) {
            currentUserPage = page;
            const search = document.getElementById('userSearchInput').value.trim();
            const role = document.getElementById('userRoleFilter').value;

            let url = `../StudentHUB_php/admin_crud.php?action=list_users&page=${page}&limit=10&search=${encodeURIComponent(search)}`;
            if (role !== 'all') url += `&role=${encodeURIComponent(role)}`;

            try {
                const res = await fetch(url);
                const result = await res.json();
                if (result.success && result.data) {
                    cachedUsers = result.data.users || [];
                    renderUsers(cachedUsers);
                    renderPagination(result.data.pagination, 'user', fetchUsers);
                } else {
                    renderUsers([]);
                }
            } catch (err) {
                document.getElementById('usersTableBody').innerHTML = `
                    <tr><td colspan="6" style="text-align: center; color: #ef4444; padding: 20px;">⚠️ Failed to load users.</td></tr>
                `;
            }
        }

        function renderUsers(users) {
            const tbody = document.getElementById('usersTableBody');
            if (!users.length) {
                tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 30px; color: var(--text-muted);">No users found.</td></tr>`;
                return;
            }

            tbody.innerHTML = users.map(u => {
                const roleClass = 'badge-role-' + (u.role || 'student').toLowerCase();
                const lastLogin = u.last_login_at ? new Date(u.last_login_at).toLocaleDateString() : 'Never';

                return `
                    <tr>
                        <td><span style="font-family: monospace; font-weight: 700;">#${u.user_id}</span></td>
                        <td><strong>${escapeHtml(u.full_name)}</strong></td>
                        <td style="color: var(--text-secondary);">${escapeHtml(u.email)}</td>
                        <td><span class="${roleClass}">${escapeHtml(u.role)}</span></td>
                        <td style="font-size: 13px; color: var(--text-muted);">${lastLogin}</td>
                        <td style="text-align: right;">
                            <button class="btn btn-secondary" style="padding: 4px 10px; font-size: 12px;" onclick="openRoleModal(${u.user_id}, '${escapeHtml(u.full_name)}', '${escapeHtml(u.email)}', '${u.role}')">
                                🔑 Edit Role
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function openRoleModal(userId, name, email, currentRole) {
            document.getElementById('roleUserId').value = userId;
            document.getElementById('roleModalUserName').textContent = name;
            document.getElementById('roleModalUserEmail').textContent = email;
            document.getElementById('roleSelect').value = currentRole;
            document.getElementById('roleModalErrors').style.display = 'none';
            document.getElementById('roleModal').classList.add('open');
        }

        function closeRoleModal() {
            document.getElementById('roleModal').classList.remove('open');
        }

        async function handleRoleSubmit(e) {
            e.preventDefault();
            const btn = document.getElementById('roleSubmitBtn');
            const errBox = document.getElementById('roleModalErrors');
            errBox.style.display = 'none';
            btn.disabled = true;

            const form = document.getElementById('roleForm');
            const formData = new FormData(form);

            try {
                const res = await fetch('../StudentHUB_php/admin_crud.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await res.json();
                if (result.success) {
                    showToast(result.message, true);
                    closeRoleModal();
                    fetchUsers(currentUserPage);
                    refreshAnalytics();
                } else {
                    errBox.textContent = result.message || 'Failed to update role.';
                    errBox.style.display = 'block';
                }
            } catch (err) {
                errBox.textContent = 'Network error while updating role.';
                errBox.style.display = 'block';
            } finally {
                btn.disabled = false;
            }
        }

        // ====================================================================
        // AUDIT LOGS MODULE
        // ====================================================================
        function debounceFetchAudit() {
            clearTimeout(auditDebounceTimer);
            auditDebounceTimer = setTimeout(() => fetchAuditLogs(1), 300);
        }

        function resetAuditFilters() {
            document.getElementById('auditSearchInput').value = '';
            document.getElementById('auditSeverityFilter').value = 'all';
            fetchAuditLogs(1);
        }

        async function fetchAuditLogs(page = 1) {
            currentAuditPage = page;
            const search = document.getElementById('auditSearchInput').value.trim();
            const severity = document.getElementById('auditSeverityFilter').value;

            let url = `../StudentHUB_php/admin_crud.php?action=list_audit_logs&page=${page}&limit=12&search=${encodeURIComponent(search)}`;
            if (severity !== 'all') url += `&severity=${encodeURIComponent(severity)}`;

            try {
                const res = await fetch(url);
                const result = await res.json();
                if (result.success && result.data) {
                    renderAuditLogs(result.data.logs || []);
                    renderPagination(result.data.pagination, 'audit', fetchAuditLogs);
                } else {
                    renderAuditLogs([]);
                }
            } catch (err) {
                document.getElementById('auditTableBody').innerHTML = `
                    <tr><td colspan="7" style="text-align: center; color: #ef4444; padding: 20px;">⚠️ Failed to load audit logs.</td></tr>
                `;
            }
        }

        function renderAuditLogs(logs) {
            const tbody = document.getElementById('auditTableBody');
            if (!logs.length) {
                tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 30px; color: var(--text-muted);">No audit events matching criteria.</td></tr>`;
                return;
            }

            tbody.innerHTML = logs.map(l => {
                const sevClass = 'badge-sev-' + (l.severity || 'info').toLowerCase();
                const d = new Date(l.created_at).toLocaleString();

                return `
                    <tr>
                        <td style="font-family: monospace; font-size: 12px; color: var(--text-muted);">#${l.log_id}</td>
                        <td style="font-size: 12px; white-space: nowrap;">${d}</td>
                        <td><span style="font-family: monospace; font-size: 12px; font-weight: 700; color: var(--brand-primary);">${escapeHtml(l.action)}</span></td>
                        <td><span class="${sevClass}">${escapeHtml(l.severity)}</span></td>
                        <td>
                            <strong style="font-size: 13px;">${escapeHtml(l.user_name)}</strong>
                            <div style="font-size: 11px; color: var(--text-muted);">${escapeHtml(l.user_email)}</div>
                        </td>
                        <td style="font-family: monospace; font-size: 12px; color: var(--text-muted);">${escapeHtml(l.ip_address)}</td>
                        <td style="font-size: 13px; color: var(--text-secondary); max-width: 320px;">${escapeHtml(l.details || '')}</td>
                    </tr>
                `;
            }).join('');
        }

        function openPurgeModal() {
            document.getElementById('purgeModal').classList.add('open');
        }

        function closePurgeModal() {
            document.getElementById('purgeModal').classList.remove('open');
        }

        async function handlePurgeSubmit(e) {
            e.preventDefault();
            const btn = document.getElementById('purgeSubmitBtn');
            btn.disabled = true;

            const form = document.getElementById('purgeForm');
            const formData = new FormData(form);

            try {
                const res = await fetch('../StudentHUB_php/admin_crud.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await res.json();
                if (result.success) {
                    showToast(result.message, true);
                    closePurgeModal();
                    fetchAuditLogs(1);
                    refreshAnalytics();
                } else {
                    showToast(result.message || 'Purge failed.', false);
                }
            } catch (err) {
                showToast('Network error during audit purge.', false);
            } finally {
                btn.disabled = false;
            }
        }

        function renderPagination(p, prefix, callback) {
            if (!p) return;
            const info = document.getElementById(prefix + 'PaginationInfo');
            const btns = document.getElementById(prefix + 'PaginationButtons');

            const start = p.total_records === 0 ? 0 : (p.current_page - 1) * p.limit + 1;
            const end = Math.min(p.current_page * p.limit, p.total_records);
            info.textContent = `Showing ${start} to ${end} of ${p.total_records} items`;

            let html = '';
            if (p.current_page > 1) {
                html += `<button class="btn btn-secondary" style="padding: 4px 10px; font-size: 12px;" onclick="${callback.name}(${p.current_page - 1})">&laquo; Prev</button>`;
            }
            for (let i = 1; i <= p.total_pages; i++) {
                if (i === 1 || i === p.total_pages || (i >= p.current_page - 1 && i <= p.current_page + 1)) {
                    const activeStyle = i === p.current_page ? 'background: var(--brand-primary); color: white;' : '';
                    html += `<button class="btn btn-secondary" style="padding: 4px 10px; font-size: 12px; ${activeStyle}" onclick="${callback.name}(${i})">${i}</button>`;
                } else if (i === p.current_page - 2 || i === p.current_page + 2) {
                    html += `<span style="padding: 4px 6px;">...</span>`;
                }
            }
            if (p.current_page < p.total_pages) {
                html += `<button class="btn btn-secondary" style="padding: 4px 10px; font-size: 12px;" onclick="${callback.name}(${p.current_page + 1})">Next &raquo;</button>`;
            }
            btns.innerHTML = html;
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
                closeRoleModal();
                closePurgeModal();
            }
        });
    </script>
</body>
</html>
