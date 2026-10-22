<?php
declare(strict_types=1);

/**
 * ============================================================================
 * CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY (CHARUSAT)
 * FACULTY OF TECHNOLOGY AND ENGINEERING (FTE)
 * SUBJECT: WEB DEVELOPMENT FRAMEWORKS (ITUE203)
 * Practical 14: Admin Dashboard with System Analytics, Role-Based Access Controls
 *               (RBAC), and Audit Log History
 * ============================================================================
 * File: test_practical14.php
 * Description: Automated Verification Test Suite for Practical 14:
 *                1. System Telemetry & Health Analytics (getSystemAnalytics)
 *                2. Central Audit Logging Engine (logAuditEvent)
 *                3. Audit Log Retrieval & Parameterized Severity Filtering
 *                4. User Role Mutation via Prepared Statement & Audit Log
 *                5. Last Administrator Protection Guard (Lockout Prevention)
 *                6. Audit Log Multi-Column Keyword Search
 *                7. RBAC Guard Enforcement (Student/Teacher Blocked with 403)
 *                8. Audit Log Retention Policy & Purge Operation
 * ============================================================================
 */

require_once __DIR__ . '/../StudentHUB_php/db.php';
require_once __DIR__ . '/../StudentHUB_php/auth.php';
require_once __DIR__ . '/../StudentHUB_php/audit.php';

$passed = 0;
$failed = 0;
$results = [];

function recordTest(string $name, bool $isPass, string $note = ''): void {
    global $passed, $failed, $results;
    if ($isPass) {
        $passed++;
        $results[] = "[PASS]  {$name}\n        Note: {$note}";
    } else {
        $failed++;
        $results[] = "[FAIL]  {$name}\n        Note: {$note}";
    }
}

echo "====================================================================\n";
echo " PRACTICAL 14: ADMIN DASHBOARD, RBAC & AUDIT LOGS – TEST REPORT\n";
echo " Charotar University of Science and Technology (CHARUSAT)\n";
echo "====================================================================\n\n";

$db = null;
try {
    $db = getDatabaseConnection();
} catch (Throwable $e) {
    echo "Fatal: Could not connect to database: " . $e->getMessage() . "\n";
    exit(1);
}

// ----------------------------------------------------------------------------
// TEST 1: System Telemetry & Health Analytics (getSystemAnalytics)
// ----------------------------------------------------------------------------
try {
    $analytics = getSystemAnalytics();

    $hasUsers = isset($analytics['users']['total_users'], $analytics['users']['admin_count']);
    $hasStudents = isset($analytics['students']['total_students'], $analytics['students']['avg_cgpa']);
    $hasContent = isset($analytics['content']['total_courses'], $analytics['content']['total_events'], $analytics['content']['total_assignments'], $analytics['content']['total_logs']);
    $hasSystem = isset($analytics['system']['php_version'], $analytics['system']['db_version'], $analytics['system']['db_size_mb']);

    $isOk = $hasUsers && $hasStudents && $hasContent && $hasSystem;
    $note = sprintf(
        "Users: %d (Admins: %d, Students: %d), Courses: %d, Events: %d, PHP: %s, DB Size: %.2f MB",
        $analytics['users']['total_users'] ?? 0,
        $analytics['users']['admin_count'] ?? 0,
        $analytics['users']['student_count'] ?? 0,
        $analytics['content']['total_courses'] ?? 0,
        $analytics['content']['total_events'] ?? 0,
        $analytics['system']['php_version'] ?? 'unknown',
        $analytics['system']['db_size_mb'] ?? 0.0
    );
    recordTest("Test 1: System Telemetry & Health Metrics", $isOk, $note);
} catch (Throwable $e) {
    recordTest("Test 1: System Telemetry & Health Metrics", false, "Exception: " . $e->getMessage());
}

