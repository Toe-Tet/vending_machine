<?php
/** @var App\Models\Product $product */
/** @var App\Auth\Auth|null $auth */
/** @var string|null $csrfToken */
?>
<article>
    <header><h1><?= e($product->name) ?></h1></header>
    <dl>
        <dt>Unit Price (USD)</dt>
        <dd><?= e($product->displayPrice()) ?></dd>
        <dt>Quantity Available</dt>
        <dd><?= $product->isSoldOut() ? '<mark>Sold out</mark>' : e($product->quantityAvailable) ?></dd>
    </dl>
    <footer>
        <?php require __DIR__ . '/_buy.php'; ?>
        <?php if (isset($auth) && $auth->isAdmin()): ?>
            <a href="/products/<?= e($product->id) ?>/edit" role="button" class="secondary">Edit</a>
            <?php require __DIR__ . '/_delete.php'; ?>
        <?php endif; ?>
    </footer>
</article>
<p><a href="/products">Back to Products</a></p>
