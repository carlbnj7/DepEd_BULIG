-- Canonical bug reports, shared admin status, replies and once-only XP deductions.
CREATE TABLE IF NOT EXISTS bug_reports (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reporter_id BIGINT UNSIGNED NOT NULL,
 status ENUM('open','processing','fixed','rejected') NOT NULL DEFAULT 'open',
 summary VARCHAR(300) NOT NULL,
 context TEXT NOT NULL,
 penalty_applied BOOLEAN NOT NULL DEFAULT 0,
 deducted_xp INT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY bug_reporter(reporter_id,id), KEY bug_status(status,id),
 FOREIGN KEY(reporter_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS bug_report_links (
 notification_id BIGINT UNSIGNED PRIMARY KEY,
 report_id BIGINT UNSIGNED NOT NULL,
 KEY bug_link_report(report_id),
 FOREIGN KEY(notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
 FOREIGN KEY(report_id) REFERENCES bug_reports(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS bug_report_updates (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 report_id BIGINT UNSIGNED NOT NULL,
 admin_id BIGINT UNSIGNED NULL,
 status ENUM('open','processing','fixed','rejected') NOT NULL,
 body TEXT NOT NULL,
 deducted_xp INT UNSIGNED NOT NULL DEFAULT 0,
 notification_id BIGINT UNSIGNED NULL,
 popup_seen_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY bug_updates(report_id,id),
 FOREIGN KEY(report_id) REFERENCES bug_reports(id) ON DELETE CASCADE,
 FOREIGN KEY(admin_id) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(notification_id) REFERENCES notifications(id) ON DELETE SET NULL
);
INSERT IGNORE INTO schema_migrations(version) VALUES('034_bug_workflow');
