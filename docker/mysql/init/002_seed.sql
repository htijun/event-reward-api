USE event_reward;

-- 더미데이터 룰렛 보상
INSERT INTO roulette_rewards
  (event_id, reward_id, reward_type, reward_value, weight, is_active)
VALUES
  (1, 'RWD1_POINT_100',  'point', 100,  50, 1),
  (1, 'RWD1_POINT_300',  'point', 300,  25, 1),
  (1, 'RWD1_POINT_500',  'point', 500,  15, 1),
  (1, 'RWD1_POINT_1000', 'point', 1000,  8, 1),
  (1, 'RWD1_POINT_3000', 'point', 3000,  2, 1);

-- 더미데이터 event_id 2번
INSERT INTO roulette_rewards
  (event_id, reward_id, reward_type, reward_value, weight, is_active)
VALUES
  (2, 'RWD2_POINT_50',    'point', 50,   40, 1),
  (2, 'RWD2_POINT_150',   'point', 150,  30, 1),
  (2, 'RWD2_POINT_400',   'point', 400,  18, 1),
  (2, 'RWD2_POINT_800',   'point', 800,  10, 1),
  (2, 'RWD2_POINT_2000',  'point', 2000,  2, 1);

-- 더미데이터 보너스 티켓
INSERT INTO roulette_bonus_tickets
  (event_id, user_id, ticket_date, source_type, source_id, status)
VALUES
  (1, 1001, CURDATE(), 'admin', 'seed', 'issued'),
  (1, 1001, CURDATE(), 'admin', 'seed', 'issued'),
  (2, 2002, CURDATE(), 'admin', 'seed', 'issued'),
  (2, 2002, CURDATE(), 'admin', 'seed', 'issued'),
  (2, 2002, CURDATE(), 'admin', 'seed', 'issued');