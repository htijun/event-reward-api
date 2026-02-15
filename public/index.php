<?php
declare(strict_types=1);

require __DIR__ . '/../src/Support/Response.php';
require __DIR__ . '/../src/Support/Request.php';
require __DIR__ . '/../src/Support/Router.php';
require __DIR__ . '/../src/Support/Database.php';
require __DIR__ . '/../src/Roulette/RouletteSpinService.php';

use App\Support\Request;
use App\Support\Response;
use App\Support\Router;
use App\Support\Database;
use App\Roulette\RouletteSpinService;

$router = new Router();

$router->get('/health', function () {
    Response::json(['ok' => true, 'data' => ['status' => 'up']]);
});

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

    $pdo = Database::pdo(); // ✅ static 메서드
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

$router->dispatch(new Request());
