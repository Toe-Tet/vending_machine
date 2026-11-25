<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers\Api;

use App\Auth\Credentials;
use App\Auth\JwtTokens;
use App\Controllers\Api\AuthController;
use App\Core\Request;
use App\Core\Response;
use App\Models\Role;
use App\Models\User;
use App\Repositories\UserRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class AuthControllerTest extends TestCase
{
    private const int NOW = 1_800_000_000;

    private static string $userHash;

    private UserRepository&Stub $users;
    private JwtTokens $tokens;
    private AuthController $controller;

    public static function setUpBeforeClass(): void
    {
        self::$userHash = password_hash('user12345', PASSWORD_BCRYPT, ['cost' => 4]);
    }

    protected function setUp(): void
    {
        $this->users = $this->createStub(UserRepository::class);
        $this->tokens = new JwtTokens('testing-secret', static fn (): int => self::NOW);
        $this->controller = new AuthController(new Credentials($this->users), $this->tokens);
    }

    public function test_the_right_password_gets_a_token_for_that_user(): void
    {
        $this->users->method('findByEmail')->willReturnMap([['user@example.com', $this->demoUser()]]);

        $response = $this->controller->login(self::login(['email' => 'User@Example.com ', 'password' => 'user12345']));
        $json = self::json($response);

        self::assertSame(200, $response->status);
        self::assertSame('2027-01-15T09:00:00+00:00', $json['expires_at']);
        self::assertSame(['id' => 2, 'name' => 'Demo User', 'email' => 'user@example.com', 'role' => 'user'], $json['user']);
        $claims = $this->tokens->verify($json['token']);
        self::assertSame(2, $claims?->userId);
        self::assertSame(Role::User, $claims->role);
    }

    public function test_a_wrong_password_or_unknown_email_is_401_without_a_token(): void
    {
        $this->users->method('findByEmail')->willReturnMap([['user@example.com', $this->demoUser()]]);

        foreach (['user@example.com', 'nobody@example.com'] as $email) {
            $response = $this->controller->login(self::login(['email' => $email, 'password' => 'nope-nope']));

            self::assertSame(401, $response->status);
            self::assertSame(
                ['error' => ['code' => 'invalid_credentials', 'message' => 'Incorrect email or password.', 'fields' => []]],
                self::json($response),
            );
        }
    }

    public function test_missing_credentials_are_422_with_a_message_per_field(): void
    {
        $response = $this->controller->login(self::login(['email' => '  ', 'password' => ['x']]));

        self::assertSame(422, $response->status);
        self::assertSame(
            ['email' => 'Enter your email.', 'password' => 'Enter your password.'],
            self::json($response)['error']['fields'],
        );
    }

    /** @return iterable<string, array{string}> */
    public static function malformedBodies(): iterable
    {
        yield 'empty' => [''];
        yield 'truncated' => ['{"email": "user@example.com"'];
        yield 'form-encoded' => ['email=user@example.com&password=user12345'];
        yield 'array, not object' => ['["user@example.com", "user12345"]'];
        yield 'string' => ['"hello"'];
    }

    #[DataProvider('malformedBodies')]
    public function test_a_body_that_is_not_a_json_object_is_400(string $content): void
    {
        $response = $this->controller->login(self::raw($content));

        self::assertSame(400, $response->status);
        self::assertSame('malformed_json', self::json($response)['error']['code']);
    }

    /** @param array<string, mixed> $body */
    private static function login(array $body): Request
    {
        return self::raw(json_encode($body, JSON_THROW_ON_ERROR));
    }

    private static function raw(string $content): Request
    {
        return new Request('POST', '/api/auth/login', headers: ['content-type' => 'application/json'], content: $content);
    }

    /** @return array<string, mixed> */
    private static function json(Response $response): array
    {
        self::assertSame('application/json', $response->headers['Content-Type']);

        return json_decode($response->body, true, flags: JSON_THROW_ON_ERROR);
    }

    private function demoUser(): User
    {
        return new User(2, 'Demo User', 'user@example.com', self::$userHash, Role::User);
    }
}
