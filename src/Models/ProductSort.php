<?php

declare(strict_types=1);

namespace App\Models;

/** Columns the Product list can be sorted by; the value is the `sort` query parameter. */
enum ProductSort: string
{
    case Name = 'name';
    case Price = 'price';
    case Quantity = 'quantity';
}
