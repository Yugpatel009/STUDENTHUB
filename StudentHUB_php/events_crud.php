<?php
declare(strict_types=1);

/**
 * ============================================================================
 * CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY (CHARUSAT)
 * FACULTY OF TECHNOLOGY AND ENGINEERING (FTE)
 * SUBJECT: WEB DEVELOPMENT FRAMEWORKS (ITUE203)
 * Practical 12: Event Management CRUD with File/Poster Upload and Validation
 * ============================================================================
 * File: events_crud.php
 * Description: Controller for Campus Events CRUD with secure file upload handling:
 *              - Server-side MIME validation (finfo_file)
 *              - File size constraint enforcement (max 2MB)
 *              - Cryptographically secure unique filename generation
 *              - Safe upload directory handling and cleanup
 *              - Prepared statements preventing SQL Injection
 *              - Role-Based Access Control (Teacher/Admin for mutating actions)
 *              - CSRF token validation
 * ============================================================================
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/db.php';

initSession();

$currentUser = getCurrentUser();

// Upload configurations
define('EVENT_UPLOAD_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'events');
define('EVENT_MAX_FILE_SIZE', 2 * 1024 * 1024); // 2 MB
const EVENT_ALLOWED_MIMES = [
    'image/jpeg' => 'jpg',
    'image/pjpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp'
];

/**
 * Helper to output structured JSON responses
 */
function sendEventJson(bool $success, string $message, $data = null, array $errors = [], int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'   => $success,
        'message'   => $message,
        'data'      => $data,
        'errors'    => $errors,
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Validates and securely stores an uploaded event poster
 *
 * @param array $file The $_FILES['poster'] array
 * @return array [bool $ok, string $pathOrError]
 */
function processPosterUpload(array $file): array
{
    if (empty($file['name']) || !isset($file['error'])) {
        return [false, 'No poster file was uploaded.'];
    }

    // 1. Check upload error codes
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds upload_max_filesize in php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds MAX_FILE_SIZE specified in the form.',
            UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.'
        ];
        return [false, $uploadErrors[$file['error']] ?? 'Unknown upload error occurred.'];
    }

    // 2. Validate file size (max 2MB)
    if ($file['size'] > EVENT_MAX_FILE_SIZE) {
        $sizeMb = round($file['size'] / (1024 * 1024), 2);
        return [false, "Poster file size ({$sizeMb} MB) exceeds maximum allowed limit of 2 MB."];
    }

    // 3. Validate real MIME type using PHP fileinfo
    $tmpPath = $file['tmp_name'];
    if (!is_uploaded_file($tmpPath)) {
        return [false, 'Security violation: uploaded file is not recognized as a valid upload.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $realMime = $finfo->file($tmpPath);

    if (!array_key_exists($realMime, EVENT_ALLOWED_MIMES)) {
        return [false, "Invalid file format detected ('{$realMime}'). Only JPEG, PNG, and WebP images are permitted."];
    }

    // 4. Validate image binary integrity via getimagesize()
    $imageInfo = @getimagesize($tmpPath);
    if ($imageInfo === false) {
        return [false, 'The uploaded file is not a valid or corrupt image.'];
    }

    // 5. Ensure target upload directory exists and is writable
    if (!is_dir(EVENT_UPLOAD_DIR)) {
        if (!mkdir(EVENT_UPLOAD_DIR, 0755, true) && !is_dir(EVENT_UPLOAD_DIR)) {
            return [false, 'Server storage directory could not be created.'];
        }
    }

    // 6. Generate cryptographically safe unique filename
    $extension = EVENT_ALLOWED_MIMES[$realMime];
    $safeName = 'poster_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $extension;
    $destination = EVENT_UPLOAD_DIR . DIRECTORY_SEPARATOR . $safeName;

    // 7. Move file securely
    if (!move_uploaded_file($tmpPath, $destination)) {
        return [false, 'Failed to save the uploaded poster on the server.'];
    }

    // Return relative public path for database storage
    $publicPath = '../uploads/events/' . $safeName;
    return [true, $publicPath];
}

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? 'list'));
$db = getDatabaseConnection();

