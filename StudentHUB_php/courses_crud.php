<?php
declare(strict_types=1);

/**
 * ============================================================================
 * CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY (CHARUSAT)
 * FACULTY OF TECHNOLOGY AND ENGINEERING (FTE)
 * SUBJECT: WEB DEVELOPMENT FRAMEWORKS (ITUE203)
 * ============================================================================
 * File: courses_crud.php
 * Description: Controller for Academic Courses Management.
 *              Allows Faculty (teachers) and Admins to create, view, update,
 *              and delete courses taught in the department using prepared statements.
 * ============================================================================
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

initSession();

$currentUser = getCurrentUser();

/**
 * Detect AJAX / Fetch request
 */
function isAjax(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhr = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    return $xhr === 'xmlhttprequest' || str_contains($accept, 'application/json');
}

/**
 * Send structured JSON response
 */
function sendCourseJson(bool $success, string $message, $data = null, array $errors = [], int $statusCode = 200): never
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
// ACTION 1: LIST COURSES (Read all / Filtered by faculty, dept, sem, search)
// ----------------------------------------------------------------------------
if ($action === 'list') {
    $facultyId  = isset($_GET['faculty_id']) && $_GET['faculty_id'] !== '' ? (int)$_GET['faculty_id'] : null;
    $department = trim((string)($_GET['department'] ?? ''));
    $semester   = isset($_GET['semester']) && $_GET['semester'] !== '' ? (int)$_GET['semester'] : null;
    $search     = trim((string)($_GET['search'] ?? ''));

    $where = [];
    $params = [];
    $types = '';

    if ($facultyId !== null && $facultyId > 0) {
        $where[] = 'c.faculty_id = ?';
        $params[] = $facultyId;
        $types .= 'i';
    }

    if ($department !== '') {
        $where[] = 'c.department = ?';
        $params[] = $department;
        $types .= 's';
    }

    if ($semester !== null && $semester >= 1 && $semester <= 10) {
        $where[] = 'c.semester = ?';
        $params[] = $semester;
        $types .= 'i';
    }

    if ($search !== '') {
        $where[] = '(c.course_code LIKE ? OR c.course_title LIKE ? OR c.description LIKE ?)';
        $wild = '%' . $search . '%';
        $params[] = $wild;
        $params[] = $wild;
        $params[] = $wild;
        $types .= 'sss';
    }

    $whereSql = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT c.course_id, c.course_code, c.course_title, c.department,
                   c.faculty_id, c.credits, c.semester, c.description, c.thumbnail_url,
                   c.created_at, c.updated_at,
                   COALESCE(u.full_name, 'Department Faculty') AS faculty_name,
                   COALESCE(u.email, 'faculty@charusat.edu.in') AS faculty_email,
                   (SELECT COUNT(*) FROM students s WHERE s.course_name = c.course_title OR s.course_name LIKE CONCAT('%', c.course_title, '%')) AS enrolled_count
            FROM courses c
            LEFT JOIN users u ON c.faculty_id = u.user_id
            {$whereSql}
            ORDER BY c.semester ASC, c.course_code ASC";

    $stmt = $db->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    sendCourseJson(true, 'Courses retrieved successfully.', [
        'courses' => $courses,
        'total'   => count($courses)
    ]);
}

// ----------------------------------------------------------------------------
// ACTION 2: GET SINGLE COURSE BY ID
// ----------------------------------------------------------------------------
if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        sendCourseJson(false, 'Invalid course ID.', null, [], 400);
    }

    $stmt = $db->prepare(
        "SELECT c.*, COALESCE(u.full_name, 'Unassigned') AS faculty_name
         FROM courses c
         LEFT JOIN users u ON c.faculty_id = u.user_id
         WHERE c.course_id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$course) {
        sendCourseJson(false, 'Course not found.', null, [], 404);
    }

    sendCourseJson(true, 'Course details fetched.', $course);
}

// ----------------------------------------------------------------------------
// Guard for Mutating Actions (Create, Update, Delete)
// Only Teachers and Admins are permitted!
// ----------------------------------------------------------------------------
if ($currentUser === null || !in_array($currentUser['role'] ?? '', ['teacher', 'admin'], true)) {
    sendCourseJson(false, 'Unauthorized. Only faculty and administrators can modify course records.', null, [], 403);
}

// Verify CSRF Token on POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!empty($_SESSION['csrf_token']) && !empty($token)) {
        if (!validateCsrfToken($token)) {
            sendCourseJson(false, 'Security token expired or invalid. Please refresh the page.', null, ['CSRF token mismatch'], 403);
        }
    }
}

