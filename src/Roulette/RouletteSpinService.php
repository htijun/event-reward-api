<?php
declare(strict_types=1);

namespace App\Roulette;

use PDO;
use PDOException;

final class RouletteSpinService
{
    public function __construct(private PDO $pdo) {}

    public function spin(int $eventId, int $userId, string $spinType, string $requestId): array
    {
        $today = date('Y-m-d');

        $this->pdo->beginTransaction();

        try {
            // 1) Idempotency: request_id로 기존 결과가 있으면 그대로 반환
            $existing = $this->findSpinByRequestId($requestId);
            if ($existing !== null) {
                $reward = $this->findRewardById((int)$existing['event_id'], (string)$existing['reward_id']);
                $this->pdo->commit();

                return [
                    'ok' => true,
                    'data' => [
                        'idempotent' => true,
                        'spin' => [
                            'spin_idx' => (int)$existing['spin_idx'],
                            'event_id' => (int)$existing['event_id'],
                            'user_id' => (int)$existing['user_id'],
                            'spin_type' => (string)$existing['spin_type'],
                            'spin_date' => (string)$existing['spin_date'],
                            'request_id' => (string)$existing['request_id'],
                            'reward_id' => (string)$existing['reward_id'],
                            'reward_type' => $reward['reward_type'] ?? null,
                            'reward_value' => isset($reward['reward_value']) ? (int)$reward['reward_value'] : null,
                        ],
                    ],
                ];
            }

            // 2) bonus면 티켓 issued 1장을 잠그고 used로 전이
            if ($spinType === 'bonus') {
                $ticketIdx = $this->lockOneIssuedTicket($eventId, $userId, $today);
                if ($ticketIdx === null) {
                    $this->pdo->rollBack();
                    return [
                        'ok' => false,
                        'error' => [
                            'code' => 'NO_BONUS_TICKET',
                            'message' => 'No issued bonus ticket available.',
                        ],
                    ];
                }
                $this->markTicketUsed($ticketIdx);
            } elseif ($spinType !== 'daily') {
                $this->pdo->rollBack();
                return [
                    'ok' => false,
                    'error' => [
                        'code' => 'INVALID_SPIN_TYPE',
                        'message' => 'spin_type must be daily or bonus.',
                    ],
                ];
            }

            // 3) reward 후보 로드 + 가중치 랜덤 선택
            $rewards = $this->getActiveRewards($eventId);
            if (count($rewards) === 0) {
                throw new \RuntimeException('NO_ACTIVE_REWARDS');
            }

            $picked = $this->pickWeighted($rewards);
            $pickedRewardId = (string)$picked['reward_id'];
            $pickedRewardValue = (int)$picked['reward_value'];

            // 4) spins 기록 (daily는 daily_key UNIQUE로 1일1회 DB 강제)
            $spinIdx = $this->insertSpin($eventId, $userId, $today, $spinType, $requestId, $pickedRewardId);

            // 5) 지급 로그 기록
            $grantStatus = ((string)$picked['reward_type'] === 'point') ? 'approved' : 'pending';

            $grantIdx = $this->insertGrantLog(
                $userId,
                $pickedRewardValue,
                'roulette',
                (string)$spinIdx,
                $requestId,
                $grantStatus
            );
                       
            $this->pdo->commit();

            return [
                'ok' => true,
                'data' => [
                    'idempotent' => false,
                    'spin' => [
                        'spin_idx' => $spinIdx,
                        'event_id' => $eventId,
                        'user_id' => $userId,
                        'spin_type' => $spinType,
                        'spin_date' => $today,
                        'request_id' => $requestId,
                        'reward_id' => $pickedRewardId,
                        'reward_type' => (string)$picked['reward_type'],
                        'reward_value' => $pickedRewardValue,
                    ],
                    'grant' => [
                        'grant_idx' => $grantIdx,
                    ],
                ],
            ];

        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();

            // Duplicate entry (MySQL)
            $dup = isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062;
            if ($dup) {
                $msg = $e->getMessage();

                // daily 1회 제한 위반 (uq_spins_daily_once)
                if (str_contains($msg, 'uq_spins_daily_once')) {
                    return [
                        'ok' => false,
                        'error' => [
                            'code' => 'DAILY_LIMIT_REACHED',
                            'message' => 'Daily spin already used today.',
                        ],
                    ];
                }

                // request_id 동시성 충돌(두 요청이 거의 동시에 들어온 경우)
                if (str_contains($msg, 'uq_spins_request')) {
                    $existing = $this->findSpinByRequestId($requestId);
                    if ($existing !== null) {
                        $reward = $this->findRewardById((int)$existing['event_id'], (string)$existing['reward_id']);
                        return [
                            'ok' => true,
                            'data' => [
                                'idempotent' => true,
                                'spin' => [
                                    'spin_idx' => (int)$existing['spin_idx'],
                                    'event_id' => (int)$existing['event_id'],
                                    'user_id' => (int)$existing['user_id'],
                                    'spin_type' => (string)$existing['spin_type'],
                                    'spin_date' => (string)$existing['spin_date'],
                                    'request_id' => (string)$existing['request_id'],
                                    'reward_id' => (string)$existing['reward_id'],
                                    'reward_type' => $reward['reward_type'] ?? null,
                                    'reward_value' => isset($reward['reward_value']) ? (int)$reward['reward_value'] : null,
                                ],
                            ],
                        ];
                    }
                }
            }

            return [
                'ok' => false,
                'error' => [
                    'code' => 'SPIN_FAILED',
                    'message' => $e->getMessage(),
                ],
            ];

        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return [
                'ok' => false,
                'error' => [
                    'code' => 'SPIN_FAILED',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    private function findSpinByRequestId(string $requestId): ?array
    {
        $st = $this->pdo->prepare("SELECT * FROM roulette_spins WHERE request_id = :rid LIMIT 1");
        $st->execute([':rid' => $requestId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function getActiveRewards(int $eventId): array
    {
        $st = $this->pdo->prepare("
            SELECT reward_id, reward_type, reward_value, weight
            FROM roulette_rewards
            WHERE event_id = :eid AND is_active = 1
        ");
        $st->execute([':eid' => $eventId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function findRewardById(int $eventId, string $rewardId): ?array
    {
        $st = $this->pdo->prepare("
            SELECT reward_id, reward_type, reward_value
            FROM roulette_rewards
            WHERE event_id = :eid AND reward_id = :rid
            LIMIT 1
        ");
        $st->execute([':eid' => $eventId, ':rid' => $rewardId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function lockOneIssuedTicket(int $eventId, int $userId, string $ticketDate): ?int
    {
        $st = $this->pdo->prepare("
            SELECT ticket_idx
            FROM roulette_bonus_tickets
            WHERE event_id = :eid
              AND user_id = :uid
              AND ticket_date = :tdate
              AND status = 'issued'
            ORDER BY ticket_idx ASC
            LIMIT 1
            FOR UPDATE
        ");
        $st->execute([':eid' => $eventId, ':uid' => $userId, ':tdate' => $ticketDate]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['ticket_idx'] : null;
    }

    private function markTicketUsed(int $ticketIdx): void
    {
        $st = $this->pdo->prepare("
            UPDATE roulette_bonus_tickets
            SET status = 'used', used_at = NOW()
            WHERE ticket_idx = :tid
        ");
        $st->execute([':tid' => $ticketIdx]);
    }

    private function insertSpin(int $eventId, int $userId, string $spinDate, string $spinType, string $requestId, string $rewardId): int
    {
        $st = $this->pdo->prepare("
            INSERT INTO roulette_spins (event_id, user_id, spin_date, spin_type, request_id, reward_id)
            VALUES (:eid, :uid, :sdate, :stype, :rid, :reward)
        ");
        $st->execute([
            ':eid' => $eventId,
            ':uid' => $userId,
            ':sdate' => $spinDate,
            ':stype' => $spinType,
            ':rid' => $requestId,
            ':reward' => $rewardId,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    private function insertGrantLog(
        int $userId,
        int $amount,
        string $sourceType,
        string $sourceId,
        string $requestId,
        string $status
    ): int {
        $st = $this->pdo->prepare("
            INSERT INTO reward_grant_log (user_id, amount, source_type, source_id, request_id, status)
            VALUES (:uid, :amt, :stype, :sid, :rid, :status)
        ");
        $st->execute([
            ':uid' => $userId,
            ':amt' => $amount,
            ':stype' => $sourceType,
            ':sid' => $sourceId,
            ':rid' => $requestId,
            ':status' => $status,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    private function pickWeighted(array $rewards): array
    {
        $sum = 0;
        foreach ($rewards as $r) {
            $w = (int)$r['weight'];
            if ($w > 0) $sum += $w;
        }
        if ($sum <= 0) {
            throw new \RuntimeException('INVALID_REWARD_WEIGHTS');
        }

        $rand = random_int(1, $sum);
        $acc = 0;
        foreach ($rewards as $r) {
            $acc += max(0, (int)$r['weight']);
            if ($rand <= $acc) return $r;
        }
        return $rewards[array_key_last($rewards)];
    }
}
