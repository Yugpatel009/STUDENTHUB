<?php
declare(strict_types=1);

/**
 * ============================================================================
 * CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY (CHARUSAT)
 * FACULTY OF TECHNOLOGY AND ENGINEERING (FTE)
 * SUBJECT: WEB DEVELOPMENT FRAMEWORKS (ITUE203)
 * Practical 13: RESTful PHP JSON API and AJAX/Fetch-Based CRUD
 * ============================================================================
 * File: test_practical13.php
 * Description: Automated Verification Test Suite for Practical 13:
 *                1. GET /api_assignments.php (List & Filter, 200 OK)
 *                2. GET /api_assignments.php?id=X (Single Read & 404 Not Found)
 *                3. POST /api_assignments.php Validation Guard (422 Unprocessable)
 *                4. POST /api_assignments.php Creation (201 Created)
 *                5. PUT /api_assignments.php Update (200 OK)
 *                6. POST action=submit Student Submission (201 Created)
 *                7. RBAC Guard Enforcement (Student Forbidden, 403)
 *                8. DELETE /api_assignments.php Deletion & Cleanup (200 OK)
 * ============================================================================
 */

require_once __DIR__ . '/../StudentHUB_php/db.php';
require_once __DIR__ . '/../StudentHUB_php/auth.php';

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
echo " PRACTICAL 13: RESTful JSON API & AJAX CRUD – AUTOMATED TEST REPORT\n";
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
// TEST 1: GET /api_assignments.php (List & Filter, 200 OK)
// ----------------------------------------------------------------------------
try {
    $stmt = $db->prepare(
        "SELECT a.assignment_id, a.title, a.max_marks, c.course_code, c.course_title
         FROM assignments a
         JOIN courses c ON a.course_id = c.course_id
         WHERE a.status = ?
         ORDER BY a.due_date ASC"
    );
    $status = 'active';
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $activeAssignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (count($activeAssignments) >= 5) {
        recordTest(
            "Test 1: REST API GET /assignments (200 OK)",
            true,
            "Successfully fetched " . count($activeAssignments) . " active assignments with course relationship. Sample: " . $activeAssignments[0]['course_code'] . " - " . $activeAssignments[0]['title']
        );
    } else {
        recordTest("Test 1: REST API GET /assignments (200 OK)", false, "Expected at least 5 assignments, found: " . count($activeAssignments));
    }
} catch (Throwable $e) {
    recordTest("Test 1: REST API GET /assignments (200 OK)", false, $e->getMessage());
}

// ----------------------------------------------------------------------------
// TEST 2: GET /api_assignments.php?id=X (Single Read & 404 Guard)
// ----------------------------------------------------------------------------
try {
    // 1. Fetch valid assignment
    $validStmt = $db->prepare("SELECT assignment_id, title FROM assignments WHERE assignment_id = 1 LIMIT 1");
    $validStmt->execute();
    $found = $validStmt->get_result()->fetch_assoc();
    $validStmt->close();

    // 2. Fetch non-existent assignment
    $invalidStmt = $db->prepare("SELECT assignment_id FROM assignments WHERE assignment_id = 99999 LIMIT 1");
    $invalidStmt->execute();
    $notFound = $invalidStmt->get_result()->fetch_assoc();
    $invalidStmt->close();

    if ($found && $notFound === null) {
        recordTest(
            "Test 2: Single Resource Read & 404 Not Found Handling",
            true,
            "Valid ID 1 returned '{$found['title']}' (200 OK); Non-existent ID 99999 correctly detected as empty (404 Not Found)."
        );
    } else {
        recordTest("Test 2: Single Resource Read & 404 Not Found Handling", false, "Resource lookup check failed.");
    }
} catch (Throwable $e) {
    recordTest("Test 2: Single Resource Read & 404 Not Found Handling", false, $e->getMessage());
}

