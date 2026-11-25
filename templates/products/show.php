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
    <?php if (isset($auth) && $auth->isAdmin()): ?>
        <footer>
            <a href="/products/<?= e($product->id) ?>/edit" role="button" class="secondary">Edit</a>
            <?php require __DIR__ . '/_delete.php'; ?>
        </footer>
    <?php endif; ?>
</article>
<p><a href="/products">Back to Products</a></p>
