<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Builds RFC 7807 (`application/problem+json`) payloads for the exception
 * renderer registered in `bootstrap/app.php`. Plain class, not a facade: the
 * renderer callbacks are the only caller, and there is no need for a
 * container binding or a swappable implementation.
 */
class Problem
{
    /**
     * Reason phrases that diverge from `Response::$statusTexts`.
     *
     * 419 ("Page Expired") is a Laravel/CSRF convention, not a registered
     * HTTP status, so Symfony's table has no entry for it.
     */
    private const TITLE_OVERRIDES = [
        419 => 'Page Expired',
    ];

    /**
     * The reason phrase for a given HTTP status, used as the `title` member.
     */
    public static function title(int $status): string
    {
        return self::TITLE_OVERRIDES[$status]
            ?? Response::$statusTexts[$status]
            ?? 'Unknown Status';
    }

    /**
     * Build the `application/problem+json` response body shared by every
     * render callback: `type`, `title`, `status`, optional `detail`,
     * `instance`, plus any extension members merged in via `$extra`.
     *
     * `$headers` lets callers pass through headers that belong to the
     * original exception (e.g. `Allow`, `Retry-After`).
     */
    public static function response(
        Request $request,
        int $status,
        ?string $detail = null,
        array $extra = [],
        array $headers = [],
    ): JsonResponse {
        $body = [
            'type'   => 'about:blank',
            'title'  => self::title($status),
            'status' => $status,
        ];

        if ($detail !== null) {
            $body['detail'] = $detail;
        }

        $body['instance'] = $request->getRequestUri();

        foreach ($extra as $key => $value) {
            $body[$key] = $value;
        }

        return response()->json($body, $status, array_merge($headers, [
            'Content-Type' => 'application/problem+json',
        ]));
    }
}
