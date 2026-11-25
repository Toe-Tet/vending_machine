<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Container;
use PHPUnit\Framework\TestCase;

final class ContainerTest extends TestCase
{
    public function test_autowires_constructor_dependencies(): void
    {
        $container = new Container();

        $service = $container->get(NeedsEngine::class);

        self::assertInstanceOf(NeedsEngine::class, $service);
        self::assertInstanceOf(Engine::class, $service->engine);
    }

    public function test_resolves_an_interface_to_its_bound_implementation(): void
    {
        $container = new Container();
        $container->bind(Clock::class, FixedClock::class);

        $service = $container->get(NeedsClock::class);

        self::assertInstanceOf(FixedClock::class, $service->clock);
    }

    public function test_returns_the_same_instance_on_every_get(): void
    {
        $container = new Container();

        self::assertSame($container->get(Engine::class), $container->get(Engine::class));
    }

    public function test_builds_a_registered_service_with_its_factory(): void
    {
        $container = new Container();
        $container->set(Clock::class, fn (Container $c) => new FixedClock());

        self::assertInstanceOf(FixedClock::class, $container->get(NeedsClock::class)->clock);
    }
}

interface Clock
{
}

final class FixedClock implements Clock
{
}

final class NeedsClock
{
    public function __construct(public readonly Clock $clock)
    {
    }
}

final class Engine
{
}

final class NeedsEngine
{
    public function __construct(public readonly Engine $engine)
    {
    }
}
