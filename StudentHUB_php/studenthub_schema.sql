-- ============================================================================
-- CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY (CHARUSAT)
-- FACULTY OF TECHNOLOGY AND ENGINEERING (FTE)
-- SUBJECT: WEB DEVELOPMENT FRAMEWORKS (ITUE203)
-- PRACTICAL 8: MySQL Schema Design, ER Model, PDO Connectivity, and Prepared Statements
-- Database Name: student_hub
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `student_hub`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `student_hub`;

-- ----------------------------------------------------------------------------
-- Drop existing tables and procedures for clean re-import
-- ----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `sp_RegisterStudentForEvent`;
DROP PROCEDURE IF EXISTS `sp_GetStudentRegistrations`;
DROP PROCEDURE IF EXISTS `sp_GetEventSummary`;

DROP TABLE IF EXISTS `registrations`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `courses`;
DROP TABLE IF EXISTS `remember_tokens`;
DROP TABLE IF EXISTS `users`;

-- ----------------------------------------------------------------------------
-- Table 1: users (Authentication and Role-Based Access Control)
-- ----------------------------------------------------------------------------
CREATE TABLE `users` (
    `user_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(120) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('student', 'teacher', 'admin') NOT NULL DEFAULT 'student',
    `course` VARCHAR(50) NULL,
    `last_login_at` DATETIME NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 1b: remember_tokens (Practical 10: Persistent Remember-Me Tokens)
-- ----------------------------------------------------------------------------
CREATE TABLE `remember_tokens` (
    `token_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `selector` CHAR(16) NOT NULL UNIQUE,
    `token_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_remember_tokens_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_selector` (`selector`),
    INDEX `idx_user_expires` (`user_id`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 2: students (3NF Normalized Profile & Academic Information)
-- ----------------------------------------------------------------------------
CREATE TABLE `students` (
    `student_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(120) NOT NULL UNIQUE,
    `enrollment_no` VARCHAR(30) NOT NULL UNIQUE,
    `course_name` VARCHAR(100) NOT NULL,
    `department` VARCHAR(100) NOT NULL DEFAULT 'Computer Engineering',
    `semester` TINYINT UNSIGNED NOT NULL,
    `cgpa` DECIMAL(3,2) NOT NULL DEFAULT 8.00,
    `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
    `deleted_at` DATETIME NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_student_semester` CHECK (`semester` BETWEEN 1 AND 10),
    CONSTRAINT `chk_student_cgpa` CHECK (`cgpa` BETWEEN 0.00 AND 10.00),
    CONSTRAINT `fk_student_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`user_id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_students_enrollment` (`enrollment_no`),
    INDEX `idx_students_course` (`course_name`),
    INDEX `idx_students_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 2b: courses (Academic Curriculum & Faculty Course Assignments)
-- ----------------------------------------------------------------------------
CREATE TABLE `courses` (
    `course_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `course_code` VARCHAR(20) NOT NULL UNIQUE,
    `course_title` VARCHAR(150) NOT NULL,
    `department` VARCHAR(100) NOT NULL DEFAULT 'Computer Engineering',
    `faculty_id` INT UNSIGNED NULL,
    `credits` TINYINT UNSIGNED NOT NULL DEFAULT 4,
    `semester` TINYINT UNSIGNED NOT NULL DEFAULT 3,
    `description` TEXT NULL,
    `thumbnail_url` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_course_faculty` FOREIGN KEY (`faculty_id`)
        REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_courses_code` (`course_code`),
    INDEX `idx_courses_department` (`department`),
    INDEX `idx_courses_semester` (`semester`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 3: events (Campus Events, Workshops, and Hackathons)
-- ----------------------------------------------------------------------------
CREATE TABLE `events` (
    `event_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event_code` VARCHAR(20) NOT NULL UNIQUE,
    `title` VARCHAR(150) NOT NULL,
    `category` ENUM('Technical', 'Workshop', 'Cultural', 'Sports', 'Seminar') NOT NULL DEFAULT 'Technical',
    `description` TEXT NULL,
    `poster_image` VARCHAR(255) NULL DEFAULT NULL,
    `venue` VARCHAR(150) NOT NULL,
    `starts_at` DATETIME NOT NULL,
    `ends_at` DATETIME NOT NULL,
    `capacity` INT UNSIGNED NOT NULL DEFAULT 100,
    `registered_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` ENUM('upcoming', 'ongoing', 'completed', 'cancelled') NOT NULL DEFAULT 'upcoming',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_event_dates` CHECK (`ends_at` > `starts_at`),
    CONSTRAINT `chk_event_capacity` CHECK (`capacity` > 0),
    INDEX `idx_events_starts_at` (`starts_at`),
    INDEX `idx_events_category` (`category`),
    INDEX `idx_events_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 4: registrations (Bridge Entity with Composite Constraints)
-- ----------------------------------------------------------------------------
CREATE TABLE `registrations` (
    `registration_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT UNSIGNED NOT NULL,
    `event_id` INT UNSIGNED NOT NULL,
    `status` ENUM('registered', 'cancelled', 'attended') NOT NULL DEFAULT 'registered',
    `registered_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_registration_student_event` UNIQUE (`student_id`, `event_id`),
    CONSTRAINT `fk_registration_student`
        FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_registration_event`
        FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_registrations_event` (`event_id`),
    INDEX `idx_registrations_student` (`student_id`),
    INDEX `idx_registrations_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ADVANCED EXTENSION: STORED PROCEDURES
-- ============================================================================

DELIMITER $$

-- Procedure 1: Safe Event Registration with Capacity & Duplicate Checks
CREATE PROCEDURE `sp_RegisterStudentForEvent`(
    IN  p_student_id    INT UNSIGNED,
    IN  p_event_id      INT UNSIGNED,
    OUT p_result_code   VARCHAR(20),
    OUT p_result_msg    VARCHAR(255)
)
proc_label: BEGIN
    DECLARE v_capacity INT UNSIGNED;
    DECLARE v_registered INT UNSIGNED;
    DECLARE v_already_reg INT;
    DECLARE v_student_exists INT;
    DECLARE v_event_status VARCHAR(20);

    -- Check if student exists
    SELECT COUNT(*) INTO v_student_exists FROM `students` WHERE `student_id` = p_student_id;
    IF v_student_exists = 0 THEN
        SET p_result_code = 'ERR_NO_STUDENT';
        SET p_result_msg  = 'Student record not found.';
        LEAVE proc_label;
    END IF;

    -- Check if event exists and get status
    SELECT `capacity`, `registered_count`, `status`
      INTO v_capacity, v_registered, v_event_status
      FROM `events`
     WHERE `event_id` = p_event_id;

    IF v_event_status IS NULL THEN
        SET p_result_code = 'ERR_NO_EVENT';
        SET p_result_msg  = 'Event does not exist.';
        LEAVE proc_label;
    END IF;

    IF v_event_status != 'upcoming' THEN
        SET p_result_code = 'ERR_NOT_OPEN';
        SET p_result_msg  = CONCAT('Registrations are closed (Event status is ', v_event_status, ').');
        LEAVE proc_label;
    END IF;

    -- Check for duplicate registration
    SELECT COUNT(*) INTO v_already_reg
      FROM `registrations`
     WHERE `student_id` = p_student_id AND `event_id` = p_event_id;

    IF v_already_reg > 0 THEN
        SET p_result_code = 'ERR_DUPLICATE';
        SET p_result_msg  = 'Student is already registered for this event.';
        LEAVE proc_label;
    END IF;

    -- Check capacity
    IF v_registered >= v_capacity THEN
        SET p_result_code = 'ERR_CAPACITY_FULL';
        SET p_result_msg  = 'Event capacity is full. No seats remaining.';
        LEAVE proc_label;
    END IF;

    -- Insert registration and update event count atomically
    START TRANSACTION;
        INSERT INTO `registrations` (`student_id`, `event_id`, `status`)
        VALUES (p_student_id, p_event_id, 'registered');

        UPDATE `events`
           SET `registered_count` = `registered_count` + 1
         WHERE `event_id` = p_event_id;
    COMMIT;

    SET p_result_code = 'SUCCESS';
    SET p_result_msg  = 'Registration confirmed successfully.';
END proc_label $$

-- Procedure 2: Retrieve all event registrations for a given student
CREATE PROCEDURE `sp_GetStudentRegistrations`(
    IN p_student_id INT UNSIGNED
)
BEGIN
    SELECT 
        r.registration_id,
        s.full_name AS student_name,
        s.enrollment_no,
        e.event_id,
        e.event_code,
        e.title AS event_title,
        e.category,
        e.venue,
        e.starts_at,
        e.ends_at,
        r.status AS registration_status,
        r.registered_at
    FROM `registrations` r
    INNER JOIN `students` s ON r.student_id = s.student_id
    INNER JOIN `events` e   ON r.event_id = e.event_id
    WHERE r.student_id = p_student_id
    ORDER BY e.starts_at ASC;
END $$

-- Procedure 3: Calculate Event Statistics (Capacity, Seats Left, Utilization)
CREATE PROCEDURE `sp_GetEventSummary`(
    IN  p_event_id         INT UNSIGNED,
    OUT p_event_title      VARCHAR(150),
    OUT p_capacity         INT,
    OUT p_registered_count INT,
    OUT p_seats_available  INT
)
BEGIN
    SELECT `title`, `capacity`, `registered_count`, (`capacity` - `registered_count`)
      INTO p_event_title, p_capacity, p_registered_count, p_seats_available
      FROM `events`
     WHERE `event_id` = p_event_id;
END $$

DELIMITER ;

-- ============================================================================
-- SEED DATA: 20+ STUDENTS, 6+ EVENTS, 20+ REGISTRATIONS
-- ============================================================================

-- 1. Seed Users (Default password: 'Password@123')
-- Hash generated via password_hash('Password@123', PASSWORD_BCRYPT)
INSERT INTO `users` (`user_id`, `full_name`, `email`, `password_hash`, `role`, `course`) VALUES
(1, 'Admin Officer', 'admin@charusat.edu.in', '$2y$10$G.jBQEtwryWyDcg3Cl8.2OchcGnc5pPx9SH7FRequJk5h.ErS.Pmy', 'admin', NULL),
(2, 'Dr. Rajesh Patel', 'rajesh.patel@charusat.edu.in', '$2y$10$G.jBQEtwryWyDcg3Cl8.2OchcGnc5pPx9SH7FRequJk5h.ErS.Pmy', 'teacher', 'Computer Engineering'),
(3, 'Aarav Sharma', 'aarav.sharma@charusat.edu.in', '$2y$10$G.jBQEtwryWyDcg3Cl8.2OchcGnc5pPx9SH7FRequJk5h.ErS.Pmy', 'student', 'bca'),
(4, 'Diya Patel', 'diya.patel@charusat.edu.in', '$2y$10$G.jBQEtwryWyDcg3Cl8.2OchcGnc5pPx9SH7FRequJk5h.ErS.Pmy', 'student', 'bca'),
(5, 'Ishaan Verma', 'ishaan.verma@charusat.edu.in', '$2y$10$G.jBQEtwryWyDcg3Cl8.2OchcGnc5pPx9SH7FRequJk5h.ErS.Pmy', 'student', 'bsc-it'),
(6, 'Ananya Iyer', 'ananya.iyer@charusat.edu.in', '$2y$10$G.jBQEtwryWyDcg3Cl8.2OchcGnc5pPx9SH7FRequJk5h.ErS.Pmy', 'student', 'mca');

-- 2. Seed Students (22 Students - Exceeds 20 requirement)
INSERT INTO `students` (`student_id`, `user_id`, `full_name`, `email`, `enrollment_no`, `course_name`, `department`, `semester`, `cgpa`) VALUES
(1,  3,    'Aarav Sharma',       'aarav.sharma@charusat.edu.in',       '25DCE001', 'B.Tech CE', 'Computer Engineering',        3, 8.85),
(2,  4,    'Diya Patel',         'diya.patel@charusat.edu.in',         '25DCE002', 'B.Tech CE', 'Computer Engineering',        3, 9.20),
(3,  5,    'Ishaan Verma',       'ishaan.verma@charusat.edu.in',       '25DCE003', 'B.Tech IT', 'Information Technology',      3, 8.40),
(4,  6,    'Ananya Iyer',        'ananya.iyer@charusat.edu.in',        '25DCE004', 'B.Tech IT', 'Information Technology',      5, 9.05),
(5,  NULL, 'Rohan Mehta',        'rohan.mehta@charusat.edu.in',        '25DCE005', 'B.Tech CE', 'Computer Engineering',        3, 7.95),
(6,  NULL, 'Sneha Joshi',        'sneha.joshi@charusat.edu.in',        '25DCE006', 'B.Tech CE', 'Computer Engineering',        3, 8.70),
(7,  NULL, 'Kabir Nair',         'kabir.nair@charusat.edu.in',         '25DCE007', 'B.Tech IT', 'Information Technology',      3, 8.15),
(8,  NULL, 'Pooja Trivedi',      'pooja.trivedi@charusat.edu.in',      '25DCE008', 'B.Tech EC', 'Electronics & Communication', 5, 8.60),
(9,  NULL, 'Devanshi Shah',      'devanshi.shah@charusat.edu.in',      '25DCE009', 'B.Tech CE', 'Computer Engineering',        3, 9.45),
(10, NULL, 'Aditya Desai',       'aditya.desai@charusat.edu.in',       '25DCE010', 'B.Tech IT', 'Information Technology',      3, 7.80),
(11, NULL, 'Kavya Pillai',       'kavya.pillai@charusat.edu.in',       '25DCE011', 'B.Tech CE', 'Computer Engineering',        3, 8.90),
(12, NULL, 'Manav Gupta',        'manav.gupta@charusat.edu.in',        '25DCE012', 'BCA',       'Computer Applications',       1, 8.25),
(13, NULL, 'Riya Choudhary',     'riya.choudhary@charusat.edu.in',     '25DCE013', 'BCA',       'Computer Applications',       1, 8.50),
(14, NULL, 'Harsh Vardhan',      'harsh.vardhan@charusat.edu.in',      '25DCE014', 'B.Tech IT', 'Information Technology',      5, 7.65),
(15, NULL, 'Tanvi Dave',         'tanvi.dave@charusat.edu.in',         '25DCE015', 'B.Tech CE', 'Computer Engineering',        3, 9.10),
(16, NULL, 'Yash Khurana',       'yash.khurana@charusat.edu.in',       '25DCE016', 'MCA',       'Computer Applications',       3, 8.35),
(17, NULL, 'Nidhi Bhatt',        'nidhi.bhatt@charusat.edu.in',        '25DCE017', 'B.Tech CE', 'Computer Engineering',        3, 8.95),
(18, NULL, 'Aryan Mishra',       'aryan.mishra@charusat.edu.in',       '25DCE018', 'B.Tech IT', 'Information Technology',      3, 8.00),
(19, NULL, 'Priyanka Rathod',    'priyanka.rathod@charusat.edu.in',    '25DCE019', 'B.Tech CE', 'Computer Engineering',        5, 9.30),
(20, NULL, 'Siddharth Sen',      'siddharth.sen@charusat.edu.in',      '25DCE020', 'B.Tech IT', 'Information Technology',      3, 7.75),
(21, NULL, 'Meera Kulkarni',     'meera.kulkarni@charusat.edu.in',     '25DCE021', 'B.Tech CE', 'Computer Engineering',        3, 8.80),
(22, NULL, 'Dhruv Trivedi',      'dhruv.trivedi@charusat.edu.in',      '25DCE022', 'BCA',       'Computer Applications',       3, 8.10);

-- 3. Seed Events (6 Distinct Events across Categories)
INSERT INTO `events` (`event_id`, `event_code`, `title`, `category`, `description`, `venue`, `starts_at`, `ends_at`, `capacity`, `registered_count`, `status`) VALUES
(1, 'EVT-2026-HACK', 'DevPulse 24hr Campus Hackathon',   'Technical', 'Intense 24-hour hackathon focused on Web Development, AI, and Cloud Frameworks.',    'Auditorium A, FTE Campus',   '2026-10-15 09:00:00', '2026-10-16 09:00:00', 120, 8, 'upcoming'),
(2, 'EVT-2026-PDO',  'Mastering PHP PDO & MySQL Security', 'Workshop',  'Hands-on lab covering prepared statements, SQL injection prevention, and transactions.', 'Lab 301, IT Department',     '2026-10-20 14:00:00', '2026-10-20 17:00:00', 60,  6, 'upcoming'),
(3, 'EVT-2026-CYBER','CyberSecurity Conclave 2026',        'Seminar',   'Industry keynote on defensive security, CSRF protection, and OWASP Top 10 web vulnerabilities.', 'Seminar Hall 2, CHARUSAT', '2026-10-25 10:00:00', '2026-10-25 13:00:00', 150, 5, 'upcoming'),
(4, 'EVT-2026-AIML', 'Applied AI/ML Workshop with Python', 'Workshop',  'Practical session on building ML models and connecting them with modern web APIs.',       'Lab 204, CE Department',     '2026-11-05 10:00:00', '2026-11-05 16:00:00', 50,  3, 'upcoming'),
(5, 'EVT-2026-CULT', 'Spandan 2026 - Annual Cultural Fest', 'Cultural', 'Annual campus cultural festival celebrating music, dance, art, and university talent.', 'Main University Amphitheatre', '2026-11-15 17:00:00', '2026-11-15 22:00:00', 500, 2, 'upcoming'),
(6, 'EVT-2026-CODE', 'CodeClash Competitive Coding Contest','Technical', 'Algorithmic problem-solving battle tested in C++, Java, and Python.',                   'Central Computing Center',   '2026-11-22 13:00:00', '2026-11-22 16:00:00', 80,  0, 'upcoming');

-- 4. Seed Registrations (24 Validated Student Registrations)
INSERT INTO `registrations` (`student_id`, `event_id`, `status`, `registered_at`) VALUES
(1,  1, 'registered', '2026-10-01 10:00:00'),
(2,  1, 'registered', '2026-10-01 10:15:00'),
(3,  1, 'registered', '2026-10-01 11:30:00'),
(4,  1, 'registered', '2026-10-01 12:00:00'),
(5,  1, 'registered', '2026-10-01 14:20:00'),
(6,  1, 'registered', '2026-10-01 15:45:00'),
(7,  1, 'registered', '2026-10-01 16:10:00'),
(8,  1, 'registered', '2026-10-01 17:00:00'),

(1,  2, 'registered', '2026-10-02 09:00:00'),
(2,  2, 'registered', '2026-10-02 09:30:00'),
(9,  2, 'registered', '2026-10-02 10:00:00'),
(10, 2, 'registered', '2026-10-02 11:15:00'),
(11, 2, 'registered', '2026-10-02 13:00:00'),
(12, 2, 'registered', '2026-10-02 14:20:00'),

(3,  3, 'registered', '2026-10-02 15:00:00'),
(4,  3, 'registered', '2026-10-02 15:30:00'),
(13, 3, 'registered', '2026-10-02 16:00:00'),
(14, 3, 'registered', '2026-10-02 16:45:00'),
(15, 3, 'registered', '2026-10-02 17:10:00'),

(16, 4, 'registered', '2026-10-03 09:15:00'),
(17, 4, 'registered', '2026-10-03 10:30:00'),
(18, 4, 'registered', '2026-10-03 11:45:00'),

(19, 5, 'registered', '2026-10-03 12:00:00'),
(20, 5, 'registered', '2026-10-03 12:30:00');

-- 5. Seed Courses (Faculty Assigned Academic Courses)
INSERT INTO `courses` (`course_id`, `course_code`, `course_title`, `department`, `faculty_id`, `credits`, `semester`, `description`, `thumbnail_url`) VALUES
(1, 'IT203', 'Web Development Frameworks', 'Computer Engineering', 2, 4, 3, 'Comprehensive study of HTML5, CSS3, JavaScript ES6+, PHP backend architecture, MySQL relational database management, and asynchronous Fetch API.', '../img/web-development.jpg'),
(2, 'CE205', 'Database Management Systems', 'Computer Engineering', 2, 4, 3, 'Relational database design, 3NF normalization, ACID transaction integrity, indexing strategies, and stored procedures in MySQL/MariaDB.', '../img/database-management.jpg'),
(3, 'CE207', 'Computer Networks & Protocols', 'Computer Engineering', 2, 4, 3, 'OSI and TCP/IP protocol stacks, network routing algorithms, IP subnetting, socket programming, and transport layer security.', '../img/computer-networks.jpg'),
(4, 'IT205', 'Data Structures & Algorithms', 'Computer Engineering', 2, 4, 3, 'Arrays, linked lists, binary search trees, hash tables, asymptotic time complexity analysis, and graph traversal algorithms.', '../img/web-development.jpg'),
(5, 'IT301', 'Operating Systems & Virtualization', 'Computer Engineering', 2, 3, 5, 'Process scheduling, multithreading, concurrency synchronization, virtual memory management, file systems, and hypervisors.', '../img/database-management.jpg'),
(6, 'IT401', 'Artificial Intelligence & Machine Learning', 'Computer Engineering', 2, 4, 7, 'Supervised and unsupervised learning, regression, classification, decision trees, neural network fundamentals, and model evaluation.', '../img/computer-networks.jpg');

-- ----------------------------------------------------------------------------
-- Practical 13: Assignments & Submissions Schema
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `assignments` (
    `assignment_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `course_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `due_date` DATETIME NOT NULL,
    `max_marks` INT UNSIGNED NOT NULL DEFAULT 100,
    `created_by` INT UNSIGNED NOT NULL,
    `status` ENUM('active', 'closed', 'archived') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_assignments_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_assignments_author` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
    INDEX `idx_assignments_due` (`due_date`),
    INDEX `idx_assignments_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `submissions` (
    `submission_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `assignment_id` INT UNSIGNED NOT NULL,
    `student_id` INT UNSIGNED NOT NULL,
    `submission_text` TEXT NOT NULL,
    `file_path` VARCHAR(255) NULL,
    `status` ENUM('submitted', 'graded', 'late') NOT NULL DEFAULT 'submitted',
    `marks_obtained` DECIMAL(5,2) NULL DEFAULT NULL,
    `feedback` TEXT NULL,
    `submitted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_submissions_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`assignment_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_submissions_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_assignment_student` (`assignment_id`, `student_id`),
    INDEX `idx_submissions_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `assignments` (`assignment_id`, `course_id`, `title`, `description`, `due_date`, `max_marks`, `created_by`, `status`) VALUES
(1, 1, 'Responsive StudentHub Portal Redesign', 'Develop a mobile-responsive dashboard using CSS Flexbox, Grid, and media queries.', '2026-11-05 23:59:59', 50, 2, 'active'),
(2, 2, 'Database Normalization & BCNF Proof', 'Normalize an unnormalized university management schema to 3NF and BCNF with functional dependencies.', '2026-11-10 23:59:59', 50, 2, 'active'),
(3, 3, 'TCP/IP Packet Sniffing & Wireshark Analysis', 'Analyze 3-way TCP handshake and DNS resolution packets captured on campus LAN.', '2026-11-15 23:59:59', 30, 2, 'active'),
(4, 4, 'AVL Tree Balancing & Graph Traversal Implementation', 'Implement AVL self-balancing insertion/deletion and Dijkstra shortest path in C++/Java.', '2026-11-20 23:59:59', 100, 2, 'active'),
(5, 6, 'Convolutional Neural Network for Image Recognition', 'Train a CNN model on MNIST/CIFAR-10 with at least 92% validation accuracy using PyTorch.', '2026-11-28 23:59:59', 100, 2, 'active');

-- ----------------------------------------------------------------------------
-- Practical 14: Audit Logs Schema & System History
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `log_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `action` VARCHAR(50) NOT NULL,
    `severity` ENUM('info', 'warning', 'danger', 'critical') NOT NULL DEFAULT 'info',
    `ip_address` VARCHAR(45) NOT NULL DEFAULT '127.0.0.1',
    `user_agent` VARCHAR(255) NULL,
    `details` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
    INDEX `idx_audit_action` (`action`),
    INDEX `idx_audit_severity` (`severity`),
    INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Practical 15: Database Views for Enterprise Analytics & Master Integration
-- ----------------------------------------------------------------------------
CREATE OR REPLACE VIEW `vw_admin_dashboard_summary` AS
SELECT 
    (SELECT COUNT(*) FROM `users`) AS `total_users`,
    (SELECT COUNT(*) FROM `users` WHERE `role` = 'student') AS `student_users`,
    (SELECT COUNT(*) FROM `users` WHERE `role` = 'teacher') AS `teacher_users`,
    (SELECT COUNT(*) FROM `users` WHERE `role` = 'admin') AS `admin_users`,
    (SELECT COUNT(*) FROM `students` WHERE `is_deleted` = 0) AS `active_students`,
    (SELECT COALESCE(AVG(`cgpa`), 0) FROM `students` WHERE `is_deleted` = 0) AS `avg_cgpa`,
    (SELECT COUNT(*) FROM `courses`) AS `total_courses`,
    (SELECT COUNT(*) FROM `assignments`) AS `total_assignments`,
    (SELECT COUNT(*) FROM `submissions`) AS `total_submissions`,
    (SELECT COUNT(*) FROM `events`) AS `total_events`,
    (SELECT COUNT(*) FROM `registrations`) AS `total_registrations`,
    (SELECT COUNT(*) FROM `audit_logs`) AS `total_audit_logs`;

CREATE OR REPLACE VIEW `vw_admin_monthly_registrations` AS
SELECT 
    DATE_FORMAT(`registered_at`, '%Y-%m') AS `month_year`,
    COUNT(*) AS `total_registrations`
FROM `registrations`
GROUP BY DATE_FORMAT(`registered_at`, '%Y-%m')
ORDER BY `month_year` DESC;



