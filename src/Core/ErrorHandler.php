<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

/**
 * Turns uncaught errors into a 500 response. Outside production the details are
 * shown; in production visitors get a generic page and the details go to the log.
 */
final readonly class ErrorHandler
{
    public function __construct(private bool $showDetails, private ?View $view = null)
    {
    }

    public function register(): void
    {
        ini_set('display_errors', $this->showDetails ? '1' : '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
    }

    public function render(Throwable $e): Response
    {
        error_log((string) $e);

        if ($this->showDetails) {
            return new Response(
                '<!doctype html><title>Error</title><pre>' . e((string) $e) . '</pre>',
                500,
            );
        }

        if ($this->view !== null) {
            try {
                return $this->view->response('errors/500', ['title' => 'Something went wrong'], 500);
            } catch (Throwable) {
                // fall through to the bare page
            }
        }

        return new Response('<!doctype html><title>Error</title><h1>Something went wrong</h1>', 500);
    }
}
