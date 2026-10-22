<?php
declare(strict_types=1);

/**
 * ============================================================================
 * CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY (CHARUSAT)
 * FACULTY OF TECHNOLOGY AND ENGINEERING (FTE)
 * SUBJECT: WEB DEVELOPMENT FRAMEWORKS (ITUE203)
 * Practical 14: Admin Dashboard & Audit Log Controller
 * ============================================================================
 * File: admin_crud.php
 * Description: Backend Controller for Administrative Command Center:
 *              - System analytics & resource metrics
 *              - User management & RBAC role reassignment
 *              - Audit trail query, filtering, and retention governance
 *              - Role-Based Access Control guard strictly enforcing 'admin'
 *              - CSRF token validation on mutating actions
 * ============================================================================
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit.php';

initSession();

$currentUser = getCurrentUser();

/**
 * Helper to output structured JSON responses
 */
function sendAdminJson(bool $success, string $message, $data = null, array $errors = [], int $statusCode = 200): never
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

// Strict RBAC Enforcement: Only 'admin' role may access this controller
if ($currentUser === null || ($currentUser['role'] ?? '') !== 'admin') {
    sendAdminJson(false, 'Forbidden: Administrative credentials required.', null, ['Access Denied'], 403);
}

// Verify CSRF Token on mutating POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!empty($_SESSION['csrf_token']) && !empty($token)) {
        if (!validateCsrfToken($token)) {
            sendAdminJson(false, 'Security token expired or invalid. Please refresh the page.', null, ['CSRF token mismatch'], 403);
        }
    }
}

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? 'analytics'));
$db = getDatabaseConnection();

// ----------------------------------------------------------------------------
// ACTION 1: SYSTEM ANALYTICS & HEALTH METRICS
// ----------------------------------------------------------------------------
if ($action === 'analytics') {
    $analytics = getSystemAnalytics();
    sendAdminJson(true, 'System analytics retrieved.', $analytics, [], 200);
}

