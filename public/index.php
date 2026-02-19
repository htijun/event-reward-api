<?php
declare(strict_types=1);

require __DIR__ . '/../src/Support/Response.php';
require __DIR__ . '/../src/Support/Request.php';
require __DIR__ . '/../src/Support/Router.php';
require __DIR__ . '/../src/Support/Database.php';
require __DIR__ . '/../src/Support/GmAuth.php';

require __DIR__ . '/../src/Roulette/RouletteSpinService.php';
require __DIR__ . '/../src/Gm/GmGrantService.php';

use App\Support\Request;
use App\Support\Response;
use App\Support\Router;
use App\Support\Database;
use App\Support\GmAuth;
use App\Roulette\RouletteSpinService;
use App\Gm\GmGrantService;

const GM_ACTOR_ID = 9001;

$router = new Router();

$router->get('/health', function () {
    Response::json(['ok' => true, 'data' => ['status' => 'up']]);
});

/**
 * POST /events/roulette/spin
 * body: { event_id, user_id, spin_type(daily|bonus), request_id }
 */
$router->post('/events/roulette/spin', function () {
    $req = new Request();
    $body = $req->json();

    $eventId = (int)($body['event_id'] ?? 0);
    $userId = (int)($body['user_id'] ?? 0);
    $spinType = (string)($body['spin_type'] ?? '');
    $requestId = (string)($body['request_id'] ?? '');

    if ($eventId <= 0 || $userId <= 0 || $requestId === '' || !in_array($spinType, ['daily', 'bonus'], true)) {
        Response::json(
            ['ok' => false, 'error' => ['code' => 'INVALID_ARGUMENT', 'message' => 'event_id, user_id, spin_type(daily|bonus), request_id are required']],
            400
        );
        return;
    }

    $pdo = Database::pdo();
    $svc = new RouletteSpinService($pdo);

    $result = $svc->spin($eventId, $userId, $spinType, $requestId);

    if (($result['ok'] ?? false) === true) {
        Response::json($result, 200);
        return;
    }

    $code = $result['error']['code'] ?? 'SPIN_FAILED';
    $status = 500;
    if (in_array($code, ['INVALID_ARGUMENT', 'INVALID_SPIN_TYPE'], true)) $status = 400;
    if (in_array($code, ['NO_BONUS_TICKET', 'DAILY_LIMIT_REACHED'], true)) $status = 409;

    Response::json($result, $status);
});

/**
 * GET /gm/grants/pending?event_id=1&limit=50&offset=0
 * header: X-GM-KEY: <GM_API_KEY>
 */
$router->get('/gm/grants/pending', function () {
    $req = new Request();

    $auth = GmAuth::check($req);
    if ($auth) {
        Response::json(['ok' => false, 'error' => ['code' => $auth['code'], 'message' => $auth['message']]], $auth['status']);
        return;
    }

    $eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    $pdo = Database::pdo();
    $svc = new GmGrantService($pdo);

    $items = $svc->listPending($eventId, $limit, $offset);
    Response::json(['ok' => true, 'data' => ['items' => $items]], 200);
});

/**
 * POST /gm/grants/approve
 * body: { grant_idx, memo? }
 * header: X-GM-KEY: <GM_API_KEY>
 */
$router->post('/gm/grants/approve', function () {
    $req = new Request();

    $auth = GmAuth::check($req);
    if ($auth) {
        Response::json(['ok' => false, 'error' => ['code' => $auth['code'], 'message' => $auth['message']]], $auth['status']);
        return;
    }

    $body = $req->json();
    $grantIdx = (int)($body['grant_idx'] ?? 0);
    $memo = isset($body['memo']) ? (string)$body['memo'] : null;

    if ($grantIdx <= 0) {
        Response::json(['ok' => false, 'error' => ['code' => 'INVALID_ARGUMENT', 'message' => 'grant_idx is required']], 400);
        return;
    }

    $pdo = Database::pdo();
    $svc = new GmGrantService($pdo);

    $res = $svc->approve($grantIdx, GM_ACTOR_ID, $memo);

    if (($res['ok'] ?? false) !== true) {
        $code = $res['code'] ?? 'APPROVE_FAILED';
        if ($code === 'ALREADY_DECIDED') {
            Response::json(['ok' => false, 'error' => ['code' => 'ALREADY_DECIDED', 'message' => 'Already approved/rejected']], 409);
            return;
        }
        Response::json(['ok' => false, 'error' => ['code' => $code, 'message' => $res['message'] ?? 'failed']], 500);
        return;
    }

    Response::json(['ok' => true], 200);
});

/**
 * POST /gm/grants/reject
 * body: { grant_idx, reason?, memo? }
 * header: X-GM-KEY: <GM_API_KEY>
 */
$router->post('/gm/grants/reject', function () {
    $req = new Request();

    $auth = GmAuth::check($req);
    if ($auth) {
        Response::json(['ok' => false, 'error' => ['code' => $auth['code'], 'message' => $auth['message']]], $auth['status']);
        return;
    }

    $body = $req->json();
    $grantIdx = (int)($body['grant_idx'] ?? 0);
    $reason = isset($body['reason']) ? (string)$body['reason'] : null;
    $memo = isset($body['memo']) ? (string)$body['memo'] : null;

    if ($grantIdx <= 0) {
        Response::json(['ok' => false, 'error' => ['code' => 'INVALID_ARGUMENT', 'message' => 'grant_idx is required']], 400);
        return;
    }

    $pdo = Database::pdo();
    $svc = new GmGrantService($pdo);

    $res = $svc->reject($grantIdx, GM_ACTOR_ID, $reason, $memo);

    if (($res['ok'] ?? false) !== true) {
        $code = $res['code'] ?? 'REJECT_FAILED';
        if ($code === 'ALREADY_DECIDED') {
            Response::json(['ok' => false, 'error' => ['code' => 'ALREADY_DECIDED', 'message' => 'Already approved/rejected']], 409);
            return;
        }
        Response::json(['ok' => false, 'error' => ['code' => $code, 'message' => $res['message'] ?? 'failed']], 500);
        return;
    }

    Response::json(['ok' => true], 200);
});

$router->dispatch(new Request());
