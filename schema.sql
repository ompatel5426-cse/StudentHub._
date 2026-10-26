CREATE DATABASE IF NOT EXISTS studenthub;

USE studenthub;

-- =========================
-- STUDENTS TABLE
-- =========================

CREATE TABLE IF NOT EXISTS students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    mobile VARCHAR(15),
    course VARCHAR(100),
    year VARCHAR(20),
    gender VARCHAR(20),
    password VARCHAR(255) NOT NULL
) ENGINE=InnoDB;


-- =========================
-- EVENTS TABLE
-- =========================

CREATE TABLE IF NOT EXISTS events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(150) NOT NULL,
    event_date DATE NOT NULL,
    venue VARCHAR(150),
    description TEXT,
    UNIQUE KEY unique_event_name (event_name)
) ENGINE=InnoDB;


-- =========================
-- REGISTRATIONS TABLE
-- =========================

CREATE TABLE IF NOT EXISTS registrations (
    registration_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    event_id INT NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_student
        FOREIGN KEY (student_id)
        REFERENCES students(student_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_event
        FOREIGN KEY (event_id)
        REFERENCES events(event_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;


-- =========================
-- TEST DATA
-- =========================

INSERT INTO students
(name, email, mobile, course, year, gender, password)
VALUES
(
    'Rahul Sharma',
    'rahul@gmail.com',
    '9876543210',
    'Computer Science Engineering',
    '3rd Year',
    'Male',
    '$2y$10$i8Avy4xPlJj4aoLSBsm/5ek9T7RyC6IzAfLZ6E5N4t46ESF7/1g8C'
) ON DUPLICATE KEY UPDATE password = VALUES(password);


INSERT INTO events
(event_name, event_date, venue, description)
VALUES
(
    'Web Development Workshop',
    '2026-10-15',
    'CSPIT Seminar Hall',
    'Workshop on HTML, CSS, JavaScript and Web Development.'
);


INSERT INTO registrations
(student_id, event_id)
VALUES
(1, 1);