// ----------------------------------------------------------------------------
// ACTION 2: LIST USERS & ROLES
// ----------------------------------------------------------------------------
if ($action === 'list_users') {
    $search = trim((string)($_GET['search'] ?? ''));
    $role   = trim((string)($_GET['role'] ?? ''));
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = max(1, min(50, (int)($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    $where = [];
    $params = [];
    $types = '';

    if ($search !== '') {
        $where[] = '(full_name LIKE ? OR email LIKE ?)';
        $wild = '%' . $search . '%';
        $params[] = $wild;
        $params[] = $wild;
        $types .= 'ss';
    }

    if ($role !== '' && $role !== 'all') {
        $where[] = 'role = ?';
        $params[] = $role;
        $types .= 's';
    }

    $whereSql = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

    $countSql = "SELECT COUNT(*) AS total FROM users {$whereSql}";
    $countStmt = $db->prepare($countSql);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $totalRecords = (int)$countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    $totalPages = (int)ceil($totalRecords / $limit);

    $sql = "SELECT user_id, full_name, email, role, course, last_login_at, created_at
            FROM users
            {$whereSql}
            ORDER BY user_id DESC
            LIMIT ? OFFSET ?";

    $stmt = $db->prepare($sql);
    $dataTypes = $types . 'ii';
    $dataParams = array_merge($params, [$limit, $offset]);
    $stmt->bind_param($dataTypes, ...$dataParams);
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    sendAdminJson(true, 'Users retrieved successfully.', [
        'users'      => $users,
        'pagination' => [
            'total_records' => $totalRecords,
            'total_pages'   => $totalPages,
            'current_page'  => $page,
            'limit'         => $limit
        ]
    ], [], 200);
}

// ----------------------------------------------------------------------------
// ACTION 3: UPDATE USER ROLE (RBAC MANAGEMENT)
// ----------------------------------------------------------------------------
if ($action === 'update_role') {
    $targetUserId = (int)($_POST['user_id'] ?? 0);
    $newRole      = trim((string)($_POST['new_role'] ?? ''));

    $validRoles = ['student', 'teacher', 'admin'];
    if ($targetUserId <= 0) {
        sendAdminJson(false, 'Invalid user ID.', null, ['user_id required'], 400);
    }
    if (!in_array($newRole, $validRoles, true)) {
        sendAdminJson(false, 'Invalid role specified.', null, ['Valid roles: student, teacher, admin'], 422);
    }

    // Fetch existing target user
    $chk = $db->prepare('SELECT user_id, full_name, email, role FROM users WHERE user_id = ? LIMIT 1');
    $chk->bind_param('i', $targetUserId);
    $chk->execute();
    $targetUser = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$targetUser) {
        sendAdminJson(false, 'Target user not found.', null, ['Not found'], 404);
    }

    $oldRole = $targetUser['role'];
    if ($oldRole === $newRole) {
        sendAdminJson(true, "User already has the role '{$newRole}'.", $targetUser, [], 200);
    }

    // Safety rule: Prevent demoting the last remaining admin
    if ($oldRole === 'admin' && $newRole !== 'admin') {
        $admCountRes = $db->query("SELECT COUNT(*) AS total_admins FROM users WHERE role = 'admin'");
        $totalAdmins = $admCountRes ? (int)$admCountRes->fetch_assoc()['total_admins'] : 1;
        if ($totalAdmins <= 1) {
            sendAdminJson(false, 'Security Safety Block: Cannot demote the last remaining administrator on the system.', null, ['Last admin guard'], 409);
        }
    }

    $stmt = $db->prepare('UPDATE users SET role = ? WHERE user_id = ?');
    $stmt->bind_param('si', $newRole, $targetUserId);
    $stmt->execute();
    $stmt->close();

    // Record in Audit Trail
    logAuditEvent(
        'ROLE_CHANGE',
        (int)$currentUser['id'],
        "Changed role of user #{$targetUserId} ({$targetUser['email']}) from '{$oldRole}' to '{$newRole}'.",
        'warning'
    );

    sendAdminJson(true, "Role for '{$targetUser['full_name']}' successfully updated from '{$oldRole}' to '{$newRole}'.", [
        'user_id'  => $targetUserId,
        'old_role' => $oldRole,
        'new_role' => $newRole
    ], [], 200);
}

// ----------------------------------------------------------------------------
// ACTION 4: LIST AUDIT TRAIL LOGS
// ----------------------------------------------------------------------------
if ($action === 'list_audit_logs') {
    $search   = trim((string)($_GET['search'] ?? ''));
    $severity = trim((string)($_GET['severity'] ?? ''));
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $limit    = max(1, min(100, (int)($_GET['limit'] ?? 15)));
    $offset   = ($page - 1) * $limit;

    $where = [];
    $params = [];
    $types = '';

    if ($search !== '') {
        $where[] = '(a.action LIKE ? OR a.details LIKE ? OR a.ip_address LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
        $wild = '%' . $search . '%';
        $params[] = $wild;
        $params[] = $wild;
        $params[] = $wild;
        $params[] = $wild;
        $params[] = $wild;
        $types .= 'sssss';
    }

    if ($severity !== '' && $severity !== 'all') {
        $where[] = 'a.severity = ?';
        $params[] = $severity;
        $types .= 's';
    }

    $whereSql = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

    $countSql = "SELECT COUNT(*) AS total FROM audit_logs a LEFT JOIN users u ON a.user_id = u.user_id {$whereSql}";
    $countStmt = $db->prepare($countSql);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $totalRecords = (int)$countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    $totalPages = (int)ceil($totalRecords / $limit);

    $sql = "SELECT a.log_id, a.user_id, a.action, a.severity, a.ip_address, a.user_agent, a.details, a.created_at,
                   COALESCE(u.full_name, 'System / Guest') AS user_name,
                   COALESCE(u.email, 'system@charusat.edu.in') AS user_email,
                   COALESCE(u.role, 'system') AS user_role
            FROM audit_logs a
            LEFT JOIN users u ON a.user_id = u.user_id
            {$whereSql}
            ORDER BY a.log_id DESC
            LIMIT ? OFFSET ?";

    $stmt = $db->prepare($sql);
    $dataTypes = $types . 'ii';
    $dataParams = array_merge($params, [$limit, $offset]);
    $stmt->bind_param($dataTypes, ...$dataParams);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    sendAdminJson(true, 'Audit logs retrieved.', [
        'logs'       => $logs,
        'pagination' => [
            'total_records' => $totalRecords,
            'total_pages'   => $totalPages,
            'current_page'  => $page,
            'limit'         => $limit
        ]
    ], [], 200);
}

// ----------------------------------------------------------------------------
// ACTION 5: CLEAR / PURGE AUDIT LOGS (RETENTION POLICY)
// ----------------------------------------------------------------------------
if ($action === 'clear_audit_logs') {
    $days = max(1, (int)($_POST['days'] ?? 30));

    $stmt = $db->prepare('DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)');
    $stmt->bind_param('i', $days);
    $stmt->execute();
    $deletedCount = $stmt->affected_rows;
    $stmt->close();

    logAuditEvent(
        'AUDIT_PURGE',
        (int)$currentUser['id'],
        "Purged {$deletedCount} audit logs older than {$days} days under retention policy.",
        'warning'
    );

    sendAdminJson(true, "Successfully purged {$deletedCount} audit logs older than {$days} days.", [
        'purged_count' => $deletedCount,
        'days'         => $days
    ], [], 200);
}

sendAdminJson(false, 'Invalid action specified.', null, [], 400);
