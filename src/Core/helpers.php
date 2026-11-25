<?php

declare(strict_types=1);

/** HTML-escapes a value for output in templates. Use for every dynamic value. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}