// ----------------------------------------------------------------------------
// ACTION 1: LIST EVENTS (Search, Filter, Pagination, Stats)
// ----------------------------------------------------------------------------
if ($action === 'list') {
    $search   = trim((string)($_GET['search'] ?? ''));
    $category = trim((string)($_GET['category'] ?? ''));
    $status   = trim((string)($_GET['status'] ?? ''));
    $sortBy   = trim((string)($_GET['sort'] ?? 'starts_at'));
    $order    = strtoupper(trim((string)($_GET['order'] ?? 'ASC'))) === 'DESC' ? 'DESC' : 'ASC';
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $limit    = max(1, min(50, (int)($_GET['limit'] ?? 12)));
    $offset   = ($page - 1) * $limit;

    $allowedSorts = [
        'starts_at'        => 'e.starts_at',
        'title'            => 'e.title',
        'event_code'       => 'e.event_code',
        'capacity'         => 'e.capacity',
        'registered_count' => 'e.registered_count',
        'created_at'       => 'e.created_at'
    ];
    $sortCol = $allowedSorts[$sortBy] ?? 'e.starts_at';

    $where = [];
    $params = [];
    $types = '';

    if ($search !== '') {
        $where[] = '(e.title LIKE ? OR e.event_code LIKE ? OR e.venue LIKE ? OR e.description LIKE ?)';
        $wild = '%' . $search . '%';
        $params[] = $wild;
        $params[] = $wild;
        $params[] = $wild;
        $params[] = $wild;
        $types .= 'ssss';
    }

    if ($category !== '' && $category !== 'all') {
        $where[] = 'e.category = ?';
        $params[] = $category;
        $types .= 's';
    }

    if ($status !== '' && $status !== 'all') {
        $where[] = 'e.status = ?';
        $params[] = $status;
        $types .= 's';
    }

    $whereSql = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

    // Total count for pagination
    $countSql = "SELECT COUNT(*) AS total FROM events e {$whereSql}";
    $countStmt = $db->prepare($countSql);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $totalRecords = (int)$countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    $totalPages = (int)ceil($totalRecords / $limit);

    // Fetch data rows
    $sql = "SELECT e.event_id, e.event_code, e.title, e.category, e.description,
                   e.poster_image, e.venue, e.starts_at, e.ends_at, e.capacity,
                   e.registered_count, e.status, e.created_at,
                   GREATEST(0, CAST(e.capacity AS SIGNED) - CAST(e.registered_count AS SIGNED)) AS seats_available
            FROM events e
            {$whereSql}
            ORDER BY {$sortCol} {$order}
            LIMIT ? OFFSET ?";

    $dataStmt = $db->prepare($sql);
    $dataTypes = $types . 'ii';
    $dataParams = array_merge($params, [$limit, $offset]);
    $dataStmt->bind_param($dataTypes, ...$dataParams);
    $dataStmt->execute();
    $events = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $dataStmt->close();

    // Compute KPI metrics
    $kpiRes = $db->query(
        "SELECT COUNT(*) AS total_events,
                SUM(CASE WHEN status = 'upcoming' THEN 1 ELSE 0 END) AS upcoming_events,
                COALESCE(SUM(registered_count), 0) AS total_registrations,
                COALESCE(SUM(capacity), 0) AS total_capacity
         FROM events"
    );
    $kpiData = $kpiRes ? $kpiRes->fetch_assoc() : [
        'total_events'        => 0,
        'upcoming_events'     => 0,
        'total_registrations' => 0,
        'total_capacity'      => 0
    ];

    sendEventJson(true, 'Events retrieved successfully.', [
        'events'     => $events,
        'pagination' => [
            'total_records' => $totalRecords,
            'total_pages'   => $totalPages,
            'current_page'  => $page,
            'limit'         => $limit
        ],
        'kpi' => $kpiData
    ]);
}

