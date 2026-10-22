USE studenthub;

-- ========================================================
-- PRACTICAL 12: EVENTS TABLE ENHANCEMENTS
-- Add poster, category, and status columns to events table
-- ========================================================
ALTER TABLE events
ADD COLUMN IF NOT EXISTS poster VARCHAR(255) NULL AFTER venue,
ADD COLUMN IF NOT EXISTS category VARCHAR(50) NOT NULL DEFAULT 'Workshop' AFTER poster,
ADD COLUMN IF NOT EXISTS status ENUM('open', 'closed') NOT NULL DEFAULT 'open' AFTER category,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER status;

-- Seed additional sample events if events table has few records
INSERT INTO events (event_name, event_date, venue, description, category, status)
VALUES 
('GitHub & Git Masterclass', '2026-11-05', 'Seminar Hall A', 'Hands-on workshop on version control, branching, and team collaboration.', 'Technical', 'open'),
('Inter-College Hackathon 2026', '2026-11-12', 'Innovation Hub', '24-hour coding hackathon focusing on web and AI solutions.', 'Competition', 'open'),
('Annual Sports Meet 2026', '2026-11-20', 'University Sports Ground', 'Athletics, football, cricket and indoor sports championships.', 'Sports', 'open'),
('Cloud Computing & DevOps Seminar', '2026-11-28', 'Auditorium 2', 'Industry experts lecture on AWS, Docker, and Kubernetes deployment.', 'Seminar', 'open')
ON DUPLICATE KEY UPDATE event_name = VALUES(event_name);

-- ========================================================
-- PRACTICAL 14: AUDIT LOGS TABLE
-- Records important administrative actions for traceability
-- ========================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    user_email VARCHAR(100) NULL,
    role VARCHAR(20) DEFAULT 'admin',
    action VARCHAR(50) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Initial audit log seed
INSERT INTO audit_logs (user_id, user_email, role, action, details, ip_address)
VALUES (2, 'admin@studenthub.com', 'admin', 'SYSTEM_INIT', 'StudentHub system initialized for Practicals 12, 13, and 14.', '127.0.0.1');
