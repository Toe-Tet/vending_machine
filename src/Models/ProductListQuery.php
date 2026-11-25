<?php

declare(strict_types=1);

namespace App\Models;

/**
 * The Product list's sort, direction and page, read from the query string.
 * Unknown or malformed values fall back to name / asc / 1 (spec §5 Product list).
 */
final readonly class ProductListQuery
{
    public const int PER_PAGE = 10;

    public function __construct(
        public ProductSort $sort = ProductSort::Name,
        public SortDirection $dir = SortDirection::Asc,
        public int $page = 1,
    ) {
    }

    /** @param array<string, mixed> $query */
    public static function fromQuery(array $query): self
    {
        $sort = $query['sort'] ?? null;
        $dir = $query['dir'] ?? null;
        $page = $query['page'] ?? null;

        return new self(
            is_string($sort) ? ProductSort::tryFrom($sort) ?? ProductSort::Name : ProductSort::Name,
            is_string($dir) ? SortDirection::tryFrom($dir) ?? SortDirection::Asc : SortDirection::Asc,
            // An over-long page number saturates to PHP_INT_MAX; the controller clamps it to the last page.
            is_string($page) && preg_match('/^[1-9][0-9]*$/', $page) ? (int) $page : 1,
        );
    }

    /** The same query on another page, e.g. once the page is clamped to the last one. */
    public function onPage(int $page): self
    {
        return new self($this->sort, $this->dir, $page);
    }

    /** The page holding the last of $total Products (1 when there are none). */
    public static function lastPage(int $total): int
    {
        return max(1, intdiv($total + self::PER_PAGE - 1, self::PER_PAGE));
    }

    public function offset(): int
    {
        return ($this->page - 1) * self::PER_PAGE;
    }

    /** Link to $page of the list, keeping the current sort. */
    public function pageUrl(int $page): string
    {
        return self::url($this->sort, $this->dir, $page);
    }

    /**
     * Link for a column header: the current column flips direction, any other
     * column starts ascending. Re-sorting goes back to the first page.
     */
    public function sortUrl(ProductSort $sort): string
    {
        return self::url($sort, $sort === $this->sort ? $this->dir->toggle() : SortDirection::Asc, 1);
    }

    private static function url(ProductSort $sort, SortDirection $dir, int $page): string
    {
        return '/products?' . http_build_query(['sort' => $sort->value, 'dir' => $dir->value, 'page' => $page]);
    }
}