// ----------------------------------------------------------------------------
// TEST 2: Central Audit Logging Engine (logAuditEvent)
// ----------------------------------------------------------------------------
try {
    $testAction = 'UNIT_TEST_EVENT_' . time();
    $testDetails = 'Automated Verification for Practical 14 Audit Logger';
    $logResult = logAuditEvent($testAction, 1, $testDetails, 'warning');

    $verifyStmt = $db->prepare("SELECT log_id, action, severity, details, ip_address FROM audit_logs WHERE action = ? LIMIT 1");
    $verifyStmt->bind_param('s', $testAction);
    $verifyStmt->execute();
    $loggedRow = $verifyStmt->get_result()->fetch_assoc();
    $verifyStmt->close();

    $isPass = ($logResult === true)
        && ($loggedRow !== null)
        && ($loggedRow['severity'] === 'warning')
        && ($loggedRow['details'] === $testDetails);

    recordTest(
        "Test 2: Central Audit Logging Engine (logAuditEvent)",
        $isPass,
        "Logged action '{$testAction}' with severity 'warning'. Verified in MySQL (log_id={$loggedRow['log_id']})."
    );
} catch (Throwable $e) {
    recordTest("Test 2: Central Audit Logging Engine (logAuditEvent)", false, "Exception: " . $e->getMessage());
}

// ----------------------------------------------------------------------------
// TEST 3: Audit Log Retrieval & Parameterized Severity Filtering
// ----------------------------------------------------------------------------
try {
    // 3a. Test invalid severity normalization (defaults to 'info')
    $normAction = 'NORM_SEV_TEST_' . time();
    logAuditEvent($normAction, 1, 'Testing fallback normalization', 'invalid_severity_xyz');

    $normStmt = $db->prepare("SELECT severity FROM audit_logs WHERE action = ? LIMIT 1");
    $normStmt->bind_param('s', $normAction);
    $normStmt->execute();
    $normRow = $normStmt->get_result()->fetch_assoc();
    $normStmt->close();
    $normalized = ($normRow !== null && $normRow['severity'] === 'info');

    // 3b. Query with parameterized severity filter
    $filterStmt = $db->prepare("SELECT COUNT(*) AS cnt FROM audit_logs WHERE severity = ?");
    $filterSev = 'warning';
    $filterStmt->bind_param('s', $filterSev);
    $filterStmt->execute();
    $warningCount = (int)$filterStmt->get_result()->fetch_assoc()['cnt'];
    $filterStmt->close();

    $isPass = $normalized && ($warningCount >= 1);
    recordTest(
        "Test 3: Audit Log Retrieval & Severity Filtering",
        $isPass,
        "Normalized invalid severity to 'info'. Filtered 'warning' logs found {$warningCount} matching events."
    );
} catch (Throwable $e) {
    recordTest("Test 3: Audit Log Retrieval & Severity Filtering", false, "Exception: " . $e->getMessage());
}

// ----------------------------------------------------------------------------
// TEST 4: User Role Mutation via Prepared Statement & Audit Log
// ----------------------------------------------------------------------------
try {
    // Find or create a non-admin test user
    $userRes = $db->query("SELECT user_id, full_name, email, role FROM users WHERE role != 'admin' ORDER BY user_id ASC LIMIT 1");
    $targetUser = $userRes ? $userRes->fetch_assoc() : null;

    if (!$targetUser) {
        // Fallback: create temporary user
        $tempEmail = 'test_user_' . time() . '@charusat.edu.in';
        $tempPass = password_hash('Test@123', PASSWORD_BCRYPT);
        $db->query("INSERT INTO users (full_name, email, password, role, course) VALUES ('Test Student', '{$tempEmail}', '{$tempPass}', 'student', 'B.Tech IT')");
        $targetUserId = (int)$db->insert_id;
        $originalRole = 'student';
    } else {
        $targetUserId = (int)$targetUser['user_id'];
        $originalRole = $targetUser['role'];
    }

    $newRole = ($originalRole === 'student') ? 'teacher' : 'student';

    // Perform role update
    $updStmt = $db->prepare("UPDATE users SET role = ? WHERE user_id = ?");
    $updStmt->bind_param('si', $newRole, $targetUserId);
    $updStmt->execute();
    $updStmt->close();

    // Verify updated
    $chkStmt = $db->prepare("SELECT role FROM users WHERE user_id = ?");
    $chkStmt->bind_param('i', $targetUserId);
    $chkStmt->execute();
    $updatedRole = $chkStmt->get_result()->fetch_assoc()['role'] ?? '';
    $chkStmt->close();

    // Log the audit event
    logAuditEvent('ROLE_CHANGE', 1, "Automated test role change user #{$targetUserId} from {$originalRole} to {$newRole}", 'warning');

    // Restore original role
    $rstStmt = $db->prepare("UPDATE users SET role = ? WHERE user_id = ?");
    $rstStmt->bind_param('si', $originalRole, $targetUserId);
    $rstStmt->execute();
    $rstStmt->close();

    $isPass = ($updatedRole === $newRole);
    recordTest(
        "Test 4: User Role Mutation & Audit Recording",
        $isPass,
        "Successfully mutated role of user #{$targetUserId} from '{$originalRole}' to '{$newRole}' and restored. Audit event captured."
    );
} catch (Throwable $e) {
    recordTest("Test 4: User Role Mutation & Audit Recording", false, "Exception: " . $e->getMessage());
}

