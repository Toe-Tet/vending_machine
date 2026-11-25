<?php
/** @var App\Models\Product $product */
/** @var App\Auth\Auth|null $auth */
/** @var string|null $csrfToken */
?>
<p class="back-link"><a href="/products">← Back to Products</a></p>

<article class="product-card">
    <header>
        <h1><?= e($product->name) ?></h1>
        <?php if ($product->isSoldOut()): ?>
            <span class="badge badge-out">Sold out</span>
        <?php else: ?>
            <span class="badge">In stock</span>
        <?php endif; ?>
    </header>

    <dl class="facts">
        <div>
            <dt>Unit Price (USD)</dt>
            <dd class="price"><?= e($product->displayPrice()) ?></dd>
        </div>
        <div>
            <dt>Quantity Available</dt>
            <dd><?= e($product->quantityAvailable) ?></dd>
        </div>
    </dl>

    <footer class="row-actions">
        <?php require __DIR__ . '/_buy.php'; ?>
        <?php if (isset($auth) && $auth->isAdmin()): ?>
            <a href="/products/<?= e($product->id) ?>/edit" role="button" class="secondary">Edit</a>
            <?php require __DIR__ . '/_delete.php'; ?>
        <?php endif; ?>
    </footer>
</article>
