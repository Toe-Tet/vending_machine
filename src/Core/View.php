<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Renders plain-PHP templates from the templates directory. Templates escape
 * dynamic values with e(); the page is wrapped in a layout receiving $content.
 */
final readonly class View
{
    public function __construct(private string $templatesDir)
    {
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
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
