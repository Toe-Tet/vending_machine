<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use RuntimeException;

/**
 * Renders plain-PHP templates from the templates directory. Templates escape
 * dynamic values with e(); the page is wrapped in a layout receiving $content.
 * Optional $globals supplies data every template sees (e.g. who is signed in);
 * it is called once per render, and the caller's data wins on a name clash.
 */
final readonly class View
{
    /** @param (Closure(): array<string, mixed>)|null $globals */
    public function __construct(private string $templatesDir, private ?Closure $globals = null)
    {
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        if ($this->globals !== null) {
            $data += ($this->globals)();
        }

        $content = $this->renderFile($template, $data);
        if ($layout === null) {
            return $content;
        }

        return $this->renderFile($layout, ['content' => $content] + $data);
    }

    /** @param array<string, mixed> $data */
    public function response(string $template, array $data = [], int $status = 200): Response
    {
        return new Response($this->render($template, $data), $status);
    }

    /** @param array<string, mixed> $data */
    private function renderFile(string $template, array $data): string
    {
        $file = $this->templatesDir . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Template not found: {$template}");
        }

        $render = static function (string $__file, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            try {
                require $__file;

                return (string) ob_get_contents();
            } finally {
                ob_end_clean();
            }
        };

        return $render($file, $data);
    }
}
