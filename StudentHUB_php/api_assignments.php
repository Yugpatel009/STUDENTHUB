<?php
declare(strict_types=1);

/**
 * ============================================================================
 * CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY (CHARUSAT)
 * FACULTY OF TECHNOLOGY AND ENGINEERING (FTE)
 * SUBJECT: WEB DEVELOPMENT FRAMEWORKS (ITUE203)
 * Practical 13: RESTful PHP JSON API and AJAX/Fetch-Based CRUD
 * ============================================================================
 * File: api_assignments.php
 * Description: RESTful JSON API endpoint supporting full CRUD operations:
 *              - GET: List assignments with pagination, search, and filtering
 *              - GET (with id): Retrieve single assignment details
 *              - POST: Create new assignment (Faculty/Admin) OR Submit solution (Student)
 *              - PUT / PATCH: Update assignment details (Faculty/Admin)
 *              - DELETE: Remove assignment and its submissions (Faculty/Admin)
 *              Standard HTTP status codes: 200, 201, 400, 401, 403, 404, 409, 422
 * ============================================================================
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/db.php';

initSession();

$currentUser = getCurrentUser();
$db = getDatabaseConnection();

/**
 * Helper to emit structured JSON response with proper HTTP status
 */
function sendRestJson(bool $success, string $message, $data = null, array $errors = [], int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode([
        'success'   => $success,
        'message'   => $message,
        'data'      => $data,
        'errors'    => $errors,
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Determine HTTP Request Method (supports method override via header or _method param)
$requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$overrideMethod = strtoupper((string)($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_POST['_method'] ?? $_GET['_method'] ?? ''));
if (!empty($overrideMethod) && in_array($overrideMethod, ['PUT', 'PATCH', 'DELETE', 'GET', 'POST'], true)) {
    $requestMethod = $overrideMethod;
}

// Parse request payload (supports JSON body and form-data)
$rawInput = file_get_contents('php://input');
$jsonBody = [];
if (!empty($rawInput)) {
    $parsed = json_decode($rawInput, true);
    if (is_array($parsed)) {
        $jsonBody = $parsed;
    }
}
$input = array_merge($_GET, $_POST, $jsonBody);

$action = trim((string)($input['action'] ?? ''));

// ============================================================================
// 1. GET REQUESTS: LIST OR SINGLE RETRIEVAL
// ============================================================================
if ($requestMethod === 'GET') {
    // Single Assignment by ID
    if (!empty($input['id'])) {
        $id = (int)$input['id'];
        $stmt = $db->prepare(
            "SELECT a.*, c.course_code, c.course_title, c.department,
                    COALESCE(u.full_name, 'Course Instructor') AS instructor_name,
                    (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.assignment_id) AS total_submissions
             FROM assignments a
             JOIN courses c ON a.course_id = c.course_id
             JOIN users u ON a.created_by = u.user_id
             WHERE a.assignment_id = ? LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $assignment = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$assignment) {
            sendRestJson(false, "Assignment #{$id} not found.", null, ['Resource not found'], 404);
        }

        // If logged-in user is a student, attach their specific submission status
        $studentSubmission = null;
        if ($currentUser && ($currentUser['role'] ?? '') === 'student') {
            $userId = (int)$currentUser['id'];
            $subStmt = $db->prepare(
                "SELECT s.* FROM submissions s
                 JOIN students st ON s.student_id = st.student_id
                 WHERE s.assignment_id = ? AND (st.user_id = ? OR st.email = ?) LIMIT 1"
            );
            $userEmail = (string)($currentUser['email'] ?? '');
            $subStmt->bind_param('iis', $id, $userId, $userEmail);
            $subStmt->execute();
            $studentSubmission = $subStmt->get_result()->fetch_assoc();
            $subStmt->close();
        }

        $assignment['my_submission'] = $studentSubmission;
        sendRestJson(true, 'Assignment details retrieved.', $assignment, [], 200);
    }

    // List Assignments with Filtering, Search, and Pagination
    $search   = trim((string)($input['search'] ?? ''));
    $courseId = isset($input['course_id']) && $input['course_id'] !== '' ? (int)$input['course_id'] : null;
    $status   = trim((string)($input['status'] ?? ''));
    $page     = max(1, (int)($input['page'] ?? 1));
    $limit    = max(1, min(50, (int)($input['limit'] ?? 10)));
    $offset   = ($page - 1) * $limit;

    $where = [];
    $params = [];
    $types = '';

    if ($search !== '') {
        $where[] = '(a.title LIKE ? OR a.description LIKE ? OR c.course_title LIKE ? OR c.course_code LIKE ?)';
        $wild = '%' . $search . '%';
        $params[] = $wild;
        $params[] = $wild;
        $params[] = $wild;
        $params[] = $wild;
        $types .= 'ssss';
    }

    if ($courseId !== null && $courseId > 0) {
        $where[] = 'a.course_id = ?';
        $params[] = $courseId;
        $types .= 'i';
    }

    if ($status !== '' && $status !== 'all') {
        $where[] = 'a.status = ?';
        $params[] = $status;
        $types .= 's';
    }

    $whereSql = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

    // Count matching
    $countSql = "SELECT COUNT(*) AS total FROM assignments a JOIN courses c ON a.course_id = c.course_id {$whereSql}";
    $countStmt = $db->prepare($countSql);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $totalRecords = (int)$countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    $totalPages = (int)ceil($totalRecords / $limit);

    // Fetch data slice
    $sql = "SELECT a.assignment_id, a.course_id, a.title, a.description, a.due_date,
                   a.max_marks, a.created_by, a.status, a.created_at,
                   c.course_code, c.course_title, c.department,
                   COALESCE(u.full_name, 'Instructor') AS instructor_name,
                   (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.assignment_id) AS submissions_count
            FROM assignments a
            JOIN courses c ON a.course_id = c.course_id
            JOIN users u ON a.created_by = u.user_id
            {$whereSql}
            ORDER BY a.due_date ASC
            LIMIT ? OFFSET ?";

    $dataStmt = $db->prepare($sql);
    $dataTypes = $types . 'ii';
    $dataParams = array_merge($params, [$limit, $offset]);
    $dataStmt->bind_param($dataTypes, ...$dataParams);
    $dataStmt->execute();
    $assignments = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $dataStmt->close();

    // Attach student submission status if logged in as student
    if ($currentUser && ($currentUser['role'] ?? '') === 'student') {
        $userId = (int)$currentUser['id'];
        $userEmail = (string)($currentUser['email'] ?? '');

        // Fetch student ID
        $stRes = $db->prepare('SELECT student_id FROM students WHERE user_id = ? OR email = ? LIMIT 1');
        $stRes->bind_param('is', $userId, $userEmail);
        $stRes->execute();
        $studentRow = $stRes->get_result()->fetch_assoc();
        $stRes->close();

        $studentId = $studentRow ? (int)$studentRow['student_id'] : 0;

        foreach ($assignments as &$asgn) {
            $asgn['student_status'] = 'Pending';
            $asgn['submission_data'] = null;

            if ($studentId > 0) {
                $subChk = $db->prepare('SELECT submission_id, status, marks_obtained, submitted_at FROM submissions WHERE assignment_id = ? AND student_id = ? LIMIT 1');
                $subChk->bind_param('ii', $asgn['assignment_id'], $studentId);
                $subChk->execute();
                $subData = $subChk->get_result()->fetch_assoc();
                $subChk->close();

                if ($subData) {
                    $asgn['student_status'] = ucfirst($subData['status']);
                    $asgn['submission_data'] = $subData;
                }
            }
        }
        unset($asgn);
    }

    // KPI Metrics
    $kpiRes = $db->query(
        "SELECT COUNT(*) AS total_assignments,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_assignments,
                (SELECT COUNT(*) FROM submissions) AS total_submissions
         FROM assignments"
    );
    $kpi = $kpiRes ? $kpiRes->fetch_assoc() : ['total_assignments' => 0, 'active_assignments' => 0, 'total_submissions' => 0];

    sendRestJson(true, 'Assignments retrieved successfully.', [
        'assignments' => $assignments,
        'pagination'  => [
            'total_records' => $totalRecords,
            'total_pages'   => $totalPages,
            'current_page'  => $page,
            'limit'         => $limit
        ],
        'kpi' => $kpi
    ], [], 200);
}

// ============================================================================
// 2. POST REQUEST: CREATE ASSIGNMENT OR SUBMIT WORK
// ============================================================================
if ($requestMethod === 'POST' && $action !== 'update' && $action !== 'delete') {
    // Sub-Action: Student Submission
    if ($action === 'submit') {
        if ($currentUser === null) {
            sendRestJson(false, 'Authentication required to submit assignments.', null, ['Unauthorized'], 401);
        }

        $assignmentId   = (int)($input['assignment_id'] ?? 0);
        $submissionText = trim(strip_tags((string)($input['submission_text'] ?? '')));

        if ($assignmentId <= 0) {
            sendRestJson(false, 'Invalid assignment ID.', null, ['assignment_id required'], 400);
        }
        if (mb_strlen($submissionText) < 5) {
            sendRestJson(false, 'Please provide assignment submission details or repository URL (min 5 characters).', null, ['submission_text too short'], 422);
        }

        // Verify assignment exists and is active
        $chkAsgn = $db->prepare('SELECT assignment_id, title, due_date, status FROM assignments WHERE assignment_id = ? LIMIT 1');
        $chkAsgn->bind_param('i', $assignmentId);
        $chkAsgn->execute();
        $targetAsgn = $chkAsgn->get_result()->fetch_assoc();
        $chkAsgn->close();

        if (!$targetAsgn) {
            sendRestJson(false, 'Assignment not found.', null, ['Not found'], 404);
        }

        // Resolve student ID
        $userId = (int)$currentUser['id'];
        $userEmail = (string)($currentUser['email'] ?? '');
        $stRes = $db->prepare('SELECT student_id FROM students WHERE user_id = ? OR email = ? LIMIT 1');
        $stRes->bind_param('is', $userId, $userEmail);
        $stRes->execute();
        $studentRow = $stRes->get_result()->fetch_assoc();
        $stRes->close();

        if (!$studentRow) {
            sendRestJson(false, 'Student profile not found. Please contact your coordinator.', null, ['Profile missing'], 404);
        }
        $studentId = (int)$studentRow['student_id'];

        $isLate = strtotime('now') > strtotime($targetAsgn['due_date']);
        $subStatus = $isLate ? 'late' : 'submitted';

        // Upsert submission
        $subStmt = $db->prepare(
            'INSERT INTO submissions (assignment_id, student_id, submission_text, status, submitted_at)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE submission_text = VALUES(submission_text), status = VALUES(status), submitted_at = NOW()'
        );
        $subStmt->bind_param('iiss', $assignmentId, $studentId, $submissionText, $subStatus);
        $subStmt->execute();
        $newSubId = (int)$db->insert_id;
        $subStmt->close();

        sendRestJson(true, "Assignment '{$targetAsgn['title']}' submitted successfully" . ($isLate ? ' (marked Late).' : '.'), [
            'assignment_id'   => $assignmentId,
            'student_id'      => $studentId,
            'status'          => $subStatus,
            'submission_text' => $submissionText
        ], [], 201);
    }

    // Default POST: Create New Assignment (Teacher / Admin Only)
    if ($currentUser === null || !in_array($currentUser['role'] ?? '', ['teacher', 'admin'], true)) {
        sendRestJson(false, 'Forbidden: Only faculty and administrators can create assignments.', null, ['Insufficient permissions'], 403);
    }

    // Verify CSRF
    $token = (string)($input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!empty($_SESSION['csrf_token']) && !empty($token)) {
        if (!validateCsrfToken($token)) {
            sendRestJson(false, 'Security token expired or invalid. Please refresh the page.', null, ['CSRF token mismatch'], 403);
        }
    }

    $courseId = (int)($input['course_id'] ?? 0);
    $title    = trim(strip_tags((string)($input['title'] ?? '')));
    $desc     = trim(strip_tags((string)($input['description'] ?? '')));
    $dueDate  = trim((string)($input['due_date'] ?? ''));
    $maxMarks = (int)($input['max_marks'] ?? 100);
    $status   = trim((string)($input['status'] ?? 'active'));

    $errors = [];
    if ($courseId <= 0) {
        $errors[] = 'Please select a valid academic course.';
    }
    if (mb_strlen($title) < 3 || mb_strlen($title) > 150) {
        $errors[] = 'Assignment Title must be between 3 and 150 characters.';
    }
    if (empty($dueDate) || strtotime($dueDate) === false) {
        $errors[] = 'Please provide a valid due date and time.';
    }
    if ($maxMarks < 1 || $maxMarks > 1000) {
        $errors[] = 'Maximum marks must be between 1 and 1000.';
    }
    if (!in_array($status, ['active', 'closed', 'archived'], true)) {
        $errors[] = 'Invalid assignment status.';
    }

    if (!empty($errors)) {
        sendRestJson(false, 'Validation failed. Please correct input fields.', null, $errors, 422);
    }

    // Verify course exists
    $chkCourse = $db->prepare('SELECT course_id, course_title FROM courses WHERE course_id = ? LIMIT 1');
    $chkCourse->bind_param('i', $courseId);
    $chkCourse->execute();
    $courseRow = $chkCourse->get_result()->fetch_assoc();
    $chkCourse->close();

    if (!$courseRow) {
        sendRestJson(false, 'The selected course does not exist.', null, ['Invalid course_id'], 404);
    }

    $mysqlDueDate = date('Y-m-d H:i:s', strtotime($dueDate));
    $createdBy = (int)($currentUser['id'] ?? 2);

    $stmt = $db->prepare(
        'INSERT INTO assignments (course_id, title, description, due_date, max_marks, created_by, status)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        sendRestJson(false, 'Database insert error: ' . $db->error, null, [], 500);
    }

    $stmt->bind_param('isssiis', $courseId, $title, $desc, $mysqlDueDate, $maxMarks, $createdBy, $status);
    $stmt->execute();
    $newId = (int)$db->insert_id;
    $stmt->close();

    sendRestJson(true, "Assignment '{$title}' created successfully.", [
        'assignment_id' => $newId,
        'course_id'     => $courseId,
        'course_title'  => $courseRow['course_title'],
        'title'         => $title,
        'description'   => $desc,
        'due_date'      => $mysqlDueDate,
        'max_marks'     => $maxMarks,
        'status'        => $status
    ], [], 201);
}

// ============================================================================
// 3. PUT / PATCH REQUEST: UPDATE ASSIGNMENT
// ============================================================================
if ($requestMethod === 'PUT' || $action === 'update') {
    if ($currentUser === null || !in_array($currentUser['role'] ?? '', ['teacher', 'admin'], true)) {
        sendRestJson(false, 'Forbidden: Only faculty and administrators can update assignments.', null, ['Insufficient permissions'], 403);
    }

    $id       = (int)($input['assignment_id'] ?? $input['id'] ?? 0);
    $courseId = (int)($input['course_id'] ?? 0);
    $title    = trim(strip_tags((string)($input['title'] ?? '')));
    $desc     = trim(strip_tags((string)($input['description'] ?? '')));
    $dueDate  = trim((string)($input['due_date'] ?? ''));
    $maxMarks = (int)($input['max_marks'] ?? 100);
    $status   = trim((string)($input['status'] ?? 'active'));

    if ($id <= 0) {
        sendRestJson(false, 'Invalid assignment ID provided for update.', null, ['id required'], 400);
    }

    // Verify assignment exists
    $chk = $db->prepare('SELECT assignment_id FROM assignments WHERE assignment_id = ? LIMIT 1');
    $chk->bind_param('i', $id);
    $chk->execute();
    if (!$chk->get_result()->fetch_assoc()) {
        $chk->close();
        sendRestJson(false, 'Assignment not found.', null, ['Not found'], 404);
    }
    $chk->close();

    $errors = [];
    if ($courseId <= 0) {
        $errors[] = 'Please select a valid academic course.';
    }
    if (mb_strlen($title) < 3 || mb_strlen($title) > 150) {
        $errors[] = 'Title must be between 3 and 150 characters.';
    }
    if (empty($dueDate) || strtotime($dueDate) === false) {
        $errors[] = 'Invalid due date format.';
    }
    if ($maxMarks < 1 || $maxMarks > 1000) {
        $errors[] = 'Maximum marks must be between 1 and 1000.';
    }
    if (!in_array($status, ['active', 'closed', 'archived'], true)) {
        $errors[] = 'Invalid status.';
    }

    if (!empty($errors)) {
        sendRestJson(false, 'Validation failed. Please correct input fields.', null, $errors, 422);
    }

    $mysqlDueDate = date('Y-m-d H:i:s', strtotime($dueDate));

    $stmt = $db->prepare(
        'UPDATE assignments
         SET course_id = ?, title = ?, description = ?, due_date = ?, max_marks = ?, status = ?
         WHERE assignment_id = ?'
    );
    $stmt->bind_param('isssisi', $courseId, $title, $desc, $mysqlDueDate, $maxMarks, $status, $id);
    $stmt->execute();
    $stmt->close();

    sendRestJson(true, "Assignment '{$title}' updated successfully.", [
        'assignment_id' => $id,
        'course_id'     => $courseId,
        'title'         => $title,
        'description'   => $desc,
        'due_date'      => $mysqlDueDate,
        'max_marks'     => $maxMarks,
        'status'        => $status
    ], [], 200);
}

// ============================================================================
// 4. DELETE REQUEST: REMOVE ASSIGNMENT
// ============================================================================
if ($requestMethod === 'DELETE' || $action === 'delete') {
    if ($currentUser === null || !in_array($currentUser['role'] ?? '', ['teacher', 'admin'], true)) {
        sendRestJson(false, 'Forbidden: Only faculty and administrators can delete assignments.', null, ['Insufficient permissions'], 403);
    }

    $id = (int)($input['assignment_id'] ?? $input['id'] ?? 0);
    if ($id <= 0) {
        sendRestJson(false, 'Invalid assignment ID provided for deletion.', null, ['id required'], 400);
    }

    $chk = $db->prepare('SELECT assignment_id, title FROM assignments WHERE assignment_id = ? LIMIT 1');
    $chk->bind_param('i', $id);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$row) {
        sendRestJson(false, 'Assignment not found or already deleted.', null, ['Not found'], 404);
    }

    // Delete (cascade handles submissions)
    $del = $db->prepare('DELETE FROM assignments WHERE assignment_id = ?');
    $del->bind_param('i', $id);
    $del->execute();
    $del->close();

    sendRestJson(true, "Assignment '{$row['title']}' (#{$id}) was deleted successfully.", [
        'deleted_id' => $id
    ], [], 200);
}

sendRestJson(false, 'Invalid request method or unsupported action.', null, ['Method not allowed'], 405);
