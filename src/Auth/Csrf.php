<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Request;
use App\Core\Session;

/**
 * Synchroniser-token CSRF protection for the web forms: one random token per
 * session, sent back by every state-changing form as the hidden `_token` field.
 */
final readonly class Csrf
{
    public const string FIELD = '_token';
    private const string KEY = '_csrf_token';

    public function __construct(private Session $session)
    {
    }

    /** The current session's token, created on first use. */
    public function token(): string
    {
        $token = $this->session->get(self::KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::KEY, $token);
        }

        return $token;
    }

    /** Issues a fresh token; call when the signed-in identity changes. */
    public function rotate(): void
    {
        $this->session->remove(self::KEY);
    }

    /**
     * True for a state-changing web request whose token is missing or wrong.
     * GET/HEAD/OPTIONS change nothing, and /api authenticates with Bearer
     * tokens rather than cookies, so neither can be forged cross-site.
     */
    public function rejects(Request $request): bool
    {
        if (in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true)
            || $request->path === '/api'
            || str_starts_with($request->path, '/api/')
        ) {
            return false;
        }

        $expected = $this->session->get(self::KEY);
        $given = $request->input(self::FIELD);

        return !is_string($expected) || $expected === '' || !is_string($given) || !hash_equals($expected, $given);
    }
}
