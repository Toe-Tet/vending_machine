<?php

declare(strict_types=1);

namespace App\Models;

enum SortDirection: string
{
    case Asc = 'asc';
    case Desc = 'desc';

    public function toggle(): self
    {
        return $this === self::Asc ? self::Desc : self::Asc;
    }
}
