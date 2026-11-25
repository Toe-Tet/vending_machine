<?php

declare(strict_types=1);

namespace App\Api;

use App\Auth\JwtTokens;
use App\Auth\TokenClaims;
use App\Core\Request;
use App\Core\Response;

/**
 * Access checks for API actions, from the `Authorization: Bearer <JWT>` header.
 * Each returns the token's claims when the caller may proceed, or the Response
 * to send instead (401 without a valid token, 403 for the wrong role):
 *
 *     $caller = $this->bearer->admin($request);
 *     if ($caller instanceof Response) {
 *         return $caller;
 *     }
 */
final readonly class BearerAuth
{
    public function __construct(private JwtTokens $tokens)
    {
    }

    /** Any signed-in User or Admin. */
    public function user(Request $request): TokenClaims|Response
    {
        $header = $request->header('Authorization') ?? '';
        $claims = preg_match('/^Bearer\s+(\S+)\s*$/i', $header, $match) ? $this->tokens->verify($match[1]) : null;
        if ($claims === null) {
            return Json::error(401, 'unauthenticated', 'A valid Bearer token is required.')
                ->withHeader('WWW-Authenticate', 'Bearer');
        }

        return $claims;
    }

    /** Admins only. */
    public function admin(Request $request): TokenClaims|Response
    {
        $claims = $this->user($request);
        if ($claims instanceof Response || $claims->isAdmin()) {
            return $claims;
        }

        return Json::error(403, 'forbidden', 'Only Admins may do that.');
    }
}
