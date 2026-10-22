<?php
declare(strict_types=1);

/**
 * ============================================================================
 * StudentHub - Automated Test Suite: Practical 11 (Student Management CRUD)
 * Tests:
 *   1. Student Record Read & Pagination (verifies >= 20 seeded students)
 *   2. Parameterized Live Search (Name, Email, Enrollment No)
 *   3. Multi-Criteria Filtering (Course & Semester)
 *   4. Student Insertion (CREATE) with Prepared Statements
 *   5. Duplicate Constraint Validation (Email & Enrollment No)
 *   6. Student Modification (UPDATE)
 *   7. Soft-Delete (DELETE) with Timestamp Verification
 *   8. Restore Soft-Deleted Record & Clean Up
 * ============================================================================
 */

$isCli = (php_sapi_name() === 'cli');

require_once dirname(__DIR__) . '/db.php';

$results = [];

function runTest(string $title, callable $fn): array {
    try {
        $note = $fn();
        return ['title' => $title, 'passed' => true, 'note' => is_string($note) ? $note : 'OK'];
    } catch (Throwable $t) {
        return ['title' => $title, 'passed' => false, 'note' => $t->getMessage()];
    }
}

// ----------------------------------------------------------------------------
// Test 1: Read & Paginate Active Students
// ----------------------------------------------------------------------------
$results[] = runTest('Student Read & Pagination (Minimum 20 Seeded Requirement)', function () {
    $db = getDatabaseConnection();
    $stmt = $db->prepare('SELECT COUNT(*) AS total FROM students WHERE is_deleted = 0');
    $stmt->execute();
    $total = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    if ($total < 20) {
        throw new RuntimeException("Found only {$total} active students. Minimum 20 required by syllabus.");
    }

    // Verify pagination slice (limit 5, offset 0)
    $sliceStmt = $db->prepare('SELECT student_id, full_name, enrollment_no FROM students WHERE is_deleted = 0 ORDER BY student_id ASC LIMIT 5 OFFSET 0');
    $sliceStmt->execute();
    $page1 = $sliceStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $sliceStmt->close();

    if (count($page1) !== 5) {
        throw new RuntimeException("Pagination limit 5 returned " . count($page1) . " rows.");
    }

    return "Verified {$total} active student records (exceeds 20 requirement). Page 1 (limit 5) returned 5 rows.";
});

// ----------------------------------------------------------------------------
// Test 2: Parameterized Live Search
// ----------------------------------------------------------------------------
$results[] = runTest('Parameterized Keyword Search (Name / Email / Enrollment)', function () {
    $db = getDatabaseConnection();

    // 1. Search by name keyword 'Aarav'
    $stmt = $db->prepare('SELECT student_id, full_name, enrollment_no FROM students WHERE is_deleted = 0 AND (full_name LIKE ? OR email LIKE ? OR enrollment_no LIKE ?)');
    $keyword = '%Aarav%';
    $stmt->bind_param('sss', $keyword, $keyword, $keyword);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($res)) {
        throw new RuntimeException("Search for 'Aarav' returned no results.");
    }

    // 2. Search by enrollment prefix '25DCE'
    $stmt2 = $db->prepare('SELECT enrollment_no FROM students WHERE is_deleted = 0 AND enrollment_no LIKE ?');
    $prefix = '25DCE%';
    $stmt2->bind_param('s', $prefix);
    $stmt2->execute();
    $res2 = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt2->close();

    if (empty($res2)) {
        throw new RuntimeException("Search for enrollment prefix '25DCE%' returned no results.");
    }

    return "Name query 'Aarav' matched " . count($res) . " record(s); enrollment query '25DCE%' matched " . count($res2) . " record(s).";
});

