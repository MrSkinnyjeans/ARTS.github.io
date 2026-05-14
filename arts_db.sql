-- ============================================================
--  ARTS – Academic Referral Tracking System
--  Updated schema with principal role, access requests,
--  scholarship tracking
-- ============================================================

DROP DATABASE IF EXISTS arts_db;
CREATE DATABASE arts_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE arts_db;

-- ── Users ────────────────────────────────────────────────────
CREATE TABLE users (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  username   VARCHAR(50)  NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  full_name  VARCHAR(100) NOT NULL,
  email      VARCHAR(120) NOT NULL DEFAULT '',
  role       ENUM('principal','admin') DEFAULT 'admin',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- principal / principal123  →  School Principal (highest authority)
-- admin     / admin123      →  Admissions Admin
INSERT INTO users (username, password, full_name, email, role) VALUES
  ('principal', 'principal123', 'School Principal', 'principal@yourdomain.com', 'principal'),
  ('admin',     'admin123',     'Administrator',    'admin@yourdomain.com',     'admin');

-- ── Access Requests ──────────────────────────────────────────
CREATE TABLE access_requests (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  full_name    VARCHAR(100) NOT NULL,
  email        VARCHAR(120) NOT NULL,
  position     VARCHAR(100) NOT NULL,
  department   VARCHAR(100),
  reason       TEXT,
  status       ENUM('pending','approved','rejected') DEFAULT 'pending',
  reviewed_by  INT,
  reviewed_at  TIMESTAMP NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ── Applicants ───────────────────────────────────────────────
CREATE TABLE applicants (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  fname        VARCHAR(80)  NOT NULL,
  lname        VARCHAR(80)  NOT NULL,
  contact      VARCHAR(30),
  email        VARCHAR(120),
  school_from  VARCHAR(150),
  date_applied DATE,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── Referrals ────────────────────────────────────────────────
CREATE TABLE referrals (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  applicant_id  INT NOT NULL,
  ref_type      ENUM('Teacher','Alumni','Partner School','Walk-in','Online') NOT NULL,
  referred_by   VARCHAR(100),
  organization  VARCHAR(150),
  date_referred DATE,
  notes         TEXT,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
);

-- ── Exam Results (with scholarship tracking) ─────────────────
CREATE TABLE exam_results (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  applicant_id        INT NOT NULL UNIQUE,
  exam_date           DATE,
  score               DECIMAL(5,2) NOT NULL,
  cutoff_score        DECIMAL(5,2) NOT NULL DEFAULT 75.00,
  status              ENUM('Passed','Failed') NOT NULL,
  remarks             VARCHAR(200),
  -- Scholarship fields (managed by Principal)
  scholarship_status  ENUM('Not Evaluated','For Review','Approved','Rejected') DEFAULT 'Not Evaluated',
  scholarship_type    VARCHAR(100),
  scholarship_notes   TEXT,
  approved_by         INT,
  approved_at         TIMESTAMP NULL,
  created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE,
  FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ── Sample Data ──────────────────────────────────────────────
INSERT INTO applicants (fname, lname, contact, email, school_from, date_applied) VALUES
  ('Maria',   'Santos',   '0917-123-4567', 'maria@mail.com',   'Mandaue City National HS',     '2026-01-10'),
  ('Joshua',  'Reyes',    '0918-234-5678', 'josh@mail.com',    'Cebu Institute of Technology', '2026-01-12'),
  ('Andrea',  'Gomez',    '0919-345-6789', 'andrea@mail.com',  'Cebu City National HS',        '2026-01-15'),
  ('Liam',    'Cruz',     '0920-456-7890', 'liam@mail.com',    'University of Cebu HS Dept',   '2026-01-18'),
  ('Sofia',   'Bautista', '0921-567-8901', 'sofia@mail.com',   'Lapu-Lapu City HS',            '2026-01-20');

INSERT INTO referrals (applicant_id, ref_type, referred_by, organization, date_referred, notes) VALUES
  (1, 'Teacher',        'Mr. Dela Torre', 'Mandaue City NHS',        '2026-01-09', ''),
  (2, 'Alumni',         'Ms. Rivera',     'ACLC Mandaue Batch 2022', '2026-01-11', ''),
  (3, 'Partner School', 'Dr. Tan',        'Cebu City NHS',           '2026-01-14', ''),
  (4, 'Walk-in',        'Self',           'N/A',                     '2026-01-18', 'Walk-in applicant'),
  (5, 'Online',         'Social Media',   'Facebook Ad',             '2026-01-19', '');

INSERT INTO exam_results (applicant_id, exam_date, score, cutoff_score, status, remarks, scholarship_status) VALUES
  (1, '2026-01-22', 88, 75, 'Passed', 'Excellent',         'For Review'),
  (2, '2026-01-22', 62, 75, 'Failed', 'Needs improvement', 'Not Evaluated'),
  (3, '2026-01-22', 79, 75, 'Passed', 'Good',              'For Review'),
  (4, '2026-01-22', 74, 75, 'Failed', 'Very close',        'Not Evaluated');

-- Sample access requests
INSERT INTO access_requests (full_name, email, position, department, reason, status) VALUES
  ('Ms. Carla Reyes',  'creyes@aclc.edu.ph',  'Guidance Counselor', 'Student Affairs', 'Need access to track referred students for counseling follow-up.', 'pending'),
  ('Mr. Ben Santos',   'bsantos@aclc.edu.ph', 'Registrar Staff',    'Registrar',       'Required to verify applicant records for enrollment processing.',  'pending');
