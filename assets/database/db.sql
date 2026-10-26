-- Smart Choice Academy (SCA) - Integrated Database & Ledger Automation Schema
-- Target Environment: MySQL / MariaDB via XAMPP

CREATE DATABASE IF NOT EXISTS sms_db;
USE sms_db;

-- =========================================================================
-- 1. SECURITY & IDENTITY ACCESS MANAGEMENT (IAM)
-- =========================================================================

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reg_id VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('student', 'teacher', 'guardian', 'admin') NOT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    permissions JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL
);

-- =========================================================================
-- 2. ACADEMIC STRUCTURES
-- =========================================================================

CREATE TABLE IF NOT EXISTS academic_years (
    id INT AUTO_INCREMENT PRIMARY KEY,
    year_name VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_current ENUM('yes', 'no') DEFAULT 'no',
    status ENUM('active', 'inactive', 'archived') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(50) NOT NULL,
    numeric_grade INT NOT NULL,
    capacity INT NOT NULL DEFAULT 40,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_grade (numeric_grade)
);

CREATE TABLE IF NOT EXISTS sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_name VARCHAR(20) NOT NULL,
    class_id INT NOT NULL,
    capacity INT NOT NULL DEFAULT 40,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_section_class (section_name, class_id)
);

-- =========================================================================
-- 3. HUMAN RESOURCES (STUDENTS & TEACHERS)
-- =========================================================================

CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reg_id VARCHAR(50) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    id_proof VARCHAR(100),
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    religion VARCHAR(50) NOT NULL,
    date_of_birth DATE,
    previous_school VARCHAR(255),
    last_exam_percentage VARCHAR(20),
    admission_type ENUM('New Admission', 'Transfer') DEFAULT 'New Admission',
    academic_year_id INT,
    class_id INT,
    section_id INT,
    father_name VARCHAR(100),
    father_phone VARCHAR(20),
    mother_name VARCHAR(100),
    mother_phone VARCHAR(20),
    photo VARCHAR(255),
    father_cnic VARCHAR(255),
    mother_cnic VARCHAR(255),
    birth_certificate VARCHAR(255),
    marksheet VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE SET NULL,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS teachers (
    teacher_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    employee_id VARCHAR(50) UNIQUE NOT NULL,
    qualification VARCHAR(255),
    experience_years INT DEFAULT 0,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    date_of_birth DATE,
    phone VARCHAR(20) NOT NULL,
    address TEXT,
    joining_date DATE,
    salary DECIMAL(10,2),
    status ENUM('active', 'inactive') DEFAULT 'active',
    photo VARCHAR(255),
    resume VARCHAR(255),
    doc_matric VARCHAR(255),
    doc_inter VARCHAR(255),
    doc_bachelors VARCHAR(255),
    doc_masters VARCHAR(255),
    doc_mphil VARCHAR(255),
    doc_phd VARCHAR(255),
    doc_experience VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_teacher_status (status),
    INDEX idx_employee_id (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 4. CURRICULUM & MATERIAL OPERATIONS
-- =========================================================================

CREATE TABLE IF NOT EXISTS books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_title VARCHAR(255) NOT NULL,
    author VARCHAR(255) DEFAULT NULL,
    class_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_name VARCHAR(255) NOT NULL,
    course_code VARCHAR(100) DEFAULT NULL,
    class_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 5. FEE STRUCTURE LAYOUT
-- =========================================================================

CREATE TABLE IF NOT EXISTS fee_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT
);

CREATE TABLE IF NOT EXISTS fee_structures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    academic_year_id INT NOT NULL,
    class_id INT NOT NULL,
    fee_type_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    frequency ENUM('monthly', 'quarterly', 'yearly') NOT NULL,
    due_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_type_id) REFERENCES fee_types(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS fee_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    fee_structure_id INT NOT NULL,
    amount_paid DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('Cash', 'Bank', 'Online') NOT NULL,
    payment_date DATE NOT NULL,
    receipt_no VARCHAR(50) NOT NULL,
    status ENUM('paid', 'pending', 'cancelled') DEFAULT 'paid',
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_structure_id) REFERENCES fee_structures(id)
);

-- =========================================================================
-- 6. DOUBLE-ENTRY GENERAL LEDGER (GL) CORE ARCHITECTURE
-- =========================================================================