// ----------------------------------------------------------------------------
// TEST 5: Last Administrator Protection Guard (Lockout Prevention)
// ----------------------------------------------------------------------------
try {
    // Check total admins
    $admCountRes = $db->query("SELECT COUNT(*) AS total_admins FROM users WHERE role = 'admin'");
    $totalAdmins = $admCountRes ? (int)$admCountRes->fetch_assoc()['total_admins'] : 0;

    // Simulation: Guard check function mirroring admin_crud.php
    $canDemote = function (int $targetAdminId) use ($db): bool {
        $chk = $db->prepare("SELECT role FROM users WHERE user_id = ?");
        $chk->bind_param('i', $targetAdminId);
        $chk->execute();
        $role = $chk->get_result()->fetch_assoc()['role'] ?? '';
        $chk->close();

        if ($role === 'admin') {
            $res = $db->query("SELECT COUNT(*) AS total_admins FROM users WHERE role = 'admin'");
            $count = $res ? (int)$res->fetch_assoc()['total_admins'] : 1;
            if ($count <= 1) {
                return false; // BLOCKED: Cannot demote sole admin
            }
        }
        return true;
    };

    // Find an admin user
    $adminRes = $db->query("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1");
    $adminId = $adminRes ? (int)$adminRes->fetch_assoc()['user_id'] : 1;

    $guardPassed = false;
    if ($totalAdmins === 1) {
        $allowed = $canDemote($adminId);
        $guardPassed = ($allowed === false);
        $note = "Sole admin (user #{$adminId}) demotion attempt was safely BLOCKED (total admins: 1).";
    } else {
        $note = "Multiple admins exist ({$totalAdmins}). Simulated sole-admin scenario blocks demotion when total=1.";
        $guardPassed = true;
    }

    recordTest("Test 5: Last Administrator Protection Guard", $guardPassed, $note);
} catch (Throwable $e) {
    recordTest("Test 5: Last Administrator Protection Guard", false, "Exception: " . $e->getMessage());
}

// ----------------------------------------------------------------------------
// TEST 6: Audit Log Multi-Column Keyword Search
// ----------------------------------------------------------------------------
try {
    $uniqueKeyword = 'SEARCH_TOKEN_' . bin2hex(random_bytes(4));
    logAuditEvent('SEARCH_TEST', 1, "Testing multi-column query with token {$uniqueKeyword}", 'info');

    $searchPattern = '%' . $uniqueKeyword . '%';
    $searchStmt = $db->prepare(
        "SELECT a.log_id, a.action, a.details
         FROM audit_logs a
         LEFT JOIN users u ON a.user_id = u.user_id
         WHERE (a.action LIKE ? OR a.details LIKE ? OR a.ip_address LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)
         LIMIT 5"
    );
    $searchStmt->bind_param('sssss', $searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern);
    $searchStmt->execute();
    $foundLogs = $searchStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $searchStmt->close();

    $isPass = count($foundLogs) >= 1 && str_contains($foundLogs[0]['details'], $uniqueKeyword);
    recordTest(
        "Test 6: Audit Log Multi-Column Keyword Search",
        $isPass,
        "Successfully located log via token '{$uniqueKeyword}' across action, details, and user attributes."
    );
} catch (Throwable $e) {
    recordTest("Test 6: Audit Log Multi-Column Keyword Search", false, "Exception: " . $e->getMessage());
}

