-- 1. Add role column for RBAC (default 'student')
ALTER TABLE students 
ADD COLUMN role ENUM('student', 'admin') NOT NULL DEFAULT 'student' AFTER gender;

-- 2. Add status column for Practical 11 Soft Delete (default 'active')
ALTER TABLE students 
ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' AFTER role;

-- 3. Add remember_token and token_expiry for Secure Remember-Me (Practical 10)
ALTER TABLE students 
ADD COLUMN remember_token VARCHAR(64) NULL AFTER password,
ADD COLUMN remember_expiry DATETIME NULL AFTER remember_token;

-- 4. Seed an Admin Account for testing Practical 10 & 11
-- (Default admin: admin@studenthub.com / Admin@123)
INSERT INTO students (name, email, mobile, course, year, gender, role, status, password)
VALUES (
    'System Administrator',
    'admin@studenthub.com',
    '9999999999',
    'Administration',
    'Faculty',
    'Other',
    'admin',
    'active',
    '$2y$10$aA17wyaqkGUogX0C03SEOeIZPo.B1laTl0B8Xrw112PxzzyLA7TEW'
) ON DUPLICATE KEY UPDATE role='admin';