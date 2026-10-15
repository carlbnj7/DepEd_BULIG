-- BULIG 027: new account IDs (Pupil PCES260001 · Teacher PCEST0001 · Admin A260001)
-- Safe to run more than once. It only adds a table: no account, password or progress is changed here.
-- After importing, the administrator opens Settings › Account IDs, checks the school code and the list,
-- and chooses "Save a backup and switch". BULIG saves a backup copy of the database before it changes any ID.
-- Each account's old ID is kept in account_id_history, so old IDs (and printed cards) still sign in.

CREATE TABLE IF NOT EXISTS account_id_history(
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  old_id VARCHAR(30) NOT NULL,
  new_id VARCHAR(30) NOT NULL,
  changed_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_account_old_id(old_id),
  KEY idx_account_user(user_id),
  CONSTRAINT fk_account_id_history_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO schema_migrations(version) VALUES('027_account_ids');