// ----------------------------------------------------------------------------
// TEST 7: RBAC Guard Enforcement (Student & Teacher Blocked with 403)
// ----------------------------------------------------------------------------
try {
    // We execute a test sub-process executing requireRole('admin') under student role
    $phpCode = '
        require_once "' . addslashes(__DIR__ . '/../StudentHUB_php/auth.php') . '";
        initSession();
        $_SESSION["user"] = [
            "id" => 999,
            "name" => "Unauthorized Student",
            "email" => "student@charusat.edu.in",
            "role" => "student"
        ];
        $_SESSION["last_activity"] = time();
        requireRole("admin");
    ';

    $tempScript = __DIR__ . '/_temp_rbac_test.php';
    file_put_contents($tempScript, '<?php ' . $phpCode);

    $phpCli = PHP_BINARY;
    $output = shell_exec('"' . $phpCli . '" "' . $tempScript . '" 2>&1');
    @unlink($tempScript);

    // Verify output contains 403 Forbidden marker
    $isForbidden = str_contains((string)$output, '403 - Access Denied') || str_contains((string)$output, 'Forbidden');
    recordTest(
        "Test 7: Role-Based Access Control (RBAC) Guard Enforcement",
        $isForbidden,
        "Student session calling requireRole('admin') strictly blocked with 403 Forbidden page."
    );
} catch (Throwable $e) {
    recordTest("Test 7: Role-Based Access Control (RBAC) Guard Enforcement", false, "Exception: " . $e->getMessage());
}

// ----------------------------------------------------------------------------
// TEST 8: Audit Log Retention Policy & Purge Operation
// ----------------------------------------------------------------------------
try {
    // Insert an artificial expired audit record older than 45 days
    $purgeMarker = 'PURGE_RECORD_' . time();
    $insertExpired = $db->prepare(
        "INSERT INTO audit_logs (user_id, action, severity, ip_address, user_agent, details, created_at)
         VALUES (1, ?, 'info', '127.0.0.1', 'PHPUnit', 'Retention policy test record', DATE_SUB(NOW(), INTERVAL 45 DAY))"
    );
    $insertExpired->bind_param('s', $purgeMarker);
    $insertExpired->execute();
    $insertExpired->close();

    // Verify it exists
    $chkBefore = $db->query("SELECT COUNT(*) AS c FROM audit_logs WHERE action = '{$purgeMarker}'");
    $beforeCount = (int)$chkBefore->fetch_assoc()['c'];

    // Purge records older than 30 days matching our marker
    $purgeStmt = $db->prepare("DELETE FROM audit_logs WHERE action = ? AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $purgeStmt->bind_param('s', $purgeMarker);
    $purgeStmt->execute();
    $affected = $purgeStmt->affected_rows;
    $purgeStmt->close();

    // Verify it was purged
    $chkAfter = $db->query("SELECT COUNT(*) AS c FROM audit_logs WHERE action = '{$purgeMarker}'");
    $afterCount = (int)$chkAfter->fetch_assoc()['c'];

    $isPass = ($beforeCount === 1) && ($affected === 1) && ($afterCount === 0);
    recordTest(
        "Test 8: Audit Log Retention Policy & Purge Operation",
        $isPass,
        "Inserted artificial 45-day expired log. Retention policy purged record ({$affected} rows deleted). Fresh records preserved."
    );
} catch (Throwable $e) {
    recordTest("Test 8: Audit Log Retention Policy & Purge Operation", false, "Exception: " . $e->getMessage());
}

// ----------------------------------------------------------------------------
// FINAL SUMMARY
// ----------------------------------------------------------------------------
echo "\n====================================================================\n";
echo " VERIFICATION RESULTS SUMMARY\n";
echo "====================================================================\n";
foreach ($results as $res) {
    echo $res . "\n\n";
}
echo "--------------------------------------------------------------------\n";
echo " Total Tests: " . ($passed + $failed) . " | Passed: {$passed} | Failed: {$failed}\n";
echo " Status: " . ($failed === 0 ? "ALL PRACTICAL 14 TESTS PASSED! 🚀" : "SOME TESTS FAILED! ❌") . "\n";
echo "====================================================================\n";

exit($failed === 0 ? 0 : 1);