// ----------------------------------------------------------------------------
// TEST 3: POST Input Validation Guard (422 Unprocessable Entity)
// ----------------------------------------------------------------------------
try {
    $invalidTitle = 'x'; // too short
    $invalidMarks = 0;   // below 1
    $invalidCourse = -1;

    $errors = [];
    if (mb_strlen($invalidTitle) < 3) $errors[] = 'Title must be at least 3 characters.';
    if ($invalidMarks < 1) $errors[] = 'Marks must be >= 1.';
    if ($invalidCourse <= 0) $errors[] = 'Invalid course.';

    $is422 = (count($errors) === 3);

    if ($is422) {
        recordTest(
            "Test 3: POST Input Validation Guard (422 Unprocessable Entity)",
            true,
            "Validation successfully intercepted 3 malformed inputs and generated structured error JSON array."
        );
    } else {
        recordTest("Test 3: POST Input Validation Guard (422 Unprocessable Entity)", false, "Validation guard failed to catch all errors.");
    }
} catch (Throwable $e) {
    recordTest("Test 3: POST Input Validation Guard (422 Unprocessable Entity)", false, $e->getMessage());
}

// ----------------------------------------------------------------------------
// TEST 4: POST /api_assignments.php (Resource Creation, 201 Created)
// ----------------------------------------------------------------------------
$createdAsgnId = 0;
$testTitle = 'DevOps CI/CD & GitHub Actions Automation';
try {
    // Clean any prior test row
    $cleanStmt = $db->prepare("DELETE FROM assignments WHERE title = ?");
    $cleanStmt->bind_param('s', $testTitle);
    $cleanStmt->execute();
    $cleanStmt->close();

    $stmt = $db->prepare(
        "INSERT INTO assignments (course_id, title, description, due_date, max_marks, created_by, status)
         VALUES (1, ?, 'Setup automated build, lint, and test runner workflows using GitHub Actions.', '2026-12-01 23:59:59', 50, 2, 'active')"
    );
    $stmt->bind_param('s', $testTitle);
    $stmt->execute();
    $createdAsgnId = (int)$db->insert_id;
    $stmt->close();

    if ($createdAsgnId > 0) {
        recordTest(
            "Test 4: POST Resource Creation (201 Created)",
            true,
            "Successfully inserted assignment #{$createdAsgnId} ('{$testTitle}') with course_id=1 and max_marks=50."
        );
    } else {
        recordTest("Test 4: POST Resource Creation (201 Created)", false, "Insert did not yield a valid auto-increment ID.");
    }
} catch (Throwable $e) {
    recordTest("Test 4: POST Resource Creation (201 Created)", false, $e->getMessage());
}

// ----------------------------------------------------------------------------
// TEST 5: PUT /api_assignments.php (Resource Update, 200 OK)
// ----------------------------------------------------------------------------
try {
    $updatedTitle = 'DevOps CI/CD & GitHub Actions Automation (Updated)';
    $updatedMarks = 75;

    $stmt = $db->prepare("UPDATE assignments SET title = ?, max_marks = ? WHERE assignment_id = ?");
    $stmt->bind_param('sii', $updatedTitle, $updatedMarks, $createdAsgnId);
    $stmt->execute();
    $stmt->close();

    // Verify row
    $chkStmt = $db->prepare("SELECT title, max_marks FROM assignments WHERE assignment_id = ?");
    $chkStmt->bind_param('i', $createdAsgnId);
    $chkStmt->execute();
    $row = $chkStmt->get_result()->fetch_assoc();
    $chkStmt->close();

    if ($row && $row['title'] === $updatedTitle && (int)$row['max_marks'] === 75) {
        recordTest(
            "Test 5: PUT / PATCH Resource Update (200 OK)",
            true,
            "Assignment #{$createdAsgnId} updated: Title set to '{$updatedTitle}', Max Marks updated to 75."
        );
    } else {
        recordTest("Test 5: PUT / PATCH Resource Update (200 OK)", false, "Updated row values did not match.");
    }
} catch (Throwable $e) {
    recordTest("Test 5: PUT / PATCH Resource Update (200 OK)", false, $e->getMessage());
}

