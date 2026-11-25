<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Auth\JwtTokens;
use App\Models\Role;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JwtTokensTest extends TestCase
{
    private const int NOW = 1_800_000_000;

    public function test_an_issued_token_verifies_as_its_user_and_role(): void
    {
        $tokens = self::tokens();

        $claims = $tokens->verify($tokens->issue(self::admin())->token);

        self::assertNotNull($claims);
        self::assertSame(1, $claims->userId);
        self::assertSame(Role::Admin, $claims->role);
    }

    public function test_a_token_lasts_one_hour(): void
    {
        $issued = self::tokens()->issue(self::admin());

        self::assertSame(self::NOW + 3600, $issued->expiresAt->getTimestamp());
        self::assertNotNull(self::tokens(now: self::NOW + 3599)->verify($issued->token));
        self::assertNull(self::tokens(now: self::NOW + 3600)->verify($issued->token));
    }

    /** @return iterable<string, array{string}> */
    public static function badTokens(): iterable
    {
        $userToken = self::tokens()->issue(new User(2, 'User', 'user@example.com', 'hash', Role::User))->token;
        [$header, , $signature] = explode('.', $userToken);
        $promoted = self::base64Url(json_encode(['sub' => '2', 'role' => 'admin', 'iat' => self::NOW, 'exp' => self::NOW + 3600]));

        yield 'empty' => [''];
        yield 'garbage' => ['not-a-jwt'];
        yield 'role edited without re-signing' => ["{$header}.{$promoted}.{$signature}"];
        yield 'signed with another secret' => [self::tokens('someone-elses-secret')->issue(self::admin())->token];
        yield 'alg none' => [self::base64Url('{"alg":"none","typ":"JWT"}') . ".{$promoted}."];
    }

    #[DataProvider('badTokens')]
    public function test_a_bad_token_is_rejected(string $token): void
    {
        self::assertNull(self::tokens()->verify($token));
    }

    private static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function tokens(string $secret = 'testing-secret', int $now = self::NOW): JwtTokens
    {
        return new JwtTokens($secret, static fn (): int => $now);
    }

    private static function admin(): User
    {
        return new User(1, 'Admin', 'admin@example.com', 'hash', Role::Admin);
    }
}
