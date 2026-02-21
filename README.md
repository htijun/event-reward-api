# event-reward-api

RESTful API 기반의 모바일 게임 이벤트/리워드 서버 포트폴리오 프로젝트입니다.
확률형 룰렛 이벤트, 보상 지급 처리, 운영자 승인(운영툴) 기능을 서버에서 담당하도록 설계했습니다.
핵심 목표: 보상 중복 지급 방지와 데이터 정합성 보장

### 주요 기능

1. 룰렛 이벤트 (확률형 보상)

서버에서 가중치(weight) 기반 확률 계산
request_id 기반 idempotent 처리
daily 1회 + bonus 티켓 구조 지원
스핀 로그 + 지급 로그를 트랜잭션으로 묶어 원자성 보장

2. 보너스 티켓 구조
roulette_bonus_tickets 테이블로 가변 수량 관리
bonus 스핀 시 FOR UPDATE로 티켓 1장 차감
used / issued 상태 전이 관리

3️.보상 지급 로그

reward_grant_log 테이블에 지급 내역 저장
point 보상은 즉시 approved
그 외 보상은 pending → GM 승인 필요
request_id unique 제약으로 중복 지급 방지

4️. 관리자 승인(운영툴)

### X-GM-KEY 헤더 기반 인증

pending → approved / rejected 상태 전이
중복 승인 방지 (status='pending' 조건 업데이트)
모든 승인/거절은 gm_actions_log에 기록

### 인증 방식

GM API
인증 방식: X-GM-KEY 헤더
서버의 .env에 정의된 GM_API_KEY와 hash_equals 비교
내부 운영용 API를 가정한 단순 Key 기반 인증 구조

## DB 설계 요약 (핵심 테이블)
roulette_rewards

이벤트별 보상 및 확률(weight) 정의 테이블
roulette_spins

스핀 로그 저장
request_id UNIQUE
daily 1회 제한: daily_key GENERATED + UNIQUE
roulette_bonus_tickets

보너스 기회 관리 테이블
issued / used / expired 상태 관리
reward_grant_log

보상 지급 로그
status: approved / pending / rejected
request_id UNIQUE
gm_actions_log

운영자 승인/거절 감사 로그

### 트랜잭션 & 정합성 전략
1. Idempotency
request_id UNIQUE 제약
동일 요청 재시도 시 중복 스핀/중복 지급 방지

2️. Daily 1회 강제 (DB 레벨)
daily_key GENERATED COLUMN
UNIQUE INDEX로 DB에서 직접 제한

3. Bonus 티켓 동시성 제어
SELECT ... FOR UPDATE
트랜잭션 내 티켓 사용 처리

4. 승인 중복 방지
UPDATE ... WHERE status='pending'
이미 승인된 건 재승인 불가

### API 엔드포인트
Roulette
GET  /events/roulette/status
POST /events/roulette/spin

GM Tool
GET  /gm/grants/pending?limit=50
POST /gm/grants/approve
POST /gm/grants/reject


### GM API 호출 예시
pending 조회
curl.exe --% "http://localhost:8001/gm/grants/pending?limit=50" -H "X-GM-KEY: gm-secret-1234"
승인
curl.exe --% -X POST http://localhost:8001/gm/grants/approve ^
  -H "Content-Type: application/json" -H "X-GM-KEY: gm-secret-1234" ^
  -d "{\"grant_idx\":1,\"memo\":\"ok\"}"
거절
curl.exe --% -X POST http://localhost:8001/gm/grants/reject ^
  -H "Content-Type: application/json" -H "X-GM-KEY: gm-secret-1234" ^
  -d "{\"grant_idx\":1,\"reason\":\"fraud\"}"

### 운영 검증 SQL
최근 grant 상태
SELECT grant_idx, request_id, amount, status, created_at
FROM reward_grant_log
ORDER BY grant_idx DESC
LIMIT 20;

pending 목록
SELECT grant_idx, user_id, amount, request_id, created_at
FROM reward_grant_log
WHERE status='pending'
ORDER BY created_at ASC;

스핀-지급 정합성 추적
SELECT s.request_id, s.reward_id, r.reward_type, r.reward_value, g.status
FROM roulette_spins s
JOIN roulette_rewards r ON r.event_id=s.event_id AND r.reward_id=s.reward_id
JOIN reward_grant_log g ON g.request_id=s.request_id
WHERE s.request_id='req-daily-1001';
  
## 실행 방법 (Docker)

### 실행 환경
- Docker Desktop (Windows / macOS)
- 또는 Docker Engine + Docker Compose (Linux)

### 서버 실행
```bash
docker compose up --build

로컬에서 8001이 사용 중이면 compose.yaml의 "8001:8000"에서 8001을 다른 포트로 변경하세요.
Windows/macOS는 Docker Desktop을 실행한 상태에서 명령을 실행하세요.
compose.yaml이 있는 프로젝트 루트에서 명령을 실행하세요.


이 프로젝트는 로컬 Docker 기반 개발/포트폴리오 용도로 설계되었습니다.
DB 접속 정보는 코드에 직접 하드코딩하지 않고,
루트 디렉토리의 .env 파일을 통해 주입됩니다.

1. .env 설정 방법
.env.example 파일을 복사하여 .env 파일을 생성합니다.

copy .env.example .env   # Windows
# 또는
cp .env.example .env     # macOS / Linux

2. .env 파일 안의 값을 확인하거나 수정합니다.

ex) MYSQL_ROOT_PASSWORD=change-me
MYSQL_DATABASE=event_reward
MYSQL_USER=app
MYSQL_PASSWORD=change-me

3. Docker를 실행합니다.

docker compose up --build
