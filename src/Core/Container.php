<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/**
 * Constructor-injection container. Classes are autowired by their constructor's
 * type hints; interfaces are mapped with bind(); anything needing configuration
 * is registered with a factory via set(). Every service is shared (one instance).
 */
final class Container
{
    /** @var array<string, object> */
    private array $instances = [];

    /** @var array<string, Closure(self): object> */
    private array $factories = [];

    /** @var array<string, class-string> */
    private array $bindings = [];

    /** @var array<string, true> */
    private array $resolving = [];

    public function __construct()
    {
        $this->instances[self::class] = $this;
    }

    /** @param class-string $concrete */
    public function bind(string $abstract, string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    /** @param object|Closure(self): object $concrete an instance, or a factory receiving the container */
    public function set(string $id, object $concrete): void
    {
        unset($this->instances[$id]);
        if ($concrete instanceof Closure) {
            $this->factories[$id] = $concrete;
        } else {
            $this->instances[$id] = $concrete;
        }
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->resolving[$id])) {
            throw new RuntimeException("Circular dependency while resolving {$id}");
        }

        $this->resolving[$id] = true;
        try {
            $instance = match (true) {
                isset($this->factories[$id]) => ($this->factories[$id])($this),
                isset($this->bindings[$id]) => $this->get($this->bindings[$id]),
                default => $this->build($id),
            };
        } finally {
            unset($this->resolving[$id]);
        }

        return $this->instances[$id] = $instance;
    }

    private function build(string $class): object
    {
        if (!class_exists($class)) {
            throw new RuntimeException("Cannot resolve {$class}: no such class and nothing bound");
        }

        $reflection = new ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Cannot resolve {$class}: not instantiable and nothing bound");
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $args = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->get($type->getName());
            } elseif ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();
            } else {
                throw new RuntimeException(
                    "Cannot autowire \${$parameter->getName()} of {$class}; register a factory with set()",
                );
            }
        }

        return $reflection->newInstanceArgs($args);
    }
}
