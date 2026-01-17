# event-reward-api

RESTful API 기반의 이벤트·리워드 서버입니다.  
출석 보상, 포인트(재화) 지급, 확률형 이벤트(룰렛), 관리자 승인 처리(운영툴) 등을 서버에서 처리하도록 설계했습니다.  
보상 중복 지급 방지와 데이터 정합성을 최우선으로 고려했습니다.

## 주요 기능
- **일일 출석 보상**: 1일 1회 지급, 중복 요청 방지
- **포인트(재화) 지급**: 지급 로그 기반 idempotent 처리
- **룰렛 이벤트(확률형 보상)**: 서버에서 확률 테이블 기반 결과 산출 + 보상 지급
- **관리자 승인 처리(운영툴)**: pending → approved/rejected 상태 전이, 중복 승인 방지 및 감사 로그 기록

## 인증/권한
- 인증은 **JWT** 기반입니다.
- 이벤트/보상 API는 인증 토큰을 요구하며, 관리자 승인 API는 **admin 권한**을 요구합니다.

## 룰렛 기회 정책 (1일 1회 + 보너스 기회)
룰렛은 기본적으로 사용자당 1일 1회(daily) 실행 가능하며,  
미션 완료/광고 시청/운영 지급 등 특정 조건을 만족하면 **보너스 기회(bonus)**를 추가로 획득할 수 있습니다.  
보너스 기회는 수량 제한을 고정하지 않고, 정책/이벤트에 따라 유연하게 확장 가능한 구조로 설계했습니다.

- daily 스핀: 1일 1회 제한
- bonus 스핀: **보너스 티켓(issued)** 보유 시, 티켓 **1개당 1회** 실행
- 정합성: 스핀 기록/보상 지급/티켓 사용 처리를 **트랜잭션**으로 처리
- 추적성: 스핀 로그 및 지급 로그를 남겨 운영 이슈 시 감사/재현이 가능

## DB 설계 요약 (핵심 테이블)
보상 중복 지급과 동시 요청에 의한 데이터 불일치를 방지하기 위해  
지급 로그 및 이벤트 로그 중심으로 테이블을 구성했습니다.

- `roulette_rewards`: 이벤트별 보상/가중치(확률) 테이블
- `roulette_spins`: 스핀 요청/결과 로그 (`request_id` unique)
- `roulette_bonus_tickets`: 보너스 기회(티켓) 발급/사용 상태 관리 (가변 수량 지원)
- `reward_grant_log`: 포인트/보상 지급 로그 (`source_type`/`source_id`로 추적)

## 트랜잭션 및 동시요청 대응
룰렛/출석/운영 지급처럼 중복 요청 시 치명적인 기능은 idempotent하게 처리했습니다.

- **request_id 기반 중복 방지**: 동일 요청이 재시도되어도 스핀/지급이 중복되지 않도록 `request_id`에 unique 제약을 적용
- **트랜잭션 처리**: 스핀 로그 기록 + 보상 지급 + (bonus인 경우) 티켓 used 처리를 하나의 트랜잭션으로 묶어 원자성 보장
- **관리자 승인 중복 방지**: 승인 처리 시 `status='pending'` 조건 업데이트로 중복 승인을 방지하고 감사 로그를 기록

## API 엔드포인트

### Auth
- `POST /auth/login`
- `POST /auth/refresh` *(optional)*

### Attendance
- `POST /attendance/check-in`
- `GET  /attendance/status`

### Wallet / Rewards
- `GET  /wallet`
- `POST /wallet/grants`
- `GET  /wallet/grants?from=&to=`

### Roulette Event
- `GET  /events/roulette/status`
- `POST /events/roulette/spin`

### Admin Approval (GM Tool)
- `POST /admin/approvals`
- `GET  /admin/approvals?status=pending`
- `POST /admin/approvals/{id}/approve`
- `POST /admin/approvals/{id}/reject`
- `GET  /admin/approvals/{id}`
