<?php

declare(strict_types=1);

namespace App\Core;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Attribute-driven router. Controllers declare routes with #[Route] on public
 * methods; path segments like {id} become action arguments, matched by name and
 * cast to the parameter's scalar type. A Request-typed parameter receives the request.
 */
final class Router
{
    /** @var list<array{method: string, path: string, pattern: string, class: class-string, action: string}> */
    private array $routes = [];

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * Registers every class in $directory (PSR-4 mapped to $namespace) that
     * declares at least one #[Route].
     */
    public function discover(string $directory, string $namespace): void
    {
        $files = glob(rtrim($directory, '/') . '/*.php') ?: [];
        sort($files);
        foreach ($files as $file) {
            $class = rtrim($namespace, '\\') . '\\' . basename($file, '.php');
            if (class_exists($class)) {
                $this->registerController($class);
            }
        }
    }

    /** @param class-string $class */
    public function registerController(string $class): void
    {
        $reflection = new ReflectionClass($class);
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(Route::class) as $attribute) {
                $route = $attribute->newInstance();
                $this->routes[] = [
                    'method' => $route->method,
                    'path' => $route->path,
                    'pattern' => self::compile($route->path),
                    'class' => $class,
                    'action' => $method->getName(),
                ];
            }
        }
    }

    public function dispatch(Request $request): Response
    {
        $method = self::effectiveMethod($request);
        $allowed = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['pattern'], $request->path, $matches)) {
                continue;
            }

            $params = array_filter($matches, is_string(...), ARRAY_FILTER_USE_KEY);
            $args = $this->arguments($route['class'], $route['action'], $params, $request);
            if ($args === null) {
                continue;
            }

            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }

            $controller = $this->container->get($route['class']);

            return $controller->{$route['action']}(...$args);
        }

        if ($allowed !== []) {
            return new Response('Method Not Allowed', 405, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Allow' => implode(', ', array_unique($allowed)),
            ]);
        }

        return new Response('Not Found', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    /** HTML forms can only POST; a hidden `_method` field upgrades them to PUT/PATCH/DELETE. */
    private static function effectiveMethod(Request $request): string
    {
        if ($request->method === 'POST') {
            $override = strtoupper((string) $request->input('_method', ''));
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }

        return $request->method;
    }

    private static function compile(string $path): string
    {
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}|[^{]+/',
            static fn (array $m) => isset($m[1]) && $m[1] !== ''
                ? '(?P<' . $m[1] . '>[^/]+)'
                : preg_quote($m[0], '#'),
            $path,
        );

        return '#^' . $regex . '/?$#';
    }

    /**
     * Builds the action's argument list, or null when a path parameter can't be
     * cast to the declared type (e.g. /products/abc for an int $id): the route
     * then doesn't match.
     *
     * @param array<string, string> $params
     * @return array<int, mixed>|null
     */
    private function arguments(string $class, string $action, array $params, Request $request): ?array
    {
        $args = [];
        foreach ((new ReflectionMethod($class, $action))->getParameters() as $parameter) {
            $type = $parameter->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;

            if ($typeName === Request::class) {
                $args[] = $request;
                continue;
            }

            $name = $parameter->getName();
            if (!array_key_exists($name, $params)) {
                $args[] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
                continue;
            }

            $value = rawurldecode($params[$name]);
            if ($typeName === 'int') {
                if (!preg_match('/^[1-9][0-9]{0,9}$/', $value)) {
                    return null;
                }
                $args[] = (int) $value;
            } else {
                $args[] = $value;
            }
        }

        return $args;
    }
}
