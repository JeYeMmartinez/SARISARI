<?php
/**
 * Migration: Create job_postings table and migrate existing data
 * Run once via browser: http://localhost/SARISARI/SARISARI/SARI-SARI_STORE/migrate_job_postings.php
 */
require_once 'Model/database.php';

$errors = [];
$success = [];

// 1. Create job_postings table if it doesn't exist
$sql = "
CREATE TABLE IF NOT EXISTS `job_postings` (
  `job_posting_id`  INT(11)        NOT NULL AUTO_INCREMENT,
  `position_id`     INT(11)        NOT NULL,
  `employment_type` VARCHAR(50)    NOT NULL DEFAULT 'Full-time',
  `slots`           INT(11)        NOT NULL DEFAULT 1,
  `salary_min`      DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `salary_max`      DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `requirements`    TEXT           DEFAULT NULL,
  `status`          ENUM('Open','Closed','On Hold') NOT NULL DEFAULT 'Open',
  `created_by`      INT(11)        DEFAULT NULL,
  `created_at`      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME       DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`job_posting_id`),
  KEY `fk_jp_position` (`position_id`),
  CONSTRAINT `fk_jp_position` FOREIGN KEY (`position_id`) REFERENCES `positions` (`position_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if (mysqli_query($conn, $sql)) {
    $success[] = "✅ job_postings table created (or already existed).";
} else {
    $errors[] = "❌ Failed to create job_postings table: " . mysqli_error($conn);
}

// 2. Migrate existing positions to job_postings (only if job_postings is empty)
$existing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM job_postings"))['cnt'];
if ($existing == 0) {
    $positions = mysqli_query($conn, "SELECT * FROM positions ORDER BY created_at ASC");
    $migrated = 0;
    while ($pos = mysqli_fetch_assoc($positions)) {
        $pid  = (int) $pos['position_id'];
        $etype = mysqli_real_escape_string($conn, $pos['employment_type']);
        $slots = (int) $pos['slots'];
        $smin  = (float) $pos['salary_min'];
        $smax  = (float) $pos['salary_max'];
        $req   = mysqli_real_escape_string($conn, $pos['requirements'] ?? '');
        $status = mysqli_real_escape_string($conn, $pos['status']);
        $cat   = isset($pos['created_at']) ? mysqli_real_escape_string($conn, $pos['created_at']) : date('Y-m-d H:i:s');

        $ins = mysqli_query($conn, "
            INSERT INTO job_postings (position_id, employment_type, slots, salary_min, salary_max, requirements, status, created_at)
            VALUES ($pid, '$etype', $slots, $smin, $smax, '$req', '$status', '$cat')
        ");
        if ($ins) $migrated++;
        else $errors[] = "⚠️ Failed to migrate position_id=$pid: " . mysqli_error($conn);
    }
    $success[] = "✅ Migrated $migrated existing positions to job_postings.";
} else {
    $success[] = "ℹ️ job_postings already has $existing records — skipping migration.";
}

echo "<html><body style='font-family:sans-serif;padding:30px;'>";
echo "<h2>Job Postings Migration</h2>";
foreach ($success as $s) echo "<p style='color:green;'>$s</p>";
foreach ($errors as $e) echo "<p style='color:red;'>$e</p>";
if (empty($errors)) {
    echo "<p style='color:green;font-weight:bold;'>Migration complete! You can delete this file.</p>";
} else {
    echo "<p style='color:red;'>Some errors occurred. Check above.</p>";
}
echo "</body></html>";
