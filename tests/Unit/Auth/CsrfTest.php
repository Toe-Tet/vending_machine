<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Auth\Csrf;
use App\Core\ArraySession;
use App\Core\Request;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    private Csrf $csrf;

    protected function setUp(): void
    {
        $this->csrf = new Csrf(new ArraySession());
    }

    public function test_a_post_without_a_token_is_rejected(): void
    {
        self::assertTrue($this->csrf->rejects(new Request('POST', '/logout')));
    }

    public function test_a_post_carrying_the_session_token_is_accepted(): void
    {
        $request = new Request('POST', '/logout', body: ['_token' => $this->csrf->token()]);

        self::assertFalse($this->csrf->rejects($request));
    }

    public function test_a_post_with_a_wrong_token_is_rejected(): void
    {
        $this->csrf->token();

        self::assertTrue($this->csrf->rejects(new Request('POST', '/logout', body: ['_token' => 'forged'])));
    }

    public function test_reading_requests_need_no_token(): void
    {
        self::assertFalse($this->csrf->rejects(new Request('GET', '/products')));
        self::assertFalse($this->csrf->rejects(new Request('HEAD', '/products')));
    }

    public function test_a_method_overridden_delete_without_a_token_is_rejected(): void
    {
        self::assertTrue($this->csrf->rejects(new Request('POST', '/products/1', body: ['_method' => 'DELETE'])));
    }

    public function test_the_json_api_is_exempt_because_it_authenticates_with_bearer_tokens(): void
    {
        self::assertFalse($this->csrf->rejects(new Request('POST', '/api/auth/login')));
    }

    public function test_rotating_invalidates_the_previous_token(): void
    {
        $old = $this->csrf->token();
        $this->csrf->rotate();

        self::assertNotSame($old, $this->csrf->token());
        self::assertTrue($this->csrf->rejects(new Request('POST', '/logout', body: ['_token' => $old])));
    }
}
