<?php
declare(strict_types=1);

/**
 * ============================================================================
 * CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY (CHARUSAT)
 * FACULTY OF TECHNOLOGY AND ENGINEERING (FTE)
 * SUBJECT: WEB DEVELOPMENT FRAMEWORKS (ITUE203)
 * Practical 12: Event Management CRUD with File/Poster Upload and Validation
 * ============================================================================
 * File: events.php
 * Description: Interactive Campus Events Management Portal supporting:
 *              - Real-time event catalog search & category filtering
 *              - Event poster file upload with strict MIME & size validation
 *              - Add, Edit, Delete CRUD operations for Faculty & Admins
 *              - Student event registration with seat capacity tracking
 *              - Full Light & Dark theme compatibility
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

// Initial KPI calculation for fast server-side render
$resKpi = $db->query(
    "SELECT COUNT(*) AS total_events,
            SUM(CASE WHEN status = 'upcoming' THEN 1 ELSE 0 END) AS upcoming_events,
            COALESCE(SUM(registered_count), 0) AS total_registrations,
            COALESCE(SUM(capacity), 0) AS total_capacity
     FROM events"
);
$kpi = $resKpi ? $resKpi->fetch_assoc() : [
    'total_events'        => 0,
    'upcoming_events'     => 0,
    'total_registrations' => 0,
    'total_capacity'      => 0
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Campus Events & Workshops (Practical 12)</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/events.css?v=2.1">
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
        .filter-panel { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 18px 20px; margin-bottom: 24px; box-shadow: var(--shadow-sm); }
        .filter-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 12px; align-items: center; }
        @media (max-width: 900px) { .filter-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 600px) { .filter-grid { grid-template-columns: 1fr; } }
        .input-control, .select-control { width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-subtle); color: var(--text-primary); font-size: 14px; transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box; }
        .input-control:focus, .select-control:focus { outline: none; border-color: var(--brand-primary); box-shadow: 0 0 0 3px var(--brand-glow); }
        .select-control option { background: var(--bg-card); color: var(--text-primary); }
        .form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        @media (max-width: 600px) { .form-row-2 { grid-template-columns: 1fr; } }
        .form-group { margin-bottom: 14px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; }
        .btn-action { padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 4px; }
        .btn-edit { background: #3b82f6; color: white; }
        .btn-edit:hover { background: #2563eb; }
        .btn-delete { background: #ef4444; color: white; }
        .btn-delete:hover { background: #dc2626; }
        .btn-register { background: #10b981; color: white; }
        .btn-register:hover { background: #059669; }
        .toast-popup { position: fixed; bottom: 24px; right: 24px; padding: 14px 22px; border-radius: 8px; color: white; font-size: 14px; font-weight: 600; display: none; z-index: 1200; box-shadow: 0 10px 25px rgba(0,0,0,0.4); }
        .toast-success { background: #059669; }
        .toast-error { background: #dc2626; }

        /* Event Modal Card & Backdrop System */
        #eventModal.modal-overlay {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            background: rgba(15, 23, 42, 0.78) !important;
            backdrop-filter: blur(6px) !important;
            -webkit-backdrop-filter: blur(6px) !important;
            display: none;
            align-items: center !important;
            justify-content: center !important;
            z-index: 2500 !important;
            padding: 20px !important;
            box-sizing: border-box !important;
        }

        #eventModal.modal-overlay.open,
        #eventModal.modal-overlay.active {
            display: flex !important;
            opacity: 1 !important;
            visibility: visible !important;
        }

        #eventModal .modal-card {
            background: var(--bg-card, #ffffff) !important;
            border: 1px solid var(--border-color, #cbd5e1) !important;
            border-radius: 14px !important;
            width: 100% !important;
            max-width: 640px !important;
            max-height: 88vh !important;
            overflow-y: auto !important;
            padding: 24px 28px !important;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.45) !important;
            position: relative !important;
            color: var(--text-primary, #0f172a) !important;
            box-sizing: border-box !important;
            display: flex !important;
            flex-direction: column !important;
            animation: eventModalScale 0.22s cubic-bezier(0.16, 1, 0.3, 1) !important;
        }

        @keyframes eventModalScale {
            from { opacity: 0; transform: scale(0.96) translateY(12px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        #eventModal .modal-header {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 18px !important;
            padding: 0 0 14px 0 !important;
            border-bottom: 1px solid var(--border-color, #e2e8f0) !important;
            background: transparent !important;
        }

        #eventModal .modal-header h2 {
            margin: 0 !important;
            font-size: 20px !important;
            font-weight: 700 !important;
            color: var(--text-primary, #0f172a) !important;
        }

        #eventModal .modal-close {
            background: transparent !important;
            border: none !important;
            font-size: 26px !important;
            line-height: 1 !important;
            color: var(--text-muted, #64748b) !important;
            cursor: pointer !important;
            padding: 4px 8px !important;
            border-radius: 6px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            transition: color 0.15s ease, transform 0.15s ease !important;
        }

        #eventModal .modal-close:hover {
            color: #ef4444 !important;
            transform: scale(1.15) !important;
        }
    </style>
</head>
<body>
    <!-- Site Header -->
    <header class="site-header">
        <a class="brand" href="../index.html">
            <img src="../img/logo.png" alt="Student Hub Logo">
        </a>

        <!-- Centered Floating Pill Navigation -->
        <nav class="main-nav" aria-label="Main Navigation">
            <a href="dashboard.php">Dashboard</a>
            <a href="students.php">Students</a>
            <a href="courses.php">Courses</a>
            <?php if ($isAdmin): ?>
                <a href="admin.php" style="color: #ef4444; font-weight: 700;">Admin Center</a>
            <?php endif; ?>
            <a href="events.php" class="active">Campus Events</a>
            <?php if ($isFaculty): ?>
                <a href="faculty.php">Faculty Portal</a>
            <?php endif; ?>
            <a href="assignment.php">Assignments</a>
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
                <?php if ($isAdmin): ?>
                <a href="admin.php" style="color: #ef4444; font-weight: 700;">Admin Center</a>
            <?php endif; ?>
            <a href="events.php" class="active">Campus Events</a>
                <?php if ($isFaculty): ?>
                    <a href="faculty.php">Faculty Portal</a>
                <?php endif; ?>
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

    <main class="container">
        <!-- Page Heading & Actions -->
        <section class="page-header-row">
            <div>
                <p style="text-transform: uppercase; font-size: 12px; letter-spacing: 0.1em; color: var(--brand-primary); font-weight: 700; margin: 0 0 6px;">
                    Practical 12: Event Management CRUD & Secure File Upload
                </p>
                <h1 style="margin: 0; font-size: 28px; color: var(--text-primary);">Campus Events & Workshops</h1>
                <p style="margin: 6px 0 0; color: var(--text-muted); font-size: 14px;">
                    Explore upcoming hackathons, technical workshops, and seminars with verified event poster uploads.
                </p>
            </div>
            <?php if ($isFaculty): ?>
                <div>
                    <button class="btn btn-primary" id="createEventBtn" type="button" onclick="openAddEventModal()">➕ Create New Event</button>
                </div>
            <?php endif; ?>
        </section>

        <!-- Event KPI Summary Cards -->
        <section class="event-kpi-grid">
            <div class="event-kpi-card">
                <div class="event-kpi-label">Total Events</div>
                <div class="event-kpi-value" id="kpiTotalEvents"><?= (int)$kpi['total_events'] ?></div>
            </div>
            <div class="event-kpi-card">
                <div class="event-kpi-label">Upcoming Events</div>
                <div class="event-kpi-value" id="kpiUpcomingEvents" style="color: #10b981;"><?= (int)$kpi['upcoming_events'] ?></div>
            </div>
            <div class="event-kpi-card">
                <div class="event-kpi-label">Total Registrations</div>
                <div class="event-kpi-value" id="kpiRegistrations" style="color: #3b82f6;"><?= (int)$kpi['total_registrations'] ?></div>
            </div>
            <div class="event-kpi-card">
                <div class="event-kpi-label">Total Capacity</div>
                <div class="event-kpi-value" id="kpiCapacity" style="color: #fbbf24;"><?= (int)$kpi['total_capacity'] ?> Seats</div>
            </div>
        </section>

        <!-- Search & Filter Controls -->
        <section class="filter-panel">
            <div class="filter-grid">
                <div>
                    <input type="text" id="eventSearchInput" class="input-control" placeholder="🔍 Search by title, venue, or code..." oninput="debounceFetchEvents()">
                </div>
                <div>
                    <select id="eventCategoryFilter" class="select-control" onchange="fetchEvents(1)">
                        <option value="all">All Categories</option>
                        <option value="Technical">Technical</option>
                        <option value="Workshop">Workshop</option>
                        <option value="Cultural">Cultural</option>
                        <option value="Sports">Sports</option>
                        <option value="Seminar">Seminar</option>
                    </select>
                </div>
                <div>
                    <select id="eventStatusFilter" class="select-control" onchange="fetchEvents(1)">
                        <option value="all">All Statuses</option>
                        <option value="upcoming">Upcoming</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div>
                    <select id="eventSortBy" class="select-control" onchange="fetchEvents(1)">
                        <option value="starts_at">Date (Earliest First)</option>
                        <option value="title">Title (A - Z)</option>
                        <option value="capacity">Capacity (High to Low)</option>
                    </select>
                </div>
                <div>
                    <button class="btn btn-secondary" onclick="resetEventFilters()" style="padding: 10px 16px; font-size: 13px;">Reset</button>
                </div>
            </div>
        </section>

        <!-- Dynamic Events Grid -->
        <section class="events-crud-grid" id="eventsGridContainer">
            <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--text-muted);">
                Loading campus events...
            </div>
        </section>

        <!-- Pagination Controls -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; color: var(--text-muted); font-size: 13px; margin-bottom: 40px;">
            <span id="eventPaginationInfo">Showing 0 events</span>
            <div style="display: flex; gap: 6px;" id="eventPaginationButtons"></div>
        </div>
    </main>

    <!-- ====================================================================== -->
    <!-- MODAL: ADD / EDIT EVENT WITH POSTER UPLOAD -->
    <!-- ====================================================================== -->
    <div class="modal-overlay" id="eventModal">
        <div class="modal-card" style="max-width: 620px;">
            <div class="modal-header">
                <h2 id="eventModalTitle">Create Campus Event</h2>
                <button type="button" class="modal-close" id="closeEventModalBtn" onclick="closeEventModal()" aria-label="Close modal">&times;</button>
            </div>
            <form id="eventForm" onsubmit="handleEventSubmit(event)" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" id="eventFormAction" value="create">
                <input type="hidden" name="event_id" id="eventFormId" value="">

                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label" for="eventCodeInput">Event Code *</label>
                        <input type="text" id="eventCodeInput" name="event_code" class="input-control" placeholder="e.g. EVT-2026-AI" required maxlength="20">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="eventCategoryInput">Category *</label>
                        <select id="eventCategoryInput" name="category" class="select-control" required>
                            <option value="Technical">Technical</option>
                            <option value="Workshop">Workshop</option>
                            <option value="Cultural">Cultural</option>
                            <option value="Sports">Sports</option>
                            <option value="Seminar">Seminar</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="eventTitleInput">Event Title *</label>
                    <input type="text" id="eventTitleInput" name="title" class="input-control" placeholder="e.g. Applied Machine Learning Bootcamp" required maxlength="150">
                </div>

                <div class="form-group">
                    <label class="form-label" for="eventVenueInput">Venue Location *</label>
                    <input type="text" id="eventVenueInput" name="venue" class="input-control" placeholder="e.g. Lab 204, FTE Department" required maxlength="150">
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label" for="eventStartsAtInput">Starts At *</label>
                        <input type="datetime-local" id="eventStartsAtInput" name="starts_at" class="input-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="eventEndsAtInput">Ends At *</label>
                        <input type="datetime-local" id="eventEndsAtInput" name="ends_at" class="input-control" required>
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label" for="eventCapacityInput">Capacity (Max Seats) *</label>
                        <input type="number" id="eventCapacityInput" name="capacity" class="input-control" min="1" max="5000" value="100" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="eventStatusInput">Status *</label>
                        <select id="eventStatusInput" name="status" class="select-control" required>
                            <option value="upcoming">Upcoming</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <!-- Event Poster Upload Zone -->
                <div class="form-group">
                    <label class="form-label">Event Poster Image (Optional / Recommended)</label>
                    <div class="poster-dropzone" onclick="document.getElementById('eventPosterInput').click()">
                        <div id="dropzoneText">
                            <span style="font-size: 24px;">📁</span>
                            <p style="margin: 4px 0; font-size: 13px; font-weight: 600; color: var(--text-primary);">
                                Click to select or drop an event banner poster
                            </p>
                            <p class="file-spec-hint">Allowed: JPG, PNG, WebP (Max Size: 2 MB)</p>
                        </div>
                        <input type="file" id="eventPosterInput" name="poster" accept="image/jpeg,image/png,image/webp" style="display: none;" onchange="previewPoster(event)">
                        <div class="poster-preview-box" id="posterPreviewBox">
                            <img id="posterPreviewImg" class="poster-preview-img" src="" alt="Poster Preview">
                            <p style="font-size: 11px; color: var(--text-muted); margin: 4px 0 0;" id="posterFileName"></p>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="eventDescInput">Event Description & Agenda</label>
                    <textarea id="eventDescInput" name="description" class="input-control" rows="3" style="resize: vertical;" placeholder="Overview, keynote speakers, prerequisite skills..."></textarea>
                </div>

                <div id="eventModalErrors" style="color: #ef4444; font-size: 13px; margin-bottom: 14px; display: none;"></div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; padding-top: 14px; border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" onclick="closeEventModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="eventSubmitBtn">Save Event</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Floating Feedback Toast -->
    <div id="eventToast" class="toast-popup"></div>

    <footer class="site-footer">
        <p>&copy; 2026 Student Hub - CHARUSAT. All rights reserved.</p>
    </footer>

    <script src="../js/script.js"></script>
    <script>
        const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
        const IS_FACULTY = <?= json_encode($isFaculty) ?>;
        let cachedEvents = [];
        let eventDebounceTimer = null;
        let currentEventPage = 1;

        function showToast(msg, isSuccess = true) {
            const toast = document.getElementById('eventToast');
            toast.textContent = msg;
            toast.className = 'toast-popup ' + (isSuccess ? 'toast-success' : 'toast-error');
            toast.style.display = 'block';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3500);
        }

        function debounceFetchEvents() {
            clearTimeout(eventDebounceTimer);
            eventDebounceTimer = setTimeout(() => fetchEvents(1), 300);
        }

        function resetEventFilters() {
            document.getElementById('eventSearchInput').value = '';
            document.getElementById('eventCategoryFilter').value = 'all';
            document.getElementById('eventStatusFilter').value = 'all';
            document.getElementById('eventSortBy').value = 'starts_at';
            fetchEvents(1);
        }

        function previewPoster(e) {
            const file = e.target.files[0];
            const previewBox = document.getElementById('posterPreviewBox');
            const previewImg = document.getElementById('posterPreviewImg');
            const fileNameText = document.getElementById('posterFileName');

            if (file) {
                if (file.size > 2 * 1024 * 1024) {
                    showToast('File exceeds 2 MB limit.', false);
                    e.target.value = '';
                    previewBox.style.display = 'none';
                    return;
                }
                const reader = new FileReader();
                reader.onload = (event) => {
                    previewImg.src = event.target.result;
                    fileNameText.textContent = `${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
                    previewBox.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                previewBox.style.display = 'none';
            }
        }

        async function fetchEvents(page = 1) {
            currentEventPage = page;
            const search = document.getElementById('eventSearchInput').value.trim();
            const category = document.getElementById('eventCategoryFilter').value;
            const status = document.getElementById('eventStatusFilter').value;
            const sortBy = document.getElementById('eventSortBy').value;

            let url = `../StudentHUB_php/events_crud.php?action=list&page=${page}&limit=9&search=${encodeURIComponent(search)}&sort=${encodeURIComponent(sortBy)}`;
            if (category !== 'all') url += `&category=${encodeURIComponent(category)}`;
            if (status !== 'all') url += `&status=${encodeURIComponent(status)}`;

            try {
                const res = await fetch(url);
                const result = await res.json();
                if (result.success && result.data) {
                    cachedEvents = result.data.events || [];
                    renderEvents(cachedEvents);
                    renderPagination(result.data.pagination);
                    if (result.data.kpi) {
                        document.getElementById('kpiTotalEvents').textContent = result.data.kpi.total_events || 0;
                        document.getElementById('kpiUpcomingEvents').textContent = result.data.kpi.upcoming_events || 0;
                        document.getElementById('kpiRegistrations').textContent = result.data.kpi.total_registrations || 0;
                        document.getElementById('kpiCapacity').textContent = (result.data.kpi.total_capacity || 0) + ' Seats';
                    }
                } else {
                    renderEvents([]);
                }
            } catch (err) {
                console.error('Fetch error:', err);
                document.getElementById('eventsGridContainer').innerHTML = `
                    <div style="grid-column: 1/-1; text-align: center; color: #ef4444; padding: 40px;">
                        ⚠️ Failed to load campus events. Please verify database connection.
                    </div>
                `;
            }
        }

        function renderEvents(events) {
            const container = document.getElementById('eventsGridContainer');
            if (!events.length) {
                container.innerHTML = `
                    <div style="grid-column: 1/-1; text-align: center; padding: 50px 20px; background: var(--bg-card); border: 1px dashed var(--border-color); border-radius: 12px;">
                        <p style="font-size: 16px; color: var(--text-muted); margin: 0 0 10px;">No campus events found matching your search criteria.</p>
                        ${IS_FACULTY ? '<button class="btn btn-primary" onclick="openAddEventModal()">➕ Create First Event</button>' : ''}
                    </div>
                `;
                return;
            }

            container.innerHTML = events.map(evt => {
                const catClass = 'cat-' + (evt.category || 'technical').toLowerCase();
                const statusClass = 'status-' + (evt.status || 'upcoming').toLowerCase();
                const poster = evt.poster_image ? evt.poster_image : '../img/web-development.jpg';
                const cap = parseInt(evt.capacity) || 100;
                const reg = parseInt(evt.registered_count) || 0;
                const avail = Math.max(0, cap - reg);
                const pct = Math.min(100, Math.round((reg / cap) * 100));

                const startDate = new Date(evt.starts_at).toLocaleDateString('en-US', {
                    month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit'
                });

                return `
                    <article class="event-crud-card" id="event-card-${evt.event_id}">
                        <div class="event-poster-thumb">
                            <img src="${escapeHtml(poster)}" alt="${escapeHtml(evt.title)} Poster" onerror="this.onerror=null; this.src='../img/web-development.jpg';">
                            <span class="event-category-pill ${catClass}">${escapeHtml(evt.category)}</span>
                            <span class="event-status-pill ${statusClass}">${escapeHtml(evt.status)}</span>
                        </div>
                        <div class="event-card-body">
                            <span class="event-card-code">${escapeHtml(evt.event_code)}</span>
                            <h3 class="event-card-title">${escapeHtml(evt.title)}</h3>
                            <div class="event-card-meta">
                                <span>📍</span>
                                <span>${escapeHtml(evt.venue)}</span>
                            </div>
                            <div class="event-card-meta">
                                <span>📅</span>
                                <span>${startDate}</span>
                            </div>
                            <p class="event-card-desc">${escapeHtml(evt.description || 'No detailed agenda provided.')}</p>
                            
                            <div class="event-seats-bar">
                                <div class="event-seats-progress" style="width: ${pct}%;"></div>
                            </div>
                            <div class="event-seats-text">
                                <span>${reg} Registered (${pct}%)</span>
                                <span><strong>${avail}</strong> Seats Left</span>
                            </div>
                        </div>
                        <div class="event-card-footer">
                            ${IS_FACULTY ? `
                                <div>
                                    <button class="btn-action btn-edit" onclick="openEditEventModal(${evt.event_id})">✏️ Edit</button>
                                    <button class="btn-action btn-delete" onclick="handleDeleteEvent(${evt.event_id}, '${escapeHtml(evt.title)}')">🗑️ Delete</button>
                                </div>
                            ` : `
                                <button class="btn-action btn-register" onclick="handleRegisterEvent(${evt.event_id})" ${avail <= 0 || evt.status !== 'upcoming' ? 'disabled style="opacity:0.5;"' : ''}>
                                    ${avail <= 0 ? 'Event Full' : (evt.status !== 'upcoming' ? 'Closed' : 'Register Now &rarr;')}
                                </button>
                            `}
                        </div>
                    </article>
                `;
            }).join('');
        }

        function renderPagination(p) {
            if (!p) return;
            const info = document.getElementById('eventPaginationInfo');
            const btns = document.getElementById('eventPaginationButtons');

            const start = p.total_records === 0 ? 0 : (p.current_page - 1) * p.limit + 1;
            const end = Math.min(p.current_page * p.limit, p.total_records);
            info.textContent = `Showing ${start} to ${end} of ${p.total_records} events`;

            let html = '';
            if (p.current_page > 1) {
                html += `<button class="btn btn-secondary" style="padding: 4px 10px; font-size: 12px;" onclick="fetchEvents(${p.current_page - 1})">&laquo; Prev</button>`;
            }
            for (let i = 1; i <= p.total_pages; i++) {
                if (i === 1 || i === p.total_pages || (i >= p.current_page - 1 && i <= p.current_page + 1)) {
                    const activeStyle = i === p.current_page ? 'background: var(--brand-primary); color: white;' : '';
                    html += `<button class="btn btn-secondary" style="padding: 4px 10px; font-size: 12px; ${activeStyle}" onclick="fetchEvents(${i})">${i}</button>`;
                } else if (i === p.current_page - 2 || i === p.current_page + 2) {
                    html += `<span style="padding: 4px 6px;">...</span>`;
                }
            }
            if (p.current_page < p.total_pages) {
                html += `<button class="btn btn-secondary" style="padding: 4px 10px; font-size: 12px;" onclick="fetchEvents(${p.current_page + 1})">Next &raquo;</button>`;
            }
            btns.innerHTML = html;
        }

        function formatLocalDatetime(date) {
            const pad = (n) => String(n).padStart(2, '0');
            const y = date.getFullYear();
            const m = pad(date.getMonth() + 1);
            const d = pad(date.getDate());
            const h = pad(date.getHours());
            const min = pad(date.getMinutes());
            return `${y}-${m}-${d}T${h}:${min}`;
        }

        function safeParseDatetime(dateStr) {
            if (!dateStr) return '';
            const safe = String(dateStr).replace(' ', 'T');
            const d = new Date(safe);
            if (isNaN(d.getTime())) return '';
            return formatLocalDatetime(d);
        }

        function openAddEventModal() {
            const form = document.getElementById('eventForm');
            if (form) form.reset();

            document.getElementById('eventModalTitle').textContent = 'Create Campus Event';
            document.getElementById('eventFormAction').value = 'create';
            document.getElementById('eventFormId').value = '';
            document.getElementById('eventCodeInput').value = '';
            document.getElementById('eventCodeInput').readOnly = false;
            document.getElementById('eventTitleInput').value = '';
            document.getElementById('eventCategoryInput').value = 'Technical';
            document.getElementById('eventVenueInput').value = '';
            document.getElementById('eventCapacityInput').value = 100;
            document.getElementById('eventStatusInput').value = 'upcoming';
            document.getElementById('eventDescInput').value = '';
            document.getElementById('eventPosterInput').value = '';
            document.getElementById('posterPreviewBox').style.display = 'none';
            document.getElementById('eventModalErrors').style.display = 'none';

            // Set default dates (tomorrow 9am to 5pm in local time)
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            tomorrow.setHours(9, 0, 0, 0);
            const tomorrowEnd = new Date(tomorrow);
            tomorrowEnd.setHours(17, 0, 0, 0);

            document.getElementById('eventStartsAtInput').value = formatLocalDatetime(tomorrow);
            document.getElementById('eventEndsAtInput').value = formatLocalDatetime(tomorrowEnd);

            document.getElementById('eventSubmitBtn').textContent = 'Create Event';
            const modal = document.getElementById('eventModal');
            if (modal) {
                modal.classList.add('open', 'active');
            }
            document.body.classList.add('modal-open');
        }

        function openEditEventModal(eventId) {
            const evt = cachedEvents.find(e => Number(e.event_id) === Number(eventId));
            if (!evt) return;

            document.getElementById('eventModalTitle').textContent = `Edit Event: ${evt.event_code}`;
            document.getElementById('eventFormAction').value = 'update';
            document.getElementById('eventFormId').value = evt.event_id;
            document.getElementById('eventCodeInput').value = evt.event_code;
            document.getElementById('eventTitleInput').value = evt.title;
            document.getElementById('eventCategoryInput').value = evt.category;
            document.getElementById('eventVenueInput').value = evt.venue;
            document.getElementById('eventCapacityInput').value = evt.capacity;
            document.getElementById('eventStatusInput').value = evt.status;
            document.getElementById('eventDescInput').value = evt.description || '';
            document.getElementById('eventPosterInput').value = '';
            document.getElementById('eventModalErrors').style.display = 'none';

            // Format dates for input using safe parser
            document.getElementById('eventStartsAtInput').value = safeParseDatetime(evt.starts_at);
            document.getElementById('eventEndsAtInput').value = safeParseDatetime(evt.ends_at);

            // Show existing poster preview if available
            if (evt.poster_image) {
                const previewBox = document.getElementById('posterPreviewBox');
                const previewImg = document.getElementById('posterPreviewImg');
                const fileNameText = document.getElementById('posterFileName');
                previewImg.src = evt.poster_image;
                fileNameText.textContent = 'Current Poster (Upload new to replace)';
                previewBox.style.display = 'block';
            } else {
                document.getElementById('posterPreviewBox').style.display = 'none';
            }

            document.getElementById('eventSubmitBtn').textContent = 'Update Event';
            const modal = document.getElementById('eventModal');
            if (modal) {
                modal.classList.add('open', 'active');
            }
            document.body.classList.add('modal-open');
        }

        function closeEventModal() {
            const modal = document.getElementById('eventModal');
            if (modal) {
                modal.classList.remove('open', 'active');
            }
            document.body.classList.remove('modal-open');
        }

        // Global exposure
        window.openAddEventModal = openAddEventModal;
        window.openEditEventModal = openEditEventModal;
        window.closeEventModal = closeEventModal;

        async function handleEventSubmit(e) {
            e.preventDefault();
            const btn = document.getElementById('eventSubmitBtn');
            const errBox = document.getElementById('eventModalErrors');
            errBox.style.display = 'none';
            btn.disabled = true;

            const form = document.getElementById('eventForm');
            const formData = new FormData(form);

            try {
                const res = await fetch('../StudentHUB_php/events_crud.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const result = await res.json();
                if (result.success) {
                    showToast(result.message, true);
                    closeEventModal();
                    fetchEvents(currentEventPage);
                } else {
                    const errors = result.errors && result.errors.length ? result.errors.join('<br>') : result.message;
                    errBox.innerHTML = errors;
                    errBox.style.display = 'block';
                }
            } catch (err) {
                errBox.textContent = 'A network error occurred while saving the event.';
                errBox.style.display = 'block';
            } finally {
                btn.disabled = false;
            }
        }

        async function handleDeleteEvent(eventId, title) {
            if (!confirm(`Are you sure you want to delete event "${title}"? This will remove the event and its uploaded poster.`)) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('event_id', eventId);
            formData.append('csrf_token', CSRF_TOKEN);

            try {
                const res = await fetch('../StudentHUB_php/events_crud.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const result = await res.json();
                if (result.success) {
                    showToast(result.message, true);
                    fetchEvents(currentEventPage);
                } else {
                    showToast(result.message || 'Failed to delete event.', false);
                }
            } catch (err) {
                showToast('Network error while deleting event.', false);
            }
        }

        async function handleRegisterEvent(eventId) {
            const formData = new FormData();
            formData.append('action', 'register');
            formData.append('event_id', eventId);
            formData.append('csrf_token', CSRF_TOKEN);

            try {
                const res = await fetch('../StudentHUB_php/events_crud.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const result = await res.json();
                if (result.success) {
                    showToast(result.message, true);
                    fetchEvents(currentEventPage);
                } else {
                    showToast(result.message || 'Registration failed.', false);
                }
            } catch (err) {
                showToast('Network error during event registration.', false);
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
            if (e.key === 'Escape') closeEventModal();
        });

        document.addEventListener('DOMContentLoaded', () => {
            fetchEvents(1);

            // Close on overlay backdrop click
            const evtModal = document.getElementById('eventModal');
            if (evtModal) {
                evtModal.addEventListener('click', (e) => {
                    if (e.target === evtModal) {
                        closeEventModal();
                    }
                });
            }

            // Fail-safe direct click binding on Create New Event button
            const createBtn = document.getElementById('createEventBtn');
            if (createBtn) {
                createBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    openAddEventModal();
                });
            }
        });
    </script>
</body>
</html>
