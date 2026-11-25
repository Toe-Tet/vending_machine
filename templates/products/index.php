<?php
/** @var list<App\Models\Product> $products */
/** @var App\Models\ProductListQuery $query */
/** @var int $lastPage */
/** @var App\Auth\Auth|null $auth */
/** @var string|null $csrfToken */

use App\Models\ProductSort;
use App\Models\SortDirection;

$isAdmin = isset($auth) && $auth->isAdmin();
$header = static function (ProductSort $sort, string $label) use ($query): string {
    $arrow = '';
    $ariaSort = '';
    if ($sort === $query->sort) {
        $arrow = $query->dir === SortDirection::Asc ? ' ▲' : ' ▼';
        $ariaSort = ' aria-sort="' . ($query->dir === SortDirection::Asc ? 'ascending' : 'descending') . '"';
    }

    return '<th scope="col"' . $ariaSort . '><a href="' . e($query->sortUrl($sort)) . '">' . e($label) . $arrow . '</a></th>';
};
?>
<hgroup>
    <h1>Products</h1>
    <?php if ($isAdmin): ?>
        <p><a href="/products/create" role="button">New Product</a></p>
    <?php endif; ?>
</hgroup>

<?php if ($products === []): ?>
    <p>No products yet.</p>
<?php else: ?>
    <div class="overflow-auto">
        <table>
            <thead>
            <tr>
                <?= $header(ProductSort::Name, 'Name') ?>
                <?= $header(ProductSort::Price, 'Price (USD)') ?>
                <?= $header(ProductSort::Quantity, 'Quantity Available') ?>
                <th scope="col">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td><a href="/products/<?= e($product->id) ?>"><?= e($product->name) ?></a></td>
                    <td><?= e($product->displayPrice()) ?></td>
                    <td><?= $product->isSoldOut() ? '<mark>Sold out</mark>' : e($product->quantityAvailable) ?></td>
                    <td>
                        <?php require __DIR__ . '/_buy.php'; ?>
                        <?php if ($isAdmin): ?>
                            <a href="/products/<?= e($product->id) ?>/edit">Edit</a>
                            <?php require __DIR__ . '/_delete.php'; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($lastPage > 1): ?>
        <nav aria-label="Pagination">
            <ul>
                <li>
                    <?php if ($query->page > 1): ?>
                        <a href="<?= e($query->pageUrl($query->page - 1)) ?>" rel="prev">← Previous</a>
                    <?php endif; ?>
                </li>
            </ul>
            <ul><li>Page <?= e($query->page) ?> of <?= e($lastPage) ?></li></ul>
            <ul>
                <li>
                    <?php if ($query->page < $lastPage): ?>
                        <a href="<?= e($query->pageUrl($query->page + 1)) ?>" rel="next">Next →</a>
                    <?php endif; ?>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>
