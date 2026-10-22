<?php
declare(strict_types=1);

/**
 * ============================================================================
 * CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY (CHARUSAT)
 * FACULTY OF TECHNOLOGY AND ENGINEERING (FTE)
 * SUBJECT: WEB DEVELOPMENT FRAMEWORKS (ITUE203)
 * Practical 11: Student Management CRUD with Search, Filter & Pagination
 * ============================================================================
 * File: students_crud.php
 * Description: Controller handling Create, Read, Update, Delete (CRUD),
 *              Search, Filter, and Pagination for Student Records using
 *              secure MySQLi / PDO Prepared Statements.
 * ============================================================================
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

initSession();

// Ensure user is authenticated to interact with student records
$currentUser = getCurrentUser();
if ($currentUser === null && php_sapi_name() !== 'cli') {
    http_response_code(401);
    if (isAjaxRequest()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ../Pages/login.html');
    exit;
}

/**
 * Detect AJAX / Fetch request
 */
function isAjaxRequest(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhr = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    return $xhr === 'xmlhttprequest' || str_contains($accept, 'application/json');
}

/**
 * Terminate with structured JSON response
 */
function sendCrudJson(bool $success, string $message, $data = null, array $errors = [], int $statusCode = 200): never
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

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? 'list'));
$db = getDatabaseConnection();

// ----------------------------------------------------------------------------
// ACTION 1: LIST (Read all with Search, Filter & Pagination)
// ----------------------------------------------------------------------------
if ($action === 'list') {
    $search   = trim((string)($_GET['search'] ?? ''));
    $course   = trim((string)($_GET['course'] ?? ''));
    $semester = isset($_GET['semester']) && $_GET['semester'] !== '' ? (int)$_GET['semester'] : null;
    $sortBy   = trim((string)($_GET['sort'] ?? 'student_id'));
    $order    = strtoupper(trim((string)($_GET['order'] ?? 'ASC'))) === 'DESC' ? 'DESC' : 'ASC';
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $limit    = max(1, min(50, (int)($_GET['limit'] ?? 10)));
    $offset   = ($page - 1) * $limit;
    $showDeleted = !empty($_GET['show_deleted']) && $_GET['show_deleted'] === '1';

    // Whitelist allowed sort columns to prevent SQL injection
    $allowedSorts = [
        'student_id'    => 's.student_id',
        'full_name'     => 's.full_name',
        'email'         => 's.email',
        'enrollment_no' => 's.enrollment_no',
        'course_name'   => 's.course_name',
        'semester'      => 's.semester',
        'cgpa'          => 's.cgpa',
        'created_at'    => 's.created_at'
    ];
    $sortColumn = $allowedSorts[$sortBy] ?? 's.student_id';

    // Build WHERE conditions dynamically with parameter binding
    $whereClauses = [];
    $params = [];
    $types = '';

    // Soft-delete filter
    if (!$showDeleted) {
        $whereClauses[] = 's.is_deleted = 0';
    }

    // Search query (matches name, email, or enrollment_no)
    if ($search !== '') {
        $whereClauses[] = '(s.full_name LIKE ? OR s.email LIKE ? OR s.enrollment_no LIKE ?)';
        $searchWildcard = '%' . $search . '%';
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
        $types .= 'sss';
    }

    // Course filter
    if ($course !== '') {
        $whereClauses[] = 's.course_name = ?';
        $params[] = $course;
        $types .= 's';
    }

    // Semester filter
    if ($semester !== null && $semester >= 1 && $semester <= 10) {
        $whereClauses[] = 's.semester = ?';
        $params[] = $semester;
        $types .= 'i';
    }

    $whereSql = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

    // 1. Get Total Count for pagination
    $countSql = "SELECT COUNT(*) AS total FROM students s {$whereSql}";
    $countStmt = $db->prepare($countSql);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $totalRecords = (int)$countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    $totalPages = (int)ceil($totalRecords / $limit);

    // 2. Fetch paginated records
    $dataSql = "SELECT s.student_id, s.user_id, s.full_name, s.email, s.enrollment_no,
                       s.course_name, s.department, s.semester, s.cgpa, s.is_deleted,
                       s.created_at, s.updated_at
                FROM students s
                {$whereSql}
                ORDER BY {$sortColumn} {$order}
                LIMIT ? OFFSET ?";

    $dataStmt = $db->prepare($dataSql);
    $dataTypes = $types . 'ii';
    $dataParams = array_merge($params, [$limit, $offset]);
    $dataStmt->bind_param($dataTypes, ...$dataParams);
    $dataStmt->execute();
    $students = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $dataStmt->close();

    // 3. Compute KPI summary statistics
    $kpiSql = "SELECT COUNT(*) AS total_active,
                      COALESCE(AVG(cgpa), 0) AS avg_cgpa,
                      COUNT(DISTINCT course_name) AS total_courses,
                      SUM(CASE WHEN is_deleted = 1 THEN 1 ELSE 0 END) AS total_deleted
               FROM students";
    $kpiResult = $db->query($kpiSql)->fetch_assoc();

    sendCrudJson(true, 'Students fetched successfully.', [
        'students'     => $students,
        'pagination'   => [
            'total'        => $totalRecords,
            'page'         => $page,
            'limit'        => $limit,
            'total_pages'  => max(1, $totalPages),
            'has_prev'     => $page > 1,
            'has_next'     => $page < $totalPages
        ],
        'kpi'          => [
            'total_active'   => (int)($kpiResult['total_active'] ?? 0),
            'avg_cgpa'       => round((float)($kpiResult['avg_cgpa'] ?? 0), 2),
            'total_courses'  => (int)($kpiResult['total_courses'] ?? 0),
            'total_deleted'  => (int)($kpiResult['total_deleted'] ?? 0),
        ]
    ]);
}

