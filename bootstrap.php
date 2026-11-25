<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Container;
use App\Core\Database;
use App\Core\Env;
use App\Core\View;
use App\Repositories\PdoProductRepository;
use App\Repositories\ProductRepository;

/*
 * Shared boot for the web front controller and CLI scripts. Throws
 * ConfigurationException when the environment is unsafe (e.g. a weak
 * JWT_SECRET in production), so the app refuses to start.
 */

require __DIR__ . '/vendor/autoload.php';

Env::load(__DIR__ . '/.env');

$config = Config::fromEnvironment();

$container = new Container();
$container->set(Config::class, $config);
$container->set(Database::class, fn (Container $c) => Database::fromConfig($c->get(Config::class)));
$container->set(View::class, new View(__DIR__ . '/templates'));
$container->bind(ProductRepository::class, PdoProductRepository::class);

return $container;
