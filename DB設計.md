-- users
CREATE TABLE users (
  user_id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_name     VARCHAR(100) NOT NULL,
  login_name    VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- question groups (設問カテゴリ)
CREATE TABLE question_groups (
  group_id    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_name  VARCHAR(200) NOT NULL,
  sort_order  INT NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- questions
CREATE TABLE questions (
  q_id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id    BIGINT UNSIGNED NOT NULL,
  question    TEXT NOT NULL,
  check_type  TINYINT UNSIGNED NOT NULL, -- 1:checkbox, 2:text(etc拡張)
  required    TINYINT(1) NOT NULL DEFAULT 0,
  sort_order  INT NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_questions_group
    FOREIGN KEY (group_id) REFERENCES question_groups(group_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_questions_group_sort (group_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- run header (= your question_group_log)
CREATE TABLE question_group_logs (
  q_g_l_id    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id    BIGINT UNSIGNED NOT NULL,
  user_id     BIGINT UNSIGNED NOT NULL,
  target_date DATE NOT NULL,
  log_date    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status      TINYINT UNSIGNED NOT NULL DEFAULT 0, -- 0:draft 1:submitted
  CONSTRAINT fk_qgl_group
    FOREIGN KEY (group_id) REFERENCES question_groups(group_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_qgl_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_qgl_group_date (group_id, target_date),
  INDEX idx_qgl_user_date (user_id, target_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- run detail (= your question_log)
CREATE TABLE question_logs (
  q_l_id      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  q_g_l_id    BIGINT UNSIGNED NOT NULL,
  q_id        BIGINT UNSIGNED NOT NULL,


  value_bool  TINYINT(1) NULL,
  value_text  TEXT NULL,

  question_snapshot TEXT NULL, -- 設問編集後もログを守るため値保存

  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_ql_run
    FOREIGN KEY (q_g_l_id) REFERENCES question_group_logs(q_g_l_id)
    ON DELETE CASCADE ON UPDATE CASCADE,

  CONSTRAINT fk_ql_question
    FOREIGN KEY (q_id) REFERENCES questions(q_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  -- 1回のRun内で同じ設問は1行だけ（自動保存で重複防止）
  UNIQUE KEY uq_run_question (q_g_l_id, q_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 推奨拡張（履歴厳密運用）
-- ALTER TABLE question_group_logs
--   ADD COLUMN group_name_snapshot VARCHAR(200) NULL,
--   ADD COLUMN checker_name_snapshot VARCHAR(100) NULL;