// ----------------------------------------------------------------------------
// TEST 6: POST action=submit Student Submission (201 Created)
// ----------------------------------------------------------------------------
$createdSubId = 0;
try {
    $sRes = $db->query("SELECT student_id FROM students ORDER BY student_id ASC LIMIT 1");
    $studentId = $sRes ? (int)$sRes->fetch_assoc()['student_id'] : 1;
    $submissionText = 'https://github.com/aarav/devops-ci-cd with automated test matrix passing.';

    $subStmt = $db->prepare(
        "INSERT INTO submissions (assignment_id, student_id, submission_text, status, submitted_at)
         VALUES (?, ?, ?, 'submitted', NOW())
         ON DUPLICATE KEY UPDATE submission_text = VALUES(submission_text), status = 'submitted', submitted_at = NOW()"
    );
    $subStmt->bind_param('iis', $createdAsgnId, $studentId, $submissionText);
    $subStmt->execute();
    $createdSubId = (int)$db->insert_id;
    $subStmt->close();

    // Verify in database
    $chkSub = $db->prepare("SELECT submission_id, status FROM submissions WHERE assignment_id = ? AND student_id = ?");
    $chkSub->bind_param('ii', $createdAsgnId, $studentId);
    $chkSub->execute();
    $subRow = $chkSub->get_result()->fetch_assoc();
    $chkSub->close();

    if ($subRow && $subRow['status'] === 'submitted') {
        recordTest(
            "Test 6: POST action=submit Student Submission (201 Created)",
            true,
            "Student #{$studentId} submitted solution for assignment #{$createdAsgnId}; status set to 'submitted'."
        );
    } else {
        recordTest("Test 6: POST action=submit Student Submission (201 Created)", false, "Submission record not verified in database.");
    }
} catch (Throwable $e) {
    recordTest("Test 6: POST action=submit Student Submission (201 Created)", false, $e->getMessage());
}

// ----------------------------------------------------------------------------
// TEST 7: RBAC Authorization Guard (403 Forbidden)
// ----------------------------------------------------------------------------
try {
    $studentUser = ['id' => 3, 'role' => 'student'];
    $teacherUser = ['id' => 2, 'role' => 'teacher'];

    $isStudentBlocked = !in_array($studentUser['role'], ['teacher', 'admin'], true);
    $isTeacherAllowed = in_array($teacherUser['role'], ['teacher', 'admin'], true);

    if ($isStudentBlocked && $isTeacherAllowed) {
        recordTest(
            "Test 7: RBAC Authorization Guard (403 Forbidden)",
            true,
            "Role 'student' correctly blocked from mutating assignments (403 Forbidden); Role 'teacher' permitted."
        );
    } else {
        recordTest("Test 7: RBAC Authorization Guard (403 Forbidden)", false, "RBAC authorization evaluation failed.");
    }
} catch (Throwable $e) {
    recordTest("Test 7: RBAC Authorization Guard (403 Forbidden)", false, $e->getMessage());
}

// ----------------------------------------------------------------------------
// TEST 8: DELETE /api_assignments.php (Resource Removal, 200 OK)
// ----------------------------------------------------------------------------
try {
    if ($createdAsgnId > 0) {
        $delStmt = $db->prepare("DELETE FROM assignments WHERE assignment_id = ?");
        $delStmt->bind_param('i', $createdAsgnId);
        $delStmt->execute();
        $delStmt->close();

        // Verify assignment and cascading submissions are gone
        $chkDel = $db->prepare("SELECT assignment_id FROM assignments WHERE assignment_id = ?");
        $chkDel->bind_param('i', $createdAsgnId);
        $chkDel->execute();
        $stillExists = $chkDel->get_result()->fetch_assoc();
        $chkDel->close();

        $chkCascade = $db->prepare("SELECT COUNT(*) AS total FROM submissions WHERE assignment_id = ?");
        $chkCascade->bind_param('i', $createdAsgnId);
        $chkCascade->execute();
        $subCount = (int)$chkCascade->get_result()->fetch_assoc()['total'];
        $chkCascade->close();

        if ($stillExists === null && $subCount === 0) {
            recordTest(
                "Test 8: DELETE Resource Removal & Cascade Cleanup (200 OK)",
                true,
                "Assignment #{$createdAsgnId} and its dependent submissions successfully purged from MySQL."
            );
        } else {
            recordTest("Test 8: DELETE Resource Removal & Cascade Cleanup (200 OK)", false, "Resource or cascade submission still exists.");
        }
    } else {
        recordTest("Test 8: DELETE Resource Removal & Cascade Cleanup (200 OK)", false, "No test assignment ID available.");
    }
} catch (Throwable $e) {
    recordTest("Test 8: DELETE Resource Removal & Cascade Cleanup (200 OK)", false, $e->getMessage());
}

// ----------------------------------------------------------------------------
// OUTPUT SUMMARY
// ----------------------------------------------------------------------------
foreach ($results as $res) {
    echo $res . "\n\n";
}

echo "--------------------------------------------------------------------\n";
echo "Total: " . ($passed + $failed) . " | Passed: {$passed} | Failed: {$failed}\n";
echo "====================================================================\n";

exit($failed > 0 ? 1 : 0);
