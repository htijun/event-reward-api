USE event_reward;

-- 룰렛보상 테이블
CREATE TABLE IF NOT EXISTS roulette_rewards (
  reward_idx     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id       BIGINT UNSIGNED NOT NULL,
  reward_id      VARCHAR(64) NOT NULL,
  reward_type    VARCHAR(32) NOT NULL,
  reward_value   BIGINT NOT NULL,
  weight         INT UNSIGNED NOT NULL,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (reward_idx),
  UNIQUE KEY uq_rewards_event_reward (event_id, reward_id),
  KEY idx_rewards_event (event_id),
  KEY idx_rewards_active (event_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 룰렛결과 테이블
CREATE TABLE IF NOT EXISTS roulette_spins (
  spin_idx     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id     BIGINT UNSIGNED NOT NULL,
  user_id      BIGINT UNSIGNED NOT NULL,
  spin_date    DATE NOT NULL,
  spin_type    ENUM('daily','bonus') NOT NULL,
  request_id   VARCHAR(128) NOT NULL,
  reward_id    VARCHAR(64) NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (spin_idx),
  UNIQUE KEY uq_spins_request (request_id),
  KEY idx_spins_event (event_id),
  KEY idx_spins_user (user_id),
  KEY idx_spins_date (spin_date),
  KEY idx_spins_user_event_date (user_id, event_id, spin_date),
  KEY idx_spins_user_event_date_type (user_id, event_id, spin_date, spin_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE roulette_spins
  ADD COLUMN daily_key VARCHAR(64)
    GENERATED ALWAYS AS (
      CASE
        WHEN spin_type = 'daily'
        THEN CONCAT(user_id, ':', event_id, ':', DATE_FORMAT(spin_date, '%Y-%m-%d'))
        ELSE NULL
      END
    ) STORED,
  ADD UNIQUE KEY uq_spins_daily_once (daily_key);

-- 룰렛보너스 관리
CREATE TABLE IF NOT EXISTS roulette_bonus_tickets (
  ticket_idx    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id      BIGINT UNSIGNED NOT NULL,
  user_id       BIGINT UNSIGNED NOT NULL,
  ticket_date   DATE NOT NULL,
  source_type   VARCHAR(32) NOT NULL,
  source_id     VARCHAR(64) DEFAULT NULL,
  status        ENUM('issued','used','expired') NOT NULL DEFAULT 'issued',
  issued_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  used_at       DATETIME DEFAULT NULL,

  PRIMARY KEY (ticket_idx),
  KEY idx_tickets_event (event_id),
  KEY idx_tickets_user (user_id),
  KEY idx_tickets_date (ticket_date),
  KEY idx_tickets_user_event_date (user_id, event_id, ticket_date),
  KEY idx_tickets_user_status (user_id, status),
  KEY idx_tickets_status_date (status, ticket_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 보상로그 테이블
CREATE TABLE IF NOT EXISTS reward_grant_log (
  grant_idx     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       BIGINT UNSIGNED NOT NULL,
  amount        BIGINT NOT NULL,
  source_type   VARCHAR(32) NOT NULL,
  source_id     VARCHAR(64) NOT NULL,
  request_id    VARCHAR(128) DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (grant_idx),
  KEY idx_grant_user (user_id),
  KEY idx_grant_source (source_type, source_id),
  UNIQUE KEY uq_grant_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
