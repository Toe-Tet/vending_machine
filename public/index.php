<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Router;
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

try {
    $request = Request::fromGlobals();

    $https = ($_SERVER['HTTPS'] ?? '') === 'on'
        || strtolower((string) $request->header('X-Forwarded-Proto')) === 'https';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $config->secureCookies($https),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    $router = new Router($container);
    $router->discover(dirname(__DIR__) . '/src/Controllers', 'App\\Controllers');
    $response = $router->dispatch($request);
} catch (Throwable $e) {
    $response = $errors->render($e);
}

$response->send();
