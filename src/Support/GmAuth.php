<?php
declare(strict_types=1);

namespace App\Support;

final class GmAuth
{
    /**
     * @return array{ok:bool,status:int,code:string,message:string}|null
     *         null이면 통과, 배열이면 에러 응답 정보
     */
    public static function check(Request $req): ?array
    {
        $expected = getenv('GM_API_KEY') ?: '';
        if ($expected === '') {
            return [
                'ok' => false,
                'status' => 500,
                'code' => 'GM_KEY_NOT_CONFIGURED',
                'message' => 'GM key not configured',
            ];
        }

        $got = $req->header('X-GM-KEY') ?? '';
        if (!hash_equals($expected, $got)) {
            return [
                'ok' => false,
                'status' => 401,
                'code' => 'UNAUTHORIZED',
                'message' => 'Invalid GM key',
            ];
        }

        return null;
    }
}