// ----------------------------------------------------------------------------
// ACTION 2: GET (Fetch single student by ID)
// ----------------------------------------------------------------------------
if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        sendCrudJson(false, 'Invalid student ID.', null, [], 400);
    }

    $stmt = $db->prepare('SELECT * FROM students WHERE student_id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$student) {
        sendCrudJson(false, 'Student not found.', null, [], 404);
    }

    sendCrudJson(true, 'Student retrieved successfully.', $student);
}

// ----------------------------------------------------------------------------
// CSRF Check for state-mutating actions (create, update, delete, restore)
// ----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!empty($_SESSION['csrf_token']) && !empty($token)) {
        if (!validateCsrfToken($token)) {
            sendCrudJson(false, 'CSRF security token mismatch. Please refresh and try again.', null, ['Invalid token'], 403);
        }
    }
}

// ----------------------------------------------------------------------------
// ACTION 3: CREATE (Insert new student)
// ----------------------------------------------------------------------------
if ($action === 'create') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendCrudJson(false, 'Only POST requests allowed for student creation.', null, [], 405);
    }

    $name       = trim(strip_tags((string)($_POST['full_name'] ?? '')));
    $email      = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_SANITIZE_EMAIL);
    $enrollment = strtoupper(trim(strip_tags((string)($_POST['enrollment_no'] ?? ''))));
    $course     = trim(strip_tags((string)($_POST['course_name'] ?? '')));
    $dept       = trim(strip_tags((string)($_POST['department'] ?? 'Computer Engineering')));
    $semester   = (int)($_POST['semester'] ?? 0);
    $cgpa       = (float)($_POST['cgpa'] ?? 0.0);

    $errors = [];
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors[] = 'Full name must be between 2 and 100 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (mb_strlen($enrollment) < 3 || mb_strlen($enrollment) > 30) {
        $errors[] = 'Enrollment number must be between 3 and 30 characters.';
    }
    if (empty($course)) {
        $errors[] = 'Course name is required.';
    }
    if ($semester < 1 || $semester > 10) {
        $errors[] = 'Semester must be between 1 and 10.';
    }
    if ($cgpa < 0.00 || $cgpa > 10.00) {
        $errors[] = 'CGPA must be between 0.00 and 10.00.';
    }

    if (!empty($errors)) {
        sendCrudJson(false, 'Validation failed. Please fix the errors.', null, $errors, 422);
    }

    // Check duplicate email or enrollment number
    $dupStmt = $db->prepare('SELECT student_id, email, enrollment_no FROM students WHERE email = ? OR enrollment_no = ? LIMIT 1');
    $dupStmt->bind_param('ss', $email, $enrollment);
    $dupStmt->execute();
    $existing = $dupStmt->get_result()->fetch_assoc();
    $dupStmt->close();

    if ($existing) {
        $msg = ($existing['email'] === $email)
            ? 'A student with this email already exists.'
            : 'A student with this enrollment number already exists.';
        sendCrudJson(false, $msg, null, [$msg], 409);
    }

    // Insert student
    $insertSql = 'INSERT INTO students (full_name, email, enrollment_no, course_name, department, semester, cgpa, is_deleted)
                  VALUES (?, ?, ?, ?, ?, ?, ?, 0)';
    $insertStmt = $db->prepare($insertSql);
    if (!$insertStmt) {
        sendCrudJson(false, 'Database insert prepare failed: ' . $db->error, null, [], 500);
    }

    $insertStmt->bind_param('sssssid', $name, $email, $enrollment, $course, $dept, $semester, $cgpa);
    $insertStmt->execute();
    $newId = (int)$db->insert_id;
    $insertStmt->close();

    sendCrudJson(true, "Student '{$name}' created successfully.", [
        'student_id'    => $newId,
        'full_name'     => $name,
        'email'         => $email,
        'enrollment_no' => $enrollment,
        'course_name'   => $course,
        'department'    => $dept,
        'semester'      => $semester,
        'cgpa'          => $cgpa
    ], [], 201);
}