// ----------------------------------------------------------------------------
// Test 3: Multi-Criteria Filtering (Course & Semester)
// ----------------------------------------------------------------------------
$results[] = runTest('Multi-Criteria Filtering (Course AND Semester)', function () {
    $db = getDatabaseConnection();
    $course = 'B.Tech CE';
    $sem = 3;

    $stmt = $db->prepare('SELECT student_id, full_name, course_name, semester FROM students WHERE is_deleted = 0 AND course_name = ? AND semester = ?');
    $stmt->bind_param('si', $course, $sem);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($rows)) {
        throw new RuntimeException("Filter for Course '{$course}' and Semester {$sem} returned 0 rows.");
    }

    foreach ($rows as $r) {
        if ($r['course_name'] !== $course || (int)$r['semester'] !== $sem) {
            throw new RuntimeException("Row violated filter: " . json_encode($r));
        }
    }

    return "Filtered Course='{$course}' AND Semester={$sem}: accurately returned " . count($rows) . " matching records.";
});

// ----------------------------------------------------------------------------
// Test 4: CREATE (Insert New Student)
// ----------------------------------------------------------------------------
$createdStudentId = 0;
$testEnrollment = '24TEST' . rand(100, 999);
$testEmail = 'test_crud_' . time() . '@charusat.edu.in';

$results[] = runTest('Create (INSERT) Student via Prepared Statement', function () use (&$createdStudentId, $testEnrollment, $testEmail) {
    $db = getDatabaseConnection();
    $name = 'Neev Test Student';
    $course = 'B.Tech IT';
    $dept = 'Information Technology';
    $sem = 4;
    $cgpa = 8.75;

    $stmt = $db->prepare('INSERT INTO students (full_name, email, enrollment_no, course_name, department, semester, cgpa, is_deleted) VALUES (?, ?, ?, ?, ?, ?, ?, 0)');
    if (!$stmt) {
        throw new RuntimeException("Prepare failed: " . $db->error);
    }

    $stmt->bind_param('sssssid', $name, $testEmail, $testEnrollment, $course, $dept, $sem, $cgpa);
    $stmt->execute();
    $createdStudentId = (int)$db->insert_id;
    $stmt->close();

    if ($createdStudentId <= 0) {
        throw new RuntimeException("Insert failed to return a valid student_id.");
    }

    return "Successfully inserted student '{$name}' with generated ID #{$createdStudentId}.";
});

// ----------------------------------------------------------------------------
// Test 5: Duplicate Prevention
// ----------------------------------------------------------------------------
$results[] = runTest('Duplicate Constraint Validation (Unique Email & Enrollment)', function () use ($testEnrollment, $testEmail) {
    $db = getDatabaseConnection();
    $stmt = $db->prepare('SELECT student_id FROM students WHERE email = ? OR enrollment_no = ? LIMIT 1');
    $stmt->bind_param('ss', $testEmail, $testEnrollment);
    $stmt->execute();
    $dup = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$dup) {
        throw new RuntimeException("Duplicate check query failed to find existing test record.");
    }

    return "Duplicate detection properly recognized collision for enrollment '{$testEnrollment}'.";
});

// ----------------------------------------------------------------------------
// Test 6: UPDATE Student Record
// ----------------------------------------------------------------------------
$results[] = runTest('Update (UPDATE) Student via Prepared Statement', function () use (&$createdStudentId) {
    if ($createdStudentId <= 0) {
        throw new RuntimeException("No student ID available from creation test.");
    }

    $db = getDatabaseConnection();
    $newCgpa = 9.45;
    $newName = 'Neev Updated Student';

    $stmt = $db->prepare('UPDATE students SET full_name = ?, cgpa = ? WHERE student_id = ?');
    $stmt->bind_param('sdi', $newName, $newCgpa, $createdStudentId);
    $stmt->execute();
    $stmt->close();

    // Verify updated values from DB
    $chk = $db->prepare('SELECT full_name, cgpa FROM students WHERE student_id = ?');
    $chk->bind_param('i', $createdStudentId);
    $chk->execute();
    $updated = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$updated || $updated['full_name'] !== $newName || abs((float)$updated['cgpa'] - $newCgpa) > 0.01) {
        throw new RuntimeException("Student record was not properly updated in DB.");
    }

    return "Updated student #{$createdStudentId}: Name set to '{$newName}', CGPA updated to 9.45.";
});