// ----------------------------------------------------------------------------
// ACTION 3: CREATE COURSE (Faculty / Admin Only)
// ----------------------------------------------------------------------------
if ($action === 'create') {
    $code        = strtoupper(trim(strip_tags((string)($_POST['course_code'] ?? ''))));
    $title       = trim(strip_tags((string)($_POST['course_title'] ?? '')));
    $dept        = trim(strip_tags((string)($_POST['department'] ?? 'Computer Engineering')));
    $credits     = (int)($_POST['credits'] ?? 4);
    $semester    = (int)($_POST['semester'] ?? 3);
    $description = trim(strip_tags((string)($_POST['description'] ?? '')));
    $facultyId   = isset($_POST['faculty_id']) && $_POST['faculty_id'] !== '' ? (int)$_POST['faculty_id'] : ($currentUser['id'] ?? 2);
    $thumb       = trim((string)($_POST['thumbnail_url'] ?? '../img/web-development.jpg'));

    $errors = [];
    if (mb_strlen($code) < 3 || mb_strlen($code) > 20) {
        $errors[] = 'Course Code must be between 3 and 20 characters (e.g. IT203).';
    }
    if (mb_strlen($title) < 3 || mb_strlen($title) > 150) {
        $errors[] = 'Course Title must be between 3 and 150 characters.';
    }
    if ($credits < 1 || $credits > 8) {
        $errors[] = 'Credits must be between 1 and 8.';
    }
    if ($semester < 1 || $semester > 10) {
        $errors[] = 'Semester must be between 1 and 10.';
    }

    if (!empty($errors)) {
        sendCourseJson(false, 'Validation failed.', null, $errors, 422);
    }

    // Check unique course code
    $chk = $db->prepare('SELECT course_id FROM courses WHERE course_code = ? LIMIT 1');
    $chk->bind_param('s', $code);
    $chk->execute();
    if ($chk->get_result()->fetch_assoc()) {
        $chk->close();
        sendCourseJson(false, "Course code '{$code}' already exists. Please choose a unique code.", null, ['Duplicate course code'], 409);
    }
    $chk->close();

    $stmt = $db->prepare(
        'INSERT INTO courses (course_code, course_title, department, faculty_id, credits, semester, description, thumbnail_url)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        sendCourseJson(false, 'Database insert error: ' . $db->error, null, [], 500);
    }

    $stmt->bind_param('sssiisss', $code, $title, $dept, $facultyId, $credits, $semester, $description, $thumb);
    $stmt->execute();
    $newId = (int)$db->insert_id;
    $stmt->close();

    sendCourseJson(true, "Course '{$title}' ({$code}) created successfully.", [
        'course_id'    => $newId,
        'course_code'  => $code,
        'course_title' => $title,
        'department'   => $dept,
        'credits'      => $credits,
        'semester'     => $semester
    ], [], 201);
}

// ----------------------------------------------------------------------------
// ACTION 4: UPDATE COURSE (Faculty / Admin Only)
// ----------------------------------------------------------------------------
if ($action === 'update') {
    $id          = (int)($_POST['course_id'] ?? 0);
    $code        = strtoupper(trim(strip_tags((string)($_POST['course_code'] ?? ''))));
    $title       = trim(strip_tags((string)($_POST['course_title'] ?? '')));
    $dept        = trim(strip_tags((string)($_POST['department'] ?? 'Computer Engineering')));
    $credits     = (int)($_POST['credits'] ?? 4);
    $semester    = (int)($_POST['semester'] ?? 3);
    $description = trim(strip_tags((string)($_POST['description'] ?? '')));
    $facultyId   = isset($_POST['faculty_id']) && $_POST['faculty_id'] !== '' ? (int)$_POST['faculty_id'] : null;

    if ($id <= 0) {
        sendCourseJson(false, 'Invalid course ID.', null, [], 400);
    }

    $errors = [];
    if (mb_strlen($code) < 3 || mb_strlen($code) > 20) {
        $errors[] = 'Course Code must be between 3 and 20 characters.';
    }
    if (mb_strlen($title) < 3 || mb_strlen($title) > 150) {
        $errors[] = 'Course Title must be between 3 and 150 characters.';
    }
    if ($credits < 1 || $credits > 8) {
        $errors[] = 'Credits must be between 1 and 8.';
    }
    if ($semester < 1 || $semester > 10) {
        $errors[] = 'Semester must be between 1 and 10.';
    }

    if (!empty($errors)) {
        sendCourseJson(false, 'Validation failed.', null, $errors, 422);
    }

    // Check duplicate code on OTHER courses
    $chk = $db->prepare('SELECT course_id FROM courses WHERE course_code = ? AND course_id != ? LIMIT 1');
    $chk->bind_param('si', $code, $id);
    $chk->execute();
    if ($chk->get_result()->fetch_assoc()) {
        $chk->close();
        sendCourseJson(false, "Course code '{$code}' is already used by another course.", null, ['Duplicate course code'], 409);
    }
    $chk->close();

    $stmt = $db->prepare(
        'UPDATE courses
         SET course_code = ?, course_title = ?, department = ?, faculty_id = ?, credits = ?, semester = ?, description = ?
         WHERE course_id = ?'
    );
    $stmt->bind_param('sssiissi', $code, $title, $dept, $facultyId, $credits, $semester, $description, $id);
    $stmt->execute();
    $stmt->close();

    sendCourseJson(true, "Course '{$title}' updated successfully.", [
        'course_id'    => $id,
        'course_code'  => $code,
        'course_title' => $title,
        'credits'      => $credits,
        'semester'     => $semester,
        'department'   => $dept
    ]);
}

// ----------------------------------------------------------------------------
// ACTION 5: DELETE COURSE
// ----------------------------------------------------------------------------
if ($action === 'delete') {
    $id = (int)($_POST['course_id'] ?? 0);
    if ($id <= 0) {
        sendCourseJson(false, 'Invalid course ID.', null, [], 400);
    }

    $stmt = $db->prepare('DELETE FROM courses WHERE course_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    sendCourseJson(true, "Course #{$id} deleted successfully.");
}

sendCourseJson(false, 'Invalid action specified.', null, [], 400);