// ----------------------------------------------------------------------------
// ACTION 4: UPDATE (Modify existing student)
// ----------------------------------------------------------------------------
if ($action === 'update') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendCrudJson(false, 'Only POST requests allowed for updates.', null, [], 405);
    }

    $id         = (int)($_POST['student_id'] ?? 0);
    $name       = trim(strip_tags((string)($_POST['full_name'] ?? '')));
    $email      = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_SANITIZE_EMAIL);
    $enrollment = strtoupper(trim(strip_tags((string)($_POST['enrollment_no'] ?? ''))));
    $course     = trim(strip_tags((string)($_POST['course_name'] ?? '')));
    $dept       = trim(strip_tags((string)($_POST['department'] ?? 'Computer Engineering')));
    $semester   = (int)($_POST['semester'] ?? 0);
    $cgpa       = (float)($_POST['cgpa'] ?? 0.0);

    if ($id <= 0) {
        sendCrudJson(false, 'Invalid student ID.', null, [], 400);
    }

    $errors = [];
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors[] = 'Full name must be between 2 and 100 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (mb_strlen($enrollment) < 3 || mb_strlen($enrollment) > 30) {
        $errors[] = 'Enrollment number must be between 3 and 30 characters.';
    }
    if (empty($course)) {
        $errors[] = 'Course name is required.';
    }
    if ($semester < 1 || $semester > 10) {
        $errors[] = 'Semester must be between 1 and 10.';
    }
    if ($cgpa < 0.00 || $cgpa > 10.00) {
        $errors[] = 'CGPA must be between 0.00 and 10.00.';
    }

    if (!empty($errors)) {
        sendCrudJson(false, 'Validation failed. Please fix the errors.', null, $errors, 422);
    }

    // Check duplicate email or enrollment on OTHER students
    $dupStmt = $db->prepare('SELECT student_id, email, enrollment_no FROM students WHERE (email = ? OR enrollment_no = ?) AND student_id != ? LIMIT 1');
    $dupStmt->bind_param('ssi', $email, $enrollment, $id);
    $dupStmt->execute();
    $existing = $dupStmt->get_result()->fetch_assoc();
    $dupStmt->close();

    if ($existing) {
        $msg = ($existing['email'] === $email)
            ? 'Another student with this email already exists.'
            : 'Another student with this enrollment number already exists.';
        sendCrudJson(false, $msg, null, [$msg], 409);
    }

    // Execute update
    $updateSql = 'UPDATE students
                  SET full_name = ?, email = ?, enrollment_no = ?, course_name = ?, department = ?, semester = ?, cgpa = ?
                  WHERE student_id = ?';
    $updateStmt = $db->prepare($updateSql);
    $updateStmt->bind_param('sssssidi', $name, $email, $enrollment, $course, $dept, $semester, $cgpa, $id);
    $updateStmt->execute();
    $affected = $updateStmt->affected_rows;
    $updateStmt->close();

    sendCrudJson(true, "Student #{$id} updated successfully.", [
        'student_id'    => $id,
        'full_name'     => $name,
        'email'         => $email,
        'enrollment_no' => $enrollment,
        'course_name'   => $course,
        'department'    => $dept,
        'semester'      => $semester,
        'cgpa'          => $cgpa
    ]);
}

// ----------------------------------------------------------------------------
// ACTION 5: DELETE (Soft-delete student)
// ----------------------------------------------------------------------------
if ($action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendCrudJson(false, 'Only POST requests allowed for deletion.', null, [], 405);
    }

    $id = (int)($_POST['student_id'] ?? 0);
    $permanent = !empty($_POST['permanent']) && $_POST['permanent'] === '1';

    if ($id <= 0) {
        sendCrudJson(false, 'Invalid student ID.', null, [], 400);
    }

    if ($permanent) {
        // Hard Delete
        $delStmt = $db->prepare('DELETE FROM students WHERE student_id = ?');
        $delStmt->bind_param('i', $id);
        $delStmt->execute();
        $delStmt->close();
        sendCrudJson(true, "Student #{$id} permanently deleted.");
    } else {
        // Soft Delete (Practical 11 standard)
        $delStmt = $db->prepare('UPDATE students SET is_deleted = 1, deleted_at = NOW() WHERE student_id = ?');
        $delStmt->bind_param('i', $id);
        $delStmt->execute();
        $delStmt->close();
        sendCrudJson(true, "Student #{$id} archived (soft-deleted).");
    }
}

// ----------------------------------------------------------------------------
// ACTION 6: RESTORE (Undo soft-delete)
// ----------------------------------------------------------------------------
if ($action === 'restore') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendCrudJson(false, 'Only POST requests allowed for restore.', null, [], 405);
    }

    $id = (int)($_POST['student_id'] ?? 0);
    if ($id <= 0) {
        sendCrudJson(false, 'Invalid student ID.', null, [], 400);
    }

    $resStmt = $db->prepare('UPDATE students SET is_deleted = 0, deleted_at = NULL WHERE student_id = ?');
    $resStmt->bind_param('i', $id);
    $resStmt->execute();
    $resStmt->close();

    sendCrudJson(true, "Student #{$id} restored successfully.");
}

sendCrudJson(false, 'Invalid action requested.', null, [], 400);
