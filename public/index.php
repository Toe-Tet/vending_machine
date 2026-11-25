<?php

declare(strict_types=1);

use App\Api\Json;
use App\Auth\Csrf;
use App\Core\Config;
use App\Core\ErrorHandler;
use App\Core\NativeSession;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;

try {
    /** @var App\Core\Container $container */
    $container = require dirname(__DIR__) . '/bootstrap.php';
} catch (Throwable $e) {
    // Misconfigured: refuse to serve. Details go to the log, and to the browser only in local.
    error_log('Boot failed: ' . $e);
    http_response_code(500);
    echo getenv('APP_ENV') === 'local'
        ? '<!doctype html><title>Boot failed</title><pre>' . htmlspecialchars((string) $e) . '</pre>'
        : '<!doctype html><title>Unavailable</title><h1>Service unavailable</h1>';
    exit(1);
}

$config = $container->get(Config::class);
$errors = new ErrorHandler(!$config->isProduction(), $container->get(View::class));
$errors->register();

$request = null;
$isApi = static fn (Request $r): bool => $r->path === '/api' || str_starts_with($r->path, '/api/');

try {
    $request = Request::fromGlobals();

    $https = ($_SERVER['HTTPS'] ?? '') === 'on'
        || strtolower((string) $request->header('X-Forwarded-Proto')) === 'https';
    $container->set(Session::class, new NativeSession($config->secureCookies($https)));

    if ($container->get(Csrf::class)->rejects($request)) {
        $response = $container->get(View::class)->response('errors/csrf', ['title' => 'Form expired'], 403);
    } else {
        // API clients get JSON errors for unknown paths and methods too.
        $router = new Router($container, static fn (Request $r, array $allowed) => match (true) {
            !$isApi($r) => null,
            $allowed === [] => Json::notFound(),
            default => Json::error(405, 'method_not_allowed', 'Method not allowed.')->withHeader('Allow', implode(', ', $allowed)),
        });
        $router->discover(dirname(__DIR__) . '/src/Controllers', 'App\\Controllers');
        $router->discover(dirname(__DIR__) . '/src/Controllers/Api', 'App\\Controllers\\Api');
        $response = $router->dispatch($request);
    }
} catch (Throwable $e) {
    $response = $errors->render($e);
    if ($request !== null && $isApi($request)) {
        // Details are logged by render(); API clients get the envelope, never HTML.
        $response = Json::error(500, 'server_error', 'Something went wrong.');
    }
}

$response->send();
