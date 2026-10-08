CREATE DATABASE IF NOT EXISTS accounting_lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE accounting_lms;

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin','Faculty','Student') NOT NULL,
    two_factor_secret VARCHAR(255) NOT NULL,
    status ENUM('Active','Pending','Disabled') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(50) NOT NULL,
    course_name VARCHAR(200) NOT NULL,
    professor_id INT NULL,
    FOREIGN KEY (professor_id) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE enrollments (
    enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    semester VARCHAR(50),
    status VARCHAR(30) DEFAULT 'Active',
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE
);

CREATE TABLE chart_of_accounts (
    account_id INT AUTO_INCREMENT PRIMARY KEY,
    account_code VARCHAR(30) NOT NULL UNIQUE,
    account_name VARCHAR(150) NOT NULL,
    account_type ENUM('Asset','Liability','Equity','Revenue','Expense') NOT NULL,
    description VARCHAR(255) NULL
);

CREATE TABLE journal_entries (
    entry_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    entry_date DATE NOT NULL,
    description VARCHAR(255) NOT NULL,
    ai_validation_status ENUM('Valid','Invalid') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE journal_lines (
    line_id INT AUTO_INCREMENT PRIMARY KEY,
    entry_id INT NOT NULL,
    account_id INT NOT NULL,
    debit_amount DECIMAL(15,2) DEFAULT 0,
    credit_amount DECIMAL(15,2) DEFAULT 0,
    FOREIGN KEY (entry_id) REFERENCES journal_entries(entry_id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES chart_of_accounts(account_id)
);

CREATE TABLE learning_modules (
    module_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content_type VARCHAR(50) NOT NULL,
    content_url TEXT,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE
);

CREATE TABLE assessments (
    assessment_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    type ENUM('Quiz','Examination','Exercise') NOT NULL,
    title VARCHAR(200) NOT NULL,
    total_points DECIMAL(10,2) DEFAULT 0,
    schedule_datetime DATETIME NULL,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE
);

CREATE TABLE assessment_questions (
    question_id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type VARCHAR(50) NOT NULL,
    correct_answer TEXT,
    FOREIGN KEY (assessment_id) REFERENCES assessments(assessment_id) ON DELETE CASCADE
);

CREATE TABLE assessment_submissions (
    submission_id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    student_id INT NOT NULL,
    answers TEXT,
    score DECIMAL(10,2) DEFAULT 0,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assessment_id) REFERENCES assessments(assessment_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    instructions TEXT,
    due_date DATETIME NULL,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE
);

CREATE TABLE task_submissions (
    submission_id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    topic VARCHAR(200),
    description TEXT,
    file_path VARCHAR(255),
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE grades (
    grade_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    assessment_id INT NULL,
    assignment_id INT NULL,
    score DECIMAL(10,2) NOT NULL,
    max_score DECIMAL(10,2) NOT NULL DEFAULT 100,
    date_recorded DATE NOT NULL,
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE
);

CREATE TABLE audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    audience VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
);

INSERT INTO users (name,email,password,role,two_factor_secret) VALUES
('System Administrator','admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC8j4p4wWv7M8rYj1m3a', 'Admin', 'MTIzNDU2Nzg5MDEyMzQ1Ng=='),
('Faculty Demo','faculty@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC8j4p4wWv7M8rYj1m3a', 'Faculty', 'MTIzNDU2Nzg5MDEyMzQ1Ng=='),
('Student Demo','student@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC8j4p4wWv7M8rYj1m3a', 'Student', 'MTIzNDU2Nzg5MDEyMzQ1Ng==');

INSERT INTO chart_of_accounts (account_code,account_name,account_type,description) VALUES
('101','Cash','Asset','Cash available for business operations'),
('110','Accounts Receivable','Asset','Amounts collectible from customers'),
('201','Accounts Payable','Liability','Amounts owed to suppliers and creditors'),
('301','Owner Capital','Equity',"Owner's investment in the business"),
('401','Service Revenue','Revenue','Income earned from services rendered'),
('501','Rent Expense','Expense','Cost of rent for business premises');

INSERT INTO courses (course_code,course_name,professor_id)
SELECT 'ACCT101','Fundamentals of Accounting', user_id FROM users WHERE email='faculty@example.com';

INSERT INTO enrollments (student_id,course_id,semester)
SELECT s.user_id,c.course_id,'2026-2027 1st Semester'
FROM users s CROSS JOIN courses c
WHERE s.email='student@example.com';

-- ============================================================
-- MIGRATION (run this instead if your accounting_lms database
-- already exists and you don't want to recreate it from scratch)
-- ============================================================
-- ALTER TABLE chart_of_accounts ADD COLUMN description VARCHAR(255) NULL AFTER account_type;
-- ALTER TABLE users ADD COLUMN status ENUM('Active','Pending','Disabled') NOT NULL DEFAULT 'Active' AFTER two_factor_secret;
-- CREATE TABLE notifications (
--     notification_id INT AUTO_INCREMENT PRIMARY KEY,
--     audience VARCHAR(100) NOT NULL,
--     message TEXT NOT NULL,
--     created_by INT NULL,
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
-- );
-- ALTER TABLE grades ADD COLUMN max_score DECIMAL(10,2) NOT NULL DEFAULT 100 AFTER score;
