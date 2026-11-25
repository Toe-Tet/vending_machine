<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Auth\Auth;
use App\Auth\Csrf;
use App\Auth\Guard;
use App\Controllers\AuthController;
use App\Core\ArraySession;
use App\Core\Request;
use App\Core\View;
use App\Models\Role;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Repositories\DuplicateEmailException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class AuthControllerTest extends TestCase
{
    private static string $userHash;

    private ArraySession $session;
    private Auth $auth;
    private UserRepository&Stub $users;
    private AuthController $controller;

    public static function setUpBeforeClass(): void
    {
        // Low cost keeps the suite fast; password_verify reads the cost from the hash.
        self::$userHash = password_hash('user12345', PASSWORD_BCRYPT, ['cost' => 4]);
    }

    protected function setUp(): void
    {
        $this->session = new ArraySession();
        $this->auth = new Auth($this->session, new Csrf($this->session));
        $this->users = $this->createStub(UserRepository::class);
        $view = new View(dirname(__DIR__, 3) . '/templates');
        $this->controller = new AuthController($this->users, $this->auth, new Guard($this->auth, $view), $view);
    }

    public function test_the_demo_user_signs_in_with_the_right_password(): void
    {
        $this->users->method('findByEmail')->willReturnMap([['user@example.com', $this->demoUser()]]);

        $response = $this->controller->login(self::post('/login', ['email' => 'user@example.com', 'password' => 'user12345']));

        self::assertSame('/products', $response->headers['Location']);
        self::assertSame(2, $this->auth->id());
        self::assertSame(Role::User, $this->auth->role());
    }

    public function test_signing_in_issues_a_new_session_id(): void
    {
        $this->users->method('findByEmail')->willReturn($this->demoUser());
        $before = $this->session->id();

        $this->controller->login(self::post('/login', ['email' => 'user@example.com', 'password' => 'user12345']));

        self::assertNotSame($before, $this->session->id());
    }

    public function test_a_wrong_password_re_renders_the_form_and_signs_no_one_in(): void
    {
        $this->users->method('findByEmail')->willReturn($this->demoUser());

        $response = $this->controller->login(self::post('/login', ['email' => 'user@example.com', 'password' => 'nope-nope']));

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Incorrect email or password.', $response->body);
        self::assertStringContainsString('value="user@example.com"', $response->body);
        self::assertFalse($this->auth->check());
    }

    public function test_an_unknown_email_gets_the_same_error_as_a_wrong_password(): void
    {
        $this->users->method('findByEmail')->willReturn(null);

        $response = $this->controller->login(self::post('/login', ['email' => 'who@example.com', 'password' => 'whatever1']));

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Incorrect email or password.', $response->body);
    }

    public function test_signing_out_forgets_the_user_and_issues_a_new_session_id(): void
    {
        $this->auth->login($this->demoUser());
        $before = $this->session->id();

        $response = $this->controller->logout();

        self::assertSame('/products', $response->headers['Location']);
        self::assertFalse($this->auth->check());
        self::assertNotSame($before, $this->session->id());
    }

    public function test_a_guest_signing_out_is_sent_to_login(): void
    {
        self::assertSame('/login', $this->controller->logout()->headers['Location']);
    }

    public function test_a_short_mismatched_password_reports_both_problems(): void
    {
        $response = $this->controller->register(self::post('/register', ['password' => 'short', 'password_confirmation' => 'other'] + self::validSignUp()));

        self::assertStringContainsString('Password must be at least 8 characters.', $response->body);
        self::assertStringContainsString(e('Passwords don’t match.'), $response->body);
    }

    public function test_a_signed_in_user_opening_the_login_page_is_sent_to_products(): void
    {
        $this->auth->login($this->demoUser());

        self::assertSame('/products', $this->controller->showLogin()->headers['Location']);
    }

    public function test_the_login_page_lists_the_demo_accounts(): void
    {
        $body = $this->controller->showLogin()->body;

        self::assertStringContainsString('Demo accounts', $body);
        self::assertStringContainsString('admin@example.com', $body);
        self::assertStringContainsString('user@example.com', $body);
    }

    public function test_signing_up_creates_a_user_and_signs_them_in(): void
    {
        $created = $this->recordCreatedUsers();

        $response = $this->controller->register(self::post('/register', self::validSignUp()));

        self::assertSame('/products', $response->headers['Location']);
        self::assertCount(1, $created);
        self::assertSame('Sam Lee', $created[0]['name']);
        self::assertSame('sam@example.com', $created[0]['email']);
        self::assertTrue(password_verify('correct horse', $created[0]['hash']));
        self::assertSame(Role::User, $created[0]['role']);
        self::assertSame(Role::User, $this->auth->role());
    }

    public function test_a_sign_up_asking_to_be_admin_still_gets_the_user_role(): void
    {
        $created = $this->recordCreatedUsers();

        $this->controller->register(self::post('/register', ['role' => 'admin'] + self::validSignUp()));

        self::assertSame(Role::User, $created[0]['role']);
        self::assertFalse($this->auth->isAdmin());
    }

    /** @return iterable<string, array{array<string, string>, string}> */
    public static function invalidSignUps(): iterable
    {
        yield 'missing name' => [['name' => '   '], 'Enter your name.'];
        yield 'name over 100 characters' => [['name' => str_repeat('é', 101)], 'Name must be 100 characters or fewer.'];
        yield 'missing email' => [['email' => ''], 'Enter a valid email address.'];
        yield 'invalid email' => [['email' => 'sam@'], 'Enter a valid email address.'];
        yield 'short password' => [['password' => 'short12', 'password_confirmation' => 'short12'], 'Password must be at least 8 characters.'];
        yield 'mismatched confirmation' => [['password_confirmation' => 'correct horsE'], 'Passwords don’t match.'];
    }

    /** @param array<string, string> $override */
    #[DataProvider('invalidSignUps')]
    public function test_an_invalid_sign_up_shows_the_problem_inline_and_creates_nothing(array $override, string $message): void
    {
        $created = $this->recordCreatedUsers();

        $response = $this->controller->register(self::post('/register', $override + self::validSignUp()));

        self::assertSame(422, $response->status);
        self::assertStringContainsString(e($message), $response->body);
        self::assertCount(0, $created);
        self::assertFalse($this->auth->check());
    }

    public function test_an_invalid_sign_up_keeps_name_and_email_but_never_echoes_the_password(): void
    {
        $response = $this->controller->register(self::post('/register', ['password_confirmation' => 'other'] + self::validSignUp()));

        self::assertStringContainsString('value="Sam Lee"', $response->body);
        self::assertStringContainsString('value="sam@example.com"', $response->body);
        self::assertStringNotContainsString('correct horse', $response->body);
    }

    public function test_signing_up_with_a_registered_email_is_rejected(): void
    {
        $this->users->method('findByEmail')->willReturnMap([['sam@example.com', $this->demoUser()]]);
        $created = $this->recordCreatedUsers();

        $response = $this->controller->register(self::post('/register', self::validSignUp()));

        self::assertSame(422, $response->status);
        self::assertStringContainsString('That email is already registered.', $response->body);
        self::assertCount(0, $created);
    }

    public function test_an_email_registered_concurrently_is_rejected_the_same_way(): void
    {
        $this->users->method('findByEmail')->willReturn(null);
        $this->users->method('create')->willThrowException(new DuplicateEmailException());

        $response = $this->controller->register(self::post('/register', self::validSignUp()));

        self::assertSame(422, $response->status);
        self::assertStringContainsString('That email is already registered.', $response->body);
        self::assertFalse($this->auth->check());
    }

    /**
     * Stubs UserRepository::create() and returns a live list of its calls.
     *
     * @return \ArrayObject<int, array{name: string, email: string, hash: string, role: Role}>
     */
    private function recordCreatedUsers(): \ArrayObject
    {
        $calls = new \ArrayObject();
        $this->users->method('create')->willReturnCallback(
            function (string $name, string $email, string $hash, Role $role = Role::User) use ($calls): User {
                $calls[] = ['name' => $name, 'email' => $email, 'hash' => $hash, 'role' => $role];

                return new User(10 + count($calls), $name, $email, $hash, $role);
            },
        );

        return $calls;
    }

    /** @return array<string, string> */
    private static function validSignUp(): array
    {
        return [
            'name' => '  Sam Lee ',
            'email' => ' Sam@Example.com',
            'password' => 'correct horse',
            'password_confirmation' => 'correct horse',
        ];
    }

    /** @param array<string, string> $body */
    private static function post(string $path, array $body): Request
    {
        return new Request('POST', $path, body: $body);
    }

    private function demoUser(): User
    {
        return new User(2, 'Demo User', 'user@example.com', self::$userHash, Role::User);
    }
}
