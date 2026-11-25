<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Env;
use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase
{
    private string $file;

    /** @var list<string> */
    private array $touched = ['ENVTEST_PLAIN', 'ENVTEST_QUOTED', 'ENVTEST_SINGLE', 'ENVTEST_REAL', 'ENVTEST_EMPTY'];

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'env');
        $this->clear();
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        $this->clear();
    }

    public function test_reads_key_value_lines_ignoring_blanks_and_comments_and_strips_quotes(): void
    {
        file_put_contents($this->file, <<<'ENV'
            # a comment

            ENVTEST_PLAIN=hello world
            ENVTEST_QUOTED="with # hash"
              ENVTEST_SINGLE = 'single'
            ENVTEST_EMPTY=
            ENV);

        Env::load($this->file);

        self::assertSame('hello world', Env::get('ENVTEST_PLAIN'));
        self::assertSame('with # hash', Env::get('ENVTEST_QUOTED'));
        self::assertSame('single', Env::get('ENVTEST_SINGLE'));
        self::assertSame('', Env::get('ENVTEST_EMPTY'));
    }

    public function test_real_environment_variables_take_precedence_over_the_file(): void
    {
        putenv('ENVTEST_REAL=from-real-env');
        file_put_contents($this->file, "ENVTEST_REAL=from-file\n");

        Env::load($this->file);

        self::assertSame('from-real-env', Env::get('ENVTEST_REAL'));
    }

    public function test_missing_file_is_silently_skipped(): void
    {
        putenv('ENVTEST_REAL=still-here');

        Env::load($this->file . '.does-not-exist');

        self::assertSame('still-here', Env::get('ENVTEST_REAL'));
    }

    public function test_unset_key_returns_default(): void
    {
        self::assertNull(Env::get('ENVTEST_PLAIN'));
        self::assertSame('fallback', Env::get('ENVTEST_PLAIN', 'fallback'));
    }

    private function clear(): void
    {
        foreach ($this->touched as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }
}