// ----------------------------------------------------------------------------
// ACTION 2: GET SINGLE EVENT DETAILS
// ----------------------------------------------------------------------------
if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        sendEventJson(false, 'Invalid event ID provided.', null, [], 400);
    }

    $stmt = $db->prepare(
        "SELECT e.*,
                GREATEST(0, CAST(e.capacity AS SIGNED) - CAST(e.registered_count AS SIGNED)) AS seats_available
         FROM events e
         WHERE e.event_id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$event) {
        sendEventJson(false, 'Event not found.', null, [], 404);
    }

    sendEventJson(true, 'Event details retrieved.', $event);
}

// ----------------------------------------------------------------------------
// MUTATING ACTIONS GUARD: Role Check (Teacher or Admin) & CSRF Verification
// ----------------------------------------------------------------------------
if (in_array($action, ['create', 'update', 'delete'], true)) {
    if ($currentUser === null || !in_array($currentUser['role'] ?? '', ['teacher', 'admin'], true)) {
        sendEventJson(false, 'Unauthorized. Only faculty and administrators can manage events.', null, [], 403);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!empty($_SESSION['csrf_token']) && !empty($token)) {
            if (!validateCsrfToken($token)) {
                sendEventJson(false, 'Security token expired or invalid. Please refresh and try again.', null, ['CSRF token mismatch'], 403);
            }
        }
    }
}

// ----------------------------------------------------------------------------
// ACTION 3: CREATE EVENT (With Optional or Mandatory Poster Upload)
// ----------------------------------------------------------------------------
if ($action === 'create') {
    $code     = strtoupper(trim(strip_tags((string)($_POST['event_code'] ?? ''))));
    $title    = trim(strip_tags((string)($_POST['title'] ?? '')));
    $category = trim(strip_tags((string)($_POST['category'] ?? 'Technical')));
    $venue    = trim(strip_tags((string)($_POST['venue'] ?? '')));
    $startsAt = trim((string)($_POST['starts_at'] ?? ''));
    $endsAt   = trim((string)($_POST['ends_at'] ?? ''));
    $capacity = (int)($_POST['capacity'] ?? 100);
    $status   = trim((string)($_POST['status'] ?? 'upcoming'));
    $desc     = trim(strip_tags((string)($_POST['description'] ?? '')));

    $validCategories = ['Technical', 'Workshop', 'Cultural', 'Sports', 'Seminar'];
    $validStatuses   = ['upcoming', 'ongoing', 'completed', 'cancelled'];

    $errors = [];
    if (mb_strlen($code) < 3 || mb_strlen($code) > 20) {
        $errors[] = 'Event Code must be between 3 and 20 characters (e.g. EVT-2026-AI).';
    }
    if (mb_strlen($title) < 3 || mb_strlen($title) > 150) {
        $errors[] = 'Event Title must be between 3 and 150 characters.';
    }
    if (!in_array($category, $validCategories, true)) {
        $errors[] = 'Invalid event category selected.';
    }
    if (mb_strlen($venue) < 3 || mb_strlen($venue) > 150) {
        $errors[] = 'Venue must be between 3 and 150 characters.';
    }
    if (empty($startsAt) || strtotime($startsAt) === false) {
        $errors[] = 'Please provide a valid start date and time.';
    }
    if (empty($endsAt) || strtotime($endsAt) === false) {
        $errors[] = 'Please provide a valid end date and time.';
    }
    if (!empty($startsAt) && !empty($endsAt) && strtotime($endsAt) <= strtotime($startsAt)) {
        $errors[] = 'Event end date/time must be strictly after the start date/time.';
    }
    if ($capacity < 1 || $capacity > 5000) {
        $errors[] = 'Capacity must be between 1 and 5,000 attendees.';
    }
    if (!in_array($status, $validStatuses, true)) {
        $errors[] = 'Invalid event status.';
    }

    // Check duplicate event code
    $chk = $db->prepare('SELECT event_id FROM events WHERE event_code = ? LIMIT 1');
    $chk->bind_param('s', $code);
    $chk->execute();
    if ($chk->get_result()->fetch_assoc()) {
        $chk->close();
        sendEventJson(false, "Event code '{$code}' already exists. Please choose a unique code.", null, ['Duplicate event code'], 409);
    }
    $chk->close();

    // Process Poster Upload if present
    $posterPath = '../img/web-development.jpg'; // default placeholder
    if (!empty($_FILES['poster']) && $_FILES['poster']['error'] !== UPLOAD_ERR_NO_FILE) {
        [$uploadOk, $uploadResult] = processPosterUpload($_FILES['poster']);
        if (!$uploadOk) {
            $errors[] = $uploadResult;
        } else {
            $posterPath = $uploadResult;
        }
    }

    if (!empty($errors)) {
        sendEventJson(false, 'Validation failed. Please correct the errors.', null, $errors, 422);
    }

    // Format dates for MySQL DATETIME
    $mysqlStartsAt = date('Y-m-d H:i:s', strtotime($startsAt));
    $mysqlEndsAt   = date('Y-m-d H:i:s', strtotime($endsAt));

    $stmt = $db->prepare(
        'INSERT INTO events (event_code, title, category, description, poster_image, venue, starts_at, ends_at, capacity, registered_count, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)'
    );
    if (!$stmt) {
        sendEventJson(false, 'Database insert error: ' . $db->error, null, [], 500);
    }

    $stmt->bind_param('ssssssssis', $code, $title, $category, $desc, $posterPath, $venue, $mysqlStartsAt, $mysqlEndsAt, $capacity, $status);
    $stmt->execute();
    $newId = (int)$db->insert_id;
    $stmt->close();

    sendEventJson(true, "Event '{$title}' created successfully.", [
        'event_id'     => $newId,
        'event_code'   => $code,
        'title'        => $title,
        'category'     => $category,
        'poster_image' => $posterPath,
        'venue'        => $venue,
        'starts_at'    => $mysqlStartsAt,
        'ends_at'      => $mysqlEndsAt,
        'capacity'     => $capacity,
        'status'       => $status
    ], [], 201);
}

