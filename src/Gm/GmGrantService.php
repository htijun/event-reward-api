<?php
declare(strict_types=1);

namespace App\Gm;

use PDO;

final class GmGrantService
{
    public function __construct(private PDO $pdo) {}

    public function listPending(?int $eventId, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);

        // eventId가 있을 경우
        if ($eventId !== null) {
            // source_id에 spin_idx를 넣고 있으니 roulette_spins와 조인해서 event_id 필터
            $st = $this->pdo->prepare("
                SELECT
                    g.grant_idx, g.user_id, g.amount, g.source_type, g.source_id, g.request_id,
                    g.status, g.created_at
                FROM reward_grant_log g
                JOIN roulette_spins s
                  ON s.spin_idx = CAST(g.source_id AS UNSIGNED)
                WHERE g.status = 'pending'
                  AND g.source_type = 'roulette'
                  AND s.event_id = :eid
                ORDER BY g.created_at ASC
                LIMIT :lim OFFSET :off
            ");
            $st->bindValue(':eid', $eventId, PDO::PARAM_INT);
        } else {
            $st = $this->pdo->prepare("
                SELECT
                    grant_idx, user_id, amount, source_type, source_id, request_id,
                    status, created_at
                FROM reward_grant_log
                WHERE status = 'pending'
                ORDER BY created_at ASC
                LIMIT :lim OFFSET :off
            ");
        }

        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->bindValue(':off', $offset, PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function approve(int $grantIdx, int $actorId, ?string $memo = null): array
    {
        $this->pdo->beginTransaction();
        try {
            $st = $this->pdo->prepare("
                UPDATE reward_grant_log
                SET status = 'approved',
                    approved_by = :actor,
                    approved_at = NOW()
                WHERE grant_idx = :gid
                  AND status = 'pending'
            ");
            $st->execute([':actor' => $actorId, ':gid' => $grantIdx]);

            if ($st->rowCount() !== 1) {
                $this->pdo->rollBack();
                return ['ok' => false, 'code' => 'ALREADY_DECIDED'];
            }

            // 로그
            $this->logAction($actorId, 'approve', $grantIdx, $memo);

            $this->pdo->commit();
            return ['ok' => true];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return ['ok' => false, 'code' => 'APPROVE_FAILED', 'message' => $e->getMessage()];
        }
    }

    public function reject(int $grantIdx, int $actorId, ?string $reason = null, ?string $memo = null): array
    {
        $this->pdo->beginTransaction();
        try {
            $st = $this->pdo->prepare("
                UPDATE reward_grant_log
                SET status = 'rejected',
                    rejected_by = :actor,
                    rejected_at = NOW(),
                    decision_reason = :reason
                WHERE grant_idx = :gid
                  AND status = 'pending'
            ");
            $st->execute([
                ':actor' => $actorId,
                ':gid' => $grantIdx,
                ':reason' => $reason,
            ]);

            if ($st->rowCount() !== 1) {
                $this->pdo->rollBack();
                return ['ok' => false, 'code' => 'ALREADY_DECIDED'];
            }

            $this->logAction($actorId, 'reject', $grantIdx, $memo ?? $reason);

            $this->pdo->commit();
            return ['ok' => true];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return ['ok' => false, 'code' => 'REJECT_FAILED', 'message' => $e->getMessage()];
        }
    }

    private function logAction(int $actorId, string $actionType, int $grantIdx, ?string $memo): void
    {
        // gm_actions_log 테이블이 없으면 조용히 스킵(포폴 최소구현)
        try {
            $st = $this->pdo->prepare("
                INSERT INTO gm_actions_log (actor_id, action_type, target_type, target_id, memo)
                VALUES (:actor, :atype, 'reward_grant', :tid, :memo)
            ");
            $st->execute([
                ':actor' => $actorId,
                ':atype' => $actionType,
                ':tid' => $grantIdx,
                ':memo' => $memo,
            ]);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
