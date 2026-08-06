-- Student Hub Database
-- MySQL compatible schema for admin, student, and faculty roles.

CREATE DATABASE student_hub;
USE student_hub;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS contact_messages;
DROP TABLE IF EXISTS result_summary;
DROP TABLE IF EXISTS results;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS assignment_submissions;
DROP TABLE IF EXISTS assignments;
DROP TABLE IF EXISTS student_courses;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS faculty_profiles;
DROP TABLE IF EXISTS student_profiles;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name ENUM('admin', 'student', 'faculty') NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    status ENUM('active', 'inactive', 'blocked') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id) REFERENCES roles(role_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

CREATE TABLE student_profiles (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    enrollment_no VARCHAR(30) NOT NULL UNIQUE,
    course_name VARCHAR(150) NOT NULL,
    department VARCHAR(100) NOT NULL,
    semester TINYINT NOT NULL,
    admission_year YEAR NOT NULL,
    CONSTRAINT fk_student_profiles_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

CREATE TABLE faculty_profiles (
    faculty_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    employee_no VARCHAR(30) NOT NULL UNIQUE,
    department VARCHAR(100) NOT NULL,
    designation VARCHAR(80) NOT NULL,
    CONSTRAINT fk_faculty_profiles_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

CREATE TABLE courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT,
    course_code VARCHAR(20) NOT NULL UNIQUE,
    course_title VARCHAR(120) NOT NULL,
    description TEXT,
    semester TINYINT NOT NULL,
    credits TINYINT NOT NULL DEFAULT 3,
    progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_courses_faculty
        FOREIGN KEY (faculty_id) REFERENCES faculty_profiles(faculty_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT chk_courses_progress
        CHECK (progress_percent >= 0 AND progress_percent <= 100)
);

CREATE TABLE student_courses (
    student_course_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    enrolled_at DATE NOT NULL,
    status ENUM('enrolled', 'completed', 'dropped') NOT NULL DEFAULT 'enrolled',
    UNIQUE KEY uq_student_course (student_id, course_id),
    CONSTRAINT fk_student_courses_student
        FOREIGN KEY (student_id) REFERENCES student_profiles(student_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_student_courses_course
        FOREIGN KEY (course_id) REFERENCES courses(course_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

CREATE TABLE assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    due_date DATE NOT NULL,
    max_marks DECIMAL(5,2) NOT NULL DEFAULT 100.00,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_assignments_course
        FOREIGN KEY (course_id) REFERENCES courses(course_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_assignments_created_by
        FOREIGN KEY (created_by) REFERENCES faculty_profiles(faculty_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
);

CREATE TABLE assignment_submissions (
    submission_id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    submission_file VARCHAR(255),
    submitted_at DATETIME,
    status ENUM('pending', 'submitted', 'late', 'graded') NOT NULL DEFAULT 'pending',
    marks_obtained DECIMAL(5,2),
    feedback TEXT,
    UNIQUE KEY uq_assignment_student (assignment_id, student_id),
    CONSTRAINT fk_assignment_submissions_assignment
        FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_assignment_submissions_student
        FOREIGN KEY (student_id) REFERENCES student_profiles(student_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

CREATE TABLE attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    total_classes INT NOT NULL DEFAULT 0,
    present_classes INT NOT NULL DEFAULT 0,
    absent_classes INT GENERATED ALWAYS AS (total_classes - present_classes) STORED,
    attendance_percent DECIMAL(5,2) GENERATED ALWAYS AS (
        CASE
            WHEN total_classes = 0 THEN 0
            ELSE ROUND((present_classes / total_classes) * 100, 2)
        END
    ) STORED,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attendance_student_course (student_id, course_id),
    CONSTRAINT fk_attendance_student
        FOREIGN KEY (student_id) REFERENCES student_profiles(student_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_attendance_course
        FOREIGN KEY (course_id) REFERENCES courses(course_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT chk_attendance_classes
        CHECK (total_classes >= 0 AND present_classes >= 0 AND present_classes <= total_classes)
);

CREATE TABLE results (
    result_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    exam_name VARCHAR(100) NOT NULL DEFAULT 'Current Semester',
    marks_obtained DECIMAL(5,2) NOT NULL,
    max_marks DECIMAL(5,2) NOT NULL DEFAULT 100.00,
    grade VARCHAR(5) NOT NULL,
    result_status ENUM('pass', 'fail') NOT NULL,
    declared_at DATE NOT NULL,
    UNIQUE KEY uq_result_student_course_exam (student_id, course_id, exam_name),
    CONSTRAINT fk_results_student
        FOREIGN KEY (student_id) REFERENCES student_profiles(student_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_results_course
        FOREIGN KEY (course_id) REFERENCES courses(course_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT chk_results_marks
        CHECK (marks_obtained >= 0 AND marks_obtained <= max_marks)
);

CREATE TABLE result_summary (
    summary_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    semester TINYINT NOT NULL,
    cgpa DECIMAL(3,1) NOT NULL,
    class_rank INT,
    summary_status ENUM('pass', 'fail') NOT NULL,
    generated_at DATE NOT NULL,
    UNIQUE KEY uq_result_summary_student_semester (student_id, semester),
    CONSTRAINT fk_result_summary_student
        FOREIGN KEY (student_id) REFERENCES student_profiles(student_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT chk_result_summary_cgpa
        CHECK (cgpa >= 0 AND cgpa <= 10)
);

CREATE TABLE contact_messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    sender_name VARCHAR(100) NOT NULL,
    sender_email VARCHAR(120) NOT NULL,
    message TEXT NOT NULL,
    handled_by INT,
    status ENUM('new', 'in_progress', 'resolved') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_contact_messages_handled_by
        FOREIGN KEY (handled_by) REFERENCES users(user_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
);

INSERT INTO roles (role_name) VALUES
('admin'),
('student'),
('faculty');

-- Demo passwords are placeholders. Store real passwords with a secure hash from your backend.
INSERT INTO users (role_id, full_name, email, password_hash, phone) VALUES
((SELECT role_id FROM roles WHERE role_name = 'admin'), 'Admin User', 'admin@studenthub.com', 'change_this_hash', '+91 90000 00001'),
((SELECT role_id FROM roles WHERE role_name = 'student'), 'Yug Kansagara', 'yug.kansagara@gmail.com', 'change_this_hash', '+91 98765 43210'),
((SELECT role_id FROM roles WHERE role_name = 'faculty'), 'Riya Shah', 'riya.shah@studenthub.com', 'change_this_hash', '+91 90000 00002'),
((SELECT role_id FROM roles WHERE role_name = 'faculty'), 'Amit Patel', 'amit.patel@studenthub.com', 'change_this_hash', '+91 90000 00003');

INSERT INTO student_profiles (user_id, enrollment_no, course_name, department, semester, admission_year) VALUES
((SELECT user_id FROM users WHERE email = 'yug.kansagara@gmail.com'), 'SH2026CE031', 'Bachelor of Technology in Computer Engineering', 'Computer Science', 3, 2026);

INSERT INTO faculty_profiles (user_id, employee_no, department, designation) VALUES
((SELECT user_id FROM users WHERE email = 'riya.shah@studenthub.com'), 'FAC2026CS001', 'Computer Science', 'Assistant Professor'),
((SELECT user_id FROM users WHERE email = 'amit.patel@studenthub.com'), 'FAC2026CS002', 'Computer Science', 'Lecturer');

INSERT INTO courses (faculty_id, course_code, course_title, description, semester, credits, progress_percent) VALUES
((SELECT faculty_id FROM faculty_profiles WHERE employee_no = 'FAC2026CS001'), 'CE301', 'Web Development', 'HTML, CSS, JavaScript, responsive design, and project building.', 3, 4, 72.00),
((SELECT faculty_id FROM faculty_profiles WHERE employee_no = 'FAC2026CS001'), 'CE302', 'Database Management', 'SQL, normalization, joins, transactions, and database design.', 3, 4, 64.00),
((SELECT faculty_id FROM faculty_profiles WHERE employee_no = 'FAC2026CS002'), 'CE303', 'Computer Networks', 'Network models, routing, IP addressing, and communication protocols.', 3, 3, 81.00);

INSERT INTO student_courses (student_id, course_id, enrolled_at) VALUES
((SELECT student_id FROM student_profiles WHERE enrollment_no = 'SH2026CE031'), (SELECT course_id FROM courses WHERE course_code = 'CE301'), '2026-07-01'),
((SELECT student_id FROM student_profiles WHERE enrollment_no = 'SH2026CE031'), (SELECT course_id FROM courses WHERE course_code = 'CE302'), '2026-07-01'),
((SELECT student_id FROM student_profiles WHERE enrollment_no = 'SH2026CE031'), (SELECT course_id FROM courses WHERE course_code = 'CE303'), '2026-07-01');

INSERT INTO assignments (course_id, title, description, due_date, created_by) VALUES
((SELECT course_id FROM courses WHERE course_code = 'CE301'), 'Responsive Website', 'Build a responsive website using HTML, CSS, and JavaScript.', '2026-07-22', (SELECT faculty_id FROM faculty_profiles WHERE employee_no = 'FAC2026CS001')),
((SELECT course_id FROM courses WHERE course_code = 'CE302'), 'SQL Queries', 'Write SQL queries using joins, filters, grouping, and sorting.', '2026-07-24', (SELECT faculty_id FROM faculty_profiles WHERE employee_no = 'FAC2026CS001')),
((SELECT course_id FROM courses WHERE course_code = 'CE303'), 'Network Diagram', 'Create a clear network diagram with routers, switches, and IP ranges.', '2026-07-29', (SELECT faculty_id FROM faculty_profiles WHERE employee_no = 'FAC2026CS002'));

INSERT INTO assignment_submissions (assignment_id, student_id, submitted_at, status, marks_obtained) VALUES
((SELECT assignment_id FROM assignments WHERE title = 'Responsive Website'), (SELECT student_id FROM student_profiles WHERE enrollment_no = 'SH2026CE031'), NULL, 'pending', NULL),
((SELECT assignment_id FROM assignments WHERE title = 'SQL Queries'), (SELECT student_id FROM student_profiles WHERE enrollment_no = 'SH2026CE031'), '2026-07-23 15:30:00', 'submitted', NULL),
((SELECT assignment_id FROM assignments WHERE title = 'Network Diagram'), (SELECT student_id FROM student_profiles WHERE enrollment_no = 'SH2026CE031'), NULL, 'pending', NULL);

INSERT INTO attendance (student_id, course_id, total_classes, present_classes) VALUES
((SELECT student_id FROM student_profiles WHERE enrollment_no = 'SH2026CE031'), (SELECT course_id FROM courses WHERE course_code = 'CE301'), 42, 38),
((SELECT student_id FROM student_profiles WHERE enrollment_no = 'SH2026CE031'), (SELECT course_id FROM courses WHERE course_code = 'CE302'), 40, 34),
((SELECT student_id FROM student_profiles WHERE enrollment_no = 'SH2026CE031'), (SELECT course_id FROM courses WHERE course_code = 'CE303'), 36, 29);

INSERT INTO results (student_id, course_id, exam_name, marks_obtained, max_marks, grade, result_status, declared_at) VALUES
((SELECT student_id FROM student_profiles WHERE enrollment_no = 'SH2026CE031'), (SELECT course_id FROM courses WHERE course_code = 'CE301'), 'Current Semester', 88, 100, 'A', 'pass', '2026-07-31'),
((SELECT student_id FROM student_profiles WHERE enrollment_no = 'SH2026CE031'), (SELECT course_id FROM courses WHERE course_code = 'CE302'), 'Current Semester', 82, 100, 'A', 'pass', '2026-07-31'),
((SELECT student_id FROM student_profiles WHERE enrollment_no = 'SH2026CE031'), (SELECT course_id FROM courses WHERE course_code = 'CE303'), 'Current Semester', 76, 100, 'B+', 'pass', '2026-07-31');

INSERT INTO result_summary (student_id, semester, cgpa, class_rank, summary_status, generated_at) VALUES
((SELECT student_id FROM student_profiles WHERE enrollment_no = 'SH2026CE031'), 3, 8.7, 12, 'pass', '2026-07-31');

INSERT INTO contact_messages (sender_name, sender_email, message, status) VALUES
('Yug Kansagara', 'yug.kansagara@gmail.com', 'Please share the latest timetable for semester 3.', 'new');