// ----------------------------------------------------------------------------
// ACTION 4: UPDATE EVENT (With Optional New Poster Upload & Old Poster Cleanup)
// ----------------------------------------------------------------------------
if ($action === 'update') {
    $id       = (int)($_POST['event_id'] ?? 0);
    $code     = strtoupper(trim(strip_tags((string)($_POST['event_code'] ?? ''))));
    $title    = trim(strip_tags((string)($_POST['title'] ?? '')));
    $category = trim(strip_tags((string)($_POST['category'] ?? 'Technical')));
    $venue    = trim(strip_tags((string)($_POST['venue'] ?? '')));
    $startsAt = trim((string)($_POST['starts_at'] ?? ''));
    $endsAt   = trim((string)($_POST['ends_at'] ?? ''));
    $capacity = (int)($_POST['capacity'] ?? 100);
    $status   = trim((string)($_POST['status'] ?? 'upcoming'));
    $desc     = trim(strip_tags((string)($_POST['description'] ?? '')));

    if ($id <= 0) {
        sendEventJson(false, 'Invalid event ID.', null, [], 400);
    }

    // Fetch existing event
    $chkStmt = $db->prepare('SELECT * FROM events WHERE event_id = ? LIMIT 1');
    $chkStmt->bind_param('i', $id);
    $chkStmt->execute();
    $existing = $chkStmt->get_result()->fetch_assoc();
    $chkStmt->close();

    if (!$existing) {
        sendEventJson(false, 'Event not found.', null, [], 404);
    }

    $validCategories = ['Technical', 'Workshop', 'Cultural', 'Sports', 'Seminar'];
    $validStatuses   = ['upcoming', 'ongoing', 'completed', 'cancelled'];

    $errors = [];
    if (mb_strlen($code) < 3 || mb_strlen($code) > 20) {
        $errors[] = 'Event Code must be between 3 and 20 characters.';
    }
    if (mb_strlen($title) < 3 || mb_strlen($title) > 150) {
        $errors[] = 'Event Title must be between 3 and 150 characters.';
    }
    if (!in_array($category, $validCategories, true)) {
        $errors[] = 'Invalid event category.';
    }
    if (mb_strlen($venue) < 3 || mb_strlen($venue) > 150) {
        $errors[] = 'Venue must be between 3 and 150 characters.';
    }
    if (empty($startsAt) || strtotime($startsAt) === false) {
        $errors[] = 'Invalid start date/time.';
    }
    if (empty($endsAt) || strtotime($endsAt) === false) {
        $errors[] = 'Invalid end date/time.';
    }
    if (!empty($startsAt) && !empty($endsAt) && strtotime($endsAt) <= strtotime($startsAt)) {
        $errors[] = 'End date/time must be strictly after the start date/time.';
    }
    if ($capacity < (int)$existing['registered_count']) {
        $errors[] = "Capacity cannot be lower than existing registered count ({$existing['registered_count']}).";
    }

    // Check duplicate code on OTHER events
    $dupChk = $db->prepare('SELECT event_id FROM events WHERE event_code = ? AND event_id != ? LIMIT 1');
    $dupChk->bind_param('si', $code, $id);
    $dupChk->execute();
    if ($dupChk->get_result()->fetch_assoc()) {
        $dupChk->close();
        sendEventJson(false, "Event code '{$code}' is already used by another event.", null, ['Duplicate event code'], 409);
    }
    $dupChk->close();

    // Check if new poster uploaded
    $posterPath = $existing['poster_image'];
    $hasNewPoster = !empty($_FILES['poster']) && $_FILES['poster']['error'] !== UPLOAD_ERR_NO_FILE;

    if ($hasNewPoster) {
        [$uploadOk, $uploadResult] = processPosterUpload($_FILES['poster']);
        if (!$uploadOk) {
            $errors[] = $uploadResult;
        } else {
            // Delete old poster file if located in uploads/events/
            if (!empty($existing['poster_image']) && str_contains($existing['poster_image'], 'uploads/events/')) {
                $oldFileName = basename($existing['poster_image']);
                $oldFullPath = EVENT_UPLOAD_DIR . DIRECTORY_SEPARATOR . $oldFileName;
                if (file_exists($oldFullPath) && is_file($oldFullPath)) {
                    @unlink($oldFullPath);
                }
            }
            $posterPath = $uploadResult;
        }
    }

    if (!empty($errors)) {
        sendEventJson(false, 'Validation failed. Please correct the errors.', null, $errors, 422);
    }

    $mysqlStartsAt = date('Y-m-d H:i:s', strtotime($startsAt));
    $mysqlEndsAt   = date('Y-m-d H:i:s', strtotime($endsAt));

    $stmt = $db->prepare(
        'UPDATE events
         SET event_code = ?, title = ?, category = ?, description = ?, poster_image = ?, venue = ?, starts_at = ?, ends_at = ?, capacity = ?, status = ?
         WHERE event_id = ?'
    );
    $stmt->bind_param('ssssssssisi', $code, $title, $category, $desc, $posterPath, $venue, $mysqlStartsAt, $mysqlEndsAt, $capacity, $status, $id);
    $stmt->execute();
    $stmt->close();

    sendEventJson(true, "Event '{$title}' updated successfully.", [
        'event_id'     => $id,
        'event_code'   => $code,
        'title'        => $title,
        'category'     => $category,
        'poster_image' => $posterPath,
        'venue'        => $venue,
        'starts_at'    => $mysqlStartsAt,
        'ends_at'      => $mysqlEndsAt,
        'capacity'     => $capacity,
        'status'       => $status
    ]);
}

