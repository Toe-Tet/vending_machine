<?php

declare(strict_types=1);

use App\Auth\Auth;
use App\Auth\Csrf;
use App\Core\Config;
use App\Core\Container;
use App\Core\Database;
use App\Core\Env;
use App\Core\View;
use App\Repositories\PdoProductRepository;
use App\Repositories\PdoUserRepository;
use App\Repositories\ProductRepository;
use App\Repositories\UserRepository;

/*
 * Shared boot for the web front controller and CLI scripts. Throws
 * ConfigurationException when the environment is unsafe (e.g. a weak
 * JWT_SECRET in production), so the app refuses to start.
 *
 * App\Core\Session is bound by the caller: public/index.php uses NativeSession.
 */

require __DIR__ . '/vendor/autoload.php';

Env::load(__DIR__ . '/.env');

$config = Config::fromEnvironment();

$container = new Container();
$container->set(Config::class, $config);
$container->set(Database::class, fn (Container $c) => Database::fromConfig($c->get(Config::class)));
// Every template sees who is signed in and the CSRF token for its forms.
$container->set(View::class, fn (Container $c) => new View(__DIR__ . '/templates', static fn () => [
    'auth' => $c->get(Auth::class),
    'csrfToken' => $c->get(Csrf::class)->token(),
]));
$container->bind(ProductRepository::class, PdoProductRepository::class);
$container->bind(UserRepository::class, PdoUserRepository::class);

return $container;
