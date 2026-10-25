-- ========================================================
-- StudentHub — Migration for Student Approvals & Marks System
-- ========================================================

-- 1. Modify students status to support pending self-registrations
ALTER TABLE students 
MODIFY COLUMN status ENUM('active','inactive','pending') NOT NULL DEFAULT 'pending';

-- 2. Track registration source (admin vs self)
ALTER TABLE students 
ADD COLUMN IF NOT EXISTS registration_source ENUM('admin','self') NOT NULL DEFAULT 'self' AFTER role;

-- 3. Add student profile fields
ALTER TABLE students 
ADD COLUMN IF NOT EXISTS enrollment_no VARCHAR(50) NULL AFTER gender,
ADD COLUMN IF NOT EXISTS dob DATE NULL AFTER enrollment_no,
ADD COLUMN IF NOT EXISTS address TEXT NULL AFTER dob;

-- 4. Create student marks table
CREATE TABLE IF NOT EXISTS student_marks (
    mark_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    semester VARCHAR(20) NOT NULL DEFAULT '1st Semester',
    subject_name VARCHAR(100) NOT NULL,
    subject_code VARCHAR(20) NULL DEFAULT '',
    internal_marks INT NOT NULL DEFAULT 0,
    external_marks INT NOT NULL DEFAULT 0,
    total_marks INT NOT NULL DEFAULT 0,
    grade VARCHAR(5) NOT NULL DEFAULT 'A',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
