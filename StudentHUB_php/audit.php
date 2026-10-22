<?php
declare(strict_types=1);

/**
 * ============================================================================
 * CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY (CHARUSAT)
 * FACULTY OF TECHNOLOGY AND ENGINEERING (FTE)
 * SUBJECT: WEB DEVELOPMENT FRAMEWORKS (ITUE203)
 * Practical 14: Audit Logging Engine
 * ============================================================================
 * File: audit.php
 * Description: Centralized Audit Logging Service recording administrative
 *              and security events into MySQL with IP and severity tracking.
 * ============================================================================
 */

require_once __DIR__ . '/db.php';

/**
 * Record an administrative or security audit event into the audit_logs table.
 *
 * @param string $action Standardized action key (e.g., USER_LOGIN, ROLE_CHANGE)
 * @param int|null $userId ID of user performing or target of the action
 * @param string $details Contextual details or metadata
 * @param string $severity 'info', 'warning', 'danger', 'critical'
 * @return bool True on success, false on failure
 */
function logAuditEvent(string $action, ?int $userId = null, string $details = '', string $severity = 'info'): bool
{
    try {
        $db = getDatabaseConnection();
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'CLI/System'), 0, 255);

        $validSeverities = ['info', 'warning', 'danger', 'critical'];
        if (!in_array($severity, $validSeverities, true)) {
            $severity = 'info';
        }

        $stmt = $db->prepare(
            'INSERT INTO audit_logs (user_id, action, severity, ip_address, user_agent, details, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('isssss', $userId, $action, $severity, $ip, $ua, $details);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    } catch (Throwable $e) {
        error_log('[StudentHub Audit Engine Error] ' . $e->getMessage());
        return false;
    }
}

/**
 * Retrieve system-wide analytics summary metrics for the Admin Dashboard.
 *
 * @return array Multi-dimensional array of counts, health, and storage stats
 */
function getSystemAnalytics(): array
{
    $db = getDatabaseConnection();

    // 1. User & Role Breakdown
    $userStatsRes = $db->query(
        "SELECT COUNT(*) AS total_users,
                SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) AS student_count,
                SUM(CASE WHEN role = 'teacher' THEN 1 ELSE 0 END) AS teacher_count,
                SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) AS admin_count,
                SUM(CASE WHEN last_login_at IS NOT NULL AND last_login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS active_7days
         FROM users"
    );
    $userStats = $userStatsRes ? $userStatsRes->fetch_assoc() : [];

    // 2. Student Academic Stats
    $studentStatsRes = $db->query(
        "SELECT COUNT(*) AS total_students,
                COALESCE(AVG(cgpa), 0) AS avg_cgpa,
                SUM(CASE WHEN is_deleted = 1 THEN 1 ELSE 0 END) AS archived_students
         FROM students"
    );
    $studentStats = $studentStatsRes ? $studentStatsRes->fetch_assoc() : [];

    // 3. Courses, Events & Assignments Counts
    $contentCounts = [];
    $tables = ['courses' => 'total_courses', 'events' => 'total_events', 'assignments' => 'total_assignments', 'submissions' => 'total_submissions', 'audit_logs' => 'total_logs'];
    foreach ($tables as $tbl => $key) {
        $res = $db->query("SELECT COUNT(*) AS count FROM {$tbl}");
        $contentCounts[$key] = $res ? (int)$res->fetch_assoc()['count'] : 0;
    }

    // 4. Audit Log Severity Breakdown
    $auditStatsRes = $db->query(
        "SELECT SUM(CASE WHEN severity = 'info' THEN 1 ELSE 0 END) AS info_logs,
                SUM(CASE WHEN severity = 'warning' THEN 1 ELSE 0 END) AS warning_logs,
                SUM(CASE WHEN severity = 'danger' THEN 1 ELSE 0 END) AS danger_logs,
                SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) AS critical_logs
         FROM audit_logs"
    );
    $auditStats = $auditStatsRes ? $auditStatsRes->fetch_assoc() : [];

    // 5. Database Health & Server Info
    $dbSizeRes = $db->query(
        "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS db_size_mb
         FROM information_schema.tables
         WHERE table_schema = DATABASE()"
    );
    $dbSize = $dbSizeRes ? (float)($dbSizeRes->fetch_assoc()['db_size_mb'] ?? 0.0) : 0.0;

    return [
        'users'        => $userStats,
        'students'     => $studentStats,
        'content'      => $contentCounts,
        'audit'        => $auditStats,
        'system'       => [
            'php_version'    => PHP_VERSION,
            'db_version'     => $db->server_info,
            'db_size_mb'     => $dbSize,
            'server_os'      => PHP_OS_FAMILY,
            'timestamp'      => date('c')
        ]
    ];
}
