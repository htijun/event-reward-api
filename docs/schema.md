# Schema (Summary)
> DB는 MySQL 기준의 요약 스키마입니다.

## roulette_rewards
이벤트별 룰렛 보상/확률(가중치) 테이블  
(테이블 row 식별자와 외부 보상 식별자를 분리)

- reward_idx (PK)
- event_id (index)
- reward_id (외부 reward 식별자 / reference)
- reward_type (point/item 등)
- reward_value
- weight (int, 정수 가중치)
- is_active
- created_at

권장 제약
- UNIQUE(event_id, reward_id)  
  → 동일 이벤트에 동일 보상 중복 등록 방지

---

## roulette_spins
스핀 요청/결과 로그 (중복 방지 핵심)

- spin_idx (PK) 
- event_id (index)
- user_id (index)
- spin_date (date, index)
- spin_type (enum: daily|bonus)
- request_id (unique)  ← idempotency key
- reward_id (reference)
- created_at

권장 제약/정책
- daily 제한: (user_id, event_id, spin_date, spin_type='daily')가  
  하루 1개만 존재하도록 제어  
  - 구현은 유니크키(가능한 형태) 또는 트랜잭션 + 조건 체크로 보강

---

## roulette_bonus_tickets
보너스 기회(티켓) 발급/사용 상태 관리 (가변 수량 지원)

- ticket_idx (PK) 
- event_id (index)
- user_id (index)
- ticket_date (date, index)  ← “해당 날짜에 사용 가능한 티켓” 관리
- source_type (mission/ad/admin 등)
- source_id
- status (enum: issued|used|expired)
- issued_at
- used_at

정책
- 티켓 1개당 bonus 스핀 1회 가능
- status 전이: issued → used/expired
- bonus 스핀 실행 시 issued → used를 트랜잭션 내에서 처리

---

## reward_grant_log
보상/포인트 지급 로그 (감사/추적 목적)

- grant_idx (PK) 
- user_id (index)
- amount (or reward payload)
- source_type (attendance|roulette|admin_approval 등)
- source_id (spin_idx, approval_id 등)
- request_id (optional unique)
- created_at

목적
- 보상 중복 지급 방지
- 운영 이슈 발생 시  
  “누가 / 언제 / 어떤 사유로 / 어떤 보상을 지급했는지”  
  재현 및 감사 근거 확보
