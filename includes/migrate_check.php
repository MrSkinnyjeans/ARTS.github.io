<?php
// includes/migrate_check.php
// Silently ensures all new tables/columns exist without touching existing data.

if (!isset($db)) require_once __DIR__ . '/db.php';
$db = getDB();

$checks = [
    // Update users.role ENUM to only principal and admin
    "ALTER TABLE users MODIFY COLUMN role ENUM('principal','admin') DEFAULT 'admin'",

    // Scholarship columns on exam_results
    "ALTER TABLE exam_results ADD COLUMN IF NOT EXISTS scholarship_status ENUM('Not Evaluated','For Review','Approved','Rejected') DEFAULT 'Not Evaluated' AFTER remarks",
    "ALTER TABLE exam_results ADD COLUMN IF NOT EXISTS scholarship_type  VARCHAR(100) AFTER scholarship_status",
    "ALTER TABLE exam_results ADD COLUMN IF NOT EXISTS scholarship_notes TEXT         AFTER scholarship_type",
    "ALTER TABLE exam_results ADD COLUMN IF NOT EXISTS approved_by       INT          AFTER scholarship_notes",
    "ALTER TABLE exam_results ADD COLUMN IF NOT EXISTS approved_at       TIMESTAMP NULL AFTER approved_by",

    // Access requests table
    "CREATE TABLE IF NOT EXISTS access_requests (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        full_name    VARCHAR(100) NOT NULL,
        email        VARCHAR(120) NOT NULL,
        position     VARCHAR(100) NOT NULL,
        department   VARCHAR(100),
        reason       TEXT,
        status       ENUM('pending','approved','rejected') DEFAULT 'pending',
        granted_user_id INT,
        username     VARCHAR(50),
        temp_password VARCHAR(100),
        reviewed_by  INT,
        reviewed_at  TIMESTAMP NULL,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",

    // Messages table for chat
    "CREATE TABLE IF NOT EXISTS messages (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        sender_id   INT NOT NULL,
        receiver_id INT NOT NULL,
        message     TEXT NOT NULL,
        is_read     TINYINT(1) DEFAULT 0,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id)   REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",

    // Approved Gmail for login verification
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS approved_gmail VARCHAR(120) NOT NULL DEFAULT '' AFTER full_name",

    // Password reset token for new accounts
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS password_reset_token VARCHAR(64) NULL AFTER approved_gmail",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS token_expires TIMESTAMP NULL AFTER password_reset_token",

    // Pending access request verifications
    "CREATE TABLE IF NOT EXISTS pending_access_requests (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        full_name  VARCHAR(100) NOT NULL,
        email      VARCHAR(120) NOT NULL,
        token      VARCHAR(64)  NOT NULL UNIQUE,
        expires_at TIMESTAMP    NOT NULL,
        created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",

    // Interview and exam schedule columns for applicants
    "ALTER TABLE applicants ADD COLUMN IF NOT EXISTS interview_date DATETIME NULL AFTER date_applied",
    "ALTER TABLE applicants ADD COLUMN IF NOT EXISTS exam_schedule  DATETIME NULL AFTER interview_date",
    "ALTER TABLE applicants ADD COLUMN IF NOT EXISTS schedule_notes VARCHAR(255) NULL AFTER exam_schedule",

    // Student documents and ID
    "ALTER TABLE applicants ADD COLUMN IF NOT EXISTS student_id    VARCHAR(30)  NULL AFTER id",
    "ALTER TABLE applicants ADD COLUMN IF NOT EXISTS grade_slip    VARCHAR(255) NULL AFTER schedule_notes",
    "ALTER TABLE applicants ADD COLUMN IF NOT EXISTS id_photo      VARCHAR(255) NULL AFTER grade_slip",

    // Soft delete for applicants
    "ALTER TABLE applicants ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL AFTER id_photo",
];

foreach ($checks as $sql) {
    try { $db->exec($sql); } catch (PDOException $e) {
        // Ignore duplicate column / already exists errors
        if (!str_contains($e->getMessage(), 'Duplicate') &&
            !str_contains($e->getMessage(), 'already exists')) {
            // Silently skip other errors in production
        }
    }
}