// ----------------------------------------------------------------------------
// ACTION 5: DELETE EVENT & CLEANUP POSTER FILE
// ----------------------------------------------------------------------------
if ($action === 'delete') {
    $id = (int)($_POST['event_id'] ?? 0);
    if ($id <= 0) {
        sendEventJson(false, 'Invalid event ID.', null, [], 400);
    }

    $stmt = $db->prepare('SELECT poster_image FROM events WHERE event_id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        sendEventJson(false, 'Event not found or already deleted.', null, [], 404);
    }

    // Delete uploaded poster file if exists
    if (!empty($row['poster_image']) && str_contains($row['poster_image'], 'uploads/events/')) {
        $fileName = basename($row['poster_image']);
        $fullPath = EVENT_UPLOAD_DIR . DIRECTORY_SEPARATOR . $fileName;
        if (file_exists($fullPath) && is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    // Delete event (CASCADE will remove registrations)
    $delStmt = $db->prepare('DELETE FROM events WHERE event_id = ?');
    $delStmt->bind_param('i', $id);
    $delStmt->execute();
    $delStmt->close();

    sendEventJson(true, "Event #{$id} and its assets were deleted successfully.");
}

// ----------------------------------------------------------------------------
// ACTION 6: STUDENT EVENT REGISTRATION
// ----------------------------------------------------------------------------
if ($action === 'register') {
    if ($currentUser === null) {
        sendEventJson(false, 'Please sign in to register for campus events.', null, [], 401);
    }

    $eventId = (int)($_POST['event_id'] ?? 0);
    if ($eventId <= 0) {
        sendEventJson(false, 'Invalid event ID.', null, [], 400);
    }

    // Find student ID
    $userId = (int)($currentUser['id'] ?? 0);
    $studStmt = $db->prepare('SELECT student_id, full_name, email FROM students WHERE user_id = ? AND is_deleted = 0 LIMIT 1');
    $studStmt->bind_param('i', $userId);
    $studStmt->execute();
    $student = $studStmt->get_result()->fetch_assoc();
    $studStmt->close();

    if (!$student) {
        // Fallback: pick by email
        $userEmail = (string)($currentUser['email'] ?? '');
        $studStmt2 = $db->prepare('SELECT student_id, full_name, email FROM students WHERE email = ? AND is_deleted = 0 LIMIT 1');
        $studStmt2->bind_param('s', $userEmail);
        $studStmt2->execute();
        $student = $studStmt2->get_result()->fetch_assoc();
        $studStmt2->close();
    }

    if (!$student) {
        sendEventJson(false, 'Student academic profile not found. Please contact administration.', null, [], 404);
    }

    $studentId = (int)$student['student_id'];

    // Check if already registered
    $chkReg = $db->prepare('SELECT registration_id FROM registrations WHERE student_id = ? AND event_id = ? LIMIT 1');
    $chkReg->bind_param('ii', $studentId, $eventId);
    $chkReg->execute();
    if ($chkReg->get_result()->fetch_assoc()) {
        $chkReg->close();
        sendEventJson(false, 'You are already registered for this event.', null, ['Already registered'], 409);
    }
    $chkReg->close();

    // Check capacity
    $evtStmt = $db->prepare('SELECT capacity, registered_count, title FROM events WHERE event_id = ? LIMIT 1');
    $evtStmt->bind_param('i', $eventId);
    $evtStmt->execute();
    $evt = $evtStmt->get_result()->fetch_assoc();
    $evtStmt->close();

    if (!$evt) {
        sendEventJson(false, 'Event not found.', null, [], 404);
    }

    if ((int)$evt['registered_count'] >= (int)$evt['capacity']) {
        sendEventJson(false, 'Event registration is closed. All seats have been filled.', null, ['Event full'], 400);
    }

    // Insert registration and increment count
    $db->begin_transaction();
    try {
        $insReg = $db->prepare("INSERT INTO registrations (student_id, event_id, status) VALUES (?, ?, 'registered')");
        $insReg->bind_param('ii', $studentId, $eventId);
        $insReg->execute();
        $regId = (int)$db->insert_id;
        $insReg->close();

        $updEvt = $db->prepare('UPDATE events SET registered_count = registered_count + 1 WHERE event_id = ?');
        $updEvt->bind_param('i', $eventId);
        $updEvt->execute();
        $updEvt->close();

        $db->commit();
        sendEventJson(true, "Congratulations! You are successfully registered for '{$evt['title']}'.", [
            'registration_id' => $regId,
            'event_id'        => $eventId,
            'student_id'      => $studentId
        ], [], 201);
    } catch (Throwable $e) {
        $db->rollback();
        sendEventJson(false, 'Failed to complete event registration: ' . $e->getMessage(), null, [], 500);
    }
}

sendEventJson(false, 'Invalid action specified.', null, [], 400);