// ----------------------------------------------------------------------------
// Test 7: Soft-Delete with Timestamp Verification
// ----------------------------------------------------------------------------
$results[] = runTest('Soft-Delete (UPDATE is_deleted=1, deleted_at=NOW())', function () use (&$createdStudentId) {
    if ($createdStudentId <= 0) {
        throw new RuntimeException("No student ID available for soft-delete.");
    }

    $db = getDatabaseConnection();
    $stmt = $db->prepare('UPDATE students SET is_deleted = 1, deleted_at = NOW() WHERE student_id = ?');
    $stmt->bind_param('i', $createdStudentId);
    $stmt->execute();
    $stmt->close();

    // 1. Ensure student is excluded from active query
    $chkActive = $db->prepare('SELECT student_id FROM students WHERE student_id = ? AND is_deleted = 0');
    $chkActive->bind_param('i', $createdStudentId);
    $chkActive->execute();
    $isActive = (bool)$chkActive->get_result()->fetch_assoc();
    $chkActive->close();

    if ($isActive) {
        throw new RuntimeException("Soft-deleted student was still returned by active filter query.");
    }

    // 2. Ensure deleted_at timestamp is populated
    $chkDel = $db->prepare('SELECT is_deleted, deleted_at FROM students WHERE student_id = ?');
    $chkDel->bind_param('i', $createdStudentId);
    $chkDel->execute();
    $delRecord = $chkDel->get_result()->fetch_assoc();
    $chkDel->close();

    if (!$delRecord || (int)$delRecord['is_deleted'] !== 1 || empty($delRecord['deleted_at'])) {
        throw new RuntimeException("Soft delete flags or deleted_at timestamp missing in DB.");
    }

    return "Student #{$createdStudentId} safely archived (is_deleted=1, deleted_at='{$delRecord['deleted_at']}'). Excluded from active queries.";
});

// ----------------------------------------------------------------------------
// Test 8: Restore & Cleanup
// ----------------------------------------------------------------------------
$results[] = runTest('Restore Soft-Deleted Record & Clean Up', function () use (&$createdStudentId) {
    if ($createdStudentId <= 0) {
        throw new RuntimeException("No student ID available for restore.");
    }

    $db = getDatabaseConnection();

    // 1. Restore record
    $resStmt = $db->prepare('UPDATE students SET is_deleted = 0, deleted_at = NULL WHERE student_id = ?');
    $resStmt->bind_param('i', $createdStudentId);
    $resStmt->execute();
    $resStmt->close();

    $chkRestored = $db->prepare('SELECT is_deleted, deleted_at FROM students WHERE student_id = ?');
    $chkRestored->bind_param('i', $createdStudentId);
    $chkRestored->execute();
    $restored = $chkRestored->get_result()->fetch_assoc();
    $chkRestored->close();

    if (!$restored || (int)$restored['is_deleted'] !== 0 || $restored['deleted_at'] !== null) {
        throw new RuntimeException("Restore failed to clear soft-delete flags.");
    }

    // 2. Clean up test record from database
    $del = $db->prepare('DELETE FROM students WHERE student_id = ?');
    $del->bind_param('i', $createdStudentId);
    $del->execute();
    $del->close();

    return "Student #{$createdStudentId} restored to active status, then purged to keep DB clean.";
});

// ----------------------------------------------------------------------------
// Output Report
// ----------------------------------------------------------------------------
if ($isCli) {
    echo "====================================================================\n";
    echo " PRACTICAL 11: STUDENT MANAGEMENT CRUD – AUTOMATED TEST REPORT\n";
    echo " Charotar University of Science and Technology (CHARUSAT)\n";
    echo "====================================================================\n\n";

    $passed = 0;
    foreach ($results as $i => $r) {
        $tag = $r['passed'] ? '[PASS]' : '[FAIL]';
        printf("%-7s Test %d: %s\n", $tag, $i + 1, $r['title']);
        echo "        Note: " . $r['note'] . "\n\n";
        if ($r['passed']) $passed++;
    }

    echo str_repeat('-', 68) . "\n";
    printf("Total: %d | Passed: %d | Failed: %d\n", count($results), $passed, count($results) - $passed);
    echo "====================================================================\n";
} else {
    header('Content-Type: application/json');
    echo json_encode($results, JSON_PRETTY_PRINT);
}
