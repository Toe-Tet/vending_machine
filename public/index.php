<?php

declare(strict_types=1);

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

try {
    $request = Request::fromGlobals();

    $https = ($_SERVER['HTTPS'] ?? '') === 'on'
        || strtolower((string) $request->header('X-Forwarded-Proto')) === 'https';
    $container->set(Session::class, new NativeSession($config->secureCookies($https)));

    if ($container->get(Csrf::class)->rejects($request)) {
        $response = $container->get(View::class)->response('errors/csrf', ['title' => 'Form expired'], 403);
    } else {
        $router = new Router($container);
        $router->discover(dirname(__DIR__) . '/src/Controllers', 'App\\Controllers');
        $response = $router->dispatch($request);
    }
} catch (Throwable $e) {
    $response = $errors->render($e);
}

$response->send();