CREATE TABLE IF NOT EXISTS accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_name VARCHAR(255) NOT NULL,
    account_code VARCHAR(100) NOT NULL UNIQUE,
    account_type ENUM('Asset', 'Liability', 'Equity', 'Revenue', 'Expense') NOT NULL,
    status ENUM('active', 'disabled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS account_mappings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_trigger VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NOT NULL,
    account_id INT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `voucher_no` VARCHAR(50) NOT NULL UNIQUE,
    `transaction_date` DATE NOT NULL,
    `reference` VARCHAR(100) DEFAULT NULL,
    `description` TEXT NOT NULL,
    `total_amount` DECIMAL(10, 2) NOT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_trx_date` (`transaction_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transaction_details` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `transaction_id` INT NOT NULL,
    `account_id` INT NOT NULL,
    `debit` DECIMAL(10, 2) DEFAULT '0.00',
    `credit` DECIMAL(10, 2) DEFAULT '0.00',
    `description` VARCHAR(255) DEFAULT NULL,
    CONSTRAINT `fk_ledger_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ledger_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT,
    INDEX `idx_ledger_account` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS school_days (
    id INT AUTO_INCREMENT PRIMARY KEY,
    day_name ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    is_working BOOLEAN DEFAULT TRUE,
    UNIQUE KEY (day_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS time_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    period_number INT NOT NULL,
    slot_name VARCHAR(50) NOT NULL, -- e.g., 'First Period', 'Break'
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_break BOOLEAN DEFAULT FALSE,
    UNIQUE KEY (period_number, is_break)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS timetable_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    academic_year_id INT NOT NULL,
    day_id INT NOT NULL,
    slot_id INT NOT NULL,
    class_id INT NOT NULL,
    section_id INT NOT NULL,
    teacher_id INT NOT NULL,
    course_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
    FOREIGN KEY (day_id) REFERENCES school_days(id) ON DELETE CASCADE,
    FOREIGN KEY (slot_id) REFERENCES time_slots(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_timetable_slot (academic_year_id, day_id, slot_id, section_id),
    UNIQUE KEY unique_teacher_slot (academic_year_id, day_id, slot_id, teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS teacher_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    course_id INT NOT NULL,
    section_id INT NOT NULL,
    academic_year_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (teacher_id, course_id, section_id, academic_year_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `exam_terms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `term_name` varchar(100) NOT NULL,
  `session_year` varchar(20) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `exam_marks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `term_id` INT NOT NULL,
  `class_id` INT NOT NULL,
  `section_id` INT NOT NULL,
  `subject_name` VARCHAR(100) NOT NULL,
  `student_id` INT NOT NULL,
  `total_marks` DECIMAL(5,2) DEFAULT 100.00,
  `passing_marks` DECIMAL(5,2) DEFAULT 33.00,
  `obtained_marks` DECIMAL(5,2) DEFAULT NULL,
  `exam_date` DATE NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_marks` (`term_id`, `class_id`, `section_id`, `subject_name`, `student_id`)
) ENGINE=InnoDB;

-- 2. Drop the existing unique constraint to allow modification
ALTER TABLE `exam_marks` DROP INDEX `unique_student_assessment`;

-- 3. Re-create the unique index including the section_id
-- This ensures you cannot accidentally add duplicate marks for the same student/term/subject/section
ALTER TABLE `exam_marks` 
ADD UNIQUE KEY `unique_student_assessment` (`term_id`, `class_id`, `section_id`, `subject_name`, `student_id`);
  -- Prevent duplicate evaluations per student/term/subject and enable clean UPSERTS
  UNIQUE KEY `unique_student_assessment` (`term_id`, `class_id`, `subject_name`, `student_id`)
 ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academic_calendar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_title VARCHAR(255) NOT NULL,
    event_date DATE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE `exam_datesheets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `term_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `exam_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room_number` varchar(50) DEFAULT NULL,
  `total_marks` decimal(5,2) DEFAULT 100.00,
  `passing_marks` decimal(5,2) DEFAULT 33.00,
  `status` enum('scheduled','completed','cancelled') DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_exam_slot` (`term_id`,`class_id`,`subject_name`,`exam_date`),
  KEY `class_id` (`class_id`),
  KEY `idx_exam_date` (`exam_date`),
  KEY `idx_term_class` (`term_id`,`class_id`),
  CONSTRAINT `exam_datesheets_ibfk_1` FOREIGN KEY (`term_id`) REFERENCES `exam_terms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_datesheets_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci

CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    section_id INT NOT NULL,
    course_id INT NOT NULL,
    teacher_id INT NOT NULL,
    academic_year_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    period_id INT,
    status ENUM('present', 'absent', 'late', 'excused') DEFAULT 'absent',
    remarks TEXT,
    marked_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)

-- =========================================================================
-- 7. NOTICES & ANNOUNCEMENTS
-- =========================================================================
CREATE TABLE IF NOT EXISTS notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    target_audience ENUM('all', 'students', 'teachers', 'parents', 'admin') DEFAULT 'all',
    class_id INT DEFAULT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 8. DAILY DIARY
-- =========================================================================
CREATE TABLE IF NOT EXISTS daily_diary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    section_id INT NOT NULL,
    subject_id INT DEFAULT NULL,
    entry_date DATE NOT NULL,
    title VARCHAR(255) DEFAULT NULL,
    homework TEXT,
    activities TEXT,
    notes TEXT,
    teacher_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 9. SYLLABUS / STUDY PLAN
-- =========================================================================
CREATE TABLE IF NOT EXISTS syllabus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    section_id INT DEFAULT NULL,
    subject_id INT DEFAULT NULL,
    term_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path VARCHAR(255) DEFAULT NULL,
    uploaded_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE SET NULL,
    FOREIGN KEY (term_id) REFERENCES exam_terms(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS study_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT DEFAULT NULL,
    class_id INT DEFAULT NULL,
    section_id INT DEFAULT NULL,
    subject_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path VARCHAR(255) DEFAULT NULL,
    week_start DATE DEFAULT NULL,
    week_end DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE SET NULL,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 10. AWARDS & AWARD LISTS
-- =========================================================================
CREATE TABLE IF NOT EXISTS awards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    award_name VARCHAR(255) NOT NULL,
    description TEXT,
    academic_year_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS award_students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    award_id INT NOT NULL,
    student_id INT NOT NULL,
    position VARCHAR(50) DEFAULT NULL,
    remarks VARCHAR(255) DEFAULT NULL,
    awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (award_id) REFERENCES awards(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_award_student (award_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 11. PARENT MESSAGES (Communication between Parents <-> Teachers)
-- =========================================================================
CREATE TABLE IF NOT EXISTS parent_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT DEFAULT NULL,
    student_id INT DEFAULT NULL,
    subject VARCHAR(255) DEFAULT NULL,
    message TEXT NOT NULL,
    parent_read TINYINT DEFAULT 0,
    teacher_read TINYINT DEFAULT 0,
    status ENUM('sent', 'read', 'replied') DEFAULT 'sent',
    reply_to INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
    FOREIGN KEY (reply_to) REFERENCES parent_messages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 12. LEAVE APPLICATIONS
-- =========================================================================
CREATE TABLE IF NOT EXISTS leave_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    guardian_user_id INT DEFAULT NULL,
    leave_type ENUM('sick', 'personal', 'family', 'other') DEFAULT 'personal',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (guardian_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 13. SALARY SLIPS
-- =========================================================================
CREATE TABLE IF NOT EXISTS salary_slips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    slip_month VARCHAR(20) NOT NULL,
    basic_salary DECIMAL(10,2) DEFAULT 0,
    allowances DECIMAL(10,2) DEFAULT 0,
    deductions DECIMAL(10,2) DEFAULT 0,
    net_salary DECIMAL(10,2) DEFAULT 0,
    generated_by INT DEFAULT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_teacher_month (teacher_id, slip_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 14. STUDENT PROMOTIONS
-- =========================================================================
CREATE TABLE IF NOT EXISTS student_promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    from_class_id INT DEFAULT NULL,
    to_class_id INT DEFAULT NULL,
    from_section_id INT DEFAULT NULL,
    to_section_id INT DEFAULT NULL,
    academic_year_id INT DEFAULT NULL,
    promoted_by INT DEFAULT NULL,
    promoted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (from_class_id) REFERENCES classes(id) ON DELETE SET NULL,
    FOREIGN KEY (to_class_id) REFERENCES classes(id) ON DELETE SET NULL,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 15. CHALLANS
-- =========================================================================
CREATE TABLE IF NOT EXISTS challans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    challan_no VARCHAR(50) NOT NULL UNIQUE,
    student_id INT NOT NULL,
    fee_structure_id INT DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL,
    month VARCHAR(20) DEFAULT NULL,
    due_date DATE DEFAULT NULL,
    status ENUM('pending', 'paid', 'cancelled', 'overdue') DEFAULT 'pending',
    generated_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_structure_id) REFERENCES fee_structures(id) ON DELETE SET NULL,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 16. EXPERIENCE LETTERS
-- =========================================================================
CREATE TABLE IF NOT EXISTS experience_letters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    letter_no VARCHAR(50) DEFAULT NULL,
    issued_date DATE DEFAULT NULL,
    content TEXT,
    generated_by INT DEFAULT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 17. GUARDIAN LINKAGE (Add guardian_id to students)
-- =========================================================================
ALTER TABLE students ADD COLUMN IF NOT EXISTS guardian_user_id INT DEFAULT NULL;

-- =========================================================================
-- 18. SEED DEFAULT NOTICE (Sample)
-- =========================================================================
INSERT INTO notices (title, content, target_audience, status)
SELECT 'Welcome to Smart Choice Academy', 'Welcome to the new academic session. Please check the academic calendar for important dates.', 'all', 'active'
WHERE NOT EXISTS (SELECT 1 FROM notices WHERE title = 'Welcome to Smart Choice Academy');

-- =========================================================================
-- 19. CORE SEEDS (CHART OF ACCOUNTS & ADVANCED TRIGGERS MAP)
-- =========================================================================
INSERT INTO school_days (id, day_name, is_working) VALUES
(1, 'Monday', TRUE),
(2, 'Tuesday', TRUE),
(3, 'Wednesday', TRUE),
(4, 'Thursday', TRUE),
(5, 'Friday', TRUE),
(6, 'Saturday', TRUE),
(7, 'Sunday', FALSE);
-- Seed Chart of Accounts with explicit pure numeric codes
INSERT INTO accounts (id, account_name, account_code, account_type) VALUES
(1, 'Cash Account (Hand/Vault)', '1010', 'Asset'),
(2, 'Main Operating Bank Account', '1020', 'Asset'),
(3, 'Tuition Fee Receivables', '1200', 'Asset'),
(4, 'Transport Fee Receivables', '1210', 'Asset'),
(5, 'General Student Fee Revenues', '4010', 'Revenue'),
(6, 'Transport Route Revenues', '4030', 'Revenue'),
(7, 'Staff Payroll Expense', '5010', 'Expense'),
(8, 'Utility Bills', '5030', 'Expense')
ON DUPLICATE KEY UPDATE account_code=VALUES(account_code);

-- Seed System Configuration Flowchart Alignment Matrix
INSERT INTO account_mappings (module_trigger, description, account_id) VALUES
('RECEIVABLE_TUITION', 'Asset account tracking outstanding/unpaid Tuition fees.', 3),
('RECEIVABLE_TRANSPORT', 'Asset account tracking outstanding/unpaid Transport fees.', 4),
('REVENUE_TUITION', 'Income statement revenue head for recognized Tuition fee earnings.', 5),
('REVENUE_TRANSPORT', 'Income statement revenue head for recognized Transport fee earnings.', 6),
('ASSET_CASH_DESK', 'Primary physical cash drawer/safe account for on-site collections.', 1),
('ASSET_BANK_OPERATING', 'Main institutional check and online clearing bank account.', 2),
('LIABILITY_PAYABLE_SUPPLIER', 'Accounts Payable tracking control for supplier bills and system vendor invoices.', NULL),
('EXPENSE_ACADEMIC_PAYROLL', 'Operational clearing expense head for internal instructional resource salaries.', 7),
('EXPENSE_UTILITIES_GENERAL', 'Operational expense head tracking utility consumption payments.', 8)
ON DUPLICATE KEY UPDATE account_id=VALUES(account_id);

-- =========================================================================
-- 8. COMPLIANT AUTOMATED DOUBLE-ENTRY ACCOUNTING ENGINE TRIGGER
-- =========================================================================

DELIMITER $$

CREATE TRIGGER `after_fee_payment_posted`
AFTER INSERT ON `fee_payments`
FOR EACH ROW
BEGIN
    -- Intercept fee rows and process automated double-entries if state is 'paid'
    IF NEW.`status` = 'paid' THEN
        
        -- A. Fetch structural fee metadata context
        SET @fee_name = (
            SELECT ft.name 
            FROM fee_structures fs
            JOIN fee_types ft ON fs.fee_type_id = ft.id
            WHERE fs.id = NEW.`fee_structure_id` 
            LIMIT 1
        );

        -- B. Resolve Flowchart Module Triggers dynamically based on name
        IF @fee_name LIKE '%Transport%' THEN
            SET @revenue_trigger    = 'REVENUE_TRANSPORT';
            SET @receivable_trigger = 'RECEIVABLE_TRANSPORT';
        ELSE
            SET @revenue_trigger    = 'REVENUE_TUITION';
            SET @receivable_trigger = 'RECEIVABLE_TUITION';
        END IF;

        -- C. Extract physical Account Head unique IDs from mapping configuration records
        SET @revenue_acc_id    = (SELECT `account_id` FROM `account_mappings` WHERE `module_trigger` = @revenue_trigger LIMIT 1);
        SET @receivable_acc_id = (SELECT `account_id` FROM `account_mappings` WHERE `module_trigger` = @receivable_trigger LIMIT 1);

        -- D. Isolate the target Cash vs Bank liquid Asset repository
        IF NEW.`payment_method` = 'Cash' THEN
            SET @asset_acc_id = (SELECT `account_id` FROM `account_mappings` WHERE `module_trigger` = 'ASSET_CASH_DESK' LIMIT 1);
        ELSE
            SET @asset_acc_id = (SELECT `account_id` FROM `account_mappings` WHERE `module_trigger` = 'ASSET_BANK_OPERATING' LIMIT 1);
        END IF;

        -- E. Safety bounds fallback targets to prevent FK Constraint Violation drops
        IF @revenue_acc_id IS NULL THEN SET @revenue_acc_id = 5; END IF;
        IF @receivable_acc_id IS NULL THEN SET @receivable_acc_id = 3; END IF;
        IF @asset_acc_id IS NULL THEN 
            IF NEW.`payment_method` = 'Cash' THEN SET @asset_acc_id = 1; ELSE SET @asset_acc_id = 2; END IF;
        END IF;

        -- F. Formulate transaction tracing metadata elements
        SET @student_name = (SELECT CONCAT(`first_name`, ' ', `last_name`) FROM `students` WHERE `id` = NEW.`student_id` LIMIT 1);
        SET @voucher_ident = CONCAT('JV-FEE-', NEW.`receipt_no`);
        SET @narration_details = CONCAT('Flowchart real-time allocation loop for student: ', IFNULL(@student_name, 'SCA Student'), ' [Item Type: ', IFNULL(@fee_name, 'General Fee'), ', Rec: #', NEW.`receipt_no`, ']');

        -- G. Write transaction master header profile row
        INSERT INTO `transactions` (`voucher_no`, `transaction_date`, `reference`, `description`, `total_amount`, `created_by`)
        VALUES (@voucher_ident, NEW.`payment_date`, NEW.`receipt_no`, @narration_details, NEW.`amount_paid`, NULL);
        
        SET @last_trx_id = LAST_INSERT_ID();

        -- H. Write multi-tier balanced double-entry lines
        -- Line 1: Credit Operational Revenue (Income Statement Recognition)
        INSERT INTO `transaction_details` (`transaction_id`, `account_id`, `debit`, `credit`, `description`)
        VALUES (@last_trx_id, @revenue_acc_id, 0.00, NEW.`amount_paid`, CONCAT('Cr. Revenue Realized - ', @fee_name));

        -- Line 2: Debit Accounts Receivable (Trace open charge tracking)
        INSERT INTO `transaction_details` (`transaction_id`, `account_id`, `debit`, `credit`, `description`)
        VALUES (@last_trx_id, @receivable_acc_id, NEW.`amount_paid`, 0.00, 'Dr. Accounts Receivable (Generate Charge)');

        -- Line 3: Debit liquid Cash/Bank Asset (Add liquid realization value to system)
        INSERT INTO `transaction_details` (`transaction_id`, `account_id`, `debit`, `credit`, `description`)
        VALUES (@last_trx_id, @asset_acc_id, NEW.`amount_paid`, 0.00, CONCAT('Dr. Asset Portfolio via Mode: ', NEW.`payment_method`));

        -- Line 4: Credit Accounts Receivable (Wipe out outstanding balance item cleanly)
        INSERT INTO `transaction_details` (`transaction_id`, `account_id`, `debit`, `credit`, `description`)
        VALUES (@last_trx_id, @receivable_acc_id, 0.00, NEW.`amount_paid`, 'Cr. Accounts Receivable (Clear Balances)');

    END IF;
END$$

DELIMITER ;