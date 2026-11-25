<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Auth\Auth;
use App\Auth\Csrf;
use App\Auth\Guard;
use App\Core\ArraySession;
use App\Core\View;
use App\Models\Role;
use App\Models\User;
use PHPUnit\Framework\TestCase;

final class GuardTest extends TestCase
{
    private Auth $auth;
    private Guard $guard;

    protected function setUp(): void
    {
        $session = new ArraySession();
        $this->auth = new Auth($session, new Csrf($session));
        $this->guard = new Guard($this->auth, new View(dirname(__DIR__, 3) . '/templates'));
    }

    public function test_a_guest_asking_for_an_admin_page_is_sent_to_login(): void
    {
        $response = $this->guard->admin();

        self::assertSame(302, $response?->status);
        self::assertSame('/login', $response->headers['Location']);
    }

    public function test_a_user_asking_for_an_admin_page_is_forbidden(): void
    {
        $this->auth->login(self::user(Role::User));

        self::assertSame(403, $this->guard->admin()?->status);
    }

    public function test_an_admin_may_open_admin_pages(): void
    {
        $this->auth->login(self::user(Role::Admin));

        self::assertNull($this->guard->admin());
    }

    public function test_a_guest_asking_for_a_signed_in_page_is_sent_to_login(): void
    {
        self::assertSame('/login', $this->guard->signedIn()?->headers['Location']);
    }

    public function test_any_signed_in_person_may_open_signed_in_pages(): void
    {
        $this->auth->login(self::user(Role::User));

        self::assertNull($this->guard->signedIn());
    }

    public function test_a_signed_in_person_opening_a_guest_page_is_sent_to_products(): void
    {
        $this->auth->login(self::user(Role::User));

        self::assertSame('/products', $this->guard->guest()?->headers['Location']);
    }

    public function test_a_guest_may_open_guest_pages(): void
    {
        self::assertNull($this->guard->guest());
    }

    private static function user(Role $role): User
    {
        return new User(7, 'Pat', 'pat@example.com', 'hash', $role);
    }
}
